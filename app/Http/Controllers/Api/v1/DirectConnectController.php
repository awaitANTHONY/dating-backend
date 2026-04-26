<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\ContactPlatform;
use App\Models\CoinTransaction;
use App\Models\DirectConnectRequest;
use App\Models\Setting;
use App\Models\User;
use App\Models\UserContact;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DirectConnectController extends Controller
{
    // ─────────────────────────────────────────────
    // GET /api/v1/direct-connect/received
    // Returns pending (and historical) requests where auth user is the owner
    // ─────────────────────────────────────────────
    public function received(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $requests = DirectConnectRequest::with([
                'requester',
                'platform',
            ])
            ->where('owner_id', $userId)
            ->orderByRaw("FIELD(status,'pending','approved','rejected')")
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn ($r) => $this->formatRequest($r, 'received'));

        return response()->json(['status' => true, 'data' => $requests]);
    }

    // ─────────────────────────────────────────────
    // GET /api/v1/direct-connect/sent
    // Returns requests the auth user has sent
    // ─────────────────────────────────────────────
    public function sent(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $requests = DirectConnectRequest::with([
                'owner',
                'platform',
            ])
            ->where('requester_id', $userId)
            ->orderByRaw("FIELD(status,'pending','approved','rejected')")
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($r) {
                $data = $this->formatRequest($r, 'sent');

                // Reveal contact value only when approved
                if ($r->status === 'approved' && $r->owner_id) {
                    $contact = UserContact::with('platform')
                        ->where('user_id', $r->owner_id)
                        ->where(function ($q) use ($r) {
                            if ($r->platform_id) {
                                $q->where('contact_platform_id', $r->platform_id);
                            }
                        })
                        ->first();

                    if ($contact) {
                        $data['contact_value']  = $contact->value;
                        $data['contacts']        = [$this->formatContact($contact)];
                    }
                }

                return $data;
            });

        return response()->json(['status' => true, 'data' => $requests]);
    }

    // ─────────────────────────────────────────────
    // POST /api/v1/direct-connect/request
    // Body: { owner_id, platform_id }
    // ─────────────────────────────────────────────
    public function request(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'owner_id'    => 'required|integer|exists:users,id',
            'platform_id' => 'nullable|integer|exists:contact_platforms,id',
        ]);

        $userId  = $request->user()->id;
        $ownerId = (int) $validated['owner_id'];

        if ($userId === $ownerId) {
            return response()->json(['status' => false, 'message' => 'You cannot request your own contact.'], 400);
        }

        // Prevent duplicate pending request
        $existing = DirectConnectRequest::where('requester_id', $userId)
            ->where('owner_id', $ownerId)
            ->whereIn('status', ['pending', 'approved'])
            ->first();

        if ($existing) {
            $msg = $existing->status === 'approved'
                ? 'Contact already shared.'
                : 'You already have a pending request for this user.';
            return response()->json(['status' => false, 'message' => $msg], 409);
        }

        // Determine coin cost from settings
        $coinCost    = $this->getCoinCost($request->user());
        $freeLeft    = $this->getFreeRequestsLeft($request->user());
        $coinsToSpend = 0;

        if ($freeLeft <= 0) {
            // Must spend coins
            $balance = (int) $request->user()->coin_balance;
            if ($balance < $coinCost) {
                return response()->json([
                    'status'  => false,
                    'message' => 'Not enough coins. Please purchase more.',
                    'code'    => 'insufficient_coins',
                ], 402);
            }
            $coinsToSpend = $coinCost;
        }

        try {
            DB::beginTransaction();

            // Deduct coins if needed
            if ($coinsToSpend > 0) {
                User::where('id', $userId)->decrement('coin_balance', $coinsToSpend);
                CoinTransaction::create([
                    'user_id' => $userId,
                    'amount'  => $coinsToSpend,
                    'status'  => 'Debit',
                ]);
            }

            $expiryHours = (int) (Setting::where('name', 'dc_request_expiry_hours')->value('value') ?? 72);

            $dcRequest = DirectConnectRequest::create([
                'requester_id' => $userId,
                'owner_id'     => $ownerId,
                'platform_id'  => $validated['platform_id'] ?? null,
                'status'       => 'pending',
                'coins_spent'  => $coinsToSpend,
                'expires_at'   => now()->addHours($expiryHours),
            ]);

            DB::commit();

            return response()->json([
                'status'  => true,
                'message' => 'Contact request sent.',
                'data'    => $this->formatRequest($dcRequest->fresh(['owner', 'platform']), 'sent'),
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['status' => false, 'message' => 'Failed to send request.'], 500);
        }
    }

    // ─────────────────────────────────────────────
    // POST /api/v1/direct-connect/respond
    // Body: { request_id, action }  action = 'approve' | 'reject'
    // ─────────────────────────────────────────────
    public function respond(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'request_id' => 'required|integer|exists:direct_connect_requests,id',
            'action'     => 'required|in:approve,reject',
        ]);

        $userId    = $request->user()->id;
        $dcRequest = DirectConnectRequest::find($validated['request_id']);

        if (!$dcRequest || $dcRequest->owner_id !== $userId) {
            return response()->json(['status' => false, 'message' => 'Request not found.'], 404);
        }

        if ($dcRequest->status !== 'pending') {
            return response()->json(['status' => false, 'message' => 'Request already responded to.'], 409);
        }

        $newStatus = $validated['action'] === 'approve' ? 'approved' : 'rejected';

        $dcRequest->update([
            'status'       => $newStatus,
            'responded_at' => now(),
        ]);

        // If rejected, refund coins to requester
        if ($newStatus === 'rejected' && $dcRequest->coins_spent > 0) {
            User::where('id', $dcRequest->requester_id)->increment('coin_balance', $dcRequest->coins_spent);
            CoinTransaction::create([
                'user_id' => $dcRequest->requester_id,
                'amount'  => $dcRequest->coins_spent,
                'status'  => 'Credit',
            ]);
        }

        return response()->json([
            'status'  => true,
            'message' => $newStatus === 'approved' ? 'Contact request approved.' : 'Contact request rejected.',
            'data'    => $this->formatRequest($dcRequest->fresh(['requester', 'platform']), 'received'),
        ]);
    }

    // ─────────────────────────────────────────────
    // GET /api/v1/direct-connect/platforms
    // Returns available contact platforms
    // ─────────────────────────────────────────────
    public function platforms(): JsonResponse
    {
        $platforms = ContactPlatform::where('status', true)
            ->orderBy('sort_order')
            ->get(['id', 'name', 'icon', 'placeholder', 'sort_order']);

        return response()->json(['status' => true, 'data' => $platforms]);
    }

    // ─────────────────────────────────────────────
    // GET /api/v1/user-contacts
    // Returns auth user's saved contact platforms
    // ─────────────────────────────────────────────
    public function myContacts(Request $request): JsonResponse
    {
        $contacts = UserContact::with('platform')
            ->where('user_id', $request->user()->id)
            ->get()
            ->map(fn ($c) => $this->formatContact($c));

        return response()->json(['status' => true, 'data' => $contacts]);
    }

    // ─────────────────────────────────────────────
    // POST /api/v1/user-contacts
    // Body: { platform_id, value }
    // ─────────────────────────────────────────────
    public function saveContact(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'platform_id' => 'required|integer|exists:contact_platforms,id',
            'value'       => 'required|string|max:255',
        ]);

        $contact = UserContact::updateOrCreate(
            ['user_id' => $request->user()->id, 'contact_platform_id' => $validated['platform_id']],
            ['value'   => trim($validated['value'])]
        );

        return response()->json([
            'status'  => true,
            'message' => 'Contact saved.',
            'data'    => $this->formatContact($contact->load('platform')),
        ]);
    }

    // ─────────────────────────────────────────────
    // DELETE /api/v1/user-contacts/{id}
    // ─────────────────────────────────────────────
    public function deleteContact(Request $request, int $id): JsonResponse
    {
        $deleted = UserContact::where('user_id', $request->user()->id)->where('id', $id)->delete();

        return response()->json(['status' => (bool) $deleted, 'message' => $deleted ? 'Deleted.' : 'Not found.']);
    }

    // ─────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────

    private function formatRequest(DirectConnectRequest $r, string $perspective): array
    {
        $other = $perspective === 'received' ? $r->requester : $r->owner;

        return [
            'id'           => $r->id,
            'requester_id' => $r->requester_id,
            'owner_id'     => $r->owner_id,
            'status'       => $r->status,
            'coins_spent'  => $r->coins_spent,
            'responded_at' => $r->responded_at?->toIso8601String(),
            'expires_at'   => $r->expires_at?->toIso8601String(),
            'created_at'   => $r->created_at?->toIso8601String(),
            'platform'     => $r->platform ? [
                'id'   => $r->platform->id,
                'name' => $r->platform->name,
                'icon' => $r->platform->icon,
            ] : null,
            $perspective === 'received' ? 'requester' : 'owner' => $other ? [
                'id'         => $other->id,
                'name'       => $other->name,
                'image'      => $other->image,
                'is_online'  => $other->last_activity && $other->last_activity->diffInHours(now()) <= 3,
            ] : null,
        ];
    }

    private function formatContact(UserContact $c): array
    {
        return [
            'id'                  => $c->id,
            'user_id'             => $c->user_id,
            'contact_platform_id' => $c->contact_platform_id,
            'value'               => $c->value,
            'platform'            => $c->platform ? [
                'id'          => $c->platform->id,
                'name'        => $c->platform->name,
                'icon'        => $c->platform->icon,
                'placeholder' => $c->platform->placeholder,
                'sort_order'  => $c->platform->sort_order,
            ] : null,
        ];
    }

    private function getCoinCost(User $user): int
    {
        $isVip = $user->isVipActive();
        $key   = $isVip ? 'dc_coin_cost_vip' : 'dc_coin_cost_default';
        return (int) (Setting::where('name', $key)->value('value') ?? ($isVip ? 3 : 5));
    }

    private function getFreeRequestsLeft(User $user): int
    {
        $tier = $this->getUserTier($user);
        $key  = "dc_free_requests_{$tier}";
        $daily = (int) (Setting::where('name', $key)->value('value') ?? 0);

        if ($daily <= 0) return 0;

        $usedToday = DirectConnectRequest::where('requester_id', $user->id)
            ->whereDate('created_at', today())
            ->count();

        return max(0, $daily - $usedToday);
    }

    private function getUserTier(User $user): string
    {
        if ($user->isVipActive()) return 'vip';

        $sub = $user->subscription;
        if (!$sub || $sub->id === 0) return 'free';

        $name = strtolower($sub->name ?? '');
        if (str_contains($name, 'gold')) return 'gold';
        if (str_contains($name, 'premium') || str_contains($name, 'plus')) return 'premium';

        return 'free';
    }
}

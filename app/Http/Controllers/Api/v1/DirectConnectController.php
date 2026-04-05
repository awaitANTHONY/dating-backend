<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\ContactPlatform;
use App\Models\UserContact;
use App\Models\ContactRequest;
use App\Models\CoinTransaction;
use App\Models\Package;
use App\Models\User;
use App\Services\RevenueCatService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use App\Models\Payment;

class DirectConnectController extends Controller
{
    private RevenueCatService $revenueCat;

    public function __construct()
    {
        $this->revenueCat = new RevenueCatService();
    }

    // ─── Contact Platforms ───────────────────────────────────────────────────

    public function platforms()
    {
        $platforms = ContactPlatform::getActivePlatforms();
        return response()->json(['status' => true, 'data' => $platforms]);
    }

    // ─── My Contacts (Owner CRUD) ────────────────────────────────────────────

    public function myContacts(Request $request)
    {
        $user = $request->user();
        $contacts = UserContact::where('user_id', $user->id)
            ->where('status', true)
            ->with('platform')
            ->get();

        return response()->json(['status' => true, 'data' => $contacts]);
    }

    public function storeContact(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'contact_platform_id' => 'required|exists:contact_platforms,id',
            'value' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => $validator->errors()->first()]);
        }

        $user = $request->user();

        // Check subscription tier
        $tierLimit = $this->getContactLimit($user);
        if ($tierLimit === 0) {
            return response()->json([
                'status' => false,
                'message' => 'Upgrade to Premium to add your contacts.',
            ]);
        }

        // Check current contact count
        $currentCount = UserContact::where('user_id', $user->id)->where('status', true)->count();

        // Check if updating existing or adding new
        $existing = UserContact::where('user_id', $user->id)
            ->where('contact_platform_id', $request->contact_platform_id)
            ->first();

        if (!$existing && $currentCount >= $tierLimit) {
            return response()->json([
                'status' => false,
                'message' => "Your plan allows up to {$tierLimit} contacts. Upgrade for more.",
            ]);
        }

        $contact = UserContact::updateOrCreate(
            [
                'user_id' => $user->id,
                'contact_platform_id' => $request->contact_platform_id,
            ],
            [
                'value' => $request->value,
                'status' => true,
            ]
        );

        $contact->load('platform');

        return response()->json([
            'status' => true,
            'message' => 'Contact saved.',
            'data' => $contact,
        ]);
    }

    public function deleteContact(Request $request, $id)
    {
        $user = $request->user();
        $contact = UserContact::where('id', $id)->where('user_id', $user->id)->first();

        if (!$contact) {
            return response()->json(['status' => false, 'message' => 'Contact not found.']);
        }

        $contact->delete();

        return response()->json(['status' => true, 'message' => 'Contact removed.']);
    }

    // ─── User Contact Availability (Public check — no values exposed) ────────

    public function userContactInfo(Request $request, $id)
    {
        $user = User::find($id);
        if (!$user) {
            return response()->json(['status' => false, 'message' => 'User not found.']);
        }

        $hasContacts = UserContact::where('user_id', $id)->where('status', true)->exists();
        $contactCount = UserContact::where('user_id', $id)->where('status', true)->count();

        // Check if requester already has an approved request
        $currentUser = $request->user();
        $existingRequest = ContactRequest::where('requester_id', $currentUser->id)
            ->where('owner_id', $id)
            ->where('status', 'approved')
            ->first();

        $pendingRequest = ContactRequest::where('requester_id', $currentUser->id)
            ->where('owner_id', $id)
            ->where('status', 'pending')
            ->first();

        $data = [
            'has_contacts' => $hasContacts,
            'contact_count' => $contactCount,
            'already_connected' => $existingRequest !== null,
            'pending_request' => $pendingRequest !== null,
            'last_active' => $user->updated_at ? $user->updated_at->diffForHumans() : null,
        ];

        // If already connected, include the actual contacts
        if ($existingRequest) {
            $contacts = UserContact::where('user_id', $id)
                ->where('status', true)
                ->with('platform')
                ->get();
            $data['contacts'] = $contacts;
        }

        return response()->json(['status' => true, 'data' => $data]);
    }

    // ─── Send Contact Request ────────────────────────────────────────────────

    public function sendRequest(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'owner_id' => 'required|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => $validator->errors()->first()]);
        }

        $user = $request->user();
        $ownerId = $request->owner_id;

        // Can't request yourself
        if ($user->id == $ownerId) {
            return response()->json(['status' => false, 'message' => 'Cannot request your own contacts.']);
        }

        // Check if already connected
        $existing = ContactRequest::where('requester_id', $user->id)
            ->where('owner_id', $ownerId)
            ->whereIn('status', ['approved', 'pending'])
            ->first();

        if ($existing) {
            $msg = $existing->status === 'approved' ? 'You are already connected.' : 'You already have a pending request.';
            return response()->json(['status' => false, 'message' => $msg]);
        }

        // Check owner has contacts
        $ownerHasContacts = UserContact::where('user_id', $ownerId)->where('status', true)->exists();
        if (!$ownerHasContacts) {
            return response()->json(['status' => false, 'message' => 'This user has no contacts available.']);
        }

        // Check if blocked
        $isBlocked = DB::table('user_blocks')
            ->where('blocker_id', $ownerId)
            ->where('blocked_id', $user->id)
            ->exists();
        if ($isBlocked) {
            return response()->json(['status' => false, 'message' => 'Cannot send request to this user.']);
        }

        // Calculate cost
        $freeRemaining = $this->getFreeRequestsRemaining($user);
        $cost = $freeRemaining > 0 ? 0 : $this->getRequestCost($user);

        // Check balance
        $balance = (int) $user->coin_balance;
        if ($cost > 0 && $balance < $cost) {
            return response()->json([
                'status' => false,
                'message' => "Not enough coins. You need {$cost} coins.",
                'data' => ['balance' => $balance, 'cost' => $cost],
            ]);
        }

        DB::beginTransaction();
        try {
            // Debit coins
            if ($cost > 0) {
                $user->coin_balance = $balance - $cost;
                $user->save();

                CoinTransaction::create([
                    'user_id' => $user->id,
                    'amount' => $cost,
                    'status' => 'Debit',
                    'description' => 'Direct Connect request',
                    'reference_type' => 'contact_request',
                ]);
            }

            // Create request
            $contactRequest = ContactRequest::create([
                'requester_id' => $user->id,
                'owner_id' => $ownerId,
                'status' => 'pending',
                'coins_spent' => $cost,
                'expires_at' => now()->addDays(7),
            ]);

            DB::commit();

            // Send push notification to owner
            $this->notifyOwner($contactRequest, $user);

            return response()->json([
                'status' => true,
                'message' => 'Request sent! The user will be notified.',
                'data' => [
                    'request_id' => $contactRequest->id,
                    'coins_spent' => $cost,
                    'balance' => (int) $user->coin_balance,
                    'free_remaining' => max(0, $freeRemaining - ($cost === 0 ? 1 : 0)),
                ],
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('DirectConnect request failed', ['error' => $e->getMessage()]);
            return response()->json(['status' => false, 'message' => 'Something went wrong. Please try again.']);
        }
    }

    // ─── Respond to Request (Owner approves/rejects) ─────────────────────────

    public function respondToRequest(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'request_id' => 'required|exists:contact_requests,id',
            'action' => 'required|in:approve,reject',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => $validator->errors()->first()]);
        }

        $user = $request->user();
        $contactRequest = ContactRequest::where('id', $request->request_id)
            ->where('owner_id', $user->id)
            ->where('status', 'pending')
            ->first();

        if (!$contactRequest) {
            return response()->json(['status' => false, 'message' => 'Request not found or already handled.']);
        }

        $action = $request->action;

        if ($action === 'approve') {
            $contactRequest->status = 'approved';
            $contactRequest->responded_at = now();
            $contactRequest->save();

            // Get owner's contacts to include in notification
            $contacts = UserContact::where('user_id', $user->id)
                ->where('status', true)
                ->with('platform')
                ->get();

            // Notify requester
            $this->notifyRequesterApproved($contactRequest, $user);

            return response()->json([
                'status' => true,
                'message' => 'Request approved. Your contacts have been shared.',
                'data' => ['contacts_shared' => $contacts->count()],
            ]);
        } else {
            $contactRequest->status = 'rejected';
            $contactRequest->responded_at = now();
            $contactRequest->save();

            // No refund — notify requester
            $this->notifyRequesterRejected($contactRequest);

            return response()->json([
                'status' => true,
                'message' => 'Request declined.',
            ]);
        }
    }

    // ─── Sent Requests ───────────────────────────────────────────────────────

    public function sentRequests(Request $request)
    {
        $user = $request->user();
        $requests = ContactRequest::where('requester_id', $user->id)
            ->with(['owner:id,name,image,updated_at', 'platform'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        // For approved requests, include the owner's contacts
        $requests->getCollection()->transform(function ($req) {
            if ($req->status === 'approved') {
                $req->contacts = UserContact::where('user_id', $req->owner_id)
                    ->where('status', true)
                    ->with('platform')
                    ->get();
            }
            return $req;
        });

        return response()->json(['status' => true, 'data' => $requests]);
    }

    // ─── Received Requests ───────────────────────────────────────────────────

    public function receivedRequests(Request $request)
    {
        $user = $request->user();
        $requests = ContactRequest::where('owner_id', $user->id)
            ->where('status', 'pending')
            ->with(['requester:id,name,image,updated_at'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json(['status' => true, 'data' => $requests]);
    }

    // ─── Coin Packages ───────────────────────────────────────────────────────

    public function coinPackages()
    {
        $packages = Package::where('status', true)->orderBy('coins')->get();
        return response()->json(['status' => true, 'data' => $packages]);
    }

    // ─── Purchase Coins (RevenueCat verified) ────────────────────────────────

    public function purchaseCoins(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'product_id' => 'required|string',
            'platform' => 'required|string|in:ios,android',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => $validator->errors()->first()]);
        }

        $user = $request->user();
        $rcUserId = (string) $user->id;
        $this->revenueCat->clearCache($rcUserId);

        // Verify the coin purchase exists in RevenueCat
        $purchase = $this->revenueCat->findNonSubscriptionPurchase($rcUserId, $request->product_id);

        if (!$purchase) {
            Log::warning('COIN_PURCHASE_REJECTED: No purchase found in RevenueCat', [
                'user_id' => $user->id,
                'product_id' => $request->product_id,
            ]);
            return response()->json([
                'status' => false,
                'message' => 'Purchase could not be verified. Please try again or contact support.',
            ], 422);
        }

        // Build transaction key and prevent duplicate processing
        $transactionKey = ($purchase['id'] ?? '') . '_' . ($purchase['purchase_date'] ?? '');
        if (Payment::where('transaction_id', $transactionKey)->exists()) {
            return response()->json([
                'status' => true,
                'message' => 'Coins already credited.',
                'data' => ['balance' => (int) $user->coin_balance],
            ]);
        }

        // Find package by product_id
        $package = Package::where('product_id', $request->product_id)->where('status', true)->first();
        if (!$package) {
            return response()->json(['status' => false, 'message' => 'Invalid coin package.']);
        }

        DB::beginTransaction();
        try {
            // Credit coins
            $user->coin_balance = (int) $user->coin_balance + $package->coins;
            $user->save();

            // Record coin transaction
            CoinTransaction::create([
                'user_id' => $user->id,
                'amount' => $package->coins,
                'status' => 'Credit',
                'description' => "Purchased {$package->coins} coins",
                'reference_type' => 'purchase',
            ]);

            // Record payment
            $payment = new Payment();
            $payment->user_id = $user->id;
            $payment->title = "{$package->coins} Coins Pack";
            $payment->date = now();
            $payment->amount = $package->amount;
            $payment->platform = $request->platform;
            $payment->transaction_id = $transactionKey;
            $payment->original_transaction_id = $transactionKey;
            $payment->payment_type = 'coins';
            $payment->save();

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => "{$package->coins} coins added to your balance!",
                'data' => ['balance' => (int) $user->coin_balance, 'coins_added' => $package->coins],
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Coin purchase failed', ['error' => $e->getMessage()]);
            return response()->json(['status' => false, 'message' => 'Something went wrong. Please try again.']);
        }
    }

    // ─── Coin Balance ────────────────────────────────────────────────────────

    public function coinBalance(Request $request)
    {
        $user = $request->user();
        $freeRemaining = $this->getFreeRequestsRemaining($user);

        return response()->json([
            'status' => true,
            'data' => [
                'balance' => (int) $user->coin_balance,
                'free_requests_remaining' => $freeRemaining,
            ],
        ]);
    }

    // ─── Private Helpers ─────────────────────────────────────────────────────

    private function getContactLimit(User $user): int
    {
        if ($user->isVipActive()) return (int) get_option('dc_contact_limit_vip', '3');

        $tier = $this->getSubscriptionTier($user);
        if ($tier === 'gold') return (int) get_option('dc_contact_limit_gold', '4');
        if ($tier === 'premium') return (int) get_option('dc_contact_limit_premium', '2');

        return (int) get_option('dc_contact_limit_free', '0');
    }

    private function getSubscriptionTier(User $user): ?string
    {
        // Must have a real subscription_id and not be expired
        if (!$user->subscription_id || $user->subscription_id == 0) return null;
        if ($user->expired_at && \Carbon\Carbon::parse($user->expired_at)->isPast()) return null;

        $subName = strtolower($user->subscription->name ?? '');
        $productId = strtolower($user->subscription->product_id ?? '');

        if (str_contains($subName, 'gold') || str_contains($productId, 'gold')) return 'gold';
        if (str_contains($subName, 'premium') || str_contains($productId, 'premium')) return 'premium';

        // Any active subscription is at least premium
        return 'premium';
    }

    private function getFreeRequestsRemaining(User $user): int
    {
        $dailyLimit = $this->getDailyFreeRequests($user);
        if ($dailyLimit === 0) return 0;

        $todayCount = ContactRequest::where('requester_id', $user->id)
            ->where('coins_spent', 0)
            ->whereDate('created_at', today())
            ->count();

        return max(0, $dailyLimit - $todayCount);
    }

    private function getDailyFreeRequests(User $user): int
    {
        if ($user->isVipActive()) return (int) get_option('dc_free_requests_vip', '10');

        $tier = $this->getSubscriptionTier($user);
        if ($tier === 'gold') return (int) get_option('dc_free_requests_gold', '5');
        if ($tier === 'premium') return (int) get_option('dc_free_requests_premium', '3');

        return (int) get_option('dc_free_requests_free', '0');
    }

    private function getRequestCost(User $user): int
    {
        return $user->isVipActive()
            ? (int) get_option('dc_coin_cost_vip', '3')
            : (int) get_option('dc_coin_cost_default', '5');
    }

    private function notifyOwner(ContactRequest $contactRequest, User $requester)
    {
        try {
            $owner = User::find($contactRequest->owner_id);
            if ($owner && $owner->device_token) {
                send_notification(
                    'single',
                    'Direct Connect Request',
                    "{$requester->name} wants to connect with you!",
                    $requester->image,
                    [
                        'device_token' => $owner->device_token,
                        'type' => 'direct_connect_request',
                        'request_id' => (string) $contactRequest->id,
                        'requester_id' => (string) $requester->id,
                    ]
                );
            }
        } catch (\Exception $e) {
            Log::error('DirectConnect notification failed', ['error' => $e->getMessage()]);
        }
    }

    private function notifyRequesterApproved(ContactRequest $contactRequest, User $owner)
    {
        try {
            $requester = User::find($contactRequest->requester_id);
            if ($requester && $requester->device_token) {
                send_notification(
                    'single',
                    'Request Approved!',
                    "{$owner->name} accepted your connect request. View their contacts now!",
                    $owner->image,
                    [
                        'device_token' => $requester->device_token,
                        'type' => 'direct_connect_approved',
                        'request_id' => (string) $contactRequest->id,
                        'owner_id' => (string) $owner->id,
                    ]
                );
            }
        } catch (\Exception $e) {
            Log::error('DirectConnect approval notification failed', ['error' => $e->getMessage()]);
        }
    }

    private function notifyRequesterRejected(ContactRequest $contactRequest)
    {
        try {
            $requester = User::find($contactRequest->requester_id);
            if ($requester && $requester->device_token) {
                send_notification(
                    'single',
                    'Request Declined',
                    'Your connect request was declined.',
                    null,
                    [
                        'device_token' => $requester->device_token,
                        'type' => 'direct_connect_rejected',
                        'request_id' => (string) $contactRequest->id,
                    ]
                );
            }
        } catch (\Exception $e) {
            Log::error('DirectConnect rejection notification failed', ['error' => $e->getMessage()]);
        }
    }
}

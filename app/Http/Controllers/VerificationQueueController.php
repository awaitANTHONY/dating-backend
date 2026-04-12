<?php

namespace App\Http\Controllers;

use App\Models\VerificationRequest;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use DataTables;

class VerificationQueueController extends Controller
{
    /**
     * Display the verification review queue.
     */
    public function index(Request $request)
    {
        if ($request->ajax()) {
            $query = VerificationRequest::with(['user.user_information'])
                ->where('status', 'review')
                ->orderBy('created_at', 'DESC');

            return DataTables::eloquent($query)
                ->addColumn('user_name', function ($req) {
                    return optional($req->user)->name ?? 'Unknown';
                })
                ->addColumn('user_image', function ($req) {
                    $img = optional($req->user)->image ?? 'default/profile.png';
                    return '<img class="img-thumbnail" style="width:50px;height:50px;object-fit:cover;" src="' . asset($img) . '">';
                })
                ->addColumn('selfie', function ($req) {
                    return '<img class="img-thumbnail" style="width:50px;height:50px;object-fit:cover;" src="' . asset($req->image) . '">';
                })
                ->addColumn('gender', function ($req) {
                    return ucfirst(optional(optional($req->user)->user_information)->gender ?? '-');
                })
                ->addColumn('country', function ($req) {
                    $code = optional(optional($req->user)->user_information)->country_code;
                    if (!$code) return '-';
                    $code = strtoupper(trim($code));
                    if (strlen($code) === 2 && ctype_alpha($code)) {
                        $flag = collect(str_split($code))->map(function ($c) {
                            return mb_chr(ord($c) - ord('A') + 0x1F1E6);
                        })->implode('');
                        return $flag . ' ' . $code;
                    }
                    return $code;
                })
                ->addColumn('age', function ($req) {
                    $dob = optional(optional($req->user)->user_information)->date_of_birth;
                    if (!$dob) return '-';
                    return \Carbon\Carbon::parse($dob)->age;
                })
                ->addColumn('face_score', function ($req) {
                    $ai = $req->ai_response;
                    if (!$ai || !isset($ai['face_matching']['highest_match'])) return '<span class="badge badge-secondary">N/A</span>';
                    $score = $ai['face_matching']['highest_match'];
                    $color = $score >= 80 ? 'success' : ($score >= 60 ? 'warning' : 'danger');
                    return '<span class="badge badge-' . $color . '" style="font-size:13px;">' . round($score, 1) . '%</span>';
                })
                ->addColumn('matched_count', function ($req) {
                    $ai = $req->ai_response;
                    if (!$ai || !isset($ai['face_matching']['matched'])) return '-';
                    $total = ($ai['face_matching']['matched'] ?? 0) +
                             ($ai['face_matching']['unmatched'] ?? 0) +
                             ($ai['face_matching']['skipped'] ?? 0);
                    return ($ai['face_matching']['matched'] ?? 0) . '/' . $total;
                })
                ->editColumn('created_at', function ($req) {
                    return $req->created_at ? $req->created_at->format('M d, g:ia') : '-';
                })
                ->addColumn('waiting', function ($req) {
                    return $req->created_at ? $req->created_at->diffForHumans(null, true) : '-';
                })
                ->addColumn('action', function ($req) {
                    return '<div class="btn-group btn-group-sm" role="group">
                        <button class="btn btn-success btn-approve" data-id="' . $req->id . '" title="Approve">
                            <i class="fas fa-check"></i>
                        </button>
                        <button class="btn btn-danger btn-reject" data-id="' . $req->id . '" title="Reject">
                            <i class="fas fa-times"></i>
                        </button>
                        <a href="' . url('verification-queue/' . $req->id) . '" class="btn btn-info btn-detail" title="Review Detail">
                            <i class="fas fa-eye"></i>
                        </a>
                    </div>';
                })
                ->rawColumns(['user_image', 'selfie', 'face_score', 'action'])
                ->make(true);
        }

        $reviewCount = VerificationRequest::where('status', 'review')->count();
        return view('backend.verification_queue.index', compact('reviewCount'));
    }

    /**
     * Get verification detail (JSON for popup).
     */
    public function show($id)
    {
        $req = VerificationRequest::with(['user.user_information'])->findOrFail($id);

        $user = $req->user;
        $info = $user ? $user->user_information : null;
        $ai   = $req->ai_response ?? [];

        // Profile photos
        $profilePhotos = [];
        if ($user && $user->image && !str_contains($user->image, 'default/profile.png')) {
            $profilePhotos[] = asset($user->image);
        }
        if ($info && $info->images) {
            $images = is_string($info->images) ? json_decode($info->images, true) : $info->images;
            if (is_array($images)) {
                foreach (array_filter($images) as $img) {
                    $profilePhotos[] = asset($img);
                }
            }
        }

        $highestMatch = $ai['face_matching']['highest_match'] ?? null;
        $matched = $ai['face_matching']['matched'] ?? 0;
        $total = $matched + ($ai['face_matching']['unmatched'] ?? 0) + ($ai['face_matching']['skipped'] ?? 0);

        return response()->json([
            'id'             => $req->id,
            'user_name'      => $user ? $user->name : 'Unknown',
            'gender'         => ucfirst($info->gender ?? '-'),
            'age'            => ($info && $info->date_of_birth) ? \Carbon\Carbon::parse($info->date_of_birth)->age : null,
            'country'        => $info->country_code ?? '-',
            'selfie_url'     => asset($req->image),
            'profile_photos' => $profilePhotos,
            'reason'         => $req->reason,
            'highest_match'  => $highestMatch,
            'matched_count'  => $matched . '/' . $total,
            'waiting'        => $req->created_at ? $req->created_at->diffForHumans() : '-',
        ]);
    }

    /**
     * Approve a verification request from the queue.
     */
    public function approve(Request $request, $id)
    {
        $req = VerificationRequest::with('user.user_information')->findOrFail($id);

        if ($req->status !== 'review') {
            return response()->json([
                'result'  => 'error',
                'message' => 'This request has already been processed.',
            ]);
        }

        DB::beginTransaction();
        try {
            $req->update([
                'status' => 'approved',
                'reason' => 'Manually approved by admin.',
            ]);

            $req->user->update([
                'verification_status' => 'approved',
                'verified_at'         => now(),
            ]);

            if ($req->user->user_information) {
                $req->user->user_information->update(['is_verified' => true]);
            }

            DB::commit();

            // Send push notification
            $req->user->refresh();
            try {
                $notificationService = app(NotificationService::class);
                $notificationService->sendVerificationApproved($req->user, 'admin_review');
            } catch (\Exception $e) {
                Log::warning('[VerificationQueue] Notification failed', ['error' => $e->getMessage()]);
            }

            $remaining = VerificationRequest::where('status', 'review')->count();

            return response()->json([
                'result'    => 'success',
                'message'   => 'Verification approved for ' . $req->user->name,
                'remaining' => $remaining,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('[VerificationQueue] Approve failed', [
                'id' => $id, 'error' => $e->getMessage(),
            ]);
            return response()->json([
                'result'  => 'error',
                'message' => 'Failed to approve: ' . $e->getMessage(),
            ]);
        }
    }

    /**
     * Reject a verification request from the queue.
     */
    public function reject(Request $request, $id)
    {
        $req = VerificationRequest::with('user.user_information')->findOrFail($id);

        if ($req->status !== 'review') {
            return response()->json([
                'result'  => 'error',
                'message' => 'This request has already been processed.',
            ]);
        }

        $reason = $request->input('reason',
            'Your verification photo did not pass review. Please try again with a clear selfie showing your face.');

        DB::beginTransaction();
        try {
            $req->update([
                'status' => 'rejected',
                'reason' => $reason,
            ]);

            $req->user->update([
                'verification_status' => 'rejected',
                'verified_at'         => null,
            ]);

            if ($req->user->user_information) {
                $req->user->user_information->update(['is_verified' => false]);
            }

            DB::commit();

            // Send push notification
            $req->user->refresh();
            try {
                $notificationService = app(NotificationService::class);
                $notificationService->sendVerificationRejected($req->user, 'admin_review');
            } catch (\Exception $e) {
                Log::warning('[VerificationQueue] Notification failed', ['error' => $e->getMessage()]);
            }

            $remaining = VerificationRequest::where('status', 'review')->count();

            return response()->json([
                'result'    => 'success',
                'message'   => 'Verification rejected for ' . $req->user->name,
                'remaining' => $remaining,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('[VerificationQueue] Reject failed', [
                'id' => $id, 'error' => $e->getMessage(),
            ]);
            return response()->json([
                'result'  => 'error',
                'message' => 'Failed to reject: ' . $e->getMessage(),
            ]);
        }
    }

    /**
     * Bulk approve all review items (convenience action).
     */
    public function bulkApprove(Request $request)
    {
        $ids = $request->input('ids', []);
        if (empty($ids)) {
            return response()->json(['result' => 'error', 'message' => 'No items selected.']);
        }

        $approved = 0;
        $notificationService = app(NotificationService::class);

        foreach ($ids as $id) {
            $req = VerificationRequest::with('user.user_information')->find($id);
            if (!$req || $req->status !== 'review') continue;

            DB::beginTransaction();
            try {
                $req->update(['status' => 'approved', 'reason' => 'Manually approved by admin (bulk).']);
                $req->user->update(['verification_status' => 'approved', 'verified_at' => now()]);
                if ($req->user->user_information) {
                    $req->user->user_information->update(['is_verified' => true]);
                }
                DB::commit();

                $req->user->refresh();
                try {
                    $notificationService->sendVerificationApproved($req->user, 'admin_review');
                } catch (\Exception $e) {}

                $approved++;
            } catch (\Exception $e) {
                DB::rollBack();
                Log::error('[VerificationQueue] Bulk approve failed', ['id' => $id, 'error' => $e->getMessage()]);
            }
        }

        $remaining = VerificationRequest::where('status', 'review')->count();

        return response()->json([
            'result'    => 'success',
            'message'   => "Approved {$approved} verification(s).",
            'remaining' => $remaining,
        ]);
    }
}

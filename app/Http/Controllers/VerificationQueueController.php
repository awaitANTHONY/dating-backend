<?php

namespace App\Http\Controllers;

use App\Models\VerificationQueue;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use DataTables;

class VerificationQueueController extends Controller
{
    /**
     * Display pending verification queue
     */
    public function index(Request $request)
    {
        if ($request->ajax()) {
            $query = VerificationQueue::with('user', 'approvedByAdmin')
                ->orderBy('created_at', 'desc');

            // Filter by status
            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            // Filter by confidence range
            if ($request->filled('confidence_min')) {
                $query->where('ai_confidence', '>=', $request->confidence_min);
            }
            if ($request->filled('confidence_max')) {
                $query->where('ai_confidence', '<=', $request->confidence_max);
            }

            return DataTables::of($query)
                ->addColumn('user_name', function ($item) {
                    return $item->user->name ?? 'Unknown';
                })
                ->addColumn('confidence_percent', function ($item) {
                    return round($item->ai_confidence * 100) . '%';
                })
                ->addColumn('status_badge', function ($item) {
                    $badges = [
                        'pending' => '<span class="badge badge-warning">Pending</span>',
                        'approved' => '<span class="badge badge-success">Approved</span>',
                        'rejected' => '<span class="badge badge-danger">Rejected</span>',
                        'auto_approved' => '<span class="badge badge-info">Auto-Approved</span>',
                    ];
                    return $badges[$item->status] ?? $item->status;
                })
                ->addColumn('action', function ($item) {
                    if ($item->status === 'pending') {
                        return '<button class="btn btn-sm btn-primary approve-btn" data-id="' . $item->id . '">Approve</button> '
                            . '<button class="btn btn-sm btn-danger reject-btn" data-id="' . $item->id . '">Reject</button> '
                            . '<button class="btn btn-sm btn-info view-btn" data-id="' . $item->id . '">View</button>';
                    }
                    return '<button class="btn btn-sm btn-info view-btn" data-id="' . $item->id . '">View</button>';
                })
                ->rawColumns(['status_badge', 'action'])
                ->setRowId(function ($item) {
                    return "row_" . $item->id;
                })
                ->make(true);
        }

        return view('backend.verification_queue.index');
    }

    /**
     * Show verification details
     */
    public function show(VerificationQueue $verificationQueue)
    {
        return response()->json([
            'status' => true,
            'data' => [
                'id' => $verificationQueue->id,
                'user' => $verificationQueue->user->only(['id', 'name', 'email', 'image']),
                'selfie_image' =>asset($verificationQueue->selfie_image),
                'ai_confidence' => round($verificationQueue->ai_confidence * 100) . '%',
                'ai_response' => $verificationQueue->ai_response,
                'reason' => $verificationQueue->reason,
                'status' => $verificationQueue->status,
                'created_at' => $verificationQueue->created_at->format('Y-m-d H:i:s'),
                'approved_at' => $verificationQueue->approved_at ? $verificationQueue->approved_at->format('Y-m-d H:i:s') : null,
                'approved_by' => $verificationQueue->approvedByAdmin?->name,
                'notes' => $verificationQueue->manual_notes,
            ]
        ]);
    }

    /**
     * Approve a verification request
     */
    public function approve(Request $request, VerificationQueue $verificationQueue)
    {
        if ($verificationQueue->status !== 'pending') {
            return response()->json([
                'status' => false,
                'message' => 'This verification has already been reviewed.'
            ], 400);
        }

        try {
            DB::beginTransaction();

            // Update queue item
            $verificationQueue->update([
                'status' => 'approved',
                'approved_by_admin' => auth()->id(),
                'approved_at' => now(),
                'manual_notes' => $request->notes ?? null,
            ]);

            // Update user verification status
            $user = $verificationQueue->user;
            $user->update([
                'verification_status' => 'approved',
                'verified_at' => now()
            ]);

            if ($user->user_information) {
                $user->user_information->update(['is_verified' => true]);
            }

            DB::commit();

            Log::info('Verification manually approved', [
                'verification_queue_id' => $verificationQueue->id,
                'user_id' => $user->id,
                'approved_by' => auth()->id()
            ]);

            // Send notification to user
            $this->sendApprovalNotification($user);

            return response()->json([
                'status' => true,
                'message' => 'Verification approved successfully!'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to approve verification', [
                'verification_queue_id' => $verificationQueue->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Failed to approve verification. Please try again.'
            ], 500);
        }
    }

    /**
     * Reject a verification request
     */
    public function reject(Request $request, VerificationQueue $verificationQueue)
    {
        $request->validate([
            'reason' => 'required|string|max:500'
        ]);

        if ($verificationQueue->status !== 'pending') {
            return response()->json([
                'status' => false,
                'message' => 'This verification has already been reviewed.'
            ], 400);
        }

        try {
            DB::beginTransaction();

            // Update queue item
            $verificationQueue->update([
                'status' => 'rejected',
                'approved_by_admin' => auth()->id(),
                'approved_at' => now(),
                'manual_notes' => $request->reason,
                'reason' => $request->reason
            ]);

            // Update user verification status
            $user = $verificationQueue->user;
            $user->update([
                'verification_status' => 'rejected',
                'verified_at' => null
            ]);

            if ($user->user_information) {
                $user->user_information->update(['is_verified' => false]);
            }

            DB::commit();

            Log::info('Verification manually rejected', [
                'verification_queue_id' => $verificationQueue->id,
                'user_id' => $user->id,
                'rejected_by' => auth()->id(),
                'reason' => $request->reason
            ]);

            // Send notification to user
            $this->sendRejectionNotification($user, $request->reason);

            return response()->json([
                'status' => true,
                'message' => 'Verification rejected successfully!'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to reject verification', [
                'verification_queue_id' => $verificationQueue->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Failed to reject verification. Please try again.'
            ], 500);
        }
    }

    /**
     * Get verification queue statistics
     */
    public function stats()
    {
        $stats = [
            'pending' => VerificationQueue::where('status', 'pending')->count(),
            'approved' => VerificationQueue::where('status', 'approved')->count(),
            'rejected' => VerificationQueue::where('status', 'rejected')->count(),
            'avg_confidence' => round(VerificationQueue::whereNotNull('ai_confidence')->avg('ai_confidence') * 100) . '%',
            'high_confidence_pending' => VerificationQueue::where('status', 'pending')
                ->where('ai_confidence', '>=', 0.95)
                ->count(),
            'medium_confidence_pending' => VerificationQueue::where('status', 'pending')
                ->whereBetween('ai_confidence', [0.85, 0.95])
                ->count(),
        ];

        return response()->json([
            'status' => true,
            'data' => $stats
        ]);
    }

    /**
     * Send approval notification to user
     */
    private function sendApprovalNotification(User $user)
    {
        if (!$user->device_token) {
            return;
        }

        try {
            send_notification(
                'single',
                '✅ Verification Approved!',
                'Congratulations! Your account is now verified. Enjoy enhanced features and trust from other users.',
                $user->image ? asset($user->image) : null,
                [
                    'device_token' => $user->device_token,
                    'type' => 'verification_status',
                ]
            );
        } catch (\Exception $e) {
            Log::error('Failed to send approval notification', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Send rejection notification to user
     */
    private function sendRejectionNotification(User $user, string $reason)
    {
        if (!$user->device_token) {
            return;
        }

        try {
            send_notification(
                'single',
                '❌ Verification Rejected',
                'Your verification was not approved. Reason: ' . $reason . '. Please try again with a new photo.',
                null,
                [
                    'device_token' => $user->device_token,
                    'type' => 'verification_status',
                ]
            );
        } catch (\Exception $e) {
            Log::error('Failed to send rejection notification', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
        }
    }
}

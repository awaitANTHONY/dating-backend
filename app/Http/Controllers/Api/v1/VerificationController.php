<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessVerificationJob;
use App\Models\VerificationRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class VerificationController extends Controller
{
    /**
     * Submit verification photo for processing
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function submitVerification(Request $request)
    {
        $user = $request->user();

        // Validate request
        $validator = Validator::make($request->all(), [
            'image' => 'required|image|mimes:jpeg,jpg,png,webp|max:5120', 
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 200);
        }

        // Check if user already has a pending verification
        if ($user->hasPendingVerification()) {
            return response()->json([
                'status' => false,
                'message' => 'You already have a pending verification request. Please wait for it to be processed.',
                'code' => 'PENDING_VERIFICATION'
            ], 200);
        }

        // Check if user is already verified
        // if ($user->isVerified()) {
        //     return response()->json([
        //         'status' => false,
        //         'message' => 'Your account is already verified.',
        //         'code' => 'ALREADY_VERIFIED'
        //     ], 200);
        // }

        try {
            DB::beginTransaction();

            // Store verification photo in public/uploads/verifications
            $verificationPhoto = $request->file('image');
            $filename = 'verification_' . $user->id . '_' . time() . '.' . $verificationPhoto->getClientOriginalExtension();
            
            $path = 'public/uploads/verifications/';
            if (!file_exists(base_path($path))) {
                mkdir(base_path($path), 0755, true);
            }
            
            $verificationPhoto->move(base_path($path), $filename);
            $imagePath = $path . $filename;

            // Create verification request
            $verificationRequest = VerificationRequest::create([
                'user_id' => $user->id,
                'image' => $imagePath,
                'status' => 'pending',
            ]);

            // Update user verification status
            $user->update([
                'verification_status' => 'pending'
            ]);

            DB::commit();

            // Dispatch queue job for background processing (non-blocking)
            ProcessVerificationJob::dispatch($verificationRequest->id);

            Log::info('Verification request submitted', [
                'user_id' => $user->id,
                'request_id' => $verificationRequest->id
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Verification photo submitted successfully. We will process your request shortly.',
                'data' => [
                    'verification_request_id' => $verificationRequest->id,
                    'image' => asset($imagePath),
                    'status' => $verificationRequest->status,
                    'submitted_at' => $verificationRequest->created_at->toDateTimeString(),
                ]
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Verification submission failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Failed to submit verification request. Please try again.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 200);
        }
    }

    /**
     * Get verification status
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getVerificationStatus(Request $request)
    {
        $user = $request->user();

        $verificationRequest = $user->verificationRequest;

        if (!$verificationRequest) {
            return response()->json([
                'status' => true,
                'data' => [
                    'verification_status' => $user->verification_status ?? 'not_requested',
                    'is_verified' => false,
                    'verified_at' => null,
                    'request' => null,
                    'message' => 'No verification request found. Submit a verification photo to get verified.'
                ]
            ], 200);
        }

        // Build response data
        $responseData = [
            'verification_status' => $user->verification_status,
            'is_verified' => $user->isVerified(),
            'verified_at' => $user->verified_at ? $user->verified_at->toDateTimeString() : null,
            'request' => [
                'id' => $verificationRequest->id,
                'status' => $verificationRequest->status,
                'reason' => $verificationRequest->reason,
                'image' => $verificationRequest->image ? asset($verificationRequest->image) : null,
                'submitted_at' => $verificationRequest->created_at->toDateTimeString(),
                'updated_at' => $verificationRequest->updated_at->toDateTimeString(),
            ]
        ];

        // Add helpful messages based on status
        if ($verificationRequest->isPending()) {
            $responseData['message'] = 'Your verification is being processed. Please check back in a few minutes.';
        } elseif ($verificationRequest->isApproved()) {
            $responseData['message'] = 'Congratulations! Your account is verified.';
        } elseif ($verificationRequest->isRejected()) {
            // Add unmatched photos if available
            if ($verificationRequest->ai_response && isset($verificationRequest->ai_response['face_match']['unmatched_photos'])) {
                $unmatchedPhotos = $verificationRequest->ai_response['face_match']['unmatched_photos'];
                if (!empty($unmatchedPhotos)) {
                    $responseData['unmatched_photos'] = $unmatchedPhotos;
                    $responseData['message'] = 'Verification rejected. ' . count($unmatchedPhotos) . ' of your profile photos do not match. Please review the reason and submit a new photo.';
                } else {
                    $responseData['message'] = 'Verification rejected. Please review the reason and submit a new photo.';
                }
            } else {
                $responseData['message'] = 'Verification rejected. Please review the reason and submit a new photo.';
            }
        }

        return response()->json([
            'status' => true,
            'data' => $responseData
        ], 200);
    }

    /**
     * Get verification history
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getVerificationHistory(Request $request)
    {
        $user = $request->user();

        $verifications = $user->verificationRequests()
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($verification) {
                $data = [
                    'id' => $verification->id,
                    'status' => $verification->status,
                    'reason' => $verification->reason,
                    'image' => $verification->image ? asset($verification->image) : null,
                    'submitted_at' => $verification->created_at->toDateTimeString(),
                    'processed_at' => $verification->updated_at->toDateTimeString(),
                ];
                
                // Add unmatched photos if available
                if ($verification->status === 'rejected' && $verification->ai_response) {
                    if (isset($verification->ai_response['face_match']['unmatched_photos'])) {
                        $unmatchedPhotos = $verification->ai_response['face_match']['unmatched_photos'];
                        if (!empty($unmatchedPhotos)) {
                            $data['unmatched_photos'] = $unmatchedPhotos;
                        }
                    }
                }
                
                return $data;
            });

        return response()->json([
            'status' => true,
            'data' => [
                'total_requests' => $verifications->count(),
                'current_status' => $user->verification_status ?? 'not_requested',
                'is_verified' => $user->isVerified(),
                'verifications' => $verifications
            ]
        ], 200);
    }
}

<?php

namespace App\Jobs;

use App\Models\VerificationRequest;
use App\Models\VerificationQueue;
use App\Models\User;
use App\Services\VerificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class ProcessVerificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The number of seconds to wait before retrying.
     */
    public $backoff = [30, 60, 120];

    /**
     * The maximum number of seconds the job can run.
     *
     * @var int
     */
    public $timeout = 180;

    /**
     * The verification request ID.
     *
     * @var int
     */
    protected $verificationRequestId;

    /**
     * Create a new job instance.
     *
     * @param int $verificationRequestId
     * @return void
     */
    public function __construct(int $verificationRequestId)
    {
        $this->verificationRequestId = $verificationRequestId;
    }

    /**
     * Execute the job.
     *
     * @param VerificationService $verificationService
     * @return void
     */
    public function handle(VerificationService $verificationService)
    {
        try {
            // Fetch verification request
            $verificationRequest = VerificationRequest::with('user.user_information')
                ->find($this->verificationRequestId);

            if (!$verificationRequest) {
                Log::error('Verification request not found', [
                    'verification_request_id' => $this->verificationRequestId
                ]);
                return;
            }

            // Check if already processed
            if ($verificationRequest->status !== 'pending') {
                Log::warning('Verification request already processed', [
                    'verification_request_id' => $this->verificationRequestId,
                    'status' => $verificationRequest->status
                ]);
                return;
            }

            $user = $verificationRequest->user;

            // Get user's profile photos
            $userInformation = $user->user_information;
            if (!$userInformation) {
                $this->failVerification($verificationRequest, 'User profile information not found');
                return;
            }

            $profilePhotos = $this->extractProfilePhotos($user, $userInformation);

            if (empty($profilePhotos)) {
                $this->failVerification($verificationRequest, 'No profile photos found to compare');
                return;
            }

            if (count($profilePhotos) < 2) {
                $this->failVerification($verificationRequest, 'You need at least 2 profile photos to verify. Please add more photos showing your face and try again.');
                return;
            }

            // Analyze verification photo
            $result = $verificationService->analyzeImage(
                $verificationRequest->image,
                $profilePhotos
            );

            // Extract confidence score
            $confidence = $result['confidence'] ?? 0.0;
            $autoApproveThreshold = config('verification.confidence_thresholds.auto_approve', 0.95);
            $manualReviewThreshold = config('verification.confidence_thresholds.manual_review', 0.85);

            DB::beginTransaction();

            // Update verification request with AI response
            $verificationRequest->update([
                'reason' => $result['reason'],
                'ai_response' => $result
            ]);

            // Route based on confidence level
            if ($confidence >= $autoApproveThreshold) {
                // ✅ High confidence: Auto-approve immediately
                $verificationRequest->update(['status' => 'approved']);

                $user->update([
                    'verification_status' => 'approved',
                    'verified_at' => now()
                ]);

                if ($user->user_information) {
                    $user->user_information->update(['is_verified' => true]);
                }

                DB::commit();

                Log::info('Verification auto-approved (high confidence)', [
                    'user_id' => $user->id,
                    'confidence' => $confidence
                ]);

                $this->sendVerificationNotification($user, 'approved', $result['reason']);

            } elseif ($confidence >= $manualReviewThreshold && $confidence < $autoApproveThreshold) {
                // ⚠️ Medium confidence: Queue for manual admin review
                VerificationQueue::create([
                    'user_id' => $user->id,
                    'selfie_image' => $verificationRequest->image,
                    'status' => 'pending',
                    'ai_confidence' => $confidence,
                    'ai_response' => $result,
                    'reason' => $result['reason']
                ]);

                $verificationRequest->update(['status' => 'pending_admin_review']);

                DB::commit();

                Log::info('Verification queued for manual review', [
                    'user_id' => $user->id,
                    'confidence' => $confidence
                ]);

                // Notify admin that there's a verification pending review
                // TODO: Send admin notification about pending verification queue item

            } else {
                // ❌ Low confidence: Auto-reject
                $verificationRequest->update(['status' => 'rejected']);

                $user->update([
                    'verification_status' => 'rejected',
                    'verified_at' => null
                ]);

                if ($user->user_information) {
                    $user->user_information->update(['is_verified' => false]);
                }

                DB::commit();

                Log::info('Verification auto-rejected (low confidence)', [
                    'user_id' => $user->id,
                    'confidence' => $confidence
                ]);

                $this->sendVerificationNotification($user, 'rejected', $result['reason']);
            }

        } catch (Exception $e) {
            DB::rollBack();

            Log::error('Verification processing failed', [
                'verification_request_id' => $this->verificationRequestId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // If max retries reached, mark as failed
            if ($this->attempts() >= $this->tries) {
                $this->failVerification(
                    VerificationRequest::find($this->verificationRequestId),
                    'Processing failed after multiple attempts: ' . $e->getMessage()
                );
            }

            throw $e; // Re-throw to trigger retry
        }
    }

    /**
     * Send verification notification to user
     *
     * @param User $user
     * @param string $status
     * @param string $reason
     * @return void
     */
    private function sendVerificationNotification(User $user, string $status, string $reason)
    {
        try {
            // Check if user has device token for push notifications
            if (!$user->device_token) {
                return;
            }

            $title = '';
            $message = '';
            $image = null;

            if ($status === 'approved') {
                $title = '✅ Verification Approved!';
                $message = 'Congratulations! Your account is now verified. Enjoy enhanced features and trust from other users.';
                $image = $user->image ? asset($user->image) : null;
            } elseif ($status === 'rejected') {
                $title = '❌ Verification Rejected';
                $message = 'Your verification was not approved. Reason: ' . $reason . '. Please try again with a new photo.';
            }

            if ($title && $message) {
                send_notification(
                    'single',
                    $title,
                    $message,
                    $image,
                    [
                        'device_token' => $user->device_token, 
                        'type' => 'verification_status',
                    ]
                );
            }
        } catch (Exception $e) {
            // Don't fail the job if notification fails
            Log::error('Failed to send verification notification', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Extract profile photos from user data
     *
     * @param User $user
     * @param mixed $userInformation
     * @return array
     */
    private function extractProfilePhotos(User $user, $userInformation): array
    {
        $photos = [];

        // Add main profile image
        if ($user->image && !str_contains($user->image, 'default/profile.png')) {
            $photos[] = $user->image;
        }

        // Add additional images from user_information
        if ($userInformation && $userInformation->images) {
            $images = is_string($userInformation->images) 
                ? json_decode($userInformation->images, true) 
                : $userInformation->images;

            if (is_array($images)) {
                foreach ($images as $image) {
                    if ($image && !empty($image)) {
                        $photos[] = $image;
                    }
                }
            }
        }

        return array_filter($photos);
    }

    /**
     * Mark verification as failed
     *
     * @param VerificationRequest|null $verificationRequest
     * @param string $reason
     * @return void
     */
    private function failVerification(?VerificationRequest $verificationRequest, string $reason)
    {
        if (!$verificationRequest) {
            return;
        }

        try {
            DB::beginTransaction();

            $verificationRequest->update([
                'status' => 'rejected',
                'reason' => $reason,
            ]);

            $verificationRequest->user->update([
                'verification_status' => 'rejected',
                'verified_at' => null
            ]);

            // Update user_information.is_verified to false
            if ($verificationRequest->user->user_information) {
                $verificationRequest->user->user_information->update([
                    'is_verified' => false
                ]);
            }

            DB::commit();

            // Send notification for failed verification
            $this->sendVerificationNotification($verificationRequest->user, 'rejected', $reason);

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Failed to mark verification as failed', [
                'verification_request_id' => $verificationRequest->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Handle a job failure.
     *
     * @param Exception $exception
     * @return void
     */
    public function failed(Exception $exception)
    {
        Log::error('Verification job failed permanently', [
            'verification_request_id' => $this->verificationRequestId,
            'error' => $exception->getMessage()
        ]);

        $verificationRequest = VerificationRequest::find($this->verificationRequestId);
        if ($verificationRequest) {
            $this->failVerification($verificationRequest, 'Processing failed: ' . $exception->getMessage());
        }
    }
}

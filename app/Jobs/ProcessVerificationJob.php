<?php

namespace App\Jobs;

use App\Models\VerificationRequest;
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
     * The maximum number of seconds the job can run.
     *
     * @var int
     */
    public $timeout = 90;

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
        Log::info('Processing verification request', [
            'verification_request_id' => $this->verificationRequestId
        ]);

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

            // Analyze verification photo
            $result = $verificationService->analyzeImage(
                $verificationRequest->image,
                $profilePhotos
            );

            // Update verification request and user based on result
            DB::beginTransaction();

            $verificationRequest->update([
                'status' => $result['status'],
                'reason' => $result['reason'],
                'ai_response' => $result
            ]);

            $user->update([
                'verification_status' => $result['status'],
                'verified_at' => $result['status'] === 'approved' ? now() : null
            ]);

            DB::commit();

            Log::info('Verification processed successfully', [
                'verification_request_id' => $this->verificationRequestId,
                'status' => $result['status'],
                'reason' => $result['reason']
            ]);

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

            DB::commit();

            Log::warning('Verification marked as failed', [
                'verification_request_id' => $verificationRequest->id,
                'reason' => $reason
            ]);
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

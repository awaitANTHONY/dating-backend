<?php

namespace App\Jobs;

use App\Models\VerificationRequest;
use App\Models\User;
use App\Services\NotificationService;
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

    public $tries   = 3;
    public $backoff = [30, 60, 120];
    public $timeout = 180;

    public function __construct(
        protected int    $verificationRequestId,
        protected string $triggeredBy = 'manual'
    ) {}

    public function handle(VerificationService $verificationService, NotificationService $notificationService): void
    {
        try {
            $verificationRequest = VerificationRequest::with('user.user_information')
                ->find($this->verificationRequestId);

            if (!$verificationRequest) {
                Log::error('[Verification] Request not found', ['id' => $this->verificationRequestId]);
                return;
            }

            if ($verificationRequest->status !== 'pending') {
                Log::warning('[Verification] Already processed', [
                    'id'     => $this->verificationRequestId,
                    'status' => $verificationRequest->status,
                ]);
                return;
            }

            $user = $verificationRequest->user;

            // Skip if user already has a verified badge
            if ($user->verification_status === 'approved' && $user->verified_at !== null) {
                Log::info('[Verification] User already verified, auto-approving', [
                    'id'      => $this->verificationRequestId,
                    'user_id' => $user->id,
                ]);
                $verificationRequest->update([
                    'status' => 'approved',
                    'reason' => 'User already has verification badge.',
                ]);
                return;
            }
            $userInformation = $user->user_information;

            if (!$userInformation) {
                $this->failVerification($verificationRequest, 'User profile information not found', $notificationService);
                return;
            }

            $profilePhotos = $this->extractProfilePhotos($user, $userInformation);

            if (empty($profilePhotos)) {
                $this->failVerification($verificationRequest, 'No profile photos found to compare', $notificationService);
                return;
            }

            if (count($profilePhotos) < 2) {
                $this->failVerification($verificationRequest, 'You need at least 2 profile photos to verify. Please add more photos showing your face and try again.', $notificationService);
                return;
            }

            $result = $verificationService->analyzeImage($verificationRequest->image, $profilePhotos);

            // --- Handle 'review' status: queue for manual admin review ---
            if ($result['status'] === 'review') {
                DB::beginTransaction();

                $verificationRequest->update([
                    'status'      => 'review',
                    'reason'      => $result['reason'],
                    'ai_response' => $result,
                ]);

                // Keep user in 'pending' state — Flutter app shows "Verification Pending"
                $user->update([
                    'verification_status' => 'pending',
                    'verified_at'         => null,
                ]);

                DB::commit();

                Log::info('[Verification] Queued for manual review', [
                    'id'      => $this->verificationRequestId,
                    'user_id' => $user->id,
                    'reason'  => $result['reason'],
                    'face_matching' => $result['face_matching']['highest_match'] ?? 'N/A',
                ]);

                // NO notification sent — user stays in pending state until admin reviews
                return;
            }

            // --- Approved or Rejected by AI ---
            DB::beginTransaction();

            $verificationRequest->update([
                'status'      => $result['status'],
                'reason'      => $result['reason'],
                'ai_response' => $result,
            ]);

            $user->update([
                'verification_status' => $result['status'],
                'verified_at'         => $result['status'] === 'approved' ? now() : null,
            ]);

            if ($user->user_information) {
                $user->user_information->update([
                    'is_verified' => $result['status'] === 'approved',
                ]);
            }

            DB::commit();

            // Send localized push + in-app notification
            $user->refresh();
            if ($result['status'] === 'approved') {
                $notificationService->sendVerificationApproved($user, $this->triggeredBy);
            } else {
                $notificationService->sendVerificationRejected($user, $this->triggeredBy);
            }

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('[Verification] Processing failed', [
                'id'    => $this->verificationRequestId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            if ($this->attempts() >= $this->tries) {
                // Instead of hard rejecting, queue for manual admin review
                $req = VerificationRequest::find($this->verificationRequestId);
                if ($req && in_array($req->status, ['pending', 'review'])) {
                    try {
                        DB::beginTransaction();
                        $req->update([
                            'status' => 'review',
                            'reason' => 'Processing failed after multiple attempts. Queued for manual review.',
                        ]);
                        // Keep user as pending so Flutter shows "Verification Pending"
                        $req->user->update(['verification_status' => 'pending']);
                        DB::commit();
                        Log::info('[Verification] All retries failed, queued for review', [
                            'id' => $this->verificationRequestId,
                        ]);
                    } catch (Exception $retryEx) {
                        DB::rollBack();
                        Log::error('[Verification] Failed to queue for review after retries', [
                            'id' => $this->verificationRequestId, 'error' => $retryEx->getMessage(),
                        ]);
                    }
                }
                return;
            }

            throw $e;
        }
    }

    private function failVerification(?VerificationRequest $req, string $reason, NotificationService $notificationService): void
    {
        if (!$req) return;

        try {
            DB::beginTransaction();
            $req->update(['status' => 'rejected', 'reason' => $reason]);
            $req->user->update(['verification_status' => 'rejected', 'verified_at' => null]);

            if ($req->user->user_information) {
                $req->user->user_information->update(['is_verified' => false]);
            }
            DB::commit();

            $req->user->refresh();
            $notificationService->sendVerificationRejected($req->user, $this->triggeredBy);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('[Verification] Failed to mark as failed', ['id' => $req->id, 'error' => $e->getMessage()]);
        }
    }

    private function extractProfilePhotos(User $user, $userInformation): array
    {
        $photos = [];

        if ($user->image && !str_contains($user->image, 'default/profile.png')) {
            $photos[] = $user->image;
        }

        if ($userInformation && $userInformation->images) {
            $images = is_string($userInformation->images)
                ? json_decode($userInformation->images, true)
                : $userInformation->images;

            if (is_array($images)) {
                foreach (array_filter($images) as $image) {
                    $photos[] = $image;
                }
            }
        }

        return array_values(array_filter($photos));
    }

    public function failed(Exception $exception): void
    {
        Log::error('[Verification] Job failed permanently', [
            'id'    => $this->verificationRequestId,
            'error' => $exception->getMessage(),
        ]);

        $req = VerificationRequest::find($this->verificationRequestId);
        if ($req) {
            $notificationService = app(NotificationService::class);
            $this->failVerification($req, 'Processing failed: ' . $exception->getMessage(), $notificationService);
        }
    }
}

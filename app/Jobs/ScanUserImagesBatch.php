<?php

namespace App\Jobs;

use App\Models\ModerationLog;
use App\Models\User;
use App\Services\NotificationService;
use App\Services\RekognitionModerationService;
use Aws\Exception\AwsException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Scans a batch of users (max 20) with AWS Rekognition.
 * - Safe: logs BEFORE deleting
 * - Never updates last_scanned_at on AWS failure
 * - Skips missing files gracefully
 */
class ScanUserImagesBatch implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 300;

    public function __construct(private readonly array $userIds) {}

    public function handle(
        RekognitionModerationService $rekognition,
        NotificationService $notifications
    ): void {
        foreach ($this->userIds as $userId) {
            $this->scanUser($userId, $rekognition, $notifications);

            // 3-second pause between users within the batch (server protection)
            sleep(3);
        }
    }

    private function scanUser(
        int $userId,
        RekognitionModerationService $rekognition,
        NotificationService $notifications
    ): void {
        $user = User::with('information')->find($userId);

        if (!$user) {
            Log::warning("[ScanUserImagesBatch] User {$userId} not found, skipping.");
            return;
        }

        $awsFailed = false;

        // ── 1. Profile image ──────────────────────────────────────────────────
        if (!empty($user->image) && !str_contains($user->image, 'default/profile')) {
            $result = $this->moderateImage($user->image, $rekognition);

            if ($result === null) {
                // AWS error — skip this user entirely, do NOT update last_scanned_at
                $awsFailed = true;
            } elseif ($result['decision'] === 'rejected') {
                // Log FIRST, then delete
                $this->log($userId, $user->image, 'rejected', $result['reason'], $result['confidence']);
                $this->deleteFile($user->image);
                $user->update(['image' => null]);
                $this->sendRemovedNotification($user, $notifications);

                Log::info("[ScanUserImagesBatch] Profile image removed for user {$userId}: {$result['reason']} ({$result['confidence']}%)");
            } else {
                $this->log($userId, $user->image, 'approved', null, null);
            }
        }

        if ($awsFailed) {
            Log::warning("[ScanUserImagesBatch] AWS failed for user {$userId} — last_scanned_at NOT updated.");
            return;
        }

        // ── 2. Gallery images ─────────────────────────────────────────────────
        $information = $user->information;
        if ($information) {
            $gallery = $information->images ?? [];
            if (is_string($gallery)) {
                $gallery = json_decode($gallery, true) ?? [];
            }

            $cleanGallery = $gallery;
            $galleryChanged = false;

            foreach ($gallery as $index => $imagePath) {
                if (empty($imagePath)) continue;

                $result = $this->moderateImage($imagePath, $rekognition);

                if ($result === null) {
                    $awsFailed = true;
                    break;
                }

                if ($result['decision'] === 'rejected') {
                    // Log FIRST, then remove
                    $this->log($userId, $imagePath, 'rejected', $result['reason'], $result['confidence']);
                    $this->deleteFile($imagePath);
                    unset($cleanGallery[$index]);
                    $galleryChanged = true;

                    Log::info("[ScanUserImagesBatch] Gallery image removed for user {$userId}: {$result['reason']} ({$result['confidence']}%)");
                } else {
                    $this->log($userId, $imagePath, 'approved', null, null);
                }
            }

            if ($awsFailed) {
                Log::warning("[ScanUserImagesBatch] AWS failed mid-gallery for user {$userId} — last_scanned_at NOT updated.");
                return;
            }

            if ($galleryChanged) {
                $information->update(['images' => array_values($cleanGallery)]);
                $this->sendRemovedNotification($user, $notifications);
            }
        }

        // ── 3. Mark as scanned (only when no AWS failures) ───────────────────
        $user->update(['last_scanned_at' => now()]);
    }

    /**
     * Returns moderation result array or null on AWS failure.
     */
    private function moderateImage(string $path, RekognitionModerationService $rekognition): ?array
    {
        try {
            return $rekognition->moderate($path);
        } catch (AwsException $e) {
            Log::error("[ScanUserImagesBatch] AWS Rekognition error", [
                'path'  => $path,
                'error' => $e->getMessage(),
            ]);
            return null;
        } catch (\RuntimeException $e) {
            // File not found — log as error but don't fail the whole user
            Log::warning("[ScanUserImagesBatch] File not found, skipping", ['path' => $path]);
            ModerationLog::create([
                'user_id'    => 0,
                'image_path' => $path,
                'decision'   => 'error',
                'reason'     => 'file_not_found',
                'confidence' => null,
                'scanned_at' => now(),
            ]);
            return ['decision' => 'approved', 'reason' => null, 'confidence' => null];
        }
    }

    private function deleteFile(string $path): void
    {
        // Try both storage paths
        if (Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        } elseif (Storage::disk('public')->exists(ltrim($path, '/'))) {
            Storage::disk('public')->delete(ltrim($path, '/'));
        }
    }

    private function log(int $userId, string $path, string $decision, ?string $reason, ?float $confidence): void
    {
        ModerationLog::create([
            'user_id'    => $userId,
            'image_path' => $path,
            'decision'   => $decision,
            'reason'     => $reason,
            'confidence' => $confidence,
            'scanned_at' => now(),
        ]);
    }

    private function sendRemovedNotification(User $user, NotificationService $notifications): void
    {
        try {
            $notifications->sendImageRemoved($user);
        } catch (\Throwable $e) {
            Log::error("[ScanUserImagesBatch] Failed to send removal notification", [
                'user_id' => $user->id,
                'error'   => $e->getMessage(),
            ]);
        }
    }
}

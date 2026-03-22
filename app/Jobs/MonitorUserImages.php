<?php

namespace App\Jobs;

use App\Models\User;
use App\Models\UserInformation;
use App\Services\OpenAIImageMonitor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class MonitorUserImages implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 180;
    public int $backoff = 30;
    protected int $userId;

    public function __construct(int $userId)
    {
        $this->userId = $userId;
        $this->onQueue('image-monitoring');
    }

    public function handle(): void
    {
        $user = User::find($this->userId);

        if (!$user || !$user->getRawOriginal('image')) {
            return;
        }

        if (str_contains($user->getRawOriginal('image'), 'default/profile.png')) {
            return;
        }

        if ($user->last_scanned_at && $user->last_scanned_at > now()->subDays(7)) {
            return;
        }

        $profileRawPath = $user->getRawOriginal('image');
        $isLocalProfile = $this->isLocalPath($profileRawPath);

        if ($isLocalProfile) {
            $profileFullPath = base_path($profileRawPath);

            if (file_exists($profileFullPath)) {
                $currentHash = md5_file($profileFullPath);

                if ($user->image_hash === $currentHash) {
                    $user->update(['last_scanned_at' => now()]);
                    return;
                }
            }
        }

        $imagePaths = [];
        $imagePaths[] = $profileRawPath;

        $userInfo = UserInformation::where('user_id', $user->id)->first();
        $galleryPaths = [];

        if ($userInfo) {
            $rawGallery = $userInfo->getRawOriginal('images');
            $galleryPaths = is_string($rawGallery) ? (json_decode($rawGallery, true) ?? []) : [];
            $galleryPaths = array_values(array_filter($galleryPaths));

            foreach ($galleryPaths as $galleryPath) {
                $imagePaths[] = $galleryPath;
            }
        }

        if (empty($imagePaths)) {
            return;
        }

        try {
            $monitor = app(OpenAIImageMonitor::class);
            $results = $monitor->moderateBatch($imagePaths);

            if (empty($results)) {
                Log::warning('MonitorUserImages: Empty moderation results, marking scanned', [
                    'user_id'     => $this->userId,
                    'image_count' => count($imagePaths),
                ]);

                $user->last_scanned_at = now();
                $user->save();
                return;
            }

            $this->processResults($user, $userInfo, $results, $galleryPaths);
        } catch (Exception $e) {
            Log::error('MonitorUserImages: Failed to process user images', [
                'user_id' => $this->userId,
                'error'   => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    private function processResults(User $user, ?UserInformation $userInfo, array $results, array $galleryPaths): void
    {
        $profileRawPath = $user->getRawOriginal('image');
        $monitor = app(OpenAIImageMonitor::class);

        DB::beginTransaction();

        try {
            if (isset($results[0]) && $monitor->shouldReject($results[0])) {
                $user->image = null;
                $user->verification_status = 'rejected';
                $user->status = 0;
                $user->verified_at = null;
                $user->save();

                if ($userInfo) {
                    $userInfo->is_verified = 0;
                    $userInfo->save();
                }

                $this->deleteImageFile($profileRawPath);

                Log::info('MonitorUserImages: Profile image rejected and removed', [
                    'user_id'    => $user->id,
                    'image_path' => $profileRawPath,
                    'result'     => $results[0],
                ]);
            }

            if ($userInfo && !empty($galleryPaths)) {
                $updatedGallery = [];

                foreach ($galleryPaths as $galleryIndex => $galleryPath) {
                    $resultIndex = $galleryIndex + 1;

                    if (isset($results[$resultIndex]) && $monitor->shouldReject($results[$resultIndex])) {
                        $this->deleteImageFile($galleryPath);

                        Log::info('MonitorUserImages: Gallery image rejected and deleted', [
                            'user_id'     => $user->id,
                            'image_index' => $resultIndex,
                            'image_path'  => $galleryPath,
                            'result'      => $results[$resultIndex],
                        ]);
                        continue;
                    }

                    $updatedGallery[] = $galleryPath;
                }

                $userInfo->images = $updatedGallery;
                $userInfo->save();
            }

            $user->last_scanned_at = now();

            if ($this->isLocalPath($profileRawPath)) {
                $profileFullPath = base_path($profileRawPath);
                $user->image_hash = file_exists($profileFullPath) ? md5_file($profileFullPath) : null;
            } else {
                $user->image_hash = md5($profileRawPath);
            }

            $user->save();

            DB::commit();

            Log::info('MonitorUserImages: Scan completed', [
                'user_id' => $user->id,
            ]);
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    private function deleteImageFile(string $path): void
    {
        if (!$this->isLocalPath($path)) {
            return;
        }

        $fullPath = base_path($path);

        if (file_exists($fullPath)) {
            unlink($fullPath);
            Log::info('MonitorUserImages: File deleted from storage', ['path' => $path]);
        }
    }

    private function isLocalPath(string $path): bool
    {
        return !str_starts_with($path, 'http://') && !str_starts_with($path, 'https://');
    }

    public function failed(Exception $exception): void
    {
        Log::error('MonitorUserImages: Job failed permanently', [
            'user_id' => $this->userId,
            'error'   => $exception->getMessage(),
        ]);
    }
}

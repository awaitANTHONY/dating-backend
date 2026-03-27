<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

class GenerateThumbnails implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    protected string $imagePath;

    // Thumbnail sizes: prefix => [width, height]
    protected static array $sizes = [
        'thumb_'  => [200, 200],   // Avatars, map markers, chat list
        'medium_' => [600, 600],   // Swipe cards, profile sheets
    ];

    public function __construct(string $imagePath)
    {
        $this->imagePath = $imagePath;
    }

    public function handle(): void
    {
        $fullPath = base_path($this->imagePath);

        if (!file_exists($fullPath)) {
            return;
        }

        $dir = dirname($fullPath);
        $filename = basename($fullPath);

        $manager = new ImageManager(new Driver());

        foreach (self::$sizes as $prefix => [$width, $height]) {
            $thumbPath = $dir . '/' . $prefix . $filename;

            // Skip if thumbnail already exists
            if (file_exists($thumbPath)) {
                continue;
            }

            try {
                $image = $manager->read($fullPath);
                $image->cover($width, $height);
                $image->toJpeg(80)->save($thumbPath);
            } catch (\Exception $e) {
                \Log::warning('Thumbnail generation failed', [
                    'path' => $this->imagePath,
                    'prefix' => $prefix,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Delete all thumbnails for a given original image path.
     */
    public static function deleteThumbnails(string $imagePath): void
    {
        $fullPath = base_path($imagePath);
        $dir = dirname($fullPath);
        $filename = basename($fullPath);

        foreach (array_keys(self::$sizes) as $prefix) {
            $thumbPath = $dir . '/' . $prefix . $filename;
            if (file_exists($thumbPath)) {
                @unlink($thumbPath);
            }
        }
    }

    /**
     * Get the thumbnail URL for a given original path and size prefix.
     */
    public static function thumbnailPath(string $originalPath, string $prefix = 'thumb_'): string
    {
        $dir = dirname($originalPath);
        $filename = basename($originalPath);
        return $dir . '/' . $prefix . $filename;
    }
}

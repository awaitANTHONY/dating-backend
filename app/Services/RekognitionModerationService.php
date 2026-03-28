<?php

namespace App\Services;

use Aws\Rekognition\RekognitionClient;
use Aws\Exception\AwsException;
use Illuminate\Support\Facades\Log;

class RekognitionModerationService
{
    private RekognitionClient $client;

    // Labels that warrant immediate rejection above the confidence threshold
    private array $blockedCategories = [
        'Explicit Nudity',
        'Nudity',
        'Graphic Male Nudity',
        'Graphic Female Nudity',
        'Sexual Activity',
        'Illustrated Explicit Nudity',
        'Adult Toys',
        'Visually Disturbing',
        'Graphic Violence Or Gore',
        'Physical Violence',
        'Weapon Violence',
        'Weapons',
        'Self Injury',
        'Hate Symbols',
    ];

    public function __construct()
    {
        $this->client = new RekognitionClient([
            'region'      => config('services.aws.region', 'us-east-1'),
            'version'     => 'latest',
            'credentials' => [
                'key'    => config('services.aws.key'),
                'secret' => config('services.aws.secret'),
            ],
        ]);
    }

    /**
     * Scan an image file by its storage-relative path.
     *
     * @param  string  $storagePath  e.g. "uploads/profile/abc.jpg"
     * @return array{decision: string, reason: string|null, confidence: float|null}
     * @throws \RuntimeException  if the file does not exist
     */
    public function moderate(string $storagePath): array
    {
        $fullPath = storage_path('app/public/' . ltrim($storagePath, '/'));

        if (!file_exists($fullPath)) {
            // Try public_path fallback
            $fullPath = public_path(ltrim($storagePath, '/'));
        }

        if (!file_exists($fullPath)) {
            throw new \RuntimeException("Image file not found: {$storagePath}");
        }

        $imageBytes = file_get_contents($fullPath);

        if ($imageBytes === false || strlen($imageBytes) < 100) {
            throw new \RuntimeException("Could not read image: {$storagePath}");
        }

        try {
            $result = $this->client->detectModerationLabels([
                'Image'         => ['Bytes' => $imageBytes],
                'MinConfidence' => 50.0,
            ]);
        } catch (AwsException $e) {
            Log::error('[RekognitionModerationService] AWS error', [
                'path'  => $storagePath,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }

        $labels = $result['ModerationLabels'] ?? [];

        foreach ($labels as $label) {
            $name       = $label['Name'] ?? '';
            $parent     = $label['ParentName'] ?? '';
            $confidence = (float) ($label['Confidence'] ?? 0);

            if ($confidence < 70.0) continue;

            foreach ($this->blockedCategories as $blocked) {
                if (stripos($name, $blocked) !== false || stripos($parent, $blocked) !== false) {
                    return [
                        'decision'   => 'rejected',
                        'reason'     => $name,
                        'confidence' => $confidence,
                    ];
                }
            }
        }

        return ['decision' => 'approved', 'reason' => null, 'confidence' => null];
    }
}

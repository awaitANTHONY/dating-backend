<?php

namespace App\Services;

use Aws\Rekognition\RekognitionClient;
use Aws\Exception\AwsException;
use Illuminate\Support\Facades\Log;
use Exception;

class VerificationService
{
    private RekognitionClient $rekognition;
    private float $faceMatchThreshold = 70.0; // Minimum similarity % (0–100 scale)

    public function __construct()
    {
        $this->rekognition = new RekognitionClient([
            'version'     => 'latest',
            'region'      => env('AWS_DEFAULT_REGION', 'us-east-1'),
            'credentials' => [
                'key'    => env('AWS_ACCESS_KEY_ID'),
                'secret' => env('AWS_SECRET_ACCESS_KEY'),
            ],
        ]);
    }

    /**
     * Analyze verification image against profile photos.
     *
     * @param string $image    Storage path to verification photo
     * @param array  $profilePhotos Array of profile photo paths/URLs
     * @return array ['status' => 'approved|rejected', 'reason' => string, 'confidence' => float, ...]
     */
    public function analyzeImage(string $image, array $profilePhotos): array
    {
        try {
            $imagePath = base_path($image);
            if (!file_exists($imagePath)) {
                return $this->rejectVerification('Verification photo not found', 0.0);
            }

            $imageBytes = file_get_contents($imagePath);

            // Step 1: Liveness — single clear face + thumbs-up gesture
            $livenessCheck = $this->checkLivenessAndGesture($imageBytes);

            if (!$livenessCheck['success']) {
                return $this->rejectVerification($livenessCheck['reason'], $livenessCheck['confidence']);
            }

            // Step 2: Face matching against profile photos
            $faceMatchResult = $this->performFaceMatching($imageBytes, $profilePhotos);

            if (!$faceMatchResult['success']) {
                return [
                    'status'        => 'rejected',
                    'reason'        => $faceMatchResult['reason'],
                    'confidence'    => $faceMatchResult['confidence'],
                    'liveness_check' => $livenessCheck,
                    'face_match'    => $faceMatchResult,
                ];
            }

            return [
                'status'        => 'approved',
                'reason'        => 'Verification successful',
                'confidence'    => min($livenessCheck['confidence'], $faceMatchResult['confidence']),
                'liveness_check' => $livenessCheck,
                'face_match'    => $faceMatchResult,
            ];

        } catch (Exception $e) {
            Log::error('Verification analysis error', [
                'verification_photo' => $image,
                'error'              => $e->getMessage(),
            ]);

            return $this->rejectVerification('Verification processing error: ' . $e->getMessage(), 0.0);
        }
    }

    /**
     * Use Rekognition DetectFaces + DetectLabels to verify:
     * - Exactly one face present
     * - Acceptable brightness and sharpness
     * - Roughly frontal pose
     * - Thumbs-up gesture visible
     */
    private function checkLivenessAndGesture(string $imageBytes): array
    {
        try {
            // --- Face detection ---
            $detectResponse = $this->rekognition->detectFaces([
                'Image'      => ['Bytes' => $imageBytes],
                'Attributes' => ['ALL'],
            ]);

            $faceDetails = $detectResponse['FaceDetails'] ?? [];

            if (count($faceDetails) === 0) {
                return [
                    'success'    => false,
                    'reason'     => 'No face detected in verification photo',
                    'confidence' => 0.0,
                ];
            }

            if (count($faceDetails) > 1) {
                return [
                    'success'    => false,
                    'reason'     => 'Multiple faces detected – photo must contain exactly one person',
                    'confidence' => 0.0,
                ];
            }

            $face           = $faceDetails[0];
            $faceConfidence = ($face['Confidence'] ?? 0) / 100.0;
            $quality        = $face['Quality'] ?? [];
            $brightness     = $quality['Brightness'] ?? 0;
            $sharpness      = $quality['Sharpness'] ?? 0;

            if ($brightness < 20) {
                return [
                    'success'    => false,
                    'reason'     => 'Photo quality is insufficient – please ensure good lighting',
                    'confidence' => $faceConfidence,
                ];
            }

            if ($sharpness < 20) {
                return [
                    'success'    => false,
                    'reason'     => 'Photo is too blurry – please take a clearer photo',
                    'confidence' => $faceConfidence,
                ];
            }

            $pose  = $face['Pose'] ?? [];
            $yaw   = abs($pose['Yaw'] ?? 0);
            $pitch = abs($pose['Pitch'] ?? 0);

            if ($yaw > 45 || $pitch > 45) {
                return [
                    'success'    => false,
                    'reason'     => 'Face must be clearly visible – please look directly at the camera',
                    'confidence' => $faceConfidence,
                ];
            }

            // --- Thumbs-up gesture detection ---
            $labelsResponse = $this->rekognition->detectLabels([
                'Image'         => ['Bytes' => $imageBytes],
                'MaxLabels'     => 50,
                'MinConfidence' => 60,
            ]);

            $thumbsUpDetected = false;
            foreach ($labelsResponse['Labels'] ?? [] as $label) {
                $name = strtolower($label['Name'] ?? '');
                if (str_contains($name, 'thumbs up') || $name === 'thumb') {
                    $thumbsUpDetected = true;
                    break;
                }
            }

            if (!$thumbsUpDetected) {
                return [
                    'success'    => false,
                    'reason'     => 'Thumbs-up gesture is required – please show a clear thumbs-up',
                    'confidence' => $faceConfidence,
                ];
            }

            return [
                'success'    => true,
                'reason'     => 'Liveness check passed',
                'confidence' => $faceConfidence,
                'details'    => [
                    'face_confidence' => $face['Confidence'],
                    'brightness'      => $brightness,
                    'sharpness'       => $sharpness,
                ],
            ];

        } catch (AwsException $e) {
            Log::error('Rekognition DetectFaces/DetectLabels error', ['error' => $e->getMessage()]);
            return [
                'success'    => false,
                'reason'     => 'Verification service temporarily unavailable',
                'confidence' => 0.0,
            ];
        }
    }

    /**
     * Use Rekognition CompareFaces to match verification photo against each profile photo.
     */
    private function performFaceMatching(string $verificationImageBytes, array $profilePhotos): array
    {
        try {
            $photosToCompare = array_slice($profilePhotos, 0, 7);
            $unmatchedPhotos = [];
            $skippedPhotos   = [];
            $totalSimilarity = 0.0;
            $comparedCount   = 0;

            foreach ($photosToCompare as $index => $photoPath) {
                $profileBytes = $this->loadImageBytes($photoPath);

                if ($profileBytes === null) {
                    Log::warning('Could not load profile photo', ['path' => $photoPath]);
                    $skippedPhotos[] = [
                        'photo_number' => $index + 1,
                        'reason'       => 'Could not load photo',
                    ];
                    continue;
                }

                try {
                    $compareResponse = $this->rekognition->compareFaces([
                        'SourceImage'        => ['Bytes' => $verificationImageBytes],
                        'TargetImage'        => ['Bytes' => $profileBytes],
                        'SimilarityThreshold' => 0, // Fetch all results; filter below
                    ]);

                    $faceMatches = $compareResponse['FaceMatches'] ?? [];

                    if (empty($faceMatches)) {
                        // No face matched in target — if there were also no unmatched target
                        // faces it usually means the target has no detectable face; skip it.
                        $hasTargetFaces = !empty($compareResponse['UnmatchedFaces'] ?? []);

                        if ($hasTargetFaces) {
                            $unmatchedPhotos[] = [
                                'photo_number' => $index + 1,
                                'reason'       => 'Face does not match verification photo',
                                'confidence'   => 0.0,
                            ];
                        } else {
                            $skippedPhotos[] = [
                                'photo_number' => $index + 1,
                                'reason'       => 'No face visible in profile photo',
                            ];
                        }
                        $comparedCount++;
                        continue;
                    }

                    // Pick the highest-similarity match
                    $bestSimilarity = max(array_column(
                        array_map(fn($m) => ['s' => $m['Similarity'] ?? 0], $faceMatches),
                        's'
                    ));

                    if ($bestSimilarity >= $this->faceMatchThreshold) {
                        $totalSimilarity += $bestSimilarity;
                    } else {
                        $unmatchedPhotos[] = [
                            'photo_number' => $index + 1,
                            'reason'       => 'Face similarity too low (' . round($bestSimilarity, 1) . '%)',
                            'confidence'   => $bestSimilarity / 100.0,
                        ];
                    }

                    $comparedCount++;

                } catch (AwsException $e) {
                    // InvalidParameterException usually means no face in source/target
                    if ($e->getAwsErrorCode() === 'InvalidParameterException') {
                        $skippedPhotos[] = [
                            'photo_number' => $index + 1,
                            'reason'       => 'No face detected in profile photo',
                        ];
                    } else {
                        Log::warning('CompareFaces error', [
                            'photo_index' => $index,
                            'error'       => $e->getMessage(),
                        ]);
                        $skippedPhotos[] = [
                            'photo_number' => $index + 1,
                            'reason'       => 'Could not process photo',
                        ];
                    }
                }
            }

            if (!empty($unmatchedPhotos)) {
                return [
                    'success'         => false,
                    'reason'          => 'Face does not match all profile photos – '
                        . count($unmatchedPhotos) . ' photo(s) show a different person',
                    'confidence'      => 0.0,
                    'unmatched_photos' => $unmatchedPhotos,
                ];
            }

            if ($comparedCount === 0) {
                return [
                    'success'    => false,
                    'reason'     => 'No comparable profile photos found',
                    'confidence' => 0.0,
                ];
            }

            $matchedCount   = $comparedCount - count($skippedPhotos);
            $avgSimilarity  = $matchedCount > 0 ? ($totalSimilarity / $matchedCount) / 100.0 : 0.0;
            $skippedCount   = count($skippedPhotos);
            $successReason  = $skippedCount > 0
                ? "Face matches profile photos ($skippedCount photo(s) without a clear face were skipped)"
                : 'Face matches all profile photos';

            return [
                'success'       => true,
                'reason'        => $successReason,
                'confidence'    => $avgSimilarity,
                'skipped_photos' => $skippedPhotos,
            ];

        } catch (Exception $e) {
            Log::error('Face matching error', ['error' => $e->getMessage()]);
            return [
                'success'    => false,
                'reason'     => 'Face matching failed',
                'confidence' => 0.0,
            ];
        }
    }

    /**
     * Load raw image bytes from a local file path or a remote URL.
     */
    private function loadImageBytes(string $path): ?string
    {
        if (str_starts_with($path, 'http')) {
            $bytes = @file_get_contents($path);
            return $bytes !== false ? $bytes : null;
        }

        $candidates = [
            base_path($path),
            public_path($path),
            storage_path('app/' . $path),
            storage_path('app/public/' . $path),
        ];

        foreach ($candidates as $candidate) {
            if (file_exists($candidate)) {
                return file_get_contents($candidate);
            }
        }

        return null;
    }

    /**
     * Build a standard rejection response.
     */
    private function rejectVerification(string $reason, float $confidence): array
    {
        return [
            'status'     => 'rejected',
            'reason'     => $reason,
            'confidence' => $confidence,
        ];
    }
}

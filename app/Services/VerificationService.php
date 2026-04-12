<?php

namespace App\Services;

use Aws\Rekognition\RekognitionClient;
use Aws\Exception\AwsException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class VerificationService
{
    private RekognitionClient $rekognitionClient;

    public function __construct()
    {
        $this->rekognitionClient = new RekognitionClient([
            'region'      => config('services.aws.region', 'us-east-1'),
            'version'     => 'latest',
            'credentials' => [
                'key'    => config('services.aws.key'),
                'secret' => config('services.aws.secret'),
            ],
        ]);
    }

    /**
     * Analyze a verification selfie against the user's profile photos.
     *
     * Step 1: GPT-4o liveness/quality check on the selfie.
     * Step 2: AWS Rekognition CompareFaces against each profile photo.
     *
     * @param  string  $image          Relative path to the verification selfie
     * @param  array   $profilePhotos  Array of profile photo paths or URLs
     * @return array{status: string, reason: string, confidence: float, liveness: array, face_matching: array, unmatched_photos: array}
     */
    public function analyzeImage(string $image, array $profilePhotos): array
    {
        Log::info('[VerificationService] Starting verification', [
            'image'         => $image,
            'profile_count' => count($profilePhotos),
        ]);

        // --- Step 1: GPT-4o Liveness Check ---
        $livenessResult = $this->checkLiveness($image);

        if ($livenessResult['status'] === 'rejected') {
            return [
                'status'           => 'rejected',
                'reason'           => $livenessResult['reason'],
                'confidence'       => $livenessResult['confidence'],
                'liveness'         => $livenessResult,
                'face_matching'    => [],
                'unmatched_photos' => [],
            ];
        }

        $isReview = ($livenessResult['status'] === 'review');

        // --- Step 2: AWS Rekognition Face Matching ---
        // Runs even when liveness is 'review' (AI unavailable) so admin gets similarity scores
        $matchResult = $this->performFaceMatching($image, $profilePhotos);

        if ($isReview) {
            // AI liveness was unavailable — queue for manual admin review
            // Include face matching data so admin can make an informed decision
            return [
                'status'           => 'review',
                'reason'           => $livenessResult['reason'],
                'confidence'       => $livenessResult['confidence'],
                'liveness'         => $livenessResult,
                'face_matching'    => $matchResult,
                'unmatched_photos' => $matchResult['unmatched_photos'] ?? [],
            ];
        }

        if ($matchResult['status'] === 'rejected') {
            return [
                'status'           => 'rejected',
                'reason'           => $matchResult['reason'],
                'confidence'       => $matchResult['confidence'],
                'liveness'         => $livenessResult,
                'face_matching'    => $matchResult,
                'unmatched_photos' => $matchResult['unmatched_photos'] ?? [],
            ];
        }

        // --- Both checks passed ---
        $overallConfidence = round(
            ($livenessResult['confidence'] + $matchResult['confidence']) / 2,
            2
        );

        return [
            'status'           => 'approved',
            'reason'           => 'Verification passed: live selfie confirmed and face matched with profile photos.',
            'confidence'       => $overallConfidence,
            'liveness'         => $livenessResult,
            'face_matching'    => $matchResult,
            'unmatched_photos' => $matchResult['unmatched_photos'] ?? [],
        ];
    }

    /**
     * Use GPT-4o Vision to determine if the selfie is a genuine, live photo.
     */
    private function checkLiveness(string $image): array
    {
        $imageUrl = asset($image);

        Log::info('[VerificationService] Liveness check', ['url' => $imageUrl]);

        $prompt = <<<'PROMPT'
You are a strict identity verification system for a dating app. Analyze this selfie and return a JSON object with these exact fields:

{
  "is_real_person": bool,       // A real human face is present (not a drawing, avatar, or AI-generated)
  "single_person": bool,        // Exactly one person is in the photo
  "face_visible": bool,         // Face is clearly visible, not obscured or cut off
  "good_quality": bool,         // Image is sharp, well-lit, not blurry or too dark
  "is_live_photo": bool,        // Appears to be a live camera selfie (not a photo of a photo, not taken from a screen)
  "is_document_or_screenshot": bool, // True if this is a screenshot, scanned document, photo of a screen, or digitally manipulated image
  "confidence": float,          // Your overall confidence from 0.0 to 1.0
  "notes": string               // Brief explanation of your assessment
}

Be strict: reject screenshots, photos of screens, AI-generated images, photos of printed pictures, and images where the face is not clearly visible. Only return the JSON object, nothing else.
PROMPT;

        try {
            $response = Http::timeout(30)->withHeaders([
                'Authorization' => 'Bearer ' . config('services.openai.api_key'),
                'Content-Type'  => 'application/json',
            ])->post('https://api.openai.com/v1/chat/completions', [
                'model'      => 'gpt-4o',
                'max_tokens' => 500,
                'messages'   => [
                    [
                        'role'    => 'user',
                        'content' => [
                            [
                                'type' => 'text',
                                'text' => $prompt,
                            ],
                            [
                                'type'      => 'image_url',
                                'image_url' => [
                                    'url'    => $imageUrl,
                                    'detail' => 'high',
                                ],
                            ],
                        ],
                    ],
                ],
            ]);

            if (!$response->successful()) {
                Log::error('[VerificationService] OpenAI API error', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
                return $this->reviewVerification('Liveness check failed: AI service unavailable. Queued for manual review.', 0.0);
            }

            $content = $response->json('choices.0.message.content', '');
            $analysis = $this->extractJsonFromResponse($content);

            if (!$analysis) {
                Log::error('[VerificationService] Could not parse GPT response', ['content' => $content]);
                return $this->reviewVerification('Liveness check failed: could not parse AI response. Queued for manual review.', 0.0);
            }

            Log::info('[VerificationService] Liveness analysis', $analysis);

            // Validate all required fields
            $isReal       = $analysis['is_real_person'] ?? false;
            $singlePerson = $analysis['single_person'] ?? false;
            $faceVisible  = $analysis['face_visible'] ?? false;
            $goodQuality  = $analysis['good_quality'] ?? false;
            $isLivePhoto  = $analysis['is_live_photo'] ?? false;
            $isDocument   = $analysis['is_document_or_screenshot'] ?? true;
            $confidence   = (float) ($analysis['confidence'] ?? 0.0);
            $notes        = $analysis['notes'] ?? '';

            // All conditions must pass
            if ($isDocument) {
                return $this->rejectVerification('Photo appears to be a screenshot or photo of a screen.', $confidence);
            }
            if (!$isReal) {
                return $this->rejectVerification('No real person detected in the photo.', $confidence);
            }
            if (!$singlePerson) {
                return $this->rejectVerification('Photo must contain exactly one person.', $confidence);
            }
            if (!$faceVisible) {
                return $this->rejectVerification('Face is not clearly visible in the photo.', $confidence);
            }
            if (!$goodQuality) {
                return $this->rejectVerification('Photo quality is too low. Please use better lighting and hold the camera steady.', $confidence);
            }
            if (!$isLivePhoto) {
                return $this->rejectVerification('Photo does not appear to be a live selfie. Please take a fresh photo with your camera.', $confidence);
            }

            return [
                'status'     => 'passed',
                'reason'     => 'Liveness check passed.',
                'confidence' => $confidence,
                'analysis'   => $analysis,
            ];

        } catch (\Exception $e) {
            Log::error('[VerificationService] Liveness check exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return $this->reviewVerification('Liveness check failed: ' . $e->getMessage() . ' Queued for manual review.', 0.0);
        }
    }

    /**
     * Use AWS Rekognition CompareFaces to match the verification selfie against profile photos.
     */
    private function performFaceMatching(string $image, array $profilePhotos): array
    {
        $selfieBytes = $this->getImageBytes($image);

        if (!$selfieBytes) {
            return $this->rejectVerification('Could not read verification selfie image.', 0.0);
        }

        // Limit to 7 profile photos
        $photosToCheck = array_slice($profilePhotos, 0, 7);

        $matched         = 0;
        $skipped         = 0;
        $unmatched       = 0;
        $highestMatch    = 0.0;
        $unmatchedPhotos = [];
        $details         = [];

        foreach ($photosToCheck as $profilePhoto) {
            $profileBytes = $this->getImageBytes($profilePhoto);

            if (!$profileBytes) {
                Log::warning('[VerificationService] Could not read profile photo', ['photo' => $profilePhoto]);
                $skipped++;
                $details[] = [
                    'photo'  => $profilePhoto,
                    'result' => 'skipped',
                    'reason' => 'Could not read image file.',
                ];
                continue;
            }

            try {
                $result = $this->rekognitionClient->compareFaces([
                    'SourceImage'         => ['Bytes' => $selfieBytes],
                    'TargetImage'         => ['Bytes' => $profileBytes],
                    'SimilarityThreshold' => 50.0,
                ]);

                $faceMatches = $result['FaceMatches'] ?? [];

                if (!empty($faceMatches)) {
                    $similarity = (float) ($faceMatches[0]['Similarity'] ?? 0.0);
                    $highestMatch = max($highestMatch, $similarity);

                    if ($similarity >= 70.0) {
                        $matched++;
                        $details[] = [
                            'photo'      => $profilePhoto,
                            'result'     => 'matched',
                            'similarity' => round($similarity, 2),
                        ];
                    } else {
                        // Low similarity — face found but doesn't match well
                        $unmatched++;
                        $unmatchedPhotos[] = $profilePhoto;
                        $details[] = [
                            'photo'      => $profilePhoto,
                            'result'     => 'unmatched',
                            'similarity' => round($similarity, 2),
                            'reason'     => 'Face similarity too low (' . round($similarity, 1) . '%).',
                        ];
                    }
                } else {
                    // Faces detected but no match at all
                    $unmatchedFaces = $result['UnmatchedFaces'] ?? [];
                    if (!empty($unmatchedFaces)) {
                        $unmatched++;
                        $unmatchedPhotos[] = $profilePhoto;
                        $details[] = [
                            'photo'  => $profilePhoto,
                            'result' => 'unmatched',
                            'reason' => 'A different face was detected in this photo.',
                        ];
                    } else {
                        $skipped++;
                        $details[] = [
                            'photo'  => $profilePhoto,
                            'result' => 'skipped',
                            'reason' => 'No comparable faces found.',
                        ];
                    }
                }

            } catch (AwsException $e) {
                $errorCode = $e->getAwsErrorCode();

                if ($errorCode === 'InvalidParameterException') {
                    // No face detected in source or target — skip this photo (likely a landscape/group/object photo)
                    Log::info('[VerificationService] No face in image, skipping', [
                        'photo' => $profilePhoto,
                        'error' => $e->getMessage(),
                    ]);
                    $skipped++;
                    $details[] = [
                        'photo'  => $profilePhoto,
                        'result' => 'skipped',
                        'reason' => 'No face detected in this photo.',
                    ];
                } else {
                    Log::error('[VerificationService] AWS CompareFaces error', [
                        'photo' => $profilePhoto,
                        'code'  => $errorCode,
                        'error' => $e->getMessage(),
                    ]);
                    $skipped++;
                    $details[] = [
                        'photo'  => $profilePhoto,
                        'result' => 'skipped',
                        'reason' => 'Face comparison service error.',
                    ];
                }
            } catch (\Exception $e) {
                Log::error('[VerificationService] Face matching exception', [
                    'photo' => $profilePhoto,
                    'error' => $e->getMessage(),
                ]);
                $skipped++;
                $details[] = [
                    'photo'  => $profilePhoto,
                    'result' => 'skipped',
                    'reason' => 'Unexpected error during comparison.',
                ];
            }
        }

        Log::info('[VerificationService] Face matching summary', [
            'matched'  => $matched,
            'skipped'  => $skipped,
            'unmatched' => $unmatched,
            'highest'  => $highestMatch,
        ]);

        // --- Decision Logic ---
        // 1. Reject if we found a clearly different face AND no matches at all
        if ($unmatched > 0 && $matched === 0) {
            return [
                'status'           => 'rejected',
                'reason'           => 'Your verification selfie does not match your profile photos. Please ensure your profile photos show your face clearly.',
                'confidence'       => $highestMatch > 0 ? round($highestMatch, 2) : 0.0,
                'matched'          => $matched,
                'skipped'          => $skipped,
                'unmatched'        => $unmatched,
                'highest_match'    => round($highestMatch, 2),
                'details'          => $details,
                'unmatched_photos' => $unmatchedPhotos,
            ];
        }

        // 2. Reject if NO face could be found in ANY profile photo (all skipped)
        if ($matched === 0 && $skipped > 0) {
            return [
                'status'           => 'rejected',
                'reason'           => 'We couldn\'t find a clear face in any of your profile photos. Please ensure at least one profile photo clearly shows your face, then try again.',
                'confidence'       => 0.0,
                'matched'          => $matched,
                'skipped'          => $skipped,
                'unmatched'        => $unmatched,
                'highest_match'    => 0.0,
                'details'          => $details,
                'unmatched_photos' => $unmatchedPhotos,
            ];
        }

        // 3. At least one face matched — approve
        $confidence = round($highestMatch, 2);

        return [
            'status'           => 'passed',
            'reason'           => "Face matched with {$matched} profile photo(s). Highest similarity: {$highestMatch}%.",
            'confidence'       => $confidence,
            'matched'          => $matched,
            'skipped'          => $skipped,
            'unmatched'        => $unmatched,
            'highest_match'    => round($highestMatch, 2),
            'details'          => $details,
            'unmatched_photos' => $unmatchedPhotos,
        ];
    }

    /**
     * Read image bytes from a local path or remote URL.
     */
    private function getImageBytes(string $path): ?string
    {
        try {
            // Remote URL — convert app URLs to local paths first
            if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
                if (str_contains($path, 'app.inmessage.xyz')) {
                    $urlPath = parse_url($path, PHP_URL_PATH);
                    $localPath = base_path(ltrim($urlPath, '/'));
                    if (file_exists($localPath)) {
                        $bytes = file_get_contents($localPath);
                        return ($bytes !== false && strlen($bytes) > 100) ? $bytes : null;
                    }
                }
                $bytes = @file_get_contents($path);
                return ($bytes !== false && strlen($bytes) > 100) ? $bytes : null;
            }

            // Local path — resolve with base_path
            $fullPath = base_path($path);

            if (!file_exists($fullPath)) {
                // Fallback: try public_path
                $fullPath = public_path(ltrim($path, '/'));
            }

            if (!file_exists($fullPath)) {
                Log::warning('[VerificationService] Image file not found', ['path' => $path]);
                return null;
            }

            $bytes = file_get_contents($fullPath);
            return ($bytes !== false && strlen($bytes) > 100) ? $bytes : null;

        } catch (\Exception $e) {
            Log::error('[VerificationService] Failed to read image', [
                'path'  => $path,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Extract a JSON object from a potentially markdown-wrapped GPT response.
     */
    private function extractJsonFromResponse(string $content): ?array
    {
        // Try direct parse first
        $decoded = json_decode($content, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        // Try extracting from markdown code block
        if (preg_match('/```(?:json)?\s*(\{[\s\S]*?\})\s*```/', $content, $matches)) {
            $decoded = json_decode($matches[1], true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        // Try extracting any JSON object
        if (preg_match('/\{[\s\S]*\}/', $content, $matches)) {
            $decoded = json_decode($matches[0], true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return null;
    }

    /**
     * Build a standardized rejection result.
     */
    private function rejectVerification(string $reason, float $confidence): array
    {
        return [
            'status'     => 'rejected',
            'reason'     => $reason,
            'confidence' => $confidence,
        ];
    }

    /**
     * Build a standardized review result (AI unavailable — queue for manual admin review).
     */
    private function reviewVerification(string $reason, float $confidence): array
    {
        return [
            'status'     => 'review',
            'reason'     => $reason,
            'confidence' => $confidence,
        ];
    }
}

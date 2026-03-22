<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Exception;

class ImageModerationService
{
    private string $openaiApiKey;
    private string $model = 'gpt-4o-mini';
    
    public function __construct()
    {
        $this->openaiApiKey = env('OPENAI_API_KEY');
        
        if (empty($this->openaiApiKey)) {
            throw new Exception('OPENAI_API_KEY is not configured in .env');
        }
    }

    /**
     * Moderate an image and return decision
     *
     * @param string $localFilePath Local file path (e.g., public/uploads/images/users/1/profile.jpg)
     * @return array ['decision' => 'approved|rejected|review', 'reason' => string, 'confidence' => float, 'details' => array]
     */
    public function moderateImage(string $localFilePath, string $imageType): array
    {
        try {
            // Step 1: Basic validation
            $validationResult = $this->validateImage($localFilePath);
            if (!$validationResult['valid']) {
                return [
                    'decision' => 'rejected',
                    'reason' => $validationResult['reason'],
                    'confidence' => 1.0,
                    'details' => []
                ];
            }

            // Step 2: Hash generation
            $hash = $this->generatePerceptualHash($localFilePath);
            if ($this->isHashRejected($hash)) {
                return [
                    'decision' => 'rejected',
                    'reason' => 'duplicate_rejected_image',
                    'confidence' => 1.0,
                    'details' => ['hash' => $hash]
                ];
            }

            // Step 3: Face detection (soft rule - informational only)
            $faceCount = $this->detectFaceCount($localFilePath);

            // Step 4: OpenAI Vision classification
            $openaiResult = $this->classifyWithOpenAI($localFilePath, $imageType);
            
            if (!$openaiResult['success']) {
                Log::warning('OpenAI moderation failed, sending to review', [
                    'file' => $localFilePath,
                    'error' => $openaiResult['error']
                ]);
                return [
                    'decision' => 'review',
                    'reason' => 'openai_failure',
                    'confidence' => 0.0,
                    'details' => ['error' => $openaiResult['error']]
                ];
            }

            $analysis = $openaiResult['data'];

            // Step 5: Decision logic (different rules for profile vs gallery)
            $decision = $this->makeDecision($analysis, $hash, $imageType);

            // Log moderation result
            Log::info('Image moderation completed', [
                'file' => basename($localFilePath),
                'decision' => $decision['decision'],
                'reason' => $decision['reason'],
                'face_count' => $faceCount,
                'confidence' => $decision['confidence']
            ]);

            return $decision;

        } catch (Exception $e) {
            Log::error('Image moderation exception', [
                'file' => $localFilePath,
                'error' => $e->getMessage()
            ]);
            
            return [
                'decision' => 'review',
                'reason' => 'moderation_error',
                'confidence' => 0.0,
                'details' => ['error' => $e->getMessage()]
            ];
        }
    }

    /**
     * Validate image file
     */
    private function validateImage(string $filePath): array
    {
        $fullPath = base_path($filePath);

        if (!file_exists($fullPath)) {
            return ['valid' => false, 'reason' => 'file_not_found'];
        }

        $imageInfo = @getimagesize($fullPath);
        if ($imageInfo === false) {
            return ['valid' => false, 'reason' => 'invalid_image_file'];
        }

        $allowedTypes = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP];
        if (!in_array($imageInfo[2], $allowedTypes)) {
            return ['valid' => false, 'reason' => 'unsupported_image_type'];
        }

        $width = $imageInfo[0];
        $height = $imageInfo[1];
        if ($width < 300 || $height < 300) {
            return ['valid' => false, 'reason' => 'resolution_too_low'];
        }

        return ['valid' => true, 'reason' => ''];
    }

    /**
     * Generate perceptual hash for duplicate detection
     */
    private function generatePerceptualHash(string $filePath): string
    {
        $fullPath = base_path($filePath);
        $imageInfo = getimagesize($fullPath);
        
        switch ($imageInfo[2]) {
            case IMAGETYPE_JPEG:
                $image = imagecreatefromjpeg($fullPath);
                break;
            case IMAGETYPE_PNG:
                $image = imagecreatefrompng($fullPath);
                break;
            case IMAGETYPE_WEBP:
                $image = imagecreatefromwebp($fullPath);
                break;
            default:
                return md5_file($fullPath);
        }

        if (!$image) {
            return md5_file($fullPath);
        }

        // Create 8x8 grayscale thumbnail for difference hash (dHash)
        $resized = imagecreatetruecolor(9, 8);
        imagecopyresampled($resized, $image, 0, 0, 0, 0, 9, 8, imagesx($image), imagesy($image));
        
        $hash = '';
        for ($y = 0; $y < 8; $y++) {
            for ($x = 0; $x < 8; $x++) {
                $rgb1 = imagecolorat($resized, $x, $y);
                $rgb2 = imagecolorat($resized, $x + 1, $y);
                
                $gray1 = (($rgb1 >> 16) & 0xFF) * 0.299 + (($rgb1 >> 8) & 0xFF) * 0.587 + ($rgb1 & 0xFF) * 0.114;
                $gray2 = (($rgb2 >> 16) & 0xFF) * 0.299 + (($rgb2 >> 8) & 0xFF) * 0.587 + ($rgb2 & 0xFF) * 0.114;
                
                $hash .= ($gray1 > $gray2) ? '1' : '0';
            }
        }

        imagedestroy($image);
        imagedestroy($resized);

        return $hash;
    }

    /**
     * Check if hash was previously rejected
     */
    private function isHashRejected(string $hash): bool
    {
        return Cache::has('rejected_image_hash:' . $hash);
    }

    /**
     * Store rejected hash
     */
    private function storeRejectedHash(string $hash): void
    {
        Cache::put('rejected_image_hash:' . $hash, true, now()->addMonths(6));
    }

    /**
     * Detect face count (soft rule - informational only)
     */
    private function detectFaceCount(string $filePath): int
    {
        // Placeholder for face detection
        // Not mandatory - returns 1 as default assumption
        return 1;
    }

    /**
     * Classify image using OpenAI Vision API
     */
    private function classifyWithOpenAI(string $filePath, string $imageType = 'gallery'): array
    {
        try {
            // Generate public URL for the image
            $imageUrl = asset($filePath);

            // Different prompts for profile (strict) vs gallery (relaxed)
            if ($imageType === 'profile') {
                $prompt = $this->getProfilePrompt();
            } else {
                $prompt = $this->getGalleryPrompt();
            }


            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->openaiApiKey,
                'Content-Type' => 'application/json',
            ])->timeout(60)->post('https://api.openai.com/v1/chat/completions', [
                'model' => $this->model,
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => [
                            [
                                'type' => 'text',
                                'text' => $prompt
                            ],
                            [
                                'type' => 'image_url',
                                'image_url' => [
                                    'url' => $imageUrl
                                ]
                            ]
                        ]
                    ]
                ],
                'max_tokens' => 300,
                'temperature' => 0.1
            ]);

            if (!$response->successful()) {
                $errorBody = $response->json();
                Log::error('OpenAI API error details', [
                    'status' => $response->status(),
                    'error' => $errorBody,
                    'image_url' => $imageUrl
                ]);
                
                return [
                    'success' => false,
                    'error' => 'OpenAI API error: ' . $response->status() . ' - ' . ($errorBody['error']['message'] ?? 'Unknown error')
                ];
            }

            $content = $response->json()['choices'][0]['message']['content'] ?? '';
            
            // Extract JSON from response
            $content = trim($content);
            if (str_starts_with($content, '```json')) {
                $content = substr($content, 7);
                $content = substr($content, 0, strrpos($content, '```'));
            } elseif (str_starts_with($content, '```')) {
                $content = substr($content, 3);
                $content = substr($content, 0, strrpos($content, '```'));
            }
            
            $data = json_decode(trim($content), true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                return [
                    'success' => false,
                    'error' => 'Invalid JSON response from OpenAI'
                ];
            }

            return [
                'success' => true,
                'data' => $data
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Make final moderation decision
     */
    private function makeDecision(array $analysis, string $hash, string $imageType = 'gallery'): array
    {
        $decision = 'approved';
        $reason = 'passed_moderation';
        $confidence = $analysis['confidence'] ?? 0.5;

        // Use strict rules for profile, relaxed for gallery
        if ($imageType === 'profile') {
            return $this->makeProfileDecision($analysis, $hash, $confidence);
        } else {
            return $this->makeGalleryDecision($analysis, $hash, $confidence);
        }
    }

    /**
     * Strict decision rules for PROFILE images - must have clear face
     */
    private function makeProfileDecision(array $analysis, string $hash, float $confidence): array
    {
        $decision = 'approved';
        $reason = 'passed_moderation';

        // STRICT RULES FOR PROFILE IMAGES
        
        // 1. CRITICAL: Reject if no human face detected
        if (!isset($analysis['has_human_face']) || $analysis['has_human_face'] !== true) {
            $decision = 'rejected';
            $reason = 'no_human_face_detected';
            $this->storeRejectedHash($hash);
        }
        // 2. Reject if not a real person
        elseif (!isset($analysis['is_real_person']) || $analysis['is_real_person'] !== true) {
            $decision = 'rejected';
            $reason = 'not_real_person';
            $this->storeRejectedHash($hash);
        }
        // 3. Reject if document/screenshot
        elseif (isset($analysis['is_document_or_screenshot']) && $analysis['is_document_or_screenshot'] === true) {
            $decision = 'rejected';
            $reason = 'document_or_screenshot';
            $this->storeRejectedHash($hash);
        }
        // 4. Reject if NSFW
        elseif (isset($analysis['nsfw']) && $analysis['nsfw'] === true) {
            $decision = 'rejected';
            $reason = 'nsfw_content';
            $this->storeRejectedHash($hash);
        }
        // 5. Reject if likely public figure or model
        elseif (isset($analysis['likely_public_figure_or_model']) && $analysis['likely_public_figure_or_model'] === true) {
            $decision = 'rejected';
            $reason = 'public_figure_or_model';
            $this->storeRejectedHash($hash);
        }
        // 6. Reject if AI generated
        elseif (isset($analysis['ai_generated']) && $analysis['ai_generated'] === true) {
            $decision = 'rejected';
            $reason = 'ai_generated_image';
            $this->storeRejectedHash($hash);
        }
        // 7. Reject if not a personal photo
        elseif (isset($analysis['personal_photo']) && $analysis['personal_photo'] === false && $confidence > 0.5) {
            $decision = 'rejected';
            $reason = 'not_personal_photo';
            $this->storeRejectedHash($hash);
        }
        // 8. Reject if has watermark or text
        elseif (isset($analysis['watermark_or_text']) && $analysis['watermark_or_text'] === true) {
            $decision = 'rejected';
            $reason = 'watermark_or_text_detected';
            $this->storeRejectedHash($hash);
        }

        return [
            'decision' => $decision,
            'reason' => $reason,
            'confidence' => $confidence,
            'details' => $analysis
        ];
    }

    /**
     * Relaxed decision rules for GALLERY images - more flexible
     */
    private function makeGalleryDecision(array $analysis, string $hash, float $confidence): array
    {
        $decision = 'approved';
        $reason = 'passed_moderation';

        // RELAXED RULES FOR GALLERY IMAGES
        // Gallery can have: side profiles, artistic shots, full body without clear face, lifestyle photos
        
        // 1. Reject if document/screenshot (still reject these)
        if (isset($analysis['is_document_or_screenshot']) && $analysis['is_document_or_screenshot'] === true) {
            $decision = 'rejected';
            $reason = 'document_or_screenshot';
            $this->storeRejectedHash($hash);
        }
        // 2. Reject if NSFW (always reject)
        elseif (isset($analysis['nsfw']) && $analysis['nsfw'] === true) {
            $decision = 'rejected';
            $reason = 'nsfw_content';
            $this->storeRejectedHash($hash);
        }
        // 3. Reject if not a real person (cartoons, memes, drawings)
        elseif (!isset($analysis['is_real_person']) || $analysis['is_real_person'] !== true) {
            $decision = 'rejected';
            $reason = 'not_real_person';
            $this->storeRejectedHash($hash);
        }
        // 4. Reject if AI generated
        elseif (isset($analysis['ai_generated']) && $analysis['ai_generated'] === true) {
            $decision = 'rejected';
            $reason = 'ai_generated_image';
            $this->storeRejectedHash($hash);
        }
        // 5. Reject if likely public figure (celebrity/athlete photos)
        elseif (isset($analysis['likely_public_figure_or_model']) && $analysis['likely_public_figure_or_model'] === true) {
            $decision = 'rejected';
            $reason = 'public_figure_or_model';
            $this->storeRejectedHash($hash);
        }
        // NOTE: For gallery, we DO NOT reject for:
        // - No face visible (side profile, back, artistic shots OK)
        // - Watermarks/text (minor text OK)
        // - Not personal photo (lifestyle/artistic OK)

        return [
            'decision' => $decision,
            'reason' => $reason,
            'confidence' => $confidence,
            'details' => $analysis
        ];
    }

    /**
     * Get strict prompt for PROFILE images
     */
    private function getProfilePrompt(): string
    {
        return 'You are a moderator for a dating app PROFILE photo.
Profile photos must show a clear human face.

Analyze the image and respond ONLY in valid JSON format:
{
  "has_human_face": true/false,
  "is_real_person": true/false,
  "personal_photo": true/false,
  "likely_public_figure_or_model": true/false,
  "nsfw": true/false,
  "ai_generated": true/false,
  "watermark_or_text": true/false,
  "is_document_or_screenshot": true/false,
  "confidence": 0.0-1.0
}

RULES FOR PROFILE PHOTO:

1. "has_human_face" = TRUE if you can see a REAL HUMAN FACE clearly
   - Full body with visible face = TRUE
   - Selfies, portraits = TRUE
   - Unusual lighting but face visible = TRUE
   - Sunglasses/glasses OK = TRUE
   
   SET FALSE FOR:
   - No face at all (objects only: cars, shoes, landscapes)
   - Back of head, face completely hidden
   - Cartoons, drawings, memes

2. "is_real_person" = TRUE for photographs of real humans
   - FALSE for: drawings, cartoons, anime, memes, illustrations

3. "is_document_or_screenshot" = TRUE if you see:
   - Screenshots from apps (Facebook, Instagram, Pinterest, Twitter, TikTok, WhatsApp)
   - App UI elements: navigation bars, like buttons, status bars with time/battery
   - Memes with text overlays
   - Stock photo watermarks

4. "nsfw" = TRUE for nudity, sexual content

5. "likely_public_figure_or_model" = TRUE for celebrities, athletes in jerseys

6. "personal_photo" = TRUE for personal photos, FALSE for stock/celebrity photos';
    }

    /**
     * Get relaxed prompt for GALLERY images
     */
    private function getGalleryPrompt(): string
    {
        return 'You are a RELAXED moderator for a dating app GALLERY photo.
Gallery photos can be more artistic and flexible - they do NOT need to show a clear face.

Analyze the image and respond ONLY in valid JSON format:
{
  "has_human_face": true/false,
  "is_real_person": true/false,
  "personal_photo": true/false,
  "likely_public_figure_or_model": true/false,
  "nsfw": true/false,
  "ai_generated": true/false,
  "watermark_or_text": true/false,
  "is_document_or_screenshot": true/false,
  "confidence": 0.0-1.0
}

RELAXED RULES FOR GALLERY PHOTO:

1. "has_human_face" - Just indicate if a face is visible, but gallery photos WITHOUT face are OK
   - Side profiles = TRUE (face partially visible)
   - Back of person showing = FALSE but still acceptable for gallery

2. "is_real_person" = TRUE if photo contains a real human (even partial, back view, side view)
   - Side profile with only partial face = TRUE
   - Person from behind = TRUE
   - FALSE ONLY for: cartoons, drawings, anime, memes, pure landscapes with no human

3. "is_document_or_screenshot" = TRUE ONLY if you clearly see:
   - Screenshots from apps with visible UI (buttons, status bar, like buttons)
   - Memes with text overlays/captions
   - Stock photo watermarks
   
   FALSE for: normal photos, artistic photos, photos with filters or colored lighting

4. "nsfw" = TRUE for nudity, explicit content

5. "likely_public_figure_or_model" = TRUE only for obvious celebrities, athletes in jerseys

6. "personal_photo" = TRUE for most personal photos including artistic/lifestyle shots

IMPORTANT FOR GALLERY:
- Side profiles, back views = APPROVE (real person visible)
- Artistic/lifestyle photos = APPROVE
- Photos with unusual lighting/filters = APPROVE
- Only reject if it is clearly a screenshot/meme/fake or NSFW';
    }
}

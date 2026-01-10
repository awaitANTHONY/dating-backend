<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Exception;

class ImageModerationService
{
    private string $openaiApiKey;
    private string $model = 'gpt-5.2';

    public function __construct()
    {
        $this->openaiApiKey = env('OPENAI_API_KEY');

        if (empty($this->openaiApiKey)) {
            throw new Exception('OPENAI_API_KEY is not configured in .env');
        }
    }

    /**
     * Moderate an image and return decision
     */
    public function moderateImage(string $localFilePath): array
    {
        try {
            // Step 1: Validate image
            $validationResult = $this->validateImage($localFilePath);
            if (!$validationResult['valid']) {
                return [
                    'decision' => 'rejected',
                    'reason' => $validationResult['reason'],
                    'confidence' => 1.0,
                    'details' => []
                ];
            }

            // Step 2: Perceptual hash
            $hash = $this->generatePerceptualHash($localFilePath);
            if ($this->isHashRejected($hash)) {
                return [
                    'decision' => 'rejected',
                    'reason' => 'duplicate_rejected_image',
                    'confidence' => 1.0,
                    'details' => ['hash' => $hash]
                ];
            }

            // Step 3: Face detection (informational)
            $faceCount = $this->detectFaceCount($localFilePath);

            // Step 4: OpenAI Vision moderation
            $openaiResult = $this->classifyWithOpenAI($localFilePath);

            if (!$openaiResult['success']) {
                Log::warning('OpenAI moderation failed', [
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

            // Step 5: Final decision
            $decision = $this->makeDecision($analysis, $hash);

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

        if (!in_array($imageInfo[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP])) {
            return ['valid' => false, 'reason' => 'unsupported_image_type'];
        }

        if ($imageInfo[0] < 300 || $imageInfo[1] < 300) {
            return ['valid' => false, 'reason' => 'resolution_too_low'];
        }

        return ['valid' => true, 'reason' => ''];
    }

    /**
     * Generate perceptual hash (dHash)
     */
    private function generatePerceptualHash(string $filePath): string
    {
        $fullPath = base_path($filePath);
        $imageInfo = getimagesize($fullPath);

        $image = match ($imageInfo[2]) {
            IMAGETYPE_JPEG => imagecreatefromjpeg($fullPath),
            IMAGETYPE_PNG => imagecreatefrompng($fullPath),
            IMAGETYPE_WEBP => imagecreatefromwebp($fullPath),
            default => null
        };

        if (!$image) {
            return md5_file($fullPath);
        }

        $resized = imagecreatetruecolor(9, 8);
        imagecopyresampled($resized, $image, 0, 0, 0, 0, 9, 8, imagesx($image), imagesy($image));

        $hash = '';
        for ($y = 0; $y < 8; $y++) {
            for ($x = 0; $x < 8; $x++) {
                $c1 = imagecolorat($resized, $x, $y);
                $c2 = imagecolorat($resized, $x + 1, $y);

                $g1 = (($c1 >> 16) & 255) * 0.299 + (($c1 >> 8) & 255) * 0.587 + ($c1 & 255) * 0.114;
                $g2 = (($c2 >> 16) & 255) * 0.299 + (($c2 >> 8) & 255) * 0.587 + ($c2 & 255) * 0.114;

                $hash .= ($g1 > $g2) ? '1' : '0';
            }
        }

        imagedestroy($image);
        imagedestroy($resized);

        return $hash;
    }

    private function isHashRejected(string $hash): bool
    {
        return Cache::has('rejected_image_hash:' . $hash);
    }

    private function storeRejectedHash(string $hash): void
    {
        Cache::put('rejected_image_hash:' . $hash, true, now()->addMonths(6));
    }

    private function detectFaceCount(string $filePath): int
    {
        return 1; // Placeholder
    }

    /**
     * GPT-5.2 Vision Classification — FULL STRICT PROMPT
     */
    private function classifyWithOpenAI(string $filePath): array
    {
        try {
            $imageUrl = asset($filePath);

            $prompt = <<<PROMPT
You MUST follow the rules below EXACTLY and respond ONLY with valid JSON. No explanations.

You are an EXTREMELY STRICT moderator for a dating app profile photo system.
Your PRIMARY job is to REJECT everything except CLEAR, CLOSE-UP photos of REAL HUMAN FACES.

THIS IS A DATING APP - ONLY APPROVE PHOTOS WHERE YOU CAN SEE A PERSON'S FACE CLEARLY.

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

CRITICAL RULES - FOLLOW EXACTLY OR REJECT:

1. "is_document_or_screenshot" = TRUE IMMEDIATELY IF YOU SEE:
- Screenshots from ANY app or website (Instagram, Pinterest, Google Images, TikTok, Facebook, Twitter)
- App UI elements, browser bars, buttons, menus, status bars
- Website branding, URLs, headers, search results, image grids
- Social media indicators, usernames, likes, comments
- Stock photo watermarks (Getty, Shutterstock, iStock, Adobe)
- Professional sports photos, jerseys, uniforms
- News media layouts, captions
- Documents, IDs, receipts, cards
- Text overlays, hashtags, quotes, memes
- Collages, grids, before/after images

2. "has_human_face" = TRUE ONLY IF:
- A REAL HUMAN FACE is the main subject
- Face is CLEAR and CLOSE-UP
- Nose and mouth visible
- Person is primary focus
- NO UI or interface elements

SET FALSE FOR:
- Screenshots
- Sports photos
- Vehicles, dashboards
- Group photos
- Masks covering face
- Distant, dark, blurry images
- Cartoons, drawings, emojis
- Animals, objects, landscapes
- Celebrities, athletes, models

3. "is_real_person" = TRUE ONLY for real human photographs
4. "personal_photo" = TRUE ONLY for personal selfies or portraits
5. "likely_public_figure_or_model" = TRUE for athletes, celebrities, influencers

EXTREME REJECTION:
Reject ANY screenshot, UI, professional photo, athlete, text overlay, document, AI image.

When in doubt, SET is_document_or_screenshot = TRUE.
PROMPT;

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->openaiApiKey,
                'Content-Type' => 'application/json',
            ])->timeout(30)->post('https://api.openai.com/v1/responses', [
                'model' => $this->model,
                'input' => [
                    [
                        'role' => 'user',
                        'content' => [
                            ['type' => 'input_text', 'text' => $prompt],
                            ['type' => 'input_image', 'image_url' => $imageUrl]
                        ]
                    ]
                ],
                'max_output_tokens' => 300,
                'temperature' => 0.1
            ]);

            if (!$response->successful()) {
                return ['success' => false, 'error' => 'OpenAI API error'];
            }

            $content = trim($response->json()['output_text'] ?? '');
            $data = json_decode($content, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                return ['success' => false, 'error' => 'Invalid JSON'];
            }

            return ['success' => true, 'data' => $data];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Final decision logic
     */
    private function makeDecision(array $analysis, string $hash): array
    {
        $confidence = $analysis['confidence'] ?? 0.5;

        $reject = function ($reason) use ($analysis, $confidence, $hash) {
            $this->storeRejectedHash($hash);
            return [
                'decision' => 'rejected',
                'reason' => $reason,
                'confidence' => $confidence,
                'details' => $analysis
            ];
        };

        if (($analysis['has_human_face'] ?? false) !== true) return $reject('no_human_face_detected');
        if (($analysis['is_real_person'] ?? false) !== true) return $reject('not_real_person');
        if (($analysis['is_document_or_screenshot'] ?? false) === true) return $reject('document_or_screenshot');
        if (($analysis['nsfw'] ?? false) === true) return $reject('nsfw_content');
        if (($analysis['likely_public_figure_or_model'] ?? false) === true) return $reject('public_figure_or_model');
        if (($analysis['ai_generated'] ?? false) === true) return $reject('ai_generated_image');
        if (($analysis['personal_photo'] ?? true) === false && $confidence > 0.5) return $reject('not_personal_photo');
        if (($analysis['watermark_or_text'] ?? false) === true) return $reject('watermark_or_text_detected');

        return [
            'decision' => 'approved',
            'reason' => 'passed_moderation',
            'confidence' => $confidence,
            'details' => $analysis
        ];
    }
}

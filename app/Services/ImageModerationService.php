<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Exception;

class ImageModerationService
{
    private string $openaiApiKey;
    private string $model = 'gpt-4o';
    
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
    public function moderateImage(string $localFilePath): array
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
            $openaiResult = $this->classifyWithOpenAI($localFilePath);
            
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

            // Step 5: Decision logic
            $decision = $this->makeDecision($analysis, $hash);

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
    private function classifyWithOpenAI(string $filePath): array
    {
        try {
            $fullPath = base_path($filePath);
            
            // Generate public URL for the image
            $imageUrl = asset($filePath);

            $prompt = 'You are an EXTREMELY STRICT moderator for a dating app profile photo system.
Your PRIMARY job is to REJECT everything except CLEAR, CLOSE-UP photos of REAL HUMAN FACES.

THIS IS A DATING APP - ONLY APPROVE PHOTOS WHERE YOU CAN SEE A PERSON\'S FACE CLEARLY.

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

1. "is_document_or_screenshot" = TRUE **IMMEDIATELY** IF YOU SEE ANY OF THESE:
   ⚠️ SCREENSHOTS FROM ANY APP/WEBSITE (Pinterest, Instagram, Facebook, Twitter, TikTok, Google Images, etc.)
   ⚠️ App UI elements: navigation bars, buttons, back arrows, share buttons, menu icons, status bars
   ⚠️ Website elements: URLs, website headers, "Visit" buttons, "Save" buttons, "Share" options
   ⚠️ Social media indicators: usernames, @handles, follower counts, like buttons, comment sections
   ⚠️ Search results: Google Images, Bing Images, Pinterest grids, image search layouts
   ⚠️ Browser elements: address bars, tabs, bookmarks, browser chrome
   ⚠️ Phone UI: battery indicator, time, signal bars, notifications at top/bottom
   ⚠️ Professional sports photos: team jerseys, uniforms, official team photos, player headshots
   ⚠️ News/media photos: news site layouts, article headers, captions
   ⚠️ Stock photo watermarks: Getty Images, Shutterstock, iStock, Adobe Stock
   ⚠️ Documents: IDs, passports, receipts, cards, certificates
   ⚠️ Text overlays: hashtags (#), quotes, memes, motivational text, captions
   ⚠️ Multiple images in one: collages, grids, before/after comparisons
   
   EXAMPLE SCREENSHOTS TO ALWAYS REJECT:
   - Pinterest screenshots (red P logo, "Save" button, "Visit" button, Pinterest layout)
   - Instagram screenshots (heart icon, comment icon, username at top, IG story interface)
   - Google Images (grid of images, search bar visible, "Images" tab)
   - Sports websites (365Scores, ESPN, team logos, jersey numbers, stats)
   - Any image with visible app/website branding or UI elements

2. "has_human_face" = TRUE **ONLY** IF ALL THESE CONDITIONS ARE MET:
   ✓ You can see a REAL HUMAN FACE as the MAIN SUBJECT of the photo
   ✓ The face is CLEAR, CLOSE-UP, and takes up significant portion of the image
   ✓ You can see NOSE and MOUTH clearly visible
   ✓ Eyes can be covered by sunglasses/glasses - this is OK
   ✓ The person is the PRIMARY focus, not a background element
   ✓ NO UI elements, buttons, or app interfaces visible
   
   SET FALSE FOR (BE STRICT):
   ✗ ANY screenshot from apps/websites/browsers
   ✗ Professional sports photos (jerseys, team uniforms, official photos)
   ✗ Cars, vehicles, dashboards, speedometers, interiors
   ✗ Car selfies where the car dashboard/interior is more visible than the face
   ✗ Photos taken inside vehicles where steering wheel/dashboard is prominent
   ✗ Any photo where objects/environment dominate over the human face
   ✗ Face masks, medical masks, bandanas, scarves covering nose/mouth
   ✗ Back of head, side profiles where face is not clearly visible
   ✗ Distant shots, group photos, tiny faces
   ✗ Silhouettes, shadows, dark photos where face is unclear
   ✗ Objects: food, drinks, buildings, landscapes, animals, nature
   ✗ Emoji, cartoon faces, drawn faces, illustrated faces, icons
   ✗ Stock photos, professional photoshoots, magazine covers
   ✗ Celebrities, athletes, models, influencers, public figures
   
3. "is_real_person" = TRUE ONLY if this is a PHOTOGRAPH of a REAL HUMAN BEING
   - NOT drawings, cartoons, anime, AI art, digital art, paintings, illustrations
   
4. "personal_photo" = TRUE ONLY if this is clearly a PERSONAL SELFIE or PORTRAIT
   - NOT professional photos, stock images, celebrity photos, magazine covers, athlete photos
   - NOT screenshots from social media, image search, or any website
   - The photo should look like someone took it for dating/social media purposes
   - Must be a DIRECT photo, not a screenshot or saved image from internet

5. "likely_public_figure_or_model" = TRUE for:
   - Professional athletes (ANY sport - football, basketball, soccer, etc.)
   - Team jerseys, sports uniforms, official player photos
   - Celebrities, models, influencers with professional photos

EXTREME REJECTION CRITERIA - SET is_document_or_screenshot = TRUE IF ANY:
❌ ANY screenshot from Pinterest, Instagram, Facebook, Google, Twitter, TikTok, etc.
❌ Visible UI elements (buttons, icons, navigation bars, status bars)
❌ Visible app branding or website logos
❌ Professional sports photos (jerseys, uniforms, team photos)
❌ Phone interface elements (battery, time, signal bars, back button)
❌ "Visit" buttons, "Save" buttons, "Share" options
❌ Search result layouts, image grids, collages
❌ Any text overlays (hashtags, quotes, captions, watermarks)
❌ Professional photography studio backgrounds

REMEMBER: This is a DATING APP. People must upload ORIGINAL photos they took themselves.
REJECT all screenshots, saved images from internet, professional photos, and athlete photos.
When in doubt, SET is_document_or_screenshot = TRUE. Be EXTREMELY STRICT.';


            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->openaiApiKey,
                'Content-Type' => 'application/json',
            ])->timeout(30)->post('https://api.openai.com/v1/chat/completions', [
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
    private function makeDecision(array $analysis, string $hash): array
    {
        $decision = 'approved';
        $reason = 'passed_moderation';
        $confidence = $analysis['confidence'] ?? 0.5;

        // STRICT REJECTION RULES (in priority order)
        
        // 1. CRITICAL: Reject if no human face detected - THIS IS THE MOST IMPORTANT CHECK
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
        // 3. Reject if document/screenshot/receipt
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
        // 7. STRICT: Reject if not a personal photo (lowered threshold from 0.7 to 0.5)
        elseif (isset($analysis['personal_photo']) && $analysis['personal_photo'] === false && $confidence > 0.5) {
            $decision = 'rejected';
            $reason = 'not_personal_photo';
            $this->storeRejectedHash($hash);
        }
        // 8. STRICT: Reject if has watermark or text (changed from review to reject)
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
}

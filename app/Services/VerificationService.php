<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Exception;

class VerificationService
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
     * Analyze verification image against profile photos
     *
     * @param string $image Storage path to verification photo
     * @param array $profilePhotos Array of profile photo paths
     * @return array ['status' => 'approved|rejected', 'reason' => string, 'confidence' => float, ...]
     */
    public function analyzeImage(string $image, array $profilePhotos): array
    {
        try {
            // Validate verification photo exists
            $imagePath = base_path($image);
            if (!file_exists($imagePath)) {
                return $this->rejectVerification('Verification photo not found', 0.0);
            }

            // Get public URL for verification photo
            $verificationPhotoFullUrl = asset($image);

            // Step 1: Check verification photo quality and liveness
            $livenessCheck = $this->checkLivenessAndGesture($verificationPhotoFullUrl);

            if (!$livenessCheck['success']) {
                return $this->rejectVerification($livenessCheck['reason'], $livenessCheck['confidence']);
            }

            // Step 2: Face matching with profile photos
            $faceMatchResult = $this->performFaceMatching(
                $verificationPhotoFullUrl,
                $profilePhotos
            );

            if (!$faceMatchResult['success']) {
                return [
                    'status' => 'rejected',
                    'reason' => $faceMatchResult['reason'],
                    'confidence' => $faceMatchResult['confidence'],
                    'liveness_check' => $livenessCheck,
                    'face_match' => $faceMatchResult
                ];
            }

            // All checks passed - approve verification
            return [
                'status' => 'approved',
                'reason' => 'Verification successful',
                'confidence' => min($livenessCheck['confidence'], $faceMatchResult['confidence']),
                'liveness_check' => $livenessCheck,
                'face_match' => $faceMatchResult
            ];

        } catch (Exception $e) {
            Log::error('Verification analysis error', [
                'verification_photo' => $image,
                'error' => $e->getMessage()
            ]);

            return $this->rejectVerification('Verification processing error: ' . $e->getMessage(), 0.0);
        }
    }

    /**
     * Check liveness and thumbs-up gesture
     *
     * @param string $imageUrl
     * @return array
     */
    private function checkLivenessAndGesture(string $imageUrl): array
    {
        try {
            $prompt = 'You are an EXTREMELY STRICT verification system for a dating app identity verification. Your PRIMARY job is to REJECT everything except CLEAR, LIVE SELFIES with THUMBS-UP GESTURE.

Respond ONLY in valid JSON format:
{
  "is_real_person": true/false,
  "single_person": true/false,
  "face_visible": true/false,
  "thumbs_up_gesture": true/false,
  "good_quality": true/false,
  "is_live_photo": true/false,
  "is_document_or_screenshot": true/false,
  "confidence": 0.0-1.0,
  "notes": "brief explanation"
}

CRITICAL VERIFICATION RULES - FOLLOW EXACTLY OR REJECT:

1. "is_document_or_screenshot" = TRUE **IMMEDIATELY** IF YOU SEE ANY OF THESE:
   ⚠️ SCREENSHOTS FROM ANY APP/WEBSITE (Pinterest, Instagram, Facebook, Twitter, TikTok, Google Images, dating apps, etc.)
   ⚠️ App UI elements: navigation bars, buttons, back arrows, share buttons, menu icons, status bars, app headers
   ⚠️ Website elements: URLs, website headers, "Visit" buttons, "Save" buttons, "Share" options, browser elements
   ⚠️ Social media indicators: usernames, @handles, follower counts, like buttons, comment sections, story indicators
   ⚠️ Search results: Google Images, Bing Images, Pinterest grids, image search layouts, thumbnail grids
   ⚠️ Browser elements: address bars, tabs, bookmarks, browser chrome, scroll bars
   ⚠️ Phone UI: battery indicator, time at top, signal bars, WiFi icon, notifications, system UI
   ⚠️ Professional photos: studio backgrounds, professional lighting setups, modeling poses, magazine quality
   ⚠️ Sports photos: team jerseys, uniforms, official player photos, athlete headshots, sports events
   ⚠️ News/media photos: news site layouts, article headers, captions, watermarks
   ⚠️ Stock photos: Getty Images, Shutterstock, iStock, Adobe Stock watermarks
   ⚠️ Documents: IDs, passports, driver licenses, printed photos, photo frames
   ⚠️ Text overlays: hashtags (#), quotes, memes, motivational text, captions, dates
   ⚠️ Multiple images: collages, grids, before/after comparisons, split screens
   ⚠️ Screen photos: photos of computer monitors, TV screens, tablets, other phones
   ⚠️ Profile screenshots: dating app profiles, social media profiles, contact cards
   
   REJECT IMMEDIATELY IF ANY UI, APP, OR WEBSITE ELEMENTS VISIBLE!

2. "is_real_person" = TRUE only if this is a REAL PHOTOGRAPH of a REAL HUMAN taken RIGHT NOW
   - NOT screenshots from ANY source (social media, apps, websites, browsers)
   - NOT AI-generated, drawings, cartoons, digital art, anime, illustrations
   - NOT photos of photos (screen, printed photo, ID card, magazine, poster)
   - NOT professional photoshoots, stock images, modeling photos
   - MUST be a FRESH SELFIE taken specifically for verification

3. "single_person" = TRUE only if EXACTLY ONE person is visible in the photo
   - Reject if multiple people, group photos, or no person visible
   - No other faces in background

4. "face_visible" = TRUE only if you can CLEARLY see the person\'s FULL FACE
   - Face must be clear, well-lit, not blurry or dark
   - NOSE and MOUTH must be CLEARLY visible (REQUIRED)
   - Eyes, eyebrows, facial structure must be visible
   - Face should not be covered by hands, masks, scarves (except hand showing thumbs-up)
   - NO face masks, medical masks, bandanas covering nose/mouth
   - Profile views or partial faces = FALSE

5. "thumbs_up_gesture" = TRUE only if person is clearly making a THUMBS-UP gesture
   - The thumb must be pointing UP and clearly visible
   - The gesture must be clear and unambiguous
   - Hand must be in the frame showing thumbs-up
   - This is MANDATORY for liveness verification
   - NO other gestures accepted (peace sign, wave, point, etc.)

6. "good_quality" = TRUE only if photo meets quality standards
   - Not blurry, dark, overexposed, or pixelated
   - Person\'s face is main subject, not too distant
   - Natural or indoor lighting (not pitch dark)
   - Photo is recent and clear
   - Not taken through mirrors, windows, or other surfaces

7. "is_live_photo" = TRUE only if this appears to be a FRESH SELFIE taken JUST NOW
   - NOT a screenshot from social media, dating apps, or any website
   - NOT a professional photo, stock image, or modeling shot
   - NOT a saved photo from camera roll with UI elements
   - NOT a photo of a screen or printed image
   - Should look like someone JUST took it with their phone camera for verification
   - Simple background (home, room, outdoor) - NOT studio background

EXTREME REJECTION CRITERIA - SET is_document_or_screenshot = TRUE IF ANY:
❌ ANY screenshot from Pinterest, Instagram, Facebook, Google, Twitter, TikTok, dating apps, etc.
❌ Visible UI elements (buttons, icons, navigation bars, status bars, app headers)
❌ Visible app branding, website logos, or watermarks
❌ Professional sports photos, team jerseys, athlete uniforms
❌ Phone interface elements (battery, time, signal bars, back button, share button)
❌ "Visit" buttons, "Save" buttons, "Share" options, social media buttons
❌ Search result layouts, image grids, collages, multiple photos in one
❌ Any text overlays (hashtags, quotes, captions, dates, watermarks)
❌ Professional photography: studio backgrounds, modeling poses, magazine-quality lighting
❌ Browser elements, address bars, tabs, scroll bars
❌ Photos of screens (computer, TV, tablet, phone)
❌ Stock photo watermarks or professional photo service marks

STRICT REQUIREMENTS - ALL MUST BE TRUE TO PASS:
✓ Real person photographed live (not AI, cartoon, screenshot, or saved image)
✓ Exactly one person visible
✓ Face clearly visible with nose and mouth showing
✓ Thumbs-up gesture clearly visible
✓ Good photo quality (clear, well-lit)
✓ Fresh selfie taken just now (not screenshot, not professional, not saved)
✓ NO screenshots, UI elements, or app interfaces visible
✓ NO professional photos, modeling shots, or stock images

REMEMBER: This is IDENTITY VERIFICATION. We need a LIVE SELFIE with THUMBS-UP, not a screenshot or saved photo.
REJECT all screenshots, saved images from internet, professional photos, and photos with ANY UI elements.
When in doubt, SET is_document_or_screenshot = TRUE. Be EXTREMELY STRICT.

Confidence should be 0.8+ for approval, lower if uncertain.';

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->openaiApiKey,
                'Content-Type' => 'application/json',
            ])->timeout(30)->post('https://api.openai.com/v1/chat/completions', [
                'model' => $this->model,
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => [
                            ['type' => 'text', 'text' => $prompt],
                            ['type' => 'image_url', 'image_url' => ['url' => $imageUrl]]
                        ]
                    ]
                ],
                'max_tokens' => 300,
                'temperature' => 0.1
            ]);

            if (!$response->successful()) {
                return [
                    'success' => false,
                    'reason' => 'AI service temporarily unavailable',
                    'confidence' => 0.0
                ];
            }

            $content = $response->json()['choices'][0]['message']['content'] ?? '';
            $data = $this->extractJsonFromResponse($content);

            if (!$data) {
                return [
                    'success' => false,
                    'reason' => 'Failed to analyze verification photo',
                    'confidence' => 0.0
                ];
            }

            // Check all requirements
            $checks = [
                'is_document_or_screenshot' => 'Screenshot or saved image detected - please take a fresh live selfie',
                'is_real_person' => 'Photo must be of a real person, not AI-generated or illustration',
                'single_person' => 'Photo must contain exactly one person',
                'face_visible' => 'Face must be clearly visible with nose and mouth showing',
                'thumbs_up_gesture' => 'Thumbs-up gesture is required for verification',
                'good_quality' => 'Photo quality is insufficient - please ensure good lighting',
                'is_live_photo' => 'Photo must be a fresh selfie taken now, not a screenshot or saved image'
            ];

            // Check is_document_or_screenshot FIRST (critical security check)
            if (isset($data['is_document_or_screenshot']) && $data['is_document_or_screenshot'] === true) {
                return [
                    'success' => false,
                    'reason' => $checks['is_document_or_screenshot'],
                    'confidence' => $data['confidence'] ?? 0.0,
                    'details' => $data
                ];
            }

            // Check all other requirements
            foreach ($checks as $key => $errorMessage) {
                if ($key === 'is_document_or_screenshot') {
                    continue; // Already checked above
                }
                
                if (!isset($data[$key]) || $data[$key] !== true) {
                    return [
                        'success' => false,
                        'reason' => $errorMessage,
                        'confidence' => $data['confidence'] ?? 0.0,
                        'details' => $data
                    ];
                }
            }

            // All checks passed
            return [
                'success' => true,
                'reason' => 'Liveness check passed',
                'confidence' => $data['confidence'] ?? 0.8,
                'details' => $data
            ];

        } catch (Exception $e) {
            Log::error('Liveness check error', ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'reason' => 'Liveness verification failed',
                'confidence' => 0.0
            ];
        }
    }

    /**
     * Perform face matching between verification and profile photos
     *
     * @param string $verificationPhotoUrl
     * @param array $profilePhotos
     * @return array
     */
    private function performFaceMatching(string $verificationPhotoUrl, array $profilePhotos): array
    {
        try {
            // Convert profile photo paths to URLs
            $profilePhotoUrls = array_map(function ($path) {
                // Handle both storage paths and direct URLs
                if (str_starts_with($path, 'http')) {
                    return $path;
                }
                return asset($path);
            }, $profilePhotos);

            // Limit to 3 photos to avoid timeout and excessive API costs
            $photoUrlsToCompare = array_slice($profilePhotoUrls, 0, 3);

            // Check all photos in a single API call for efficiency
            $result = $this->checkMultiplePhotosMatch($verificationPhotoUrl, $photoUrlsToCompare);

            return $result;

        } catch (Exception $e) {
            Log::error('Face matching error', ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'reason' => 'Face matching failed',
                'confidence' => 0.0
            ];
        }
    }

    /**
     * Check if verification photo matches multiple profile photos in one call
     *
     * @param string $verificationPhotoUrl
     * @param array $profilePhotoUrls
     * @return array
     */
    private function checkMultiplePhotosMatch(string $verificationPhotoUrl, array $profilePhotoUrls): array
    {
        try {
            $prompt = 'You are a face matching expert for identity verification. Compare the verification photo with ALL profile photos provided.

Respond ONLY in valid JSON format:
{
  "all_photos_match": true/false,
  "overall_confidence": 0.0-1.0,
  "reasoning": "brief explanation",
  "profile_has_screenshot": true/false,
  "unmatched_photos": [
    {
      "photo_number": 1,
      "reason": "specific reason why this photo doesn\'t match",
      "confidence": 0.0-1.0
    }
  ]
}

CRITICAL PRE-CHECK - REJECT PROFILE PHOTOS ONLY IF:
⚠️ Profile photo is a SCREENSHOT from apps/websites with visible UI elements (buttons, navigation bars, status bars)
⚠️ Profile photo has social media watermarks (@username, Instagram logo, TikTok logo, etc.)
⚠️ Profile photo appears to be a celebrity, public figure, or model (recognizable famous person)
⚠️ Profile photo is clearly downloaded from internet with search result elements

SET "profile_has_screenshot" = TRUE ONLY if you see clear UI elements or obvious screenshots.

IMPORTANT: Good quality photos, professional-looking photos, or similar poses are ALLOWED if they show a real person.

MATCHING RULES - Focus on FACIAL FEATURES:

1. "all_photos_match" = TRUE if the person has the SAME FACIAL FEATURES across all photos:
   - SAME face shape (oval, round, square, heart-shaped)
   - SAME eye shape, size, and spacing
   - SAME nose shape and size
   - SAME mouth and lip shape
   - SAME facial bone structure (cheekbones, jawline, chin)
   - SAME skin tone and complexion
   - SAME gender and approximate age

2. IGNORE these differences (they are NORMAL and ALLOWED):
   - Different hairstyles, hair color, or hair length
   - Presence or absence of facial hair (beard, mustache)
   - Different makeup styles or amounts
   - Different lighting, angles, or photo quality
   - Different expressions (smiling, serious, etc.)
   - Different backgrounds or settings
   - Glasses vs no glasses
   - Different photo quality (professional vs casual)
   - Weight changes or aging (up to 5-10 years)

3. "overall_confidence" scoring:
   - 0.9-1.0 = Same person, facial features clearly match
   - 0.7-0.89 = Likely same person, minor variations in features
   - 0.5-0.69 = Uncertain, features somewhat similar
   - 0.0-0.49 = Different people, facial features do not match

4. "unmatched_photos" - Only list photos where facial features are CLEARLY DIFFERENT:
   - Different face shape or bone structure
   - Different eye shape or spacing
   - Different nose structure
   - Different ethnicity or gender
   - Clearly a different person

APPROVAL THRESHOLD: overall_confidence 0.7+ AND all_photos_match = true

CRITICAL: Focus ONLY on facial features that identify a person. Do NOT reject based on:
- Photo quality (professional vs casual)
- Styling differences (hair, makeup, clothing)
- Similar poses or backgrounds
- Good lighting or photo editing

Be LENIENT with legitimate users. Only reject if you are confident the facial features show DIFFERENT PEOPLE.

Compare facial structure carefully.';


            // Build image content for prompt
            $imageContent = [
                ['type' => 'text', 'text' => $prompt],
                ['type' => 'text', 'text' => 'VERIFICATION PHOTO (person to verify):'],
                ['type' => 'image_url', 'image_url' => ['url' => $verificationPhotoUrl]],
                ['type' => 'text', 'text' => 'PROFILE PHOTOS TO COMPARE (must all match verification photo):']
            ];

            foreach ($profilePhotoUrls as $index => $profileUrl) {
                $imageContent[] = [
                    'type' => 'text',
                    'text' => 'Profile Photo ' . ($index + 1) . ':'
                ];
                $imageContent[] = [
                    'type' => 'image_url',
                    'image_url' => ['url' => $profileUrl]
                ];
            }

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->openaiApiKey,
                'Content-Type' => 'application/json',
            ])->timeout(60)->post('https://api.openai.com/v1/chat/completions', [
                'model' => $this->model,
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => $imageContent
                    ]
                ],
                'max_tokens' => 500,
                'temperature' => 0.1
            ]);

            if (!$response->successful()) {
                Log::error('OpenAI API failed', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                return [
                    'success' => false,
                    'reason' => 'Face matching service temporarily unavailable',
                    'confidence' => 0.0
                ];
            }

            $content = $response->json()['choices'][0]['message']['content'] ?? '';
            $data = $this->extractJsonFromResponse($content);

            if (!$data) {
                return [
                    'success' => false,
                    'reason' => 'Failed to analyze photos',
                    'confidence' => 0.0
                ];
            }

            $allMatch = $data['all_photos_match'] ?? false;
            $confidence = $data['overall_confidence'] ?? 0.0;
            $reasoning = $data['reasoning'] ?? 'No reasoning provided';
            $hasScreenshot = $data['profile_has_screenshot'] ?? false;
            $unmatchedPhotos = $data['unmatched_photos'] ?? [];

            // Critical security check - reject if any profile photo is screenshot/fake
            if ($hasScreenshot) {
                return [
                    'success' => false,
                    'reason' => 'One or more profile photos appear to be screenshots or downloaded images',
                    'confidence' => 0.0,
                    'unmatched_photos' => array_map(function($photo) use ($profilePhotoUrls) {
                        $index = ($photo['photo_number'] ?? 1) - 1;
                        return [
                            'url' => $profilePhotoUrls[$index] ?? '',
                            'reason' => $photo['reason'] ?? 'Screenshot or downloaded image detected',
                            'confidence' => $photo['confidence'] ?? 0.0
                        ];
                    }, $unmatchedPhotos),
                    'details' => $data
                ];
            }

            // If not all match or confidence too low
            if (!$allMatch || $confidence < 0.7) {
                // Map photo numbers to URLs
                $unmatchedWithUrls = array_map(function($photo) use ($profilePhotoUrls) {
                    $index = ($photo['photo_number'] ?? 1) - 1;
                    return [
                        'url' => $profilePhotoUrls[$index] ?? '',
                        'reason' => $photo['reason'] ?? 'Face does not match',
                        'confidence' => $photo['confidence'] ?? 0.0
                    ];
                }, $unmatchedPhotos);

                return [
                    'success' => false,
                    'reason' => 'Face does not match all profile photos',
                    'confidence' => $confidence,
                    'unmatched_photos' => $unmatchedWithUrls,
                    'details' => $data
                ];
            }

            // All photos match
            return [
                'success' => true,
                'reason' => 'Face matches all profile photos',
                'confidence' => $confidence,
                'details' => $data
            ];

        } catch (Exception $e) {
            Log::error('Multiple photo match error', ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'reason' => 'Photo matching failed: ' . $e->getMessage(),
                'confidence' => 0.0
            ];
        }
    }

    /**
     * Extract JSON from OpenAI response
     *
     * @param string $content
     * @return array|null
     */
    private function extractJsonFromResponse(string $content): ?array
    {
        $content = trim($content);

        // Remove markdown code blocks if present
        if (str_starts_with($content, '```json')) {
            $content = substr($content, 7);
            $content = substr($content, 0, strrpos($content, '```'));
        } elseif (str_starts_with($content, '```')) {
            $content = substr($content, 3);
            $content = substr($content, 0, strrpos($content, '```'));
        }

        $data = json_decode(trim($content), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::error('JSON decode error', [
                'content' => $content,
                'error' => json_last_error_msg()
            ]);
            return null;
        }

        return $data;
    }

    /**
     * Create rejection response
     *
     * @param string $reason
     * @param float $confidence
     * @return array
     */
    private function rejectVerification(string $reason, float $confidence): array
    {
        return [
            'status' => 'rejected',
            'reason' => $reason,
            'confidence' => $confidence
        ];
    }
}

<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Exception;

class VerificationService
{
    private string $openaiApiKey;
    private string $model;

    public function __construct()
    {
        $this->openaiApiKey = config('services.openai.api_key');
        $this->model = config('services.openai.verification_model', 'gpt-4o-mini');

        if (empty($this->openaiApiKey)) {
            throw new Exception('OPENAI_API_KEY is not configured. Add it to your .env file.');
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
     * Check liveness and head-turn gesture
     *
     * @param string $imageUrl
     * @return array
     */
    private function checkLivenessAndGesture(string $imageUrl): array
    {
        try {
            $prompt = 'Analyze this photo and determine if it meets specific quality requirements. Respond in JSON format:

{
  "is_real_person": true/false,
  "single_person": true/false,
  "face_visible": true/false,
  "head_turn_gesture": true/false,
  "good_quality": true/false,
  "is_live_photo": true/false,
  "is_document_or_screenshot": true/false,
  "confidence": 0.0-1.0,
  "notes": "brief explanation"
}

REQUIREMENTS TO CHECK:

1. SCREENSHOT/DOCUMENT CHECK - Set "is_document_or_screenshot" = true if you detect:
   - Screenshots from apps or websites (visible UI, buttons, navigation bars)
   - Social media watermarks or app logos
   - Browser elements or phone UI elements
   - Professional studio photos or modeling shots
   - Photos of screens, documents, or printed images
   - Text overlays, hashtags, or captions
   - Image grids, collages, or multiple photos in one

2. REAL PERSON CHECK - Set "is_real_person" = true only if:
   - This appears to be a photograph of a real human
   - NOT AI-generated, cartoon, drawing, or illustration
   - NOT a photo of a photo or screen
   - Appears to be a fresh selfie taken with a camera

3. SINGLE PERSON - Set "single_person" = true only if:
   - Exactly one person visible in photo
   - No additional people in frame or background

4. FACE VISIBLE - Set "face_visible" = true only if:
   - Person\'s full face is clearly visible
   - Nose and mouth are clearly showing
   - Eyes and facial structure visible
   - Face not covered by hands, masks, or objects
   - Not a profile view or partial face

5. HEAD TURN GESTURE - Set "head_turn_gesture" = true only if:
   - Person appears to be turning their head slightly left or right
   - OR person is looking slightly to the side (not perfectly centered)
   - This is a LIVENESS gesture - be LENIENT. Even a slight head angle counts.
   - A natural selfie angle counts as a head turn

6. GOOD QUALITY - Set "good_quality" = true only if:
   - Photo is clear, not blurry or pixelated
   - Good lighting (not too dark or overexposed)
   - Person\'s face is the main subject
   - Recent and clear photo quality

7. LIVE PHOTO - Set "is_live_photo" = true only if:
   - Appears to be a fresh selfie taken just now
   - NOT a screenshot or saved image
   - NOT a professional or stock photo
   - Simple background (home, room, outdoor) not studio

ALL REQUIREMENTS MUST BE TRUE TO PASS (except is_document_or_screenshot must be FALSE).
Set confidence 0.8+ for clear pass, lower if uncertain.

Analyze objectively without identifying individuals.

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
   - Face should not be covered by hands, masks, scarves
   - NO face masks, medical masks, bandanas covering nose/mouth
   - Profile views or partial faces = FALSE

5. "head_turn_gesture" = TRUE only if person shows any sign of head turning left or right
   - Even a SLIGHT head angle or tilt counts as passing
   - A natural selfie angle (not perfectly straight) counts
   - Be LENIENT - this is just a liveness check, not a strict pose requirement

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
✓ Head turned slightly left or right (liveness gesture)
✓ Good photo quality (clear, well-lit)
✓ Fresh selfie taken just now (not screenshot, not professional, not saved)
✓ NO screenshots, UI elements, or app interfaces visible
✓ NO professional photos, modeling shots, or stock images

REMEMBER: This is IDENTITY VERIFICATION. We need a LIVE SELFIE with the person turning their head slightly, not a screenshot or saved photo.
REJECT all screenshots, saved images from internet, professional photos, and photos with ANY UI elements.
When in doubt, SET is_document_or_screenshot = TRUE. Be EXTREMELY STRICT on screenshots but LENIENT on head turn gesture.

Confidence should be 0.8+ for approval, lower if uncertain.';

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->openaiApiKey,
                'Content-Type' => 'application/json',
            ])->timeout(120)->post('https://api.openai.com/v1/chat/completions', [
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
                'head_turn_gesture' => 'Please turn your head slightly left or right for verification',
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

            // Limit to 7 photos to avoid excessive API costs while checking more photos
            $photoUrlsToCompare = array_slice($profilePhotoUrls, 0, 7);

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
            $prompt = 'You are a RELAXED image analysis system for a dating app verification.

Respond in JSON format:
{
  "all_photos_match": true/false,
  "overall_confidence": 0.0-1.0,
  "reasoning": "brief explanation",
  "profile_has_screenshot": true/false,
  "unmatched_photos": [{"photo_number": 1, "reason": "description", "confidence": 0.0-1.0}],
  "skipped_photos": [{"photo_number": 1, "reason": "no face visible - side/back view"}]
}

TASK: Compare the verification photo to profile photos. Be RELAXED and understanding.

IMPORTANT - GALLERY PHOTOS ARE ALLOWED:
Profile photos can be gallery-style images that may NOT show a clear face:
- Side profiles (face partially visible) = SKIP, do not mark as unmatched
- Back view photos = SKIP, do not mark as unmatched  
- Artistic/lifestyle photos without clear face = SKIP, do not mark as unmatched
- Photos with unusual lighting/filters = STILL TRY TO MATCH

SKIP vs UNMATCH:
- If a photo shows NO FACE or only partial view (back, side) = add to "skipped_photos", NOT "unmatched_photos"
- Only add to "unmatched_photos" if you can clearly see a DIFFERENT person\'s face

SCREENSHOT CHECK:
Set "profile_has_screenshot" = true ONLY for obvious:
- App UI elements (buttons, status bars, navigation)
- Memes with text overlays
- Stock photo watermarks
Normal photos with filters or colored lighting are NOT screenshots.

MATCHING CRITERIA (when face is visible):
Compare visual characteristics:
- Facial structure and proportions
- Eye, nose, mouth characteristics
- Skin tone and apparent gender/age

NORMAL VARIATIONS TO ALLOW:
- Different hairstyles, hair colors
- Facial hair changes
- Different makeup, expressions, angles
- Different lighting (including colored/unusual lighting)
- Glasses, sunglasses
- Photo quality differences
- Appearance changes over time

CONFIDENCE SCALE:
- 0.7+: Match (same person likely)
- 0.5-0.69: Uncertain but acceptable
- Below 0.5: Different person

RELAXED OUTPUT REQUIREMENTS:
- "all_photos_match" = true if all COMPARABLE photos (with visible faces) appear to match
- Photos without clear faces should be SKIPPED, not counted as unmatched
- Only mark as "unmatched" if you see a CLEARLY DIFFERENT person\'s face
- Be generous - when in doubt, give the benefit of the doubt

BE RELAXED: Dating app users have various photo styles. Only reject if clearly different person.';


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
            ])->timeout(120)->post('https://api.openai.com/v1/chat/completions', [
                'model' => $this->model,
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => $imageContent
                    ]
                ],
                'max_tokens' => 800,
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
                Log::error('Failed to parse face match response', [
                    'raw_content' => $content
                ]);
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
            $skippedPhotos = $data['skipped_photos'] ?? [];

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

            // RELAXED LOGIC: Only reject if there are ACTUAL unmatched photos (different faces)
            // Skipped photos (no face visible - side/back views) are OK for gallery-style images
            
            // If there are unmatched photos (clearly different people), reject
            if (!empty($unmatchedPhotos)) {
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
                    'reason' => 'Face does not match all profile photos - ' . count($unmatchedWithUrls) . ' photo(s) show a different person',
                    'confidence' => $confidence,
                    'unmatched_photos' => $unmatchedWithUrls,
                    'details' => $data
                ];
            }

            // RELAXED: Lower confidence threshold from 0.7 to 0.5
            // And only reject if all_photos_match is explicitly false AND confidence is very low
            if (!$allMatch && $confidence < 0.5) {
                return [
                    'success' => false,
                    'reason' => 'Confidence too low for verification',
                    'confidence' => $confidence,
                    'unmatched_photos' => [],
                    'details' => $data
                ];
            }

            // Reject if ALL photos were skipped (no face found in any profile photo)
            $totalPhotos = count($profilePhotoUrls);
            $skippedCount = count($skippedPhotos);
            if ($skippedCount >= $totalPhotos) {
                return [
                    'success' => false,
                    'reason' => 'We couldn\'t find a clear face in any of your profile photos. Please ensure at least one profile photo clearly shows your face, then try again.',
                    'confidence' => 0.0,
                    'unmatched_photos' => [],
                    'details' => $data
                ];
            }

            // All comparable photos match (skipped photos are OK if at least one matched)
            $skippedCount = count($skippedPhotos);
            $successReason = $skippedCount > 0 
                ? "Face matches profile photos ($skippedCount gallery-style photos without clear face were skipped)"
                : 'Face matches all profile photos';

            return [
                'success' => true,
                'reason' => $successReason,
                'confidence' => $confidence,
                'skipped_photos' => $skippedPhotos,
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

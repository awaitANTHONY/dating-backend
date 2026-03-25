<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class OpenAIImageMonitor
{
    private string $apiKey;
    private string $model = 'gpt-4o-mini';

    public function __construct()
    {
        $this->apiKey = config('services.openai.api_key');

        if (empty($this->apiKey)) {
            throw new Exception('OPENAI_API_KEY is not configured.');
        }
    }

    public function moderateBatch(array $imagePaths): array
    {
        if (empty($imagePaths)) {
            return [];
        }

        $content = $this->buildRequestContent($imagePaths);

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type'  => 'application/json',
            ])->timeout(120)->post('https://api.openai.com/v1/chat/completions', [
                'model'       => $this->model,
                'max_tokens'  => 2000,
                'temperature' => 0,
                'messages'    => [
                    [
                        'role'    => 'system',
                        'content' => 'You moderate dating app images. Respond ONLY valid JSON array. No explanation.',
                    ],
                    [
                        'role'    => 'user',
                        'content' => $content,
                    ],
                ],
            ]);

            if (!$response->successful()) {
                Log::error('OpenAIImageMonitor: API request failed', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
                return [];
            }

            $body = $response->json();
            $raw  = $body['choices'][0]['message']['content'] ?? '';

            return $this->parseResponse($raw, count($imagePaths));
        } catch (Exception $e) {
            Log::error('OpenAIImageMonitor: Exception during moderation', [
                'message' => $e->getMessage(),
            ]);
            return [];
        }
    }

    private function buildRequestContent(array $imagePaths): array
    {
        $content = [
            [
                'type' => 'text',
                'text' => 'Analyze each image carefully for a dating app. For each image at the given index return: {"index":<int>,"real_person":<bool>,"nsfw":<bool>,"ai_generated":<bool>,"celebrity":<bool>}. Rules: real_person=false if cartoon, object, animal, landscape, text-only, or no clear human face. nsfw=true if nudity, sexual content, underwear-only, or suggestive poses. ai_generated=true if image looks AI-generated, deepfake, or digitally manipulated. celebrity=true if the person is a known public figure. Return ONLY the JSON array.',
            ],
        ];

        foreach ($imagePaths as $index => $path) {
            $imageUrl = $this->resolveImageUrl($path);

            if ($imageUrl === null) {
                continue;
            }

            $content[] = [
                'type'      => 'image_url',
                'image_url' => [
                    'url'    => $imageUrl,
                    'detail' => 'low',
                ],
            ];
        }

        return $content;
    }

    private function resolveImageUrl(string $path): ?string
    {
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        return asset($path);
    }

    private function parseResponse(string $raw, int $expectedCount): array
    {
        $cleaned = trim($raw);
        $cleaned = preg_replace('/^```(?:json)?\s*/i', '', $cleaned);
        $cleaned = preg_replace('/\s*```$/i', '', $cleaned);
        $cleaned = trim($cleaned);

        $decoded = json_decode($cleaned, true);

        if (!is_array($decoded)) {
            Log::warning('OpenAIImageMonitor: Failed to parse response', [
                'raw' => $raw,
            ]);
            return [];
        }

        $results = [];
        foreach ($decoded as $item) {
            if (!isset($item['index'])) {
                continue;
            }

            $results[(int) $item['index']] = [
                'index'        => (int) $item['index'],
                'real_person'  => (bool) ($item['real_person'] ?? true),
                'nsfw'         => (bool) ($item['nsfw'] ?? false),
                'ai_generated' => (bool) ($item['ai_generated'] ?? false),
                'celebrity'    => (bool) ($item['celebrity'] ?? false),
            ];
        }

        return $results;
    }

    public function shouldReject(array $result): bool
    {
        if (!$result['real_person']) {
            return true;
        }
        if ($result['nsfw']) {
            return true;
        }
        if ($result['ai_generated']) {
            return true;
        }
        if ($result['celebrity']) {
            return true;
        }

        return false;
    }
}

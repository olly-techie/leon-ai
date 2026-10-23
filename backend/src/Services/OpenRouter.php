<?php

declare(strict_types=1);

namespace App\Services;

final class OpenRouter
{
    private string $apiKey;
    private string $fastModel;
    private string $strongModel;
    private string $siteUrl;
    private string $appName;

    public function __construct()
    {
        $this->apiKey    = getenv('OPENROUTER_API_KEY') ?: '';
        $this->fastModel = getenv('OPENROUTER_FAST_MODEL') ?: 'google/gemini-2.5-flash';
        $this->strongModel = getenv('OPENROUTER_STRONG_MODEL') ?: 'google/gemini-2.5-pro';
        $this->siteUrl   = getenv('OPENROUTER_SITE_URL') ?? 'http://localhost:5173';
        $this->appName   = getenv('OPENROUTER_APP_NAME') ?? 'Defence Panel';
    }

    /**
     * Chat completion. $tier: 'fast' | 'strong'
     * Returns assistant message text, or throws on failure.
     */
    public function chat(string $system, string $user, string $tier = 'fast', ?array $jsonSchema = null): string
    {
        $model = $tier === 'strong' ? $this->strongModel : $this->fastModel;
        // Panel turns are tiny JSON (~300 tokens); scan/scores need more headroom.
        $maxTokens = $tier === 'strong' ? 2000 : 800;

        $payload = [
            'model'       => $model,
            'max_tokens'  => $maxTokens,
            'messages'    => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user',   'content' => $user],
            ],
        ];

        if ($jsonSchema !== null) {
            $payload['response_format'] = [
                'type' => 'json_schema',
                'json_schema' => $jsonSchema,
            ];
        }

        $ch = curl_init('https://openrouter.ai/api/v1/chat/completions');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_TIMEOUT        => 60,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $this->apiKey,
                'Content-Type: application/json',
                'HTTP-Referer: ' . $this->siteUrl,
                'X-Title: ' . $this->appName,
            ],
            CURLOPT_POSTFIELDS     => json_encode($payload),
        ]);

        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code !== 200 || $resp === false) {
            throw new \RuntimeException("OpenRouter error (HTTP {$code}): {$resp}");
        }

        $data = json_decode($resp, true);
        return $data['choices'][0]['message']['content'] ?? '';
    }

    /**
     * Chat with JSON schema validation + one retry on invalid JSON.
     */
    public function chatJson(string $system, string $user, string $tier, array $jsonSchema): array
    {
        for ($attempt = 0; $attempt < 2; $attempt++) {
            $raw = $this->chat($system, $user, $tier, $jsonSchema);
            $decoded = json_decode($raw, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
        }
        throw new \RuntimeException('Model returned invalid JSON after retry');
    }
}

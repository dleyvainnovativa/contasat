<?php

namespace App\Services;

use GuzzleHttp\Client as HttpClient;
use RuntimeException;

/**
 * Minimal OpenAI client for structured extraction. Sends a system + user prompt
 * and requests a JSON object back (response_format: json_object), then decodes
 * it. Kept deliberately small — one job: turn text into structured JSON.
 */
class OpenAiClient
{
    private HttpClient $http;

    public function __construct()
    {
        $this->http = new HttpClient([
            'base_uri' => rtrim(config('openai.base_url'), '/') . '/',
            'timeout'  => config('openai.timeout'),
        ]);
    }

    /**
     * Ask the model to return a JSON object. Returns the decoded array.
     *
     * @throws RuntimeException on transport error, empty response, or invalid JSON.
     */
    public function extractJson(string $systemPrompt, string $userContent): array
    {
        $apiKey = config('openai.api_key');
        if (! $apiKey) {
            throw new RuntimeException('OPENAI_API_KEY no está configurado.');
        }

        try {
            $response = $this->http->post('chat/completions', [
                'headers' => [
                    'Authorization' => "Bearer {$apiKey}",
                    'Content-Type'  => 'application/json',
                ],
                'json' => [
                    'model'           => config('openai.model'),
                    'temperature'     => 0,               // deterministic extraction
                    'max_tokens'      => config('openai.max_output_tokens'),
                    'response_format' => ['type' => 'json_object'],
                    'messages'        => [
                        ['role' => 'system', 'content' => $systemPrompt],
                        ['role' => 'user',   'content' => $userContent],
                    ],
                ],
            ]);
        } catch (\Throwable $e) {
            throw new RuntimeException('Error al llamar a OpenAI: ' . $e->getMessage(), previous: $e);
        }

        $body = json_decode((string) $response->getBody(), true);
        $content = $body['choices'][0]['message']['content'] ?? null;

        if (! $content) {
            throw new RuntimeException('Respuesta vacía de OpenAI.');
        }

        $decoded = json_decode($content, true);
        if (! is_array($decoded)) {
            throw new RuntimeException('OpenAI no devolvió JSON válido.');
        }

        return $decoded;
    }
}

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
        $content = $this->postChat([
            'model'           => config('openai.model'),
            'temperature'     => 0,                       // deterministic extraction
            'max_tokens'      => config('openai.max_output_tokens'),
            'response_format' => ['type' => 'json_object'],
            'messages'        => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user',   'content' => $userContent],
            ],
        ]);

        return $this->decodeJson($content);
    }

    /**
     * Same as extractJson, but reads the PDF itself (multimodal) instead of text —
     * the fallback for scanned/image PDFs that carry no text layer. Sends the PDF
     * inline as base64, so nothing is rasterized locally.
     *
     * @throws RuntimeException on transport error, empty response, or invalid JSON.
     */
    public function extractJsonFromPdf(string $systemPrompt, string $userPrompt, string $pdfPath): array
    {
        $content = $this->postChat([
            'model'           => config('openai.vision_model', config('openai.model')),
            'temperature'     => 0,
            'max_tokens'      => config('openai.max_output_tokens'),
            'response_format' => ['type' => 'json_object'],
            'messages'        => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user',   'content' => $this->pdfUserContent($userPrompt, $pdfPath)],
            ],
        ]);

        return $this->decodeJson($content);
    }

    /**
     * Plain-text OCR of a PDF via the multimodal model — for documents where we
     * only need the text back (e.g. SAT constancias) rather than structured JSON.
     */
    public function ocrPdf(string $instruction, string $pdfPath): string
    {
        return $this->postChat([
            'model'       => config('openai.vision_model', config('openai.model')),
            'temperature' => 0,
            'max_tokens'  => config('openai.max_output_tokens'),
            'messages'    => [
                ['role' => 'user', 'content' => $this->pdfUserContent($instruction, $pdfPath)],
            ],
        ]);
    }

    /** Build a multimodal user message: an instruction plus the PDF inline. */
    private function pdfUserContent(string $instruction, string $pdfPath): array
    {
        if (! is_file($pdfPath)) {
            throw new RuntimeException('PDF no encontrado para la lectura por visión.');
        }

        $bytes = (string) file_get_contents($pdfPath);
        $max   = (int) config('openai.max_pdf_bytes', 15 * 1024 * 1024);
        if (strlen($bytes) > $max) {
            throw new RuntimeException('El PDF excede el tamaño máximo permitido para lectura por visión.');
        }

        return [
            ['type' => 'text', 'text' => $instruction],
            ['type' => 'file', 'file' => [
                'filename'  => basename($pdfPath),
                'file_data' => 'data:application/pdf;base64,' . base64_encode($bytes),
            ]],
        ];
    }

    /** POST a chat/completions payload and return the assistant message content. */
    private function postChat(array $payload): string
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
                'json' => $payload,
            ]);
        } catch (\Throwable $e) {
            throw new RuntimeException('Error al llamar a OpenAI: ' . $e->getMessage(), previous: $e);
        }

        $body    = json_decode((string) $response->getBody(), true);
        $content = $body['choices'][0]['message']['content'] ?? null;

        if (! $content) {
            throw new RuntimeException('Respuesta vacía de OpenAI.');
        }

        return $content;
    }

    private function decodeJson(string $content): array
    {
        $decoded = json_decode($content, true);
        if (! is_array($decoded)) {
            throw new RuntimeException('OpenAI no devolvió JSON válido.');
        }

        return $decoded;
    }
}

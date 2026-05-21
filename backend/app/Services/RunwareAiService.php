<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Runware AI Service — gọi OpenAI-compatible Chat Completions API.
 *
 * Laravel AI SDK v0.7 dùng Responses API (/v1/responses) với format `input`,
 * Runware chỉ hỗ trợ Chat Completions (/v1/chat/completions) với format `messages`.
 * Service này bypass SDK để gọi trực tiếp.
 *
 * Hỗ trợ:
 * - Text-only: chatJson(systemPrompt, userMessage)
 * - Multimodal: chatMultimodal(systemPrompt, parts) — text + image + audio
 * - Raw: chat(messages) — full control
 */
class RunwareAiService
{
    private const BASE_URL = 'https://api.runware.ai/v1';
    private const DEFAULT_MODEL = 'google:gemini@3.1-flash-lite';
    private const DEFAULT_TIMEOUT = 30;

    private string $apiKey;
    private string $model;

    public function __construct()
    {
        $this->apiKey = config('services.runware.api_key', env('RUNWARE_API_KEY', ''));
        $this->model = config('services.runware.model', self::DEFAULT_MODEL);
    }

    /**
     * Text-only: Gửi system prompt + user text, parse JSON response.
     *
     * @param string $systemPrompt System instructions cho AI
     * @param string $userMessage Input text cần xử lý
     * @param float $temperature 0 = deterministic, 1 = creative
     * @param int $maxTokens Token limit cho response
     * @return array|null Parsed JSON response, hoặc null nếu lỗi
     *
     * @throws \RuntimeException Khi API trả lỗi nghiêm trọng (auth, rate limit)
     */
    public function chatJson(
        string $systemPrompt,
        string $userMessage,
        float $temperature = 0,
        int $maxTokens = 4096,
    ): ?array {
        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $userMessage],
        ];

        return $this->sendAndParseJson($messages, $temperature, $maxTokens);
    }

    /**
     * Multimodal: Gửi system prompt + content parts (text, image, audio).
     *
     * OpenAI-compatible format cho multimodal:
     * - Text: ['type' => 'text', 'text' => '...']
     * - Image URL: ['type' => 'image_url', 'image_url' => ['url' => 'https://...']]
     * - Image base64: ['type' => 'image_url', 'image_url' => ['url' => 'data:image/jpeg;base64,...']]
     * - Audio URL: ['type' => 'input_audio', 'input_audio' => ['data' => base64, 'format' => 'mp3']]
     *
     * @param string $systemPrompt System instructions
     * @param array $parts Array of content parts (text + image + audio)
     * @param float $temperature Mức sáng tạo
     * @param int $maxTokens Token limit
     * @return array|null Parsed JSON response
     *
     * @throws \RuntimeException Khi API lỗi nghiêm trọng
     */
    public function chatMultimodal(
        string $systemPrompt,
        array $parts,
        float $temperature = 0,
        int $maxTokens = 4096,
    ): ?array {
        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $parts],
        ];

        return $this->sendAndParseJson($messages, $temperature, $maxTokens);
    }

    /**
     * Raw chat: Full control — tự build messages array.
     *
     * @param array $messages Array of message objects [{role, content}, ...]
     * @param float $temperature Mức sáng tạo
     * @param int $maxTokens Token limit
     * @return string|null Raw text content từ AI
     *
     * @throws \RuntimeException Khi API lỗi nghiêm trọng
     */
    public function chat(
        array $messages,
        float $temperature = 0,
        int $maxTokens = 4096,
    ): ?string {
        $response = $this->sendRequest($messages, $temperature, $maxTokens);

        if (!$response) {
            return null;
        }

        return $response['choices'][0]['message']['content'] ?? null;
    }

    /**
     * Helper: Tạo image_url content part từ URL hoặc base64.
     */
    public static function imageUrl(string $url): array
    {
        return [
            'type' => 'image_url',
            'image_url' => ['url' => $url],
        ];
    }

    /**
     * Helper: Tạo image_url content part từ local file (base64 encode).
     */
    public static function imageFile(string $path, string $mimeType = 'image/jpeg'): array
    {
        $base64 = base64_encode(file_get_contents($path));

        return [
            'type' => 'image_url',
            'image_url' => ['url' => "data:{$mimeType};base64,{$base64}"],
        ];
    }

    /**
     * Helper: Tạo text content part.
     */
    public static function text(string $text): array
    {
        return ['type' => 'text', 'text' => $text];
    }

    /**
     * Helper: Tạo audio content part từ base64 data.
     */
    public static function audioBase64(string $base64Data, string $format = 'mp3'): array
    {
        return [
            'type' => 'input_audio',
            'input_audio' => [
                'data' => $base64Data,
                'format' => $format,
            ],
        ];
    }

    /**
     * Helper: Tạo audio content part từ local file.
     */
    public static function audioFile(string $path, string $format = 'mp3'): array
    {
        $base64 = base64_encode(file_get_contents($path));

        return self::audioBase64($base64, $format);
    }

    /**
     * Cho phép override model tạm thời (ví dụ dùng model lớn hơn cho task phức tạp).
     */
    public function withModel(string $model): static
    {
        $clone = clone $this;
        $clone->model = $model;

        return $clone;
    }

    // =========================================================================
    // Private methods
    // =========================================================================

    /**
     * Gửi request và parse JSON từ response content.
     */
    private function sendAndParseJson(
        array $messages,
        float $temperature,
        int $maxTokens,
    ): ?array {
        $data = $this->sendRequest($messages, $temperature, $maxTokens);

        if (!$data) {
            return null;
        }

        $content = $data['choices'][0]['message']['content'] ?? null;

        if (!$content) {
            Log::warning('Runware API empty content', [
                'response' => $data,
            ]);
            return null;
        }

        return $this->parseJsonFromContent($content);
    }

    /**
     * Gửi HTTP request tới Runware Chat Completions API.
     *
     * @return array|null Raw JSON response data
     * @throws \RuntimeException Khi lỗi auth/rate limit
     */
    private function sendRequest(
        array $messages,
        float $temperature,
        int $maxTokens,
    ): ?array {
        if (empty($this->apiKey)) {
            Log::error('Runware API key is empty. Set RUNWARE_API_KEY in .env');
            return null;
        }

        $response = Http::timeout(self::DEFAULT_TIMEOUT)
            ->withHeaders([
                'Authorization' => "Bearer {$this->apiKey}",
                'Content-Type' => 'application/json',
            ])
            ->post(self::BASE_URL . '/chat/completions', [
                'model' => $this->model,
                'temperature' => $temperature,
                'max_tokens' => $maxTokens,
                'messages' => $messages,
            ]);

        // Lỗi auth → throw để caller xử lý (job fail, retry)
        if ($response->status() === 401 || $response->status() === 403) {
            throw new \RuntimeException(
                "Runware API auth error: HTTP {$response->status()} - " . mb_substr($response->body(), 0, 300)
            );
        }

        // Rate limit → throw để job retry sau backoff
        if ($response->status() === 429) {
            throw new \RuntimeException(
                "Runware API rate limit exceeded: " . mb_substr($response->body(), 0, 300)
            );
        }

        if ($response->failed()) {
            Log::warning('Runware API request failed', [
                'status' => $response->status(),
                'body' => mb_substr($response->body(), 0, 500),
            ]);
            return null;
        }

        return $response->json();
    }

    /**
     * Parse JSON từ AI response content.
     * Xử lý các case: pure JSON, markdown ```json block, text kèm JSON.
     */
    private function parseJsonFromContent(string $content): ?array
    {
        $content = trim($content);

        // Case 1: Pure JSON
        $decoded = json_decode($content, true);
        if ($decoded !== null) {
            return $decoded;
        }

        // Case 2: Markdown ```json ... ``` block
        if (preg_match('/```(?:json)?\s*\n?(.*?)\n?\s*```/s', $content, $matches)) {
            $decoded = json_decode(trim($matches[1]), true);
            if ($decoded !== null) {
                return $decoded;
            }
        }

        // Case 3: Tìm JSON object/array trong text
        if (preg_match('/(\{[\s\S]*\}|\[[\s\S]*\])/', $content, $matches)) {
            $decoded = json_decode($matches[1], true);
            if ($decoded !== null) {
                return $decoded;
            }
        }

        Log::warning('Runware AI: cannot parse JSON from response', [
            'content' => mb_substr($content, 0, 500),
        ]);

        return null;
    }
}

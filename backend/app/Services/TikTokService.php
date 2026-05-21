<?php

namespace App\Services;

use App\Exceptions\TikTokSessionNotFoundException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TikTokService
{
    private string $baseUrl;

    private string $apiKey;

    private int $timeout;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.tiktok.url', 'http://127.0.0.1:50000'), '/');
        $this->apiKey = config('services.tiktok.api_key', '');
        $this->timeout = (int) config('services.tiktok.timeout', 15);
    }

    /**
     * Bắt đầu theo dõi một phiên TikTok LIVE.
     *
     * @return array{session_id: string, status: string}|null
     */
    public function startSession(string $tiktokUsername, ?string $sessionId = null): ?array
    {
        $payload = [
            'tiktok_username' => $tiktokUsername,
        ];
        if ($sessionId) {
            $payload['session_id'] = $sessionId;
        }

        return $this->post('/sessions/start', $payload);
    }

    /**
     * Lấy thông tin session + events từ Python service.
     */
    public function getSession(string $sessionId, bool $includeEvents = true, int $limit = 200, float $since = 0): ?array
    {
        $params = [
            'include_events' => $includeEvents ? 'true' : 'false',
            'limit' => $limit,
        ];
        if ($since > 0) {
            $params['since'] = $since;
        }

        return $this->get("/sessions/{$sessionId}", $params);
    }

    /**
     * Lấy thống kê session.
     */
    public function getStats(string $sessionId): ?array
    {
        return $this->get("/sessions/{$sessionId}/stats");
    }

    /**
     * Dừng theo dõi session.
     */
    public function stopSession(string $sessionId): ?array
    {
        return $this->post("/sessions/{$sessionId}/stop");
    }

    /**
     * Health check Python service.
     */
    public function healthCheck(): bool
    {
        try {
            $response = $this->client()->get($this->baseUrl.'/health');

            return $response->successful();
        } catch (ConnectionException $e) {
            Log::warning('TikTok service health check failed', ['error' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * Capture snapshot (ảnh + audio) từ live stream cho AI phân tích.
     *
     * @return array{image_b64: ?string, audio_b64: ?string, title: ?string}|null
     */
    public function getSnapshot(string $sessionId): ?array
    {
        try {
            // Timeout cao hơn vì FFmpeg capture mất vài giây
            $response = Http::timeout(20)
                ->withHeaders(['X-Service-Key' => $this->apiKey])
                ->get($this->baseUrl."/sessions/{$sessionId}/snapshot");

            if ($response->failed()) {
                Log::warning('TikTok snapshot failed', [
                    'session_id' => $sessionId,
                    'status' => $response->status(),
                ]);

                return null;
            }

            return $response->json();
        } catch (ConnectionException $e) {
            Log::warning('TikTok snapshot connection failed', ['error' => $e->getMessage()]);

            return null;
        }
    }

    // --- Internal ---

    private function client()
    {
        return Http::timeout($this->timeout)
            ->withHeaders([
                'X-Service-Key' => $this->apiKey,
            ]);
    }

    private function get(string $path, array $query = []): ?array
    {
        try {
            $response = $this->client()->get($this->baseUrl.$path, $query);

            if ($response->failed()) {
                if ($response->status() === 404) {
                    throw new TikTokSessionNotFoundException('TikTok session not found on service');
                }

                Log::error('TikTok service GET failed', [
                    'path' => $path,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return null;
            }

            return $response->json();
        } catch (ConnectionException $e) {
            Log::error('TikTok service connection failed', [
                'path' => $path,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function post(string $path, array $data = []): ?array
    {
        try {
            $response = $this->client()->post($this->baseUrl.$path, $data);

            if ($response->failed()) {
                if ($response->status() === 404) {
                    throw new TikTokSessionNotFoundException('TikTok session not found on service');
                }

                Log::error('TikTok service POST failed', [
                    'path' => $path,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return null;
            }

            return $response->json();
        } catch (ConnectionException $e) {
            Log::error('TikTok service connection failed', [
                'path' => $path,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}

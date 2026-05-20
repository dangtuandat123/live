<?php

namespace App\Services;

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
    public function startSession(string $tiktokUsername): ?array
    {
        return $this->post('/sessions/start', [
            'tiktok_username' => $tiktokUsername,
        ]);
    }

    /**
     * Lấy thông tin session + events từ Python service.
     *
     * @return array|null
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
     *
     * @return array|null
     */
    public function getStats(string $sessionId): ?array
    {
        return $this->get("/sessions/{$sessionId}/stats");
    }

    /**
     * Dừng theo dõi session.
     *
     * @return array|null
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
            $response = $this->client()->get($this->baseUrl . '/health');
            return $response->successful();
        } catch (ConnectionException $e) {
            Log::warning('TikTok service health check failed', ['error' => $e->getMessage()]);
            return false;
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
            $response = $this->client()->get($this->baseUrl . $path, $query);

            if ($response->failed()) {
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
            $response = $this->client()->post($this->baseUrl . $path, $data);

            if ($response->failed()) {
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

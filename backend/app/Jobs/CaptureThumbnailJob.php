<?php

namespace App\Jobs;

use App\Models\LiveSession;
use App\Services\TikTokService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;

class CaptureThumbnailJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 60;
    public array $backoff = [10, 30];
    public int $uniqueFor = 60; // Khóa unique trong 60 giây

    public function uniqueId(): string
    {
        return 'capture-thumbnail-' . $this->liveSessionId;
    }

    public function __construct(
        private int $liveSessionId,
        private ?string $coverUrl = null
    ) {}

    public function handle(TikTokService $tiktokService): void
    {
        $session = LiveSession::find($this->liveSessionId);
        if (!$session) {
            return;
        }

        if (in_array($session->status, ['ended', 'error'])) {
            Log::info("CaptureThumbnailJob skipped for session {$this->liveSessionId} because its status is: {$session->status}");
            return;
        }

        // Cách 1: Tải trực tiếp từ coverUrl nhận từ TikTok API
        if ($this->coverUrl) {
            try {
                Log::info("Downloading cover image for session {$this->liveSessionId} from: {$this->coverUrl}");
                $response = Http::timeout(15)->get($this->coverUrl);
                if ($response->successful() && $response->header('Content-Type') !== 'text/html') {
                    $this->updateThumbnail($session, $response->body());
                    Log::info("Successfully saved cover image thumbnail for session {$this->liveSessionId}");
                    return;
                }
            } catch (\Exception $e) {
                Log::warning("Failed to download cover image from URL: " . $e->getMessage());
            }
        }

        // Cách 2: Fallback - Gọi Python service snapshot (FFmpeg capture frame)
        if ($session->tiktok_session_id) {
            try {
                Log::info("Falling back to FFmpeg snapshot for session {$this->liveSessionId}");
                $snapshot = $tiktokService->getSnapshot($session->tiktok_session_id);
                if ($snapshot && !empty($snapshot['image_b64'])) {
                    $imageData = base64_decode($snapshot['image_b64']);
                    $this->updateThumbnail($session, $imageData);
                    Log::info("Successfully captured FFmpeg thumbnail for session {$this->liveSessionId}");
                    return;
                }
            } catch (\Exception $e) {
                Log::error("Failed to capture FFmpeg thumbnail: " . $e->getMessage());
            }
        }

        // Cách 3: Fallback cuối cùng - Dùng avatar của streamer nếu có thông tin từ Python service stats
        if ($session->tiktok_session_id) {
            try {
                $serviceData = $tiktokService->getStats($session->tiktok_session_id);
                $avatarUrl = $serviceData['streamer']['avatar'] ?? null;
                if ($avatarUrl) {
                    Log::info("Falling back to streamer avatar for session {$this->liveSessionId}: {$avatarUrl}");
                    $response = Http::timeout(15)->get($avatarUrl);
                    if ($response->successful()) {
                        $this->updateThumbnail($session, $response->body());
                        Log::info("Successfully saved avatar thumbnail for session {$this->liveSessionId}");
                        return;
                    }
                }
            } catch (\Exception $e) {
                Log::warning("Failed to fallback to streamer avatar: " . $e->getMessage());
            }
        }
    }

    /**
     * Cập nhật ảnh thumbnail mới, đồng thời dọn dẹp ảnh cũ để tiết kiệm dung lượng (host 1GB)
     * và khóa cập nhật trong 10 phút (600 giây).
     */
    private function updateThumbnail(LiveSession $session, string $imageData): void
    {
        $disk = Storage::disk('public');

        // Xóa ảnh cũ nếu có để tránh đầy đĩa host (rất quan trọng với gói host 1GB)
        if (!empty($session->thumbnail)) {
            try {
                $filename = basename($session->thumbnail);
                $oldPath = 'thumbnails/' . $filename;
                if ($disk->exists($oldPath)) {
                    $disk->delete($oldPath);
                }
            } catch (\Exception $e) {
                Log::warning("Failed to delete old thumbnail: " . $e->getMessage());
            }
        }

        // Lưu ảnh mới
        $filename = 'thumbnails/' . $this->liveSessionId . '_' . time() . '.jpg';
        $disk->put($filename, $imageData);

        $session->update([
            'thumbnail' => '/storage/' . $filename,
        ]);

        // Cập nhật Cache Lock dài hạn: 10 phút (600 giây)
        Cache::put("live_session_{$this->liveSessionId}_thumbnail_lock", true, 600);
    }
}

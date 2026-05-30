<?php

namespace App\Jobs;

use App\Ai\Agents\LiveSessionAnalyzer;
use App\Models\LiveSession;
use App\Services\LiveInsightsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Phân tích "Tổng kết AI" + "Cảnh báo AI" cho một phiên live.
 *
 * Tách riêng khỏi AnalyzeCommentsJob để:
 * - Có timeout/retry độc lập (tác vụ thinking-mode nặng, không nên ăn chung budget 120s với comment).
 * - Không giữ lock phân tích comment, không làm trễ pipeline comment.
 *
 * Throttle 30s và tính credit được xử lý ngay trong job để đảm bảo nhất quán dù được
 * gọi tự động (sau batch comment) hay không.
 */
class AnalyzeLiveInsightsJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 90;

    public array $backoff = [10, 30];

    /**
     * Khoảng tối thiểu (giây) giữa 2 lần phân tích insights tự động của cùng 1 phiên.
     */
    public const THROTTLE_SECONDS = 30;

    public int $uniqueFor = 120;

    public function __construct(
        private int $liveSessionId,
    ) {}

    public function uniqueId(): string
    {
        return 'analyze-insights-'.$this->liveSessionId;
    }

    public static function cacheKey(int $liveSessionId): string
    {
        return "live_session_{$liveSessionId}_last_insight_time";
    }

    public function handle(LiveInsightsService $insights): void
    {
        $session = LiveSession::with(['products', 'keywords', 'stats', 'user'])->find($this->liveSessionId);
        if (! $session) {
            return;
        }

        // Chỉ phân tích cho phiên đang hoạt động
        if (! in_array($session->status, ['live', 'connecting'])) {
            return;
        }

        // Throttle: bỏ qua nếu vừa phân tích trong vòng THROTTLE_SECONDS
        $cacheKey = self::cacheKey($this->liveSessionId);
        $lastInsightTime = Cache::get($cacheKey);
        if ($lastInsightTime && (now()->timestamp - $lastInsightTime) < self::THROTTLE_SECONDS) {
            return;
        }

        // Credit gating
        $user = $session->user;
        if (! $user) {
            return;
        }
        $activeSub = $user->resolveActiveSubscription();
        $features = $user->getSubscriptionFeatures();
        $aiCreditsLimit = $features['ai_credits'] ?? 1000;
        if ($aiCreditsLimit !== -1 && $activeSub && $activeSub->used_ai_credits >= $aiCreditsLimit) {
            return;
        }

        try {
            $response = $insights->analyze($session);

            if ($response) {
                $session->update([
                    'ai_insights' => $response['summary'] ?? $session->ai_insights,
                    'ai_alerts' => $response['alerts'] ?? $session->ai_alerts,
                ]);

                if ($activeSub) {
                    $activeSub->increment('used_ai_credits', LiveSessionAnalyzer::INSIGHTS_CREDIT_COST);
                }
            }

            Cache::put($cacheKey, now()->timestamp);
            $this->clearSessionCache();
        } catch (\Throwable $e) {
            Log::warning('Automatic AI insights analysis failed', [
                'session_id' => $this->liveSessionId,
                'error' => $e->getMessage(),
            ]);
            // Không rethrow: insights là tính năng phụ, không nên làm fail/retry ồ ạt.
        }
    }

    private function clearSessionCache(): void
    {
        Cache::forget("live_session_{$this->liveSessionId}_top_products");
        Cache::forget("live_session_{$this->liveSessionId}_potential_customers");
        Cache::forget("live_session_{$this->liveSessionId}_top_questions");
        Cache::forget("live_session_{$this->liveSessionId}_stats_history");
        Cache::forget("live_session_{$this->liveSessionId}_potential_customers_count");
        Cache::forget("live_session_{$this->liveSessionId}_top_keywords");
    }
}

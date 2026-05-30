<?php

namespace App\Services;

use App\Ai\Agents\LiveSessionAnalyzer;
use App\Models\LiveEvent;
use App\Models\LiveSession;

/**
 * Đóng gói việc dựng input và gọi LiveSessionAnalyzer để sinh "Tổng kết AI" + "Cảnh báo AI".
 *
 * Service KHÔNG ghi DB, KHÔNG tính credit, KHÔNG throttle — caller (Job/Controller)
 * tự xử lý các phần đó để giữ logic vận hành nằm ở một nơi duy nhất nhưng linh hoạt
 * theo ngữ cảnh (Job chạy nền, Controller trả JSON trực tiếp).
 */
class LiveInsightsService
{
    /**
     * Số bình luận gần nhất đưa vào phân tích tổng hợp.
     */
    private const RECENT_COMMENTS_LIMIT = 150;

    /**
     * Dựng input từ phiên live và gọi AI phân tích tổng hợp.
     *
     * @return array{summary?: string, alerts?: array}|null
     */
    public function analyze(LiveSession $session): ?array
    {
        $session->loadMissing(['products', 'keywords', 'stats']);

        $comments = LiveEvent::where('live_session_id', $session->id)
            ->where('event_type', 'comment')
            ->orderByDesc('event_at')
            ->limit(self::RECENT_COMMENTS_LIMIT)
            ->get()
            ->map(fn (LiveEvent $e) => [
                'user' => $e->tiktok_nickname ?? 'Unknown',
                'text' => $e->data['comment'] ?? '',
                'time' => $e->event_at?->toISOString() ?? '',
            ])
            ->toArray();

        $stats = $session->stats ? [
            'total_views' => $session->stats->total_views,
            'total_comments' => $session->stats->total_comments,
            'total_likes' => $session->stats->total_likes,
            'total_gifts' => $session->stats->total_gifts,
            'total_follows' => $session->stats->total_follows,
            'total_shares' => $session->stats->total_shares,
            'viewer_count' => $session->stats->viewer_count,
            'leads_count' => $session->stats->leads_count,
        ] : [];

        $products = $session->products->map(fn ($p) => [
            'name' => $p->name,
            'sku' => $p->sku,
            'price' => $p->price,
            'keywords' => $p->keywords ?? [],
        ])->toArray();

        $keywords = $session->keywords->pluck('keyword')->toArray();
        $oldMemory = $session->ai_context_summary ?? '';

        $userMessage = json_encode([
            'comments' => $comments,
            'stats' => $stats,
            'products' => $products,
            'keywords' => $keywords,
            'old_memory' => $oldMemory,
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        return (new LiveSessionAnalyzer)
            ->withComments($comments)
            ->withStats($stats)
            ->withProducts($products)
            ->withKeywords($keywords)
            ->withOldMemory($oldMemory)
            ->prompt($userMessage)
            ->toArray();
    }
}

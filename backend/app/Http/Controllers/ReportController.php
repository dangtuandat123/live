<?php

namespace App\Http\Controllers;

use App\Models\LiveSession;
use App\Models\LiveStat;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        $userId = $request->user()->id;
        $period = $request->input('period', '7d');

        $daysLimit = 7;
        if ($period === '30d') {
            $daysLimit = 30;
        } elseif ($period === '90d') {
            $daysLimit = 90;
        }

        // 1. Lọc session trong kỳ và kỳ trước
        $sessionsInPeriod = LiveSession::forUser($userId)
            ->where('created_at', '>=', now()->subDays($daysLimit))
            ->pluck('id')
            ->toArray();

        $sessionsInPrevPeriod = LiveSession::forUser($userId)
            ->where('created_at', '>=', now()->subDays($daysLimit * 2))
            ->where('created_at', '<', now()->subDays($daysLimit))
            ->pluck('id')
            ->toArray();

        // 2. Thống kê KPI Overview
        // Kỳ hiện tại
        $statsCur = \DB::table('live_stats')
            ->whereIn('live_session_id', $sessionsInPeriod)
            ->selectRaw('
                SUM(total_views) as total_views,
                SUM(total_comments) as total_comments,
                SUM(sentiment_positive) as sentiment_positive,
                SUM(sentiment_neutral) as sentiment_neutral,
                SUM(sentiment_negative) as sentiment_negative
            ')
            ->first();

        // Kỳ trước
        $statsPrev = \DB::table('live_stats')
            ->whereIn('live_session_id', $sessionsInPrevPeriod)
            ->selectRaw('
                SUM(total_views) as total_views,
                SUM(total_comments) as total_comments,
                SUM(sentiment_positive) as sentiment_positive,
                SUM(sentiment_neutral) as sentiment_neutral,
                SUM(sentiment_negative) as sentiment_negative
            ')
            ->first();

        $curViews = (int) ($statsCur->total_views ?? 0);
        $prevViews = (int) ($statsPrev->total_views ?? 0);

        $curComments = (int) ($statsCur->total_comments ?? 0);
        $prevComments = (int) ($statsPrev->total_comments ?? 0);

        // Tính cảm xúc trung bình
        $curPos = (int) ($statsCur->sentiment_positive ?? 0);
        $curTotalSent = $curPos + (int) ($statsCur->sentiment_neutral ?? 0) + (int) ($statsCur->sentiment_negative ?? 0);
        $curSentimentPct = $curTotalSent > 0 ? (int) round(($curPos / $curTotalSent) * 100) : 0;

        $prevPos = (int) ($statsPrev->sentiment_positive ?? 0);
        $prevTotalSent = $prevPos + (int) ($statsPrev->sentiment_neutral ?? 0) + (int) ($statsPrev->sentiment_negative ?? 0);
        $prevSentimentPct = $prevTotalSent > 0 ? (int) round(($prevPos / $prevTotalSent) * 100) : 0;

        $overviewStats = [
            [
                'title' => 'Tổng phiên Live',
                'value' => (string) count($sessionsInPeriod),
                'prev' => (string) count($sessionsInPrevPeriod),
            ],
            [
                'title' => 'Tổng bình luận',
                'value' => number_format($curComments),
                'prev' => number_format($prevComments),
            ],
            [
                'title' => 'Tổng lượt xem',
                'value' => number_format($curViews),
                'prev' => number_format($prevViews),
            ],
            [
                'title' => 'TB cảm xúc tích cực',
                'value' => $curSentimentPct . '%',
                'prev' => $prevSentimentPct . '%',
            ],
        ];

        // 3. Biểu đồ xu hướng
        $days = [];
        for ($i = $daysLimit - 1; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('d/m');
            $days[$date] = ['date' => $date, 'views' => 0, 'comments' => 0, 'leads' => 0];
        }

        if (!empty($sessionsInPeriod)) {
            $trendQuery = \DB::table('live_sessions')
                ->join('live_stats', 'live_sessions.id', '=', 'live_stats.live_session_id')
                ->whereIn('live_sessions.id', $sessionsInPeriod)
                ->selectRaw('DATE_FORMAT(live_sessions.created_at, "%d/%m") as day_date, SUM(live_stats.total_views) as views, SUM(live_stats.total_comments) as comments, SUM(live_stats.leads_count) as leads')
                ->groupBy('day_date')
                ->get();

            foreach ($trendQuery as $t) {
                if (isset($days[$t->day_date])) {
                    $days[$t->day_date]['views'] = (int) $t->views;
                    $days[$t->day_date]['comments'] = (int) $t->comments;
                    $days[$t->day_date]['leads'] = (int) $t->leads;
                }
            }
        }
        $trendData = array_values($days);

        // 4. So sánh phiên Live (Top 5)
        $sessionCompareData = [];
        if (!empty($sessionsInPeriod)) {
            $sessionCompareData = LiveSession::forUser($userId)
                ->whereIn('id', $sessionsInPeriod)
                ->with('stats')
                ->get()
                ->map(fn ($s) => [
                    'name' => strlen($s->name) > 18 ? mb_substr($s->name, 0, 15) . '...' : $s->name,
                    'views' => $s->stats?->total_views ?? 0,
                    'comments' => $s->stats?->total_comments ?? 0,
                ])
                ->sortByDesc('views')
                ->take(5)
                ->values()
                ->toArray();
        }

        // 5. Từ khóa nổi bật trong kỳ
        $hotKeywords = [];
        if (!empty($sessionsInPeriod)) {
            $hotKeywords = \DB::table('live_events')
                ->whereIn('live_session_id', $sessionsInPeriod)
                ->where('event_type', 'comment')
                ->whereNotNull('question_tag')
                ->where('question_tag', '!=', '')
                ->selectRaw('question_tag as keyword, COUNT(*) as count')
                ->groupBy('question_tag')
                ->orderByDesc('count')
                ->limit(10)
                ->get()
                ->map(fn ($k) => [
                    'keyword' => $k->keyword,
                    'count' => (int) $k->count,
                    'trend' => 'up',
                ])->toArray();
        }

        if (empty($hotKeywords)) {
            $hotKeywords = [
                ['keyword' => 'giá bao nhiêu', 'count' => 0, 'trend' => 'up'],
                ['keyword' => 'còn hàng không', 'count' => 0, 'trend' => 'up'],
                ['keyword' => 'ship về HN', 'count' => 0, 'trend' => 'down'],
            ];
        }

        // 6. Phiên live gần đây trong kỳ
        $recentSessions = [];
        if (!empty($sessionsInPeriod)) {
            $recentSessions = LiveSession::forUser($userId)
                ->whereIn('id', $sessionsInPeriod)
                ->with('stats')
                ->withCount(['events as comments_count' => fn ($q) => $q->where('event_type', 'comment')])
                ->orderByDesc('created_at')
                ->limit(10)
                ->get()
                ->map(fn (LiveSession $session) => [
                    'id' => (string) $session->id,
                    'name' => $session->name,
                    'comments' => $session->stats?->total_comments ?? $session->comments_count ?? 0,
                    'views' => $session->stats?->total_views ?? 0,
                    'leads' => $session->stats?->leads_count ?? 0,
                    'sentiment' => $this->calculateSentimentScore($session->stats),
                    'date' => $session->created_at?->format('d/m') ?? '',
                ])->toArray();
        }

        // 7. Gợi ý từ AI dựa trên dữ liệu
        $mostMentionedProduct = null;
        if (!empty($sessionsInPeriod)) {
            $mostMentionedProduct = \DB::table('live_events')
                ->whereIn('live_session_id', $sessionsInPeriod)
                ->where('event_type', 'comment')
                ->whereNotNull('product_tag')
                ->where('product_tag', '!=', '')
                ->selectRaw('product_tag, COUNT(*) as cnt')
                ->groupBy('product_tag')
                ->orderByDesc('cnt')
                ->value('product_tag');
        }

        $recommendation = "Dựa trên phân tích AI: ";
        if ($mostMentionedProduct) {
            $recommendation .= "Sản phẩm \"{$mostMentionedProduct}\" được nhắc đến nhiều nhất trong các bình luận. Bạn nên tăng thời lượng giới thiệu sản phẩm này. ";
        } else {
            $recommendation .= "Chưa xác định được sản phẩm tâm điểm trong kỳ này. Hãy thử giới thiệu rõ SKU sản phẩm khi livestream. ";
        }

        $bestHour = null;
        if (!empty($sessionsInPeriod)) {
            $bestHourQuery = LiveSession::forUser($userId)
                ->whereIn('id', $sessionsInPeriod)
                ->selectRaw('HOUR(created_at) as hr, COUNT(*) as cnt')
                ->groupBy('hr')
                ->orderByDesc('cnt')
                ->first();
            if ($bestHourQuery) {
                $bestHour = $bestHourQuery->hr;
            }
        }

        if ($bestHour !== null) {
            $recommendation .= "Khung giờ vàng thu hút tương tác tốt nhất của bạn là khoảng {$bestHour}h. Hãy tập trung live vào khung giờ này và chèn thêm các mini-game hoặc Q&A để tăng tỷ lệ chốt đơn.";
        } else {
            $recommendation .= "Hãy tiến hành thêm các phiên live và bật tính năng AI quét tự động để tối ưu hóa thời gian phát sóng.";
        }

        return Inertia::render('Reports/Index', [
            'overviewStats' => $overviewStats,
            'trendData' => $trendData,
            'sessionCompareData' => $sessionCompareData,
            'hotKeywords' => $hotKeywords,
            'recentSessions' => $recentSessions,
            'aiRecommendation' => $recommendation,
            'filters' => [
                'period' => $period,
            ],
        ]);
    }

    private function calculateSentimentScore(?LiveStat $stats): int
    {
        if (!$stats) {
            return 0;
        }
        $total = $stats->sentiment_positive + $stats->sentiment_neutral + $stats->sentiment_negative;
        if ($total === 0) {
            return 0;
        }
        return (int) round(($stats->sentiment_positive / $total) * 100);
    }
}

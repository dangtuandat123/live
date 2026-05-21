<?php

namespace App\Http\Controllers;

use App\Models\LiveSession;
use App\Models\LiveStat;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $userId = $request->user()->id;

        // 1. Live session đang diễn ra
        $activeSession = LiveSession::forUser($userId)
            ->where('status', 'live')
            ->orderByDesc('created_at')
            ->first();

        $liveSessionData = null;
        if ($activeSession) {
            $activeSession->load('stats');
            $liveSessionData = [
                'id' => (string) $activeSession->id,
                'name' => $activeSession->name,
                'views' => $activeSession->stats?->total_views ?? 0,
                'comments' => $activeSession->stats?->total_comments ?? 0,
                'duration' => $activeSession->duration_formatted,
            ];
        }

        // 2. Thống kê KPI
        $allSessions = LiveSession::forUser($userId)->pluck('id')->toArray();
        $totalSessionsCount = count($allSessions);

        $kpisQuery = \DB::table('live_stats')
            ->whereIn('live_session_id', $allSessions)
            ->selectRaw('
                SUM(total_views) as total_views,
                SUM(total_comments) as total_comments,
                SUM(sentiment_positive) as sentiment_positive,
                SUM(sentiment_neutral) as sentiment_neutral,
                SUM(sentiment_negative) as sentiment_negative
            ')
            ->first();

        $totalViews = (int) ($kpisQuery->total_views ?? 0);
        $totalComments = (int) ($kpisQuery->total_comments ?? 0);
        $pos = (int) ($kpisQuery->sentiment_positive ?? 0);
        $neu = (int) ($kpisQuery->sentiment_neutral ?? 0);
        $neg = (int) ($kpisQuery->sentiment_negative ?? 0);
        $sentimentTotal = $pos + $neu + $neg;
        $positivePercentage = $sentimentTotal > 0 ? (int) round(($pos / $sentimentTotal) * 100) : 0;

        // Tính trend so với tuần trước
        $sessionsThisWeek = LiveSession::forUser($userId)
            ->where('created_at', '>=', now()->subDays(7))
            ->pluck('id')
            ->toArray();
        $sessionsPrevWeek = LiveSession::forUser($userId)
            ->where('created_at', '>=', now()->subDays(14))
            ->where('created_at', '<', now()->subDays(7))
            ->pluck('id')
            ->toArray();

        // 2.1 Trend tổng phiên Live
        $sessionsThisWeekCount = count($sessionsThisWeek);

        // 2.2 Trend tổng bình luận
        $commentsThisWeek = (int) \DB::table('live_stats')->whereIn('live_session_id', $sessionsThisWeek)->sum('total_comments');
        $commentsPrevWeek = (int) \DB::table('live_stats')->whereIn('live_session_id', $sessionsPrevWeek)->sum('total_comments');
        $commentsChange = $this->calculatePercentageChange($commentsThisWeek, $commentsPrevWeek);

        // 2.3 Trend tổng lượt xem
        $viewsThisWeek = (int) \DB::table('live_stats')->whereIn('live_session_id', $sessionsThisWeek)->sum('total_views');
        $viewsPrevWeek = (int) \DB::table('live_stats')->whereIn('live_session_id', $sessionsPrevWeek)->sum('total_views');
        $viewsChange = $this->calculatePercentageChange($viewsThisWeek, $viewsPrevWeek);

        // 2.4 Trend cảm xúc tích cực
        $posThisWeek = (int) \DB::table('live_stats')->whereIn('live_session_id', $sessionsThisWeek)->sum('sentiment_positive');
        $totalSentimentThisWeek = (int) \DB::table('live_stats')
            ->whereIn('live_session_id', $sessionsThisWeek)
            ->selectRaw('SUM(sentiment_positive + sentiment_neutral + sentiment_negative) as total')
            ->value('total');
        $sentimentThisWeekPercentage = $totalSentimentThisWeek > 0 ? (int) round(($posThisWeek / $totalSentimentThisWeek) * 100) : 0;

        $posPrevWeek = (int) \DB::table('live_stats')->whereIn('live_session_id', $sessionsPrevWeek)->sum('sentiment_positive');
        $totalSentimentPrevWeek = (int) \DB::table('live_stats')
            ->whereIn('live_session_id', $sessionsPrevWeek)
            ->selectRaw('SUM(sentiment_positive + sentiment_neutral + sentiment_negative) as total')
            ->value('total');
        $sentimentPrevWeekPercentage = $totalSentimentPrevWeek > 0 ? (int) round(($posPrevWeek / $totalSentimentPrevWeek) * 100) : 0;

        $sentimentDiff = $sentimentThisWeekPercentage - $sentimentPrevWeekPercentage;

        $stats = [
            [
                'title' => 'Tổng phiên Live',
                'value' => (string) $totalSessionsCount,
                'change' => "+{$sessionsThisWeekCount} tuần này",
                'trend' => 'up',
            ],
            [
                'title' => 'Tổng bình luận',
                'value' => number_format($totalComments),
                'change' => ($commentsChange >= 0 ? '+' : '') . $commentsChange . '% so với tuần trước',
                'trend' => $commentsChange >= 0 ? 'up' : 'down',
            ],
            [
                'title' => 'Tổng lượt xem',
                'value' => number_format($totalViews),
                'change' => ($viewsChange >= 0 ? '+' : '') . $viewsChange . '% so với tuần trước',
                'trend' => $viewsChange >= 0 ? 'up' : 'down',
            ],
            [
                'title' => 'Cảm xúc tích cực',
                'value' => $positivePercentage . '%',
                'change' => ($sentimentDiff >= 0 ? '+' : '') . $sentimentDiff . '% so với tuần trước',
                'trend' => $sentimentDiff >= 0 ? 'up' : 'down',
            ],
        ];

        // 3. Biểu đồ xu hướng 7 ngày
        $days = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('d/m');
            $days[$date] = ['date' => $date, 'views' => 0, 'comments' => 0];
        }

        if (!empty($allSessions)) {
            $trendQuery = \DB::table('live_sessions')
                ->join('live_stats', 'live_sessions.id', '=', 'live_stats.live_session_id')
                ->whereIn('live_sessions.id', $allSessions)
                ->where('live_sessions.created_at', '>=', now()->subDays(7))
                ->selectRaw('DATE_FORMAT(live_sessions.created_at, "%d/%m") as day_date, SUM(live_stats.total_views) as views, SUM(live_stats.total_comments) as comments')
                ->groupBy('day_date')
                ->get();

            foreach ($trendQuery as $t) {
                if (isset($days[$t->day_date])) {
                    $days[$t->day_date]['views'] = (int) $t->views;
                    $days[$t->day_date]['comments'] = (int) $t->comments;
                }
            }
        }
        $trendData = array_values($days);

        // 4. Từ khóa nổi bật
        $hotKeywords = [];
        if (!empty($allSessions)) {
            $hotKeywords = \DB::table('live_events')
                ->whereIn('live_session_id', $allSessions)
                ->where('event_type', 'comment')
                ->whereNotNull('question_tag')
                ->where('question_tag', '!=', '')
                ->selectRaw('question_tag as keyword, COUNT(*) as count')
                ->groupBy('question_tag')
                ->orderByDesc('count')
                ->limit(5)
                ->get()
                ->map(fn ($k) => [
                    'keyword' => $k->keyword,
                    'count' => (int) $k->count,
                    'trend' => 'up',
                ])->toArray();
        }

        // Fallback mock nếu chưa có dữ liệu từ khóa AI
        if (empty($hotKeywords)) {
            $hotKeywords = [
                ['keyword' => 'giá bao nhiêu', 'count' => 0, 'trend' => 'up'],
                ['keyword' => 'còn hàng không', 'count' => 0, 'trend' => 'up'],
                ['keyword' => 'ship về HN', 'count' => 0, 'trend' => 'down'],
            ];
        }

        // 5. Top sản phẩm
        $topProducts = [];
        if (!empty($allSessions)) {
            $topProducts = \DB::table('live_events')
                ->whereIn('live_session_id', $allSessions)
                ->where('event_type', 'comment')
                ->whereNotNull('product_tag')
                ->where('product_tag', '!=', '')
                ->selectRaw('product_tag as name, COUNT(*) as mentions')
                ->groupBy('product_tag')
                ->orderByDesc('mentions')
                ->limit(5)
                ->get();

            $maxMentions = $topProducts->max('mentions') ?: 1;
            $topProducts = $topProducts->map(fn ($p) => [
                'name' => $p->name,
                'mentions' => (int) $p->mentions,
                'percent' => (int) round(($p->mentions / $maxMentions) * 100),
            ])->toArray();
        }

        // 6. Phiên live gần đây
        $recentSessions = LiveSession::forUser($userId)
            ->with('stats')
            ->withCount(['events as comments_count' => fn ($q) => $q->where('event_type', 'comment')])
            ->orderByDesc('created_at')
            ->limit(5)
            ->get()
            ->map(fn (LiveSession $session) => [
                'id' => (string) $session->id,
                'name' => $session->name,
                'status' => $session->status,
                'comments' => $session->stats?->total_comments ?? $session->comments_count ?? 0,
                'views' => $session->stats?->total_views ?? 0,
                'leads' => $session->stats?->leads_count ?? 0,
                'sentiment' => $this->calculateSentimentScore($session->stats),
                'duration' => $session->duration_formatted,
                'date' => $session->created_at?->format('d/m/Y') ?? '',
            ])->toArray();

        return Inertia::render('Dashboard', [
            'stats' => $stats,
            'liveSession' => $liveSessionData,
            'trendData' => $trendData,
            'hotKeywords' => $hotKeywords,
            'topProducts' => $topProducts,
            'recentSessions' => $recentSessions,
        ]);
    }

    private function calculatePercentageChange(int $current, int $previous): int
    {
        if ($previous === 0) {
            return $current > 0 ? 100 : 0;
        }
        return (int) round((($current - $previous) / $previous) * 100);
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

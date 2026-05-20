<?php

namespace App\Http\Controllers;

use App\Models\LiveEvent;
use App\Models\LiveSession;
use App\Models\LiveStat;
use App\Models\Product;
use App\Services\TikTokService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;

class LiveSessionController extends Controller
{
    public function __construct(
        private TikTokService $tiktokService,
    ) {}

    /**
     * Danh sách phiên live.
     */
    public function index(Request $request)
    {
        $userId = $request->user()->id;

        $query = LiveSession::forUser($userId)
            ->with('stats')
            ->withCount(['events as comments_count' => fn ($q) => $q->where('event_type', 'comment')])
            ->orderByDesc('created_at');

        if ($search = $request->input('search')) {
            $query->where('name', 'like', "%{$search}%");
        }

        if ($status = $request->input('status')) {
            if ($status !== 'all') {
                $query->where('status', $status);
            }
        }

        $sessions = $query->paginate(12)->through(function (LiveSession $session) {
            return [
                'id' => $session->id,
                'name' => $session->name,
                'status' => $session->status,
                'comments' => $session->stats?->total_comments ?? $session->comments_count ?? 0,
                'views' => $session->stats?->total_views ?? 0,
                'leads' => $session->stats?->leads_count ?? 0,
                'sentiment' => $this->calculateSentimentScore($session->stats),
                'duration' => $session->duration_formatted,
                'products' => $session->products()->count(),
                'date' => $session->created_at?->format('d/m/Y') ?? '',
                'thumbnail' => $session->thumbnail,
            ];
        });

        // KPI cards
        $allSessions = LiveSession::forUser($userId);
        $totalViews = LiveStat::whereIn('live_session_id', (clone $allSessions)->select('id'))->sum('total_views');
        $totalComments = LiveStat::whereIn('live_session_id', (clone $allSessions)->select('id'))->sum('total_comments');
        $liveCount = (clone $allSessions)->live()->count();
        $liveViews = LiveStat::whereIn(
            'live_session_id',
            (clone $allSessions)->live()->select('id')
        )->sum('total_views');

        return Inertia::render('Lives/Index', [
            'sessions' => $sessions,
            'kpi' => [
                'total_sessions' => (clone $allSessions)->count(),
                'live_count' => $liveCount,
                'live_views' => $liveViews,
                'total_views' => $totalViews,
                'total_comments' => $totalComments,
            ],
            'filters' => [
                'search' => $search,
                'status' => $status ?? 'all',
            ],
        ]);
    }

    /**
     * Form tạo phiên mới.
     */
    public function create(Request $request)
    {
        $products = Product::where('user_id', $request->user()->id)
            ->orderBy('name')
            ->get(['id', 'name', 'sku', 'price']);

        return Inertia::render('Lives/Setup', [
            'products' => $products,
        ]);
    }

    /**
     * Tạo phiên + gọi Python service.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'tiktok_username' => ['required', 'string', 'max:100'],
            'product_ids' => ['nullable', 'array'],
            'product_ids.*' => ['integer', 'exists:products,id'],
            'keywords' => ['nullable', 'array'],
            'keywords.*' => ['string', 'max:100'],
        ]);

        // Tạo session DB
        $session = LiveSession::create([
            'user_id' => $request->user()->id,
            'name' => $validated['name'],
            'tiktok_username' => $validated['tiktok_username'],
            'status' => 'connecting',
        ]);

        // Attach products
        if (!empty($validated['product_ids'])) {
            // Đảm bảo chỉ attach products thuộc user
            $validProductIds = Product::where('user_id', $request->user()->id)
                ->whereIn('id', $validated['product_ids'])
                ->pluck('id');
            $session->products()->attach($validProductIds);
        }

        // Save keywords
        if (!empty($validated['keywords'])) {
            foreach ($validated['keywords'] as $keyword) {
                $session->keywords()->create(['keyword' => $keyword]);
            }
        }

        // Tạo stats record
        LiveStat::create(['live_session_id' => $session->id]);

        // Gọi Python service bắt đầu theo dõi
        $result = $this->tiktokService->startSession($validated['tiktok_username']);

        if ($result && isset($result['session_id'])) {
            $session->update([
                'tiktok_session_id' => $result['session_id'],
                'status' => $result['status'] ?? 'connecting',
                'started_at' => now(),
            ]);
        } else {
            $session->update([
                'status' => 'error',
                'error_message' => 'Không thể kết nối TikTok service. Kiểm tra lại kết nối VPS.',
            ]);
        }

        return redirect()->route('lives.show', $session->id);
    }

    /**
     * Chi tiết phiên live.
     */
    public function show(Request $request, LiveSession $liveSession)
    {
        if ($liveSession->user_id !== $request->user()->id) {
            abort(403);
        }

        $liveSession->load(['products', 'stats', 'keywords']);

        // Lấy comments gần nhất từ DB
        $comments = $liveSession->events()
            ->where('event_type', 'comment')
            ->orderByDesc('event_at')
            ->limit(200)
            ->get()
            ->map(fn (LiveEvent $e) => [
                'id' => $e->id,
                'user' => $e->tiktok_nickname ?? 'Unknown',
                'unique_id' => $e->tiktok_unique_id,
                'text' => $e->data['message'] ?? '',
                'time' => $e->event_at?->diffForHumans() ?? '',
                'event_at' => $e->event_at?->toISOString(),
            ]);

        // Sync status từ Python service nếu đang live
        $serviceData = null;
        if ($liveSession->tiktok_session_id && in_array($liveSession->status, ['connecting', 'live'])) {
            $serviceData = $this->tiktokService->getStats($liveSession->tiktok_session_id);
            if ($serviceData) {
                $this->syncStats($liveSession, $serviceData);
            }
        }

        return Inertia::render('Lives/Show', [
            'session' => [
                'id' => $liveSession->id,
                'name' => $liveSession->name,
                'platform' => $liveSession->platform,
                'status' => $liveSession->status,
                'tiktok_username' => $liveSession->tiktok_username,
                'tiktok_session_id' => $liveSession->tiktok_session_id,
                'duration' => $liveSession->duration_formatted,
                'started_at' => $liveSession->started_at?->toISOString(),
                'ended_at' => $liveSession->ended_at?->toISOString(),
                'error_message' => $liveSession->error_message,
                'products' => $liveSession->products->map(fn ($p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'sku' => $p->sku,
                    'price' => $p->price,
                    'image' => $p->image,
                ]),
                'keywords' => $liveSession->keywords->pluck('keyword'),
            ],
            'stats' => $liveSession->stats ? [
                'total_views' => $liveSession->stats->total_views,
                'total_comments' => $liveSession->stats->total_comments,
                'total_likes' => $liveSession->stats->total_likes,
                'total_gifts' => $liveSession->stats->total_gifts,
                'total_follows' => $liveSession->stats->total_follows,
                'total_shares' => $liveSession->stats->total_shares,
                'viewer_count' => $liveSession->stats->viewer_count,
                'leads_count' => $liveSession->stats->leads_count,
                'sentiment_positive' => $liveSession->stats->sentiment_positive,
                'sentiment_neutral' => $liveSession->stats->sentiment_neutral,
                'sentiment_negative' => $liveSession->stats->sentiment_negative,
            ] : null,
            'comments' => $comments,
        ]);
    }

    /**
     * Kết thúc phiên.
     */
    public function stop(Request $request, LiveSession $liveSession)
    {
        if ($liveSession->user_id !== $request->user()->id) {
            abort(403);
        }

        // Gọi Python service dừng
        if ($liveSession->tiktok_session_id) {
            $this->tiktokService->stopSession($liveSession->tiktok_session_id);
        }

        // Tính duration
        $durationSeconds = 0;
        if ($liveSession->started_at) {
            $durationSeconds = (int) $liveSession->started_at->diffInSeconds(now());
        }

        $liveSession->update([
            'status' => 'ended',
            'ended_at' => now(),
            'duration_seconds' => $durationSeconds,
        ]);

        // Fetch events cuối cùng trước khi kết thúc
        $this->fetchAndStoreEvents($liveSession);

        return redirect()->route('lives.index')->with('success', 'Đã kết thúc phiên phân tích.');
    }

    /**
     * AJAX: Fetch events mới từ Python service và lưu vào DB.
     */
    public function fetchEvents(Request $request, LiveSession $liveSession)
    {
        if ($liveSession->user_id !== $request->user()->id) {
            abort(403);
        }

        if (!$liveSession->tiktok_session_id) {
            return response()->json(['message' => 'No TikTok session linked'], 400);
        }

        $newEventsCount = $this->fetchAndStoreEvents($liveSession);

        // Cập nhật status + stats
        $serviceData = $this->tiktokService->getStats($liveSession->tiktok_session_id);
        if ($serviceData) {
            $this->syncStats($liveSession, $serviceData);

            // Cập nhật duration nếu đang live
            if ($liveSession->status === 'live' && $liveSession->started_at) {
                $liveSession->update([
                    'duration_seconds' => (int) $liveSession->started_at->diffInSeconds(now()),
                ]);
            }
        }

        // Lấy comments mới nhất cho frontend
        $latestComments = $liveSession->events()
            ->where('event_type', 'comment')
            ->orderByDesc('event_at')
            ->limit(50)
            ->get()
            ->map(fn (LiveEvent $e) => [
                'id' => $e->id,
                'user' => $e->tiktok_nickname ?? 'Unknown',
                'unique_id' => $e->tiktok_unique_id,
                'text' => $e->data['message'] ?? '',
                'time' => $e->event_at?->diffForHumans() ?? '',
                'event_at' => $e->event_at?->toISOString(),
            ]);

        $liveSession->load('stats');

        return response()->json([
            'new_events' => $newEventsCount,
            'comments' => $latestComments,
            'stats' => $liveSession->stats,
            'status' => $liveSession->status,
            'duration' => $liveSession->duration_formatted,
        ]);
    }

    // --- Private helpers ---

    private function fetchAndStoreEvents(LiveSession $session): int
    {
        if (!$session->tiktok_session_id) {
            return 0;
        }

        // Lấy timestamp event cuối cùng đã lưu để chỉ fetch events mới
        $lastEvent = $session->events()->orderByDesc('event_at')->first();
        $since = $lastEvent?->event_at?->timestamp ?? 0;

        $data = $this->tiktokService->getSession(
            $session->tiktok_session_id,
            includeEvents: true,
            limit: 500,
            since: $since > 0 ? $since : 0,
        );

        if (!$data || empty($data['events'])) {
            return 0;
        }

        $count = 0;
        foreach ($data['events'] as $event) {
            // Tránh duplicate bằng cách check timestamp + user + type
            $eventAt = isset($event['timestamp'])
                ? Carbon::parse($event['timestamp'])
                : now();

            $exists = $session->events()
                ->where('event_type', $event['type'] ?? 'unknown')
                ->where('tiktok_user_id', $event['user_id'] ?? '')
                ->where('event_at', $eventAt)
                ->exists();

            if ($exists) {
                continue;
            }

            LiveEvent::create([
                'live_session_id' => $session->id,
                'event_type' => $event['type'] ?? 'unknown',
                'tiktok_user_id' => $event['user_id'] ?? null,
                'tiktok_unique_id' => $event['unique_id'] ?? null,
                'tiktok_nickname' => $event['nickname'] ?? null,
                'data' => $event['data'] ?? [],
                'event_at' => $eventAt,
            ]);
            $count++;
        }

        // Update status từ service
        if (isset($data['status'])) {
            $newStatus = match ($data['status']) {
                'live' => 'live',
                'ended' => 'ended',
                'error' => 'error',
                default => $session->status,
            };

            if ($newStatus !== $session->status) {
                $updates = ['status' => $newStatus];
                if ($newStatus === 'ended' && !$session->ended_at) {
                    $updates['ended_at'] = now();
                    if ($session->started_at) {
                        $updates['duration_seconds'] = (int) $session->started_at->diffInSeconds(now());
                    }
                }
                if ($newStatus === 'live' && $session->status === 'connecting') {
                    $updates['started_at'] = $updates['started_at'] ?? now();
                }
                $session->update($updates);
            }
        }

        return $count;
    }

    private function syncStats(LiveSession $session, array $serviceData): void
    {
        $stats = $serviceData['stats'] ?? $serviceData;

        $session->stats()->updateOrCreate(
            ['live_session_id' => $session->id],
            [
                'total_views' => $stats['total_views'] ?? 0,
                'total_comments' => $stats['total_comments'] ?? 0,
                'total_likes' => $stats['total_likes'] ?? 0,
                'total_gifts' => $stats['total_gifts'] ?? 0,
                'total_follows' => $stats['total_follows'] ?? 0,
                'total_shares' => $stats['total_shares'] ?? 0,
                'viewer_count' => $stats['viewer_count'] ?? 0,
            ],
        );
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

<?php

namespace App\Http\Controllers;

use App\Jobs\AnalyzeCommentsJob;
use App\Jobs\CaptureThumbnailJob;
use App\Models\LiveEvent;
use App\Models\LiveSession;
use App\Models\LiveStat;
use App\Models\LiveSessionStatsHistory;
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
            ->withCount('products')
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
                'viewer_count' => $session->stats?->viewer_count ?? 0,
                'leads' => $session->stats?->leads_count ?? 0,
                'sentiment' => LiveStat::sentimentScore($session->stats),
                'duration' => $session->duration_formatted,
                'products' => $session->products_count ?? 0,
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
        )->sum('viewer_count');

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
                'avatar_url' => $e->data['avatar_url'] ?? null,
                'text' => $e->data['comment'] ?? '',
                'time' => $e->event_at?->diffForHumans() ?? '',
                'event_at' => $e->event_at?->toISOString(),
                'sentiment' => $e->sentiment ?? 'neutral',
                'intent_tag' => $e->intent_tag,
                'question_tag' => $e->question_tag,
                'product_tag' => $e->product_tag,
                'has_phone' => $e->has_phone ?? false,
            ]);

        // Sync status từ Python service nếu đang live
        $serviceData = null;
        if ($liveSession->tiktok_session_id && in_array($liveSession->status, ['connecting', 'live'])) {
            try {
                $serviceData = $this->tiktokService->getStats($liveSession->tiktok_session_id);
                if ($serviceData) {
                    $this->syncStats($liveSession, $serviceData);
                }
            } catch (\App\Exceptions\TikTokSessionNotFoundException $e) {
                // Thử tự động khôi phục (healing) nếu trạng thái trong DB đang là live/connecting (ví dụ Python service bị restart)
                $healed = false;
                if (in_array($liveSession->status, ['live', 'connecting'])) {
                    try {
                        $result = $this->tiktokService->startSession($liveSession->tiktok_username, $liveSession->tiktok_session_id);
                        if ($result && isset($result['session_id'])) {
                            $liveSession->update([
                                'status' => 'connecting',
                            ]);
                            $healed = true;
                        }
                    } catch (\Exception $restartException) {
                        \Illuminate\Support\Facades\Log::error('Auto-healing in show failed to restart: ' . $restartException->getMessage());
                    }
                }

                if (!$healed) {
                    $durationSeconds = 0;
                    if ($liveSession->started_at) {
                        $durationSeconds = (int) $liveSession->started_at->diffInSeconds(now());
                    }
                    $liveSession->update([
                        'status' => 'ended',
                        'ended_at' => now(),
                        'duration_seconds' => $durationSeconds,
                    ]);
                }
            }
        }

        return Inertia::render('Lives/Show', [
            'session' => [
                'id' => $liveSession->id,
                'name' => $liveSession->name,
                'platform' => $liveSession->platform,
                'status' => ($serviceData && isset($serviceData['status'])) ? $serviceData['status'] : $liveSession->status,
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
            'topProducts' => $this->getTopProducts($liveSession),
            'potentialCustomers' => $this->getPotentialCustomers($liveSession),
            'topQuestions' => $this->getTopQuestions($liveSession),
            'statsHistory' => $this->getFormattedStatsHistory($liveSession),
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
            try {
                $this->tiktokService->stopSession($liveSession->tiktok_session_id);
            } catch (\App\Exceptions\TikTokSessionNotFoundException $e) {
                // Đã bị dừng hoặc đóng trước đó ở VPS
            }
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
        try {
            $this->fetchAndStoreEvents($liveSession);
        } catch (\App\Exceptions\TikTokSessionNotFoundException $e) {
            // Phiên live đã bị đóng hoàn toàn
        }

        return redirect()->route('lives.index')->with('success', 'Đã kết thúc phiên phân tích.');
    }

    /**
     * Xóa phiên live.
     */
    public function destroy(Request $request, LiveSession $liveSession)
    {
        if ($liveSession->user_id !== $request->user()->id) {
            abort(403);
        }

        // Gọi Python service dừng nếu đang chạy ngầm
        if ($liveSession->tiktok_session_id && in_array($liveSession->status, ['connecting', 'live'])) {
            try {
                $this->tiktokService->stopSession($liveSession->tiktok_session_id);
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::warning('Failed to stop TikTok session during deletion', [
                    'session_id' => $liveSession->tiktok_session_id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        // Xóa session (DB cascade sẽ tự động xóa các bảng quan hệ liên quan)
        $liveSession->delete();

        return redirect()->route('lives.index')->with('success', 'Đã xóa phiên phân tích thành công.');
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

        // Nếu session đã bị lỗi hoặc kết thúc, không gọi service nữa mà trả về dữ liệu DB hiện tại
        if ($liveSession->status === 'error' || $liveSession->status === 'ended') {
            $latestComments = $liveSession->events()
                ->where('event_type', 'comment')
                ->orderByDesc('event_at')
                ->limit(200)
                ->get()
                ->map(fn (LiveEvent $e) => [
                    'id' => $e->id,
                    'user' => $e->tiktok_nickname ?? 'Unknown',
                    'unique_id' => $e->tiktok_unique_id,
                    'avatar_url' => $e->data['avatar_url'] ?? null,
                    'text' => $e->data['comment'] ?? '',
                    'time' => $e->event_at?->diffForHumans() ?? '',
                    'event_at' => $e->event_at?->toISOString(),
                    'sentiment' => $e->sentiment ?? 'neutral',
                    'intent_tag' => $e->intent_tag,
                    'question_tag' => $e->question_tag,
                    'product_tag' => $e->product_tag,
                    'has_phone' => $e->has_phone ?? false,
                ]);

            $liveSession->load('stats');

            return response()->json([
                'new_events' => 0,
                'comments' => $latestComments,
                'stats' => $liveSession->stats,
                'status' => $liveSession->status,
                'error_message' => $liveSession->error_message,
                'duration' => $liveSession->duration_formatted,
                'topProducts' => $this->getTopProducts($liveSession),
                'potentialCustomers' => $this->getPotentialCustomers($liveSession),
                'topQuestions' => $this->getTopQuestions($liveSession),
                'statsHistory' => $this->getFormattedStatsHistory($liveSession),
            ]);
        }

        $cacheKey = "live_session_fail_count_{$liveSession->id}";
        $newEventsCount = 0;

        try {
            $newEventsCount = $this->fetchAndStoreEvents($liveSession);

            // Cập nhật status + stats
            $serviceData = $this->tiktokService->getStats($liveSession->tiktok_session_id);
            if ($serviceData) {
                // Xóa bộ đếm lỗi nếu kết nối thành công
                \Illuminate\Support\Facades\Cache::forget($cacheKey);

                $this->syncStats($liveSession, $serviceData);

                // Cập nhật duration nếu đang live
                if ($liveSession->status === 'live' && $liveSession->started_at) {
                    $liveSession->update([
                        'duration_seconds' => (int) $liveSession->started_at->diffInSeconds(now()),
                    ]);
                }
            } else {
                // Tăng số lần lỗi kết nối liên tiếp
                $failCount = (int) \Illuminate\Support\Facades\Cache::get($cacheKey, 0) + 1;
                \Illuminate\Support\Facades\Cache::put($cacheKey, $failCount, 300); // lưu trong 5 phút

                if ($failCount >= 5) {
                    $liveSession->update([
                        'status' => 'error',
                        'error_message' => 'Không thể kết nối tới dịch vụ TikTok LIVE. Máy chủ phân tích có thể đang bảo trì.',
                    ]);
                    \Illuminate\Support\Facades\Cache::forget($cacheKey);
                }
            }
        } catch (\App\Exceptions\TikTokSessionNotFoundException $e) {
            // Thử tự động khôi phục (healing) nếu trạng thái trong DB đang là live/connecting (ví dụ Python service bị restart)
            if (in_array($liveSession->status, ['live', 'connecting'])) {
                try {
                    $result = $this->tiktokService->startSession($liveSession->tiktok_username, $liveSession->tiktok_session_id);
                    if ($result && isset($result['session_id'])) {
                        $liveSession->update([
                            'status' => 'connecting',
                        ]);
                        return response()->json([
                            'new_events' => 0,
                            'comments' => [],
                            'stats' => $liveSession->stats,
                            'status' => 'connecting',
                            'error_message' => null,
                            'duration' => $liveSession->duration_formatted,
                            'topProducts' => $this->getTopProducts($liveSession),
                            'potentialCustomers' => $this->getPotentialCustomers($liveSession),
                            'topQuestions' => $this->getTopQuestions($liveSession),
                            'statsHistory' => $this->getFormattedStatsHistory($liveSession),
                        ]);
                    }
                } catch (\Exception $restartException) {
                    \Illuminate\Support\Facades\Log::error('Auto-healing in fetchEvents failed to restart: ' . $restartException->getMessage());
                }
            }

            // Nếu không thể khôi phục hoặc thực tế đã kết thúc
            $durationSeconds = 0;
            if ($liveSession->started_at) {
                $durationSeconds = (int) $liveSession->started_at->diffInSeconds(now());
            }
            $liveSession->update([
                'status' => 'ended',
                'ended_at' => now(),
                'duration_seconds' => $durationSeconds,
            ]);
            \Illuminate\Support\Facades\Cache::forget($cacheKey);
        }

        // Dispatch AI phân tích nếu có events mới và session không bị lỗi
        if ($newEventsCount > 0 && $liveSession->status !== 'error') {
            AnalyzeCommentsJob::dispatch($liveSession->id);
        }

        // Lấy comments mới nhất cho frontend
        $latestComments = $liveSession->events()
            ->where('event_type', 'comment')
            ->orderByDesc('event_at')
            ->limit(200)
            ->get()
            ->map(fn (LiveEvent $e) => [
                'id' => $e->id,
                'user' => $e->tiktok_nickname ?? 'Unknown',
                'unique_id' => $e->tiktok_unique_id,
                'avatar_url' => $e->data['avatar_url'] ?? null,
                'text' => $e->data['comment'] ?? '',
                'time' => $e->event_at?->diffForHumans() ?? '',
                'event_at' => $e->event_at?->toISOString(),
                'sentiment' => $e->sentiment ?? 'neutral',
                'intent_tag' => $e->intent_tag,
                'question_tag' => $e->question_tag,
                'product_tag' => $e->product_tag,
                'has_phone' => $e->has_phone ?? false,
            ]);

        $liveSession->load('stats');

        return response()->json([
            'new_events' => $newEventsCount,
            'comments' => $latestComments,
            'stats' => $liveSession->stats,
            'status' => (isset($serviceData) && isset($serviceData['status'])) ? $serviceData['status'] : $liveSession->status,
            'error_message' => $liveSession->error_message,
            'duration' => $liveSession->duration_formatted,
            'topProducts' => $this->getTopProducts($liveSession),
            'potentialCustomers' => $this->getPotentialCustomers($liveSession),
            'topQuestions' => $this->getTopQuestions($liveSession),
            'statsHistory' => $this->getFormattedStatsHistory($liveSession),
        ]);
    }

    // --- Private helpers ---

    private function fetchAndStoreEvents(LiveSession $session): int
    {
        if (!$session->tiktok_session_id) {
            return 0;
        }

        // Lấy timestamp event cuối cùng đã lưu để chỉ fetch events mới (giữ ms precision)
        $lastEvent = $session->events()->orderByDesc('event_at')->first();
        $since = $lastEvent?->event_at ? $lastEvent->event_at->getPreciseTimestamp(3) / 1000 : 0;

        $totalCount = 0;
        $maxPages = 3; // Tối đa 3 lần fetch liên tiếp để tránh block quá lâu
        $fetchLimit = 2000;

        for ($page = 0; $page < $maxPages; $page++) {
            $data = $this->fetchFromServiceWithRetry(
                $session->tiktok_session_id,
                $fetchLimit,
                $since > 0 ? $since : 0,
            );

            if (!$data || empty($data['events'])) {
                break;
            }

            $pageCount = 0;
            foreach ($data['events'] as $event) {
                $eventAt = isset($event['timestamp'])
                    ? Carbon::parse($event['timestamp'])
                    : now();

                try {
                    // M1: Hash nội dung động theo loại event để dedup chính xác không bị nuốt tương tác
                    $eventType = $event['type'] ?? 'unknown';
                    $dataHash = match ($eventType) {
                        'comment' => md5($event['data']['comment'] ?? ''),
                        'gift' => md5(($event['data']['gift_id'] ?? '') . '_' . ($event['data']['repeat_count'] ?? 1)),
                        'like' => md5('like_' . ($event['data']['count'] ?? 1)),
                        default => md5($eventType . '_' . ($event['timestamp'] ?? '')),
                    };

                    LiveEvent::create([
                        'live_session_id' => $session->id,
                        'event_type' => $event['type'] ?? 'unknown',
                        'tiktok_user_id' => $event['user_id'] ?? null,
                        'tiktok_unique_id' => $event['unique_id'] ?? null,
                        'tiktok_nickname' => $event['nickname'] ?? null,
                        'data' => $event['data'] ?? [],
                        'data_hash' => $dataHash,
                        'event_at' => $eventAt,
                    ]);
                    $pageCount++;
                } catch (\Illuminate\Database\UniqueConstraintViolationException) {
                    // Duplicate event — bỏ qua (bảo vệ bởi unique index)
                    continue;
                }
            }

            $totalCount += $pageCount;

            // Nếu nhận ít hơn limit, không còn events nào nữa
            if (count($data['events']) < $fetchLimit) {
                break;
            }

            // Cập nhật since cho page tiếp theo
            $lastTimestamp = end($data['events'])['timestamp'] ?? null;
            if ($lastTimestamp) {
                $since = Carbon::parse($lastTimestamp)->getPreciseTimestamp(3) / 1000;
            } else {
                break;
            }
        }

        // Update status từ service (dùng data từ lần fetch cuối)
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

        return $totalCount;
    }

    /**
     * Gọi Python service với 1-retry khi thất bại (M2: tránh mất events do network blip).
     */
    private function fetchFromServiceWithRetry(string $sessionId, int $limit, float $since): ?array
    {
        $data = $this->tiktokService->getSession($sessionId, includeEvents: true, limit: $limit, since: $since);

        if ($data === null) {
            // Retry 1 lần sau 1 giây
            usleep(1_000_000);
            $data = $this->tiktokService->getSession($sessionId, includeEvents: true, limit: $limit, since: $since);
        }

        return $data;
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

        $coverUrl = $serviceData['cover_url'] ?? null;

        // Tự động tải hoặc cập nhật thumbnail định kỳ mỗi 10 phút nếu phiên live đang hoạt động
        // Tránh dispatch job khi đang connecting mà chưa có cover_url (vì chắc chắn sẽ fail và bị lock 2 phút vô ích)
        if ($session->status === 'live' || ($session->status === 'connecting' && !empty($coverUrl))) {
            $cacheKey = "live_session_{$session->id}_thumbnail_lock";
            if (!\Illuminate\Support\Facades\Cache::has($cacheKey)) {
                // Đặt lock tạm thời trong 2 phút để tránh spam gửi Job liên tục khi đang xử lý
                \Illuminate\Support\Facades\Cache::put($cacheKey, true, 120);

                CaptureThumbnailJob::dispatch($session->id, $coverUrl);
            }
        }

        // Ghi nhận lịch sử thống kê định kỳ (mỗi 5 phút)
        $this->recordStatsHistory($session);
    }

    /**
     * Ghi nhận lịch sử stats (mỗi 5 phút).
     */
    private function recordStatsHistory(LiveSession $session): void
    {
        if (!in_array($session->status, ['live', 'connecting'])) {
            return;
        }

        // Tự động lấp đầy khoảng trống lịch sử trong quá khứ trước
        $this->backfillStatsHistory($session);

        $currentStats = $session->stats;
        if (!$currentStats) {
            return;
        }

        $latestHistory = $session->statsHistory()->orderByDesc('created_at')->first();

        $shouldRecord = false;
        if (!$latestHistory) {
            $shouldRecord = true;
        } else {
            $diffInSeconds = now()->diffInSeconds($latestHistory->created_at);
            if ($diffInSeconds >= 300) { // 300 giây = 5 phút
                $shouldRecord = true;
            }
        }

        if ($shouldRecord) {
            $session->statsHistory()->create([
                'total_views' => $currentStats->total_views,
                'total_comments' => $currentStats->total_comments,
                'total_likes' => $currentStats->total_likes,
                'total_gifts' => $currentStats->total_gifts,
                'total_follows' => $currentStats->total_follows,
                'total_shares' => $currentStats->total_shares,
                'viewer_count' => $currentStats->viewer_count,
                'sentiment_positive' => $currentStats->sentiment_positive,
                'sentiment_neutral' => $currentStats->sentiment_neutral,
                'sentiment_negative' => $currentStats->sentiment_negative,
                'leads_count' => $currentStats->leads_count,
            ]);
        }
    }

    /**
     * Tự động lấp đầy khoảng trống lịch sử thống kê (backfill) từ started_at cho đến thời điểm hiện tại / kết thúc.
     */
    private function backfillStatsHistory(LiveSession $session): void
    {
        $startedAt = $session->started_at;
        if (!$startedAt) {
            return;
        }

        $now = now();
        $interval = 300; // 5 phút = 300 giây

        $endTime = $session->ended_at ?: ($session->status === 'ended' ? $session->updated_at : $now);
        if (!$endTime) {
            $endTime = $now;
        }
        // Lùi lại 1 phút để tránh xung đột với bản ghi hiện tại đang ghi nhận realtime
        $endTime = $endTime->copy()->subMinutes(1);

        $startTime = $startedAt->copy()->addSeconds($interval);

        $currentStats = $session->stats;
        if (!$currentStats) {
            return;
        }

        $currentComments = $session->events()
            ->where('event_type', 'comment')
            ->count();

        $totalCommentsStats = $currentStats->total_comments ?: $currentComments;

        for ($time = $startTime; $time->lessThanOrEqualTo($endTime); $time->addSeconds($interval)) {
            // Kiểm tra xem đã có bản ghi lịch sử nào gần mốc thời gian này chưa (trong khoảng +- 2 phút)
            $exists = $session->statsHistory()
                ->whereBetween('created_at', [
                    $time->copy()->subSeconds(120),
                    $time->copy()->addSeconds(120)
                ])
                ->exists();

            if (!$exists) {
                // Đếm số lượng comment tại mốc $time
                $commentsAtTime = $session->events()
                    ->where('event_type', 'comment')
                    ->where('event_at', '<=', $time)
                    ->count();

                $r = 0;
                if ($totalCommentsStats > 0) {
                    $r = $commentsAtTime / $totalCommentsStats;
                    if ($r > 1) $r = 1;
                }

                // Nội suy các giá trị stats dựa trên tỷ lệ tăng trưởng comment
                $views = (int) ($currentStats->total_views * $r);
                $likes = (int) ($currentStats->total_likes * $r);
                $gifts = (int) ($currentStats->total_gifts * $r);
                $follows = (int) ($currentStats->total_follows * $r);
                $shares = (int) ($currentStats->total_shares * $r);
                
                // Người xem đồng thời biến động nhẹ quanh mức hiện tại dựa trên tỷ lệ r
                $viewerCount = (int) ($currentStats->viewer_count * ($r > 0 ? (0.7 + 0.3 * $r) : 0));
                if ($viewerCount < 0) $viewerCount = 0;

                $history = new \App\Models\LiveSessionStatsHistory([
                    'total_views' => $views,
                    'total_comments' => $commentsAtTime,
                    'total_likes' => $likes,
                    'total_gifts' => $gifts,
                    'total_follows' => $follows,
                    'total_shares' => $shares,
                    'viewer_count' => $viewerCount,
                    'sentiment_positive' => (int) ($currentStats->sentiment_positive * $r),
                    'sentiment_neutral' => (int) ($currentStats->sentiment_neutral * $r),
                    'sentiment_negative' => (int) ($currentStats->sentiment_negative * $r),
                    'leads_count' => (int) ($currentStats->leads_count * $r),
                ]);
                $history->live_session_id = $session->id;
                $history->timestamps = false; // Tắt tự động timestamps của Eloquent để ghi đè thời gian quá khứ
                $history->created_at = $time;
                $history->updated_at = $time;
                $history->save();
            }
        }
    }

    /**
     * Lấy và định dạng lịch sử stats của phiên live.
     */
    private function getFormattedStatsHistory(LiveSession $liveSession): \Illuminate\Support\Collection
    {
        // Tự động lấp đầy các khoảng trống trước khi trả về dữ liệu lịch sử
        $this->backfillStatsHistory($liveSession);

        return $liveSession->statsHistory()
            ->orderBy('created_at')
            ->get()
            ->map(fn ($h) => [
                'time' => $h->created_at?->format('H:i') ?? '',
                'comments' => $h->total_comments,
                'viewers' => $h->viewer_count,
            ]);
    }


    /**
     * Top sản phẩm được nhắc đến trong bình luận (từ AI tags).
     */
    private function getTopProducts(LiveSession $session): array
    {
        $rows = $session->events()
            ->where('event_type', 'comment')
            ->whereNotNull('product_tag')
            ->where('product_tag', '!=', '')
            ->selectRaw("
                product_tag as name,
                COUNT(*) as mentions,
                SUM(CASE WHEN sentiment = 'positive' THEN 1 ELSE 0 END) as positive_count,
                SUM(CASE WHEN question_tag IS NOT NULL AND question_tag != '' THEN 1 ELSE 0 END) as questions
            ")
            ->groupBy('product_tag')
            ->orderByDesc('mentions')
            ->limit(15)
            ->get();

        return $rows->map(fn ($r) => [
            'name' => $r->name,
            'mentions' => (int) $r->mentions,
            'sentiment' => $r->mentions > 0 ? (int) round(($r->positive_count / $r->mentions) * 100) : 0,
            'questions' => (int) $r->questions,
        ])->toArray();
    }

    /**
     * Khách hàng tiềm năng (chốt đơn / có SĐT).
     */
    private function getPotentialCustomers(LiveSession $session): array
    {
        return $session->events()
            ->where('event_type', 'comment')
            ->where(function ($q) {
                $q->where('intent_tag', 'Chốt đơn')
                  ->orWhere('has_phone', true);
            })
            ->orderByDesc('event_at')
            ->limit(50)
            ->get()
            ->map(fn (LiveEvent $e) => [
                'name' => $e->tiktok_nickname ?? 'Unknown',
                'phone' => $e->has_phone ? $this->extractPhone($e->data['comment'] ?? '') : '',
                'product' => $e->product_tag ?? '',
                'comment' => $e->data['comment'] ?? '',
                'time' => $e->event_at?->diffForHumans() ?? '',
            ])
            ->toArray();
    }

    /**
     * Top câu hỏi (từ AI question_tag).
     */
    private function getTopQuestions(LiveSession $session): array
    {
        $rows = $session->events()
            ->where('event_type', 'comment')
            ->whereNotNull('question_tag')
            ->where('question_tag', '!=', '')
            ->selectRaw("
                question_tag as question,
                COUNT(*) as cnt,
                GROUP_CONCAT(DISTINCT NULLIF(product_tag, '') SEPARATOR ', ') as products
            ")
            ->groupBy('question_tag')
            ->orderByDesc('cnt')
            ->limit(15)
            ->get();

        return $rows->map(fn ($r) => [
            'question' => $r->question,
            'count' => (int) $r->cnt,
            'product' => $r->products ?: 'Chung',
        ])->toArray();
    }

    /**
     * Extract phone number from Vietnamese text.
     */
    private function extractPhone(string $text): string
    {
        // Normalize: bỏ dấu chấm, dấu cách, gạch ngang giữa các chữ số
        $normalized = preg_replace('/[\s.\-]/', '', $text);
        if (preg_match('/0\d{9,10}/', $normalized, $matches)) {
            return $matches[0];
        }
        return '';
    }
}

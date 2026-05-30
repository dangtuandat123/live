<?php

namespace App\Http\Controllers;

use App\Ai\Agents\LiveSessionAnalyzer;
use App\Exceptions\TikTokSessionNotFoundException;
use App\Jobs\AnalyzeCommentsJob;
use App\Jobs\CaptureThumbnailJob;
use App\Models\LiveEvent;
use App\Models\LiveSession;
use App\Models\LiveSessionStatsHistory;
use App\Models\LiveStat;
use App\Models\Product;
use App\Services\TikTokService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
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
                'error_message' => $session->error_message,
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
        $userId = $request->user()->id;
        $products = Product::where('user_id', $userId)
            ->orderBy('name')
            ->get(['id', 'name', 'sku', 'price']);

        $activeStreamsCount = LiveSession::forUser($userId)
            ->whereIn('status', ['connecting', 'live'])
            ->count();

        return Inertia::render('Lives/Setup', [
            'products' => $products,
            'active_streams_count' => $activeStreamsCount,
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
        ]);

        $user = $request->user();
        $user->resolveActiveSubscription();
        $features = $user->getSubscriptionFeatures();
        $limitStreams = $features['limit_streams'] ?? 1;

        if ($limitStreams !== -1) {
            $activeSessionsCount = LiveSession::forUser($user->id)
                ->whereIn('status', ['connecting', 'live'])
                ->count();

            if ($activeSessionsCount >= $limitStreams) {
                throw ValidationException::withMessages([
                    'tiktok_username' => ['Bạn đã đạt giới hạn số lượng livestream active tối đa của gói dịch vụ ('.$limitStreams.').'],
                ]);
            }
        }

        // Tạo session DB
        $session = LiveSession::create([
            'user_id' => $request->user()->id,
            'name' => $validated['name'],
            'tiktok_username' => $validated['tiktok_username'],
            'status' => 'connecting',
        ]);

        // Attach products
        if (! empty($validated['product_ids'])) {
            // Đảm bảo chỉ attach products thuộc user
            $validProductIds = Product::where('user_id', $request->user()->id)
                ->whereIn('id', $validated['product_ids'])
                ->pluck('id');
            $session->products()->attach($validProductIds);
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

        $this->checkAndStopIfDurationExceeded($request, $liveSession);

        $liveSession->load(['products', 'stats', 'keywords']);

        // Lấy comments gần nhất từ DB
        $comments = $liveSession->events()
            ->where('event_type', 'comment')
            ->orderByDesc('is_pinned')
            ->orderByDesc('sort_order')
            ->orderByDesc('event_at')
            ->limit(200)
            ->get()
            ->map(fn (LiveEvent $e) => [
                'id' => $e->id,
                'user' => $e->tiktok_nickname ?? 'Unknown',
                'unique_id' => $e->tiktok_unique_id,
                'avatar_url' => $e->data['avatar_url'] ?? null,
                'text' => $e->data['comment'] ?? '',
                'time' => $e->event_at?->locale('vi')->diffForHumans() ?? '',
                'event_at' => $e->event_at?->toISOString(),
                'sentiment' => $e->sentiment ?? 'neutral',
                'intent_tag' => $e->intent_tag,
                'question_tag' => $e->question_tag,
                'product_tag' => $e->product_tag,
                'has_phone' => $e->has_phone ?? false,
                'ai_processed' => (bool) $e->ai_processed,
                'is_pinned' => (bool) $e->is_pinned,
                'is_highlighted' => (bool) $e->is_highlighted,
                'sort_order' => (int) $e->sort_order,
            ]);

        // Sync status từ Python service nếu đang live
        $serviceData = null;
        if ($liveSession->tiktok_session_id && in_array($liveSession->status, ['connecting', 'live'])) {
            try {
                $serviceData = $this->tiktokService->getStats($liveSession->tiktok_session_id);
                if ($serviceData) {
                    $this->syncStats($liveSession, $serviceData);
                }
            } catch (TikTokSessionNotFoundException $e) {
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
                        Log::error('Auto-healing in show failed to restart: '.$restartException->getMessage());
                    }
                }

                if (! $healed) {
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

        $cacheTtl = in_array($liveSession->status, ['ended', 'error']) ? 3600 : 5;
        $potentialCustomersCount = Cache::remember("live_session_{$liveSession->id}_potential_customers_count", $cacheTtl, function () use ($liveSession) {
            return $this->getPotentialCustomersCount($liveSession);
        });
        $topKeywords = Cache::remember("live_session_{$liveSession->id}_top_keywords", $cacheTtl, function () use ($liveSession) {
            return $this->getTopKeywords($liveSession);
        });

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
            'potentialCustomersCount' => $potentialCustomersCount,
            'topKeywords' => $topKeywords,
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
            } catch (TikTokSessionNotFoundException $e) {
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
        } catch (TikTokSessionNotFoundException $e) {
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
                Log::warning('Failed to stop TikTok session during deletion', [
                    'session_id' => $liveSession->tiktok_session_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Xóa session (DB cascade sẽ tự động xóa các bảng quan hệ liên quan)
        $liveSession->delete();

        return redirect()->route('lives.index')->with('success', 'Đã xóa phiên phân tích thành công.');
    }

    /**
     * Refresh AI insights manually.
     */
    public function refreshInsights(Request $request, LiveSession $liveSession)
    {
        if ($liveSession->user_id !== $request->user()->id) {
            abort(403);
        }

        // Cache Throttle Check
        $cacheKey = "live_session_{$liveSession->id}_last_insight_time";
        $lastInsightTime = Cache::get($cacheKey);
        if ($lastInsightTime !== null) {
            $elapsed = now()->timestamp - $lastInsightTime;
            if ($elapsed < 30) {
                $secondsRemaining = 30 - $elapsed;

                return response()->json(['error' => "Vui lòng đợi {$secondsRemaining} giây trước khi yêu cầu làm mới tiếp theo."], 429);
            }
        }

        // Credit Limit Check
        $user = $request->user();
        $activeSub = $user->resolveActiveSubscription();
        $features = $user->getSubscriptionFeatures();
        $aiCreditsLimit = $features['ai_credits'] ?? 1000;
        if ($aiCreditsLimit !== -1 && $activeSub !== null) {
            if ($activeSub->used_ai_credits >= $aiCreditsLimit) {
                return response()->json(['error' => 'Đã hết tín dụng AI của gói dịch vụ.'], 402);
            }
        }

        // Fetch recent 150 comments
        $comments = $liveSession->events()
            ->where('event_type', 'comment')
            ->orderByDesc('event_at')
            ->limit(150)
            ->get()
            ->map(fn ($e) => [
                'user' => $e->tiktok_nickname ?? 'Unknown',
                'text' => $e->data['comment'] ?? '',
                'time' => $e->event_at?->toISOString() ?? '',
            ])
            ->toArray();

        // Stats
        $liveSession->load('stats');
        $stats = $liveSession->stats ? [
            'total_views' => $liveSession->stats->total_views,
            'total_comments' => $liveSession->stats->total_comments,
            'total_likes' => $liveSession->stats->total_likes,
            'total_gifts' => $liveSession->stats->total_gifts,
            'total_follows' => $liveSession->stats->total_follows,
            'total_shares' => $liveSession->stats->total_shares,
            'viewer_count' => $liveSession->stats->viewer_count,
            'leads_count' => $liveSession->stats->leads_count,
        ] : [];

        // Products
        $products = $liveSession->products->map(fn ($p) => [
            'name' => $p->name,
            'sku' => $p->sku,
            'price' => $p->price,
            'keywords' => $p->keywords ?? [],
        ])->toArray();

        // Keywords
        $keywords = $liveSession->keywords->pluck('keyword')->toArray();

        // Memory
        $oldMemory = $liveSession->ai_context_summary ?? '';

        $inputData = [
            'comments' => $comments,
            'stats' => $stats,
            'products' => $products,
            'keywords' => $keywords,
            'old_memory' => $oldMemory,
        ];

        $userMessage = json_encode($inputData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        try {
            $response = (new LiveSessionAnalyzer)
                ->withComments($comments)
                ->withStats($stats)
                ->withProducts($products)
                ->withKeywords($keywords)
                ->withOldMemory($oldMemory)
                ->prompt($userMessage)
                ->toArray();
        } catch (\Throwable $e) {
            Log::error('Failed to refresh AI insights: '.$e->getMessage());

            return response()->json(['error' => 'Dịch vụ AI hiện đang bận. Vui lòng thử lại sau.'], 500);
        }

        if ($response) {
            $liveSession->update([
                'ai_insights' => $response['summary'] ?? $liveSession->ai_insights,
                'ai_alerts' => $response['alerts'] ?? $liveSession->ai_alerts,
            ]);

            if ($activeSub !== null) {
                $activeSub->used_ai_credits += count($comments);
                $activeSub->save();
            }
        }

        // Updates the cache key
        Cache::put($cacheKey, now()->timestamp);

        // Clears/updates session cache
        $this->clearSessionCache($liveSession->id);

        return response()->json([
            'ai_insights' => $liveSession->ai_insights,
            'ai_alerts' => $liveSession->ai_alerts,
        ]);
    }

    /**
     * AJAX: Fetch events mới từ Python service và lưu vào DB.
     */
    public function fetchEvents(Request $request, LiveSession $liveSession)
    {
        if ($liveSession->user_id !== $request->user()->id) {
            abort(403);
        }

        $this->checkAndStopIfDurationExceeded($request, $liveSession);

        if (! $liveSession->tiktok_session_id) {
            return response()->json(['message' => 'No TikTok session linked'], 400);
        }

        // Khởi tạo TTL cho cache: cache lâu hơn (1 giờ) nếu phiên live đã kết thúc
        $cacheTtl = in_array($liveSession->status, ['ended', 'error']) ? 3600 : 5;
        $historyTtl = in_array($liveSession->status, ['ended', 'error']) ? 3600 : 10;

        $topProducts = Cache::remember("live_session_{$liveSession->id}_top_products", $cacheTtl, function () use ($liveSession) {
            return $this->getTopProducts($liveSession);
        });

        $potentialCustomers = Cache::remember("live_session_{$liveSession->id}_potential_customers", $cacheTtl, function () use ($liveSession) {
            return $this->getPotentialCustomers($liveSession);
        });

        $topQuestions = Cache::remember("live_session_{$liveSession->id}_top_questions", $cacheTtl, function () use ($liveSession) {
            return $this->getTopQuestions($liveSession);
        });

        $statsHistory = Cache::remember("live_session_{$liveSession->id}_stats_history", $historyTtl, function () use ($liveSession) {
            return $this->getFormattedStatsHistory($liveSession);
        });

        $potentialCustomersCount = Cache::remember("live_session_{$liveSession->id}_potential_customers_count", $cacheTtl, function () use ($liveSession) {
            return $this->getPotentialCustomersCount($liveSession);
        });

        $topKeywords = Cache::remember("live_session_{$liveSession->id}_top_keywords", $cacheTtl, function () use ($liveSession) {
            return $this->getTopKeywords($liveSession);
        });

        // Nếu session đã bị lỗi hoặc kết thúc, không gọi service nữa mà trả về dữ liệu DB hiện tại
        if ($liveSession->status === 'error' || $liveSession->status === 'ended') {
            $latestComments = $liveSession->events()
                ->where('event_type', 'comment')
                ->orderByDesc('is_pinned')
                ->orderByDesc('sort_order')
                ->orderByDesc('event_at')
                ->limit(200)
                ->get()
                ->map(fn (LiveEvent $e) => [
                    'id' => $e->id,
                    'user' => $e->tiktok_nickname ?? 'Unknown',
                    'unique_id' => $e->tiktok_unique_id,
                    'avatar_url' => $e->data['avatar_url'] ?? null,
                    'text' => $e->data['comment'] ?? '',
                    'time' => $e->event_at?->locale('vi')->diffForHumans() ?? '',
                    'event_at' => $e->event_at?->toISOString(),
                    'sentiment' => $e->sentiment ?? 'neutral',
                    'intent_tag' => $e->intent_tag,
                    'question_tag' => $e->question_tag,
                    'product_tag' => $e->product_tag,
                    'has_phone' => $e->has_phone ?? false,
                    'ai_processed' => (bool) $e->ai_processed,
                    'is_pinned' => (bool) $e->is_pinned,
                    'is_highlighted' => (bool) $e->is_highlighted,
                    'sort_order' => (int) $e->sort_order,
                ]);

            $liveSession->load('stats');

            return response()->json([
                'new_events' => 0,
                'comments' => $latestComments,
                'stats' => $liveSession->stats,
                'status' => $liveSession->status,
                'error_message' => $liveSession->error_message,
                'duration' => $liveSession->duration_formatted,
                'topProducts' => $topProducts,
                'potentialCustomers' => $potentialCustomers,
                'topQuestions' => $topQuestions,
                'statsHistory' => $statsHistory,
                'potentialCustomersCount' => $potentialCustomersCount,
                'topKeywords' => $topKeywords,
                'ai_insights' => $liveSession->ai_insights,
                'ai_alerts' => $liveSession->ai_alerts,
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
                Cache::forget($cacheKey);

                $this->syncStats($liveSession, $serviceData);

                // Cập nhật duration nếu đang live
                if ($liveSession->status === 'live' && $liveSession->started_at) {
                    $liveSession->update([
                        'duration_seconds' => (int) $liveSession->started_at->diffInSeconds(now()),
                    ]);
                }
            } else {
                // Tăng số lần lỗi kết nối liên tiếp
                $failCount = (int) Cache::get($cacheKey, 0) + 1;
                Cache::put($cacheKey, $failCount, 300); // lưu trong 5 phút

                if ($failCount >= 5) {
                    $liveSession->update([
                        'status' => 'error',
                        'error_message' => 'Không thể kết nối tới dịch vụ TikTok LIVE. Máy chủ phân tích có thể đang bảo trì.',
                    ]);
                    Cache::forget($cacheKey);
                }
            }
        } catch (TikTokSessionNotFoundException $e) {
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
                            'potentialCustomersCount' => $this->getPotentialCustomersCount($liveSession),
                            'topKeywords' => $this->getTopKeywords($liveSession),
                        ]);
                    }
                } catch (\Exception $restartException) {
                    Log::error('Auto-healing in fetchEvents failed to restart: '.$restartException->getMessage());
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
            Cache::forget($cacheKey);
        }

        // Dispatch AI phân tích nếu có events mới và session không bị lỗi
        if ($newEventsCount > 0 && $liveSession->status !== 'error') {
            AnalyzeCommentsJob::dispatch($liveSession->id);
        }

        // Lấy comments mới nhất cho frontend
        $latestComments = $liveSession->events()
            ->where('event_type', 'comment')
            ->orderByDesc('is_pinned')
            ->orderByDesc('sort_order')
            ->orderByDesc('event_at')
            ->limit(200)
            ->get()
            ->map(fn (LiveEvent $e) => [
                'id' => $e->id,
                'user' => $e->tiktok_nickname ?? 'Unknown',
                'unique_id' => $e->tiktok_unique_id,
                'avatar_url' => $e->data['avatar_url'] ?? null,
                'text' => $e->data['comment'] ?? '',
                'time' => $e->event_at?->locale('vi')->diffForHumans() ?? '',
                'event_at' => $e->event_at?->toISOString(),
                'sentiment' => $e->sentiment ?? 'neutral',
                'intent_tag' => $e->intent_tag,
                'question_tag' => $e->question_tag,
                'product_tag' => $e->product_tag,
                'has_phone' => $e->has_phone ?? false,
                'ai_processed' => (bool) $e->ai_processed,
                'is_pinned' => (bool) $e->is_pinned,
                'is_highlighted' => (bool) $e->is_highlighted,
                'sort_order' => (int) $e->sort_order,
            ]);

        $liveSession->load('stats');

        return response()->json([
            'new_events' => $newEventsCount,
            'comments' => $latestComments,
            'stats' => $liveSession->stats,
            'status' => (isset($serviceData) && isset($serviceData['status'])) ? $serviceData['status'] : $liveSession->status,
            'error_message' => $liveSession->error_message,
            'duration' => $liveSession->duration_formatted,
            'topProducts' => $topProducts,
            'potentialCustomers' => $potentialCustomers,
            'topQuestions' => $topQuestions,
            'statsHistory' => $statsHistory,
            'potentialCustomersCount' => $potentialCustomersCount,
            'topKeywords' => $topKeywords,
            'ai_insights' => $liveSession->ai_insights,
            'ai_alerts' => $liveSession->ai_alerts,
        ]);
    }

    // --- Private helpers ---

    private function fetchAndStoreEvents(LiveSession $session): int
    {
        if (! $session->tiktok_session_id) {
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

            if (! $data || empty($data['events'])) {
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
                    $dataHash = $event['id'] ?? match ($eventType) {
                        'comment' => md5($event['data']['comment'] ?? ''),
                        'gift' => md5(($event['data']['gift_id'] ?? '').'_'.($event['data']['repeat_count'] ?? 1)),
                        'like' => md5('like_'.($event['data']['count'] ?? 1)),
                        default => md5($eventType.'_'.($event['timestamp'] ?? '')),
                    };

                    // Đồng bộ hóa trích xuất số điện thoại (Instant Phone Capture)
                    $hasPhone = false;
                    if ($eventType === 'comment' && ! empty($event['data']['comment'])) {
                        $normalized = preg_replace('/[\s.\-]/', '', $event['data']['comment']);
                        $hasPhone = (bool) preg_match('/0\d{9,10}/', $normalized);
                    }

                    LiveEvent::create([
                        'live_session_id' => $session->id,
                        'event_type' => $event['type'] ?? 'unknown',
                        // Sửa lỗi lọt trùng lặp do nullability: gán giá trị mặc định cho tiktok_user_id nếu null
                        'tiktok_user_id' => ! empty($event['user_id']) ? (string) $event['user_id'] : 'anonymous',
                        'tiktok_unique_id' => $event['unique_id'] ?? null,
                        'tiktok_nickname' => $event['nickname'] ?? null,
                        'data' => $event['data'] ?? [],
                        'data_hash' => $dataHash,
                        'event_at' => $eventAt,
                        'has_phone' => $hasPhone,
                    ]);
                    $pageCount++;
                } catch (UniqueConstraintViolationException) {
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
                if ($newStatus === 'ended' && ! $session->ended_at) {
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
        $currentStats = $session->stats()->first();

        // Tính toán các thông số từ bảng live_events để làm nguồn đối chiếu chính xác
        $dbCommentsCount = $session->events()->where('event_type', 'comment')->count();
        $dbGiftsCount = $session->events()->where('event_type', 'gift')->count();
        $dbFollowsCount = $session->events()->where('event_type', 'follow')->count();
        $dbSharesCount = $session->events()->where('event_type', 'share')->count();

        // Đảm bảo các chỉ số tích lũy không bị giảm đi (sử dụng max)
        $totalViews = max($currentStats?->total_views ?? 0, $stats['total_views'] ?? 0);
        $totalLikes = max($currentStats?->total_likes ?? 0, $stats['total_likes'] ?? 0);

        $totalComments = max($dbCommentsCount, $stats['total_comments'] ?? 0);
        $totalGifts = max($dbGiftsCount, $stats['total_gifts'] ?? 0);
        $totalFollows = max($dbFollowsCount, $stats['total_follows'] ?? 0);
        $totalShares = max($dbSharesCount, $stats['total_shares'] ?? 0);

        $session->stats()->updateOrCreate(
            ['live_session_id' => $session->id],
            [
                'total_views' => $totalViews,
                'total_comments' => $totalComments,
                'total_likes' => $totalLikes,
                'total_gifts' => $totalGifts,
                'total_follows' => $totalFollows,
                'total_shares' => $totalShares,
                'viewer_count' => $stats['viewer_count'] ?? 0,
            ],
        );

        $coverUrl = $serviceData['cover_url'] ?? null;

        // Tự động tải hoặc cập nhật thumbnail định kỳ mỗi 10 phút nếu phiên live đang hoạt động
        // Tránh dispatch job khi đang connecting mà chưa có cover_url (vì chắc chắn sẽ fail và bị lock 2 phút vô ích)
        if ($session->status === 'live' || ($session->status === 'connecting' && ! empty($coverUrl))) {
            $cacheKey = "live_session_{$session->id}_thumbnail_lock";
            if (! Cache::has($cacheKey)) {
                // Đặt lock tạm thời trong 1 phút (60 giây) để tránh spam gửi Job liên tục khi đang xử lý
                Cache::put($cacheKey, true, 60);

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
        if (! in_array($session->status, ['live', 'connecting'])) {
            return;
        }

        // Tự động lấp đầy khoảng trống lịch sử trong quá khứ trước
        $this->backfillStatsHistory($session);

        $currentStats = $session->stats;
        if (! $currentStats) {
            return;
        }

        $latestHistory = $session->statsHistory()->orderByDesc('created_at')->first();

        $shouldRecord = false;
        if (! $latestHistory) {
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
        if (! $startedAt) {
            return;
        }

        $now = now();
        $interval = 300; // 5 phút = 300 giây

        $endTime = $session->ended_at ?: ($session->status === 'ended' ? $session->updated_at : $now);
        if (! $endTime) {
            $endTime = $now;
        }
        // Lùi lại 1 phút để tránh xung đột với bản ghi hiện tại đang ghi nhận realtime
        $endTime = $endTime->copy()->subMinutes(1);

        $startTime = $startedAt->copy()->addSeconds($interval);

        $currentStats = $session->stats;
        if (! $currentStats) {
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
                    $time->copy()->addSeconds(120),
                ])
                ->exists();

            if (! $exists) {
                // Đếm số lượng comment tại mốc $time
                $commentsAtTime = $session->events()
                    ->where('event_type', 'comment')
                    ->where('event_at', '<=', $time)
                    ->count();

                $r = 0;
                if ($totalCommentsStats > 0) {
                    $r = $commentsAtTime / $totalCommentsStats;
                    if ($r > 1) {
                        $r = 1;
                    }
                }

                // Nội suy các giá trị stats dựa trên tỷ lệ tăng trưởng comment
                $views = (int) ($currentStats->total_views * $r);
                $likes = (int) ($currentStats->total_likes * $r);
                $gifts = (int) ($currentStats->total_gifts * $r);
                $follows = (int) ($currentStats->total_follows * $r);
                $shares = (int) ($currentStats->total_shares * $r);

                // Người xem đồng thời biến động nhẹ quanh mức hiện tại dựa trên tỷ lệ r
                $viewerCount = (int) ($currentStats->viewer_count * ($r > 0 ? (0.7 + 0.3 * $r) : 0));
                if ($viewerCount < 0) {
                    $viewerCount = 0;
                }

                $history = new LiveSessionStatsHistory([
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
    private function getFormattedStatsHistory(LiveSession $liveSession): Collection
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
                'id' => $e->id,
                'name' => $e->tiktok_nickname ?? 'Unknown',
                'phone' => $e->has_phone ? $this->extractPhone($e->data['comment'] ?? '') : '',
                'product' => $e->product_tag ?? '',
                'comment' => $e->data['comment'] ?? '',
                'time' => $e->event_at?->locale('vi')->diffForHumans() ?? '',
                'is_pinned' => (bool) $e->is_pinned,
                'is_highlighted' => (bool) $e->is_highlighted,
                'sort_order' => (int) $e->sort_order,
                'qty' => $e->data['qty'] ?? 1,
                'note' => $e->data['note'] ?? '',
                'status' => $e->data['status'] ?? 'pending',
            ])
            ->toArray();
    }

    /**
     * Top câu hỏi (từ AI question_tag).
     */
    private function getTopQuestions(LiveSession $session): array
    {
        $dbDriver = $session->events()->getConnection()->getDriverName();
        $groupConcatSql = $dbDriver === 'sqlite'
            ? "GROUP_CONCAT(DISTINCT NULLIF(product_tag, '')) as products"
            : "GROUP_CONCAT(DISTINCT NULLIF(product_tag, '') SEPARATOR ', ') as products";

        $rows = $session->events()
            ->where('event_type', 'comment')
            ->where(function ($query) {
                $query->whereNotNull('question_tag')
                    ->where('question_tag', '!=', '')
                    ->orWhere('intent_tag', 'Hỏi thông tin');
            })
            ->selectRaw("
                COALESCE(NULLIF(question_tag, ''), 'Hỏi chung') as question,
                COUNT(*) as cnt,
                {$groupConcatSql}
            ")
            ->groupByRaw("COALESCE(NULLIF(question_tag, ''), 'Hỏi chung')")
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

    /**
     * Check if the livestream duration has exceeded package maximum allowed duration and stop it.
     */
    private function checkAndStopIfDurationExceeded(Request $request, LiveSession $liveSession): void
    {
        if (in_array($liveSession->status, ['connecting', 'live']) && $liveSession->started_at) {
            $user = $request->user();
            $features = $user->getSubscriptionFeatures();
            $maxDurationHours = $features['max_duration_hours'] ?? 1;

            if ((int) $maxDurationHours === -1) {
                return;
            }

            $elapsedSeconds = $liveSession->started_at->diffInSeconds(now());
            $durationHours = $elapsedSeconds / 3600;

            if ($durationHours >= $maxDurationHours) {
                if ($liveSession->tiktok_session_id) {
                    try {
                        $this->tiktokService->stopSession($liveSession->tiktok_session_id);
                    } catch (\Exception $e) {
                        Log::error('Failed to stop TikTok session: '.$e->getMessage());
                    }
                }

                $liveSession->update([
                    'status' => 'ended',
                    'ended_at' => now(),
                    'duration_seconds' => (int) $elapsedSeconds,
                    'error_message' => 'Phiên livestream đã tự động kết thúc do vượt quá thời lượng tối đa cho phép của gói dịch vụ ('.$maxDurationHours.' giờ).',
                ]);
            }
        }
    }

    public function updateEvent(Request $request, LiveEvent $liveEvent)
    {
        $liveSession = $liveEvent->liveSession;
        if (! $liveSession || $liveSession->user_id !== $request->user()->id) {
            abort(403);
        }

        $validated = $request->validate([
            'is_pinned' => ['nullable', 'boolean'],
            'is_highlighted' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer'],
            'qty' => ['nullable', 'integer'],
            'note' => ['nullable', 'string', 'nullable'],
            'status' => ['nullable', 'string'],
        ]);

        $updates = [];
        if (isset($validated['is_pinned'])) {
            $updates['is_pinned'] = $validated['is_pinned'];
        }
        if (isset($validated['is_highlighted'])) {
            $updates['is_highlighted'] = $validated['is_highlighted'];
        }
        if (isset($validated['sort_order'])) {
            $updates['sort_order'] = $validated['sort_order'];
        }

        if (! empty($updates)) {
            $liveEvent->update($updates);
        }

        $orderUpdated = false;
        $data = $liveEvent->data ?? [];
        foreach (['qty', 'note', 'status'] as $field) {
            if ($request->has($field)) {
                $data[$field] = $request->input($field);
                $orderUpdated = true;
            }
        }

        if ($orderUpdated) {
            $liveEvent->data = $data;
            $liveEvent->save();
        }

        $this->clearSessionCache($liveSession->id);

        return response()->json([
            'success' => true,
            'event' => [
                'id' => $liveEvent->id,
                'is_pinned' => (bool) $liveEvent->is_pinned,
                'is_highlighted' => (bool) $liveEvent->is_highlighted,
                'sort_order' => (int) $liveEvent->sort_order,
                'qty' => $liveEvent->data['qty'] ?? 1,
                'note' => $liveEvent->data['note'] ?? '',
                'status' => $liveEvent->data['status'] ?? 'pending',
            ],
        ]);
    }

    private function getPotentialCustomersCount(LiveSession $session): int
    {
        return $session->events()
            ->where('event_type', 'comment')
            ->whereNotNull('tiktok_user_id')
            ->where('tiktok_user_id', '!=', '')
            ->where(function ($q) {
                $q->where('intent_tag', 'Chốt đơn')
                    ->orWhere('has_phone', true);
            })
            ->distinct('tiktok_user_id')
            ->count('tiktok_user_id');
    }

    private function getTopKeywords(LiveSession $session): array
    {
        $setupKeywords = $session->keywords()->pluck('keyword')->toArray();
        $keywordCounts = [];

        foreach ($setupKeywords as $kw) {
            $kw = trim($kw);
            if ($kw === '') {
                continue;
            }
            $count = $session->events()
                ->where('event_type', 'comment')
                ->where('data->comment', 'like', "%{$kw}%")
                ->count();
            if ($count > 0) {
                $keywordCounts[] = [
                    'keyword' => $kw,
                    'count' => $count,
                ];
            }
        }

        // Sort descending by count
        usort($keywordCounts, function ($a, $b) {
            return $b['count'] <=> $a['count'];
        });

        return $keywordCounts;
    }

    private function clearSessionCache(int $sessionId): void
    {
        Cache::forget("live_session_{$sessionId}_top_products");
        Cache::forget("live_session_{$sessionId}_potential_customers");
        Cache::forget("live_session_{$sessionId}_top_questions");
        Cache::forget("live_session_{$sessionId}_stats_history");
        Cache::forget("live_session_{$sessionId}_potential_customers_count");
        Cache::forget("live_session_{$sessionId}_top_keywords");
    }
}

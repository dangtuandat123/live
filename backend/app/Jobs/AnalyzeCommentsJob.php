<?php

namespace App\Jobs;

use App\Ai\Agents\CommentAnalyzer;
use App\Ai\Agents\LiveSessionAnalyzer;
use App\Models\LiveEvent;
use App\Models\LiveSession;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AnalyzeCommentsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 120;

    public array $backoff = [10, 30];

    /**
     * Giá trị hợp lệ cho các AI fields — validation trước khi lưu DB.
     */
    private const VALID_SENTIMENTS = ['positive', 'neutral', 'negative'];

    private const VALID_INTENTS = ['Chốt đơn', 'Hỏi thông tin', 'Phản hồi SP', 'Yêu cầu hỗ trợ'];

    private const VALID_QUESTIONS = [
        'Hỏi giá', 'Hỏi size', 'Hỏi ship', 'Hỏi chất liệu',
        'Hỏi màu', 'Hỏi tồn kho', 'Hỏi giảm giá', 'Hỏi bảo hành',
        'Hỏi thanh toán', 'Hỏi mùi hương', 'Hỏi công dụng',
        'Hỏi cấu hình', 'Hỏi trả góp', 'Hỏi xuất xứ', 'Hỏi phụ kiện',
        'Hỏi tình trạng', 'Hỏi quà tặng',
    ];

    public function __construct(
        private int $liveSessionId,
    ) {}

    public function uniqueId(): string
    {
        return 'analyze-comments-'.$this->liveSessionId;
    }

    public function handle(): void
    {
        $lockKey = 'analyze-comments-lock-'.$this->liveSessionId;
        $lock = cache()->lock($lockKey, 120);
        if (! $lock->get()) {
            return;
        }

        $dispatchedNext = false;

        try {
            $session = LiveSession::with(['products', 'keywords', 'stats', 'user'])->find($this->liveSessionId);
            if (! $session) {
                return;
            }

            // Chỉ xử lý AI cho phiên live đang hoạt động
            if (! in_array($session->status, ['live', 'connecting'])) {
                return;
            }

            $user = $session->user;
            if (! $user) {
                return;
            }

            $activeSub = $user->resolveActiveSubscription();
            $features = $user->getSubscriptionFeatures();
            $aiCreditsLimit = $features['ai_credits'] ?? 1000;

            if ($aiCreditsLimit !== -1 && $activeSub) {
                if ($activeSub->used_ai_credits >= $aiCreditsLimit) {
                    $session->update([
                        'status' => 'error',
                        'error_message' => 'Đã hết tín dụng AI của gói dịch vụ.',
                    ]);

                    return;
                }
            }

            // Batch 50 comments — giảm từ 100 để tăng accuracy
            $unprocessed = LiveEvent::where('live_session_id', $this->liveSessionId)
                ->where('event_type', 'comment')
                ->where('ai_processed', false)
                ->orderBy('event_at')
                ->limit(50)
                ->get();

            if ($unprocessed->isEmpty()) {
                return;
            }

            // Build compact input: "ID|text"
            $commentsText = $unprocessed
                ->map(fn ($e) => ['id' => $e->id, 'text' => $e->data['comment'] ?? ''])
                ->filter(fn ($c) => ! empty($c['text']));

            if ($commentsText->isEmpty()) {
                LiveEvent::whereIn('id', $unprocessed->pluck('id'))
                    ->update(['ai_processed' => true, 'sentiment' => 'neutral']);

                $this->clearSessionCache();

                // Check if there are more unprocessed comments to continue the pipeline
                $hasMoreUnprocessed = LiveEvent::where('live_session_id', $this->liveSessionId)
                    ->where('event_type', 'comment')
                    ->where('ai_processed', false)
                    ->exists();

                if ($hasMoreUnprocessed) {
                    $lock->release();
                    $dispatchedNext = true;
                    self::dispatch($this->liveSessionId)->delay(now()->addSeconds(2));
                }

                return;
            }

            // Chuẩn bị context
            $products = $session->products->map(fn ($p) => [
                'name' => $p->name,
                'keywords' => $p->keywords ?? [],
            ])->toArray();

            $productNames = $session->products->pluck('name')->toArray();
            $keywords = $session->keywords->pluck('keyword')->toArray();

            try {
                // Live metadata
                $liveTitle = $session->name ?? '';
                $streamerName = $session->tiktok_username ?? '';
                $viewerCount = 0;

                if ($session->stats) {
                    $viewerCount = $session->stats->viewer_count ?? 0;
                }

                // === Memory: Đọc context từ batch trước ===
                $memoryContext = $session->ai_context_summary ?? '';

                // Build user message: "ID|text" per line
                $userMessage = $commentsText->map(fn ($c) => "{$c['id']}|{$c['text']}")->join("\n");

                Log::info('AI comment analysis start', [
                    'session_id' => $this->liveSessionId,
                    'comments_count' => $commentsText->count(),
                    'has_memory' => ! empty($memoryContext),
                ]);

                $response = (new CommentAnalyzer)
                    ->withProducts($products)
                    ->withKeywords($keywords)
                    ->withLiveContext($liveTitle, $streamerName, $viewerCount)
                    ->withMemory($memoryContext)
                    ->prompt($userMessage);

                // StructuredAgentResponse → array (json_decode của AI output)
                $responseArray = $response->toArray();

                // Extract results array
                $results = $responseArray['results'] ?? $responseArray;

                // Đảm bảo là array
                if (! is_array($results) || empty($results)) {
                    throw new \RuntimeException('AI response is empty or not an array');
                }

                // Nếu không phải là list tuần tự (associative array), kiểm tra xem có phải single object không
                if (! array_is_list($results)) {
                    if (isset($results['id'])) {
                        $results = [$results];
                    } else {
                        throw new \RuntimeException('AI response format is invalid: expected list of results');
                    }
                }

                // Debug log
                if (! empty($results) && config('app.debug')) {
                    Log::info('AI analysis batch', [
                        'session_id' => $this->liveSessionId,
                        'input_count' => $commentsText->count(),
                        'output_count' => count($results),
                        'sample' => array_slice($results, 0, 3),
                    ]);
                }

                // Prepare local variables to track batch statistics changes
                $batchStats = [
                    'positive' => 0,
                    'neutral' => 0,
                    'negative' => 0,
                    'new_leads_count' => 0,
                ];

                // Validate + save trong transaction
                DB::transaction(function () use ($results, $unprocessed, $productNames, $session, &$batchStats, $activeSub, $commentsText, $responseArray) {
                    $processedIds = [];
                    $positive = 0;
                    $neutral = 0;
                    $negative = 0;
                    $chotDonUsers = [];
                    $updatesGrouped = [];

                    foreach ($results as $result) {
                        $eventId = $result['id'] ?? null;
                        if (! $eventId) {
                            continue;
                        }

                        $processedIds[] = $eventId;

                        // Validate AI output trước khi save
                        $validated = $this->validateResult($result, $productNames);

                        // Find matching event in unprocessed to identify user ID
                        $event = $unprocessed->firstWhere('id', $eventId);
                        $tiktokUserId = $event ? $event->tiktok_user_id : null;

                        // Guard for has_phone: if it is already true in the DB, keep it true
                        if ($event && $event->has_phone) {
                            $validated['has_phone'] = true;
                        }

                        // Increment local counts
                        if ($validated['sentiment'] === 'positive') {
                            $positive++;
                        } elseif ($validated['sentiment'] === 'negative') {
                            $negative++;
                        } else {
                            $neutral++;
                        }

                        if ($validated['intent_tag'] === 'Chốt đơn' && $tiktokUserId) {
                            $chotDonUsers[] = $tiktokUserId;
                        }

                        // Group updates by target attributes serialization to perform bulk updates
                        $groupKey = serialize([
                            'sentiment' => $validated['sentiment'],
                            'intent_tag' => $validated['intent_tag'],
                            'question_tag' => $validated['question_tag'],
                            'product_tag' => $validated['product_tag'],
                            'has_phone' => $validated['has_phone'],
                        ]);

                        $updatesGrouped[$groupKey][] = $eventId;
                    }

                    // Calculate the new leads count before executing the bulk comments update query
                    $chotDonUsers = array_values(array_unique($chotDonUsers));
                    $newLeadsCount = 0;
                    if (! empty($chotDonUsers)) {
                        $existingLeads = LiveEvent::where('live_session_id', $this->liveSessionId)
                            ->where('event_type', 'comment')
                            ->where('ai_processed', true)
                            ->where('intent_tag', 'Chốt đơn')
                            ->whereIn('tiktok_user_id', $chotDonUsers)
                            ->pluck('tiktok_user_id')
                            ->unique()
                            ->toArray();

                        $newLeads = array_diff($chotDonUsers, $existingLeads);
                        $newLeadsCount = count($newLeads);
                    }

                    // Execute grouped bulk updates
                    foreach ($updatesGrouped as $serializedAttributes => $ids) {
                        $attributes = unserialize($serializedAttributes);
                        $attributes['ai_processed'] = true;

                        LiveEvent::whereIn('id', $ids)
                            ->where('live_session_id', $this->liveSessionId)
                            ->update($attributes);
                    }

                    // Đánh dấu comments không có trong results (AI bỏ sót)
                    $missingIds = $unprocessed->pluck('id')->diff($processedIds)->toArray();
                    if (! empty($missingIds)) {
                        LiveEvent::whereIn('id', $missingIds)
                            ->where('live_session_id', $this->liveSessionId)
                            ->update(['ai_processed' => true, 'sentiment' => 'neutral']);

                        $neutral += count($missingIds);
                    }

                    $batchStats = [
                        'positive' => $positive,
                        'neutral' => $neutral,
                        'negative' => $negative,
                        'new_leads_count' => $newLeadsCount,
                    ];

                    // Cập nhật aggregate stats inside transaction
                    $this->updateAggregateStats($session, $batchStats);

                    if ($activeSub) {
                        $activeSub->increment('used_ai_credits', $commentsText->count());
                    }

                    // R2: Integrate AI Auto-Discovery Keywords
                    $extractedKeywords = $responseArray['extracted_keywords'] ?? [];
                    if (is_array($extractedKeywords) && ! empty($extractedKeywords)) {
                        $currentCount = $session->keywords()->count();
                        if ($currentCount < 30) {
                            $normalizedKeywords = [];
                            foreach ($extractedKeywords as $kw) {
                                if (! is_string($kw)) {
                                    continue;
                                }
                                $normalized = mb_strtolower(trim($kw));
                                if ($normalized !== '' && ! in_array($normalized, $normalizedKeywords)) {
                                    $normalizedKeywords[] = $normalized;
                                }
                            }

                            if (! empty($normalizedKeywords)) {
                                $existingKeywords = $session->keywords()
                                    ->pluck('keyword')
                                    ->map(fn ($k) => mb_strtolower(trim($k)))
                                    ->toArray();

                                $newKeywords = [];
                                foreach ($normalizedKeywords as $kw) {
                                    if (! in_array($kw, $existingKeywords)) {
                                        $newKeywords[] = $kw;
                                    }
                                }

                                if (! empty($newKeywords)) {
                                    $availableSlots = 30 - $currentCount;
                                    $toAdd = array_slice($newKeywords, 0, $availableSlots);
                                    foreach ($toAdd as $kw) {
                                        $session->keywords()->create(['keyword' => $kw]);
                                    }
                                }
                            }
                        }
                    }
                });

                // Clear session cache dynamically
                $this->clearSessionCache();

                // === Memory: Lưu session_note từ AI cho batch tiếp theo ===
                $sessionNote = $responseArray['session_note'] ?? null;
                if ($sessionNote && is_string($sessionNote)) {
                    $session->update(['ai_context_summary' => mb_substr($sessionNote, 0, 500)]);
                }

                // === Auto-Insights: Tự động chạy phân tích insight/alert nếu vượt qua throttle 30s ===
                $insightCacheKey = "live_session_{$this->liveSessionId}_last_insight_time";
                $lastInsightTime = cache()->get($insightCacheKey);
                if (! $lastInsightTime || (now()->timestamp - $lastInsightTime) >= 30) {
                    $session->load('stats');

                    // Fetch recent 150 comments
                    $insightComments = LiveEvent::where('live_session_id', $this->liveSessionId)
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

                    $insightStats = $session->stats ? [
                        'total_views' => $session->stats->total_views,
                        'total_comments' => $session->stats->total_comments,
                        'total_likes' => $session->stats->total_likes,
                        'total_gifts' => $session->stats->total_gifts,
                        'total_follows' => $session->stats->total_follows,
                        'total_shares' => $session->stats->total_shares,
                        'viewer_count' => $session->stats->viewer_count,
                        'leads_count' => $session->stats->leads_count,
                    ] : [];

                    $insightProducts = $session->products->map(fn ($p) => [
                        'name' => $p->name,
                        'sku' => $p->sku,
                        'price' => $p->price,
                        'keywords' => $p->keywords ?? [],
                    ])->toArray();

                    $insightKeywords = $session->keywords->pluck('keyword')->toArray();
                    $insightOldMemory = $session->ai_context_summary ?? '';

                    $insightInput = [
                        'comments' => $insightComments,
                        'stats' => $insightStats,
                        'products' => $insightProducts,
                        'keywords' => $insightKeywords,
                        'old_memory' => $insightOldMemory,
                    ];

                    $insightUserMessage = json_encode($insightInput, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

                    try {
                        $insightResponse = (new LiveSessionAnalyzer)
                            ->withComments($insightComments)
                            ->withStats($insightStats)
                            ->withProducts($insightProducts)
                            ->withKeywords($insightKeywords)
                            ->withOldMemory($insightOldMemory)
                            ->prompt($insightUserMessage)
                            ->toArray();

                        if ($insightResponse) {
                            $session->update([
                                'ai_insights' => $insightResponse['summary'] ?? $session->ai_insights,
                                'ai_alerts' => $insightResponse['alerts'] ?? $session->ai_alerts,
                            ]);

                            // Tính credit nhất quán cho lần gọi insights tổng hợp.
                            if ($activeSub) {
                                $activeSub->increment('used_ai_credits', LiveSessionAnalyzer::INSIGHTS_CREDIT_COST);
                            }
                        }

                        cache()->put($insightCacheKey, now()->timestamp);
                        $this->clearSessionCache();
                    } catch (\Throwable $insightEx) {
                        Log::warning('Automatic AI insights analysis failed in job', [
                            'session_id' => $this->liveSessionId,
                            'error' => $insightEx->getMessage(),
                        ]);
                    }
                }

                // Vét sạch comments chưa được xử lý AI của session này
                $hasMoreUnprocessed = LiveEvent::where('live_session_id', $this->liveSessionId)
                    ->where('event_type', 'comment')
                    ->where('ai_processed', false)
                    ->exists();

                if ($hasMoreUnprocessed) {
                    $lock->release();
                    $dispatchedNext = true;

                    // Dispatch tiếp với delay 2 giây để tránh spam / rate limit AI
                    self::dispatch($this->liveSessionId)->delay(now()->addSeconds(2));

                    Log::info('Dispatched next AnalyzeCommentsJob to process remaining comments', [
                        'session_id' => $this->liveSessionId,
                    ]);
                }

            } catch (\Throwable $e) {
                Log::error('AI comment analysis failed', [
                    'session_id' => $this->liveSessionId,
                    'error' => $e->getMessage(),
                    'comments_count' => $commentsText->count(),
                ]);

                // Khắc phục lỗi Poison Pill deadlock: Đánh dấu các comment là đã xử lý (sentiment neutral)
                // nếu lỗi không thể tự phục hồi (JSON parse, null response) hoặc đã đạt số lần thử tối đa.
                $isLastAttempt = $this->attempts() >= $this->tries;
                $errMsg = strtolower($e->getMessage());
                $isUnrecoverable = ! str_contains($errMsg, 'rate limit') &&
                                   ! str_contains($errMsg, 'timeout') &&
                                   ! str_contains($errMsg, 'connection');

                if ($isLastAttempt || $isUnrecoverable) {
                    try {
                        DB::table('live_events')
                            ->whereIn('id', $unprocessed->pluck('id'))
                            ->update(['ai_processed' => true, 'sentiment' => 'neutral']);

                        $this->clearSessionCache();

                        Log::warning('Marked batch comments as processed (neutral) due to unrecoverable AI error or max retries reached to prevent queue deadlock', [
                            'session_id' => $this->liveSessionId,
                            'comments_ids' => $unprocessed->pluck('id')->toArray(),
                        ]);

                        // Check if there are more unprocessed comments to continue the pipeline
                        $hasMoreUnprocessed = LiveEvent::where('live_session_id', $this->liveSessionId)
                            ->where('event_type', 'comment')
                            ->where('ai_processed', false)
                            ->exists();

                        if ($hasMoreUnprocessed) {
                            $lock->release();
                            $dispatchedNext = true;
                            self::dispatch($this->liveSessionId)->delay(now()->addSeconds(2));
                        }
                    } catch (\Throwable $dbEx) {
                        Log::error('Failed to mark poison pill comments as processed', [
                            'error' => $dbEx->getMessage(),
                        ]);
                    }
                }

                // Không retry nếu lỗi auth
                if (str_contains($e->getMessage(), 'API key') || str_contains($e->getMessage(), '401') || str_contains($e->getMessage(), 'auth')) {
                    $this->fail($e);

                    return;
                }

                // Rethrow để Laravel queue tự động retry theo $tries/$backoff nếu còn lượt
                throw $e;
            }
        } finally {
            if (isset($lock) && ! $dispatchedNext) {
                $lock->release();
            }
        }
    }

    /**
     * Validate AI response trước khi lưu DB.
     * Reject giá trị không hợp lệ, fuzzy match product_tag.
     */
    private function validateResult(array $result, array $productNames): array
    {
        $sentiment = $result['sentiment'] ?? 'neutral';
        if (! in_array($sentiment, self::VALID_SENTIMENTS)) {
            $sentiment = 'neutral';
        }

        $intentTag = $result['intent_tag'] ?? null;
        if ($intentTag !== null && ! in_array($intentTag, self::VALID_INTENTS)) {
            $intentTag = null;
        }

        $questionTag = $result['question_tag'] ?? null;
        if ($questionTag !== null && ! in_array($questionTag, self::VALID_QUESTIONS)) {
            $questionTag = null;
        }

        $productTag = $result['product_tag'] ?? null;
        if ($productTag !== null) {
            $productTag = $this->matchProductTag($productTag, $productNames);
        }

        return [
            'sentiment' => $sentiment,
            'intent_tag' => $intentTag,
            'question_tag' => $questionTag,
            'product_tag' => $productTag,
            'has_phone' => (bool) ($result['has_phone'] ?? false),
        ];
    }

    /**
     * Fuzzy match product_tag against danh sách sản phẩm đã đăng ký.
     * Trả null nếu không match — tránh hallucination đẩy sản phẩm "ma" vào thống kê Top sản phẩm.
     */
    private function matchProductTag(string $aiTag, array $productNames): ?string
    {
        // Exact match
        foreach ($productNames as $name) {
            if (mb_strtolower($aiTag) === mb_strtolower($name)) {
                return $name;
            }
        }

        // Contains match — AI tag chứa tên sản phẩm hoặc ngược lại
        foreach ($productNames as $name) {
            if (
                mb_stripos($aiTag, $name) !== false ||
                mb_stripos($name, $aiTag) !== false
            ) {
                return $name;
            }
        }

        // Không khớp sản phẩm nào đã đăng ký → trả null để tránh hallucination.
        if (config('app.debug')) {
            Log::debug('AI product_tag not in product list, discarded', [
                'ai_tag' => $aiTag,
                'registered_products' => $productNames,
            ]);
        }

        return null;
    }

    /**
     * Gộp cập nhật sentiment + leads.
     * Leads = COUNT DISTINCT tiktok_user_id WHERE intent_tag = 'Chốt đơn'
     * → Tránh 1 user spam "đã mua" 82 lần tính 82 leads.
     */
    private function updateAggregateStats(LiveSession $session, array $batchStats): void
    {
        $newLeadsCount = $batchStats['new_leads_count'] ?? 0;

        $statsModel = $session->stats;
        if (! $statsModel) {
            // Create stats record if not exists
            $session->stats()->create([
                'sentiment_positive' => $batchStats['positive'],
                'sentiment_neutral' => $batchStats['neutral'],
                'sentiment_negative' => $batchStats['negative'],
                'leads_count' => $newLeadsCount,
            ]);
        } else {
            // Increment the stats values atomically using the query builder to bypass model casts
            $session->stats()->update([
                'sentiment_positive' => DB::raw("sentiment_positive + {$batchStats['positive']}"),
                'sentiment_neutral' => DB::raw("sentiment_neutral + {$batchStats['neutral']}"),
                'sentiment_negative' => DB::raw("sentiment_negative + {$batchStats['negative']}"),
                'leads_count' => DB::raw("leads_count + {$newLeadsCount}"),
            ]);
            $statsModel->refresh();
        }
    }

    /**
     * Clear the cache for the live session.
     */
    private function clearSessionCache(): void
    {
        $cacheKeys = [
            "live_session_{$this->liveSessionId}_top_products",
            "live_session_{$this->liveSessionId}_potential_customers",
            "live_session_{$this->liveSessionId}_top_questions",
            "live_session_{$this->liveSessionId}_stats_history",
            "live_session_{$this->liveSessionId}_potential_customers_count",
            "live_session_{$this->liveSessionId}_top_keywords",
        ];
        foreach ($cacheKeys as $key) {
            Cache::forget($key);
        }
    }
}

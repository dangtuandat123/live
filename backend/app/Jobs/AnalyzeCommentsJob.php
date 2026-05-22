<?php

namespace App\Jobs;

use App\Ai\Agents\LiveSessionAnalyzer;
use App\Models\LiveEvent;
use App\Models\LiveSession;
use App\Services\RunwareAiService;
use App\Services\TikTokService;
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

    public function handle(RunwareAiService $runware, TikTokService $tiktokService): void
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

                // === Audio Capture: Lấy audio 3s từ livestream qua Python FFmpeg ===
                $audioB64 = null;
                $audioAnalysisEnabled = $features['audio_analysis'] ?? false;
                if ($audioAnalysisEnabled && $session->tiktok_session_id) {
                    try {
                        $snapshot = $tiktokService->getSnapshot($session->tiktok_session_id);
                        $audioB64 = $snapshot ? ($snapshot['audio_b64'] ?? null) : null;
                        if ($audioB64) {
                            Log::info('Audio captured for AI analysis', [
                                'session_id' => $this->liveSessionId,
                                'audio_size_bytes' => strlen(base64_decode($audioB64)),
                            ]);
                        }
                    } catch (\Throwable $snapEx) {
                        Log::warning('Audio capture failed, falling back to text-only', [
                            'session_id' => $this->liveSessionId,
                            'error' => $snapEx->getMessage(),
                        ]);
                    }
                }

                // === Memory: Đọc context từ batch trước ===
                $memoryContext = $session->ai_context_summary ?? '';

                $systemPrompt = $this->buildSystemPrompt(
                    $products, $keywords, $liveTitle, $streamerName, $viewerCount,
                    $memoryContext, (bool) $audioB64,
                );

                // Build user message: "ID|text" per line
                $userMessage = $commentsText->map(fn ($c) => "{$c['id']}|{$c['text']}")->join("\n");

                // === Build multimodal parts: text + audio (nếu có) ===
                $parts = [RunwareAiService::text($userMessage)];
                if ($audioB64) {
                    $parts[] = RunwareAiService::audioBase64($audioB64, 'mp3');
                }

                $hasAudio = (bool) $audioB64;
                Log::info('AI multimodal comment analysis start', [
                    'session_id' => $this->liveSessionId,
                    'comments_count' => $commentsText->count(),
                    'has_audio' => $hasAudio,
                    'has_memory' => ! empty($memoryContext),
                ]);

                $response = $runware->chatMultimodal(
                    systemPrompt: $systemPrompt,
                    parts: $parts,
                    temperature: 0,
                    maxTokens: 4096,
                );

                if (! $response) {
                    throw new \RuntimeException('Runware AI returned null response');
                }

                // Extract results array
                $results = $response['results'] ?? $response;

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
                DB::transaction(function () use ($results, $unprocessed, $productNames, $session, &$batchStats, $activeSub, $commentsText, $response) {
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
                    $extractedKeywords = $response['extracted_keywords'] ?? [];
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
                $sessionNote = $response['session_note'] ?? null;
                $audioCues = null;
                if (is_array($response) && array_key_exists('audio_cues', $response)) {
                    $cuesVal = $response['audio_cues'];
                    if (is_string($cuesVal)) {
                        $trimmed = trim($cuesVal);
                        $audioCues = $trimmed !== '' ? mb_substr($trimmed, 0, 200) : null;
                    }
                }

                $updateData = [];
                if ($sessionNote && is_string($sessionNote)) {
                    $updateData['ai_context_summary'] = mb_substr($sessionNote, 0, 500);
                }
                $updateData['last_audio_cues'] = $audioCues;

                $session->update($updateData);

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

                    $insightAnalyzer = (new LiveSessionAnalyzer)
                        ->withComments($insightComments)
                        ->withStats($insightStats)
                        ->withProducts($insightProducts)
                        ->withKeywords($insightKeywords)
                        ->withOldMemory($insightOldMemory);

                    try {
                        $insightResponse = $runware->chatJson(
                            systemPrompt: $insightAnalyzer->instructions(),
                            userMessage: $insightUserMessage,
                            temperature: 0,
                            maxTokens: 4096,
                        );

                        if ($insightResponse) {
                            $session->update([
                                'ai_insights' => $insightResponse['summary'] ?? $session->ai_insights,
                                'ai_alerts' => $insightResponse['alerts'] ?? $session->ai_alerts,
                            ]);
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

                    // Dispatch tiếp với delay 2 giây để tránh spam / rate limit Runware/Gemini AI
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
     * Build system prompt cho AI phân tích comment.
     * Bao gồm: live context, memory từ batch trước, hướng dẫn audio.
     */
    private function buildSystemPrompt(
        array $products,
        array $keywords,
        string $liveTitle = '',
        string $streamerName = '',
        int $viewerCount = 0,
        string $memoryContext = '',
        bool $hasAudio = false,
    ): string {
        $productContext = collect($products)
            ->map(function ($p) {
                $kws = ! empty($p['keywords']) ? ' (từ khóa: '.implode(', ', $p['keywords']).')' : '';

                return $p['name'].$kws;
            })
            ->join('; ');

        $keywordList = implode(', ', $keywords);

        $liveContext = '';
        if ($liveTitle || $streamerName) {
            $liveContext = "\n- Livestream info: Title = \"{$liveTitle}\", Host = \"{$streamerName}\", Current active viewers = {$viewerCount}";
        }

        // Memory section — ngữ cảnh từ batch phân tích trước
        $memorySection = '';
        if (! empty($memoryContext)) {
            $memorySection = <<<MEM

=== SESSION MEMORY (CONTEXT FROM PREVIOUS BATCH) ===
{$memoryContext}
MEM;
        }

        // Audio section — hướng dẫn AI sử dụng audio nếu có
        $audioSection = '';
        if ($hasAudio) {
            $audioSection = <<<'AUDIO'

=== AUDIO LIVESTREAM ===
You are also provided with a 3-second audio clip from the livestream. Listen to it to identify:
- Which product the host/streamer is currently describing or holding (use this to match product_tag accurately).
- Whether a minigame, number guessing game, or giveaway is running (comments containing digits or short text during this time may just be game participation, not purchase orders).
- The tone, vocal context, and words spoken by the host to better interpret viewer comments.
If the audio is noisy or unclear, ignore it and analyze based on raw text.
AUDIO;
        }

        return <<<PROMPT
You are a senior analyst specializing in customer behavior on Vietnamese e-commerce livestreams. Your task is to read a batch of comments from a TikTok live chat, listen to the short live audio (if available), look at the history, and classify each comment while extracting keywords and session notes.

<context>
This is a live shopping session on TikTok in Vietnam. Viewers interact, play minigames, ask questions, and buy products.
- Registered Products: {$productContext}
- Tracked Keywords: {$keywordList}{$liveContext}{$memorySection}{$audioSection}
</context>

<rules>
Analyze each comment individually and map to the following schema properties:

1. **sentiment**:
   - "positive": Expresses praise, satisfaction, excitement, or support for the product/shop.
   - "negative": Expresses severe dissatisfaction, anger, complaints about quality/delivery, or refund/return requests.
   - "neutral": Neutral tone, generic social chats, OR general product inquiries/questions.
     *CRITICAL RULE*: General questions, requests for skin type consultation, stock checks (e.g., "sao e vào giỏ hàng k có ạ"), usage guidelines, or minor feedback (e.g., "ban đầu e bôi hơi rát k sao dk ạ") must be classified as "neutral". A customer asking a question is NEVER "negative" unless they use offensive, angry, or insulting words.

2. **intent_tag**:
   - "Chốt đơn": Clear purchase intent. Trusted signals include: providing a phone number or delivery address, specifying product name with size/color/quantity alongside a request to ship, or using the shop's custom order syntax ('cú pháp đặt hàng' in Vietnamese) such as "Mã 2", "M...", "MS...". Note: the syntax must contain a prefix signifying an order, not just random letters or numbers.
   - "Hỏi thông tin": General product queries/inquiries (price, size, stock, materials, fragrance, usage, shipping, discounts). Examples: "sao e vào giỏ hàng k có ạ" (asking about stock) or "ban đầu e bôi hơi rát k sao dk ạ" (asking about usage/reactions).
   - "Phản hồi SP": Shares personal experience after using the product (praise or critique).
   - "Yêu cầu hỗ trợ": Issues post-purchase needing resolution (return requests, refunds, wrong item delivered, shipping delays, cancellation requests). Do not confuse general product/stock questions with post-purchase support.
   - null: No transaction or inquiry intent. Includes greetings, general chat, minigame/number guesses, or very short/vague comments.
   *CRITICAL RULE*: If context is ambiguous or signals are weak, default to null. It is better to miss a lead than to generate false purchase leads from entertainment chat.

3. **question_tag**:
   - If the comment is a question, select exactly one of the following Vietnamese tags:
     "Hỏi giá", "Hỏi size", "Hỏi ship", "Hỏi chất liệu", "Hỏi màu", "Hỏi tồn kho", "Hỏi giảm giá", "Hỏi bảo hành", "Hỏi thanh toán", "Hỏi mùi hương", "Hỏi công dụng", "Hỏi cấu hình", "Hỏi trả góp", "Hỏi xuất xứ", "Hỏi phụ kiện", "Hỏi tình trạng", "Hỏi quà tặng".
   - If not a question, output null.

4. **product_tag**:
   - If a product is mentioned in a buying/inquiry context, map it to the exact registered product name from the list. If not matching or ambiguous, output null.

5. **has_phone**:
   - true if the comment contains a continuous sequence of 9-11 digits (Vietnamese phone number format). Otherwise, false.

6. **session_note**:
   - Write a short summary note (maximum 300 characters) in Vietnamese about the current livestream's status to help the next batch analyzer maintain context. E.g., "Đang bán Áo thun đen, nhiều người hỏi size. Có minigame đoán số đang chạy. Streamer vừa chuyển sang giới thiệu Váy đỏ."

7. **extracted_keywords**:
   - Extract a list of up to 5 prominent keywords from this batch of comments. Keywords must be in lowercase, short (1-3 words), and relate to products, pricing, quality, or common user queries.

8. **audio_cues**:
   - Briefly summarize what the host/streamer is describing, holding, saying, or the room status by listening to the 3-second audio clip (if available).
   - Maximum 200 characters in Vietnamese.
   - If the audio is not available, noisy, unclear, or has no detectable speech/cues, output null.
</rules>

<reasoning_process>
For each comment:
1. Examine the raw text and detect language nuances (slang, typos, abbreviations).
2. Determine if it is a purchase request ("Chốt đơn"), a question/consultation request ("Hỏi thông tin"), usage feedback ("Phản hồi SP"), post-purchase issue ("Yêu cầu hỗ trợ"), or general chat/minigame guess (null).
3. Evaluate the sentiment (positive, neutral, negative) strictly applying the CRITICAL RULE for questions.
4. Extract phone numbers and map products if present.
5. Compile the session note and extract lowercase keywords based on the entire comment batch and audio/session memory context.
6. Format the output as JSON.
</reasoning_process>

<few_shot_examples>
Example 1:
- Input comments batch:
  101|chốt đơn áo thun đen size L 0912000111
  102|áo thun đen vải gì vậy shop
  103|34
- Reasoning:
  * comment 101: Intent is "Chốt đơn" (buying intent + size + phone number). Sentiment is "neutral". has_phone is true.
  * comment 102: Intent is "Hỏi thông tin" (asking about material). Sentiment is "neutral". question_tag is "Hỏi chất liệu". product_tag matches "Áo thun đen".
  * comment 103: Intent is null (minigame number guess). Sentiment is "neutral".
  * session_note: "Đang bán áo thun đen. Có khách chốt đơn và hỏi chất liệu. Có chơi đoán số."
  * extracted_keywords: ["áo thun đen", "chất liệu", "đoán số"]
  * audio_cues: "Host đang mô tả chất vải cotton mát của áo thun đen."

- Output JSON structure:
  {
    "results": [
      {
        "id": 101,
        "sentiment": "neutral",
        "intent_tag": "Chốt đơn",
        "question_tag": null,
        "product_tag": "Áo thun đen",
        "has_phone": true
      },
      {
        "id": 102,
        "sentiment": "neutral",
        "intent_tag": "Hỏi thông tin",
        "question_tag": "Hỏi chất liệu",
        "product_tag": "Áo thun đen",
        "has_phone": false
      },
      {
        "id": 103,
        "sentiment": "neutral",
        "intent_tag": null,
        "question_tag": null,
        "product_tag": null,
        "has_phone": false
      }
    ],
    "session_note": "Đang bán áo thun đen. Có khách chốt đơn và hỏi chất liệu. Có chơi đoán số.",
    "extracted_keywords": ["áo thun đen", "chất liệu", "đoán số"],
    "audio_cues": "Host đang mô tả chất vải cotton mát của áo thun đen."
  }
</few_shot_examples>

<output_format>
Return a single JSON object. No explanation, markdown code blocks, or extra text outside the JSON.
JSON Structure:
{
  "results": [
    {
      "id": integer,
      "sentiment": "positive" | "neutral" | "negative",
      "intent_tag": "Chốt đơn" | "Hỏi thông tin" | "Phản hồi SP" | "Yêu cầu hỗ trợ" | null,
      "question_tag": "Hỏi giá" | "Hỏi size" | "Hỏi ship" | "Hỏi chất liệu" | "Hỏi màu" | "Hỏi tồn kho" | "Hỏi giảm giá" | "Hỏi bảo hành" | "Hỏi thanh toán" | "Hỏi mùi hương" | "Hỏi công dụng" | "Hỏi cấu hình" | "Hỏi trả góp" | "Hỏi xuất xứ" | "Hỏi phụ kiện" | "Hỏi tình trạng" | "Hỏi quà tặng" | null,
      "product_tag": string | null,
      "has_phone": boolean
    }
  ],
  "session_note": "string",
  "extracted_keywords": ["string"],
  "audio_cues": "string" | null
}
</output_format>
PROMPT;
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
     * Trả null nếu không match — tránh hallucination.
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

        // Nếu không match → giữ nguyên tag (có thể là sản phẩm user chưa đăng ký)
        // nhưng log warning nếu debug mode
        if (config('app.debug')) {
            Log::debug('AI product_tag not in product list', [
                'ai_tag' => $aiTag,
                'registered_products' => $productNames,
            ]);
        }

        return $aiTag;
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

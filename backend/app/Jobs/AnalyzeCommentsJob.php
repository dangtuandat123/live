<?php

namespace App\Jobs;

use App\Ai\Agents\CommentAnalyzer;
use App\Models\LiveEvent;
use App\Models\LiveSession;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AnalyzeCommentsJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 60;
    public array $backoff = [10, 30];

    /**
     * Mỗi session chỉ có tối đa 1 job trong queue (ShouldBeUnique).
     * Tránh tràn queue khi polling dispatch nhiều lần.
     */
    public int $uniqueFor = 30; // 30s lock

    /**
     * Giá trị hợp lệ cho các AI fields — validation trước khi lưu DB.
     */
    private const VALID_SENTIMENTS = ['positive', 'neutral', 'negative'];
    private const VALID_INTENTS = ['Chốt đơn', 'Hỏi thông tin', 'Phản hồi SP', 'Yêu cầu hỗ trợ'];
    private const VALID_QUESTIONS = [
        'Hỏi giá', 'Hỏi size', 'Hỏi ship', 'Hỏi chất liệu',
        'Hỏi màu', 'Hỏi tồn kho', 'Hỏi giảm giá', 'Hỏi bảo hành',
        'Hỏi thanh toán', 'Hỏi mùi hương', 'Hỏi công dụng',
    ];

    public function __construct(
        private int $liveSessionId,
    ) {}

    public function uniqueId(): string
    {
        return 'analyze-comments-' . $this->liveSessionId;
    }

    public function handle(): void
    {
        $session = LiveSession::with(['products', 'keywords'])->find($this->liveSessionId);
        if (!$session) {
            return;
        }

        // Chỉ xử lý AI cho phiên live đang hoạt động
        if (!in_array($session->status, ['live', 'connecting'])) {
            return;
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
            ->filter(fn ($c) => !empty($c['text']));

        if ($commentsText->isEmpty()) {
            LiveEvent::whereIn('id', $unprocessed->pluck('id'))
                ->update(['ai_processed' => true, 'sentiment' => 'neutral']);
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
            // Compact prompt: "ID|text" per line
            $promptText = $commentsText->map(fn ($c) => "{$c['id']}|{$c['text']}")->join("\n");

            $agent = (new CommentAnalyzer())
                ->withProducts($products)
                ->withKeywords($keywords);

            $response = $agent->prompt($promptText);

            // Structured output response
            $results = $response['results'] ?? [];

            // Debug log
            if (!empty($results) && config('app.debug')) {
                Log::info('AI analysis batch', [
                    'session_id' => $this->liveSessionId,
                    'input_count' => $commentsText->count(),
                    'output_count' => count($results),
                    'sample' => array_slice($results, 0, 3),
                ]);
            }

            // Validate + save trong transaction
            DB::transaction(function () use ($results, $unprocessed, $productNames) {
                $processedIds = [];
                foreach ($results as $result) {
                    $eventId = $result['id'] ?? null;
                    if (!$eventId) {
                        continue;
                    }

                    $processedIds[] = $eventId;

                    // Validate AI output trước khi save
                    $validated = $this->validateResult($result, $productNames);

                    LiveEvent::where('id', $eventId)
                        ->where('live_session_id', $this->liveSessionId)
                        ->update([
                            'sentiment' => $validated['sentiment'],
                            'intent_tag' => $validated['intent_tag'],
                            'question_tag' => $validated['question_tag'],
                            'product_tag' => $validated['product_tag'],
                            'has_phone' => $validated['has_phone'],
                            'ai_processed' => true,
                        ]);
                }

                // Đánh dấu comments không có trong results (AI bỏ sót)
                $missingIds = $unprocessed->pluck('id')->diff($processedIds)->toArray();
                if (!empty($missingIds)) {
                    LiveEvent::whereIn('id', $missingIds)
                        ->update(['ai_processed' => true, 'sentiment' => 'neutral']);
                }
            });

            // Cập nhật aggregate stats
            $this->updateAggregateStats($session);

        } catch (\Throwable $e) {
            Log::error('AI comment analysis failed', [
                'session_id' => $this->liveSessionId,
                'error' => $e->getMessage(),
                'comments_count' => $commentsText->count(),
            ]);

            // Không retry nếu lỗi auth
            if (str_contains($e->getMessage(), 'API key') || str_contains($e->getMessage(), '401')) {
                $this->fail($e);
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
        if (!in_array($sentiment, self::VALID_SENTIMENTS)) {
            $sentiment = 'neutral';
        }

        $intentTag = $result['intent_tag'] ?? null;
        if ($intentTag !== null && !in_array($intentTag, self::VALID_INTENTS)) {
            $intentTag = null;
        }

        $questionTag = $result['question_tag'] ?? null;
        if ($questionTag !== null && !in_array($questionTag, self::VALID_QUESTIONS)) {
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
    private function updateAggregateStats(LiveSession $session): void
    {
        $stats = LiveEvent::where('live_session_id', $session->id)
            ->where('event_type', 'comment')
            ->where('ai_processed', true)
            ->selectRaw("
                SUM(CASE WHEN sentiment = 'positive' THEN 1 ELSE 0 END) as positive,
                SUM(CASE WHEN sentiment = 'neutral' THEN 1 ELSE 0 END) as neutral,
                SUM(CASE WHEN sentiment = 'negative' THEN 1 ELSE 0 END) as negative,
                COUNT(DISTINCT CASE WHEN intent_tag = 'Chốt đơn' THEN tiktok_user_id END) as leads
            ")
            ->first();

        $session->stats()->updateOrCreate(
            ['live_session_id' => $session->id],
            [
                'sentiment_positive' => $stats->positive ?? 0,
                'sentiment_neutral' => $stats->neutral ?? 0,
                'sentiment_negative' => $stats->negative ?? 0,
                'leads_count' => $stats->leads ?? 0,
            ]
        );
    }
}

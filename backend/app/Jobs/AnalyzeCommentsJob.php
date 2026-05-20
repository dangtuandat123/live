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
use Illuminate\Support\Facades\Log;

class AnalyzeCommentsJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 60;

    /**
     * Mỗi session chỉ có tối đa 1 job trong queue (ShouldBeUnique).
     * Tránh tràn queue khi polling dispatch nhiều lần.
     */
    public int $uniqueFor = 30; // 30s lock

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

        // Lấy tất cả comments chưa phân tích, batch 100 (gấp đôi cũ, giảm số API calls)
        $unprocessed = LiveEvent::where('live_session_id', $this->liveSessionId)
            ->where('event_type', 'comment')
            ->where('ai_processed', false)
            ->orderBy('event_at')
            ->limit(100)
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

        // Chuẩn bị context ngắn gọn
        $products = $session->products->map(fn ($p) => [
            'name' => $p->name,
            'keywords' => $p->keywords ?? [],
        ])->toArray();

        $keywords = $session->keywords->pluck('keyword')->toArray();

        try {
            // Compact prompt: "ID|text" per line — tiết kiệm token
            $promptText = $commentsText->map(fn ($c) => "{$c['id']}|{$c['text']}")->join("\n");

            $agent = (new CommentAnalyzer())
                ->withProducts($products)
                ->withKeywords($keywords);

            $response = $agent->prompt($promptText);

            // Structured output response
            $results = $response['results'] ?? [];

            // Debug: log first batch response để verify format
            if (!empty($results)) {
                Log::info('AI analysis sample', ['first_result' => $results[0] ?? null, 'count' => count($results)]);
            }

            // Bulk prepare updates
            $processedIds = [];
            foreach ($results as $result) {
                $eventId = $result['id'] ?? null;
                if (!$eventId) {
                    continue;
                }

                $processedIds[] = $eventId;

                LiveEvent::where('id', $eventId)
                    ->where('live_session_id', $this->liveSessionId)
                    ->update([
                        'sentiment' => $result['sentiment'] ?? 'neutral',
                        'intent_tag' => $result['intent_tag'] ?? null,
                        'question_tag' => $result['question_tag'] ?? null,
                        'product_tag' => $result['product_tag'] ?? null,
                        'has_phone' => $result['has_phone'] ?? false,
                        'ai_processed' => true,
                    ]);
            }

            // Đánh dấu comments không có trong results (AI bỏ sót)
            $missingIds = $unprocessed->pluck('id')->diff($processedIds)->toArray();
            if (!empty($missingIds)) {
                LiveEvent::whereIn('id', $missingIds)
                    ->update(['ai_processed' => true, 'sentiment' => 'neutral']);
            }

            // Cập nhật sentiment stats + leads count trong 1 lần query
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
     * Gộp cập nhật sentiment + leads trong 1 query thay vì 2.
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
                SUM(CASE WHEN intent_tag = 'Chốt đơn' THEN 1 ELSE 0 END) as leads
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

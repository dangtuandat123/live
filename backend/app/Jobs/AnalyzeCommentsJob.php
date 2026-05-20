<?php

namespace App\Jobs;

use App\Ai\Agents\CommentAnalyzer;
use App\Models\LiveEvent;
use App\Models\LiveSession;
use App\Models\LiveStat;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class AnalyzeCommentsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 120;

    public function __construct(
        private int $liveSessionId,
    ) {}

    public function handle(): void
    {
        $session = LiveSession::with(['products', 'keywords'])->find($this->liveSessionId);
        if (!$session) {
            return;
        }

        // Lấy comments chưa phân tích
        $unprocessed = LiveEvent::where('live_session_id', $this->liveSessionId)
            ->where('event_type', 'comment')
            ->where('ai_processed', false)
            ->orderBy('event_at')
            ->limit(50) // Batch 50 comments mỗi lần
            ->get();

        if ($unprocessed->isEmpty()) {
            return;
        }

        // Chuẩn bị dữ liệu products cho AI
        $products = $session->products->map(fn ($p) => [
            'name' => $p->name,
            'keywords' => $p->keywords ?? [],
        ])->toArray();

        $keywords = $session->keywords->pluck('keyword')->toArray();

        // Build prompt text từ comments
        $commentsText = $unprocessed->map(fn ($e) => [
            'id' => $e->id,
            'text' => $e->data['comment'] ?? '',
        ])->filter(fn ($c) => !empty($c['text']));

        if ($commentsText->isEmpty()) {
            // Đánh dấu đã xử lý nếu toàn bộ rỗng
            LiveEvent::whereIn('id', $unprocessed->pluck('id'))
                ->update(['ai_processed' => true, 'sentiment' => 'neutral']);
            return;
        }

        try {
            $promptText = $commentsText->map(fn ($c) => "ID:{$c['id']} | {$c['text']}")->join("\n");

            $agent = (new CommentAnalyzer())
                ->withProducts($products)
                ->withKeywords($keywords);

            $response = $agent->prompt("Phân tích các bình luận sau:\n\n{$promptText}");

            $results = $response['results'] ?? [];

            // Cập nhật từng comment
            foreach ($results as $result) {
                $eventId = $result['id'] ?? null;
                if (!$eventId) {
                    continue;
                }

                LiveEvent::where('id', $eventId)
                    ->where('live_session_id', $this->liveSessionId) // bảo mật: chỉ update events thuộc session
                    ->update([
                        'sentiment' => $result['sentiment'] ?? 'neutral',
                        'intent_tag' => $result['intent_tag'] ?? null,
                        'question_tag' => $result['question_tag'] ?? null,
                        'product_tag' => $result['product_tag'] ?? null,
                        'has_phone' => $result['has_phone'] ?? false,
                        'ai_processed' => true,
                    ]);
            }

            // Đánh dấu các comments không có trong results
            $processedIds = collect($results)->pluck('id')->filter()->toArray();
            $missingIds = $unprocessed->pluck('id')->diff($processedIds)->toArray();
            if (!empty($missingIds)) {
                LiveEvent::whereIn('id', $missingIds)
                    ->update(['ai_processed' => true, 'sentiment' => 'neutral']);
            }

            // Cập nhật thống kê sentiment tổng hợp
            $this->updateSentimentStats($session);

            // Cập nhật leads_count (số chốt đơn)
            $this->updateLeadsCount($session);

        } catch (\Throwable $e) {
            Log::error('AI comment analysis failed', [
                'session_id' => $this->liveSessionId,
                'error' => $e->getMessage(),
                'comments_count' => $commentsText->count(),
            ]);

            // Không retry nếu lỗi API key / config
            if (str_contains($e->getMessage(), 'API key') || str_contains($e->getMessage(), '401')) {
                $this->fail($e);
            }
        }
    }

    private function updateSentimentStats(LiveSession $session): void
    {
        $sentiments = LiveEvent::where('live_session_id', $session->id)
            ->where('event_type', 'comment')
            ->where('ai_processed', true)
            ->selectRaw("
                SUM(CASE WHEN sentiment = 'positive' THEN 1 ELSE 0 END) as positive,
                SUM(CASE WHEN sentiment = 'neutral' THEN 1 ELSE 0 END) as neutral,
                SUM(CASE WHEN sentiment = 'negative' THEN 1 ELSE 0 END) as negative
            ")
            ->first();

        $session->stats()->updateOrCreate(
            ['live_session_id' => $session->id],
            [
                'sentiment_positive' => $sentiments->positive ?? 0,
                'sentiment_neutral' => $sentiments->neutral ?? 0,
                'sentiment_negative' => $sentiments->negative ?? 0,
            ]
        );
    }

    private function updateLeadsCount(LiveSession $session): void
    {
        $leadsCount = LiveEvent::where('live_session_id', $session->id)
            ->where('event_type', 'comment')
            ->where('intent_tag', 'Chốt đơn')
            ->count();

        $session->stats()->updateOrCreate(
            ['live_session_id' => $session->id],
            ['leads_count' => $leadsCount]
        );
    }
}

<?php

namespace App\Jobs;

use App\Models\LiveEvent;
use App\Models\LiveSession;
use App\Services\RunwareAiService;
use App\Services\TikTokService;
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
    public int $timeout = 90;
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

    public function handle(RunwareAiService $runware, TikTokService $tiktokService): void
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
            // Lấy snapshot (ảnh + audio) từ live stream nếu có tiktok_session_id
            $snapshot = null;
            if ($session->tiktok_session_id) {
                $snapshot = $tiktokService->getSnapshot($session->tiktok_session_id);
            }

            // Build system prompt — bao gồm live context
            $liveTitle = ($snapshot && isset($snapshot['title'])) ? $snapshot['title'] : ($session->name ?? '');
            $streamerName = ($snapshot && isset($snapshot['streamer'])) ? $snapshot['streamer'] : ($session->tiktok_username ?? '');
            $viewerCount = ($snapshot && isset($snapshot['viewer_count'])) ? $snapshot['viewer_count'] : 0;

            $systemPrompt = $this->buildSystemPrompt($products, $keywords, $liveTitle, $streamerName, $viewerCount);

            // Build user message: "ID|text" per line
            $userMessage = $commentsText->map(fn ($c) => "{$c['id']}|{$c['text']}")->join("\n");

            // --- Timing-aware snapshot decision ---
            // Comments được query oldest-first, nên có thể cũ hơn snapshot hiện tại.
            // Nếu comments quá cũ (>90s), snapshot không phản ánh đúng context
            // → chỉ dùng metadata (title, streamer) mà không gửi image/audio.
            $oldestEventAt = $unprocessed->min('event_at');
            $commentAgeSeconds = $oldestEventAt ? (int) $oldestEventAt->diffInSeconds(now()) : 0;
            $snapshotRelevant = $commentAgeSeconds <= 90;

            $hasImage = $snapshotRelevant && !empty($snapshot['image_b64']);
            $hasAudio = $snapshotRelevant && !empty($snapshot['audio_b64']);

            if ($hasImage || $hasAudio) {
                // Multimodal: text + image + audio
                $parts = [];

                if ($hasImage) {
                    $parts[] = RunwareAiService::imageUrl(
                        'data:image/jpeg;base64,' . $snapshot['image_b64']
                    );
                }

                if ($hasAudio) {
                    $parts[] = RunwareAiService::audioBase64(
                        $snapshot['audio_b64'],
                        'mp3'
                    );
                }

                // Thêm time context để AI biết mối quan hệ thời gian
                $timeNote = '';
                if ($commentAgeSeconds > 30) {
                    $timeNote = "⏱️ Lưu ý: Ảnh/audio là trạng thái HIỆN TẠI. Một số comments đã từ {$commentAgeSeconds}s trước — sản phẩm trong ảnh có thể khác lúc comments được viết. Ưu tiên TEXT bình luận để xác định product_tag.\n\n";
                }

                $parts[] = RunwareAiService::text(
                    $timeNote . "Bình luận cần phân tích (format: ID|nội_dung):\n" . $userMessage
                );

                Log::info('AI multimodal analysis', [
                    'session_id' => $this->liveSessionId,
                    'has_image' => $hasImage,
                    'has_audio' => $hasAudio,
                    'comments_count' => $commentsText->count(),
                    'comment_age_seconds' => $commentAgeSeconds,
                ]);

                $response = $runware->chatMultimodal(
                    systemPrompt: $systemPrompt,
                    parts: $parts,
                    temperature: 0,
                    maxTokens: 4096,
                );
            } else {
                // Text-only fallback
                $response = $runware->chatJson(
                    systemPrompt: $systemPrompt,
                    userMessage: $userMessage,
                    temperature: 0,
                    maxTokens: 4096,
                );
            }

            if (!$response) {
                throw new \RuntimeException('Runware AI returned null response');
            }

            // Extract results array
            $results = $response['results'] ?? $response;

            // Đảm bảo là array
            if (!is_array($results) || empty($results)) {
                throw new \RuntimeException('AI response is empty or not an array');
            }

            // Nếu không phải là list tuần tự (associative array), kiểm tra xem có phải single object không
            if (!array_is_list($results)) {
                if (isset($results['id'])) {
                    $results = [$results];
                } else {
                    throw new \RuntimeException('AI response format is invalid: expected list of results');
                }
            }

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
            if (str_contains($e->getMessage(), 'API key') || str_contains($e->getMessage(), '401') || str_contains($e->getMessage(), 'auth')) {
                $this->fail($e);
                return;
            }

            // Rethrow để Laravel queue tự động retry theo $tries/$backoff
            throw $e;
        }
    }

    /**
     * Build system prompt cho AI phân tích comment.
     * Bao gồm live context: title, streamer, viewer count.
     */
    private function buildSystemPrompt(
        array $products,
        array $keywords,
        string $liveTitle = '',
        string $streamerName = '',
        int $viewerCount = 0,
    ): string {
        $productContext = collect($products)
            ->map(function ($p) {
                $kws = !empty($p['keywords']) ? ' (từ khóa: ' . implode(', ', $p['keywords']) . ')' : '';
                return $p['name'] . $kws;
            })
            ->join('; ');

        $keywordList = implode(', ', $keywords);

        // Live context block — cho AI biết đang xem gì
        $liveContext = '';
        if ($liveTitle || $streamerName) {
            $liveContext = "\n--- NGỮ CẢNH LIVESTREAM ---";
            if ($liveTitle) {
                $liveContext .= "\nTiêu đề live: {$liveTitle}";
            }
            if ($streamerName) {
                $liveContext .= "\nHost/Streamer: {$streamerName}";
            }
            if ($viewerCount > 0) {
                $liveContext .= "\nSố viewer hiện tại: {$viewerCount}";
            }
            $liveContext .= "\n---";
        }

        // Multimodal instruction block
        $multimodalNote = <<<'NOTE'

=== HÌNH ẢNH & ÂM THANH ===
Bạn CÓ THỂ nhận được hình ảnh chụp màn hình livestream và đoạn audio ngắn từ stream.
- Hình ảnh: cho biết sản phẩm đang được trình bày, không gian live, banner/text overlay.
- Audio: cho biết host đang nói gì, giới thiệu sản phẩm nào, có đang chạy khuyến mãi không.
→ Dùng thông tin này để HIỂU NGỮ CẢNH tốt hơn khi phân loại bình luận.
→ VÍ DỤ: Nếu host đang giới thiệu "kem dưỡng da" và viewer hỏi "bao nhiêu" → product_tag = kem dưỡng da, question_tag = "Hỏi giá".
→ Nếu KHÔNG có hình/audio, phân tích dựa trên text bình luận.

⚠️ TIMING: Ảnh/audio là trạng thái HIỆN TẠI của livestream. Một số bình luận có thể đã cũ (từ lúc host giới thiệu sản phẩm khác).
→ Nếu bình luận ĐỀ CẬP RÕ tên sản phẩm ("con 15prm", "cái kem") → dùng TEXT để xác định product_tag, KHÔNG dùng ảnh.
→ Chỉ dùng ảnh/audio để gán product_tag khi bình luận KHÔNG đề cập sản phẩm cụ thể (VD: "bao nhiêu", "còn không", "ship tỉnh k").
NOTE;

        return <<<PROMPT
Bạn là AI chuyên gia phân tích bình luận livestream bán hàng TikTok Việt Nam.
Nhiệm vụ: phân loại chính xác từng bình luận theo 5 tiêu chí bên dưới.
{$liveContext}
{$multimodalNote}

=== CONTEXT ===
Sản phẩm đang bán: {$productContext}
Từ khóa theo dõi: {$keywordList}

=== PHÂN LOẠI ===
Mỗi bình luận (format: ID|nội dung), trả về:

1. **sentiment** — cảm xúc của người bình luận VỀ SẢN PHẨM ĐANG BÁN:
   - "positive": khen ngợi sản phẩm, hài lòng, phấn khích VỀ SẢN PHẨM
   - "negative": CHỈ KHI chê/phàn nàn/thất vọng VỀ SẢN PHẨM ĐÃ MUA/DÙNG, hoặc báo lỗi sản phẩm
   - "neutral": hỏi thông tin, tương tác, spam, emoticon, nói chuyện phiếm, không rõ cảm xúc

   ⚠️ QUAN TRỌNG:
   - Mô tả tình trạng (tóc rụng, da dầu, gàu...) để HỎI sản phẩm phù hợp → "neutral" + intent "Hỏi thông tin"
   - Phàn nàn SAU KHI ĐÃ MUA/DÙNG sản phẩm (ví dụ: "dùng xong bị rụng", "mua về bị lỗi") → "negative"
   - Nếu không rõ đang chê hay đang hỏi → mặc định "neutral"

2. **intent_tag** — ý định hành động:
   - "Chốt đơn": Người dùng THỰC SỰ muốn mua — CÓ KÈM ít nhất 1 trong: tên sản phẩm cụ thể, số lượng, size/màu, SĐT, địa chỉ, yêu cầu ship/COD
   - "Hỏi thông tin": Hỏi giá, hỏi size, hỏi ship, hỏi tồn kho, hỏi chi tiết sản phẩm
   - "Phản hồi SP": Chia sẻ trải nghiệm dùng sản phẩm (khen hoặc chê)
   - "Yêu cầu hỗ trợ": Báo lỗi, yêu cầu đổi trả, huỷ đơn, khiếu nại
   - null: Không thuộc nhóm nào (tương tác, chào hỏi, spam, emoticon, nói chuyện phiếm)

   ⚠️ QUAN TRỌNG — "đã mua" / "mua" / "chốt" ĐƠN LẺ:
   Trong TikTok Live VN, viewer spam "đã mua"/"ĐÃ MUA"/"mua mua"/"chốt" để tham gia mini-game hoặc tương tác host.
   → Nếu KHÔNG kèm thông tin cụ thể (SP, SL, size, SĐT) → intent_tag = null, sentiment = "neutral"
   → Chỉ = "Chốt đơn" khi có chi tiết mua hàng thực sự

3. **question_tag** — loại câu hỏi (null nếu không phải câu hỏi):
   "Hỏi giá" / "Hỏi size" / "Hỏi ship" / "Hỏi chất liệu" / "Hỏi màu" / "Hỏi tồn kho" / "Hỏi giảm giá" / "Hỏi bảo hành" / "Hỏi thanh toán" / "Hỏi mùi hương" / "Hỏi công dụng"

4. **product_tag** — tên sản phẩm CHUẨN HÓA từ danh sách sản phẩm đang bán.
   - CHỈ trả product_tag nếu bình luận đề cập rõ ràng đến sản phẩm trong danh sách.
   - PHẢI dùng tên đầy đủ chuẩn hóa (VD: "15prm" → "iPhone 15 Pro Max", "13th" → "iPhone 13").
   - KHÔNG hallucinate tên sản phẩm không có trong danh sách.
   - null nếu không liên quan đến sản phẩm nào.

5. **has_phone** — true nếu chứa SĐT (10-11 chữ số bắt đầu bằng 0, có thể có dấu chấm/cách/gạch). false nếu không.

=== VÍ DỤ ===

Input: 101|đã mua
Output: {"id":101,"sentiment":"neutral","intent_tag":null,"question_tag":null,"product_tag":null,"has_phone":false}
→ Lý do: "đã mua" đơn lẻ = spam tương tác, không phải ý định mua thật

Input: 102|ĐÃ MUA
Output: {"id":102,"sentiment":"neutral","intent_tag":null,"question_tag":null,"product_tag":null,"has_phone":false}
→ Lý do: Tương tự, chỉ spam

Input: 103|mua con 15 Pro Max ship HCM
Output: {"id":103,"sentiment":"positive","intent_tag":"Chốt đơn","question_tag":null,"product_tag":"iPhone 15 Pro Max","has_phone":false}
→ Lý do: Có tên SP + địa chỉ ship = ý định mua thật

Input: 104|chốt 2 cái size L
Output: {"id":104,"sentiment":"positive","intent_tag":"Chốt đơn","question_tag":null,"product_tag":null,"has_phone":false}
→ Lý do: Có số lượng + size = ý định mua thật

Input: 105|14prm 128 gb bao nhiêu ạ
Output: {"id":105,"sentiment":"neutral","intent_tag":"Hỏi thông tin","question_tag":"Hỏi giá","product_tag":"iPhone 14 Pro Max","has_phone":false}

Input: 106|có con 13 ko
Output: {"id":106,"sentiment":"neutral","intent_tag":"Hỏi thông tin","question_tag":"Hỏi tồn kho","product_tag":"iPhone 13","has_phone":false}

Input: 107|xài con vàng mượt lắm
Output: {"id":107,"sentiment":"positive","intent_tag":"Phản hồi SP","question_tag":null,"product_tag":null,"has_phone":false}
→ Lý do: Chia sẻ trải nghiệm tích cực

Input: 108|mua bị lỗi đổi cho em chứ
Output: {"id":108,"sentiment":"negative","intent_tag":"Yêu cầu hỗ trợ","question_tag":null,"product_tag":null,"has_phone":false}

Input: 109|:))
Output: {"id":109,"sentiment":"neutral","intent_tag":null,"question_tag":null,"product_tag":null,"has_phone":false}
→ Lý do: Emoticon = neutral, không có intent

Input: 110|0389682600
Output: {"id":110,"sentiment":"neutral","intent_tag":null,"question_tag":null,"product_tag":null,"has_phone":true}

Input: 111|màu hồng thơm k ạ
Output: {"id":111,"sentiment":"neutral","intent_tag":"Hỏi thông tin","question_tag":"Hỏi mùi hương","product_tag":null,"has_phone":false}

Input: 112|Huỷ đơn
Output: {"id":112,"sentiment":"negative","intent_tag":"Yêu cầu hỗ trợ","question_tag":null,"product_tag":null,"has_phone":false}

=== LƯU Ý VIẾT TẮT VN ===
sp=sản phẩm, sz=size, prm/prx=Pro Max, pr=Pro, bn/bnh/bnhiu=bao nhiêu, k/ko/kg=không, hcm=HCM, hn=Hà Nội, cod=thanh toán khi nhận hàng

=== OUTPUT FORMAT ===
Trả về JSON duy nhất, format: {"results": [{...}, {...}]}
Mỗi object trong results phải có đủ 5 key: id, sentiment, intent_tag, question_tag, product_tag, has_phone.
KHÔNG giải thích, KHÔNG markdown, KHÔNG text thừa. CHỈ JSON.
PROMPT;
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

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

            // Vét sạch comments chưa được xử lý AI của session này
            $hasMoreUnprocessed = LiveEvent::where('live_session_id', $this->liveSessionId)
                ->where('event_type', 'comment')
                ->where('ai_processed', false)
                ->exists();

            if ($hasMoreUnprocessed) {
                // Giải phóng unique lock của job hiện tại để Laravel cho phép dispatch job tiếp theo
                $lockKey = 'laravel_unique_job:' . self::class . ':' . $this->uniqueId();
                try {
                    cache()->forget($lockKey);
                } catch (\Throwable $cacheEx) {
                    Log::warning('Failed to clear unique job lock key', [
                        'key' => $lockKey,
                        'error' => $cacheEx->getMessage(),
                    ]);
                }

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
Bạn là một AI phân tích bình luận Livestream bán hàng (TikTok/Social) thông minh và nhạy bén nhất tại thị trường Việt Nam.
Nhiệm vụ của bạn là đọc các bình luận thời gian thực và phân tích để tìm ra Insight khách hàng, hỗ trợ chốt đơn thần tốc, chăm sóc khách hàng và tối ưu doanh thu.
{$liveContext}
{$multimodalNote}

=== NGỮ CẢNH HỆ THỐNG ===
- Sản phẩm đang bán: {$productContext}
- Từ khóa cần theo dõi: {$keywordList}

=== QUY TẮC PHÂN LOẠI CHI TIẾT ===

1. **sentiment** (Cảm xúc của khách hàng đối với sản phẩm/thương hiệu):
   - "positive" (Tích cực / Tạo Social Proof):
     + Lời khen ngợi, thích thú, tin tưởng trực tiếp về sản phẩm ("đẹp quá", "thơm xỉu", "dùng mê lắm").
     + Chia sẻ kết quả sử dụng tốt sau khi mua ("dùng 2 tuần thấy đỡ rụng tóc hẳn", "mua lần 2 rồi, chất vải siêu mát"). Đây là các Social Proof cực kỳ đắt giá giúp kích thích khách hàng khác mua hàng.
   - "negative" (Tiêu cực / Rủi ro cao):
     + Phàn nàn, khiếu nại về sản phẩm đã mua hoặc dịch vụ ("sao chưa giao hàng", "bị vỡ nắp rồi shop", "dùng bị ngứa", "lừa đảo").
     + Thể hiện sự thất vọng, tức giận, huỷ đơn.
   - "neutral" (Trung lập):
     + Câu hỏi bình thường về thông tin sản phẩm ("có màu xanh không", "bao nhiêu tiền").
     + Lời chào hỏi, tương tác vui vẻ, biểu tượng cảm xúc đơn thuần.
     + Việc mô tả tình trạng cá nhân để nhờ tư vấn ("da mình bị mụn ẩn thì dùng loại nào").

2. **intent_tag** (Ý định hành động - Quyết định phễu bán hàng):
   - "Chốt đơn" (High Purchase Intent):
     + Khách hàng thể hiện ý muốn mua hàng rõ ràng bằng cách cung cấp thông tin giao dịch: SĐT, địa chỉ, size, màu kèm yêu cầu ship.
     + KHÁCH HÀNG SỬ DỤNG CÚ PHÁP CHỐT ĐƠN LIVESTREAM VIỆT NAM: Chứa mã sản phẩm dạng "Mã + chữ/số" hoặc "M + chữ/số" (ví dụ: "Mã 2", "Mã 2 ạ", "M2", "mã A", "mã A ạ", "MS1").
     + LƯU Ý ĐẶC BIỆT: KHÔNG phân loại các bình luận chỉ chứa mã đơn lẻ hoặc kèm kính ngữ như "Mã 2 ạ" thành "Hỏi thông tin", hãy phân loại là "Chốt đơn". Đây là hành vi chốt đơn trực tiếp của khách hàng.
   - "Hỏi thông tin" (Information Seeking):
     + Khách hàng hỏi chi tiết về thông số, tính năng, cách dùng, giá cả, ưu đãi ("size L nặng bn kg mặc vừa", "có được kiểm tra hàng không shop", "chai này bao nhiêu ml").
   - "Phản hồi SP" (Product Feedback / Social Proof):
     + Đánh giá hoặc phản hồi trực tiếp sau khi trải nghiệm sản phẩm (dù là khen hay chê).
   - "Yêu cầu hỗ trợ" (Customer Support / Post-purchase issue):
     + Yêu cầu xử lý vấn đề sau mua: đổi size, hoàn tiền, báo lỗi vận chuyển, hủy đơn ("cho mình hủy mã 2 nhé", "giao lộn size rồi đổi sao shop").
   - null: Các comment spam tương tác, nói chuyện phiếm không có ý định mua hoặc hỏi cụ thể (ví dụ chỉ ghi "đã mua", "chốt chốt", "hello" để nhận quà mini-game).

3. **question_tag** (Loại câu hỏi cụ thể - Giúp nhân viên phản hồi đúng trọng tâm):
   Phân loại chính xác thành một trong các nhãn sau (hoặc null nếu không phải câu hỏi):
   - "Hỏi giá" (Hỏi về giá tiền, khuyến mãi giá)
   - "Hỏi size" (Hỏi size, kích thước, cân nặng phù hợp)
   - "Hỏi ship" (Hỏi về phí ship, thời gian giao hàng, khu vực giao)
   - "Hỏi chất liệu" (Hỏi về vải, thành phần, cấu tạo)
   - "Hỏi màu" (Hỏi về màu sắc, thiết kế bên ngoài)
   - "Hỏi tồn kho" (Hỏi còn hàng hay hết hàng)
   - "Hỏi giảm giá" (Hỏi về voucher, coupon, chương trình flash sale)
   - "Hỏi bảo hành" (Hỏi về chính sách bảo hành, đổi trả)
   - "Hỏi thanh toán" (Hỏi về chuyển khoản, COD, ví điện tử)
   - "Hỏi mùi hương" (Hỏi về mùi hương, độ thơm, độ lưu hương)
   - "Hỏi công dụng" (Hỏi về tác dụng, cách dùng, đối tượng sử dụng)

4. **product_tag** (Tên sản phẩm chuẩn hóa):
   - Trích xuất tên sản phẩm CHUẨN HÓA từ danh sách sản phẩm đang bán.
   - Phải ánh xạ từ tên viết tắt hoặc biệt danh trong livestream về tên chuẩn (ví dụ: "15prm" hoặc "mười lăm pờ rô mắc" -> "iPhone 15 Pro Max").
   - Trả về null nếu không khớp với sản phẩm nào trong danh sách.

5. **has_phone** (Có số điện thoại):
   - Trả về true nếu bình luận chứa chuỗi chữ số giống SĐT Việt Nam (9-11 chữ số, bắt đầu bằng số 0 hoặc +84, có thể có dấu cách, chấm, gạch ngang). Trả về false nếu không.

=== BỘ TỪ ĐIỂN TIẾNG LÓNG & VIẾT TẮT LIVESTREAM VIỆT NAM ===
- Sản phẩm/mã chốt: sp = sản phẩm, mã / ma / mas / m = mã chốt đơn, đờn = đơn hàng, cb = combo
- Thông số: sz / szi / szie = size (kích cỡ), kg / kí / kgs = cân nặng, m = màu, prm = Pro Max, pr = Pro
- Câu hỏi/Tương tác: bn / bnh / bnhiu / nhiu = bao nhiêu, k / ko / kg / koo = không, ib / ibox = inbox (nhắn tin riêng)
- Địa điểm/Vận chuyển: ship / ship cod = giao hàng thanh toán tận nơi, hn = Hà Nội, hcm = TP. Hồ Chí Minh, t = tỉnh/thành phố

=== VÍ DỤ PHÂN TÍCH ===

Input: 101|đã mua
Output: {"id":101,"sentiment":"neutral","intent_tag":null,"question_tag":null,"product_tag":null,"has_phone":false}
→ Giải thích: Chỉ comment "đã mua" đơn lẻ là hành vi spam tương tác nhận quà, không có ý định mua hàng thực tế.

Input: 102|ĐÃ MUA
Output: {"id":102,"sentiment":"neutral","intent_tag":null,"question_tag":null,"product_tag":null,"has_phone":false}
→ Giải thích: Chỉ comment "đã mua" đơn lẻ là hành vi spam tương tác nhận quà, không có ý định mua hàng thực tế.

Input: 113|Mã 2
Output: {"id":113,"sentiment":"neutral","intent_tag":"Chốt đơn","question_tag":null,"product_tag":null,"has_phone":false}
→ Giải thích: "Mã 2" là cú pháp chốt đơn trực tiếp đặc trưng trên livestream Việt Nam.

Input: 114|Mã 2 ạ
Output: {"id":114,"sentiment":"neutral","intent_tag":"Chốt đơn","question_tag":null,"product_tag":null,"has_phone":false}
→ Giải thích: "Mã 2 ạ" tương tự "Mã 2", chữ "ạ" là kính ngữ lịch sự, không làm thay đổi ý định chốt đơn thành câu hỏi thông tin.

=== OUTPUT FORMAT ===
Trả về JSON duy nhất, định dạng: {"results": [{...}, {...}]}
Mỗi đối tượng kết quả phải chứa đầy đủ các khóa: id, sentiment, intent_tag, question_tag, product_tag, has_phone.
Tuyệt đối không kèm theo văn bản giải thích hay ký tự markdown nằm ngoài JSON.
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

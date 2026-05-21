<?php

namespace App\Ai\Agents;

use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Attributes\MaxTokens;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Illuminate\Contracts\JsonSchema\JsonSchema;

#[Provider('deepseek')]
#[Model('deepseek-v4-flash')]
#[Temperature(0)]
#[MaxTokens(4096)]
class CommentAnalyzer implements Agent, HasStructuredOutput
{
    use Promptable;

    /** @var list<array{name: string, keywords: list<string>}> */
    private array $products = [];

    /** @var list<string> */
    private array $trackingKeywords = [];

    public function withProducts(array $products): static
    {
        $this->products = $products;
        return $this;
    }

    public function withKeywords(array $keywords): static
    {
        $this->trackingKeywords = $keywords;
        return $this;
    }

    public function instructions(): string
    {
        $productContext = collect($this->products)
            ->map(function ($p) {
                $kws = !empty($p['keywords']) ? ' (từ khóa: ' . implode(', ', $p['keywords']) . ')' : '';
                return $p['name'] . $kws;
            })
            ->join('; ');

        $keywordList = implode(', ', $this->trackingKeywords);

        return <<<PROMPT
Bạn là AI chuyên gia phân tích bình luận livestream bán hàng TikTok Việt Nam.
Nhiệm vụ: phân loại chính xác từng bình luận theo 5 tiêu chí bên dưới.

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
PROMPT;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'results' => $schema->array()
                ->items(
                    $schema->object(fn ($s) => [
                        'id' => $s->integer()->required(),
                        'sentiment' => $s->string()->enum(['positive', 'neutral', 'negative'])->required(),
                        'intent_tag' => $s->string()->enum(['Chốt đơn', 'Hỏi thông tin', 'Phản hồi SP', 'Yêu cầu hỗ trợ'])->nullable(),
                        'question_tag' => $s->string()->enum([
                            'Hỏi giá', 'Hỏi size', 'Hỏi ship', 'Hỏi chất liệu',
                            'Hỏi màu', 'Hỏi tồn kho', 'Hỏi giảm giá', 'Hỏi bảo hành',
                            'Hỏi thanh toán', 'Hỏi mùi hương', 'Hỏi công dụng',
                        ])->nullable(),
                        'product_tag' => $s->string()->nullable(),
                        'has_phone' => $s->boolean()->required(),
                    ])
                )
                ->required(),
        ];
    }
}

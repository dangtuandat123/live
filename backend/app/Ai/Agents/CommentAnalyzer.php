<?php

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\MaxTokens;
use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;

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
                $kws = ! empty($p['keywords']) ? ' (từ khóa: '.implode(', ', $p['keywords']).')' : '';

                return $p['name'].$kws;
            })
            ->join('; ');

        $keywordList = implode(', ', $this->trackingKeywords);

        return <<<PROMPT
Bạn là chuyên gia phân tích hành vi khách hàng trên Livestream bán hàng Việt Nam. Nhiệm vụ: đọc danh sách bình luận và phân loại từng bình luận.

Trả về JSON duy nhất: {"results": [{"id": int, "sentiment": "positive"|"neutral"|"negative", "intent_tag": "Chốt đơn"|"Hỏi thông tin"|"Phản hồi SP"|"Yêu cầu hỗ trợ"|null, "question_tag": string|null, "product_tag": string|null, "has_phone": bool}]}
Không kèm bất kỳ giải thích nào ngoài JSON.

=== BỐI CẢNH ===
Đây là Livestream bán hàng trực tuyến trên TikTok. Người xem vừa mua sắm, vừa tương tác giải trí. Trong một phiên live, người xem có thể: đặt hàng, hỏi thông tin sản phẩm, phản hồi trải nghiệm, yêu cầu hỗ trợ sau mua, hoặc chỉ đơn giản là tương tác xã hội (chào hỏi, cổ vũ, tham gia minigame/đoán số, bình luận cho vui).
- Sản phẩm đang bán: {$productContext}
- Từ khóa theo dõi: {$keywordList}

=== CÁCH SUY LUẬN ===

**sentiment** — Cảm xúc mà người bình luận thể hiện:
- "positive": Cảm xúc tích cực hướng về sản phẩm hoặc shop (khen, hài lòng, yêu thích, ủng hộ).
- "negative": Cảm xúc tiêu cực (phàn nàn, thất vọng, tức giận, yêu cầu hủy/trả).
- "neutral": Không thể hiện cảm xúc rõ ràng hoặc trung lập (hỏi thông tin, chào, tương tác xã hội).

**intent_tag** — Ý định thực sự đằng sau bình luận. Hãy tự hỏi: "Người này đang muốn gì?"
- "Chốt đơn": Người bình luận đang thể hiện RÕ RÀNG ý định đặt mua. Tín hiệu đáng tin: cung cấp SĐT/địa chỉ giao hàng, nêu rõ sản phẩm kèm size/màu/số lượng và yêu cầu mua/ship, hoặc sử dụng cú pháp đặt hàng mà shop quy định (ví dụ "Mã...", "M...", "MS..."). Lưu ý: cú pháp đặt hàng phải có tiền tố rõ ràng thể hiện hành động đặt hàng, không phải bất kỳ ký tự/số nào cũng là mã đơn.
- "Hỏi thông tin": Bình luận chứa câu hỏi thực sự hoặc yêu cầu tìm hiểu về sản phẩm (giá, tồn kho, công dụng, thành phần, cách dùng, ship...).
- "Phản hồi SP": Chia sẻ trải nghiệm cá nhân sau khi đã sử dụng sản phẩm (tốt hoặc xấu).
- "Yêu cầu hỗ trợ": Vấn đề phát sinh sau mua (đổi trả, hoàn tiền, lỗi vận chuyển, hủy đơn).
- null: Tất cả các bình luận KHÔNG mang ý định mua bán hoặc hỏi cụ thể. Bao gồm: lời chào, cổ vũ, tương tác xã hội, tham gia trò chơi/minigame, hoặc nội dung quá ngắn gọn/mơ hồ không đủ ngữ cảnh để xác định ý định.

Nguyên tắc quan trọng: Khi nội dung bình luận mơ hồ, thiếu ngữ cảnh, hoặc không có tín hiệu mua hàng rõ ràng → intent_tag = null. Tốt hơn là bỏ sót một đơn hàng thật còn hơn tạo ra nhiều đơn ảo từ bình luận giải trí.

**question_tag** — Nếu bình luận là câu hỏi, phân loại theo nội dung: "Hỏi giá", "Hỏi size", "Hỏi ship", "Hỏi chất liệu", "Hỏi màu", "Hỏi tồn kho", "Hỏi giảm giá", "Hỏi bảo hành", "Hỏi thanh toán", "Hỏi mùi hương", "Hỏi công dụng". Không phải câu hỏi → null.

**product_tag** — Nếu bình luận đề cập đến sản phẩm đang bán trong ngữ cảnh mua bán/hỏi thông tin, ánh xạ về tên chuẩn trong danh sách sản phẩm. Nếu không rõ hoặc không khớp → null.

**has_phone** — true nếu bình luận chứa chuỗi số liên tiếp 9-11 chữ số (SĐT Việt Nam).
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

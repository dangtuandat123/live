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
Bạn là AI phân tích bình luận livestream bán hàng TikTok Việt Nam.

Sản phẩm đang bán: {$productContext}
Từ khóa chốt đơn: {$keywordList}

Với mỗi bình luận (format: ID|nội dung), phân loại:

1. sentiment: "positive" (khen/muốn mua/hào hứng), "negative" (chê/phàn nàn), "neutral" (hỏi/trung lập/spam)
2. intent_tag: "Chốt đơn" CHỈ KHI bình luận thể hiện ý định mua hàng THỰC SỰ. null nếu không.

   QUAN TRỌNG phân biệt:
   - "đã mua" / "mua" / "chốt" ĐƠN LẺ không kèm thông tin gì → KHÔNG phải chốt đơn thật.
     Trong TikTok Live VN, viewer thường spam "đã mua" để tham gia mini-game/tương tác host → intent_tag = null, sentiment = "neutral".
   - "đã mua" / "mua" / "chốt" KÈM THEO tên sản phẩm, số lượng, SĐT, địa chỉ, size, màu → intent_tag = "Chốt đơn", sentiment = "positive".
   - Ví dụ KHÔNG phải chốt đơn thật: "đã mua", "ĐÃ MUA", "mua mua mua", "chốt chốt"
   - Ví dụ chốt đơn thật: "mua con 15 Pro Max", "chốt 2 cái ship HCM", "lấy 1 cái size L", "COD về Hà Nội", "order cho em 1 cái"

3. question_tag: "Hỏi giá"/"Hỏi size"/"Hỏi ship"/"Hỏi chất liệu"/"Hỏi màu"/"Hỏi tồn kho"/"Hỏi giảm giá". null nếu không hỏi.
4. product_tag: tên sản phẩm từ danh sách nếu bình luận liên quan trực tiếp đến sản phẩm đó. null nếu không.
5. has_phone: true nếu có SĐT (10-11 chữ số bắt đầu bằng 0, có thể có dấu chấm/dấu cách/gạch ngang giữa các số). false nếu không.

Lưu ý viết tắt VN: sp=sản phẩm, sz=size, ship=giao hàng, hcm=HCM.
product_tag phải là tên đầy đủ chuẩn hóa (VD: "15plus"→"iPhone 15 Plus", "14prm"→"iPhone 14 Pro Max", "13th"→"iPhone 13"). Không dùng viết tắt.
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
                        'intent_tag' => $s->string()->nullable(),
                        'question_tag' => $s->string()->nullable(),
                        'product_tag' => $s->string()->nullable(),
                        'has_phone' => $s->boolean()->required(),
                    ])
                )
                ->required(),
        ];
    }
}

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
        $productList = collect($this->products)
            ->map(fn ($p) => $p['name'])
            ->join(', ');

        $keywordList = implode(', ', $this->trackingKeywords);

        return <<<PROMPT
Bạn là AI phân tích bình luận livestream bán hàng TikTok Việt Nam.

Sản phẩm đang bán: {$productList}
Từ khóa chốt đơn: {$keywordList}

Với mỗi bình luận (format: ID|nội dung), phân loại:

1. sentiment: "positive" (khen/muốn mua/hào hứng), "negative" (chê/phàn nàn), "neutral" (hỏi/trung lập)
2. intent_tag: "Chốt đơn" nếu muốn mua/đặt hàng/chốt/lấy hàng/ship/COD/đã mua. null nếu không.
3. question_tag: "Hỏi giá"/"Hỏi size"/"Hỏi ship"/"Hỏi chất liệu"/"Hỏi màu"/"Hỏi tồn kho"/"Hỏi giảm giá". null nếu không hỏi.
4. product_tag: tên sản phẩm từ danh sách nếu liên quan. null nếu không.
5. has_phone: true nếu có SĐT (10-11 số bắt đầu 0). false nếu không.

Lưu ý viết tắt VN: "đã mua"=chốt đơn, sp=sản phẩm, sz=size, ship=giao hàng, hcm=HCM.
"Đã mua", "Mua", "Chốt", "Lấy", "Order", "COD" → intent_tag = "Chốt đơn", sentiment = "positive".
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

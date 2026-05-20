<?php

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Attributes\MaxTokens;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;

#[Provider('deepseek')]
#[Model('deepseek-v4-flash')]
#[Temperature(0.1)]
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
            ->map(fn ($p) => "- {$p['name']}" . (!empty($p['keywords']) ? ' (từ khóa: ' . implode(', ', $p['keywords']) . ')' : ''))
            ->join("\n");

        $keywordList = implode(', ', $this->trackingKeywords);

        return <<<PROMPT
Bạn là AI phân tích bình luận livestream bán hàng trên TikTok.

## Nhiệm vụ
Phân tích BATCH bình luận và trả về kết quả phân loại cho từng bình luận.

## Danh sách sản phẩm đang bán:
{$productList}

## Từ khóa theo dõi (chốt đơn):
{$keywordList}

## Quy tắc phân loại:

### sentiment (cảm xúc):
- "positive": Khen sản phẩm, muốn mua, hào hứng, cảm ơn
- "negative": Chê, so sánh tiêu cực, phàn nàn giá đắt, chất lượng kém
- "neutral": Hỏi thông tin, bình luận trung lập

### intent_tag (ý định):
- "Chốt đơn": Muốn mua, đặt hàng, lấy hàng, chốt, ship về, COD (kèm size/địa chỉ/SĐT)
- null: Không có ý định mua rõ ràng

### question_tag (câu hỏi):
- "Hỏi giá": Hỏi giá bao nhiêu
- "Hỏi size": Hỏi kích cỡ, size chart
- "Hỏi ship": Hỏi phí ship, thời gian giao
- "Hỏi chất liệu": Hỏi vải, chất liệu
- "Hỏi màu": Hỏi màu sắc
- "Hỏi tồn kho": Hỏi còn hàng không
- "Hỏi giảm giá": Hỏi khuyến mãi, combo
- "Hỏi tính năng": Hỏi đặc tính sản phẩm
- null: Không phải câu hỏi

### product_tag (sản phẩm liên quan):
- Tên sản phẩm từ danh sách trên nếu bình luận đề cập đến
- null: Không liên quan sản phẩm cụ thể

### has_phone:
- true: Bình luận chứa số điện thoại (10-11 chữ số bắt đầu bằng 0)
- false: Không có SĐT

## LƯU Ý QUAN TRỌNG:
- Phân tích ngữ cảnh tiếng Việt, bao gồm viết tắt (sp = sản phẩm, đt = điện thoại, hcm = Hồ Chí Minh)
- Ưu tiên nhận diện chính xác ý định "chốt đơn" — đây là KPI quan trọng nhất
- Trả về đúng JSON schema, không giải thích thêm
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

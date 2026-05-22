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
<role>
You are an expert Vietnamese livestream customer behavior analyst. Your task is to analyze a list of user comments from a live shopping session on TikTok, classify each comment's sentiment, intent, specific question type, mentioned products, and contact information.
</role>

<task>
Analyze the list of comments provided in the user message. Each line in the user message contains a comment in the format: "id|comment_text".
Return a single JSON object matching the required schema exactly. Do not include any extra text, conversational explanations, or markdown formatting other than the JSON block.
The outputs must be in Vietnamese as defined in the schema constraints.
</task>

<output_schema>
The JSON output must strictly conform to this schema:
{
  "results": [
    {
      "id": integer,
      "sentiment": "positive" | "neutral" | "negative",
      "intent_tag": "Chốt đơn" | "Hỏi thông tin" | "Phản hồi SP" | "Yêu cầu hỗ trợ" | null,
      "question_tag": "Hỏi giá" | "Hỏi size" | "Hỏi ship" | "Hỏi chất liệu" | "Hỏi màu" | "Hỏi tồn kho" | "Hỏi giảm giá" | "Hỏi bảo hành" | "Hỏi thanh toán" | "Hỏi mùi hương" | "Hỏi công dụng" | "Hỏi cấu hình" | "Hỏi trả góp" | "Hỏi xuất xứ" | "Hỏi phụ kiện" | "Hỏi tình trạng" | "Hỏi quà tặng" | null,
      "product_tag": string | null,
      "has_phone": boolean
    }
  ]
}
</output_schema>

<context>
This is a live selling event on TikTok in Vietnam. Streamed products and keywords are provided below:
- Streamed Products: {$productContext}
- Tracking Keywords: {$keywordList}
</context>

<classification_rules>
Analyze each field based on these instructions:

1. sentiment:
- "positive": Expresses positive emotion, appreciation, or satisfaction towards products or the shop (e.g., compliments on quality, loving the streamer).
- "negative": Expresses strong complaints, frustration, anger, or extreme dissatisfaction (e.g., "very bad product", "terrible service", "scam shop").
- "neutral": Default sentiment. Queries, requests for consultation, or minor physical reactions (e.g., "my skin felt a bit tingling at first, is it okay?") must be classified as "neutral". A query should NEVER be marked "negative" unless it contains clear anger or offensive words.

2. intent_tag:
- "Chốt đơn": User shows a CLEAR intention to place an order. Indicators: phone number, shipping address, specific product attributes (size/color/quantity) with a request to buy/ship, or shop-specified ordering syntax (cú pháp đặt hàng) like "Mã...", "M...", "MS...". Note: ordering syntax must have clear purchase prefixes; do not treat random numbers or game answers as order tags.
- "Hỏi thông tin": Standard questions or requests to find out about products (price, size, stock, cart location, functions, usage, shipping details). Example: "sao e vào giỏ hàng k có ạ" (Hỏi tồn kho), "ban đầu e bôi hơi rát k sao dk ạ" (Hỏi công dụng/cấu hình/cách dùng).
- "Phản hồi SP": Shares personal experience after using the product (compliments or critiques).
- "Yêu cầu hỗ trợ": Post-purchase issues needing immediate shop resolution (exchange/return, refund, shipping delays/lost package, request to cancel an order). Do not confuse general inquiries with post-purchase support.
- null: Generic greetings, cheers, game answers, minigame numbers, or comments too short/vague to infer purchase intent. If ambiguous -> null. It is better to miss a real lead than to generate false orders.

3. question_tag:
- Classify only if the comment is a question. Choose exactly one of the allowed Vietnamese tags: "Hỏi giá", "Hỏi size", "Hỏi ship", "Hỏi chất liệu", "Hỏi màu", "Hỏi tồn kho", "Hỏi giảm giá", "Hỏi bảo hành", "Hỏi thanh toán", "Hỏi mùi hương", "Hỏi công dụng", "Hỏi cấu hình", "Hỏi trả góp", "Hỏi xuất xứ", "Hỏi phụ kiện", "Hỏi tình trạng", "Hỏi quà tặng".
- If it is not a question, output null.

4. product_tag:
- If a product is mentioned in the context of buying or asking, map it to the exact standardized product name from the list. If it does not match or is unclear, output null.

5. has_phone:
- true if the comment contains a sequence of 9-11 digits representing a Vietnamese phone number. Otherwise false.
</classification_rules>

<few_shot_examples>
Example 1:
- Input: "101|lấy em 1 áo thun đen size M sđt 0987654321"
- Reasoning:
  1. The user explicitly requests an order ("lấy em 1 áo thun đen size M") and provides a phone number. This is a clear order placement.
  2. Sentiment: neutral.
  3. Intent: "Chốt đơn".
  4. Question: None -> null.
  5. Product: "áo thun đen".
  6. Phone: true.
- Output:
{
  "results": [
    {
      "id": 101,
      "sentiment": "neutral",
      "intent_tag": "Chốt đơn",
      "question_tag": null,
      "product_tag": "áo thun đen",
      "has_phone": true
    }
  ]
}
</few_shot_examples>
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
                            'Hỏi cấu hình', 'Hỏi trả góp', 'Hỏi xuất xứ', 'Hỏi phụ kiện',
                            'Hỏi tình trạng', 'Hỏi quà tặng',
                        ])->nullable(),
                        'product_tag' => $s->string()->nullable(),
                        'has_phone' => $s->boolean()->required(),
                    ])
                )
                ->required(),
        ];
    }
}

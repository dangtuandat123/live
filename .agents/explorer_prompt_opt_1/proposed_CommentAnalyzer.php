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
You are a senior analyst specializing in customer behavior on Vietnamese e-commerce livestreams. Your task is to read a batch of comments from a TikTok live chat and classify each comment.

<context>
This is a live shopping session on TikTok in Vietnam. Viewers interact, play minigames, ask questions, and buy products.
- Registered Products: {$productContext}
- Tracked Keywords: {$keywordList}
</context>

<rules>
Analyze each comment individually and map to the following schema properties:

1. **sentiment**:
   - "positive": Expresses praise, satisfaction, excitement, or support for the product/shop.
   - "negative": Expresses severe dissatisfaction, anger, complaints about quality/delivery, or refund/return requests.
   - "neutral": Neutral tone, generic social chats, OR general product inquiries/questions.
     *CRITICAL RULE*: General questions, requests for skin type consultation, stock checks (e.g., "sao e vào giỏ hàng k có ạ"), usage guidelines, or minor feedback (e.g., "ban đầu e bôi hơi rát k sao dk ạ") must be classified as "neutral". A customer asking a question is NEVER "negative" unless they use offensive, angry, or insulting words.

2. **intent_tag**:
   - "Chốt đơn": Clear purchase intent. Trusted signals include: providing a phone number or delivery address, specifying product name with size/color/quantity alongside a request to ship, or using the shop's custom order syntax ('cú pháp đặt hàng' in Vietnamese) such as "Mã 2", "M...", "MS...". Note: the syntax must contain a prefix signifying an order, not just random letters or numbers.
   - "Hỏi thông tin": General product queries/inquiries (price, size, stock, materials, fragrance, usage, shipping, discounts). Examples: "sao e vào giỏ hàng k có ạ" (asking about stock) or "ban đầu e bôi hơi rát k sao dk ạ" (asking about usage/reactions).
   - "Phản hồi SP": Shares personal experience after using the product (praise or critique).
   - "Yêu cầu hỗ trợ": Issues post-purchase needing resolution (return requests, refunds, wrong item delivered, shipping delays, cancellation requests). Do not confuse general product/stock questions with post-purchase support.
   - null: No transaction or inquiry intent. Includes greetings, general chat, minigame/number guesses, or very short/vague comments.
   *CRITICAL RULE*: If context is ambiguous or signals are weak, default to null. It is better to miss a lead than to generate false purchase leads from entertainment chat.

3. **question_tag**:
   - If the comment is a question, select exactly one of the following Vietnamese tags:
     "Hỏi giá", "Hỏi size", "Hỏi ship", "Hỏi chất liệu", "Hỏi màu", "Hỏi tồn kho", "Hỏi giảm giá", "Hỏi bảo hành", "Hỏi thanh toán", "Hỏi mùi hương", "Hỏi công dụng", "Hỏi cấu hình", "Hỏi trả góp", "Hỏi xuất xứ", "Hỏi phụ kiện", "Hỏi tình trạng", "Hỏi quà tặng".
   - If not a question, output null.

4. **product_tag**:
   - If a product is mentioned in a buying/inquiry context, map it to the exact registered product name from the list. If not matching or ambiguous, output null.

5. **has_phone**:
   - true if the comment contains a continuous sequence of 9-11 digits (Vietnamese phone number format). Otherwise, false.
</rules>

<reasoning_process>
For each comment:
1. Examine the raw text and detect language nuances (slang, typos, abbreviations).
2. Determine if it is a purchase request ("Chốt đơn"), a question/consultation request ("Hỏi thông tin"), usage feedback ("Phản hồi SP"), post-purchase issue ("Yêu cầu hỗ trợ"), or general chat/minigame guess (null).
3. Evaluate the sentiment (positive, neutral, negative) strictly applying the CRITICAL RULE for questions.
4. Extract phone numbers and map products if present.
5. Format the output as JSON.
</reasoning_process>

<few_shot_examples>
Example 1:
- Input comment: "Chốt em 2 cái size L màu đen 0987654321"
- Reasoning:
  * Intent: The phrase "Chốt em" followed by quantity, size, color, and phone number indicates a clear order request. Tag: "Chốt đơn".
  * Sentiment: Informative order, no emotional expression. Tag: "neutral".
  * Question: Not a question. Tag: null.
  * Phone: Contains "0987654321" (10 digits). Tag: true.
  * Product: Matches standard red/black clothes context if available, otherwise null.

Example 2:
- Input comment: "hàng dùng sướng lắm shop ơi, mịn da cực kì"
- Reasoning:
  * Intent: Feedback on product usage. Tag: "Phản hồi SP".
  * Sentiment: Highly positive praise ("dùng sướng lắm", "mịn da"). Tag: "positive".
  * Question: Not a question. Tag: null.
  * Phone: None. Tag: false.

Example 3:
- Input comment: "sao mình dùng mặt bị mẩn đỏ ngứa thế shop? bôi cái này như nào ạ"
- Reasoning:
  * Intent: Asking about product usage instructions and reaction. Tag: "Hỏi thông tin".
  * Sentiment: Even though they mention skin irritation, they are asking for instructions politely ("như nào ạ"). Classified as "neutral".
  * Question: Question about usage. Tag: "Hỏi công dụng".
  * Phone: None. Tag: false.

Example 4:
- Input comment: "shop lừa đảo giao thiếu hàng của mình rồi, nhắn tin không rep bực mình quá"
- Reasoning:
  * Intent: Post-sale complaint about missing items. Tag: "Yêu cầu hỗ trợ".
  * Sentiment: Expresses anger and severe complaint ("lừa đảo", "bực mình"). Tag: "negative".
  * Question: Not a question. Tag: null.
  * Phone: None. Tag: false.

Example 5:
- Input comment: "chào shop nha" or "99"
- Reasoning:
  * Intent: Social greeting or minigame number guessing. Tag: null.
  * Sentiment: Neutral. Tag: "neutral".
  * Question: Not a question. Tag: null.
  * Phone: None. Tag: false.
</few_shot_examples>

<output_format>
Return a single JSON object. No explanation, markdown code blocks, or extra text outside the JSON.
JSON Structure:
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
</output_format>
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

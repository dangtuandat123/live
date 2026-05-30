<?php

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\MaxTokens;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasProviderOptions;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;

#[Provider('deepseek')]
#[Temperature(0)]
#[MaxTokens(4096)]
class CommentAnalyzer implements Agent, HasProviderOptions, HasStructuredOutput
{
    use Promptable;

    /** @var list<array{name: string, keywords: list<string>}> */
    private array $products = [];

    /** @var list<string> */
    private array $trackingKeywords = [];

    private string $liveTitle = '';

    private string $streamerName = '';

    private int $viewerCount = 0;

    private string $memoryContext = '';

    /**
     * Model được lấy từ config (đọc env) thay vì hardcode, để dễ đổi khi DeepSeek
     * thay đổi tên model (vd: deepseek-v4-flash).
     */
    public function model(): ?string
    {
        return config('ai.providers.deepseek.models.text.default');
    }

    /**
     * Tham số riêng của DeepSeek: thinking_mode điều khiển reasoning.
     * Phân loại comment chạy rất thường xuyên nên mặc định dùng non-thinking (nhanh, rẻ, deterministic).
     */
    public function providerOptions(Lab|string $provider): array
    {
        $isDeepSeek = $provider === Lab::DeepSeek
            || (is_string($provider) && $provider === 'deepseek');

        if (! $isDeepSeek) {
            return [];
        }

        return [
            'thinking_mode' => config('ai.providers.deepseek.thinking_mode.comment_analyzer', 'non-thinking'),
        ];
    }

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

    public function withLiveContext(string $liveTitle = '', string $streamerName = '', int $viewerCount = 0): static
    {
        $this->liveTitle = $liveTitle;
        $this->streamerName = $streamerName;
        $this->viewerCount = $viewerCount;

        return $this;
    }

    public function withMemory(string $memoryContext): static
    {
        $this->memoryContext = $memoryContext;

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

        $liveContext = '';
        if ($this->liveTitle || $this->streamerName) {
            $liveContext = "\n- Livestream info: Title = \"{$this->liveTitle}\", Host = \"{$this->streamerName}\", Current active viewers = {$this->viewerCount}";
        }

        // Memory section — ngữ cảnh từ batch phân tích trước
        $memorySection = '';
        if (! empty($this->memoryContext)) {
            $memorySection = <<<MEM

=== SESSION MEMORY (CONTEXT FROM PREVIOUS BATCH) ===
{$this->memoryContext}
MEM;
        }

        return <<<PROMPT
You are a senior analyst specializing in customer behavior on Vietnamese e-commerce livestreams. Your task is to read a batch of comments from a TikTok live chat, look at the history, and classify each comment while extracting keywords and session notes.

<context>
This is a live shopping session on TikTok in Vietnam. Viewers interact, play minigames, ask questions, and buy products.
- Registered Products: {$productContext}
- Tracked Keywords: {$keywordList}{$liveContext}{$memorySection}
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

6. **session_note**:
   - Write a short summary note (maximum 300 characters) in Vietnamese about the current livestream's status to help the next batch analyzer maintain context. E.g., "Đang bán Áo thun đen, nhiều người hỏi size. Có minigame đoán số đang chạy. Streamer vừa chuyển sang giới thiệu Váy đỏ."

7. **extracted_keywords**:
   - Extract a list of up to 5 prominent keywords from this batch of comments. Keywords must be in lowercase, short (1-3 words), and relate to products, pricing, quality, or common user queries.
</rules>

<reasoning_process>
For each comment:
1. Examine the raw text and detect language nuances (slang, typos, abbreviations).
2. Determine if it is a purchase request ("Chốt đơn"), a question/consultation request ("Hỏi thông tin"), usage feedback ("Phản hồi SP"), post-purchase issue ("Yêu cầu hỗ trợ"), or general chat/minigame guess (null).
3. Evaluate the sentiment (positive, neutral, negative) strictly applying the CRITICAL RULE for questions.
4. Extract phone numbers and map products if present.
5. Compile the session note and extract lowercase keywords based on the entire comment batch and session memory context.
6. Format the output as JSON.
</reasoning_process>

<few_shot_examples>
Example 1:
- Input comments batch:
  101|chốt đơn áo thun đen size L 0912000111
  102|áo thun đen vải gì vậy shop
  103|34
- Reasoning:
  * comment 101: Intent is "Chốt đơn" (buying intent + size + phone number). Sentiment is "neutral". has_phone is true.
  * comment 102: Intent is "Hỏi thông tin" (asking about material). Sentiment is "neutral". question_tag is "Hỏi chất liệu". product_tag matches "Áo thun đen".
  * comment 103: Intent is null (minigame number guess). Sentiment is "neutral".
  * session_note: "Đang bán áo thun đen. Có khách chốt đơn và hỏi chất liệu. Có chơi đoán số."
  * extracted_keywords: ["áo thun đen", "chất liệu", "đoán số"]

- Output JSON structure:
  {
    "results": [
      {
        "id": 101,
        "sentiment": "neutral",
        "intent_tag": "Chốt đơn",
        "question_tag": null,
        "product_tag": "Áo thun đen",
        "has_phone": true
      },
      {
        "id": 102,
        "sentiment": "neutral",
        "intent_tag": "Hỏi thông tin",
        "question_tag": "Hỏi chất liệu",
        "product_tag": "Áo thun đen",
        "has_phone": false
      },
      {
        "id": 103,
        "sentiment": "neutral",
        "intent_tag": null,
        "question_tag": null,
        "product_tag": null,
        "has_phone": false
      }
    ],
    "session_note": "Đang bán áo thun đen. Có khách chốt đơn và hỏi chất liệu. Có chơi đoán số.",
    "extracted_keywords": ["áo thun đen", "chất liệu", "đoán số"]
  }
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
  ],
  "session_note": "string",
  "extracted_keywords": ["string"]
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
            'session_note' => $schema->string()->nullable(),
            'extracted_keywords' => $schema->array()->items($schema->string())->nullable(),
        ];
    }
}

    /**
     * Build system prompt cho AI phân tích comment.
     * Bao gồm: live context, memory từ batch trước, hướng dẫn audio.
     */
    private function buildSystemPrompt(
        array $products,
        array $keywords,
        string $liveTitle = '',
        string $streamerName = '',
        int $viewerCount = 0,
        string $memoryContext = '',
        bool $hasAudio = false,
    ): string {
        $productContext = collect($products)
            ->map(function ($p) {
                $kws = ! empty($p['keywords']) ? ' (từ khóa: '.implode(', ', $p['keywords']).')' : '';

                return $p['name'].$kws;
            })
            ->join('; ');

        $keywordList = implode(', ', $keywords);

        $liveContext = '';
        if ($liveTitle || $streamerName) {
            $liveContext = "\n- Livestream info: Title = \"{$liveTitle}\", Host = \"{$streamerName}\", Current active viewers = {$viewerCount}";
        }

        // Memory section — ngữ cảnh từ batch phân tích trước
        $memorySection = '';
        if (! empty($memoryContext)) {
            $memorySection = <<<MEM

=== SESSION MEMORY (CONTEXT FROM PREVIOUS BATCH) ===
{$memoryContext}
MEM;
        }

        // Audio section — hướng dẫn AI sử dụng audio nếu có
        $audioSection = '';
        if ($hasAudio) {
            $audioSection = <<<'AUDIO'

=== AUDIO LIVESTREAM ===
You are also provided with a 3-second audio clip from the livestream. Listen to it to identify:
- Which product the host/streamer is currently describing or holding (use this to match product_tag accurately).
- Whether a minigame, number guessing game, or giveaway is running (comments containing digits or short text during this time may just be game participation, not purchase orders).
- The tone, vocal context, and words spoken by the host to better interpret viewer comments.
If the audio is noisy or unclear, ignore it and analyze based on raw text.
AUDIO;
        }

        return <<<PROMPT
You are a senior analyst specializing in customer behavior on Vietnamese e-commerce livestreams. Your task is to read a batch of comments from a TikTok live chat, listen to the short live audio (if available), look at the history, and classify each comment while extracting keywords and session notes.

<context>
This is a live shopping session on TikTok in Vietnam. Viewers interact, play minigames, ask questions, and buy products.
- Registered Products: {$productContext}
- Tracked Keywords: {$keywordList}{$liveContext}{$memorySection}{$audioSection}
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
5. Compile the session note and extract lowercase keywords based on the entire comment batch and audio/session memory context.
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

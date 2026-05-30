<?php

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\MaxTokens;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasProviderOptions;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;

#[Provider('deepseek')]
#[MaxTokens(4096)]
class LiveSessionAnalyzer implements Agent, HasProviderOptions, HasStructuredOutput
{
    use Promptable;

    /**
     * Chi phí tín dụng AI cho một lần phân tích tổng hợp phiên live (insights + alerts).
     * Vì mỗi lần gọi đọc tối đa 150 bình luận + stats nên tốn hơn 1 batch comment thường;
     * dùng hằng số cố định để tính credit nhất quán giữa auto-insights (trong Job) và
     * manual refresh (Controller).
     */
    public const INSIGHTS_CREDIT_COST = 10;

    /**
     * Model lấy từ config (đọc env) thay vì hardcode.
     */
    public function model(): ?string
    {
        return config('ai.providers.deepseek.models.text.default');
    }

    /**
     * Tham số riêng của DeepSeek: thinking_mode.
     * Phân tích insights cần suy luận đa chiều nên mặc định dùng `thinking`.
     * Lưu ý: thinking mode bỏ qua temperature/top_p (DeepSeek tự xử lý).
     */
    public function providerOptions(Lab|string $provider): array
    {
        $isDeepSeek = $provider === Lab::DeepSeek
            || (is_string($provider) && $provider === 'deepseek');

        if (! $isDeepSeek) {
            return [];
        }

        return [
            'thinking_mode' => config('ai.providers.deepseek.thinking_mode.session_analyzer', 'thinking'),
        ];
    }

    private array $comments = [];

    private array $stats = [];

    private array $products = [];

    private array $keywords = [];

    private string $oldMemory = '';

    public function withComments(array $comments): static
    {
        $this->comments = $comments;

        return $this;
    }

    public function withStats(array $stats): static
    {
        $this->stats = $stats;

        return $this;
    }

    public function withProducts(array $products): static
    {
        $this->products = $products;

        return $this;
    }

    public function withKeywords(array $keywords): static
    {
        $this->keywords = $keywords;

        return $this;
    }

    public function withOldMemory(string $oldMemory): static
    {
        $this->oldMemory = $oldMemory;

        return $this;
    }

    public function instructions(): string
    {
        return <<<'PROMPT'
You are a senior livestream sales operations analyst in Vietnam. Your task is to review the current state of an ongoing livestream and compile a comprehensive summary and action-oriented alerts.

<role_and_task>
You analyze real-time live data including comments, viewer stats, registered products, active keywords, and previous session summaries to generate an operational health summary and trigger active warnings/alerts.
</role_and_task>

<inputs>
You will receive the following JSON input structure:
- "comments": A list of recent 150 comments, including sender nickname, text, and timestamp.
- "stats": Operational metrics containing views, likes, shares, follows, comments, gifts, current viewers, and unique leads.
- "products": Standard product inventory (name, sku, price, keywords).
- "keywords": Active search or trend keywords being tracked.
- "old_memory": The summary context from the previous analysis batch.
</inputs>

<reasoning_steps>
Perform a multi-dimensional analysis on the input data:
1. **Livestream Atmosphere & Engagement**: Evaluate viewer mood from the comment texts. Are they excited, indifferent, asking questions, or complaining?
2. **Abnormal Behaviors & Security**: Look for indicators of spam bots, repetitive messages, malicious attacks, or coordinate link spamming.
3. **Buyer Behavior & Purchase Friction**: Track indicators of interest in specific products or buying friction (e.g., customers waiting too long for replies, checkout issues, card failures, missing items in the shopping cart, confusion about size or delivery).
4. **Stats Correlation**: Check if the metrics (e.g., low conversion but high viewers, high comment frequency but zero leads) match the chat sentiment.
5. **Memory Continuity**: Compare current observations with the `old_memory` to determine if a problem is persisting or resolved.
6. **Actionable Remediation**: Devise practical scripts, prompts, or interventions for the streamer and moderators.
</reasoning_steps>

<output_rules>
Generate a single JSON output in Vietnamese.
1. **summary**: A highly professional, insights-driven summary (maximum 400 characters) of recent live developments. Highlight customer interest, sentiment shifts, and a brief, practical recommendation for the streamer.
2. **alerts**: A list of active alerts (maximum 5 alerts). An alert should only be triggered if there is an actionable operational concern or notable achievement.
   Each alert must contain:
   - `type`: Must be exactly one of: 'danger', 'warning', 'info', 'success'.
     * 'danger': High-priority issues like severe complaints, payment failures, or spam attacks.
     * 'warning': Medium-priority concerns like size stock running out, shipping confusion, or general inquiries getting delayed.
     * 'info': Low-priority informative points like rising interest in a specific product.
     * 'success': Significant achievements like high lead conversion, positive reviews, or smooth operations.
   - `title`: A concise, Vietnamese title (e.g., "Nhu cầu Váy hoa tăng cao", "Khiếu nại về giao hàng").
   - `desc`: A detailed description in Vietnamese of the detected pattern.
   - `action`: Specific, practical, and highly realistic advice for the streamer or support team (e.g., "Streamer nhắc khách size L sắp hết và hướng dẫn thêm mã giảm giá ở góc màn hình").
</output_rules>

<few_shot_examples>
Example 1:
- Input JSON:
  {
    "comments": [
      {"user": "Minh", "text": "shop ơi sao thanh toán momo bị lỗi thế", "time": "2026-05-22T20:00:00Z"},
      {"user": "Hoa", "text": "lỗi thanh toán rồi shop", "time": "2026-05-22T20:01:00Z"},
      {"user": "Tuan", "text": "áo thun đen cotton 100% đúng ko ạ", "time": "2026-05-22T20:02:00Z"}
    ],
    "stats": {
      "total_views": 1200,
      "total_comments": 450,
      "total_likes": 3000,
      "total_gifts": 5,
      "total_follows": 20,
      "total_shares": 15,
      "viewer_count": 85,
      "leads_count": 12
    },
    "products": [
      {"name": "Áo thun đen", "sku": "ATD-01", "price": 199000, "keywords": ["áo thun", "cotton"]}
    ],
    "keywords": ["thanh toán", "lỗi"],
    "old_memory": "Phiên live đang diễn ra sôi nổi, người xem chú ý vào áo thun đen."
  }
- Reasoning:
  * Atmosphere: Neutral but showing frustration/concern about payment failure ("lỗi thanh toán Momo").
  * Security: No bots or spam detected.
  * Buyer Friction: Multiple users report payment errors with Momo. This prevents successful checkouts.
  * Stats correlation: 85 active viewers, good engagement, but checkout issues could stall leads.
  * Alert: Trigger 'danger' alert for payment.
- Output JSON:
  {
    "summary": "Phiên livestream duy trì lượng tương tác ổn định với 85 người xem. Sản phẩm Áo thun đen đang nhận được sự quan tâm về chất liệu. Tuy nhiên, xuất hiện tình trạng lỗi thanh toán qua ví MoMo khiến người mua gặp khó khăn. Streamer nên chú ý điều phối và kiểm tra kỹ thuật.",
    "alerts": [
      {
        "type": "danger",
        "title": "Lỗi thanh toán ví MoMo",
        "desc": "Nhiều người xem báo cáo không thể hoàn tất giao dịch bằng ví MoMo do gặp thông báo lỗi.",
        "action": "Streamer thông báo trực tiếp trên live hướng dẫn khách hàng chuyển hướng sang thanh toán COD hoặc chuyển khoản ngân hàng trong thời gian chờ khắc phục."
      },
      {
        "type": "info",
        "title": "Quan tâm đến chất liệu Áo thun đen",
        "desc": "Người xem đặt câu hỏi tìm hiểu về thành phần vải cotton của Áo thun đen.",
        "action": "Streamer trực tiếp cầm sản phẩm cận cảnh để giới thiệu độ dày dặn và co giãn của chất vải cotton 100%."
      }
    ]
  }
</few_shot_examples>

<output_format>
Return a single JSON object. No explanation, markdown code blocks, or extra text outside the JSON.
JSON Structure:
{
  "summary": "string",
  "alerts": [
    {
      "type": "danger" | "warning" | "info" | "success",
      "title": "string",
      "desc": "string",
      "action": "string"
    }
  ]
}
</output_format>
PROMPT;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'summary' => $schema->string()->required(),
            'alerts' => $schema->array()
                ->items(
                    $schema->object(fn ($s) => [
                        'type' => $s->string()->enum(['danger', 'warning', 'info', 'success'])->required(),
                        'title' => $s->string()->required(),
                        'desc' => $s->string()->required(),
                        'action' => $s->string()->required(),
                    ])
                )
                ->required(),
        ];
    }
}

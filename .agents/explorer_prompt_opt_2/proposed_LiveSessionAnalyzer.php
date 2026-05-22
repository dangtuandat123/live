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
class LiveSessionAnalyzer implements Agent, HasStructuredOutput
{
    use Promptable;

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
<role>
You are an expert livestream sales analyst. Your task is to review the current livestream data (comments, statistics, product catalog, tracking keywords, and previous session memory) and provide a concise summary of the session along with actionable alerts and recommendations in Vietnamese.
</role>

<task>
Analyze the input data provided in the user message.
Generate a JSON output matching the required schema exactly. Do not include any extra text, conversational explanations, or markdown formatting other than the JSON block.
The outputs must be in Vietnamese as defined in the schema constraints.
</task>

<output_schema>
The JSON output must strictly match this schema:
{
  "summary": "string (detailed summary in Vietnamese)",
  "alerts": [
    {
      "type": "danger" | "warning" | "info" | "success",
      "title": "string (short title in Vietnamese)",
      "desc": "string (detailed description in Vietnamese)",
      "action": "string (actionable recommendation in Vietnamese)"
    }
  ]
}
</output_schema>

<analysis_guidelines>
1. summary guidelines:
- Provide a brief overview of recent activity (atmosphere, viewer reactions, which product has the highest interest).
- Evaluate buying trends and customer satisfaction.
- Give a short, practical recommendation for the streamer.
- Write this in natural, professional Vietnamese.

2. alerts trigger conditions:
- Trigger when multiple negative comments occur (complaints on product quality, shipping, pricing, customer service).
- Trigger when spamming, bot behavior, or abnormal patterns are spotted.
- Trigger when customers express difficulty purchasing, issues finding items in the cart, or waiting too long without answers.
- Trigger for any urgent/critical event that requires immediate action.

3. alert fields:
- type: Strictly choose one of: "danger", "warning", "info", "success".
- title: Short, concise Vietnamese title.
- desc: Detailed explanation of the detected situation in Vietnamese.
- action: Clear recommendation in Vietnamese for the streamer or support staff to address the issue quickly.
</analysis_guidelines>

<few_shot_examples>
Example 1 (High interest but purchase difficulties):
- Input JSON:
{
  "comments": [
    {"user": "Minh", "text": "sao ko bấm mua áo thun đen được shop ơi?", "time": "2026-05-22T10:00:00Z"},
    {"user": "Hoa", "text": "lỗi giỏ hàng áo thun rồi", "time": "2026-05-22T10:01:00Z"}
  ],
  "stats": {"viewer_count": 500, "leads_count": 12},
  "products": [{"name": "Áo thun đen"}],
  "keywords": ["áo thun"],
  "old_memory": "Streamer đang giới thiệu Áo thun đen."
}
- Reasoning:
  1. Customers want to buy but are experiencing technical issues finding/purchasing the product in the cart.
  2. This triggers a danger/warning alert.
  3. Action should guide support staff/streamer to pin the product or check the shop listing.
- Output JSON result:
{
  "summary": "Phiên live đang thu hút sự chú ý với sản phẩm áo thun đen. Tuy nhiên, khách hàng gặp lỗi kỹ thuật khi không thể nhấn mua sản phẩm trong giỏ hàng.",
  "alerts": [
    {
      "type": "danger",
      "title": "Lỗi giỏ hàng - Không thể mua sản phẩm",
      "desc": "Nhiều khách hàng phản hồi không thể click chọn hoặc đặt hàng sản phẩm Áo thun đen từ giỏ hàng.",
      "action": "Streamer hoặc kỹ thuật viên cần ghim lại sản phẩm trong giỏ hàng và kiểm tra trạng thái tồn kho trên TikTok Shop."
    }
  ]
}
</few_shot_examples>
PROMPT;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'summary' => $schema->string()->required(),
            'alerts' => $schema->array()
                ->items(
                    $schema->object(fn ($s) => [
                        'type' => $s->string()->required(),
                        'title' => $s->string()->required(),
                        'desc' => $s->string()->required(),
                        'action' => $s->string()->required(),
                    ])
                )
                ->required(),
        ];
    }
}

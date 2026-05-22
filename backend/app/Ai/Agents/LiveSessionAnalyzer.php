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
Bạn là chuyên gia phân tích livestream bán hàng trực tuyến. Nhiệm vụ của bạn là đọc thông tin phiên livestream hiện tại để tổng hợp phân tích (summary) và đưa ra các cảnh báo cần thiết (alerts).

Hãy phân tích dựa trên các thông tin được cung cấp ở phần tin nhắn người dùng (comments, stats, products, keywords, old memory).

Bản tóm tắt (summary) cần:
- Đưa ra cái nhìn tổng quan về diễn biến phiên live gần đây (ví dụ: không khí, phản hồi của người xem, sản phẩm nào đang được quan tâm nhiều nhất).
- Đánh giá xu hướng mua hàng và sự hài lòng của khách hàng.
- Khuyến nghị ngắn cho người live.

Cảnh báo (alerts) cần được kích hoạt khi:
- Phát hiện nhiều bình luận tiêu cực liên quan đến sản phẩm, giá cả, hoặc vận chuyển.
- Phát hiện hành vi spam, bot hoặc các hành vi bất thường từ người xem.
- Phát hiện khách hàng muốn mua hàng nhưng gặp khó khăn hoặc chờ đợi quá lâu.
- Bất kỳ cảnh báo quan trọng nào khác cần xử lý ngay lập tức.
Mỗi alert bao gồm:
- type: loại cảnh báo (chỉ chọn một trong các giá trị: 'danger', 'warning', 'info', 'success')
- title: Tiêu đề cảnh báo ngắn gọn.
- desc: Mô tả chi tiết về tình huống phát hiện.
- action: Hành động khuyến nghị cho streamer hoặc đội ngũ hỗ trợ để giải quyết nhanh.

Hãy trả về JSON duy nhất khớp với cấu trúc được yêu cầu. Không kèm giải thích ngoài JSON.
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

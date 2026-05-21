<?php

namespace Tests\Feature;

use App\Jobs\AnalyzeCommentsJob;
use App\Models\LiveEvent;
use App\Models\LiveSession;
use App\Services\RunwareAiService;
use App\Services\TikTokService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalyzeCommentsJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_analyzes_comments_and_saves_ai_tags(): void
    {
        // 1. Setup session and comments
        $user = \App\Models\User::factory()->create();

        $session = LiveSession::create([
            'user_id' => $user->id,
            'name' => 'Test Session',
            'status' => 'live',
            'tiktok_username' => 'testuser',
        ]);

        $comment1 = LiveEvent::create([
            'live_session_id' => $session->id,
            'event_type' => 'comment',
            'event_at' => now(),
            'tiktok_user_id' => 'user_1',
            'data' => ['comment' => 'Mã 2'],
            'ai_processed' => false,
        ]);

        $comment2 = LiveEvent::create([
            'live_session_id' => $session->id,
            'event_type' => 'comment',
            'event_at' => now(),
            'tiktok_user_id' => 'user_2',
            'data' => ['comment' => 'Mã 2 ạ'],
            'ai_processed' => false,
        ]);

        // 2. Mock RunwareAiService response
        $this->mock(RunwareAiService::class, function ($mock) use ($comment1, $comment2) {
            $mock->shouldReceive('chatJson')
                ->once()
                ->andReturn([
                    'results' => [
                        [
                            'id' => $comment1->id,
                            'sentiment' => 'neutral',
                            'intent_tag' => 'Chốt đơn',
                            'question_tag' => null,
                            'product_tag' => null,
                            'has_phone' => false,
                        ],
                        [
                            'id' => $comment2->id,
                            'sentiment' => 'neutral',
                            'intent_tag' => 'Chốt đơn',
                            'question_tag' => null,
                            'product_tag' => null,
                            'has_phone' => false,
                        ],
                    ],
                ]);
        });

        // Mock TikTokService
        $this->mock(TikTokService::class);

        // 3. Run job
        $job = new AnalyzeCommentsJob($session->id);
        app()->call([$job, 'handle']);

        // 4. Assert database is updated correctly
        $this->assertDatabaseHas('live_events', [
            'id' => $comment1->id,
            'intent_tag' => 'Chốt đơn',
            'sentiment' => 'neutral',
            'ai_processed' => true,
        ]);

        $this->assertDatabaseHas('live_events', [
            'id' => $comment2->id,
            'intent_tag' => 'Chốt đơn',
            'sentiment' => 'neutral',
            'ai_processed' => true,
        ]);
    }

    public function test_system_prompts_contain_single_product_code_instructions(): void
    {
        // 1. Check CommentAnalyzer agent instructions
        $analyzer = new \App\Ai\Agents\CommentAnalyzer();
        $instructions = $analyzer->instructions();

        $this->assertStringContainsString('Mã 2', $instructions);
        $this->assertStringContainsString('Mã 2 ạ', $instructions);
        $this->assertStringContainsString('Chốt đơn', $instructions);
        $this->assertStringContainsString('KHÔNG phân loại các bình luận chỉ chứa mã đơn lẻ hoặc kèm kính ngữ như "Mã 2 ạ" thành "Hỏi thông tin"', $instructions);

        // 2. Check AnalyzeCommentsJob system prompt (via Reflection or calling handle with a mocked Runware service)
        $user = \App\Models\User::factory()->create();
        $session = LiveSession::create([
            'user_id' => $user->id,
            'name' => 'Test Session',
            'status' => 'live',
            'tiktok_username' => 'testuser',
        ]);

        $comment = LiveEvent::create([
            'live_session_id' => $session->id,
            'event_type' => 'comment',
            'event_at' => now(),
            'tiktok_user_id' => 'user_1',
            'data' => ['comment' => 'Mã 2'],
            'ai_processed' => false,
        ]);

        $this->mock(RunwareAiService::class, function ($mock) use ($comment) {
            $mock->shouldReceive('chatJson')
                ->once()
                ->withArgs(function ($systemPrompt, $userMessage) {
                    $hasMa2 = str_contains($systemPrompt, 'Mã 2');
                    $hasMa2A = str_contains($systemPrompt, 'Mã 2 ạ');
                    $hasNoHoiThongTinRule = str_contains($systemPrompt, 'KHÔNG phân loại các bình luận chỉ chứa mã đơn lẻ hoặc kèm kính ngữ như "Mã 2 ạ" thành "Hỏi thông tin"');
                    return $hasMa2 && $hasMa2A && $hasNoHoiThongTinRule;
                })
                ->andReturn([
                    'results' => [
                        [
                            'id' => $comment->id,
                            'sentiment' => 'neutral',
                            'intent_tag' => 'Chốt đơn',
                            'question_tag' => null,
                            'product_tag' => null,
                            'has_phone' => false,
                        ]
                    ]
                ]);
        });

        $this->mock(TikTokService::class);

        $job = new AnalyzeCommentsJob($session->id);
        app()->call([$job, 'handle']);
    }
}

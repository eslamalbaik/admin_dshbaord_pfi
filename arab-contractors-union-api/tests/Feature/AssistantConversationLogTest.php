<?php

namespace Tests\Feature;

use App\Models\AssistantConversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AssistantConversationLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_ai_answer_is_saved_to_the_conversation_log(): void
    {
        config(['services.gemini.key' => 'test-key']);
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [['content' => ['parts' => [['text' => 'أهلاً، أنا سامي.']]]]],
        ])]);

        $this->postJson('/api/v1/assistant/chat', ['message' => 'كم رسوم العضوية؟', 'session_id' => 'abc-123', 'page' => '/landing'])
            ->assertOk()->assertJson(['available' => true]);

        $conv = AssistantConversation::with('messages')->firstOrFail();
        $this->assertSame('/landing', $conv->page);
        $this->assertSame(2, $conv->messages_count);
        $this->assertSame(['user', 'bot'], $conv->messages->pluck('role')->all());
        $this->assertSame('ai', $conv->messages[1]->source);
        $this->assertNotSame('127.0.0.1', $conv->ip_hash);

        Http::assertSent(fn ($req) => str_contains($req['systemInstruction']['parts'][0]['text'], 'سامي'));
    }

    public function test_failed_ai_call_is_not_logged_by_the_server(): void
    {
        config(['services.gemini.key' => null]);
        $this->postJson('/api/v1/assistant/chat', ['message' => 'مرحبا', 'session_id' => 'abc-123'])->assertOk();
        $this->assertSame(0, AssistantConversation::count());
    }

    public function test_browser_answers_are_appended_to_the_same_conversation(): void
    {
        $this->postJson('/api/v1/assistant/log', ['session_id' => 's-1', 'messages' => [
            ['role' => 'user', 'text' => 'مرحبا', 'source' => null],
            ['role' => 'bot', 'text' => 'أهلاً بك', 'source' => 'smalltalk'],
        ]])->assertOk();
        $this->postJson('/api/v1/assistant/log', ['session_id' => 's-1', 'messages' => [
            ['role' => 'user', 'text' => 'سؤال غريب'],
            ['role' => 'bot', 'text' => 'لم أجد إجابة', 'source' => 'fallback'],
        ]])->assertOk();

        $this->assertSame(1, AssistantConversation::count());
        $this->assertSame(4, AssistantConversation::first()->messages_count);
    }

    public function test_log_rejects_bad_input(): void
    {
        $this->postJson('/api/v1/assistant/log', ['session_id' => 'bad id!', 'messages' => [['role' => 'user', 'text' => 'x']]])->assertStatus(422);
        $this->postJson('/api/v1/assistant/log', ['session_id' => 's-1', 'messages' => [['role' => 'bot', 'text' => 'x', 'source' => 'ai']]])->assertStatus(422);
    }

    public function test_admin_lists_reads_and_deletes_conversations(): void
    {
        $this->postJson('/api/v1/assistant/log', ['session_id' => 's-1', 'messages' => [
            ['role' => 'user', 'text' => 'كيف أجدد العضوية؟'],
            ['role' => 'bot', 'text' => 'من بوابة المقاولين', 'source' => 'faq'],
        ]]);
        $this->postJson('/api/v1/assistant/log', ['session_id' => 's-2', 'messages' => [
            ['role' => 'user', 'text' => 'شو الطقس؟'],
            ['role' => 'bot', 'text' => 'لم أجد إجابة', 'source' => 'fallback'],
        ]]);

        $this->getJson('/api/v1/dashboard/assistant-conversations')->assertStatus(401);

        Sanctum::actingAs(User::factory()->create(['role' => 'admin']), ['*']);

        $this->getJson('/api/v1/dashboard/assistant-conversations')
            ->assertOk()->assertJsonPath('meta.total', 2);
        $this->getJson('/api/v1/dashboard/assistant-conversations?search=تجدد')
            ->assertOk()->assertJsonPath('meta.total', 0);
        $list = $this->getJson('/api/v1/dashboard/assistant-conversations?search=أجدد')
            ->assertOk()->assertJsonPath('meta.total', 1)
            ->assertJsonPath('items.0.first_question', 'كيف أجدد العضوية؟')
            ->assertJsonMissingPath('items.0.ip_hash');
        $this->getJson('/api/v1/dashboard/assistant-conversations?source=fallback')
            ->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('items.0.unanswered_count', 1);

        $id = $list->json('items.0.id');
        $this->getJson("/api/v1/dashboard/assistant-conversations/{$id}")
            ->assertOk()->assertJsonCount(2, 'messages')->assertJsonPath('messages.1.source', 'faq');

        $this->deleteJson("/api/v1/dashboard/assistant-conversations/{$id}")->assertOk();
        $this->assertSame(1, AssistantConversation::count());
    }
}

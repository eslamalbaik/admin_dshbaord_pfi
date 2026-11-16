<?php

namespace Tests\Feature;

use App\Models\Faq;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AssistantChatTest extends TestCase
{
    use RefreshDatabase;

    public function test_without_key_it_reports_unavailable_so_the_ui_falls_back(): void
    {
        config(['services.gemini.key' => null]);

        $this->postJson('/api/v1/assistant/chat', ['message' => 'مرحبا'])
            ->assertOk()->assertJson(['available' => false]);
    }

    public function test_returns_the_model_reply_and_sends_faqs_as_context(): void
    {
        config(['services.gemini.key' => 'test-key']);
        Faq::query()->delete();
        Faq::create(['question' => 'ما رسوم العضوية؟', 'answer' => 'تختلف حسب الدرجة.', 'is_active' => true]);
        Faq::create(['question' => 'سؤال مخفي', 'answer' => 'سر', 'is_active' => false]);

        Http::fake(['generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [['content' => ['parts' => [['text' => 'تختلف الرسوم حسب الدرجة.']]]]],
        ])]);

        $this->postJson('/api/v1/assistant/chat', [
            'message' => 'كم الرسوم؟',
            'history' => [['role' => 'bot', 'text' => 'مرحباً'], ['role' => 'user', 'text' => 'مرحبا']],
        ])->assertOk()->assertJson(['available' => true, 'reply' => 'تختلف الرسوم حسب الدرجة.']);

        Http::assertSent(function ($req) {
            $sys = $req['systemInstruction']['parts'][0]['text'];
            $contents = $req['contents'];

            return $req->hasHeader('x-goog-api-key', 'test-key')
                && str_contains($sys, 'ما رسوم العضوية؟')
                && ! str_contains($sys, 'سؤال مخفي')
                && $contents[0]['role'] === 'user'
                && $contents[count($contents) - 1]['parts'][0]['text'] === 'كم الرسوم؟';
        });
    }

    public function test_provider_failure_falls_back_instead_of_erroring(): void
    {
        config(['services.gemini.key' => 'test-key']);
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['error' => 'quota'], 429)]);

        $this->postJson('/api/v1/assistant/chat', ['message' => 'سؤال'])
            ->assertOk()->assertJson(['available' => false]);
    }

    public function test_message_is_validated(): void
    {
        $this->postJson('/api/v1/assistant/chat', ['message' => ''])->assertStatus(422);
        $this->postJson('/api/v1/assistant/chat', ['message' => str_repeat('ا', 301)])->assertStatus(422);
    }
}

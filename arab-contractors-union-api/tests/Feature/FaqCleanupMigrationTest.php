<?php

namespace Tests\Feature;

use App\Models\Faq;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FaqCleanupMigrationTest extends TestCase
{
    use RefreshDatabase;

    private function runCleanup(): void
    {
        (require base_path('database/migrations/2026_11_16_000003_remove_legacy_academy_faqs.php'))->up();
    }

    public function test_legacy_academy_faqs_are_removed_and_union_faqs_seeded(): void
    {
        Faq::query()->delete();
        Faq::create(['question' => 'ما هي منصة بروميتريكا أكاديمي؟', 'answer' => 'x', 'is_active' => true]);
        Faq::create(['question' => 'ما هي طرق الدفع المتاحة؟', 'answer' => 'x', 'is_active' => true]);

        $this->runCleanup();

        $this->assertSame(0, Faq::where('question', 'like', '%بروميتريكا%')->count());
        $this->assertGreaterThan(5, Faq::count());
        $this->assertTrue(Faq::where('question', 'like', '%رسوم العضوية%')->exists());
    }

    public function test_custom_admin_faqs_are_kept(): void
    {
        Faq::query()->delete();
        Faq::create(['question' => 'ما هي منصة بروميتريكا أكاديمي؟', 'answer' => 'x', 'is_active' => true]);
        Faq::create(['question' => 'سؤال أضافه الأدمن', 'answer' => 'جواب', 'is_active' => true]);

        $this->runCleanup();

        $this->assertTrue(Faq::where('question', 'سؤال أضافه الأدمن')->exists());
        $this->assertSame(1, Faq::count());
    }

    public function test_public_endpoint_returns_active_faqs_only(): void
    {
        Faq::query()->delete();
        Faq::create(['question' => 'مفعّل', 'answer' => 'a', 'is_active' => true]);
        Faq::create(['question' => 'مخفي', 'answer' => 'b', 'is_active' => false]);

        $this->getJson('/api/v1/faqs')->assertOk()->assertJsonCount(1)->assertJsonPath('0.question', 'مفعّل');
    }
}

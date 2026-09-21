<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AnnouncementAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    private function actingAsAdmin(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']), ['*']);
    }

    // ─────────────────────────────────────────────────────────────────────
    //  published_at — must be today or later on create (REQ-12 #1)
    // ─────────────────────────────────────────────────────────────────────

    public function test_store_rejects_published_at_in_the_past(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/v1/admin/announcements', [
            'title'        => 'تعميم بتاريخ ماضٍ',
            'body'         => 'نص التعميم',
            'published_at' => now()->subDay()->toDateString(),
        ])->assertStatus(422)->assertJsonValidationErrors(['published_at']);

        $this->assertDatabaseCount('announcements', 0);
    }

    public function test_store_accepts_published_at_today(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/v1/admin/announcements', [
            'title'        => 'تعميم بتاريخ اليوم',
            'body'         => 'نص التعميم',
            'is_published' => true,
            'published_at' => now()->toDateString(),
        ])->assertStatus(201);

        $this->assertDatabaseCount('announcements', 1);
    }

    // ─────────────────────────────────────────────────────────────────────
    //  auto-numbering
    // ─────────────────────────────────────────────────────────────────────

    public function test_store_auto_generates_number_when_not_given(): void
    {
        $this->actingAsAdmin();

        $response = $this->postJson('/api/v1/admin/announcements', [
            'title' => 'تعميم بلا رقم', 'body' => 'نص',
        ]);

        $response->assertStatus(201);
        $this->assertMatchesRegularExpression('/^\d{4}\/1$/', $response->json('items.number'));
    }

    public function test_store_increments_sequence_within_same_year(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/v1/admin/announcements', ['title' => 'الأول', 'body' => 'نص']);
        $response = $this->postJson('/api/v1/admin/announcements', ['title' => 'الثاني', 'body' => 'نص']);

        $this->assertSame(now()->year . '/2', $response->json('items.number'));
    }

    // ─────────────────────────────────────────────────────────────────────
    //  effective_status — a future-dated published announcement isn't "published" yet
    // ─────────────────────────────────────────────────────────────────────

    public function test_scheduled_announcement_not_shown_in_public_published_list(): void
    {
        Announcement::create([
            'title' => 'مجدول', 'body' => 'نص',
            'is_published' => true, 'published_at' => now()->addDay(),
        ]);
        $past = Announcement::create([
            'title' => 'منشور فعلاً', 'body' => 'نص',
            'is_published' => true, 'published_at' => now()->subHour(),
        ]);

        $response = $this->getJson('/api/v1/announcements');

        $response->assertStatus(200)->assertJsonCount(1, 'items');
        $this->assertSame($past->id, $response->json('items.0.id'));
    }

    public function test_effective_status_reflects_draft_scheduled_published(): void
    {
        $draft = Announcement::create(['title' => 'مسودة', 'body' => 'نص', 'is_published' => false]);
        $scheduled = Announcement::create(['title' => 'مجدول', 'body' => 'نص', 'is_published' => true, 'published_at' => now()->addDay()]);
        $published = Announcement::create(['title' => 'منشور', 'body' => 'نص', 'is_published' => true, 'published_at' => now()->subHour()]);

        $this->assertSame('draft', $draft->effective_status);
        $this->assertSame('scheduled', $scheduled->effective_status);
        $this->assertSame('published', $published->effective_status);
    }

    // ─────────────────────────────────────────────────────────────────────
    //  image + attachment upload
    // ─────────────────────────────────────────────────────────────────────

    public function test_store_persists_image_and_attachment(): void
    {
        $this->actingAsAdmin();

        $response = $this->post('/api/v1/admin/announcements', [
            'title'      => 'تعميم بمرفقات',
            'body'       => 'نص',
            'image'      => UploadedFile::fake()->image('a.jpg'),
            'attachment' => UploadedFile::fake()->create('a.pdf', 100, 'application/pdf'),
        ]);

        $response->assertStatus(201);
        $this->assertNotNull($response->json('items.image'));
        $this->assertNotNull($response->json('items.attachment'));
    }

    public function test_store_requires_auth(): void
    {
        $this->postJson('/api/v1/admin/announcements', ['title' => 'x', 'body' => 'y'])->assertStatus(401);
    }
}

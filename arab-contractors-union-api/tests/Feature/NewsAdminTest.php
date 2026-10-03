<?php

namespace Tests\Feature;

use App\Models\News;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NewsAdminTest extends TestCase
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
    //  published_at — today or later on create (REQ-10 #2)
    // ─────────────────────────────────────────────────────────────────────

    public function test_store_rejects_published_at_in_the_past(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/v1/admin/news', [
            'title' => 'خبر بتاريخ ماضٍ', 'body' => 'نص',
            'published_at' => now()->subDay()->toDateString(),
        ])->assertStatus(422)->assertJsonValidationErrors(['published_at']);

        $this->assertDatabaseCount('news', 0);
    }

    public function test_update_allows_past_published_at_unchanged(): void
    {
        $this->actingAsAdmin();

        $news = News::create([
            'title' => 'خبر قديم', 'slug' => 'old-news', 'body' => 'نص',
            'is_published' => true, 'published_at' => now()->subMonth(),
        ]);

        $this->putJson("/api/v1/admin/news/{$news->id}", [
            'title' => 'خبر قديم (معدّل)', 'body' => 'نص',
            'published_at' => $news->published_at->toDateString(),
        ])->assertStatus(200);
    }

    public function test_update_rejects_changing_published_at_to_yesterday(): void
    {
        $this->actingAsAdmin();

        $news = News::create([
            'title' => 'خبر', 'slug' => 'news-y', 'body' => 'نص',
            'is_published' => true, 'published_at' => now()->addWeek(),
        ]);

        $this->putJson("/api/v1/admin/news/{$news->id}", [
            'published_at' => now(config('app.local_timezone'))->subDay()->toDateString(),
        ])->assertStatus(422)->assertJsonValidationErrors(['published_at']);
    }

    public function test_update_allows_changing_published_at_to_today_or_later(): void
    {
        $this->actingAsAdmin();

        $news = News::create([
            'title' => 'خبر', 'slug' => 'news-z', 'body' => 'نص',
            'is_published' => true, 'published_at' => now()->addWeek(),
        ]);

        $tomorrow = now(config('app.local_timezone'))->addDay()->toDateString();

        $this->putJson("/api/v1/admin/news/{$news->id}", ['published_at' => $tomorrow])
            ->assertStatus(200);

        $this->assertSame($tomorrow, $news->fresh()->published_at->toDateString());
    }

    public function test_update_keeps_slug_when_title_unchanged(): void
    {
        $this->actingAsAdmin();

        $news = News::create([
            'title' => 'خبر ثابت', 'slug' => News::generateSlug('خبر ثابت'), 'body' => 'نص',
            'is_published' => true, 'published_at' => now(),
        ]);
        $slug = $news->slug;

        $this->putJson("/api/v1/admin/news/{$news->id}", ['title' => 'خبر ثابت'])->assertStatus(200);

        $this->assertSame($slug, $news->fresh()->slug);
    }

    public function test_store_reuses_title_of_soft_deleted_news(): void
    {
        $this->actingAsAdmin();

        $old = News::create(['title' => 'خبر مكرر', 'slug' => News::generateSlug('خبر مكرر'), 'body' => 'نص']);
        $old->delete();

        $this->postJson('/api/v1/admin/news', ['title' => 'خبر مكرر', 'body' => 'نص'])->assertStatus(201);
    }

    public function test_update_publish_now_moves_scheduled_news_to_now(): void
    {
        $this->actingAsAdmin();

        $news = News::create([
            'title' => 'خبر مجدول', 'slug' => 'news-s', 'body' => 'نص',
            'is_published' => true, 'published_at' => now()->addWeek(),
        ]);

        $this->putJson("/api/v1/admin/news/{$news->id}", ['is_published' => true, 'publish_now' => true])
            ->assertStatus(200);

        $this->assertTrue($news->fresh()->published_at->lte(now()));
    }

    // ─────────────────────────────────────────────────────────────────────
    //  gallery — max 5 images total (REQ-10 #3)
    // ─────────────────────────────────────────────────────────────────────

    public function test_store_rejects_more_than_five_gallery_images(): void
    {
        $this->actingAsAdmin();

        $this->post('/api/v1/admin/news', [
            'title' => 'خبر بصور كثيرة', 'body' => 'نص',
            'gallery' => array_map(fn ($i) => UploadedFile::fake()->image("g{$i}.jpg"), range(1, 6)),
        ])->assertStatus(422)->assertJsonValidationErrors(['gallery']);
    }

    public function test_store_accepts_exactly_five_gallery_images(): void
    {
        $this->actingAsAdmin();

        $this->post('/api/v1/admin/news', [
            'title' => 'خبر بخمس صور', 'body' => 'نص',
            'gallery' => array_map(fn ($i) => UploadedFile::fake()->image("g{$i}.jpg"), range(1, 5)),
        ])->assertStatus(201);
    }

    public function test_update_rejects_when_existing_plus_new_exceeds_five(): void
    {
        $this->actingAsAdmin();

        $news = News::create([
            'title' => 'خبر بمعرض', 'slug' => 'gallery-news', 'body' => 'نص',
            'gallery' => ['news/gallery/a.jpg', 'news/gallery/b.jpg', 'news/gallery/c.jpg'],
        ]);

        $response = $this->post("/api/v1/admin/news/{$news->id}", [
            '_method' => 'PUT',
            'title' => 'خبر بمعرض', 'body' => 'نص',
            'gallery' => array_map(fn ($i) => UploadedFile::fake()->image("n{$i}.jpg"), range(1, 3)),
        ]);

        $response->assertStatus(422);
        $this->assertCount(3, $news->fresh()->gallery);
    }

    public function test_store_requires_auth(): void
    {
        $this->postJson('/api/v1/admin/news', ['title' => 'x', 'body' => 'y'])->assertStatus(401);
    }
}

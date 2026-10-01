<?php

namespace Tests\Feature;

use App\Models\News;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NewsShowTest extends TestCase
{
    use RefreshDatabase;

    private function createNews(array $attrs = []): News
    {
        return News::create(array_merge([
            'title'        => 'خبر تجريبي',
            'slug'         => 'demo-news',
            'body'         => 'نص الخبر',
            'is_published' => true,
            'published_at' => now(),
        ], $attrs));
    }

    public function test_news_can_be_fetched_by_id(): void
    {
        $news = $this->createNews();

        $this->getJson("/api/v1/news/{$news->id}")
            ->assertOk()
            ->assertJsonPath('items.id', $news->id)
            ->assertJsonPath('items.slug', 'demo-news');
    }

    public function test_news_can_still_be_fetched_by_slug(): void
    {
        $news = $this->createNews();

        $this->getJson('/api/v1/news/demo-news')
            ->assertOk()
            ->assertJsonPath('items.id', $news->id);
    }

    public function test_latest_route_is_not_shadowed_by_the_detail_route(): void
    {
        $this->createNews();

        $this->getJson('/api/v1/news/latest')
            ->assertOk()
            ->assertJsonCount(1, 'items');
    }

    public function test_unpublished_news_is_not_exposed_by_id(): void
    {
        $news = $this->createNews(['is_published' => false, 'published_at' => null]);

        $this->getJson("/api/v1/news/{$news->id}")->assertStatus(404);
    }

    public function test_unknown_id_returns_404(): void
    {
        $this->getJson('/api/v1/news/999999')->assertStatus(404);
    }
}

<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiteVideoViewsTest extends TestCase
{
    use RefreshDatabase;

    public function test_views_start_at_zero_and_increment(): void
    {
        $this->getJson('/api/v1/site-videos/login-guide')->assertOk()->assertJsonPath('data.views', 0);

        $this->postJson('/api/v1/site-videos/login-guide/view')->assertOk()->assertJsonPath('data.views', 1);
        $this->postJson('/api/v1/site-videos/login-guide/view')->assertOk()->assertJsonPath('data.views', 2);

        $this->getJson('/api/v1/site-videos/login-guide')->assertOk()->assertJsonPath('data.views', 2);
    }

    public function test_unknown_video_is_404(): void
    {
        $this->getJson('/api/v1/site-videos/anything')->assertNotFound();
        $this->postJson('/api/v1/site-videos/anything/view')->assertNotFound();
        $this->assertDatabaseCount('site_video_views', 0);
    }

    public function test_view_endpoint_is_rate_limited(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/api/v1/site-videos/login-guide/view')->assertOk();
        }
        $this->postJson('/api/v1/site-videos/login-guide/view')->assertStatus(429);
        $this->assertDatabaseHas('site_video_views', ['video_key' => 'login-guide', 'views' => 10]);
    }
}

<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ServiceIconUploadTest extends TestCase
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

    private function uploadIcon(): string
    {
        return $this->post('/api/v1/dashboard/settings/service-icon', [
            'icon' => UploadedFile::fake()->image('icon.png', 64, 64),
        ])->assertOk()->json('items.path');
    }

    private function saveServices(array $services): void
    {
        $this->putJson('/api/v1/dashboard/settings', ['settings' => [
            ['key' => 'union_services', 'value' => json_encode($services, JSON_UNESCAPED_UNICODE), 'group' => 'about'],
        ]])->assertOk();
    }

    public function test_upload_stores_image_and_returns_path(): void
    {
        $this->actingAsAdmin();

        $path = $this->uploadIcon();

        $this->assertStringStartsWith('union/services/', $path);
        Storage::disk('public')->assertExists($path);
    }

    public function test_upload_rejects_non_image(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/v1/dashboard/settings/service-icon', [
            'icon' => UploadedFile::fake()->create('doc.pdf', 10, 'application/pdf'),
        ])->assertStatus(422)->assertJsonValidationErrors(['icon']);
    }

    public function test_about_returns_icon_url_only_for_uploaded_icons(): void
    {
        $this->actingAsAdmin();
        $path = $this->uploadIcon();

        $this->saveServices([
            ['title' => 'تنظيم المهنة', 'description' => 'وصف', 'icon' => $path],
            ['title' => 'خدمة قديمة', 'description' => 'وصف', 'icon' => 'ب'],
        ]);

        $services = $this->getJson('/api/v1/app/about')->assertOk()->json('items.services');

        $this->assertSame(Storage::disk('public')->url($path), $services[0]['icon_url']);
        $this->assertNull($services[1]['icon_url']);
    }

    public function test_replacing_or_removing_icon_deletes_old_file(): void
    {
        $this->actingAsAdmin();
        $old = $this->uploadIcon();
        $kept = $this->uploadIcon();

        $this->saveServices([
            ['title' => 'أ', 'description' => '', 'icon' => $old],
            ['title' => 'ب', 'description' => '', 'icon' => $kept],
        ]);

        $new = $this->uploadIcon();
        $this->saveServices([
            ['title' => 'أ', 'description' => '', 'icon' => $new],
            ['title' => 'ب', 'description' => '', 'icon' => $kept],
        ]);

        Storage::disk('public')->assertMissing($old);
        Storage::disk('public')->assertExists($new);
        Storage::disk('public')->assertExists($kept);
    }

    public function test_long_services_json_is_accepted(): void
    {
        $this->actingAsAdmin();

        $services = array_fill(0, 12, ['title' => 'خدمة', 'description' => str_repeat('وصف طويل ', 25), 'icon' => '']);
        $this->saveServices($services);

        $this->assertCount(12, json_decode(Setting::get('union_services'), true));
    }
}

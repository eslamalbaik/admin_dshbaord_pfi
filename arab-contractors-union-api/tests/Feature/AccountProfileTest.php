<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * الملف الشخصي لمستخدمي لوحة التحكم: عرض، تعديل الاسم والصورة، تغيير كلمة السر.
 */
class AccountProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_sees_own_profile_details(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'last_login_at' => now()->subHour()]);
        Sanctum::actingAs($admin, ['*']);

        $this->getJson('/api/v1/account/profile')
            ->assertOk()
            ->assertJsonPath('data.email', $admin->email)
            ->assertJsonPath('data.role_label', 'مدير النظام')
            ->assertJsonPath('data.last_login_at', $admin->last_login_at->toISOString());
    }

    public function test_supervisor_without_permissions_can_use_profile(): void
    {
        Storage::fake('public');
        $sup = User::factory()->create(['role' => 'supervisor', 'permissions' => [], 'is_active' => true]);
        Sanctum::actingAs($sup, ['*']);

        $this->getJson('/api/v1/account/profile')->assertOk()->assertJsonPath('data.role_label', 'مشرف');

        $res = $this->post('/api/v1/account/profile', [
            'name'   => '  مشرف جديد  ',
            'avatar' => UploadedFile::fake()->image('me.png', 200, 200),
        ], ['Accept' => 'application/json'])->assertOk();

        $sup->refresh();
        $this->assertSame('مشرف جديد', $sup->name);
        Storage::disk('public')->assertExists($sup->avatar);
        $res->assertJsonPath('data.avatar', $sup->avatar);
    }

    public function test_avatar_validation_rejects_big_or_non_image_files(): void
    {
        Storage::fake('public');
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']), ['*']);

        $this->post('/api/v1/account/profile', [
            'name'   => 'الاسم',
            'avatar' => UploadedFile::fake()->create('a.pdf', 100, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertStatus(422)->assertJsonValidationErrors('avatar');

        $this->post('/api/v1/account/profile', [
            'name'   => 'الاسم',
            'avatar' => UploadedFile::fake()->image('big.jpg')->size(3000),
        ], ['Accept' => 'application/json'])->assertStatus(422)->assertJsonValidationErrors('avatar');

        $this->post('/api/v1/account/profile', ['name' => ''], ['Accept' => 'application/json'])
            ->assertStatus(422)->assertJsonValidationErrors('name');
    }

    public function test_remove_avatar_deletes_old_file(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('avatars/old.png', 'x');
        $admin = User::factory()->create(['role' => 'admin', 'avatar' => 'avatars/old.png']);
        Sanctum::actingAs($admin, ['*']);

        $this->postJson('/api/v1/account/profile', ['name' => 'الأدمن', 'remove_avatar' => true])->assertOk();

        $this->assertNull($admin->fresh()->avatar);
        Storage::disk('public')->assertMissing('avatars/old.png');
    }

    public function test_password_change_requires_correct_current_password(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'password' => 'old-password-1']);
        Sanctum::actingAs($admin, ['*']);

        $this->postJson('/api/v1/account/password', [
            'current_password'      => 'wrong',
            'password'              => 'new-password-1',
            'password_confirmation' => 'new-password-1',
        ])->assertStatus(422)->assertJsonValidationErrors('current_password');

        $this->postJson('/api/v1/account/password', [
            'current_password'      => 'old-password-1',
            'password'              => 'new-password-1',
            'password_confirmation' => 'mismatch-123',
        ])->assertStatus(422)->assertJsonValidationErrors('password');

        $this->postJson('/api/v1/account/password', [
            'current_password'      => 'old-password-1',
            'password'              => 'new-password-1',
            'password_confirmation' => 'new-password-1',
        ])->assertOk();

        $this->assertTrue(Hash::check('new-password-1', $admin->fresh()->password));
    }

    public function test_password_change_logs_out_other_devices_only(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'password' => 'old-password-1']);
        $admin->createToken('other-device');
        $current = $admin->createToken('this-device')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$current}")
            ->postJson('/api/v1/account/password', [
                'current_password'      => 'old-password-1',
                'password'              => 'new-password-1',
                'password_confirmation' => 'new-password-1',
            ])->assertOk();

        $this->assertSame(['this-device'], $admin->tokens()->pluck('name')->all());
    }

    public function test_login_records_last_login_at(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'email' => 'a@pcu.test', 'password' => 'secret-123']);

        $this->postJson('/api/v1/auth/login', ['email' => 'a@pcu.test', 'password' => 'secret-123'])->assertOk();

        $this->assertNotNull($admin->fresh()->last_login_at);
    }

    public function test_migration_renames_generic_admin_name(): void
    {
        $generic = User::factory()->create(['role' => 'admin', 'name' => 'Admin']);
        $custom  = User::factory()->create(['role' => 'admin', 'name' => 'أحمد']);
        $acct    = User::factory()->create(['role' => 'accountant', 'name' => 'Admin']);

        $migration = require database_path('migrations/2026_10_14_000001_add_last_login_at_to_users_and_rename_admin.php');
        $migration->up();

        $this->assertSame('اتحاد المقاولين الفلسطينيين', $generic->fresh()->name);
        $this->assertSame('أحمد', $custom->fresh()->name);
        $this->assertSame('Admin', $acct->fresh()->name);
    }
}

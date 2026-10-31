<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * إشعارات لوحة التحكم (طلبات الشهادات، تذاكر الدعم…) لا تُعرض إلا لمستخدم مسجّل —
 * زائر الصفحة العامة بلا توكن يجب أن يأخذ 401 ولا يرى أي بيانات.
 */
class AdminNotificationsAuthTest extends TestCase
{
    use RefreshDatabase;

    public static function protectedEndpoints(): array
    {
        return [
            'admin list'             => ['get', '/api/v1/notifications'],
            'admin unread count'     => ['get', '/api/v1/notifications/unread-count'],
            'admin mark all read'    => ['post', '/api/v1/notifications/read'],
            'contractor list'        => ['get', '/api/v1/contractor/auth/notifications'],
            'contractor unread'      => ['get', '/api/v1/contractor/auth/notifications/unread-count'],
            'broadcast channel auth' => ['post', '/api/broadcasting/auth'],
        ];
    }

    #[DataProvider('protectedEndpoints')]
    public function test_guest_cannot_read_notifications(string $method, string $uri): void
    {
        $this->json($method, $uri)->assertStatus(401);
    }

    public function test_guest_cannot_join_admin_private_channel(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->postJson('/api/broadcasting/auth', [
            'socket_id'    => '1234.5678',
            'channel_name' => 'private-App.Models.User.'.$admin->id,
        ])->assertStatus(401);
    }

    public function test_admin_sees_only_own_notifications(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $other = User::factory()->create(['role' => 'admin']);

        foreach ([$admin, $other] as $user) {
            DatabaseNotification::create([
                'id'              => (string) Str::uuid(),
                'type'            => 'App\\Notifications\\Test',
                'notifiable_type' => $user->getMorphClass(),
                'notifiable_id'   => $user->id,
                'data'            => ['message' => 'for '.$user->id],
            ]);
        }

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/v1/notifications')->assertOk();

        $this->assertSame(1, $response->json('items.notifications.total'));
    }
}

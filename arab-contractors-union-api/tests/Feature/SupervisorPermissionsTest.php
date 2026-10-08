<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\User;
use App\Support\DashboardPermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * صلاحيات لوحة التحكم: الأدمن بينشئ مشرفين ويعطيهم صلاحيات حسب الأقسام الداخلية،
 * والفرض بيصير بالباك إند على كل route. الأدمن والمحاسب ما بيتأثروا.
 */
class SupervisorPermissionsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function supervisor(array $permissions, array $attrs = []): User
    {
        return User::factory()->create(array_merge([
            'role'        => 'supervisor',
            'permissions' => $permissions,
            'is_active'   => true,
        ], $attrs));
    }

    // ── إدارة المشرفين ──

    public function test_admin_creates_supervisor_with_sanitized_permissions_and_critical_log(): void
    {
        Sanctum::actingAs($this->admin(), ['*']);

        $res = $this->postJson('/api/v1/dashboard/supervisors', [
            'name'        => 'مشرف المالية',
            'email'       => 'Finance@Example.com',
            'password'    => 'secret-123',
            // تعديل بدون عرض → العرض بينضاف تلقائياً، والمفتاح المجهول بينشال
            'permissions' => ['finance.dues.update', 'unknown.section.view', 'tenders.list.view'],
        ])->assertCreated();

        $res->assertJsonPath('items.email', 'finance@example.com');
        $this->assertEqualsCanonicalizing(
            ['finance.dues.view', 'finance.dues.update', 'tenders.list.view'],
            $res->json('items.permissions')
        );

        $user = User::where('email', 'finance@example.com')->sole();
        $this->assertSame('supervisor', $user->role);
        $this->assertTrue(Hash::check('secret-123', $user->password));

        $log = ActivityLog::where('action', 'supervisor.created')->sole();
        $this->assertTrue($log->is_critical);
        $this->assertStringContainsString('الذمم المالية: عرض، تعديل', $log->meta['after']['permissions']);
    }

    public function test_permission_update_is_logged_with_before_and_after(): void
    {
        Sanctum::actingAs($this->admin(), ['*']);
        $sup = $this->supervisor(['tenders.list.view']);

        $this->putJson("/api/v1/dashboard/supervisors/{$sup->id}", [
            'permissions' => ['tenders.list.view', 'tenders.list.delete'],
        ])->assertOk();

        $log = ActivityLog::where('action', 'supervisor.updated')->sole();
        $this->assertSame('قائمة العطاءات: عرض', $log->meta['before']['permissions']);
        $this->assertSame('قائمة العطاءات: عرض، حذف', $log->meta['after']['permissions']);
    }

    public function test_supervisor_endpoints_only_touch_supervisor_accounts(): void
    {
        Sanctum::actingAs($this->admin(), ['*']);
        $otherAdmin = $this->admin();
        $accountant = User::factory()->create(['role' => 'accountant']);

        $this->putJson("/api/v1/dashboard/supervisors/{$otherAdmin->id}", ['name' => 'x'])->assertNotFound();
        $this->patchJson("/api/v1/dashboard/supervisors/{$accountant->id}/status", ['is_active' => false])->assertNotFound();
        $this->postJson("/api/v1/dashboard/supervisors/{$otherAdmin->id}/reset-password", ['password' => 'newpass-123'])->assertNotFound();

        $this->assertSame('admin', $otherAdmin->fresh()->role);
        $this->assertNotFalse($accountant->fresh()->is_active);
    }

    public function test_supervisor_cannot_manage_supervisors_even_with_all_permissions(): void
    {
        Sanctum::actingAs($this->supervisor(DashboardPermissions::all()), ['*']);

        $this->getJson('/api/v1/dashboard/supervisors')->assertForbidden();
        $this->postJson('/api/v1/dashboard/supervisors', [
            'name' => 'x', 'email' => 'x@example.com', 'password' => 'secret-123', 'permissions' => [],
        ])->assertForbidden();
    }

    public function test_deactivating_supervisor_revokes_tokens_and_blocks_login(): void
    {
        $sup = $this->supervisor(['tenders.list.view'], ['email' => 'sup@example.com', 'password' => 'secret-123']);
        $sup->createToken('t');

        Sanctum::actingAs($this->admin(), ['*']);
        $this->patchJson("/api/v1/dashboard/supervisors/{$sup->id}/status", ['is_active' => false])->assertOk();

        $this->assertSame(0, $sup->tokens()->count());
        $this->assertSame(1, ActivityLog::where('action', 'supervisor.status_changed')->count());

        // تسجيل دخول جديد كزائر (actingAs فوق حوّل الـ guard الافتراضي لـ sanctum)
        \Illuminate\Support\Facades\Auth::shouldUse('web');
        $this->postJson('/api/v1/auth/login', ['email' => 'sup@example.com', 'password' => 'secret-123'])
            ->assertForbidden();
    }

    public function test_inactive_supervisor_is_rejected_on_every_request(): void
    {
        Sanctum::actingAs($this->supervisor(['tenders.list.view'], ['is_active' => false]), ['*']);

        $this->getJson('/api/v1/tenders')->assertForbidden()->assertJsonPath('force_logout', true);
    }

    public function test_reset_password(): void
    {
        $sup = $this->supervisor([]);
        Sanctum::actingAs($this->admin(), ['*']);

        $this->postJson("/api/v1/dashboard/supervisors/{$sup->id}/reset-password", ['password' => 'brand-new-1'])->assertOk();

        $this->assertTrue(Hash::check('brand-new-1', $sup->fresh()->password));
        $this->assertSame(1, ActivityLog::where('action', 'supervisor.password_reset')->where('is_critical', true)->count());
    }

    // ── الفرض على الـ routes ──

    public function test_supervisor_access_follows_section_and_action(): void
    {
        Sanctum::actingAs($this->supervisor(['tenders.list.view']), ['*']);

        $this->getJson('/api/v1/tenders')->assertOk();
        // تصنيفات العطاءات كقائمة اختيار مسموحة لمن عنده عرض العطاءات
        $this->getJson('/api/v1/tender-categories')->assertOk();
        // بس إضافة تصنيف لأ
        $this->postJson('/api/v1/tender-categories', ['name' => 'x'])->assertForbidden();
        // ولا إضافة عطاء (ما عنده create)
        $this->postJson('/api/v1/tenders', [])->assertForbidden();
        // قسم تاني بالكامل
        $this->getJson('/api/v1/admin/news')->assertForbidden();
    }

    public function test_supervisor_with_permission_passes_existing_role_admin_routes(): void
    {
        Sanctum::actingAs($this->supervisor(['settings.activity_log.view', 'services.support_tickets.view']), ['*']);

        $this->getJson('/api/v1/dashboard/activity-logs')->assertOk();
        $this->getJson('/api/v1/dashboard/support-tickets')->assertOk();
        $this->getJson('/api/v1/dashboard/settings')->assertForbidden();
        $this->getJson('/api/v1/dashboard/dues')->assertForbidden();
    }

    public function test_supervisor_with_dues_permission_passes_admin_accountant_routes(): void
    {
        Sanctum::actingAs($this->supervisor(['finance.dues.view']), ['*']);

        $this->getJson('/api/v1/dashboard/dues')->assertOk();
        $this->getJson('/api/v1/dashboard/exchange-rates')->assertOk();
        $this->postJson('/api/v1/dashboard/exchange-rates', [])->assertForbidden();
        $this->postJson('/api/v1/dashboard/dues/bulk-delete', ['ids' => [1]])->assertForbidden();
    }

    public function test_supervisor_without_permissions_only_reaches_personal_routes(): void
    {
        Sanctum::actingAs($this->supervisor([]), ['*']);

        $this->getJson('/api/v1/user')->assertOk()->assertJsonPath('permissions', []);
        $this->getJson('/api/v1/notifications')->assertOk();
        $this->getJson('/api/v1/dashboard/stats')->assertForbidden();
        $this->getJson('/api/v1/contractors')->assertForbidden();
        $this->getJson('/api/v1/reports/summary')->assertForbidden();
    }

    public function test_every_admin_route_is_guarded_for_supervisors(): void
    {
        // أي route جديد بلوحة التحكم لازم يكون عليه perm:... أو يكون أدمن فقط —
        // وإلا RestrictSupervisor بيقفله على المشرفين (هاد الاختبار بيذكّر بالسبب).
        $personal = ['api/v1/user', 'api/v1/auth/me', 'api/v1/auth/logout', 'api/v1/notifications',
            'api/v1/notifications/unread-count', 'api/v1/notifications/read', 'api/v1/notifications/{id}/mark-as-read'];

        $unguarded = collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn ($r) => in_array('supervisor.scope', $r->gatherMiddleware(), true))
            ->reject(fn ($r) => in_array($r->uri(), $personal, true))
            ->reject(fn ($r) => str_starts_with($r->uri(), 'api/v1/dashboard/supervisors'))
            ->reject(fn ($r) => collect($r->gatherMiddleware())->contains(fn ($m) => is_string($m) && str_starts_with($m, 'perm:')))
            ->map(fn ($r) => implode('|', $r->methods()) . ' ' . $r->uri())
            ->values()
            ->all();

        $this->assertSame([], $unguarded);
    }

    public function test_every_perm_middleware_references_a_defined_section(): void
    {
        $leaves = DashboardPermissions::leaves();

        foreach (app('router')->getRoutes()->getRoutes() as $route) {
            foreach ($route->gatherMiddleware() as $m) {
                if (! is_string($m) || ! str_starts_with($m, 'perm:')) {
                    continue;
                }
                [$sections, $action] = array_pad(explode(',', substr($m, 5)), 2, null);
                foreach (explode('|', $sections) as $section) {
                    $this->assertArrayHasKey($section, $leaves, "{$route->uri()} → {$section}");
                    if ($action) {
                        $this->assertContains($action, $leaves[$section]['actions'], "{$route->uri()} → {$section}.{$action}");
                    }
                }
            }
        }
    }

    // ── الحسابات الحالية ما بتتأثر ──

    public function test_admin_and_accountant_unchanged(): void
    {
        Sanctum::actingAs($this->admin(), ['*']);
        $this->getJson('/api/v1/dashboard/settings')->assertOk();
        $this->getJson('/api/v1/user')->assertOk()->assertJsonPath('permissions', ['*']);

        $accountant = User::factory()->create(['role' => 'accountant']);
        Sanctum::actingAs($accountant, ['*']);
        $this->getJson('/api/v1/dashboard/dues')->assertOk();
        $this->getJson('/api/v1/tenders')->assertOk();
        // role:admin بيضل مقفول على المحاسب كما قبل
        $this->getJson('/api/v1/dashboard/settings')->assertForbidden();
        $this->getJson('/api/v1/user')->assertOk()->assertJsonPath('permissions', null);
    }

    public function test_existing_admin_login_unchanged(): void
    {
        User::factory()->create(['role' => 'admin', 'email' => 'boss@example.com', 'password' => 'secret-123']);

        $this->postJson('/api/v1/auth/login', ['email' => 'boss@example.com', 'password' => 'secret-123'])
            ->assertOk()
            ->assertJsonPath('items.user.permissions', ['*']);
    }
}

<?php

namespace Tests\Feature;

use App\Models\Contractor;
use App\Models\Payment;
use App\Models\User;
use App\Notifications\PaymentSubmittedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * حساب «محاسب مالي» منشأ كمشرف بصلاحيات المالية: بيوصله إشعار الدفعة الجديدة،
 * وبيقدر يقبل الدفعات، واسمه وصورته بيظهروا كمن أكّد الدفعة.
 */
class FinanceSupervisorPaymentsTest extends TestCase
{
    use RefreshDatabase;

    private function supervisor(array $permissions): User
    {
        return User::create([
            'name' => 'محاسب مالي', 'email' => 'fin' . uniqid() . '@example.com', 'password' => 'Secret@1234',
            'role' => User::ROLE_SUPERVISOR, 'permissions' => $permissions, 'is_active' => true,
            'avatar' => 'avatars/fin.png',
        ]);
    }

    private function payment(): Payment
    {
        $contractor = Contractor::create([
            'name' => 'شركة اختبار', 'membership_number' => '9001_g', 'status' => 'active', 'is_frozen' => false,
            'phone' => '0590000009', 'password' => 'Test@1234', 'phone_verified_at' => now(),
        ]);

        return Payment::create([
            'contractor_id' => $contractor->id, 'amount' => 50, 'currency' => 'JOD', 'type' => 'dues_payment',
            'status' => 'pending', 'method' => 'bank_transfer', 'submitted_at' => now(),
        ]);
    }

    public function test_recipients_include_supervisor_with_payments_view_only(): void
    {
        $admin = User::create(['name' => 'A', 'email' => 'a@example.com', 'password' => 'Secret@1234', 'role' => 'admin']);
        $fin   = $this->supervisor(['finance.payments.view', 'finance.payments.update']);
        $other = $this->supervisor(['news.list.view']);

        $ids = User::dashboardRecipients('finance.payments', ['accountant'])->pluck('id')->all();

        $this->assertContains($admin->id, $ids);
        $this->assertContains($fin->id, $ids);
        $this->assertNotContains($other->id, $ids);
    }

    public function test_finance_supervisor_confirms_payment_and_response_shows_confirmer(): void
    {
        $fin     = $this->supervisor(['finance.payments.view', 'finance.payments.update']);
        $payment = $this->payment();

        Sanctum::actingAs($fin);
        $res = $this->postJson("/api/v1/payments/transactions/{$payment->id}/confirm");

        $res->assertOk();
        $res->assertJsonPath('items.confirmed_by_user.id', $fin->id);
        $res->assertJsonPath('items.confirmed_by_user.name', 'محاسب مالي');
        $res->assertJsonPath('items.confirmed_by_user.avatar', 'avatars/fin.png');
        $this->assertSame($fin->id, $payment->fresh()->confirmed_by);
    }

    public function test_view_only_supervisor_cannot_confirm(): void
    {
        $viewer  = $this->supervisor(['finance.payments.view']);
        $payment = $this->payment();

        Sanctum::actingAs($viewer);
        $this->postJson("/api/v1/payments/transactions/{$payment->id}/confirm")->assertForbidden();
    }

    public function test_contractor_app_sees_who_confirmed_the_payment(): void
    {
        $fin     = $this->supervisor(['finance.payments.view', 'finance.payments.update']);
        $payment = $this->payment();
        $payment->update(['status' => 'paid', 'confirmed_by' => $fin->id, 'confirmed_at' => now(), 'paid_at' => now()]);

        Sanctum::actingAs($payment->contractor);

        $res = $this->getJson('/api/v1/contractor/payments/transfer');
        $res->assertOk();
        $this->assertSame($fin->id, $res->json('items.0.confirmed_by_user.id'));
        $this->assertSame('محاسب مالي', $res->json('items.0.confirmed_by_user.name'));
        $this->assertStringContainsString('avatars/fin.png', $res->json('items.0.confirmed_by_user.avatar_url'));

        $this->getJson("/api/v1/contractor/payments/{$payment->id}")
            ->assertOk()->assertJsonPath('items.confirmed_by_user.id', $fin->id);
    }
}

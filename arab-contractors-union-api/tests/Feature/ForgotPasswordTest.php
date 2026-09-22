<?php

namespace Tests\Feature;

use App\Models\Contractor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * استعادة كلمة المرور (نسيت كلمة المرور) — POST contractor/auth/forgot-password/*.
 *
 * الانحدار الأساسي المُغطَّى هنا: المقاول بدون سجل عضوية فعّالة في جدول memberships
 * يجب أن يقدر يستعيد كلمة مروره — تماماً كما يقدر يسجّل الدخول. سابقاً كان الـ
 * endpoint يفحص activeMembership ويُرجع 403 membership_inactive، فيقفل الميزة على
 * كل المقاولين الذين تُدار عضويتهم عبر contractors.status لا عبر جدول memberships
 * (وهم الأغلبية في الإنتاج). راجع ContractorRegisterController::forgotPasswordSendOtp.
 */
class ForgotPasswordTest extends TestCase
{
    use RefreshDatabase;

    private function createContractor(array $attrs = []): Contractor
    {
        return Contractor::create(array_merge([
            'name'              => 'شركة نانسي للمقاولات',
            'membership_number' => '9551_g',
            'phone'             => '592373805',
            'password'          => Hash::make('OldPass@123'),
            'status'            => 'active',
            'is_frozen'         => false,
            'phone_verified_at' => now(),
        ], $attrs));
    }

    // ─────────────────────────────────────────────────────────────────────
    //  send-otp
    // ─────────────────────────────────────────────────────────────────────

    /** الانحدار: مقاول بلا سجل عضوية يقدر يطلب رمز الاستعادة (كان يُرجع 403 سابقاً) */
    public function test_send_otp_succeeds_without_active_membership(): void
    {
        $this->createContractor(); // لا يوجد أي سجل في memberships

        $this->postJson('/api/v1/contractor/auth/forgot-password/send-otp', [
            'phone' => '592373805',
        ])
            ->assertStatus(200)
            ->assertJsonPath('status', true)
            ->assertJsonPath('items.membership_number', '9551_g');
    }

    public function test_send_otp_returns_404_for_unknown_phone(): void
    {
        $this->postJson('/api/v1/contractor/auth/forgot-password/send-otp', [
            'phone' => '599999999',
        ])
            ->assertStatus(404)
            ->assertJsonPath('error', 'contractor_not_found');
    }

    public function test_send_otp_blocks_frozen_account(): void
    {
        $this->createContractor(['is_frozen' => true]);

        $this->postJson('/api/v1/contractor/auth/forgot-password/send-otp', [
            'phone' => '592373805',
        ])
            ->assertStatus(403)
            ->assertJsonPath('error', 'account_inactive');
    }

    // ─────────────────────────────────────────────────────────────────────
    //  reset — full flow
    // ─────────────────────────────────────────────────────────────────────

    /** المسار الكامل بدون عضوية: طلب رمز ثم إعادة تعيين كلمة المرور وتسجيل الدخول */
    public function test_reset_flow_succeeds_without_active_membership(): void
    {
        $contractor = $this->createContractor();

        // 1) طلب الرمز — is_preview=true في بيئة الاختبار (sms driver = log) فيُرجع otp_preview
        $send = $this->postJson('/api/v1/contractor/auth/forgot-password/send-otp', [
            'phone' => '592373805',
        ])->assertStatus(200);

        $otp = $send->json('items.otp_preview');
        $this->assertNotNull($otp, 'يُتوقّع otp_preview في بيئة الاختبار');

        // 2) إعادة التعيين بالرمز — تُرجع توكن دخول فوري
        $this->postJson('/api/v1/contractor/auth/forgot-password/reset', [
            'phone'                 => '592373805',
            'otp'                   => (string) $otp,
            'password'              => 'NewPass@456',
            'password_confirmation' => 'NewPass@456',
        ])
            ->assertStatus(200)
            ->assertJsonPath('status', true)
            ->assertJsonStructure(['items' => ['token', 'token_type']]);

        // كلمة المرور الجديدة سارية فعلاً
        $this->assertTrue(Hash::check('NewPass@456', $contractor->fresh()->password));
    }

    public function test_reset_rejects_wrong_otp(): void
    {
        $this->createContractor();

        $this->postJson('/api/v1/contractor/auth/forgot-password/send-otp', [
            'phone' => '592373805',
        ])->assertStatus(200);

        $this->postJson('/api/v1/contractor/auth/forgot-password/reset', [
            'phone'                 => '592373805',
            'otp'                   => '000000',
            'password'              => 'NewPass@456',
            'password_confirmation' => 'NewPass@456',
        ])
            ->assertStatus(422)
            ->assertJsonPath('error', 'invalid_otp');
    }
}

<?php

namespace Tests\Feature;

use App\Models\Contractor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ContractorRegisterFlowTest extends TestCase
{
    use RefreshDatabase;

    private function createContractor(array $attrs = []): Contractor
    {
        return Contractor::create(array_merge([
            'name'               => 'شركة اختبار للمقاولات',
            'membership_number'  => '905_g',
            'phone'              => '0590000001',
            'status'             => 'active',
            'is_frozen'          => false,
            'commercial_register'=> 'CR-1001',
            'trade'              => 'مقاولات عامة',
            'classification'     => 'أ',
        ], $attrs));
    }

    // ─────────────────────────────────────────────────────────────────────
    //  verify-identity
    // ─────────────────────────────────────────────────────────────────────

    public function test_verify_identity_success_sends_otp(): void
    {
        $this->createContractor();

        $response = $this->postJson('/api/v1/contractor/auth/verify-identity', [
            'phone'          => '0590000001',
            'terms_accepted' => 1,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('items.otp_required', true)
            ->assertJsonPath('items.phone', '0590000001');
    }

    public function test_verify_identity_fails_for_unknown_phone(): void
    {
        $response = $this->postJson('/api/v1/contractor/auth/verify-identity', [
            'phone'          => '0599999999',
            'terms_accepted' => 1,
        ]);

        $response->assertStatus(404)->assertJsonPath('error', 'contractor_not_found');
    }

    public function test_verify_identity_fails_when_membership_number_missing(): void
    {
        $this->createContractor(['membership_number' => null]);

        $response = $this->postJson('/api/v1/contractor/auth/verify-identity', [
            'phone'          => '0590000001',
            'terms_accepted' => 1,
        ]);

        $response->assertStatus(422)->assertJsonPath('error', 'no_membership_number');
    }

    public function test_verify_identity_blocks_frozen_account(): void
    {
        $this->createContractor(['is_frozen' => true]);

        $response = $this->postJson('/api/v1/contractor/auth/verify-identity', [
            'phone'          => '0590000001',
            'terms_accepted' => 1,
        ]);

        $response->assertStatus(403)->assertJsonPath('error', 'account_inactive');
    }

    public function test_verify_identity_rejects_already_registered_account(): void
    {
        $this->createContractor([
            'password'          => Hash::make('password123'),
            'phone_verified_at' => now(),
        ]);

        $response = $this->postJson('/api/v1/contractor/auth/verify-identity', [
            'phone'          => '0590000001',
            'terms_accepted' => 1,
        ]);

        $response->assertStatus(422)->assertJsonPath('error', 'already_registered');
    }

    public function test_verify_identity_requires_terms_accepted(): void
    {
        $this->createContractor();

        $response = $this->postJson('/api/v1/contractor/auth/verify-identity', [
            'phone' => '0590000001',
        ]);

        $response->assertStatus(422);
    }

    // ─────────────────────────────────────────────────────────────────────
    //  verify-otp
    // ─────────────────────────────────────────────────────────────────────

    public function test_verify_otp_success_activates_phone(): void
    {
        $contractor = $this->createContractor();

        $this->postJson('/api/v1/contractor/auth/verify-identity', [
            'phone' => '0590000001', 'terms_accepted' => 1,
        ]);
        $otp = \Illuminate\Support\Facades\Cache::get('register_otp_' . $contractor->id)['otp'];

        $response = $this->postJson('/api/v1/contractor/auth/verify-otp', [
            'phone' => '0590000001', 'otp' => (string) $otp,
        ]);

        $response->assertStatus(200)->assertJsonPath('items.phone_verified', true);
        $this->assertNotNull($contractor->fresh()->phone_verified_at);
    }

    public function test_verify_otp_fails_with_wrong_code(): void
    {
        $contractor = $this->createContractor();
        $this->postJson('/api/v1/contractor/auth/verify-identity', [
            'phone' => '0590000001', 'terms_accepted' => 1,
        ]);

        $response = $this->postJson('/api/v1/contractor/auth/verify-otp', [
            'phone' => '0590000001', 'otp' => '000000',
        ]);

        $response->assertStatus(422)->assertJsonPath('error', 'invalid_otp');
        $this->assertNull($contractor->fresh()->phone_verified_at);
    }

    // ─────────────────────────────────────────────────────────────────────
    //  resend-otp
    // ─────────────────────────────────────────────────────────────────────

    public function test_resend_otp_fails_if_already_verified(): void
    {
        $this->createContractor(['phone_verified_at' => now()]);

        $response = $this->postJson('/api/v1/contractor/auth/resend-otp', [
            'phone' => '0590000001',
        ]);

        $response->assertStatus(422)->assertJsonPath('error', 'already_verified');
    }

    public function test_resend_otp_issues_new_code(): void
    {
        $this->createContractor();

        $response = $this->postJson('/api/v1/contractor/auth/resend-otp', [
            'phone' => '0590000001',
        ]);

        $response->assertStatus(200)->assertJsonPath('items.otp_required', true);
    }

    // ─────────────────────────────────────────────────────────────────────
    //  set-password
    // ─────────────────────────────────────────────────────────────────────

    public function test_set_password_fails_before_phone_verified(): void
    {
        $this->createContractor();

        $response = $this->postJson('/api/v1/contractor/auth/set-password', [
            'phone' => '0590000001', 'password' => 'password123', 'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(422)->assertJsonPath('error', 'phone_not_verified');
    }

    public function test_set_password_fails_when_membership_number_missing(): void
    {
        $this->createContractor(['phone_verified_at' => now(), 'membership_number' => null]);

        $response = $this->postJson('/api/v1/contractor/auth/set-password', [
            'phone' => '0590000001', 'password' => 'password123', 'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(422)->assertJsonPath('error', 'no_membership_number');
    }

    public function test_set_password_rejects_mismatched_confirmation(): void
    {
        $this->createContractor(['phone_verified_at' => now()]);

        $response = $this->postJson('/api/v1/contractor/auth/set-password', [
            'phone' => '0590000001', 'password' => 'password123', 'password_confirmation' => 'different123',
        ]);

        $response->assertStatus(422)->assertJsonPath('error', 'password_mismatch');
    }

    public function test_set_password_success_returns_real_membership_data_and_token(): void
    {
        $this->createContractor(['phone_verified_at' => now()]);

        $response = $this->postJson('/api/v1/contractor/auth/set-password', [
            'phone' => '0590000001', 'password' => 'password123', 'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('items.user.membership_number', '905_g')
            ->assertJsonPath('items.user.commercial_register', 'CR-1001')
            ->assertJsonPath('items.user.trade', 'مقاولات عامة')
            ->assertJsonPath('items.user.classification', 'أ');
        $this->assertNotEmpty($response->json('items.token'));
    }

    public function test_set_password_fails_if_already_registered(): void
    {
        $this->createContractor([
            'phone_verified_at' => now(),
            'password'          => Hash::make('oldpassword'),
        ]);

        $response = $this->postJson('/api/v1/contractor/auth/set-password', [
            'phone' => '0590000001', 'password' => 'password123', 'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(422)->assertJsonPath('error', 'already_registered');
    }

    public function test_set_password_with_fcm_token_persists_it(): void
    {
        $this->createContractor(['phone_verified_at' => now()]);

        $response = $this->postJson('/api/v1/contractor/auth/set-password', [
            'phone' => '0590000001',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'fcm_token' => 'test-fcm-token-12345',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('contractors', [
            'phone' => '0590000001',
            'fcm_token' => 'test-fcm-token-12345',
        ]);
    }

    public function test_set_password_without_fcm_token_leaves_previous_value_intact(): void
    {
        $this->createContractor([
            'phone_verified_at' => now(),
            'fcm_token' => 'existing-token-xyz',
        ]);

        $response = $this->postJson('/api/v1/contractor/auth/set-password', [
            'phone' => '0590000001',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('contractors', [
            'phone' => '0590000001',
            'fcm_token' => 'existing-token-xyz',
        ]);
    }

    public function test_set_password_empty_string_does_not_clear_previous_fcm_token(): void
    {
        $this->createContractor([
            'phone_verified_at' => now(),
            'fcm_token' => 'existing-token-abc',
        ]);

        $response = $this->postJson('/api/v1/contractor/auth/set-password', [
            'phone' => '0590000001',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'fcm_token' => '',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('contractors', [
            'phone' => '0590000001',
            'fcm_token' => 'existing-token-abc',
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────
    //  login
    // ─────────────────────────────────────────────────────────────────────

    public function test_login_success_returns_token(): void
    {
        $this->createContractor([
            'phone_verified_at' => now(),
            'password'          => Hash::make('password123'),
        ]);

        $response = $this->postJson('/api/v1/contractor/auth/login', [
            'membership_number' => '905_g', 'password' => 'password123',
        ]);

        $response->assertStatus(200);
        $this->assertNotEmpty($response->json('items.token'));
    }

    public function test_login_fails_for_unknown_membership_number(): void
    {
        $response = $this->postJson('/api/v1/contractor/auth/login', [
            'membership_number' => '999_g', 'password' => 'password123',
        ]);

        $response->assertStatus(404)->assertJsonPath('error', 'no_membership');
    }

    public function test_login_fails_with_wrong_password(): void
    {
        $this->createContractor([
            'phone_verified_at' => now(),
            'password'          => Hash::make('password123'),
        ]);

        $response = $this->postJson('/api/v1/contractor/auth/login', [
            'membership_number' => '905_g', 'password' => 'wrongpassword',
        ]);

        $response->assertStatus(401)->assertJsonPath('error', 'invalid_credentials');
    }

    public function test_login_fails_before_phone_verified(): void
    {
        $this->createContractor(['password' => Hash::make('password123')]);

        $response = $this->postJson('/api/v1/contractor/auth/login', [
            'membership_number' => '905_g', 'password' => 'password123',
        ]);

        $response->assertStatus(403)->assertJsonPath('error', 'phone_not_verified');
    }

    public function test_login_with_fcm_token_persists_it(): void
    {
        $this->createContractor([
            'phone_verified_at' => now(),
            'password' => Hash::make('password123'),
        ]);

        $response = $this->postJson('/api/v1/contractor/auth/login', [
            'membership_number' => '905_g',
            'password' => 'password123',
            'fcm_token' => 'new-fcm-token-xyz',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('contractors', [
            'membership_number' => '905_g',
            'fcm_token' => 'new-fcm-token-xyz',
        ]);
    }

    public function test_login_empty_fcm_token_does_not_clear_existing(): void
    {
        $this->createContractor([
            'phone_verified_at' => now(),
            'password' => Hash::make('password123'),
            'fcm_token' => 'existing-token-123',
        ]);

        $response = $this->postJson('/api/v1/contractor/auth/login', [
            'membership_number' => '905_g',
            'password' => 'password123',
            'fcm_token' => '',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('contractors', [
            'membership_number' => '905_g',
            'fcm_token' => 'existing-token-123',
        ]);
    }
}

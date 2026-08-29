<?php

namespace Tests\Feature;

use App\Models\Contractor;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ContractorPaymentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    private function createContractor(array $attrs = []): Contractor
    {
        return Contractor::create(array_merge([
            'name'              => 'شركة اختبار للمقاولات',
            'membership_number' => '930_g',
            'status'            => 'active',
            'is_frozen'         => false,
        ], $attrs));
    }

    // ─────────────────────────────────────────────────────────────────────
    //  submitTransfer
    // ─────────────────────────────────────────────────────────────────────

    public function test_submit_transfer_creates_pending_payment_and_persists_receipt_file(): void
    {
        $contractor = $this->createContractor();
        Sanctum::actingAs($contractor, ['*']);

        $response = $this->postJson('/api/v1/contractor/payments/transfer', [
            'amount'         => 150,
            'currency'       => 'JOD',
            'receipt_image'  => UploadedFile::fake()->image('receipt.jpg'),
            'reference_number' => 'REF-1',
            'type'           => 'membership_fee',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('items.contractor_id', $contractor->id)
            ->assertJsonPath('items.status', 'pending')
            ->assertJsonPath('items.amount', '150.00');

        $this->assertDatabaseHas('payments', [
            'contractor_id' => $contractor->id,
            'status'        => 'pending',
            'reference_number' => 'REF-1',
        ]);

        $payment = Payment::first();
        Storage::disk('public')->assertExists($payment->receipt_image);

        // re-fetch through API confirms DB state
        $this->getJson('/api/v1/contractor/payments/transfer')
            ->assertJsonPath('items.0.reference_number', 'REF-1');
    }

    public function test_submit_transfer_fails_validation_without_receipt_image(): void
    {
        $contractor = $this->createContractor();
        Sanctum::actingAs($contractor, ['*']);

        $response = $this->postJson('/api/v1/contractor/payments/transfer', ['amount' => 100]);

        $response->assertStatus(422)->assertJsonValidationErrors(['receipt_image']);
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_submit_transfer_rejects_non_file_mimetype(): void
    {
        $contractor = $this->createContractor();
        Sanctum::actingAs($contractor, ['*']);

        $response = $this->postJson('/api/v1/contractor/payments/transfer', [
            'amount'        => 100,
            'receipt_image' => UploadedFile::fake()->create('malware.exe', 10),
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['receipt_image']);
    }

    public function test_submit_transfer_blocked_when_dues_outstanding_for_non_dues_type(): void
    {
        $contractor = $this->createContractor();
        \App\Models\ContractorDue::create([
            'contractor_id' => $contractor->id,
            'description'   => 'ذمة',
            'amount_jod'    => 50,
            'status'        => 'unpaid',
        ]);
        \App\Models\Setting::set('enforce_dues_blocking', '1');
        Sanctum::actingAs($contractor, ['*']);

        $response = $this->postJson('/api/v1/contractor/payments/transfer', [
            'amount'        => 100,
            'receipt_image' => UploadedFile::fake()->image('r.jpg'),
            'type'          => 'membership_fee',
        ]);

        $response->assertStatus(403)->assertJsonPath('error', 'dues_pending');
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_submit_transfer_allows_dues_payment_type_even_with_outstanding_dues(): void
    {
        $contractor = $this->createContractor();
        \App\Models\ContractorDue::create([
            'contractor_id' => $contractor->id,
            'description'   => 'ذمة',
            'amount_jod'    => 50,
            'status'        => 'unpaid',
        ]);
        \App\Models\Setting::set('enforce_dues_blocking', '1');
        Sanctum::actingAs($contractor, ['*']);

        $response = $this->postJson('/api/v1/contractor/payments/transfer', [
            'amount'        => 50,
            'receipt_image' => UploadedFile::fake()->image('r.jpg'),
            'type'          => 'dues_payment',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('payments', ['contractor_id' => $contractor->id, 'type' => 'dues_payment']);
    }

    public function test_duplicate_submission_creates_two_independent_pending_payments(): void
    {
        $contractor = $this->createContractor();
        Sanctum::actingAs($contractor, ['*']);

        $payload = ['amount' => 100, 'receipt_image' => UploadedFile::fake()->image('r.jpg')];
        $this->postJson('/api/v1/contractor/payments/transfer', $payload)->assertStatus(201);

        $payload2 = ['amount' => 100, 'receipt_image' => UploadedFile::fake()->image('r2.jpg')];
        $this->postJson('/api/v1/contractor/payments/transfer', $payload2)->assertStatus(201);

        $this->assertDatabaseCount('payments', 2);
    }

    // ─────────────────────────────────────────────────────────────────────
    //  myTransfers
    // ─────────────────────────────────────────────────────────────────────

    public function test_my_transfers_only_lists_own_payments(): void
    {
        $me      = $this->createContractor();
        $another = $this->createContractor(['membership_number' => '931_g']);

        Payment::create(['contractor_id' => $me->id, 'amount' => 10, 'status' => 'pending']);
        Payment::create(['contractor_id' => $another->id, 'amount' => 20, 'status' => 'pending']);

        Sanctum::actingAs($me, ['*']);

        $this->getJson('/api/v1/contractor/payments/transfer')
            ->assertJsonCount(1, 'items')
            ->assertJsonPath('items.0.amount', '10.00');
    }

    // ─────────────────────────────────────────────────────────────────────
    //  receipt — ownership + status guard
    // ─────────────────────────────────────────────────────────────────────

    public function test_receipt_fails_for_unconfirmed_payment(): void
    {
        $contractor = $this->createContractor();
        $payment = Payment::create(['contractor_id' => $contractor->id, 'amount' => 10, 'status' => 'pending']);
        Sanctum::actingAs($contractor, ['*']);

        $this->getJson("/api/v1/contractor/payments/{$payment->id}/receipt")->assertStatus(404);
    }

    public function test_receipt_nonexistent_payment_returns_404(): void
    {
        $contractor = $this->createContractor();
        Sanctum::actingAs($contractor, ['*']);

        $this->getJson('/api/v1/contractor/payments/999999/receipt')->assertStatus(404);
    }

    public function test_contractor_a_cannot_access_contractor_b_paid_receipt(): void
    {
        $a = $this->createContractor(['membership_number' => '940_g']);
        $b = $this->createContractor(['membership_number' => '941_g', 'name' => 'مقاول ب']);
        $bPayment = Payment::create(['contractor_id' => $b->id, 'amount' => 100, 'status' => 'paid']);

        Sanctum::actingAs($a, ['*']);
        $this->getJson("/api/v1/contractor/payments/{$bPayment->id}/receipt")->assertStatus(403);
    }

    public function test_contractor_a_cannot_see_contractor_b_payment_in_my_transfers(): void
    {
        $a = $this->createContractor(['membership_number' => '942_g']);
        $b = $this->createContractor(['membership_number' => '943_g', 'name' => 'مقاول ب']);
        Payment::create(['contractor_id' => $b->id, 'amount' => 100, 'status' => 'pending']);

        Sanctum::actingAs($a, ['*']);
        $this->getJson('/api/v1/contractor/payments/transfer')->assertJsonCount(0, 'items');
    }

    public function test_submit_transfer_requires_auth(): void
    {
        $this->postJson('/api/v1/contractor/payments/transfer', ['amount' => 10])->assertStatus(401);
    }
}

<?php

namespace Tests\Feature;

use App\Models\Contractor;
use App\Models\ContractorDue;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * "إضافة دفعة" من شاشة سجل المدفوعات — الأدمن يُدخل دفعة نيابةً عن المقاول مع إشعارها،
 * فتُسجَّل مؤكَّدة وتوزَّع على أقدم الذمم والزائد يبقى رصيداً متاحاً.
 */
class ManualPaymentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Notification::fake();
        Queue::fake();
    }

    private function actingAs_(string $role): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => $role]), ['*']);
    }

    private function contractor(): Contractor
    {
        return Contractor::create([
            'name'              => 'شركة اختبار للمقاولات',
            'membership_number' => '931_g',
            'status'            => 'active',
            'is_frozen'         => false,
        ]);
    }

    private function due(Contractor $contractor, int $year, float $amount): ContractorDue
    {
        return ContractorDue::create([
            'contractor_id' => $contractor->id,
            'description'   => "رسوم {$year}",
            'year'          => $year,
            'amount_jod'    => $amount,
            'paid_jod'      => 0,
            'status'        => 'unpaid',
        ]);
    }

    public function test_manual_payment_is_paid_with_receipt_and_settles_oldest_dues_first(): void
    {
        $this->actingAs_('admin');
        $contractor = $this->contractor();
        $old = $this->due($contractor, 2024, 100);
        $new = $this->due($contractor, 2025, 100);

        $response = $this->postJson('/api/v1/payments/transactions/manual', [
            'contractor_id'    => $contractor->id,
            'amount'           => 250,
            'currency'         => 'JOD',
            'method'           => 'bank_transfer',
            'reference_number' => 'BANK-77',
            'notes'            => 'حوالة عن المقاول',
            'receipt_image'    => UploadedFile::fake()->image('receipt.jpg'),
        ]);

        $response->assertCreated()
            ->assertJsonPath('items.payment.status', 'paid')
            ->assertJsonPath('items.payment.type', 'dues_payment')
            ->assertJsonPath('items.unapplied_jod', 50)
            ->assertJsonPath('items.remaining_total_jod', 0);

        $payment = Payment::firstOrFail();
        Storage::disk('public')->assertExists($payment->receipt_image);
        $this->assertSame('250.00', (string) $payment->amount_jod);
        $this->assertEquals(200, (float) $payment->used_amount_jod);
        $this->assertNotNull($payment->transaction_number);

        $this->assertSame('paid', $old->fresh()->status);
        $this->assertSame('paid', $new->fresh()->status);
    }

    public function test_foreign_currency_is_converted_to_jod_with_the_given_rate(): void
    {
        $this->actingAs_('accountant');
        $contractor = $this->contractor();
        $this->due($contractor, 2025, 100);

        $this->postJson('/api/v1/payments/transactions/manual', [
            'contractor_id' => $contractor->id,
            'amount'        => 100,
            'currency'      => 'USD',
            'exchange_rate' => 0.709,
            'method'        => 'cash',
        ])->assertCreated()->assertJsonPath('items.payment.amount_jod', '70.90');

        $this->assertSame('partially_paid', ContractorDue::first()->status);
    }

    public function test_bank_transfer_requires_a_receipt_image(): void
    {
        $this->actingAs_('admin');

        $this->postJson('/api/v1/payments/transactions/manual', [
            'contractor_id' => $this->contractor()->id,
            'amount'        => 10,
            'method'        => 'bank_transfer',
        ])->assertStatus(422)->assertJsonValidationErrors('receipt_image');
    }

    public function test_other_staff_roles_cannot_add_payments(): void
    {
        $this->actingAs_('editor');

        $this->postJson('/api/v1/payments/transactions/manual', [
            'contractor_id' => $this->contractor()->id,
            'amount'        => 10,
            'method'        => 'cash',
        ])->assertForbidden();
    }
}

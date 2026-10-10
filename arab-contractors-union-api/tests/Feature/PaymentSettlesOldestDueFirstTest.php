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
 * قاعدة eslam (10/10/2026): عليه 300 = 150 لسنة 2025 + 150 لسنة 2026. لو دفع 150 لسنة 2026
 * (من كرت ذمة 2026 أو كرسوم عضوية)، الدفعة بتسدّ الأقدم: 2025، و2026 بتضل عليه.
 */
class PaymentSettlesOldestDueFirstTest extends TestCase
{
    use RefreshDatabase;

    private Contractor $contractor;
    private ContractorDue $due2025;
    private ContractorDue $due2026;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Notification::fake();
        Queue::fake();

        $this->contractor = Contractor::create([
            'name'              => 'شركة اختبار للمقاولات',
            'membership_number' => '940_g',
            'status'            => 'active',
            'is_frozen'         => false,
        ]);

        $this->due2025 = $this->due(2025, 'رسوم متراكمة 2025');
        $this->due2026 = $this->due(2026, 'رسوم اشتراك سنة 2026');
    }

    private function due(int $year, string $description): ContractorDue
    {
        $due = ContractorDue::create([
            'contractor_id' => $this->contractor->id,
            'year'          => $year,
            'description'   => $description,
            'amount_jod'    => 150,
            'status'        => 'unpaid',
            'source'        => 'manual',
        ]);
        $due->update(['reference_number' => ContractorDue::generateReferenceNumber($due)]);

        return $due;
    }

    private function payAndConfirm(array $payload): Payment
    {
        Sanctum::actingAs($this->contractor, ['*']);
        $id = $this->postJson('/api/v1/contractor/payments/transfer', $payload + [
            'amount'        => 150,
            'receipt_image' => UploadedFile::fake()->image('r.jpg'),
        ])->assertCreated()->json('items.id');

        Sanctum::actingAs(User::factory()->create(['role' => 'admin']), ['*']);
        $this->postJson("/api/v1/payments/transactions/{$id}/confirm")->assertOk();

        return Payment::findOrFail($id);
    }

    private function assertOldestSettled(Payment $payment): void
    {
        $this->assertSame('paid', $this->due2025->fresh()->status, 'ذمة 2025 (الأقدم) انسدّت');
        $this->assertSame('unpaid', $this->due2026->fresh()->status, 'ذمة 2026 بتضل عليه');
        $this->assertEquals(150, $this->due2026->fresh()->remaining_jod);
        $this->assertEquals([$this->due2025->id], $payment->allocations()->pluck('contractor_due_id')->all());
        $this->assertSame('رسوم متراكمة 2025', $payment->fresh()->title, 'عنوان الدفعة بيتبع الذمة اللي انسدّت');
    }

    public function test_payment_on_the_2026_due_card_settles_2025_first(): void
    {
        $payment = $this->payAndConfirm(['contractor_due_id' => $this->due2026->id, 'type' => 'dues_payment']);

        $this->assertOldestSettled($payment);
    }

    public function test_membership_fee_payment_settles_2025_before_the_2026_annual_fee(): void
    {
        $payment = $this->payAndConfirm(['type' => 'membership_fee']);

        $this->assertOldestSettled($payment);
    }

    public function test_paying_the_full_300_settles_both(): void
    {
        Sanctum::actingAs($this->contractor, ['*']);
        $id = $this->postJson('/api/v1/contractor/payments/transfer', [
            'amount'            => 300,
            'contractor_due_id' => $this->due2026->id,
            'type'              => 'dues_payment',
            'receipt_image'     => UploadedFile::fake()->image('r.jpg'),
        ])->assertCreated()->json('items.id');

        Sanctum::actingAs(User::factory()->create(['role' => 'admin']), ['*']);
        $this->postJson("/api/v1/payments/transactions/{$id}/confirm")->assertOk();

        $this->assertSame('paid', $this->due2025->fresh()->status);
        $this->assertSame('paid', $this->due2026->fresh()->status);
    }
}

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
 * شاشة "الملف المالي" بالتطبيق: زر "ادفع الآن" على كرت ذمة يربط التحويل بالذمة نفسها،
 * فيظهر على الكرت "قيد المراجعة" أو سبب الرفض، وتُسدَّد الذمة تلقائياً عند الاعتماد.
 */
class DueLinkedTransferTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Notification::fake();
        Queue::fake();
    }

    private function contractor(string $number = '940_g'): Contractor
    {
        return Contractor::create([
            'name'              => 'شركة اختبار للمقاولات',
            'membership_number' => $number,
            'status'            => 'active',
            'is_frozen'         => false,
        ]);
    }

    private function due(Contractor $contractor, array $attrs = []): ContractorDue
    {
        $due = ContractorDue::create(array_merge([
            'contractor_id' => $contractor->id,
            'year'          => 2026,
            'description'   => 'رسوم اشتراك سنة 2026',
            'amount_jod'    => 100,
            'status'        => 'unpaid',
            'source'        => 'manual',
        ], $attrs));
        $due->update(['reference_number' => ContractorDue::generateReferenceNumber($due)]);

        return $due;
    }

    private function submit(ContractorDue $due, float $amount = 100)
    {
        return $this->postJson('/api/v1/contractor/payments/transfer', [
            'amount'            => $amount,
            'contractor_due_id' => $due->id,
            'receipt_image'     => UploadedFile::fake()->image('r.jpg'),
        ]);
    }

    private function financialDue(int $id): array
    {
        return collect($this->getJson('/api/v1/contractor/financial')->assertOk()->json('items.dues'))
            ->firstWhere('id', $id);
    }

    public function test_transfer_links_to_due_and_card_shows_under_review(): void
    {
        $contractor = $this->contractor();
        $due = $this->due($contractor);
        Sanctum::actingAs($contractor, ['*']);

        $this->submit($due)->assertStatus(201)
            ->assertJsonPath('items.contractor_due_id', $due->id)
            ->assertJsonPath('items.type', 'dues_payment');

        $card = $this->financialDue($due->id);
        $this->assertTrue($card['is_under_review']);
        $this->assertNotNull($card['pending_payment']['receipt_image_url']);
        $this->assertNull($card['last_rejection_reason']);

        // إشعار ثاني لنفس الذمة وهي قيد المراجعة مرفوض
        $this->submit($due)->assertStatus(409);
    }

    public function test_cannot_pay_someone_elses_due(): void
    {
        $other = $this->due($this->contractor('941_g'));
        Sanctum::actingAs($this->contractor(), ['*']);

        $this->submit($other)->assertStatus(403);
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_rejection_reason_shows_on_card_until_a_new_transfer(): void
    {
        $contractor = $this->contractor();
        $due = $this->due($contractor);
        Sanctum::actingAs($contractor, ['*']);
        $paymentId = $this->submit($due)->json('items.id');

        Sanctum::actingAs(User::factory()->create(['role' => 'admin']), ['*']);
        $this->postJson("/api/v1/payments/transactions/{$paymentId}/reject", [
            'rejection_reason' => 'صورة الإشعار غير واضحة',
        ])->assertOk();

        Sanctum::actingAs($contractor, ['*']);
        $card = $this->financialDue($due->id);
        $this->assertSame('صورة الإشعار غير واضحة', $card['last_rejection_reason']);
        $this->assertFalse($card['is_under_review']);

        $this->submit($due)->assertStatus(201);
        $card = $this->financialDue($due->id);
        $this->assertNull($card['last_rejection_reason']);
        $this->assertTrue($card['is_under_review']);
    }

    public function test_confirming_linked_transfer_settles_the_due(): void
    {
        $contractor = $this->contractor();
        $due = $this->due($contractor);
        Sanctum::actingAs($contractor, ['*']);
        $paymentId = $this->submit($due, 120)->json('items.id');

        Sanctum::actingAs(User::factory()->create(['role' => 'admin']), ['*']);
        $this->postJson("/api/v1/payments/transactions/{$paymentId}/confirm")->assertOk();

        $this->assertSame('paid', $due->fresh()->status);
        // الفائض (20) يضل رصيداً غير مستخدم
        $this->assertEquals(100, (float) Payment::find($paymentId)->used_amount_jod);
    }

    public function test_receipt_date_and_discount_fields(): void
    {
        $contractor = $this->contractor();
        $fee = $this->due($contractor);
        $fee->applyDiscount('percent', 10, null, User::factory()->create()->id);
        $other = $this->due($contractor, ['year' => 2024, 'description' => 'رسوم متراكمة']);
        Sanctum::actingAs($contractor, ['*']);

        $card = $this->financialDue($fee->id);
        $this->assertSame('2026-12-31', $card['receipt_date']);
        $this->assertEquals(100, (float) $card['original_amount_jod']);
        $this->assertSame('percent', $card['discount_type']);
        $this->assertEquals(10, (float) $card['discount_value']);
        $this->assertEquals(10, (float) $card['discount_amount_jod']);
        $this->assertEquals(90, (float) $card['remaining_jod']);

        $card = $this->financialDue($other->id);
        $this->assertNull($card['receipt_date']);
        $this->assertNull($card['original_amount_jod']);
        $this->assertNull($card['discount_type']);
    }
}

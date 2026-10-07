<?php

namespace Tests\Feature;

use App\Models\Contractor;
use App\Models\ContractorDue;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * تبويب "الدفعات السابقة" بالتطبيق: كل دفعة بترجع title و type_label جاهزين للعرض
 * بدل ما التطبيق يكتب "رسوم اشتراك سنوي" ثابتة لكل كرت.
 */
class PaymentTitleTest extends TestCase
{
    use RefreshDatabase;

    private Contractor $contractor;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Notification::fake();
        Queue::fake();

        $this->contractor = Contractor::create([
            'name'              => 'شركة اختبار للمقاولات',
            'membership_number' => '950_g',
            'status'            => 'active',
            'is_frozen'         => false,
        ]);
    }

    private function due(string $description, int $year = 2026, float $amount = 100): ContractorDue
    {
        return ContractorDue::create([
            'contractor_id' => $this->contractor->id,
            'year'          => $year,
            'description'   => $description,
            'amount_jod'    => $amount,
            'status'        => 'unpaid',
            'source'        => 'manual',
        ]);
    }

    private function submit(array $extra = []): int
    {
        Sanctum::actingAs($this->contractor, ['*']);

        return $this->postJson('/api/v1/contractor/payments/transfer', $extra + [
            'amount'        => 100,
            'type'          => 'dues_payment',
            'receipt_image' => UploadedFile::fake()->image('r.jpg'),
        ])->assertStatus(201)->json('items.id');
    }

    private function confirm(int $paymentId): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']), ['*']);
        $this->postJson("/api/v1/payments/transactions/{$paymentId}/confirm")->assertOk();
    }

    private function myPayment(int $id): array
    {
        Sanctum::actingAs($this->contractor, ['*']);

        return $this->getJson("/api/v1/contractor/payments/{$id}")->assertOk()->json('items');
    }

    public function test_linked_due_gives_its_title_without_internal_suffixes(): void
    {
        $due = $this->due('رسوم اشتراك سنة 2026 (محرّك الاحتساب الآلي) (بعد خصم 10%)');
        $id = $this->submit(['contractor_due_id' => $due->id]);

        $payment = $this->myPayment($id);
        $this->assertSame('رسوم اشتراك سنة 2026', $payment['title']);
        $this->assertSame('تسديد ذمم مالية', $payment['type_label']);
    }

    public function test_unlinked_dues_payment_takes_title_of_the_due_it_settled(): void
    {
        $this->due('رسوم اشتراك سنة 2026');
        $id = $this->submit();

        $this->assertSame('تسديد ذمم مالية', $this->myPayment($id)['title']);

        $this->confirm($id);
        $this->assertSame('رسوم اشتراك سنة 2026', $this->myPayment($id)['title']);

        Sanctum::actingAs($this->contractor, ['*']);
        $this->getJson('/api/v1/contractor/payments/transfer?type=dues_payment')
            ->assertJsonPath('items.0.title', 'رسوم اشتراك سنة 2026')
            ->assertJsonPath('items.0.type_label', 'تسديد ذمم مالية');
    }

    public function test_payment_spread_over_several_dues_is_titled_by_count(): void
    {
        $this->due('رسوم اشتراك سنة 2024', 2024, 50);
        $this->due('رسوم اشتراك سنة 2025', 2025, 50);
        $id = $this->submit();
        $this->confirm($id);

        $this->assertSame('تسديد 2 ذمم مالية', $this->myPayment($id)['title']);
    }
}

<?php

namespace Tests\Feature;

use App\Jobs\GeneratePaymentReceiptJob;
use App\Models\Contractor;
use App\Models\ContractorDue;
use App\Models\Payment;
use App\Models\User;
use App\Notifications\PaymentConfirmedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * طلب eslam (25/10/2026): نافذة "تسجيل دفعة واردة من الشركة" بصفحة الذمم فيها حقل اختياري
 * لإرفاق صورة الإشعار، وبتنحفظ على الدفعة بنفس مكان صورة إشعار التحويل (receipt_image).
 */
class DuesPagePaymentReceiptTest extends TestCase
{
    use RefreshDatabase;

    private Contractor $contractor;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Notification::fake();
        Queue::fake();

        Sanctum::actingAs(User::factory()->create(['role' => 'admin']), ['*']);
        $this->contractor = Contractor::create([
            'name'              => 'شركة الإشعار',
            'membership_number' => '976_g',
            'status'            => 'active',
            'is_frozen'         => false,
        ]);
        ContractorDue::create([
            'contractor_id' => $this->contractor->id,
            'year'          => 2026,
            'description'   => 'ذمة 2026',
            'amount_jod'    => 100,
            'paid_jod'      => 0,
            'status'        => 'unpaid',
            'source'        => 'manual',
            'due_date'      => '2026-01-01',
        ]);
    }

    private function pay(array $data)
    {
        return $this->post("/api/v1/dashboard/contractors/{$this->contractor->id}/dues/pay", $data, ['Accept' => 'application/json']);
    }

    public function test_receipt_image_is_stored_on_the_payment(): void
    {
        $id = $this->pay(['amount' => 100, 'receipt_image' => UploadedFile::fake()->image('notice.jpg')])
            ->assertCreated()->json('items.payment_id');

        $payment = Payment::findOrFail($id);
        $this->assertNotNull($payment->receipt_image);
        Storage::disk('public')->assertExists($payment->receipt_image);
        $this->assertNotNull($payment->receipt_image_url);
    }

    public function test_pdf_receipt_is_accepted(): void
    {
        $this->pay(['amount' => 50, 'receipt_image' => UploadedFile::fake()->create('notice.pdf', 100, 'application/pdf')])
            ->assertCreated();
    }

    public function test_receipt_is_optional(): void
    {
        $id = $this->pay(['amount' => 100])->assertCreated()->json('items.payment_id');
        $this->assertNull(Payment::findOrFail($id)->receipt_image);
    }

    public function test_other_file_types_are_rejected(): void
    {
        $this->pay(['amount' => 100, 'receipt_image' => UploadedFile::fake()->create('notice.exe', 10)])
            ->assertStatus(422)->assertJsonValidationErrors('receipt_image');
    }

    /** بلاغ eslam (27/10/2026): دفعة صفحة الذمم ما كانت تطلع إلها فاتورة مثل دفعة صفحة المدفوعات */
    public function test_dues_page_payment_generates_invoice_and_notifies_contractor(): void
    {
        $id = $this->pay(['amount' => 100])->assertCreated()->json('items.payment_id');

        Queue::assertPushed(GeneratePaymentReceiptJob::class, fn ($job) => $job->paymentId === $id);
        Notification::assertSentTo($this->contractor, PaymentConfirmedNotification::class);
    }
}

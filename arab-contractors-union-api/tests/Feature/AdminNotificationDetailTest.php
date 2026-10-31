<?php

namespace Tests\Feature;

use App\Models\Contractor;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * لما الأدمن يفتح إشعار طلب شهادة أو طلب دعم من اللوحة، الصفحة بتجيب التفاصيل من
 * endpoint الـ show بالمعرّف اللي بالإشعار. هون بنتأكد إن التفاصيل بترجع بشكل
 * بتقدر النافذة تعرضه (نصوص مش كائنات JSON).
 */
class AdminNotificationDetailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    private function compliantContractor(): Contractor
    {
        $contractor = Contractor::create([
            'name'               => 'شركة الإشعارات للمقاولات',
            'membership_number'  => '777_g',
            'status'             => 'active',
            'is_frozen'          => false,
            'owner_name'         => 'مالك الشركة',
            'authorized_person'  => 'مفوّض التوقيع',
            'phone'              => '0590000001',
            'address'            => 'غزة - الرمال',
            'license_number'     => 'LIC-1',
            'established_date'   => '2010-01-01',
            'capital'            => 10000,
            'legal_form'         => 'شركة مساهمة',
            'registration_date'  => '2010-01-01',
            'company_purposes'   => 'مقاولات إنشائية',
            'city'               => 'غزة',
            'cr_file'                        => 'docs/cr.pdf',
            'company_register'               => 'docs/reg.pdf',
            'municipal_license'              => 'docs/muni.pdf',
            'bank_dealing_letter'            => 'docs/bank.pdf',
            'articles_of_association'        => 'docs/articles.pdf',
            'internal_bylaws'                => 'docs/bylaws.pdf',
            'lease_or_ownership_contract'    => 'docs/lease.pdf',
            'partners_ids'                   => 'docs/partners.pdf',
            'authorization_letter'           => 'docs/auth.pdf',
            'company_approval_letter'        => 'docs/approval.pdf',
            'full_time_engineer_certificate' => 'docs/engineer.pdf',
            'accountant_certificate_or_contract' => 'docs/accountant.pdf',
            'secretary_contract'             => 'docs/secretary.pdf',
        ]);

        Membership::create([
            'contractor_id' => $contractor->id,
            'type'          => 'new',
            'status'        => 'active',
            'expires_at'    => now()->addYear(),
        ]);

        return $contractor->fresh();
    }

    /** آخر إشعار وصل للأدمن كما تقرأه اللوحة من /api/v1/notifications */
    private function latestAdminNotificationData(User $admin): array
    {
        $this->app['auth']->forgetGuards();
        Sanctum::actingAs($admin, ['*']);

        $data = $this->getJson('/api/v1/notifications')
            ->assertOk()
            ->json('items.notifications.data.0.data');

        $this->assertIsArray($data);

        return $data;
    }

    public function test_certificate_request_notification_opens_full_details(): void
    {
        $admin      = User::factory()->create(['role' => 'admin']);
        $contractor = $this->compliantContractor();

        Sanctum::actingAs($contractor, ['*']);
        $this->postJson('/api/v1/contractor/certificate-requests', [
            'type'  => 'good_standing',
            'notes' => 'لتقديم عطاء',
        ])->assertStatus(201);

        $data = $this->latestAdminNotificationData($admin);
        $this->assertSame('certificate_request_submitted', $data['type']);
        $this->assertNotEmpty($data['message']);
        $this->assertNotEmpty($data['request_id']);

        $this->getJson("/api/v1/dashboard/certificate-requests/{$data['request_id']}")
            ->assertOk()
            ->assertJsonPath('items.id', $data['request_id'])
            ->assertJsonPath('items.contractor', 'شركة الإشعارات للمقاولات')
            ->assertJsonPath('items.membership_number', '777_g')
            ->assertJsonPath('items.type', 'good_standing')
            ->assertJsonPath('items.status', 'pending')
            ->assertJsonPath('items.notes', 'لتقديم عطاء');
    }

    public function test_support_ticket_notification_opens_readable_details(): void
    {
        $admin      = User::factory()->create(['role' => 'admin']);
        $contractor = $this->compliantContractor();

        Sanctum::actingAs($contractor, ['*']);
        $this->post('/api/v1/contractor/support-tickets', [
            'subject'        => 'مشكلة بالدخول',
            'whatsapp_phone' => '0599000000',
            'category'       => 'technical',
            'message'        => 'ما بقدر أفتح التطبيق',
            'attachment'     => UploadedFile::fake()->image('screen.png'),
        ], ['Accept' => 'application/json'])->assertSuccessful();

        $data = $this->latestAdminNotificationData($admin);
        $this->assertSame('support_ticket_created', $data['type']);
        $this->assertNotEmpty($data['ticket_id']);

        $res = $this->getJson("/api/v1/dashboard/support-tickets/{$data['ticket_id']}")
            ->assertOk()
            ->assertJsonPath('items.id', $data['ticket_id'])
            ->assertJsonPath('items.subject', 'مشكلة بالدخول')
            ->assertJsonPath('items.message', 'ما بقدر أفتح التطبيق')
            ->assertJsonPath('items.category_label', 'دعم فني')
            ->assertJsonPath('items.membership_number', '777_g');

        // نافذة اللوحة بتعرض contractor كنص — لو رجع كائن بيطلع JSON خام
        $this->assertSame('شركة الإشعارات للمقاولات', $res->json('items.contractor'));
        $this->assertIsString($res->json('items.attachment_url'));
        $this->assertIsArray($res->json('items.messages'));
    }
}

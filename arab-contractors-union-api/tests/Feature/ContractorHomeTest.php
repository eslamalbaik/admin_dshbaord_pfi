<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\CertificateRequest;
use App\Models\Contractor;
use App\Models\ContractorDue;
use App\Models\ContractorNameChangeRequest;
use App\Models\Equipment;
use App\Models\EquipmentType;
use App\Models\Membership;
use App\Models\News;
use App\Models\Payment;
use App\Models\Penalty;
use App\Models\Tender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ContractorHomeTest extends TestCase
{
    use RefreshDatabase;

    private function createContractor(array $attrs = []): Contractor
    {
        return Contractor::create(array_merge([
            'name'              => 'شركة اختبار للمقاولات',
            'membership_number' => '900_g',
            'status'            => 'active',
            'is_frozen'         => false,
        ], $attrs));
    }

    // ─────────────────────────────────────────────────────────────────────
    //  Auth / access control
    // ─────────────────────────────────────────────────────────────────────

    public function test_guest_cannot_access_home(): void
    {
        $this->getJson('/api/v1/contractor/home')->assertStatus(401);
    }

    public function test_suspended_contractor_is_blocked_with_force_logout(): void
    {
        $contractor = $this->createContractor(['status' => 'suspended']);
        Sanctum::actingAs($contractor, ['*']);

        $response = $this->getJson('/api/v1/contractor/home');

        $response->assertStatus(403)
            ->assertJson(['status' => false, 'force_logout' => true, 'error' => 'account_suspended']);
    }

    public function test_frozen_contractor_is_blocked(): void
    {
        $contractor = $this->createContractor(['is_frozen' => true]);
        Sanctum::actingAs($contractor, ['*']);

        $this->getJson('/api/v1/contractor/home')
            ->assertStatus(403)
            ->assertJson(['error' => 'account_frozen']);
    }

    public function test_expired_contractor_can_still_browse_home(): void
    {
        // متأخر بالدفع لا يعني حظر — يقدر يتصفح التطبيق بشكل طبيعي
        $contractor = $this->createContractor(['status' => 'expired']);
        Sanctum::actingAs($contractor, ['*']);

        $this->getJson('/api/v1/contractor/home')->assertStatus(200);
    }

    // ─────────────────────────────────────────────────────────────────────
    //  Response shape
    // ─────────────────────────────────────────────────────────────────────

    public function test_home_returns_expected_structure(): void
    {
        $contractor = $this->createContractor();
        Sanctum::actingAs($contractor, ['*']);

        $this->getJson('/api/v1/contractor/home')
            ->assertStatus(200)
            ->assertJsonStructure([
                'status', 'message', 'status_code',
                'items' => [
                    'contractor' => ['id', 'name', 'membership_number', 'logo'],
                    'membership' => ['status', 'badge', 'expires_at', 'expiring_soon', 'days_remaining'],
                    'financial'  => ['balance', 'has_overdue', 'last_invoice'],
                    'stats'      => ['announcements_count', 'machinery_count', 'new_tenders_count'],
                    'cta_certificate' => ['show', 'has_pending_dues', 'outstanding_amount'],
                    'latest_updates',
                ],
            ]);
    }

    // ─────────────────────────────────────────────────────────────────────
    //  Membership badge
    // ─────────────────────────────────────────────────────────────────────

    public function test_badge_is_active_only_with_unexpired_paid_membership(): void
    {
        $contractor = $this->createContractor(['status' => 'active']);
        Membership::create([
            'contractor_id' => $contractor->id,
            'type'          => 'renewal',
            'status'        => 'active',
            'expires_at'    => now()->addDays(60),
        ]);
        Sanctum::actingAs($contractor, ['*']);

        $this->getJson('/api/v1/contractor/home')
            ->assertJsonPath('items.membership.badge', 'active')
            ->assertJsonPath('items.membership.expiring_soon', false);
    }

    public function test_badge_is_not_active_when_membership_lapsed_even_if_contractor_status_says_active(): void
    {
        // العضوية بقيت status=active بقاعدة البيانات لكن تاريخها فات — bug كان موجود:
        // كان الكود يرجع badge=contractor->status (active) بالغلط بهذي الحالة
        $contractor = $this->createContractor(['status' => 'active']);
        Membership::create([
            'contractor_id' => $contractor->id,
            'type'          => 'renewal',
            'status'        => 'active',
            'expires_at'    => now()->subDays(5),
        ]);
        Sanctum::actingAs($contractor, ['*']);

        $badge = $this->getJson('/api/v1/contractor/home')->json('items.membership.badge');

        $this->assertNotEquals('active', $badge);
        $this->assertEquals('expired', $badge);
    }

    public function test_expiring_soon_flag_within_30_days(): void
    {
        $contractor = $this->createContractor();
        Membership::create([
            'contractor_id' => $contractor->id,
            'type'          => 'renewal',
            'status'        => 'active',
            'expires_at'    => now()->addDays(10),
        ]);
        Sanctum::actingAs($contractor, ['*']);

        $this->getJson('/api/v1/contractor/home')
            ->assertJsonPath('items.membership.expiring_soon', true)
            ->assertJsonPath('items.membership.badge', 'active');
    }

    // ─────────────────────────────────────────────────────────────────────
    //  Financial summary
    // ─────────────────────────────────────────────────────────────────────

    public function test_financial_balance_sums_dues_payments_and_penalties(): void
    {
        $contractor = $this->createContractor();

        ContractorDue::create([
            'contractor_id' => $contractor->id,
            'description'   => 'رسوم اشتراك 2024',
            'amount_jod'    => 100,
            'paid_jod'      => 40,
            'status'        => 'partially_paid',
        ]);
        Payment::create([
            'contractor_id' => $contractor->id,
            'amount'        => 50,
            'status'        => 'pending',
        ]);
        Penalty::create([
            'contractor_id' => $contractor->id,
            'reason'        => 'تأخير سداد',
            'amount'        => 20,
            'status'        => 'unpaid',
        ]);

        Sanctum::actingAs($contractor, ['*']);
        $balance = $this->getJson('/api/v1/contractor/home')->json('items.financial.balance');

        // (100-40) due + 50 pending payment + 20 penalty = 130
        $this->assertEquals('130.00', $balance);
    }

    public function test_has_overdue_true_only_for_unpaid_due_past_due_date(): void
    {
        $contractor = $this->createContractor();
        ContractorDue::create([
            'contractor_id' => $contractor->id,
            'description'   => 'ذمة متأخرة',
            'amount_jod'    => 30,
            'status'        => 'unpaid',
            'due_date'      => now()->subDays(10),
        ]);
        Sanctum::actingAs($contractor, ['*']);

        $this->getJson('/api/v1/contractor/home')->assertJsonPath('items.financial.has_overdue', true);
    }

    public function test_cta_certificate_flags_pending_dues(): void
    {
        $contractor = $this->createContractor();
        ContractorDue::create([
            'contractor_id' => $contractor->id,
            'description'   => 'ذمة غير مسددة',
            'amount_jod'    => 75,
            'status'        => 'unpaid',
        ]);
        Sanctum::actingAs($contractor, ['*']);

        $this->getJson('/api/v1/contractor/home')
            ->assertJsonPath('items.cta_certificate.show', true)
            ->assertJsonPath('items.cta_certificate.has_pending_dues', true);
    }

    // ─────────────────────────────────────────────────────────────────────
    //  Stats — machinery isolated per contractor, tenders window, events
    // ─────────────────────────────────────────────────────────────────────

    public function test_machinery_count_only_counts_this_contractor_equipment(): void
    {
        $type = EquipmentType::create(['name_ar' => 'حفارة']);
        $me      = $this->createContractor(['membership_number' => '901_g']);
        $another = $this->createContractor(['membership_number' => '902_g', 'name' => 'مقاول آخر']);

        Equipment::create(['contractor_id' => $me->id, 'equipment_type_id' => $type->id, 'name' => 'حفارة 1']);
        Equipment::create(['contractor_id' => $me->id, 'equipment_type_id' => $type->id, 'name' => 'حفارة 2']);
        Equipment::create(['contractor_id' => $another->id, 'equipment_type_id' => $type->id, 'name' => 'حفارة غيره']);

        Sanctum::actingAs($me, ['*']);

        $this->getJson('/api/v1/contractor/home')->assertJsonPath('items.stats.machinery_count', 2);
    }

    public function test_new_tenders_count_only_includes_open_tenders_within_7_days(): void
    {
        $contractor = $this->createContractor();

        $recentOpen = Tender::create(['title' => 'عطاء حديث', 'status' => 'open']);

        $oldOpen = Tender::create(['title' => 'عطاء قديم', 'status' => 'open']);
        $oldOpen->created_at = now()->subDays(20);
        $oldOpen->save();

        Tender::create(['title' => 'عطاء مغلق حديث', 'status' => 'closed']);

        Sanctum::actingAs($contractor, ['*']);

        $this->getJson('/api/v1/contractor/home')->assertJsonPath('items.stats.new_tenders_count', 1);
    }

    public function test_announcements_count_only_counts_published_announcements(): void
    {
        $contractor = $this->createContractor();

        Announcement::create([
            'title' => 'إعلان منشور', 'body' => 'x', 'is_published' => true, 'published_at' => now(),
        ]);
        Announcement::create([
            'title' => 'إعلان غير منشور', 'body' => 'x', 'is_published' => false,
        ]);
        News::create([
            'title' => 'خبر عادي', 'slug' => 'plain-news', 'body' => 'x',
            'category' => 'news', 'is_published' => true, 'published_at' => now(),
        ]);

        Sanctum::actingAs($contractor, ['*']);

        $this->getJson('/api/v1/contractor/home')->assertJsonPath('items.stats.announcements_count', 1);
    }

    // ─────────────────────────────────────────────────────────────────────
    //  Latest updates feed
    // ─────────────────────────────────────────────────────────────────────

    public function test_latest_updates_caps_at_ten_and_sorts_by_recency(): void
    {
        $contractor = $this->createContractor();

        for ($i = 0; $i < 15; $i++) {
            Tender::create(['title' => "عطاء $i", 'status' => 'open']);
        }

        Sanctum::actingAs($contractor, ['*']);
        $updates = $this->getJson('/api/v1/contractor/home')->json('items.latest_updates');

        $this->assertCount(10, $updates);
        $this->assertEquals('عطاء 14', $updates[0]['title']); // آخر عنصر أُنشئ يظهر أولاً
    }

    public function test_new_badge_applied_within_24_hours(): void
    {
        $contractor = $this->createContractor();

        $fresh = Tender::create(['title' => 'عطاء جديد جداً', 'status' => 'open']);

        $old = Tender::create(['title' => 'عطاء قديم شوي', 'status' => 'open']);
        $old->created_at = now()->subDays(2);
        $old->save();

        Sanctum::actingAs($contractor, ['*']);
        $updates = collect($this->getJson('/api/v1/contractor/home')->json('items.latest_updates'))
            ->keyBy('title');

        $this->assertContains('جديد', $updates['عطاء جديد جداً']['badges']);
        $this->assertNotContains('جديد', $updates['عطاء قديم شوي']['badges']);
    }

    public function test_rejected_certificate_request_shows_rejected_badge_and_high_priority(): void
    {
        $contractor = $this->createContractor();
        CertificateRequest::create([
            'contractor_id' => $contractor->id,
            'type'          => 'membership',
            'status'        => 'rejected',
            'reject_reason' => 'مستندات ناقصة',
        ]);

        Sanctum::actingAs($contractor, ['*']);
        $updates = $this->getJson('/api/v1/contractor/home')->json('items.latest_updates');

        $item = collect($updates)->firstWhere('type', 'member');
        $this->assertNotNull($item);
        $this->assertContains('مرفوض', $item['badges']);
        $this->assertEquals('high', $item['priority']);
    }

    public function test_finance_feed_item_is_scoped_to_owning_contractor(): void
    {
        $me      = $this->createContractor(['membership_number' => '903_g']);
        $another = $this->createContractor(['membership_number' => '904_g', 'name' => 'مقاول آخر']);

        ContractorDue::create([
            'contractor_id' => $another->id,
            'description'   => 'ذمة تخص مقاول آخر',
            'amount_jod'    => 40,
            'status'        => 'unpaid',
        ]);

        Sanctum::actingAs($me, ['*']);
        $updates = $this->getJson('/api/v1/contractor/home')->json('items.latest_updates');

        $this->assertFalse(collect($updates)->contains('title', 'ذمة تخص مقاول آخر'));
    }

    public function test_name_change_request_rejected_flags_member_update(): void
    {
        $contractor = $this->createContractor();
        ContractorNameChangeRequest::create([
            'contractor_id'        => $contractor->id,
            'current_name'         => 'اسم قديم',
            'requested_name'       => 'اسم جديد',
            'supporting_document'  => 'docs/name-change.pdf',
            'status'               => 'rejected',
        ]);

        Sanctum::actingAs($contractor, ['*']);
        $updates = collect($this->getJson('/api/v1/contractor/home')->json('items.latest_updates'));

        $item = $updates->firstWhere('title', 'طلب تغيير الاسم');
        $this->assertNotNull($item);
        $this->assertContains('مرفوض', $item['badges']);
    }

    // ─────────────────────────────────────────────────────────────────────
    //  GET /contractor/home/updates — full paginated history
    // ─────────────────────────────────────────────────────────────────────

    public function test_updates_endpoint_paginates_at_twenty_per_page(): void
    {
        $contractor = $this->createContractor();

        for ($i = 0; $i < 25; $i++) {
            Tender::create(['title' => "عطاء رقم $i", 'status' => 'open']);
        }

        Sanctum::actingAs($contractor, ['*']);

        $page1 = $this->getJson('/api/v1/contractor/home/updates');
        $page1->assertStatus(200);
        $this->assertCount(20, $page1->json('items'));
        $this->assertEquals(25, $page1->json('meta.total'));
        $this->assertEquals(2, $page1->json('meta.last_page'));

        $page2 = $this->getJson('/api/v1/contractor/home/updates?page=2');
        $this->assertCount(5, $page2->json('items'));
    }

    public function test_updates_endpoint_requires_auth(): void
    {
        $this->getJson('/api/v1/contractor/home/updates')->assertStatus(401);
    }
}

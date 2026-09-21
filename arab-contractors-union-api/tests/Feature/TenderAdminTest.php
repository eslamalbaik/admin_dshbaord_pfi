<?php

namespace Tests\Feature;

use App\Models\Contractor;
use App\Models\Tender;
use App\Models\TenderAttachment;
use App\Models\User;
use App\Notifications\NewTenderPublishedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TenderAdminTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAdmin(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']), ['*']);
    }

    // ─────────────────────────────────────────────────────────────────────
    //  store — deadline must be in the future (REQ-07 #1/#2)
    // ─────────────────────────────────────────────────────────────────────

    public function test_store_rejects_deadline_in_the_past(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/v1/tenders', [
            'title'    => 'عطاء بموعد فائت',
            'deadline' => now()->subDay()->toDateTimeString(),
        ])->assertStatus(422)->assertJsonValidationErrors(['deadline']);

        $this->assertDatabaseCount('tenders', 0);
    }

    public function test_store_accepts_deadline_with_time_component_in_the_future(): void
    {
        $this->actingAsAdmin();

        $deadline = now()->addDays(3)->setTime(14, 30);

        $response = $this->postJson('/api/v1/tenders', [
            'title'    => 'عطاء بموعد مستقبلي',
            'deadline' => $deadline->toDateTimeString(),
        ]);

        $response->assertStatus(201);
        $this->assertSame($deadline->format('H:i'), Tender::first()->deadline->format('H:i'));
    }

    // ─────────────────────────────────────────────────────────────────────
    //  update — never touches attachments (they're a separate resource/table)
    // ─────────────────────────────────────────────────────────────────────

    public function test_update_does_not_drop_existing_attachments(): void
    {
        $this->actingAsAdmin();

        $tender = Tender::create(['title' => 'عطاء له مرفقات', 'status' => 'open']);
        TenderAttachment::create(['tender_id' => $tender->id, 'file_path' => 'tenders/attachments/a.pdf', 'label' => 'a.pdf']);
        TenderAttachment::create(['tender_id' => $tender->id, 'file_path' => 'tenders/attachments/b.pdf', 'label' => 'b.pdf']);

        // تعديل لا يتضمن أي شيء عن المرفقات إطلاقاً — يجب أن يبقيا كما هما
        $this->patchJson("/api/v1/tenders/{$tender->id}", ['title' => 'عطاء معدّل'])
            ->assertStatus(200)
            ->assertJsonPath('items.title', 'عطاء معدّل');

        $this->assertDatabaseCount('tender_attachments', 2);
        $this->assertEquals(2, $tender->fresh()->attachments()->count());
    }

    // ─────────────────────────────────────────────────────────────────────
    //  attachments — delete
    // ─────────────────────────────────────────────────────────────────────

    public function test_destroy_attachment_removes_only_the_targeted_attachment(): void
    {
        $this->actingAsAdmin();

        $tender = Tender::create(['title' => 'عطاء له مرفقات', 'status' => 'open']);
        $keep   = TenderAttachment::create(['tender_id' => $tender->id, 'file_path' => 'tenders/attachments/keep.pdf', 'label' => 'keep.pdf']);
        $remove = TenderAttachment::create(['tender_id' => $tender->id, 'file_path' => 'tenders/attachments/remove.pdf', 'label' => 'remove.pdf']);

        $this->deleteJson("/api/v1/tenders/{$tender->id}/attachments/{$remove->id}")
            ->assertStatus(200);

        $this->assertDatabaseMissing('tender_attachments', ['id' => $remove->id]);
        $this->assertDatabaseHas('tender_attachments', ['id' => $keep->id]);
    }

    public function test_destroy_attachment_rejects_mismatched_tender(): void
    {
        $this->actingAsAdmin();

        $tenderA = Tender::create(['title' => 'عطاء أ', 'status' => 'open']);
        $tenderB = Tender::create(['title' => 'عطاء ب', 'status' => 'open']);
        $attachment = TenderAttachment::create(['tender_id' => $tenderB->id, 'file_path' => 'tenders/attachments/x.pdf', 'label' => 'x.pdf']);

        $this->deleteJson("/api/v1/tenders/{$tenderA->id}/attachments/{$attachment->id}")
            ->assertStatus(422);

        $this->assertDatabaseHas('tender_attachments', ['id' => $attachment->id]);
    }

    public function test_tender_routes_require_auth(): void
    {
        $this->postJson('/api/v1/tenders', ['title' => 'x'])->assertStatus(401);
    }

    // ─────────────────────────────────────────────────────────────────────
    //  store — يُشعر المقاولين بالعطاء الجديد (database + push)
    // ─────────────────────────────────────────────────────────────────────

    private function contractor(array $attrs = []): Contractor
    {
        return Contractor::create(array_merge([
            'name'              => 'شركة اختبار للمقاولات',
            'membership_number' => '990_g',
            'status'            => 'active',
            'is_frozen'         => false,
        ], $attrs));
    }

    public function test_store_notifies_non_frozen_contractors(): void
    {
        Notification::fake();
        $this->actingAsAdmin();

        $contractor = $this->contractor();

        $this->postJson('/api/v1/tenders', ['title' => 'عطاء جديد للطرق'])
            ->assertStatus(201);

        Notification::assertSentTo($contractor, NewTenderPublishedNotification::class);
    }

    public function test_store_does_not_notify_frozen_contractors(): void
    {
        Notification::fake();
        $this->actingAsAdmin();

        // الاثنان معاً عمداً: لو أُرسل الإشعار لأحد فقط، assertNotSentTo وحده كان
        // سينجح حتى لو لم يُرسَل شيء إطلاقاً — نثبّت الطرفين ليبقى الاختبار ذا معنى.
        $active = $this->contractor(['membership_number' => '990_g', 'is_frozen' => false]);
        $frozen = $this->contractor(['membership_number' => '991_g', 'is_frozen' => true]);

        $this->postJson('/api/v1/tenders', ['title' => 'عطاء جديد'])
            ->assertStatus(201);

        Notification::assertSentTo($active, NewTenderPublishedNotification::class);
        Notification::assertNotSentTo($frozen, NewTenderPublishedNotification::class);
    }

    public function test_store_does_not_notify_when_tender_is_created_already_closed(): void
    {
        Notification::fake();
        $this->actingAsAdmin();

        $this->contractor();

        $this->postJson('/api/v1/tenders', ['title' => 'عطاء مغلق', 'status' => 'closed'])
            ->assertStatus(201);

        Notification::assertNothingSent();
    }

    /** الحمولة تُغذّي الـdeep link في التطبيق — tender_id ورقم مرجعي غير فارغين. */
    public function test_new_tender_notification_payload_carries_tender_identifiers(): void
    {
        Notification::fake();
        $this->actingAsAdmin();

        $contractor = $this->contractor();

        $this->postJson('/api/v1/tenders', ['title' => 'عطاء بمرجع'])
            ->assertStatus(201);

        $tender = Tender::first();

        Notification::assertSentTo(
            $contractor,
            NewTenderPublishedNotification::class,
            function ($notification) use ($contractor, $tender) {
                $payload = $notification->toArray($contractor);

                return $payload['type'] === 'new_tender_published'
                    && $payload['tender_id'] === $tender->id
                    && $payload['reference_number'] === $tender->reference_number
                    && ! empty($payload['reference_number'])
                    && ! empty($payload['message']);
            },
        );
    }
}

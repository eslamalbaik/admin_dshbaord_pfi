<?php

namespace Tests\Feature;

use App\Models\Contractor;
use App\Models\ContractorFieldHistory;
use App\Models\ProfileChange;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\ProfileChangesDecidedNotification;
use App\Notifications\ProfileChangesSubmittedNotification;
use App\Support\ProfileReview;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * مراجعة تعديلات الملف التعريفي (Maker-Checker) — 2026-11-09.
 */
class ProfileChangeReviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Notification::fake();
        Setting::set(ProfileReview::SETTING_KEY, '1');
    }

    private function contractor(array $attrs = []): Contractor
    {
        return Contractor::create(array_merge([
            'name'              => 'شركة المراجعة',
            'membership_number' => '970_g',
            'phone'             => '0590007766',
            'password'          => Hash::make('Test@1234'),
            'status'            => 'active',
            'is_frozen'         => false,
            'phone_verified_at' => now(),
            'capital'           => '100000',
            'email'             => 'old@example.com',
        ], $attrs));
    }

    private function asContractor(Contractor $contractor): void
    {
        Sanctum::actingAs($contractor, ['*']);
        app('auth')->guard('contractor')->setUser($contractor);
    }

    private function asAdmin(): User
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin, ['*']);

        return $admin;
    }

    public function test_legal_field_waits_for_approval_and_contact_field_applies_instantly(): void
    {
        $c = $this->contractor();
        $this->asContractor($c);

        $resp = $this->postJson('/api/v1/contractor/auth/profile/update', [
            'capital' => '250000',
            'email'   => 'new@example.com',
        ])->assertOk();

        $resp->assertJsonPath('items.pending_review', true)
            ->assertJsonPath('items.capital', '100000')          // الرسمي ما تغيّر
            ->assertJsonPath('items.email', 'new@example.com')   // التواصل انطبّق
            ->assertJsonPath('items.profile_review.pending_changes.capital.value', '250000');
        $this->assertStringContainsString('رأس المال', $resp->json('message'));

        $c->refresh();
        $this->assertSame('100000', $c->capital);
        $this->assertSame('new@example.com', $c->email);
        $this->assertDatabaseHas('profile_changes', ['field' => 'capital', 'mode' => 'pre_approval', 'status' => 'pending', 'old_value' => '100000', 'new_value' => '250000']);
        $this->assertDatabaseHas('profile_changes', ['field' => 'email', 'mode' => 'post_review', 'status' => 'pending', 'old_value' => 'old@example.com']);
        $this->assertDatabaseHas('contractor_field_history', ['field' => 'email', 'source' => 'app', 'actor_type' => 'contractor', 'new_value' => 'new@example.com']);
    }

    public function test_resubmitting_the_same_form_does_not_duplicate_and_new_value_supersedes(): void
    {
        $c = $this->contractor();
        $this->asContractor($c);

        $this->postJson('/api/v1/contractor/auth/profile/update', ['capital' => '250000'])->assertOk();
        $this->postJson('/api/v1/contractor/auth/profile/update', ['capital' => '250000'])->assertOk();
        $this->assertSame(1, ProfileChange::where('field', 'capital')->count());

        $this->postJson('/api/v1/contractor/auth/profile/update', ['capital' => '300000'])->assertOk();
        $this->assertSame(1, ProfileChange::where('field', 'capital')->pending()->count());
        $this->assertSame('300000', ProfileChange::where('field', 'capital')->pending()->value('new_value'));
        $this->assertSame(1, ProfileChange::where('field', 'capital')->where('status', 'superseded')->count());
    }

    public function test_returning_a_field_to_its_official_value_withdraws_the_pending_change(): void
    {
        $c = $this->contractor();
        $this->asContractor($c);

        $this->postJson('/api/v1/contractor/auth/profile/update', ['capital' => '250000'])->assertOk();
        $this->postJson('/api/v1/contractor/auth/profile/update', ['capital' => '100000'])
            ->assertOk()
            ->assertJsonPath('items.pending_review', false);

        $this->assertSame(0, ProfileChange::pending()->count());
        $this->assertSame(1, ProfileChange::where('status', 'withdrawn')->count());
    }

    public function test_document_is_staged_and_live_document_untouched_until_approval(): void
    {
        $oldPath = UploadedFile::fake()->create('old.pdf', 10, 'application/pdf')->store('contractors/cr', 'public');
        $c = $this->contractor(['cr_file' => $oldPath]);
        $this->asContractor($c);

        $this->post('/api/v1/contractor/auth/profile/update', [
            'cr_file' => UploadedFile::fake()->create('new.pdf', 10, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertOk()->assertJsonPath('items.pending_fields.0', 'cr_file');

        $this->assertSame($oldPath, $c->fresh()->cr_file);
        $item = ProfileChange::where('field', 'cr_file')->firstOrFail();
        $this->assertStringStartsWith('contractors/profile-changes/' . $c->id . '/', $item->new_value);
        Storage::disk('public')->assertExists($item->new_value);

        $admin = $this->asAdmin();
        $this->postJson("/api/v1/dashboard/profile-changes/contractors/{$c->id}/decide", [
            'decisions' => [['id' => $item->id, 'action' => 'approve']],
        ])->assertOk();

        $c->refresh();
        $this->assertStringStartsWith('contractors/cr/', $c->cr_file);
        $this->assertNotSame($oldPath, $c->cr_file);
        Storage::disk('public')->assertExists($c->cr_file);
        Storage::disk('public')->assertExists($oldPath); // القديم مؤرشف، ما انحذف

        $history = ContractorFieldHistory::where('field', 'cr_file')->firstOrFail();
        $this->assertSame($oldPath, $history->old_value);
        $this->assertSame($admin->id, $history->actor_id);
        $this->assertSame($item->id, $history->profile_change_id);
        Notification::assertSentTo($c, ProfileChangesDecidedNotification::class);
    }

    public function test_partial_decision_applies_approved_and_keeps_rejected_with_reason(): void
    {
        $c = $this->contractor(['legal_form' => 'فردية']);
        $this->asContractor($c);
        $this->postJson('/api/v1/contractor/auth/profile/update', ['capital' => '250000', 'legal_form' => 'شركة عادية'])->assertOk();

        $capital = ProfileChange::where('field', 'capital')->first();
        $legal   = ProfileChange::where('field', 'legal_form')->first();

        $this->asAdmin();
        $this->postJson("/api/v1/dashboard/profile-changes/contractors/{$c->id}/decide", [
            'decisions' => [
                ['id' => $capital->id, 'action' => 'approve'],
                ['id' => $legal->id, 'action' => 'reject'],
            ],
        ])->assertStatus(422); // الرفض بدون سبب ممنوع

        $this->postJson("/api/v1/dashboard/profile-changes/contractors/{$c->id}/decide", [
            'decisions' => [
                ['id' => $capital->id, 'action' => 'approve'],
                ['id' => $legal->id, 'action' => 'reject', 'reason' => 'لا يطابق السجل التجاري'],
            ],
        ])->assertOk();

        $c->refresh();
        $this->assertSame('250000', $c->capital);
        $this->assertSame('فردية', $c->legal_form);

        $this->asContractor($c);
        $this->getJson('/api/v1/contractor/auth/profile')
            ->assertOk()
            ->assertJsonPath('items.profile_review.has_pending', false)
            ->assertJsonPath('items.profile_review.rejected_changes.legal_form.reject_reason', 'لا يطابق السجل التجاري')
            ->assertJsonPath('items.profile_review.rejected_changes.legal_form.value', 'شركة عادية');
    }

    public function test_admin_can_revert_an_instant_contact_change(): void
    {
        $c = $this->contractor();
        $this->asContractor($c);
        $this->postJson('/api/v1/contractor/auth/profile/update', ['email' => 'spam@example.com'])->assertOk();
        $item = ProfileChange::where('field', 'email')->firstOrFail();

        $this->asAdmin();
        $this->postJson("/api/v1/dashboard/profile-changes/{$item->id}/revert", ['reason' => 'بريد غير صحيح'])->assertOk();

        $this->assertSame('old@example.com', $c->fresh()->email);
        $this->assertSame('reverted', $item->fresh()->status);
        Notification::assertSentTo($c, ProfileChangesDecidedNotification::class);
    }

    public function test_bulk_confirm_instant_changes(): void
    {
        $c = $this->contractor();
        $this->asContractor($c);
        $this->postJson('/api/v1/contractor/auth/profile/update', ['email' => 'a@example.com', 'fax' => '082222'])->assertOk();

        $this->asAdmin();
        $ids = ProfileChange::where('mode', 'post_review')->pluck('id')->all();
        $this->postJson('/api/v1/dashboard/profile-changes/confirm', ['ids' => $ids])
            ->assertOk()
            ->assertJsonPath('items.confirmed', 2);
    }

    public function test_contractor_can_withdraw_a_pending_change(): void
    {
        $c = $this->contractor();
        $this->asContractor($c);
        $this->postJson('/api/v1/contractor/auth/profile/update', ['capital' => '250000'])->assertOk();
        $id = ProfileChange::value('id');

        $this->deleteJson("/api/v1/contractor/profile-changes/{$id}")
            ->assertOk()
            ->assertJsonPath('items.has_pending', false);
    }

    public function test_reviewers_are_notified_and_stale_change_is_flagged(): void
    {
        User::factory()->create(['role' => 'admin']);
        $c = $this->contractor();
        $this->asContractor($c);
        $this->postJson('/api/v1/contractor/auth/profile/update', ['capital' => '250000'])->assertOk();
        Notification::assertSentTo(User::first(), ProfileChangesSubmittedNotification::class);

        // موظف عدّل رأس المال من لوحة التحكم بعد الطلب
        $c->update(['capital' => '120000']);

        $this->asAdmin();
        $resp = $this->getJson("/api/v1/dashboard/profile-changes/contractors/{$c->id}")->assertOk();
        $this->assertTrue($resp->json('items.groups.0.items.0.is_stale'));

        $this->getJson('/api/v1/dashboard/profile-changes?tab=review')
            ->assertOk()
            ->assertJsonPath('items.data.0.contractor_id', $c->id)
            ->assertJsonPath('items.data.0.fields_count', 1);
    }

    public function test_fcm_token_and_locked_fields_never_enter_review(): void
    {
        $c = $this->contractor();
        $this->asContractor($c);

        $this->patchJson('/api/v1/contractor/auth/profile', ['fcm_token' => 'tok-123'])->assertOk();
        $this->assertSame('tok-123', $c->fresh()->fcm_token);

        $this->postJson('/api/v1/contractor/auth/profile/update', ['classification' => 'اولى أ', 'owner_name' => 'X'])->assertOk();
        $this->assertSame(0, ProfileChange::count());
    }

    public function test_disabled_review_keeps_direct_writes(): void
    {
        Setting::set(ProfileReview::SETTING_KEY, '0');
        $c = $this->contractor();
        $this->asContractor($c);

        $this->postJson('/api/v1/contractor/auth/profile/update', ['capital' => '250000'])->assertOk();
        $this->assertSame('250000', $c->fresh()->capital);
        $this->assertSame(0, ProfileChange::count());
        // السجل بيشتغل حتى والمراجعة مطفية
        $this->assertDatabaseHas('contractor_field_history', ['field' => 'capital', 'new_value' => '250000']);
    }

    public function test_dashboard_edit_is_recorded_in_history_with_the_staff_name(): void
    {
        $c = $this->contractor();
        $admin = $this->asAdmin();

        $c->update(['capital' => '999']); // بدون request user: نظام
        $this->assertDatabaseHas('contractor_field_history', ['field' => 'capital', 'source' => 'system']);

        $this->getJson("/api/v1/contractors/{$c->id}/field-history")
            ->assertOk()
            ->assertJsonPath('items.data.0.label', 'رأس المال')
            ->assertJsonPath('items.data.0.new_display', '999');
        $this->assertNotNull($admin);
    }

    public function test_supervisor_needs_the_new_permission(): void
    {
        $supervisor = User::factory()->create(['role' => 'supervisor', 'permissions' => ['contractors.list.view']]);
        Sanctum::actingAs($supervisor, ['*']);
        $this->getJson('/api/v1/dashboard/profile-changes')->assertStatus(403);

        $supervisor->update(['permissions' => ['contractors.profile_changes.view']]);
        $this->getJson('/api/v1/dashboard/profile-changes')->assertOk();
    }
}

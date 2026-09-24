<?php

namespace Tests\Feature;

use App\Models\Tender;
use App\Models\TenderCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TenderCategoryTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAdmin(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']), ['*']);
    }

    public function test_migration_seeds_the_initial_categories(): void
    {
        $this->assertEqualsCanonicalizing(Tender::CATEGORIES, TenderCategory::pluck('name')->all());
    }

    public function test_admin_can_create_category_with_default_image(): void
    {
        Storage::fake('public');
        $this->actingAsAdmin();

        $response = $this->post('/api/v1/tender-categories', [
            'name'  => 'كهرباء',
            'image' => UploadedFile::fake()->image('c.png'),
        ], ['Accept' => 'application/json'])->assertStatus(201);

        $category = TenderCategory::where('name', 'كهرباء')->firstOrFail();
        Storage::disk('public')->assertExists($category->image_path);
        $this->assertNotNull($response->json('items.image_url'));
    }

    public function test_new_category_is_accepted_by_tender_store_and_its_image_is_exposed_publicly(): void
    {
        Storage::fake('public');
        $this->actingAsAdmin();

        $this->post('/api/v1/tender-categories', [
            'name'  => 'كهرباء',
            'image' => UploadedFile::fake()->image('c.png'),
        ], ['Accept' => 'application/json'])->assertStatus(201);

        $tender = $this->postJson('/api/v1/tenders', ['title' => 'عطاء', 'category' => 'كهرباء'])
            ->assertStatus(201)->json('items');

        $this->getJson("/api/v1/tenders-public/{$tender['id']}")
            ->assertOk()
            ->assertJsonPath('items.category', 'كهرباء')
            ->assertJsonPath('items.category_image', TenderCategory::where('name', 'كهرباء')->first()->image_url);
    }

    public function test_inactive_category_is_rejected_for_new_tenders_but_kept_on_existing_ones(): void
    {
        $this->actingAsAdmin();

        $tender = Tender::create(['title' => 'عطاء', 'category' => 'طرق', 'status' => 'open']);
        TenderCategory::where('name', 'طرق')->update(['is_active' => false]);

        $this->postJson('/api/v1/tenders', ['title' => 'جديد', 'category' => 'طرق'])
            ->assertStatus(422)->assertJsonValidationErrors(['category']);

        // تعديل عطاء قائم على تصنيف معطَّل لا يجب أن يفشل بسبب التصنيف
        $this->patchJson("/api/v1/tenders/{$tender->id}", ['title' => 'معدّل', 'category' => 'طرق'])
            ->assertOk();
    }

    public function test_renaming_category_renames_it_on_tenders_without_marking_them_updated(): void
    {
        $this->actingAsAdmin();

        $tender   = Tender::create(['title' => 'عطاء', 'category' => 'طرق', 'status' => 'open']);
        $category = TenderCategory::where('name', 'طرق')->first();

        $this->patchJson("/api/v1/tender-categories/{$category->id}", ['name' => 'طرق وجسور'])->assertOk();

        $this->assertSame('طرق وجسور', $tender->fresh()->category);
        $this->assertSame('new', $tender->fresh()->display_status);
    }

    public function test_category_in_use_cannot_be_deleted(): void
    {
        $this->actingAsAdmin();

        Tender::create(['title' => 'عطاء', 'category' => 'طرق', 'status' => 'open']);
        $used   = TenderCategory::where('name', 'طرق')->first();
        $unused = TenderCategory::where('name', 'مباني')->first();

        $this->deleteJson("/api/v1/tender-categories/{$used->id}")->assertStatus(422);
        $this->deleteJson("/api/v1/tender-categories/{$unused->id}")->assertOk();

        $this->assertDatabaseHas('tender_categories', ['id' => $used->id]);
        $this->assertDatabaseMissing('tender_categories', ['id' => $unused->id]);
    }

    public function test_replacing_image_deletes_old_file(): void
    {
        Storage::fake('public');
        $this->actingAsAdmin();

        $category = TenderCategory::where('name', 'طرق')->first();

        $this->post("/api/v1/tender-categories/{$category->id}", ['_method' => 'PATCH', 'image' => UploadedFile::fake()->image('a.png')], ['Accept' => 'application/json'])->assertOk();
        $old = $category->fresh()->image_path;

        $this->post("/api/v1/tender-categories/{$category->id}", ['_method' => 'PATCH', 'image' => UploadedFile::fake()->image('b.png')], ['Accept' => 'application/json'])->assertOk();

        Storage::disk('public')->assertMissing($old);
        Storage::disk('public')->assertExists($category->fresh()->image_path);
    }

    // ─────────────────────────────────────────────────────────────────────
    //  deadline — تاريخ اليوم + 1 على الأقل، بالساعة والدقيقة
    // ─────────────────────────────────────────────────────────────────────

    public function test_store_rejects_deadline_later_today(): void
    {
        $this->actingAsAdmin();
        Carbon::setTestNow('2026-09-24 08:00:00'); // 11:00 بتوقيت غزة

        $this->postJson('/api/v1/tenders', ['title' => 'عطاء', 'deadline' => '2026-09-24T18:00:00.000Z'])
            ->assertStatus(422)->assertJsonValidationErrors(['deadline']);
    }

    public function test_store_accepts_early_tomorrow_local_time_and_keeps_hour_and_minute(): void
    {
        $this->actingAsAdmin();
        Carbon::setTestNow('2026-09-24 08:00:00');

        // 01:30 غداً بتوقيت غزة (UTC+3) = 22:30 اليوم UTC
        $this->postJson('/api/v1/tenders', ['title' => 'عطاء', 'deadline' => '2026-09-25T01:30:00+03:00'])
            ->assertStatus(201);

        $this->assertSame('2026-09-24 22:30:00', Tender::first()->deadline->toDateTimeString());
    }

    public function test_update_allows_keeping_an_old_deadline_but_rejects_a_new_one_before_tomorrow(): void
    {
        $this->actingAsAdmin();
        Carbon::setTestNow('2026-09-24 08:00:00');

        $tender = Tender::create(['title' => 'عطاء', 'status' => 'open', 'deadline' => now()->addHours(3)]);

        $this->patchJson("/api/v1/tenders/{$tender->id}", ['title' => 'معدّل', 'deadline' => $tender->deadline->toIso8601String()])
            ->assertOk();

        $this->patchJson("/api/v1/tenders/{$tender->id}", ['deadline' => now()->addHours(5)->toIso8601String()])
            ->assertStatus(422)->assertJsonValidationErrors(['deadline']);
    }

    // ─────────────────────────────────────────────────────────────────────
    //  submission_file — يبقى بعد الحفظ ما لم يُستبدل
    // ─────────────────────────────────────────────────────────────────────

    public function test_submission_file_survives_an_update_that_does_not_send_a_new_one(): void
    {
        Storage::fake('public');
        $this->actingAsAdmin();

        $id = $this->post('/api/v1/tenders', [
            'title'              => 'عطاء',
            'submission_types'   => ['file'],
            'submission_file'    => UploadedFile::fake()->create('form.pdf', 50, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertStatus(201)->json('items.id');

        $path = Tender::find($id)->getRawOriginal('submission_file');
        $this->assertNotNull($path);

        $this->post("/api/v1/tenders/{$id}", [
            '_method'          => 'PATCH',
            'title'            => 'معدّل',
            'submission_types' => ['file'],
        ], ['Accept' => 'application/json'])->assertOk();

        $this->assertSame($path, Tender::find($id)->getRawOriginal('submission_file'));
        Storage::disk('public')->assertExists($path);
    }

    public function test_replacing_submission_file_removes_the_old_one(): void
    {
        Storage::fake('public');
        $this->actingAsAdmin();

        $id = $this->post('/api/v1/tenders', [
            'title'           => 'عطاء',
            'submission_file' => UploadedFile::fake()->create('a.pdf', 50, 'application/pdf'),
        ], ['Accept' => 'application/json'])->json('items.id');
        $old = Tender::find($id)->getRawOriginal('submission_file');

        $this->post("/api/v1/tenders/{$id}", [
            '_method'         => 'PATCH',
            'submission_file' => UploadedFile::fake()->create('b.pdf', 50, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertOk();

        Storage::disk('public')->assertMissing($old);
        Storage::disk('public')->assertExists(Tender::find($id)->getRawOriginal('submission_file'));
    }
}

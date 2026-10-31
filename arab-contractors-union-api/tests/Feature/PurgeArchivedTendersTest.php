<?php

namespace Tests\Feature;

use App\Models\Tender;
use App\Models\TenderAttachment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PurgeArchivedTendersTest extends TestCase
{
    use RefreshDatabase;

    private function tender(array $attrs = []): Tender
    {
        return Tender::create(array_merge([
            'title'    => 'عطاء تجريبي',
            'status'   => 'closed',
            'deadline' => now()->subDays(100),
        ], $attrs));
    }

    public function test_deletes_tenders_archived_more_than_70_days_ago_with_files(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('tenders/files/old.pdf', 'x');
        Storage::disk('public')->put('tenders/attachments/old.png', 'x');

        $old = $this->tender([
            'archived_at'     => now()->subDays(71),
            'submission_file' => 'tenders/files/old.pdf',
        ]);
        TenderAttachment::create(['tender_id' => $old->id, 'file_path' => 'tenders/attachments/old.png', 'label' => 'صورة']);

        $this->artisan('tenders:purge')->assertSuccessful();

        $this->assertDatabaseMissing('tenders', ['id' => $old->id]);
        $this->assertDatabaseCount('tender_attachments', 0);
        Storage::disk('public')->assertMissing('tenders/files/old.pdf');
        Storage::disk('public')->assertMissing('tenders/attachments/old.png');
    }

    public function test_keeps_recently_archived_active_and_reopened_tenders(): void
    {
        Storage::fake('public');

        $recent = $this->tender(['archived_at' => now()->subDays(69)]);
        $active = $this->tender(['status' => 'open', 'deadline' => now()->addDays(5), 'archived_at' => null]);
        // أُرشف قديماً ثم مُدِّد موعده — لا يُحذف
        $reopened = $this->tender(['status' => 'open', 'deadline' => now()->addDays(5), 'archived_at' => now()->subDays(90)]);

        $this->artisan('tenders:purge')->assertSuccessful();

        $this->assertDatabaseHas('tenders', ['id' => $recent->id]);
        $this->assertDatabaseHas('tenders', ['id' => $active->id]);
        $this->assertDatabaseHas('tenders', ['id' => $reopened->id]);
    }

    public function test_dry_run_deletes_nothing(): void
    {
        $old = $this->tender(['archived_at' => now()->subDays(100)]);

        $this->artisan('tenders:purge --dry-run')->assertSuccessful();

        $this->assertDatabaseHas('tenders', ['id' => $old->id]);
    }

    public function test_also_purges_soft_deleted_archived_tenders(): void
    {
        $old = $this->tender(['archived_at' => now()->subDays(80)]);
        $old->delete();

        $this->artisan('tenders:purge')->assertSuccessful();

        $this->assertDatabaseMissing('tenders', ['id' => $old->id]);
    }
}

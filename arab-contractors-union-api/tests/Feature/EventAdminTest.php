<?php

namespace Tests\Feature;

use App\Models\Contractor;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EventAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    private function actingAsAdmin(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']), ['*']);
    }

    // ─────────────────────────────────────────────────────────────────────
    //  published_at — today or later on create (REQ-11 #2)
    // ─────────────────────────────────────────────────────────────────────

    public function test_store_rejects_published_at_in_the_past(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/v1/admin/events', [
            'title' => 'فعالية بتاريخ ماضٍ', 'body' => 'نص',
            'published_at' => now()->subDay()->toDateString(),
        ])->assertStatus(422)->assertJsonValidationErrors(['published_at']);

        $this->assertDatabaseCount('events', 0);
    }

    public function test_store_accepts_published_at_today(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/v1/admin/events', [
            'title' => 'فعالية بتاريخ اليوم', 'body' => 'نص',
            'published_at' => now()->toDateString(),
        ])->assertStatus(201);

        $this->assertDatabaseCount('events', 1);
    }

    public function test_update_allows_past_published_at_unchanged(): void
    {
        $this->actingAsAdmin();

        $event = Event::create([
            'title' => 'فعالية قديمة', 'body' => 'نص', 'slug' => 'old-event',
            'is_published' => true, 'published_at' => now()->subMonth(),
        ]);

        // تعديل حقل تاني بدون لمس published_at — ما لازم يُرفض بسبب تاريخ ماضٍ
        $this->putJson("/api/v1/admin/events/{$event->id}", [
            'title' => 'فعالية قديمة (معدّلة)', 'body' => 'نص',
            'published_at' => $event->published_at->toDateString(),
        ])->assertStatus(200);
    }

    // ─────────────────────────────────────────────────────────────────────
    //  event_location required for onsite AND hybrid (REQ-11 #3)
    // ─────────────────────────────────────────────────────────────────────

    public function test_store_requires_location_for_onsite(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/v1/admin/events', [
            'title' => 'فعالية وجاهية', 'body' => 'نص', 'event_format' => 'onsite',
        ])->assertStatus(422)->assertJsonValidationErrors(['event_location']);
    }

    public function test_store_requires_location_for_hybrid(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/v1/admin/events', [
            'title' => 'فعالية مختلطة', 'body' => 'نص', 'event_format' => 'hybrid',
        ])->assertStatus(422)->assertJsonValidationErrors(['event_location']);
    }

    public function test_store_does_not_require_location_for_online(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/v1/admin/events', [
            'title' => 'فعالية أونلاين', 'body' => 'نص', 'event_format' => 'online',
        ])->assertStatus(201);
    }

    // ─────────────────────────────────────────────────────────────────────
    //  speaker photo — uploaded file persists and is returned as a full URL (Application Problem #1)
    // ─────────────────────────────────────────────────────────────────────

    public function test_speaker_photo_is_persisted_and_returned_to_contractor_app(): void
    {
        $this->actingAsAdmin();

        $response = $this->post('/api/v1/admin/events', [
            'title' => 'فعالية بمتحدث', 'body' => 'نص',
            'is_published' => true,
            'speakers' => [['name' => 'م. أحمد', 'is_keynote' => true]],
            'speaker_photos' => [0 => UploadedFile::fake()->image('speaker.jpg')],
        ]);

        $response->assertStatus(201);
        $photoUrl = $response->json('items.speakers.0.photo');
        $this->assertNotEmpty($photoUrl);
        $this->assertStringContainsString('events/speakers/', $photoUrl);

        $event = Event::first();
        Sanctum::actingAs(Contractor::create([
            'name' => 'شركة اختبار', 'membership_number' => '995_g', 'status' => 'active', 'is_frozen' => false,
        ]), ['*']);

        $this->getJson("/api/v1/contractor/events/{$event->id}")
            ->assertStatus(200)
            ->assertJsonPath('items.speakers.0.photo', $photoUrl);
    }

    public function test_store_requires_auth(): void
    {
        $this->postJson('/api/v1/admin/events', ['title' => 'x', 'body' => 'y'])->assertStatus(401);
    }

    // ─────────────────────────────────────────────────────────────────────
    //  archiving — events:archive command + scope filtering + admin status
    // ─────────────────────────────────────────────────────────────────────

    private function createContractor(): Contractor
    {
        return Contractor::create([
            'name' => 'شركة اختبار', 'membership_number' => '996_g', 'status' => 'active', 'is_frozen' => false,
        ]);
    }

    public function test_archive_command_archives_only_past_events(): void
    {
        $past = Event::create([
            'title' => 'فعالية ماضية', 'body' => 'نص', 'slug' => 'past-event',
            'is_published' => true, 'published_at' => now()->subWeek(), 'event_date' => now()->subDay(),
        ]);
        $future = Event::create([
            'title' => 'فعالية قادمة', 'body' => 'نص', 'slug' => 'future-event',
            'is_published' => true, 'published_at' => now()->subDay(), 'event_date' => now()->addWeek(),
        ]);

        $this->artisan('events:archive')->assertSuccessful();

        $this->assertNotNull($past->fresh()->archived_at);
        $this->assertNull($future->fresh()->archived_at);
    }

    public function test_contractor_events_scope_active_excludes_archived(): void
    {
        Event::create([
            'title' => 'مؤرشفة', 'body' => 'نص', 'slug' => 'archived-1',
            'is_published' => true, 'published_at' => now()->subWeek(), 'event_date' => now()->subDay(),
            'archived_at' => now(),
        ]);
        $active = Event::create([
            'title' => 'فعالة', 'body' => 'نص', 'slug' => 'active-1',
            'is_published' => true, 'published_at' => now()->subDay(), 'event_date' => now()->addWeek(),
        ]);

        Sanctum::actingAs($this->createContractor(), ['*']);

        $response = $this->getJson('/api/v1/contractor/events?scope=active');

        $response->assertStatus(200)->assertJsonCount(1, 'items');
        $this->assertSame($active->id, $response->json('items.0.id'));
        $this->assertFalse($response->json('items.0.is_archived'));
    }

    public function test_contractor_events_scope_archived_returns_only_archived(): void
    {
        $archived = Event::create([
            'title' => 'مؤرشفة', 'body' => 'نص', 'slug' => 'archived-2',
            'is_published' => true, 'published_at' => now()->subWeek(), 'event_date' => now()->subDay(),
            'archived_at' => now(),
        ]);
        Event::create([
            'title' => 'فعالة', 'body' => 'نص', 'slug' => 'active-2',
            'is_published' => true, 'published_at' => now()->subDay(), 'event_date' => now()->addWeek(),
        ]);

        Sanctum::actingAs($this->createContractor(), ['*']);

        $response = $this->getJson('/api/v1/contractor/events?scope=archived');

        $response->assertStatus(200)->assertJsonCount(1, 'items');
        $this->assertSame($archived->id, $response->json('items.0.id'));
        $this->assertTrue($response->json('items.0.is_archived'));
    }

    public function test_admin_index_exposes_is_archived_status(): void
    {
        Event::create([
            'title' => 'مؤرشفة', 'body' => 'نص', 'slug' => 'archived-3',
            'archived_at' => now(),
        ]);
        Event::create(['title' => 'فعالة', 'body' => 'نص', 'slug' => 'active-3']);

        $this->actingAsAdmin();

        $response = $this->getJson('/api/v1/admin/events');

        $response->assertStatus(200);
        $byTitle = collect($response->json('items'))->keyBy('title');
        $this->assertTrue($byTitle['مؤرشفة']['is_archived']);
        $this->assertFalse($byTitle['فعالة']['is_archived']);
    }

    public function test_admin_index_scope_archived_filters_correctly(): void
    {
        Event::create(['title' => 'مؤرشفة', 'body' => 'نص', 'slug' => 'archived-4', 'archived_at' => now()]);
        Event::create(['title' => 'فعالة', 'body' => 'نص', 'slug' => 'active-4']);

        $this->actingAsAdmin();

        $this->getJson('/api/v1/admin/events?scope=archived')
            ->assertStatus(200)
            ->assertJsonCount(1, 'items')
            ->assertJsonPath('items.0.title', 'مؤرشفة');
    }

    // ─────────────────────────────────────────────────────────────────────
    //  Speaker normalization — photo key always present, is_keynote always boolean
    // ─────────────────────────────────────────────────────────────────────

    public function test_contractor_event_speaker_without_photo_returns_null_photo(): void
    {
        $contractor = Contractor::create([
            'name' => 'مقاول اختبار',
            'phone' => '0590000001',
            'membership_number' => '1_g',
        ]);
        $event = Event::create([
            'title' => 'فعالية',
            'body' => 'نص',
            'slug' => 'speaker-test-1',
            'is_published' => true,
            'speakers' => [
                ['name' => 'المتحدث الأول', 'title' => 'مهندس', 'is_keynote' => true],
            ],
        ]);

        $response = $this->actingAs($contractor, 'sanctum')
            ->getJson("/api/v1/contractor/events/{$event->id}");

        $response->assertStatus(200);
        $speaker = $response->json('items.speakers.0');
        $this->assertArrayHasKey('photo', $speaker);
        $this->assertNull($speaker['photo']);
        $this->assertTrue($speaker['is_keynote']);
        $this->assertIsBool($speaker['is_keynote']);
    }

    public function test_public_event_speaker_without_photo_returns_null_photo(): void
    {
        $event = Event::create([
            'title' => 'فعالية عامة',
            'body' => 'نص',
            'slug' => 'public-speaker-test',
            'is_published' => true,
            'published_at' => now(),
            'speakers' => [
                ['name' => 'المتحدث الثاني', 'title' => 'استشاري', 'photo' => 'path/to/photo.jpg'],
                ['name' => 'المتحدث الثالث', 'title' => 'مدير', 'is_keynote' => '1'],
            ],
        ]);

        $response = $this->getJson("/api/v1/events/{$event->slug}");

        $response->assertStatus(200);
        $speakers = $response->json('items.speakers');

        $this->assertCount(2, $speakers);

        // First speaker with photo
        $this->assertEquals('المتحدث الثاني', $speakers[0]['name']);
        $this->assertEquals('path/to/photo.jpg', $speakers[0]['photo']);
        $this->assertFalse($speakers[0]['is_keynote']);
        $this->assertIsBool($speakers[0]['is_keynote']);

        // Second speaker without photo but with string is_keynote
        $this->assertEquals('المتحدث الثالث', $speakers[1]['name']);
        $this->assertNull($speakers[1]['photo']);
        $this->assertTrue($speakers[1]['is_keynote']);
        $this->assertIsBool($speakers[1]['is_keynote']);
    }

    public function test_contractor_events_list_normalizes_speakers(): void
    {
        $contractor = Contractor::create([
            'name' => 'مقاول اختبار آخر',
            'phone' => '0590000002',
            'membership_number' => '2_g',
        ]);
        $event = Event::create([
            'title' => 'فعالية مع متحدثين',
            'body' => 'نص',
            'slug' => 'list-speaker-test',
            'is_published' => true,
            'published_at' => now(),
            'speakers' => [
                ['name' => 'متحدث بدون صورة', 'title' => null],
                ['name' => 'متحدث مع صورة', 'title' => 'خبير', 'photo' => 'url/to/photo.png'],
            ],
        ]);

        $response = $this->actingAs($contractor, 'sanctum')
            ->getJson('/api/v1/contractor/events');

        $response->assertStatus(200);
        $items = $response->json('items');
        $this->assertNotEmpty($items, 'Expected at least one event in the response');
        $returnedEvent = collect($items)->firstWhere('id', $event->id);
        $this->assertNotNull($returnedEvent, 'Expected event not found in response');
        $speakers = $returnedEvent['speakers'];

        $this->assertCount(2, $speakers);
        // All speakers should have all keys
        foreach ($speakers as $speaker) {
            $this->assertArrayHasKey('name', $speaker);
            $this->assertArrayHasKey('title', $speaker);
            $this->assertArrayHasKey('photo', $speaker);
            $this->assertArrayHasKey('is_keynote', $speaker);
            $this->assertIsBool($speaker['is_keynote']);
        }
    }
}

<?php

namespace Tests\Feature;

use App\Models\Contractor;
use App\Models\ProfileUpdateRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProfileUpdateRequestTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    private function createContractor(array $attrs = []): Contractor
    {
        return Contractor::create(array_merge([
            'name'              => 'شركة اختبار للمقاولات',
            'membership_number' => '970_g',
            'status'            => 'active',
            'is_frozen'         => false,
        ], $attrs));
    }

    // ─────────────────────────────────────────────────────────────────────
    //  mine
    // ─────────────────────────────────────────────────────────────────────

    public function test_mine_only_lists_own_requests(): void
    {
        $me      = $this->createContractor();
        $another = $this->createContractor(['membership_number' => '971_g']);

        ProfileUpdateRequest::create([
            'contractor_id' => $me->id, 'proposed_data' => ['address' => 'عنواني'],
            'attachment' => 'a.pdf', 'status' => 'pending',
        ]);
        ProfileUpdateRequest::create([
            'contractor_id' => $another->id, 'proposed_data' => ['address' => 'عنوانه'],
            'attachment' => 'b.pdf', 'status' => 'pending',
        ]);

        Sanctum::actingAs($me, ['*']);

        $response = $this->getJson('/api/v1/contractor/profile-update-requests/mine');
        $response->assertStatus(200);
        $this->assertCount(1, $response->json('items'));
        $this->assertEquals('عنواني', $response->json('items.0.proposed_data.address'));
    }

    public function test_mine_requires_auth(): void
    {
        $this->getJson('/api/v1/contractor/profile-update-requests/mine')->assertStatus(401);
    }

    // ─────────────────────────────────────────────────────────────────────
    //  store — non-phone fields (no OTP required)
    // ─────────────────────────────────────────────────────────────────────

    public function test_store_creates_request_with_whitelisted_fields_only(): void
    {
        $contractor = $this->createContractor();
        Sanctum::actingAs($contractor, ['*']);

        $response = $this->post('/api/v1/contractor/profile-update-requests', [
            'address'           => 'عنوان جديد',
            'authorized_person' => 'شخص جديد',
            'attachment'        => UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf'),
        ], ['Accept' => 'application/json']);

        $response->assertStatus(201)
            ->assertJsonPath('items.status', 'pending')
            ->assertJsonPath('items.proposed_data.address', 'عنوان جديد')
            ->assertJsonPath('items.proposed_data.authorized_person', 'شخص جديد');

        $this->assertDatabaseHas('profile_update_requests', [
            'contractor_id' => $contractor->id,
            'status'        => 'pending',
        ]);

        $record = ProfileUpdateRequest::first();
        $this->assertEquals(['address' => 'عنوان جديد', 'authorized_person' => 'شخص جديد'], $record->proposed_data);
        Storage::disk('public')->assertExists($record->attachment);

        // re-fetch through API confirms DB state
        $this->getJson('/api/v1/contractor/profile-update-requests/mine')
            ->assertJsonPath('items.0.proposed_data.address', 'عنوان جديد');
    }

    public function test_store_fails_validation_without_attachment(): void
    {
        $contractor = $this->createContractor();
        Sanctum::actingAs($contractor, ['*']);

        $this->postJson('/api/v1/contractor/profile-update-requests', ['address' => 'x'])
            ->assertStatus(422)->assertJsonValidationErrors(['attachment']);
        $this->assertDatabaseCount('profile_update_requests', 0);
    }

    public function test_store_fails_when_no_updatable_field_given(): void
    {
        $contractor = $this->createContractor();
        Sanctum::actingAs($contractor, ['*']);

        $response = $this->post('/api/v1/contractor/profile-update-requests', [
            'attachment' => UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf'),
        ], ['Accept' => 'application/json']);

        $response->assertStatus(422);
        $this->assertDatabaseCount('profile_update_requests', 0);
    }

    public function test_store_ignores_non_whitelisted_fields_like_membership_number(): void
    {
        $contractor = $this->createContractor();
        Sanctum::actingAs($contractor, ['*']);

        // membership_number ليس ضمن ALLOWED_FIELDS ولا حتى ضمن قواعد validate() — لازم يُتجاهل تماماً
        $this->post('/api/v1/contractor/profile-update-requests', [
            'address'           => 'عنوان',
            'membership_number' => '999_g',
            'attachment'        => UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertStatus(201);

        $record = ProfileUpdateRequest::first();
        $this->assertArrayNotHasKey('membership_number', $record->proposed_data);
        $this->assertEquals('970_g', $contractor->fresh()->membership_number); // لم يتغيّر
    }

    public function test_store_rejects_duplicate_pending_request(): void
    {
        $contractor = $this->createContractor();
        Sanctum::actingAs($contractor, ['*']);

        $payload = fn () => [
            'address'    => 'عنوان',
            'attachment' => UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf'),
        ];

        $this->post('/api/v1/contractor/profile-update-requests', $payload(), ['Accept' => 'application/json'])
            ->assertStatus(201);
        $this->post('/api/v1/contractor/profile-update-requests', $payload(), ['Accept' => 'application/json'])
            ->assertStatus(422);

        $this->assertDatabaseCount('profile_update_requests', 1);
    }

    public function test_store_requires_auth(): void
    {
        $this->postJson('/api/v1/contractor/profile-update-requests', ['address' => 'x'])->assertStatus(401);
    }

    // ─────────────────────────────────────────────────────────────────────
    //  store — phone change requires OTP
    // ─────────────────────────────────────────────────────────────────────

    public function test_store_with_phone_change_requires_otp(): void
    {
        $contractor = $this->createContractor();
        Sanctum::actingAs($contractor, ['*']);

        $this->post('/api/v1/contractor/profile-update-requests', [
            'phone'      => '0590009999',
            'attachment' => UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf'),
        ], ['Accept' => 'application/json'])
            ->assertStatus(422)->assertJsonValidationErrors(['otp']);
    }

    public function test_store_with_phone_change_fails_with_wrong_otp(): void
    {
        $contractor = $this->createContractor();
        Sanctum::actingAs($contractor, ['*']);

        $this->postJson('/api/v1/contractor/profile-update-requests/send-phone-otp', ['phone' => '0590009999'])
            ->assertStatus(200);

        $response = $this->post('/api/v1/contractor/profile-update-requests', [
            'phone'      => '0590009999',
            'otp'        => '000000',
            'attachment' => UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf'),
        ], ['Accept' => 'application/json']);

        $response->assertStatus(422)->assertJsonPath('error', 'invalid_otp');
        $this->assertDatabaseCount('profile_update_requests', 0);
    }

    public function test_store_with_phone_change_succeeds_with_correct_otp(): void
    {
        $contractor = $this->createContractor();
        Sanctum::actingAs($contractor, ['*']);

        $this->postJson('/api/v1/contractor/profile-update-requests/send-phone-otp', ['phone' => '0590009999'])
            ->assertStatus(200);
        $otp = Cache::get('profile_phone_otp_' . $contractor->id)['otp'];

        $response = $this->post('/api/v1/contractor/profile-update-requests', [
            'phone'      => '0590009999',
            'otp'        => (string) $otp,
            'attachment' => UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf'),
        ], ['Accept' => 'application/json']);

        $response->assertStatus(201)->assertJsonPath('items.proposed_data.phone', '0590009999');

        $record = ProfileUpdateRequest::first();
        $this->assertNotNull($record->phone_otp_verified_at);
    }

    public function test_send_phone_otp_is_rate_limited_by_cooldown(): void
    {
        $contractor = $this->createContractor();
        Sanctum::actingAs($contractor, ['*']);

        $this->postJson('/api/v1/contractor/profile-update-requests/send-phone-otp', ['phone' => '0590009999'])
            ->assertStatus(200);

        $response = $this->postJson('/api/v1/contractor/profile-update-requests/send-phone-otp', ['phone' => '0590009999']);
        $response->assertStatus(429)->assertJsonPath('error', 'otp_cooldown');
    }

    // ─────────────────────────────────────────────────────────────────────
    //  Contractor isolation
    // ─────────────────────────────────────────────────────────────────────

    public function test_contractor_a_pending_request_does_not_block_contractor_b(): void
    {
        $a = $this->createContractor(['membership_number' => '980_g']);
        $b = $this->createContractor(['membership_number' => '981_g']);

        ProfileUpdateRequest::create([
            'contractor_id' => $a->id, 'proposed_data' => ['address' => 'x'],
            'attachment' => 'a.pdf', 'status' => 'pending',
        ]);

        Sanctum::actingAs($b, ['*']);
        $this->post('/api/v1/contractor/profile-update-requests', [
            'address'    => 'عنوان ب',
            'attachment' => UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertStatus(201);

        $this->assertDatabaseCount('profile_update_requests', 2);
    }
}

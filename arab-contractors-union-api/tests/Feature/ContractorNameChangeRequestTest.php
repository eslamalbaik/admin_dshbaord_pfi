<?php

namespace Tests\Feature;

use App\Models\Contractor;
use App\Models\ContractorNameChangeRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ContractorNameChangeRequestTest extends TestCase
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
            'name'              => 'الاسم القديم للشركة',
            'membership_number' => '960_g',
            'status'            => 'active',
            'is_frozen'         => false,
        ], $attrs));
    }

    // ─────────────────────────────────────────────────────────────────────
    //  show
    // ─────────────────────────────────────────────────────────────────────

    public function test_show_returns_null_when_no_request_exists(): void
    {
        $contractor = $this->createContractor();
        Sanctum::actingAs($contractor, ['*']);

        $response = $this->getJson('/api/v1/contractor/auth/name-change-request');
        $response->assertStatus(200);
        $this->assertArrayNotHasKey('items', $response->json());
    }

    public function test_show_requires_auth(): void
    {
        $this->getJson('/api/v1/contractor/auth/name-change-request')->assertStatus(401);
    }

    public function test_show_returns_only_own_latest_request(): void
    {
        $me      = $this->createContractor();
        $another = $this->createContractor(['membership_number' => '961_g']);

        ContractorNameChangeRequest::create([
            'contractor_id' => $another->id, 'current_name' => 'أ', 'requested_name' => 'ب',
            'supporting_document' => 'doc.pdf', 'status' => 'pending',
        ]);
        $mine = ContractorNameChangeRequest::create([
            'contractor_id' => $me->id, 'current_name' => 'القديم', 'requested_name' => 'الجديد',
            'supporting_document' => 'doc2.pdf', 'status' => 'pending',
        ]);

        Sanctum::actingAs($me, ['*']);

        $this->getJson('/api/v1/contractor/auth/name-change-request')
            ->assertJsonPath('items.id', $mine->id)
            ->assertJsonPath('items.requested_name', 'الجديد');
    }

    // ─────────────────────────────────────────────────────────────────────
    //  store
    // ─────────────────────────────────────────────────────────────────────

    public function test_store_creates_request_and_persists_current_name_snapshot(): void
    {
        $contractor = $this->createContractor();
        Sanctum::actingAs($contractor, ['*']);

        $response = $this->post('/api/v1/contractor/auth/name-change-request', [
            'requested_name'      => 'اسم جديد للشركة',
            'supporting_document' => UploadedFile::fake()->create('letter.pdf', 100, 'application/pdf'),
        ], ['Accept' => 'application/json']);

        $response->assertStatus(201)
            ->assertJsonPath('items.current_name', 'الاسم القديم للشركة')
            ->assertJsonPath('items.requested_name', 'اسم جديد للشركة')
            ->assertJsonPath('items.status', 'pending');

        $this->assertDatabaseHas('contractor_name_change_requests', [
            'contractor_id'  => $contractor->id,
            'current_name'   => 'الاسم القديم للشركة',
            'requested_name' => 'اسم جديد للشركة',
            'status'         => 'pending',
        ]);

        $record = ContractorNameChangeRequest::first();
        Storage::disk('public')->assertExists($record->supporting_document);

        // re-fetch through API confirms DB state
        $this->getJson('/api/v1/contractor/auth/name-change-request')
            ->assertJsonPath('items.requested_name', 'اسم جديد للشركة');
    }

    public function test_store_fails_validation_without_document(): void
    {
        $contractor = $this->createContractor();
        Sanctum::actingAs($contractor, ['*']);

        $this->postJson('/api/v1/contractor/auth/name-change-request', ['requested_name' => 'اسم جديد'])
            ->assertStatus(422)->assertJsonValidationErrors(['supporting_document']);
        $this->assertDatabaseCount('contractor_name_change_requests', 0);
    }

    public function test_store_fails_validation_without_requested_name(): void
    {
        $contractor = $this->createContractor();
        Sanctum::actingAs($contractor, ['*']);

        $this->post('/api/v1/contractor/auth/name-change-request', [
            'supporting_document' => UploadedFile::fake()->create('letter.pdf', 100, 'application/pdf'),
        ], ['Accept' => 'application/json'])
            ->assertStatus(422)->assertJsonValidationErrors(['requested_name']);
    }

    public function test_store_rejects_duplicate_pending_request(): void
    {
        $contractor = $this->createContractor();
        Sanctum::actingAs($contractor, ['*']);

        $payload = fn () => [
            'requested_name'      => 'اسم جديد',
            'supporting_document' => UploadedFile::fake()->create('letter.pdf', 100, 'application/pdf'),
        ];

        $this->post('/api/v1/contractor/auth/name-change-request', $payload(), ['Accept' => 'application/json'])
            ->assertStatus(201);
        $this->post('/api/v1/contractor/auth/name-change-request', $payload(), ['Accept' => 'application/json'])
            ->assertStatus(422);

        $this->assertDatabaseCount('contractor_name_change_requests', 1);
    }

    public function test_store_requires_auth(): void
    {
        $this->postJson('/api/v1/contractor/auth/name-change-request', ['requested_name' => 'x'])
            ->assertStatus(401);
    }
}

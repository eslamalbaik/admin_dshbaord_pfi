<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\CertificateRequest;
use App\Models\Contractor;
use App\Services\CertificateSerialService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use App\Models\User;
use Tests\TestCase;

class CertificateSerialVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    private function issued(array $attrs = [], array $contractor = []): CertificateRequest
    {
        $c = Contractor::create($contractor + [
            'name'              => 'شركة التحقق للمقاولات',
            'membership_number' => '777_g',
            'status'            => 'active',
            'is_frozen'         => false,
            'phone'             => '0599999999',
            'address'           => 'عنوان خاص لا يجب أن يظهر',
        ]);

        $cert = $c->certificateRequests()->create(['type' => 'membership', 'status' => 'issued'] + $attrs);
        $cert->forceFill(['issued_at' => now(), 'valid_until' => $attrs['valid_until'] ?? now()->addMonths(3)->toDateString()])->save();
        $cert->ensureSerialNumber();

        return $cert->fresh();
    }

    private function actingAsAdmin(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']), ['*']);
    }

    private function check(string $serial)
    {
        return $this->getJson('/api/v1/certificates/check?serial=' . urlencode($serial));
    }

    // ── التفرّد وعدم التعديل ──

    public function test_serials_are_unique_and_well_formed(): void
    {
        $serials = [];
        $c = Contractor::create(['name' => 'س', 'membership_number' => '1_g', 'status' => 'active', 'is_frozen' => false]);
        for ($i = 0; $i < 600; $i++) {
            $serials[] = $c->certificateRequests()->create(['type' => 'membership', 'status' => 'issued'])->ensureSerialNumber();
        }

        $this->assertCount(600, array_unique($serials));
        foreach ($serials as $s) {
            $this->assertMatchesRegularExpression('/^[0-9A-HJKMNP-TV-Z]{12}$/', $s);
            $this->assertTrue(CertificateSerialService::hasValidChecksum($s));
        }
    }

    public function test_database_rejects_a_duplicate_serial(): void
    {
        $a = $this->issued();
        $b = $this->issued([], ['membership_number' => '778_g']);

        $this->expectException(QueryException::class);
        \DB::table('certificate_requests')->where('id', $b->id)->update(['serial_number' => $a->serial_number]);
    }

    public function test_serial_is_assigned_once_and_survives_reissue(): void
    {
        $cert = $this->issued();
        $first = $cert->serial_number;

        $this->assertSame($first, $cert->ensureSerialNumber());
        $this->assertSame($first, CertificateRequest::find($cert->id)->ensureSerialNumber());
    }

    public function test_serial_cannot_be_modified_after_issue(): void
    {
        $cert = $this->issued();

        $this->expectException(\LogicException::class);
        $cert->serial_number = 'AAAABBBBCCCC';
        $cert->save();
    }

    public function test_serial_is_not_mass_assignable(): void
    {
        $cert = $this->issued();
        $cert->fill(['serial_number' => 'AAAABBBBCCCC']);

        $this->assertSame($cert->getOriginal('serial_number'), $cert->serial_number);
    }

    // ── حالات التحقق الأربع ──

    public function test_valid_certificate(): void
    {
        $cert = $this->issued();

        $this->check($cert->serial_number)
            ->assertOk()
            ->assertJsonPath('items.state', 'valid')
            ->assertJsonPath('items.state_label', 'شهادة سارية')
            ->assertJsonPath('items.contractor_name', 'شركة التحقق للمقاولات')
            ->assertJsonPath('items.membership_number', '777_g')
            ->assertJsonPath('items.serial_number', $cert->serial_number)
            ->assertJsonPath('items.type_label', 'شهادة العضوية')
            ->assertJsonPath('items.valid_until', $cert->valid_until->toDateString());
    }

    public function test_expired_certificate(): void
    {
        $cert = $this->issued(['valid_until' => now()->subDays(2)->toDateString()]);

        $this->check($cert->serial_number)->assertOk()
            ->assertJsonPath('items.state', 'expired')
            ->assertJsonPath('items.state_label', 'شهادة منتهية');
    }

    public function test_certificate_is_still_valid_on_its_last_day(): void
    {
        $cert = $this->issued(['valid_until' => now(config('app.local_timezone'))->toDateString()]);

        $this->check($cert->serial_number)->assertJsonPath('items.state', 'valid');
    }

    public function test_revoked_certificate_wins_over_expiry(): void
    {
        $cert = $this->issued(['valid_until' => now()->subYear()->toDateString()]);
        $cert->update(['revoked_at' => now(), 'revoke_reason' => 'خطأ']);

        $this->check($cert->serial_number)->assertOk()
            ->assertJsonPath('items.state', 'revoked')
            ->assertJsonPath('items.state_label', 'شهادة ملغاة');
    }

    public function test_unknown_or_malformed_serial_is_not_found(): void
    {
        $this->issued();

        foreach (['AAAABBBBCCCC', 'MC-000999', 'garbage', '12345'] as $bad) {
            $this->check($bad)->assertOk()
                ->assertJsonPath('items.state', 'not_found')
                ->assertJsonPath('items.state_label', 'شهادة غير موجودة')
                ->assertJsonMissingPath('items.contractor_name');
        }
    }

    public function test_pending_request_with_a_serial_is_not_verifiable(): void
    {
        $cert = $this->issued();
        $cert->update(['status' => 'pending']);

        $this->check($cert->serial_number)->assertJsonPath('items.state', 'not_found');
    }

    public function test_lookup_is_lenient_about_case_and_spacing_and_reads_live_data(): void
    {
        $cert = $this->issued();
        $sloppy = strtolower(str_replace('-', ' ', $cert->serial_number));

        $this->check($sloppy)->assertJsonPath('items.state', 'valid');

        // لا كاش: أي تغيير بالقاعدة ينعكس فوراً
        $cert->update(['revoked_at' => now()]);
        $this->check($sloppy)->assertJsonPath('items.state', 'revoked');
    }

    public function test_serial_pasted_in_reversed_rtl_order_still_verifies(): void
    {
        $cert = $this->issued();
        $compact = str_replace('-', '', $cert->serial_number);

        // مقلوب بالكامل، أو المجموعات بترتيب معكوس، أو نفس الأحرف بشرطات بأماكن غلط
        $reversed  = strrev($compact);
        $rtlGroups = implode('-', array_reverse(str_split($compact, 4)));
        $badDashes = substr($compact, 0, 1) . '-' . substr($compact, 1, 4) . '-' . substr($compact, 5);

        // قطع بين شرطات بترتيب مخربط وبأحجام غير منتظمة
        $scrambled = substr($compact, 7) . '-' . strrev(substr($compact, 0, 3)) . '-' . substr($compact, 3, 4);

        foreach ([$scrambled, $reversed, $rtlGroups, $badDashes, "  {$compact}  ", strtolower($compact)] as $pasted) {
            $this->check($pasted)->assertJsonPath('items.state', 'valid');
        }
    }

    public function test_previous_17_char_serial_still_verifies(): void
    {
        $cert = $this->issued();
        $A = \App\Services\CertificateSerialService::ALPHABET;
        do {
            $c = '';
            for ($i = 0; $i < 17; $i++) {
                $c .= $A[random_int(0, 31)];
            }
            $v1 = $c;
        } while (! \App\Services\CertificateSerialService::hasValidChecksum($v1));
        \DB::table('certificate_requests')->where('id', $cert->id)->update(['serial_number' => $v1]);

        $this->check($v1)->assertJsonPath('items.state', 'valid');
        $this->check(strrev($v1))->assertJsonPath('items.state', 'valid');
    }

    public function test_legacy_printed_serial_still_verifies(): void
    {
        $cert = $this->issued();
        $legacy = 'MC-' . str_pad((string) $cert->id, 6, '0', STR_PAD_LEFT);
        \DB::table('certificate_requests')->where('id', $cert->id)->update(['serial_number' => $legacy]);

        $this->check($legacy)->assertJsonPath('items.state', 'valid');
    }

    public function test_public_response_does_not_leak_private_data_and_is_not_cached(): void
    {
        $cert = $this->issued();

        $res = $this->check($cert->serial_number)->assertOk();
        $res->assertHeader('Cache-Control');
        $this->assertStringContainsString('no-store', $res->headers->get('Cache-Control'));

        $body = json_encode($res->json());
        foreach (['0599999999', 'عنوان خاص', 'reviewed_by', 'revoke_reason', 'certificate_path', 'phone'] as $secret) {
            $this->assertStringNotContainsString($secret, $body);
        }
    }

    public function test_verification_needs_no_login(): void
    {
        $cert = $this->issued();

        $this->withHeaders(['Authorization' => ''])->check($cert->serial_number)->assertOk();
    }

    // ── الإلغاء وسجل التدقيق ──

    public function test_revoke_and_reinstate_are_audited(): void
    {
        $cert = $this->issued();
        $this->actingAsAdmin();

        $this->postJson("/api/v1/dashboard/certificate-requests/{$cert->id}/revoke", [])->assertStatus(422);

        $this->postJson("/api/v1/dashboard/certificate-requests/{$cert->id}/revoke", ['reason' => 'صدرت بالخطأ'])
            ->assertOk()->assertJsonPath('items.is_revoked', true);
        $this->check($cert->serial_number)->assertJsonPath('items.state', 'revoked');

        $log = ActivityLog::where('action', 'certificate.revoked')->first();
        $this->assertNotNull($log);
        $this->assertTrue((bool) $log->is_critical);
        $this->assertSame($cert->serial_number, $log->meta['serial_number']);
        $this->assertSame('صدرت بالخطأ', $log->meta['reason']);

        $this->postJson("/api/v1/dashboard/certificate-requests/{$cert->id}/revoke", ['reason' => 'x'])->assertStatus(422);

        $this->postJson("/api/v1/dashboard/certificate-requests/{$cert->id}/reinstate", ['reason' => 'تراجع'])->assertOk();
        $this->check($cert->serial_number)->assertJsonPath('items.state', 'valid');
        $this->assertDatabaseHas('activity_logs', ['action' => 'certificate.reinstated']);
    }

    public function test_issue_assigns_serial_and_logs_it(): void
    {
        $c = Contractor::create(['name' => 'ش', 'membership_number' => '5_g', 'status' => 'active', 'is_frozen' => false]);
        $cert = $c->certificateRequests()->create(['type' => 'membership', 'status' => 'approved']);
        $this->actingAsAdmin();

        $spy = $this->spy(\App\Services\MembershipCertificatePdfService::class);
        $spy->shouldReceive('generate')->andReturn('certificates/membership/x.pdf');

        $this->postJson("/api/v1/dashboard/certificate-requests/{$cert->id}/issue", [])
            ->assertOk()
            ->assertJsonPath('items.serial_number', fn ($s) => is_string($s) && strlen($s) === 12);

        $serial = $cert->fresh()->serial_number;
        $this->check($serial)->assertJsonPath('items.state', 'valid');
        $this->assertSame($serial, ActivityLog::where('action', 'certificate.issued')->first()->meta['serial_number']);
    }
}

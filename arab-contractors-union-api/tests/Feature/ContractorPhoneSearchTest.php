<?php

namespace Tests\Feature;

use App\Models\Contractor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * البحث بقائمة المقاولين برقم الجوال: الجوال مخزن 9 أرقام بدون صفر، والأدمن ممكن يكتبه
 * بالصفر، بمفتاح الدولة، بمسافات، أو بأرقام عربية — كلها لازم تلاقي نفس المقاول.
 */
class ContractorPhoneSearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']), ['*']);

        Contractor::create([
            'name'              => 'شركة الجوال',
            'membership_number' => '940_g',
            'phone'             => '599123456',
            'status'            => 'active',
            'is_frozen'         => false,
        ]);
        Contractor::create([
            'name'              => 'شركة أخرى',
            'membership_number' => '941_g',
            'phone'             => '567000111',
            'status'            => 'active',
            'is_frozen'         => false,
        ]);
    }

    public static function phoneInputs(): array
    {
        return [
            'as stored'      => ['599123456'],
            'leading zero'   => ['0599123456'],
            'country code'   => ['+970599123456'],
            'double zero'    => ['00970 599 123 456'],
            'partial'        => ['123456'],
            'arabic digits'  => ['٠٥٩٩١٢٣٤٥٦'],
        ];
    }

    /** @dataProvider phoneInputs */
    public function test_finds_contractor_by_phone(string $input): void
    {
        $this->getJson('/api/v1/contractors?search='.urlencode($input))
            ->assertOk()
            ->assertJsonCount(1, 'items')
            ->assertJsonPath('items.0.membership_number', '940_g');
    }
}

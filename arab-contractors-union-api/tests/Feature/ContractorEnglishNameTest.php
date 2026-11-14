<?php

namespace Tests\Feature;

use App\Models\Contractor;
use App\Models\User;
use App\Support\EnglishNameSuggester;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * اقتراح اسم الشركة وعنوانها بالإنجليزي + صفحة المراجعة الجماعية.
 */
class ContractorEnglishNameTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAdmin(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']), ['*']);
    }

    public function test_company_names_are_translated_and_transliterated(): void
    {
        $cases = [
            'شركة النور للمقاولات'                             => 'Al-Noor Contracting Co.',
            'شركة مشتهى للمقاولات'                             => 'Mushtaha Contracting Co.',
            'مؤسسة أبو عيدة للمقاولات العامة'                  => 'Abu Eida General Contracting Est.',
            'شركة عبد الرحمن السقا وأولاده'                     => 'Abdulrahman Al-Saqqa & Sons Co.',
            'شركة القدس للهندسة والمقاولات م.خ.م'              => 'Al-Quds Engineering & Contracting Co. Ltd.',
            'شركة الاتحاد العربي للمقاولات العامة والإنشاءات والطرق والتجارة المحدودة'
                => 'Al-Ittihad Arab General Contracting, Construction, Roads & Trading Co. Ltd.',
        ];

        foreach ($cases as $ar => $en) {
            $this->assertSame($en, EnglishNameSuggester::companyName($ar), $ar);
        }
    }

    public function test_unknown_names_still_produce_valid_english(): void
    {
        $en = EnglishNameSuggester::companyName('شركة الزعانين للمقاولات');

        $this->assertMatchesRegularExpression('/^[A-Za-z0-9 .,&\'()\/\-]+$/', $en);
        $this->assertStringStartsWith('Al-', $en);
        $this->assertStringEndsWith('Contracting Co.', $en);
    }

    public function test_addresses_are_translated(): void
    {
        $this->assertSame('Gaza - Rimal - Omar Al-Mukhtar St.', EnglishNameSuggester::address('غزة - الرمال - شارع عمر المختار'));
        $this->assertSame('Khan Younis', EnglishNameSuggester::address('خان يونس'));
        $this->assertSame('Deir al-Balah - Nuseirat', EnglishNameSuggester::address('دير البلح - النصيرات'));
        $this->assertNull(EnglishNameSuggester::address('  '));
    }

    public function test_suggest_endpoint(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/v1/dashboard/contractors/english-suggest', ['name' => 'شركة النور للمقاولات', 'address' => 'رفح'])
            ->assertOk()
            ->assertJsonPath('items.name_en', 'Al-Noor Contracting Co.')
            ->assertJsonPath('items.address_en', 'Rafah');
    }

    public function test_review_list_and_bulk_save(): void
    {
        $this->actingAsAdmin();
        $a = Contractor::create(['name' => 'شركة النور للمقاولات', 'membership_number' => '1_g', 'status' => 'active', 'is_frozen' => false, 'city' => 'غزة']);
        $b = Contractor::create(['name' => 'شركة مشتهى للمقاولات', 'name_en' => 'Mushtaha Co.', 'membership_number' => '2_g', 'status' => 'active', 'is_frozen' => false]);

        $res = $this->getJson('/api/v1/dashboard/contractors/english-names')->assertOk();
        $rowA = collect($res->json('items.items'))->firstWhere('id', $a->id);
        $this->assertNull($rowA['name_en']);
        $this->assertSame('Al-Noor Contracting Co.', $rowA['suggested_name_en']);
        $this->assertSame('Gaza', $rowA['suggested_address_en']);
        $this->assertSame(1, $res->json('items.missing_name'));

        // الاقتراح ما بينحفظ لحاله
        $this->assertNull($a->fresh()->name_en);

        $this->postJson('/api/v1/dashboard/contractors/english-names', ['items' => [
            ['id' => $a->id, 'name_en' => 'Al-Nour Contracting Co.', 'address_en' => 'Gaza'],
            ['id' => $b->id, 'name_en' => 'Mushtaha Co.', 'address_en' => null],
        ]])->assertOk()->assertJsonPath('items.updated', 1);

        $this->assertSame('Al-Nour Contracting Co.', $a->fresh()->name_en);
        $this->assertSame('Gaza', $a->fresh()->address_en);
    }

    public function test_bulk_save_rejects_arabic_text(): void
    {
        $this->actingAsAdmin();
        $a = Contractor::create(['name' => 'شركة', 'membership_number' => '3_g', 'status' => 'active', 'is_frozen' => false]);

        $this->postJson('/api/v1/dashboard/contractors/english-names', ['items' => [
            ['id' => $a->id, 'name_en' => 'شركة'],
        ]])->assertStatus(422);
    }
}

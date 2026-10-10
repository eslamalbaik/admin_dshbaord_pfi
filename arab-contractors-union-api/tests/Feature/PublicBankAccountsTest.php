<?php

namespace Tests\Feature;

use App\Models\BankAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * GET /api/v1/bank-accounts (شاشة "التفاصيل البنكية" بالتطبيق):
 * لازم يرجّع كل الحسابات الفعّالة بدون أي حد على العدد، مرتبة حسب sort، والمعطّل مخفي.
 */
class PublicBankAccountsTest extends TestCase
{
    use RefreshDatabase;

    private function account(string $name, string $currency, int $sort, bool $active = true): BankAccount
    {
        return BankAccount::create([
            'bank_name' => $name,
            'currency'  => $currency,
            'iban'      => 'PS00TEST' . $sort . $currency,
            'is_active' => $active,
            'sort'      => $sort,
        ]);
    }

    public function test_returns_all_active_banks_without_a_count_limit(): void
    {
        $this->account('بنك فلسطين', 'JOD', 0);
        $this->account('البنك الإسلامي', 'JOD', 1);
        $this->account('بنك القدس', 'ILS', 2);
        $this->account('البنك العربي', 'JOD', 3);
        $this->account('بنك الاستثمار', 'USD', 4);

        $banks = $this->getJson('/api/v1/bank-accounts')->assertOk()->json('items.banks');

        $this->assertSame(
            ['بنك فلسطين', 'البنك الإسلامي', 'بنك القدس', 'البنك العربي', 'بنك الاستثمار'],
            array_column($banks, 'bank_name'),
        );
        $this->assertSame('ILS', $banks[2]['accounts'][0]['currency']);
    }

    public function test_hides_inactive_accounts_and_keeps_sort_order(): void
    {
        $this->account('بنك فلسطين', 'JOD', 0, active: false);
        $this->account('البنك العربي', 'JOD', 3);
        $this->account('البنك الإسلامي', 'JOD', 1);
        $this->account('بنك القدس', 'ILS', 2);

        $banks = $this->getJson('/api/v1/bank-accounts')->assertOk()->json('items.banks');

        $this->assertSame(['البنك الإسلامي', 'بنك القدس', 'البنك العربي'], array_column($banks, 'bank_name'));
    }

    public function test_same_bank_name_in_two_currencies_is_one_card_with_both_ibans(): void
    {
        $this->account('بنك فلسطين', 'JOD', 0);
        $this->account('بنك فلسطين', 'ILS', 1);

        $banks = $this->getJson('/api/v1/bank-accounts')->assertOk()->json('items.banks');

        $this->assertCount(1, $banks);
        $this->assertSame(['JOD', 'ILS'], array_column($banks[0]['accounts'], 'currency'));
    }
}

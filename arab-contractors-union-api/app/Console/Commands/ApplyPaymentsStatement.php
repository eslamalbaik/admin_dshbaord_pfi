<?php

namespace App\Console\Commands;

use App\Models\Contractor;
use App\Models\ContractorDue;
use App\Models\Membership;
use App\Models\ContractorCredit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * تطبيق كشف دفعات سنة معيّنة على حالة العضوية والذمم.
 *
 * الملف JSON: [{membership_number, name, balance, last_date, note}, ...]
 * balance = صافي رصيد الشركة من الكشف (سالب = عليها، موجب = لها عند الاتحاد).
 *
 * - رصيد >= -0.99 → عضوية فعّالة لنهاية السنة (31/12).
 * - غير ذلك، أو شركة فعّالة غير موجودة بالكشف → منتهية.
 * - رصيد سالب → ذمة مالية بالمبلغ المستحق.
 * - رصيد موجب → رصيد افتتاحي دائن (جدول contractor_credits) يظهر في صفحة أرصدة المقاولين.
 * - --expire-missing: إنهاء الشركات الفعّالة غير الموجودة بالكشف أيضاً.
 * إعادة التشغيل آمنة: الذمم والأرصدة المعلَّمة بنفس الوسم تُستبدل.
 */
class ApplyPaymentsStatement extends Command
{
    protected $signature = 'contractors:apply-payments-statement
                            {file : مسار ملف JSON المستخرج من الكشف}
                            {--year=2026 : سنة العضوية}
                            {--expire-missing : إنهاء عضوية الشركات غير الموجودة بالكشف}
                            {--dry-run : عرض التقرير دون كتابة}';

    protected $description = 'تحديث حالة العضوية وتسجيل الذمم والأرصدة من كشف دفعات المقاولين';

    private const TOLERANCE = 0.99;

    public function handle(): int
    {
        $year = (int) $this->option('year');
        $tag  = "كشف دفعات {$year}";
        $rows = json_decode((string) @file_get_contents($this->argument('file')), true);

        if (! is_array($rows)) {
            $this->error('تعذّرت قراءة الملف.');

            return self::FAILURE;
        }

        $plan = [];
        $unmatched = [];

        foreach ($rows as $row) {
            $contractor = $this->match($row);

            if (! $contractor) {
                $unmatched[] = [$row['membership_number'] ?? '-', $row['name']];

                continue;
            }

            $plan[$contractor->id] = ['contractor' => $contractor, 'row' => $row];
        }

        $toActivate = collect($plan)->filter(fn ($p) => $p['row']['balance'] >= -self::TOLERANCE);
        $debtors    = collect($plan)->filter(fn ($p) => $p['row']['balance'] < -0.01);
        $creditors  = collect($plan)->filter(fn ($p) => $p['row']['balance'] > 0.01);

        // شركات بالكشف رصيدها لا يغطي السنة، + (اختيارياً) الفعّالة غير الموجودة بالكشف
        $toExpire = Contractor::whereIn('id', collect($plan)->keys()->diff($toActivate->keys()))
            ->get(['id', 'membership_number', 'name']);

        if ($this->option('expire-missing')) {
            $toExpire = $toExpire->merge(Contractor::where('status', 'active')
                ->whereNotIn('id', array_keys($plan))->get(['id', 'membership_number', 'name']));
        }

        $this->table(['البند', 'العدد'], [
            ['سطور الكشف', count($rows)],
            ['مطابَقة مع قاعدة البيانات', count($plan)],
            ['غير مطابَقة', count($unmatched)],
            ['ستصبح فعّالة', $toActivate->count()],
            ['ستصبح منتهية', $toExpire->count()],
            ['ذمم على الشركات', $debtors->count() . ' / ' . number_format(-$debtors->sum(fn ($p) => $p['row']['balance']), 2) . ' دينار'],
            ['أرصدة للشركات', $creditors->count() . ' / ' . number_format($creditors->sum(fn ($p) => $p['row']['balance']), 2) . ' دينار'],
        ]);

        if ($unmatched) {
            $this->warn('غير مطابَقة:');
            $this->table(['رقم العضوية', 'الاسم'], $unmatched);
        }

        if ($this->option('dry-run')) {
            $this->line('EXPIRE: ' . $toExpire->map(fn ($c) => $c->membership_number)->implode(','));
            $this->info('وضع dry-run — لم يُكتب أي شيء.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($plan, $toActivate, $toExpire, $year, $tag) {
            $startsAt  = "{$year}-01-01";
            $expiresAt = "{$year}-12-31";

            foreach ($toActivate as $id => $p) {
                Contractor::whereKey($id)->update(['status' => 'active']);

                $membership = Membership::where('contractor_id', $id)
                    ->whereYear('expires_at', $year)->latest('id')->first();

                if ($membership) {
                    $membership->update(['status' => 'active', 'starts_at' => $startsAt, 'expires_at' => $expiresAt]);
                } else {
                    Membership::create([
                        'contractor_id' => $id, 'type' => 'renewal', 'status' => 'active',
                        'starts_at' => $startsAt, 'expires_at' => $expiresAt, 'notes' => $tag,
                    ]);
                }

                Membership::where('contractor_id', $id)->where('status', 'active')
                    ->whereYear('expires_at', '<', $year)->update(['status' => 'expired']);
            }

            foreach ($toExpire as $c) {
                Contractor::whereKey($c->id)->update(['status' => 'expired']);
                Membership::where('contractor_id', $c->id)->where('status', 'active')->update(['status' => 'expired']);
            }

            foreach ($plan as $id => $p) {
                $balance = round((float) $p['row']['balance'], 2);

                ContractorDue::where('contractor_id', $id)->where('notes', $tag)
                    ->where('paid_jod', 0)->forceDelete();

                if ($balance < -0.01) {
                    ContractorDue::create([
                        'contractor_id' => $id,
                        'year'          => $year,
                        'description'   => "رصيد مستحق على الشركة حسب {$tag}",
                        'amount_jod'    => -$balance,
                        'status'        => 'unpaid',
                        'source'        => 'manual',
                        'notes'         => $tag,
                    ]);
                }

                ContractorCredit::where('contractor_id', $id)->where('notes', $tag)
                    ->where('used_jod', 0)->forceDelete();

                if ($balance > 0.01) {
                    ContractorCredit::create([
                        'contractor_id' => $id,
                        'amount_jod'    => $balance,
                        'description'   => "رصيد دائن للشركة حسب {$tag}",
                        'source'        => 'statement_import',
                        'notes'         => $tag,
                    ]);
                }
            }
        });

        $this->info('تم التطبيق.');

        return self::SUCCESS;
    }

    private function match(array $row): ?Contractor
    {
        $number = trim((string) ($row['membership_number'] ?? ''));

        if ($number !== '') {
            $found = Contractor::where('membership_number', $number)->first();

            if ($found) {
                return $found;
            }
        }

        $name = $this->normalizeName($row['name']);
        $hits = Contractor::get(['id', 'name', 'membership_number', 'notes', 'status'])
            ->filter(fn ($c) => $this->normalizeName((string) $c->name) === $name);

        return $hits->count() === 1 ? $hits->first() : null;
    }

    private function normalizeName(string $name): string
    {
        $name = preg_replace('/[\x{200E}\x{200F}\x{202A}-\x{202E}]/u', '', $name);
        $name = strtr($name, ['أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ة' => 'ه', 'ى' => 'ي']);

        return preg_replace('/\s+/u', ' ', trim($name));
    }
}

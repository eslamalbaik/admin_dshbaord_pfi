<?php

namespace App\Observers;

use App\Services\MembershipRenewalService;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
use Illuminate\Database\Eloquent\Model;

/**
 * بعد أي تغيير على ذمة أو دفعة أو رصيد دائن، حالة المقاول المحفوظة وعضوية السنة الحالية
 * بتتزامن مع الحالة المعروضة بالأرصدة (MembershipRenewalService::syncWithBalance)، عشان
 * التطبيق وقائمة المقاولين ونافذة تغيير الحالة يشوفوا نفس الحالة.
 *
 * بعد الـcommit: الدفعة بتنعمل وبعدين بتتوزّع على الذمم بنفس الـtransaction، والحسبة على
 * نص الطريق بتشوف الدفعة كلها رصيد قبل ما ينحسب المستخدم منها.
 */
class MembershipBalanceObserver implements ShouldHandleEventsAfterCommit
{
    public function saved(Model $model): void
    {
        $this->sync($model);
    }

    public function deleted(Model $model): void
    {
        $this->sync($model);
    }

    public function restored(Model $model): void
    {
        $this->sync($model);
    }

    private function sync(Model $model): void
    {
        if (! $model->contractor_id) {
            return;
        }

        // العملية الأصلية انحفظت؛ فشل المزامنة ما لازم يرجّع 500 عليها
        try {
            app(MembershipRenewalService::class)->syncWithBalance((int) $model->contractor_id);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}

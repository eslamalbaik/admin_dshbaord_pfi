<?php

namespace App\Observers;

use App\Services\BalanceRoundingService;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
use Illuminate\Database\Eloquent\Model;

/**
 * بعد أي تغيير على ذمة أو دفعة أو غرامة أو رصيد دائن، يُعاد ضبط قيد جبر كسور رصيد الشركة.
 *
 * بعد الـcommit مش لحظة الحدث: الدفعة تُنشأ أولاً ثم توزَّع على الذمم داخل نفس الـtransaction،
 * والجبر على حالة نصف موزَّعة كان سيحذف وينشئ قيد الجبر عدة مرات بنفس العملية.
 *
 * التعديلات الجماعية بالـquery builder (where()->update()) ما بتطلق هذا؛ يلحقها
 * balances:round المجدول يومياً.
 */
class BalanceRoundingObserver implements ShouldHandleEventsAfterCommit
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
        if (! $model->contractor_id || BalanceRoundingService::isSyncing()) {
            return;
        }

        // العملية الأصلية (دفعة، ذمة…) انحفظت فعلاً قبل هذا؛ فشل الجبر ما لازم يرجّع 500 عليها.
        // بينسجّل، وbalances:round الليلي بيصلّحه.
        try {
            app(BalanceRoundingService::class)->sync((int) $model->contractor_id);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}

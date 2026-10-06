<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** جزء من دفعة انصرف على ذمة معيّنة */
class PaymentAllocation extends Model
{
    protected $fillable = ['payment_id', 'contractor_due_id', 'amount_jod'];

    protected $casts = ['amount_jod' => 'decimal:2'];

    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }

    public function due()
    {
        return $this->belongsTo(ContractorDue::class, 'contractor_due_id')->withTrashed();
    }

    /** سجّل إن الدفعة سدّدت هالمبلغ من الذمة */
    public static function record(Payment $payment, ContractorDue $due, float $amountJod): void
    {
        if ($amountJod <= 0) {
            return;
        }

        static::create([
            'payment_id'        => $payment->id,
            'contractor_due_id' => $due->id,
            'amount_jod'        => round($amountJod, 2),
        ]);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** جزء من دفعة (أو من رصيد دائن contractor_credits) انصرف على ذمة معيّنة أو غرامة */
class PaymentAllocation extends Model
{
    protected $fillable = ['payment_id', 'contractor_credit_id', 'contractor_due_id', 'penalty_id', 'amount_jod'];

    protected $casts = ['amount_jod' => 'decimal:2'];

    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }

    public function credit()
    {
        return $this->belongsTo(ContractorCredit::class, 'contractor_credit_id')->withTrashed();
    }

    public function due()
    {
        return $this->belongsTo(ContractorDue::class, 'contractor_due_id')->withTrashed();
    }

    public function penalty()
    {
        return $this->belongsTo(Penalty::class);
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

    /** سجّل إن الدفعة سدّدت هالمبلغ من الغرامة */
    public static function recordPenalty(Payment $payment, Penalty $penalty, float $amountJod): void
    {
        if ($amountJod <= 0) {
            return;
        }

        static::create([
            'payment_id' => $payment->id,
            'penalty_id' => $penalty->id,
            'amount_jod' => round($amountJod, 2),
        ]);
    }

    /** سجّل إن رصيد دائن سابق سدّد هالمبلغ من ذمة أو غرامة */
    public static function recordFromCredit(ContractorCredit $credit, ContractorDue|Penalty $target, float $amountJod): void
    {
        if ($amountJod <= 0) {
            return;
        }

        static::create([
            'contractor_credit_id' => $credit->id,
            'contractor_due_id'    => $target instanceof ContractorDue ? $target->id : null,
            'penalty_id'           => $target instanceof Penalty ? $target->id : null,
            'amount_jod'           => round($amountJod, 2),
        ]);
    }
}

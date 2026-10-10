<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** تعديل يدوي على رصيد مقاول من صفحة الأرصدة (ContractorBalanceAdjustmentService) */
class ContractorBalanceAdjustment extends Model
{
    public const MODE_LABELS = [
        'increase' => 'زيادة الرصيد',
        'decrease' => 'إنقاص الرصيد',
        'set'      => 'تحديد رصيد جديد',
    ];

    protected $fillable = [
        'contractor_id', 'mode', 'amount_jod', 'balance_before_jod', 'balance_after_jod',
        'status_before', 'status_after', 'reason', 'contractor_credit_id', 'contractor_due_id', 'created_by',
    ];

    protected $casts = [
        'amount_jod'         => 'decimal:2',
        'balance_before_jod' => 'decimal:2',
        'balance_after_jod'  => 'decimal:2',
    ];

    public function contractor()
    {
        return $this->belongsTo(Contractor::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

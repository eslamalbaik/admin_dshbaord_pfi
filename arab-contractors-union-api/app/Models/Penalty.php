<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Penalty extends Model
{
    protected $fillable = [
        'contractor_id', 'reason', 'amount', 'paid_amount', 'status',
        'notes', 'reject_reason', 'paid_at',
    ];

    protected $casts = [
        'amount'      => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'paid_at'     => 'datetime',
    ];

    /** الحالات الأربع المعتمَدة (REQ-06 #6) — مصدر واحد للتسميات المعروضة بلوحة الأدمن. */
    public const STATUS_LABELS = [
        'unpaid'         => 'غير مسدَّدة',
        'paid'           => 'مسدَّدة',
        'partially_paid' => 'مسدَّدة جزئياً',
        'rejected'       => 'مرفوضة',
    ];

    public function contractor()
    {
        return $this->belongsTo(Contractor::class);
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    /**
     * الأعمدة المشتقّة من حالة الغرامة — مصدر واحد يستهلكه الإنشاء والتحديث على حدّ سواء.
     *
     * كان هذا المنطق داخل PenaltyController::updateStatus() وحده، فكانت الغرامة تُنشأ
     * دائماً "غير مسدَّدة" ثم تحتاج نداءً ثانياً لتسجيل ما يقوله السجل الورقي أصلاً
     * (TASK-17 #8). تكراره في الميثودين كان سيجعلهما يتفرّقان عند أول تعديل.
     *
     * @param float $amount مبلغ الغرامة الكامل — تُشتقّ منه paid_amount في حالة "مسدَّدة"
     */
    public static function attributesForStatus(string $status, float $amount, ?float $paidAmount = null, ?string $rejectReason = null): array
    {
        return ['status' => $status] + match ($status) {
            'paid'           => ['paid_amount' => $amount, 'paid_at' => now(), 'reject_reason' => null],
            'partially_paid' => ['paid_amount' => $paidAmount, 'paid_at' => null, 'reject_reason' => null],
            'rejected'       => ['paid_amount' => 0, 'paid_at' => null, 'reject_reason' => $rejectReason],
            default          => ['paid_amount' => 0, 'paid_at' => null, 'reject_reason' => null],
        };
    }

    /** المتبقي من الغرامة بالدينار (المرفوضة والمسدَّدة صفر) */
    public function getRemainingAttribute(): float
    {
        if (! in_array($this->status, ['unpaid', 'partially_paid'], true)) {
            return 0.0;
        }

        return round(max(0, (float) $this->amount - (float) $this->paid_amount), 2);
    }

    /** تسجيل سداد (كامل أو جزئي) من دفعة، وتحديث الحالة تبعاً للمتبقي */
    public function applyPayment(float $amountJod): void
    {
        $this->setPaidAmount(min((float) $this->amount, (float) $this->paid_amount + $amountJod));
    }

    /** إلغاء جزء من السداد (لما الدفعة اللي سدّدته ترجع لقيد المراجعة أو تنرفض) */
    public function reversePayment(float $amountJod): void
    {
        $this->setPaidAmount(max(0, (float) $this->paid_amount - $amountJod));
    }

    private function setPaidAmount(float $paid): void
    {
        $paid = round($paid, 2);

        $this->update([
            'paid_amount' => $paid,
            'status'      => $paid >= (float) $this->amount && $paid > 0 ? 'paid' : ($paid > 0 ? 'partially_paid' : 'unpaid'),
            'paid_at'     => $paid >= (float) $this->amount && $paid > 0 ? ($this->paid_at ?? now()) : null,
        ]);
    }
}

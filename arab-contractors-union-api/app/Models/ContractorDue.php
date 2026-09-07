<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ContractorDue extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'contractor_id', 'year', 'period', 'reference_number', 'description',
        'amount_jod', 'paid_jod', 'status', 'source',
        'due_date', 'notes', 'created_by',
        'discount_type', 'discount_value', 'discount_amount_jod', 'discount_reason',
        'discount_by', 'original_amount_jod', 'fee_breakdown',
    ];

    protected $casts = [
        'year'                 => 'integer',
        'amount_jod'           => 'decimal:2',
        'paid_jod'             => 'decimal:2',
        'due_date'             => 'date',
        'discount_value'       => 'decimal:2',
        'discount_amount_jod'  => 'decimal:2',
        'original_amount_jod'  => 'decimal:2',
        'fee_breakdown'        => 'array',
    ];

    public const STATUS_LABELS = [
        'unpaid'         => 'غير مسدَّدة',
        'partially_paid' => 'مسدَّدة جزئياً',
        'paid'           => 'مسدَّدة',
    ];

    public function contractor()
    {
        return $this->belongsTo(Contractor::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** الذمم غير المسدَّدة بالكامل */
    public function scopeOutstanding($query)
    {
        return $query->where('status', '!=', 'paid');
    }

    public function getRemainingJodAttribute(): float
    {
        return round(max(0, (float) $this->amount_jod - (float) $this->paid_jod), 2);
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    /** رقم مرجعي بصيغة INV-<سنة الإنشاء>-<رقم الذمة بـ3 خانات> — يُولَّد مرة واحدة عند الإنشاء */
    public static function generateReferenceNumber(self $due): string
    {
        return 'INV-' . $due->created_at->year . '-' . str_pad((string) $due->id, 3, '0', STR_PAD_LEFT);
    }

    /**
     * تفصيل رسوم التسجيل (المادة 37) — عدد وقيمة ذمم أول سنة انتساب طبّقت رسم التسجيل
     * بدل الرسم السنوي على المجال الأعلى، مستخرَجة من fee_breakdown لكل ذمة محرّك احتساب.
     * $year يحصر الاحتساب بسنة معيّنة (لتقرير سنوي)، أو كل السنوات إن تُرك null.
     *
     * @return array{dues_count: int, total_jod: float}
     */
    public static function registrationFeesSummary(?int $year = null): array
    {
        $query = self::where('source', 'fee_engine')->whereNotNull('fee_breakdown');

        if ($year !== null) {
            $query->where('year', $year);
        }

        $duesCount = 0;
        $totalJod  = 0.0;

        foreach ($query->get(['fee_breakdown']) as $due) {
            $breakdown = $due->fee_breakdown;

            if (empty($breakdown['is_new_registration_year'])) {
                continue;
            }

            $duesCount++;

            foreach ($breakdown['fields'] ?? [] as $field) {
                if (($field['fee_type'] ?? null) === 'registration') {
                    $totalJod += (float) ($field['amount_jod'] ?? 0);
                }
            }
        }

        return ['dues_count' => $duesCount, 'total_jod' => round($totalJod, 2)];
    }

    /** تسجيل سداد (كامل أو جزئي) وتحديث الحالة تبعاً للمتبقي */
    public function applyPayment(float $amountJod): void
    {
        $paid = min((float) $this->amount_jod, (float) $this->paid_jod + $amountJod);

        $this->update([
            'paid_jod' => $paid,
            'status'   => $paid >= (float) $this->amount_jod ? 'paid'
                        : ($paid > 0 ? 'partially_paid' : 'unpaid'),
        ]);
    }

    /**
     * تطبيق خصم إداري (فردي أو جماعي — المادة 37/ت) على مبلغ الذمة.
     * يحفظ original_amount_jod عند أول خصم فقط، ويرفض أي خصم يُنزل amount_jod تحت المسدَّد فعلاً.
     *
     * @throws \InvalidArgumentException لو تجاوز الخصم المبلغ الأصلي أو المتبقي أقل من المسدَّد
     */
    public function applyDiscount(string $type, float $value, ?string $reason, int $byUserId): void
    {
        $original = (float) ($this->original_amount_jod ?? $this->amount_jod);

        $newAmount = $type === 'percent'
            ? $original * (1 - $value / 100)
            : $original - $value;

        $newAmount = round(max(0, $newAmount), 2);

        if ($newAmount < (float) $this->paid_jod) {
            throw new \InvalidArgumentException('الخصم يُنزل المبلغ تحت ما تم سداده فعلياً على هذه الذمة.');
        }

        $this->update([
            'original_amount_jod' => $original,
            'discount_type'       => $type,
            'discount_value'      => $value,
            'discount_amount_jod' => round($original - $newAmount, 2),
            'discount_reason'     => $reason,
            'discount_by'         => $byUserId,
            'amount_jod'          => $newAmount,
        ]);

        $this->applyPayment(0);
    }
}

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

    /** تحويلات التطبيق المرفوعة لتسديد هالذمة (pending/paid/rejected) */
    public function payments()
    {
        return $this->hasMany(Payment::class, 'contractor_due_id');
    }

    /**
     * تاريخ سند القبض المعروض بكرت الذمة بالتطبيق: رسوم الاشتراك السنوي سندها 31/12 من سنتها
     * (العضوية تنتهي آخر السنة الميلادية)، وأي ذمة غيرها (رسوم متراكمة) بلا تاريخ.
     */
    public function getReceiptDateAttribute(): ?string
    {
        return $this->is_membership_fee ? "{$this->year}-12-31" : null;
    }

    /** ذمة رسوم اشتراك سنوي لسنة محددة (محرّك الاحتساب، أو بيانها "اشتراك/عضوية") */
    public function getIsMembershipFeeAttribute(): bool
    {
        if (! $this->year) {
            return false;
        }

        return $this->source === 'fee_engine'
            || preg_match('/اشتراك|عضوية/u', (string) $this->description) === 1;
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * ذمم المقاولين غير المحذوفين فقط — حذف المقاول ناعم ولا يحذف ذممه،
     * فبدون هذا القيد تظهر ذمم المحذوفين بالبطاقات وهي غائبة عن جدول المقاولين.
     */
    public function scopeOfActiveContractors($query)
    {
        return $query->whereHas('contractor');
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
        return self::STATUS_LABELS[$this->status] ?? $this->status ?? self::STATUS_LABELS['unpaid'];
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
        $query = self::ofActiveContractors()->where('source', 'fee_engine')->whereNotNull('fee_breakdown');

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

    /** إلغاء جزء من السداد (لما دفعة مؤكَّدة ترجع لقيد المراجعة أو تنرفض) */
    public function reversePayment(float $amountJod): void
    {
        $paid = round(max(0, (float) $this->paid_jod - $amountJod), 2);

        $this->update([
            'paid_jod' => $paid,
            'status'   => $paid >= (float) $this->amount_jod && $paid > 0 ? 'paid'
                        : ($paid > 0 ? 'partially_paid' : 'unpaid'),
        ]);
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
     * احتساب أثر خصم مقترح على هذه الذمة — **دون** أي كتابة.
     *
     * مصدر واحد للحساب يستهلكه applyDiscount() والمعاينة الجماعية على حدّ سواء. قبل ذلك
     * كانت المعاينة في DuesDiscountService تحسب `original - value` بينما التطبيق الفعلي
     * يراكم الخصم على الخصم السابق، فكان أثر التطبيق يتجاوز ما عُرض على المستخدم لكل ذمة
     * سبق خصمها (TASK-17 #10).
     *
     * @return array{
     *     original: float, effective_type: string, effective_value: float, new_amount: float,
     *     discount_amount: float, blocked_reason: ?string
     * }
     */
    public function projectDiscount(string $type, float $value): array
    {
        $original = (float) ($this->original_amount_jod ?? $this->amount_jod);
        $current  = (float) $this->amount_jod;
        $previous = $this->discount_type;

        if ($type === 'percent' && ($previous === null || $previous === 'percent')) {
            // نسبة بعد نسبة تتراكم على المبلغ الأصلي (30% ثم 30% = 60%)
            $effectiveType  = 'percent';
            $effectiveValue = $value + (float) $this->discount_value;
            $newAmount      = $original * (1 - $effectiveValue / 100);
        } else {
            // أي خصم غير هيك بينطبق على صافي الذمة الحالي بعد الخصومات السابقة:
            // 100 ← 10% = 90 ← 20 د.أ = 70 (مش 80 محسوبة من الـ100). لما يختلط النوعان
            // بينحفظ الخصم كمبلغ ثابت = إجمالي ما انخصم من الأصل.
            $newAmount     = $type === 'percent' ? $current * (1 - $value / 100) : $current - $value;
            $effectiveType = 'fixed';
        }

        $newAmount = round(max(0, $newAmount), 2);

        if ($effectiveType === 'fixed') {
            $effectiveValue = round($original - $newAmount, 2);
        }

        return [
            'original'        => $original,
            'effective_type'  => $effectiveType,
            'effective_value' => $effectiveValue,
            'new_amount'      => $newAmount,
            'discount_amount' => round($original - $newAmount, 2),
            'blocked_reason'  => $newAmount < (float) $this->paid_jod
                ? 'الخصم يُنزل المبلغ تحت ما تم سداده فعلياً على هذه الذمة.'
                : null,
        ];
    }

    /**
     * تطبيق خصم إداري (فردي أو جماعي — المادة 37/ت) على مبلغ الذمة.
     * يحفظ original_amount_jod عند أول خصم فقط، ويرفض أي خصم يُنزل amount_jod تحت المسدَّد فعلاً.
     *
     * @throws \InvalidArgumentException لو تجاوز الخصم المبلغ الأصلي أو المتبقي أقل من المسدَّد
     */
    public function applyDiscount(string $type, float $value, ?string $reason, int $byUserId): void
    {
        $projection = $this->projectDiscount($type, $value);

        if ($projection['blocked_reason'] !== null) {
            throw new \InvalidArgumentException($projection['blocked_reason']);
        }

        $effectiveType  = $projection['effective_type'];
        $effectiveValue = $projection['effective_value'];

        // تحديث لاحقة "(بعد خصم ...)" بنص البيان لتعكس نسبة/مبلغ الخصم المتراكم الفعلي —
        // بدونه يضل النص القديم (مثلاً 50%) ظاهر حتى بعد ما يصير الخصم الحقيقي 70%.
        $suffix = $effectiveType === 'percent' ? "(بعد خصم {$effectiveValue}%)" : "(بعد خصم {$effectiveValue} د.أ)";
        $baseDescription = trim(preg_replace('/\s*\(بعد خصم[^)]*\)\s*$/u', '', (string) $this->description));

        $this->update([
            'original_amount_jod' => $projection['original'],
            'discount_type'       => $effectiveType,
            'discount_value'      => $effectiveValue,
            'discount_amount_jod' => $projection['discount_amount'],
            'discount_reason'     => $reason,
            'discount_by'         => $byUserId,
            'amount_jod'          => $projection['new_amount'],
            'description'         => "{$baseDescription} {$suffix}",
        ]);

        $this->applyPayment(0);
    }
}

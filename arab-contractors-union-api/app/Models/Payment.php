<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Payment extends Model
{
    protected $fillable = [
        'contractor_id', 'membership_id', 'membership_snapshot', 'equipment_package_id', 'contractor_due_id', 'penalty_id', 'bank_account_id', 'amount',
        'currency', 'exchange_rate', 'amount_jod', 'used_amount_jod', 'rate_source',
        'type', 'status', 'method', 'reference_number', 'transaction_number', 'receipt_image',
        'notes', 'paid_at', 'submitted_at', 'confirmed_by', 'confirmed_at',
        'rejection_reason', 'receipt_pdf_path',
    ];

    protected $casts = [
        'amount'        => 'decimal:2',
        'exchange_rate' => 'decimal:6',
        'amount_jod'    => 'decimal:2',
        'paid_at'       => 'datetime',
        'submitted_at'  => 'datetime',
        'confirmed_at'  => 'datetime',
        'membership_snapshot' => 'array',
    ];

    protected $appends = ['receipt_image_url', 'receipt_pdf_url'];

    public const STATUS_LABELS = [
        'pending'  => 'بانتظار اعتماد قسم المحاسبة',
        'paid'     => 'مؤكَّدة',
        'rejected' => 'مرفوضة',
        'refunded' => 'مسترجعة',
        'failed'   => 'فشلت',
    ];

    /** العلاقات اللي بيقراها عنوان الدفعة (title) — للـ eager loading بالقوائم */
    public const TITLE_RELATIONS = ['due', 'penalty', 'membership', 'equipmentPackage', 'allocations.due', 'allocations.penalty'];

    /**
     * تسمية نوع الدفعة بالعربي. `membership` قيمة قديمة لنفس رسوم العضوية، و`penalty` قيمة قديمة
     * من إدخال الداشبورد (فائضها ما بينحسب رصيد) — التطبيق بيبعت `penalty_payment`.
     */
    public const TYPE_LABELS = [
        'dues_payment'           => 'سداد ذمة',
        'membership_fee'         => 'رسوم اشتراك',
        'penalty_payment'        => 'دفع غرامة',
        'advance_payment'        => 'دفعة مقدمة',
        'membership'             => 'رسوم اشتراك',
        'penalty'                => 'دفع غرامة',
        'equipment_subscription' => 'اشتراك باقة المعدات',
    ];

    /** أنواع الدفعة اللي بيختار منها المقاول بالتطبيق (بالترتيب المعروض) */
    public const APP_TYPES = ['dues_payment', 'membership_fee', 'penalty_payment', 'advance_payment'];

    /** أنواع فائضها (المبلغ اللي ما انصرف على ذمة أو غرامة) بيضل رصيداً دائناً للمقاول */
    public const CREDIT_TYPES = ['dues_payment', 'penalty_payment', 'advance_payment'];

    /** تسميات بديلة ممكن يبعتها التطبيق لنفس الأنواع */
    private const TYPE_ALIASES = [
        'membership'  => 'membership_fee',
        'subscription' => 'membership_fee',
        'dues'        => 'dues_payment',
        'due'         => 'dues_payment',
        'penalty'     => 'penalty_payment',
        'fine'        => 'penalty_payment',
        'advance'     => 'advance_payment',
        'prepayment'  => 'advance_payment',
        'credit'      => 'advance_payment',
    ];

    /**
     * نوع الدفعة المرسل من التطبيق بعد التوحيد، أو null لقيمة فاضية أو غير معروفة
     * (وقتها السيرفر بيحدد النوع حسب وضع المقاول، متل قبل).
     */
    public static function normalizeAppType(?string $type): ?string
    {
        $type = strtolower(trim((string) $type));
        $type = self::TYPE_ALIASES[$type] ?? $type;

        return in_array($type, [...self::APP_TYPES, 'equipment_subscription'], true) ? $type : null;
    }

    /** خيارات نوع الدفعة للتطبيق: [{value, label}] */
    public static function appTypeOptions(): array
    {
        return array_map(fn ($type) => ['value' => $type, 'label' => self::TYPE_LABELS[$type]], self::APP_TYPES);
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    public function getTypeLabelAttribute(): string
    {
        return self::TYPE_LABELS[$this->type] ?? 'دفعة';
    }

    /**
     * عنوان كرت الدفعة بالتطبيق (تبويب "الدفعات السابقة"):
     * 1. ذمة مربوطة بالتحويل ← عنوان الذمة (مثلاً "رسوم اشتراك سنة 2026")، بس لو ما انصرفت على غيرها.
     * 2. دفعة مقدمة ← "دفعة مقدمة" دايماً؛ غرامة مربوطة بالتحويل ← سبب الغرامة، بس لو ما انصرفت على غيرها.
     * 3. انصرفت على ذمة/غرامة وحدة ← عنوانها؛ على أكثر من وحدة ← "تسديد N ذمم مالية".
     * 4. رسوم عضوية مربوطة بعضوية ← "رسوم اشتراك سنة <سنة انتهاء العضوية>"؛ باقة معدات ← اسم الباقة.
     * 5. غير هيك ← تسمية النوع.
     */
    public function getTitleAttribute(): string
    {
        // الدفعة المربوطة بذمة بتنصرف على أقدم الذمم، فعنوان الذمة المختارة بس لو هي اللي انسدّت
        if ($this->contractor_due_id && $this->due
            && $this->allocations->every(fn ($a) => $a->contractor_due_id === $this->contractor_due_id)) {
            return $this->due->title;
        }

        if ($this->type === 'advance_payment') {
            return $this->type_label;
        }

        // نفس الشي للغرامة المربوطة: الدفعة بتسدّ أقدم الذمم أولاً، فسبب الغرامة بس لو هي اللي انسدّت
        if ($this->penalty_id && $this->penalty
            && $this->allocations->every(fn ($a) => $a->penalty_id === $this->penalty_id)) {
            return $this->penalty->reason ?: $this->type_label;
        }

        $allocations = $this->allocations;
        if ($allocations->count() === 1) {
            $allocation = $allocations->first();
            if ($allocation->due) {
                return $allocation->due->title;
            }
            if ($allocation->penalty) {
                return $allocation->penalty->reason ?: self::TYPE_LABELS['penalty_payment'];
            }
        } elseif ($allocations->count() > 1) {
            return "تسديد {$allocations->count()} ذمم مالية";
        }

        if (in_array($this->type, ['membership_fee', 'membership'], true) && $this->membership?->expires_at) {
            return 'رسوم اشتراك سنة ' . $this->membership->expires_at->year;
        }

        if ($this->type === 'equipment_subscription' && $this->equipmentPackage) {
            return 'اشتراك باقة ' . $this->equipmentPackage->name;
        }

        return $this->type_label;
    }

    protected static function booted(): void
    {
        // كل دفعة لازم يكون إلها رقم مرجعي، بغض النظر عن مصدر الإنشاء (مقاول أو إدخال يدوي من الداشبورد)
        static::created(function (self $payment) {
            if (! $payment->transaction_number) {
                $payment->transaction_number = self::generateTransactionNumber($payment);
                $payment->saveQuietly();
            }
        });
    }

    /** رابط صورة إشعار التحويل الكامل */
    public function getReceiptImageUrlAttribute(): ?string
    {
        return $this->receipt_image ? Storage::disk('public')->url($this->receipt_image) : null;
    }

    /** رابط إيصال القبض PDF (يُولَّد عند تأكيد الدفعة) */
    public function getReceiptPdfUrlAttribute(): ?string
    {
        return $this->receipt_pdf_path ? Storage::disk('public')->url($this->receipt_pdf_path) : null;
    }

    public function contractor()
    {
        return $this->belongsTo(Contractor::class);
    }

    /** الذمة اللي رُفع هالتحويل لتسديدها (تحويلات dues_payment من التطبيق) */
    public function due()
    {
        return $this->belongsTo(ContractorDue::class, 'contractor_due_id');
    }

    public function penalty()
    {
        return $this->belongsTo(Penalty::class);
    }

    public function membership()
    {
        return $this->belongsTo(Membership::class);
    }

    public function equipmentPackage()
    {
        return $this->belongsTo(EquipmentPackage::class);
    }

    public function bankAccount()
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function confirmedBy()
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    /** الذمم اللي سدّدتها هالدفعة وبأي مبلغ */
    public function allocations()
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    /** سجل تغييرات الحالة اليدوية مع أسبابها */
    public function statusChanges()
    {
        return $this->hasMany(PaymentStatusChange::class);
    }

    public function latestStatusChange()
    {
        return $this->hasOne(PaymentStatusChange::class)->latestOfMany();
    }

    /** رقم العملية بصيغة TRX-<رقم الدفعة بـ6 خانات> — يُولَّد مرة واحدة عند الإنشاء، لعرضه بشاشة "تم الإرسال" */
    public static function generateTransactionNumber(self $payment): string
    {
        return 'TRX-' . str_pad((string) $payment->id, 6, '0', STR_PAD_LEFT);
    }
}

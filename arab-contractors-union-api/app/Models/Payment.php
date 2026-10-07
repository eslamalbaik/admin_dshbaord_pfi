<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Payment extends Model
{
    protected $fillable = [
        'contractor_id', 'membership_id', 'membership_snapshot', 'equipment_package_id', 'contractor_due_id', 'bank_account_id', 'amount',
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

    /** تسمية نوع الدفعة بالعربي — `membership` قيمة قديمة لنفس رسوم العضوية */
    /** العلاقات اللي بيقراها عنوان الدفعة (title) — للـ eager loading بالقوائم */
    public const TITLE_RELATIONS = ['due', 'membership', 'equipmentPackage', 'allocations.due', 'allocations.penalty'];

    public const TYPE_LABELS = [
        'membership_fee'         => 'رسوم اشتراك العضوية',
        'membership'             => 'رسوم اشتراك العضوية',
        'dues_payment'           => 'تسديد ذمم مالية',
        'penalty'                => 'تسديد غرامة',
        'equipment_subscription' => 'اشتراك باقة المعدات',
    ];

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
     * 1. ذمة مربوطة بالتحويل ← عنوان الذمة (مثلاً "رسوم اشتراك سنة 2026").
     * 2. انصرفت على ذمة/غرامة وحدة ← عنوانها؛ على أكثر من وحدة ← "تسديد N ذمم مالية".
     * 3. رسوم عضوية مربوطة بعضوية ← "رسوم اشتراك سنة <سنة انتهاء العضوية>"؛ باقة معدات ← اسم الباقة.
     * 4. غير هيك ← تسمية النوع.
     */
    public function getTitleAttribute(): string
    {
        if ($this->contractor_due_id && $this->due) {
            return $this->due->title;
        }

        $allocations = $this->allocations;
        if ($allocations->count() === 1) {
            $allocation = $allocations->first();
            if ($allocation->due) {
                return $allocation->due->title;
            }
            if ($allocation->penalty) {
                return $allocation->penalty->reason ?: self::TYPE_LABELS['penalty'];
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

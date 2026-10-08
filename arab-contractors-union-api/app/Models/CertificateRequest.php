<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

class CertificateRequest extends Model
{
    protected $fillable = [
        'contractor_id', 'type', 'status', 'notes', 'attachment',
        'reject_reason', 'certificate_path', 'issued_at', 'reviewed_by', 'reviewed_at',
        'pending_payment_id', 'viewed_at', 'last_viewed_at', 'views_count',
        'certificate_address', 'decision_number', 'decision_date',
    ];

    protected $casts = [
        'issued_at'      => 'datetime',
        'reviewed_at'    => 'datetime',
        'viewed_at'      => 'datetime',
        'last_viewed_at' => 'datetime',
        'views_count'    => 'integer',
        'decision_date'  => 'date',
    ];

    /**
     * بيانات الطباعة المحفوظة على الطلب (من الموافقة أو إصدار سابق)، بصيغة overrides
     * اللي بيستقبلها MembershipCertificatePdfService::generate. الفارغ ما بيتبعت، فبيرجع
     * التوليد لملف المقاول.
     */
    public function certificateOverrides(): array
    {
        return array_filter([
            'address'         => $this->certificate_address,
            'decision_number' => $this->decision_number,
            'decision_date'   => $this->decision_date?->toDateString(),
        ], fn ($v) => filled($v));
    }

    public function contractor()
    {
        return $this->belongsTo(Contractor::class);
    }

    /**
     * دفعة الرسوم التي سُمح بتقديم الطلب بانتظار تأكيدها (TASK-17 #5). وجودها غير مؤكَّدة
     * يمنع الموافقة والإصدار: الطلب يُرى ويُراجَع، لكن لا شهادة تُصدر مقابل مال غير مؤكَّد.
     */
    public function pendingPayment()
    {
        return $this->belongsTo(\App\Models\Payment::class, 'pending_payment_id');
    }

    /** الطلب موقوف بانتظار اعتماد المحاسب للدفعة المرتبطة به. */
    public function isAwaitingPaymentConfirmation(): bool
    {
        return $this->pending_payment_id !== null
            && $this->pendingPayment?->status !== 'paid';
    }

    public function reviewedBy()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function getAttachmentUrlAttribute(): ?string
    {
        return $this->attachment
            ? Storage::disk('public')->url($this->attachment)
            : null;
    }

    public function getCertificateUrlAttribute(): ?string
    {
        return $this->certificate_path
            ? Storage::disk('public')->url($this->certificate_path)
            : null;
    }

    /**
     * رابط الشهادة اللي بيستلمه المقاول (التطبيق والبريد): رابط موقَّع بيمرّ على السيرفر
     * فبينسجّل فتحه (viewed_at)، بعكس certificate_url المباشر اللي بتستخدمه اللوحة —
     * فتح الأدمن للشهادة ما بينحسب على المقاول.
     */
    public function getTrackedCertificateUrlAttribute(): ?string
    {
        if (! $this->certificate_path) {
            return null;
        }

        // توقيع نسبي (مسار + query بس): رابط مطلق بيتكسر توقيعه لو اختلف المخطط/المضيف
        // بين APP_URL والطلب الواصل من خلف nginx.
        return url(URL::signedRoute('certificates.file', ['certificateRequest' => $this->id], absolute: false));
    }

    public function getTypeLabelAttribute(): string
    {
        return [
            'membership'     => 'شهادة العضوية',
            'good_standing'  => 'شهادة حسن السير والسلوك',
            'classification' => 'شهادة التصنيف',
            'experience'     => 'شهادة الخبرة والمشاريع',
        ][$this->type] ?? $this->type;
    }

    public function getStatusLabelAttribute(): string
    {
        return [
            'pending'  => 'قيد المراجعة',
            'approved' => 'موافق عليه',
            'issued'   => 'تم الإصدار',
            'rejected' => 'مرفوض',
        ][$this->status] ?? $this->status;
    }
}

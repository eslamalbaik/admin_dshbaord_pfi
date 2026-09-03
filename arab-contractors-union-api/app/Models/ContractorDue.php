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
    ];

    protected $casts = [
        'year'       => 'integer',
        'amount_jod' => 'decimal:2',
        'paid_jod'   => 'decimal:2',
        'due_date'   => 'date',
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
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EquipmentReservation extends Model
{
    protected $fillable = ['equipment_id', 'contractor_id', 'start_date', 'end_date', 'status', 'notes'];

    protected $casts = [
        'start_date' => 'date',
        'end_date'   => 'date',
    ];

    public const STATUS_LABELS = [
        'confirmed' => 'مؤكَّد',
        'cancelled' => 'ملغى',
    ];

    public function getStatusLabelAttribute(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    public function equipment()
    {
        return $this->belongsTo(Equipment::class);
    }

    public function contractor()
    {
        return $this->belongsTo(Contractor::class);
    }

    /** يتقاطع نطاق [start_date,end_date] المطلوب مع أي حجز/حجب فعّال لهذه الآلية؟ */
    public static function hasOverlap(int $equipmentId, string $startDate, string $endDate, ?int $excludeReservationId = null): bool
    {
        $reservationOverlap = static::where('equipment_id', $equipmentId)
            ->where('status', 'confirmed')
            ->when($excludeReservationId, fn ($q) => $q->where('id', '!=', $excludeReservationId))
            ->where('start_date', '<=', $endDate)
            ->where('end_date', '>=', $startDate)
            ->exists();

        if ($reservationOverlap) {
            return true;
        }

        return EquipmentBlockedDate::where('equipment_id', $equipmentId)
            ->whereBetween('blocked_date', [$startDate, $endDate])
            ->exists();
    }
}

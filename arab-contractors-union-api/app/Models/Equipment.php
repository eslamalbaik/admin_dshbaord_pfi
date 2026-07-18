<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Equipment extends Model
{
    use SoftDeletes;

    protected $table = 'equipment';

    protected $fillable = [
        'contractor_id',
        'equipment_type_id',
        'name',
        'description',
        'manufacture_year',
        'power',
        'condition',
        'governorate',
        'city',
        'daily_price',
        'owner_phone',
        'status',
        'admin_notes',
    ];

    protected $casts = [
        'daily_price'      => 'decimal:2',
        'manufacture_year' => 'integer',
    ];

    public function contractor()
    {
        return $this->belongsTo(Contractor::class);
    }

    public function type()
    {
        return $this->belongsTo(EquipmentType::class, 'equipment_type_id');
    }

    public function images()
    {
        return $this->hasMany(EquipmentImage::class)->orderBy('sort_order');
    }

    public function primaryImage()
    {
        return $this->hasOne(EquipmentImage::class)->where('is_primary', true);
    }

    public function blockedDates()
    {
        return $this->hasMany(EquipmentBlockedDate::class);
    }
}

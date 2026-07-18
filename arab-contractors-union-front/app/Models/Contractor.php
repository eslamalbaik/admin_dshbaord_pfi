<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Contractor extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name', 'license_number', 'trade', 'classification',
        'established_year', 'owner_name', 'email', 'phone',
        'city', 'address', 'status', 'cr_file', 'id_file', 'notes',
    ];

    protected $casts = [
        'established_year' => 'integer',
    ];

    // ========== Relations ==========

    public function memberships()
    {
        return $this->hasMany(Membership::class);
    }

    public function activeMembership()
    {
        return $this->hasOne(Membership::class)
            ->where('status', 'active')
            ->latestOfMany();
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function penalties()
    {
        return $this->hasMany(Penalty::class);
    }

    public function inspections()
    {
        return $this->hasMany(Inspection::class);
    }

    public function documents()
    {
        return $this->hasMany(Document::class);
    }
}

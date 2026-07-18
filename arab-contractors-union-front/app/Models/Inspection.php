<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Inspection extends Model
{
    protected $fillable = [
        'contractor_id', 'location', 'lat', 'lng',
        'scheduled_at', 'status', 'notes', 'findings', 'inspector_id',
    ];

    protected $casts = [
        'lat'          => 'decimal:7',
        'lng'          => 'decimal:7',
        'scheduled_at' => 'datetime',
    ];

    public function contractor()
    {
        return $this->belongsTo(Contractor::class);
    }

    public function inspector()
    {
        return $this->belongsTo(User::class, 'inspector_id');
    }
}

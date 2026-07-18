<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tender extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'title', 'description', 'budget', 'deadline',
        'status', 'bids_count', 'created_by',
    ];

    protected $casts = [
        'deadline'   => 'date',
        'budget'     => 'decimal:2',
        'bids_count' => 'integer',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

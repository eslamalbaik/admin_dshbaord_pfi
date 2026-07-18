<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tender extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'title', 'description', 'union_notes', 'category', 'budget', 'deadline',
        'status', 'bids_count', 'created_by',
        'submission_types', 'submission_email', 'submission_phone', 'submission_file',
        'external_url',
    ];

    protected $casts = [
        'deadline'         => 'date',
        'budget'           => 'decimal:2',
        'bids_count'       => 'integer',
        'submission_types' => 'array',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

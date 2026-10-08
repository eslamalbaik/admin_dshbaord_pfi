<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'actor_id', 'actor_type', 'action', 'is_critical', 'subject_type', 'subject_id', 'meta', 'created_at',
    ];

    protected $casts = [
        'meta'        => 'array',
        'is_critical' => 'boolean',
        'created_at' => 'datetime',
    ];

    public function actor()
    {
        return $this->morphTo();
    }

    public function subject()
    {
        return $this->morphTo();
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Lesson extends Model
{
    protected $fillable = [
        'title', 'video_url', 'duration', 'order', 'module_id'
    ];

    public function module()
    {
        return $this->belongsTo(Module::class);
    }
}

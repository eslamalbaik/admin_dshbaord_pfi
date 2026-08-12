<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AnnouncementAcknowledgement extends Model
{
    public $timestamps = false;

    protected $fillable = ['contractor_id', 'announcement_id', 'acknowledged_at'];

    protected $casts = ['acknowledged_at' => 'datetime'];

    public function contractor()
    {
        return $this->belongsTo(Contractor::class);
    }

    public function announcement()
    {
        return $this->belongsTo(Announcement::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AnnouncementCategory extends Model
{
    protected $fillable = ['name', 'is_active'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function announcements()
    {
        return $this->hasMany(Announcement::class, 'category_id');
    }
}

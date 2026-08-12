<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Announcement extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'title', 'body', 'image', 'is_published', 'is_pinned', 'published_at', 'created_by',
    ];

    protected $casts = [
        'is_published' => 'boolean',
        'is_pinned'    => 'boolean',
        'published_at' => 'datetime',
    ];

    public function scopePublished($query)
    {
        return $query->where('is_published', true)
                     ->whereNotNull('published_at')
                     ->where('published_at', '<=', now());
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function acknowledgements()
    {
        return $this->hasMany(AnnouncementAcknowledgement::class);
    }
}

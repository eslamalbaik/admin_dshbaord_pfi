<?php

namespace App\Models;

use App\Models\Concerns\GeneratesSlug;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class News extends Model
{
    use SoftDeletes, GeneratesSlug;

    protected $table = 'news';

    protected $fillable = [
        'title', 'slug', 'excerpt', 'body',
        'image', 'video_url', 'external_url', 'gallery',
        'category', 'is_published',
        'published_at', 'created_by',
    ];

    protected $casts = [
        'is_published' => 'boolean',
        'published_at' => 'datetime',
        'gallery'      => 'array',
    ];

    // ─── Scopes ──────────────────────────────────────────────────────────────

    public function scopePublished($query)
    {
        return $query->where('is_published', true)
                     ->whereNotNull('published_at')
                     ->where('published_at', '<=', now());
    }

    // ─── Relations ───────────────────────────────────────────────────────────

    public function author()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

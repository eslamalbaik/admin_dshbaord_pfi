<?php

namespace App\Models;

use App\Models\Concerns\GeneratesSlug;
use App\Models\Concerns\HasPublicMediaUrls;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class News extends Model
{
    use SoftDeletes, GeneratesSlug, HasPublicMediaUrls;

    protected $table = 'news';

    public const CATEGORIES = ['union', 'local', 'international'];

    protected $fillable = [
        'title', 'slug', 'excerpt', 'category', 'body',
        'image', 'video_url', 'external_url', 'gallery',
        'is_published', 'convened_at',
        'published_at', 'created_by',
    ];

    protected $casts = [
        'is_published' => 'boolean',
        'convened_at'  => 'date:Y-m-d',
        'published_at' => 'datetime',
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

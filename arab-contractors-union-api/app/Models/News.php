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
    ];

    /**
     * يُخزَّن مسار نسبي داخل قرص public (لا رابط كامل) — الرابط العام يُبنى هنا وقت القراءة
     * عبر asset()، فيعكس دومين APP_URL الحالي دوماً بدل ما يتجمّد على قيمته وقت الرفع.
     */
    public function getImageAttribute(?string $value): ?string
    {
        return $value ? asset('storage/' . $value) : null;
    }

    /** @return string[] */
    public function getGalleryAttribute(?string $value): array
    {
        $paths = $value ? (json_decode($value, true) ?? []) : [];

        return array_map(fn ($path) => asset('storage/' . $path), $paths);
    }

    /** @param string[]|null $value مصفوفة مسارات نسبية — تُخزَّن JSON كما كانت بالـ cast السابق */
    public function setGalleryAttribute(?array $value): void
    {
        $this->attributes['gallery'] = $value ? json_encode(array_values($value)) : null;
    }

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

<?php

namespace App\Models;

use App\Models\Concerns\GeneratesSlug;
use App\Models\Concerns\HasPublicMediaUrls;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Event extends Model
{
    use SoftDeletes, GeneratesSlug, HasPublicMediaUrls;

    public const EVENT_TYPES = ['international', 'institutional', 'local'];

    protected $fillable = [
        'title', 'slug', 'excerpt', 'body',
        'image', 'video_url', 'external_url', 'gallery',
        'is_published',
        'event_date', 'event_location', 'event_format', 'is_international', 'event_type', 'stream_url', 'speakers',
        'published_at', 'created_by',
    ];

    protected $casts = [
        'is_published'     => 'boolean',
        'published_at'     => 'datetime',
        'event_date'       => 'datetime',
        // لا cast على gallery — الـ accessor/mutator في HasPublicMediaUrls يتولّياه
        'is_international' => 'boolean',
        'speakers'         => 'array',
    ];

    protected static function booted(): void
    {
        // is_international بديل محسوب مؤقت (deprecated) لضمان توافق أي مستهلك قديم للـAPI لم يُحدَّث بعد
        static::saving(function (Event $event) {
            if ($event->isDirty('event_type'))
                $event->is_international = $event->event_type === 'international';
        });
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

    public function registrations()
    {
        return $this->hasMany(EventRegistration::class);
    }
}

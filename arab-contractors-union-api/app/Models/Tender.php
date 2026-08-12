<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tender extends Model
{
    use SoftDeletes;

    /** سقف 5 مجالات رئيسية (REQ-12) + "عام" كتصنيف احتياطي للمجالات النادرة */
    public const CATEGORIES = ['مباني', 'طرق', 'بنية تحتية', 'قطاع صحي', 'قطاع تعليمي', 'عام'];

    protected $fillable = [
        'title', 'description', 'union_notes', 'category', 'budget', 'deadline',
        'status', 'archived_at', 'bids_count', 'created_by',
        'submission_types', 'submission_email', 'submission_phone', 'submission_file',
        'external_url',
    ];

    protected $casts = [
        'deadline'         => 'date',
        'archived_at'      => 'datetime',
        'budget'           => 'decimal:2',
        'bids_count'       => 'integer',
        'submission_types' => 'array',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function attachments()
    {
        return $this->hasMany(TenderAttachment::class);
    }

    public function bookmarks()
    {
        return $this->hasMany(TenderBookmark::class);
    }

    public function bookmarkedBy()
    {
        return $this->belongsToMany(Contractor::class, 'tender_bookmarks');
    }

    /** فعّال = مفتوح ولم يتجاوز موعد التسليم بعد (REQ-09) */
    public function getIsActiveAttribute(): bool
    {
        return $this->status === 'open' && (! $this->deadline || $this->deadline->isFuture());
    }
}

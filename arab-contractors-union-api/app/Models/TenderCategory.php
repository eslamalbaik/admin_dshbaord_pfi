<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * تصنيف عطاء قابل للإدارة من لوحة التحكم، مع صورة افتراضية تُعرض بدل صورة العطاء
 * (العطاءات لا تحمل صوراً خاصة بها). الربط بالعطاء عبر الاسم: tenders.category = name.
 */
class TenderCategory extends Model
{
    protected $fillable = ['name', 'image_path', 'is_active', 'sort_order'];

    protected $casts = [
        'is_active'  => 'boolean',
        'sort_order' => 'integer',
    ];

    protected $appends = ['image_url'];

    protected $hidden = ['image_path'];

    public function getImageUrlAttribute(): ?string
    {
        return $this->image_path ? Storage::disk('public')->url($this->image_path) : null;
    }

    public function tenders()
    {
        return $this->hasMany(Tender::class, 'category', 'name');
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    /** أسماء التصنيفات الفعّالة — المسموح اختيارها لعطاء جديد/معدَّل */
    public static function activeNames(): array
    {
        return static::where('is_active', true)->ordered()->pluck('name')->all();
    }

    /** [name => image_url] لكل التصنيفات ذات الصورة — استعلام واحد بدل استعلام لكل عطاء */
    public static function imageMap(): array
    {
        return static::whereNotNull('image_path')->get()
            ->mapWithKeys(fn (self $c) => [$c->name => $c->image_url])
            ->all();
    }
}

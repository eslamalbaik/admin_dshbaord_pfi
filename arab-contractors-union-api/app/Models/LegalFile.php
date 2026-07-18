<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class LegalFile extends Model
{
    protected $fillable = [
        'title',
        'title_en',
        'description',
        'description_en',
        'category',
        'file_path',
        'disk',
        'mime_type',
        'size',
        'sort',
        'is_active',
        'uploaded_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort'      => 'integer',
        'size'      => 'integer',
    ];

    /** تصنيفات الملفات */
    public static array $categories = ['legislation', 'mou', 'other'];

    /** عنوان URL الكامل للملف */
    public function getUrlAttribute(): string
    {
        return Storage::disk($this->disk)->url($this->file_path);
    }

    /** حجم الملف مُنسَّق */
    public function getFormattedSizeAttribute(): string
    {
        $bytes = $this->size ?? 0;
        if ($bytes < 1024)    return "{$bytes} B";
        if ($bytes < 1048576) return round($bytes / 1024, 1) . ' KB';
        return round($bytes / 1048576, 1) . ' MB';
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}

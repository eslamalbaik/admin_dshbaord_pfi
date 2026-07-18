<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Document extends Model
{
    protected $fillable = [
        'contractor_id', 'title', 'type', 'url',
        'disk', 'size', 'mime_type', 'uploaded_by',
    ];

    protected $casts = [
        'size' => 'integer',
    ];

    // حجم الملف مُنسَّق
    public function getFormattedSizeAttribute(): string
    {
        $bytes = $this->size ?? 0;
        if ($bytes < 1024) return "{$bytes} B";
        if ($bytes < 1048576) return round($bytes / 1024, 1) . ' KB';
        return round($bytes / 1048576, 1) . ' MB';
    }

    public function contractor()
    {
        return $this->belongsTo(Contractor::class);
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class TenderAttachment extends Model
{
    protected $fillable = ['tender_id', 'file_path', 'label'];

    protected $appends = ['file_url', 'is_image'];

    public function tender()
    {
        return $this->belongsTo(Tender::class);
    }

    public function getFileUrlAttribute(): ?string
    {
        return $this->file_path ? Storage::disk('public')->url($this->file_path) : null;
    }

    public function getIsImageAttribute(): bool
    {
        return (bool) preg_match('/\.(jpe?g|png|webp)$/i', $this->file_path ?? '');
    }
}

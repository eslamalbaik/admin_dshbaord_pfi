<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SiteVideoView extends Model
{
    protected $fillable = ['video_key', 'views'];

    protected $casts = ['views' => 'integer'];
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventRegistration extends Model
{
    public $timestamps = false;

    protected $fillable = ['news_id', 'contractor_id', 'registered_at'];

    protected $casts = ['registered_at' => 'datetime'];

    public function contractor()
    {
        return $this->belongsTo(Contractor::class);
    }

    public function news()
    {
        return $this->belongsTo(News::class);
    }
}

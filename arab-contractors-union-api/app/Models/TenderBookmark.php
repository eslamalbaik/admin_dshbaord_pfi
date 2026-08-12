<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TenderBookmark extends Model
{
    protected $fillable = ['contractor_id', 'tender_id'];

    public function contractor()
    {
        return $this->belongsTo(Contractor::class);
    }

    public function tender()
    {
        return $this->belongsTo(Tender::class);
    }
}

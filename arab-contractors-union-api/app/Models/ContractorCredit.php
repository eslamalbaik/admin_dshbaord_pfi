<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/** رصيد دائن للشركة لدى الاتحاد (دفعة مقدّمة) بالدينار الأردني */
class ContractorCredit extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'contractor_id', 'amount_jod', 'used_jod', 'description',
        'source', 'notes', 'created_by',
    ];

    protected $casts = [
        'amount_jod' => 'decimal:2',
        'used_jod'   => 'decimal:2',
    ];

    public function contractor()
    {
        return $this->belongsTo(Contractor::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentStatusChange extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['payment_id', 'from_status', 'to_status', 'reason', 'changed_by'];

    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }

    public function changer()
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}

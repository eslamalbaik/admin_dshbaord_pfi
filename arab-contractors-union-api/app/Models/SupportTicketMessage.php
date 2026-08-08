<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class SupportTicketMessage extends Model
{
    protected $fillable = [
        'support_ticket_id', 'sender_type', 'sender_id', 'message', 'attachment',
    ];

    protected $appends = ['attachment_url', 'sender_name'];

    public function ticket()
    {
        return $this->belongsTo(SupportTicket::class, 'support_ticket_id');
    }

    public function getAttachmentUrlAttribute(): ?string
    {
        return $this->attachment ? Storage::disk('public')->url($this->attachment) : null;
    }

    public function getSenderNameAttribute(): ?string
    {
        return $this->sender_type === 'admin'
            ? User::find($this->sender_id)?->name
            : Contractor::find($this->sender_id)?->name;
    }
}

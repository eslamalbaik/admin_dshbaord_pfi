<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class AssistantConversation extends Model
{
    protected $fillable = ['session_key', 'ip_hash', 'user_agent', 'page', 'messages_count', 'last_message_at'];

    protected $hidden = ['ip_hash', 'session_key'];

    protected $casts = ['last_message_at' => 'datetime'];

    public function messages(): HasMany
    {
        return $this->hasMany(AssistantMessage::class)->orderBy('id');
    }

    /** أول سؤال كتبه الزائر (للعرض بالقائمة) */
    public function firstQuestion(): HasOne
    {
        return $this->hasOne(AssistantMessage::class)->where('role', 'user')->oldestOfMany();
    }
}

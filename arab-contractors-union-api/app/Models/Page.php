<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Page extends Model
{
    protected $fillable = [
        'slug', 'title', 'content', 'is_published', 'created_by',
    ];

    protected $casts = [
        'is_published' => 'boolean',
    ];

    /** slugs محجوزة لا يجوز استخدامها كصفحات ديناميكية */
    public const RESERVED_SLUGS = [
        'landing', 'contractor', 'contractors', 'admin', 'dashboards', 'dashboard',
        'api', 'login', 'register', 'under-construction', 'not-authorized',
        'settings', 'news', 'tenders', 'payments', 'analytics', 'preview',
    ];

    public function author()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

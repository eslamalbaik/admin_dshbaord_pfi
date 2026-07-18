<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * مفاتيح إعدادات جديدة:
 * - بيانات الاتحاد (شاشة "عن الاتحاد"): الاسم، النبذة، العنوان، أرقام التواصل، البريد، الشعار.
 * - روابط التواصل الاجتماعي (General Settings).
 */
return new class extends Migration
{
    private array $keys = [
        // ─── عن الاتحاد ───
        ['key' => 'union_name',       'group' => 'about'],
        ['key' => 'union_name_en',    'group' => 'about'],
        ['key' => 'union_about',      'group' => 'about'],
        ['key' => 'union_address',    'group' => 'about'],
        ['key' => 'union_phone',      'group' => 'about'],
        ['key' => 'union_phone2',     'group' => 'about'],
        ['key' => 'union_email',      'group' => 'about'],
        ['key' => 'union_logo',       'group' => 'about'],
        // ─── روابط التواصل الاجتماعي ───
        ['key' => 'social_facebook',  'group' => 'social'],
        ['key' => 'social_instagram', 'group' => 'social'],
        ['key' => 'social_twitter',   'group' => 'social'],
        ['key' => 'social_linkedin',  'group' => 'social'],
        ['key' => 'social_youtube',   'group' => 'social'],
        ['key' => 'social_website',   'group' => 'social'],
    ];

    public function up(): void
    {
        foreach ($this->keys as $item) {
            DB::table('settings')->updateOrInsert(
                ['key' => $item['key']],
                ['value' => '', 'group' => $item['group'], 'created_at' => now(), 'updated_at' => now()],
            );
        }
    }

    public function down(): void
    {
        DB::table('settings')->whereIn('key', array_column($this->keys, 'key'))->delete();
    }
};

<?php

namespace Tests\Feature;

use App\Models\Contractor;
use App\Notifications\CompleteProfileNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * البيانات خاصة بالشركة: "الملف الشخصي" صار "الملف التعريفي" بالإشعارات الجديدة والمخزّنة.
 */
class ProfileWordingMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_complete_profile_notification_uses_company_wording(): void
    {
        $data = (new CompleteProfileNotification())->toArray(new Contractor());

        $this->assertSame('أكمل بيانات ملفك التعريفي', $data['title']);
        $this->assertStringNotContainsString('شخصي', $data['message']);
    }

    public function test_migration_rewrites_stored_notifications(): void
    {
        $id = (string) Str::uuid();
        DB::table('notifications')->insert([
            'id'              => $id,
            'type'            => CompleteProfileNotification::class,
            'notifiable_type' => Contractor::class,
            'notifiable_id'   => 1,
            'data'            => json_encode([
                'type'    => 'complete_profile',
                'title'   => 'أكمل بيانات ملفك الشخصي',
                'message' => 'يجب إكمال بيانات ملفك الشخصي ومرفقاته قبل التمكّن من طلب شهادة عضوية.',
            ]),
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        $migration = require database_path('migrations/2026_10_07_000002_rename_personal_profile_wording_in_notifications.php');
        $migration->up();

        $data = json_decode(DB::table('notifications')->where('id', $id)->value('data'), true);
        $this->assertSame('أكمل بيانات ملفك التعريفي', $data['title']);
        $this->assertSame('يجب إكمال بيانات ملفك التعريفي ومرفقاته قبل التمكّن من طلب شهادة عضوية.', $data['message']);
        $this->assertSame('complete_profile', $data['type']);
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // البيانات خاصة بالشركة، فـ"الملف الشخصي" صار "الملف التعريفي" — تحديث نص الإشعارات المخزّنة قبل التعديل
    private const TYPES = [
        'App\\Notifications\\CompleteProfileNotification',
        'App\\Notifications\\ContractorProfileUpdatedNotification',
    ];

    public function up(): void
    {
        $this->rewrite(fn (string $text) => preg_replace('/(ملف\S*\s+(?:ال)?)شخصي(?!ة)/u', '$1تعريفي', $text));
    }

    public function down(): void
    {
        $this->rewrite(fn (string $text) => preg_replace('/(ملف\S*\s+(?:ال)?)تعريفي/u', '$1شخصي', $text));
    }

    private function rewrite(callable $replace): void
    {
        DB::table('notifications')->whereIn('type', self::TYPES)->orderBy('id')
            ->chunk(500, function ($rows) use ($replace) {
                foreach ($rows as $row) {
                    $data = json_decode($row->data, true);
                    if (! is_array($data)) {
                        continue;
                    }

                    $changed = false;
                    foreach (['title', 'message'] as $key) {
                        if (isset($data[$key]) && is_string($data[$key])) {
                            $new = $replace($data[$key]);
                            $changed = $changed || $new !== $data[$key];
                            $data[$key] = $new;
                        }
                    }

                    if ($changed) {
                        DB::table('notifications')->where('id', $row->id)->update(['data' => json_encode($data)]);
                    }
                }
            });
    }
};

<?php

namespace Database\Factories;

use App\Models\Announcement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Announcement>
 */
class AnnouncementFactory extends Factory
{
    protected $model = Announcement::class;

    /**
     * الحالة الافتراضية: مسودة — لا يوجد عمود status على الجدول، الحالة مشتقّة من
     * is_published + published_at عبر effective_status (draft/scheduled/published).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title'        => fake()->sentence(6),
            'body'         => fake()->paragraph(),
            'is_published' => false,
            'published_at' => null,
            'created_by'   => null,
        ];
    }

    /**
     * إعلان منشور فعلاً الآن.
     */
    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_published' => true,
            'published_at' => now(),
        ]);
    }

    /**
     * إعلان مجدول لتاريخ مستقبلي — is_published صحيح لكنه غير مرئي بعد.
     */
    public function scheduled(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_published' => true,
            'published_at' => now()->addWeek(),
        ]);
    }
}

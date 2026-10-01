<?php

namespace Database\Factories;

use App\Models\Contractor;
use App\Models\Membership;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Membership>
 */
class MembershipFactory extends Factory
{
    protected $model = Membership::class;

    /**
     * عضوية فعّالة تنتهي بعد سنة — expires_at هنا هو المصدر الوحيد لتاريخ انتهاء
     * الاشتراك (لا يوجد عمود مقابل على جدول contractors).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'contractor_id' => Contractor::factory(),
            'type'          => 'new',
            'status'        => 'active',
            'starts_at'     => now()->subYear(),
            'expires_at'    => now()->addYear(),
            'amount'        => 0,
        ];
    }

    /**
     * عضوية تنتهي خلال $days يوماً — تُستخدم لاختبار نافذة تذكير التجديد.
     */
    public function expiringInDays(int $days): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => now()->addDays($days),
        ]);
    }

    /**
     * عضوية منتهية منذ $days يوماً (فترة السماح).
     */
    public function expiredDaysAgo(int $days): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => now()->subDays($days),
        ]);
    }
}

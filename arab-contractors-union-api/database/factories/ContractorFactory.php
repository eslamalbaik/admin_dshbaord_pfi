<?php

namespace Database\Factories;

use App\Models\Contractor;
use App\Rules\MembershipNumber;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Contractor>
 */
class ContractorFactory extends Factory
{
    protected $model = Contractor::class;

    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * مقاول فعّال بحساب جاهز على التطبيق — الأعمدة الفريدة (رقم العضوية، الهاتف،
     * البريد، رقم الرخصة، السجل التجاري) مولّدة عبر unique() لأن كلاً منها عليه
     * unique index في جدول contractors.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'membership_number'  => $this->newMembershipNumber(),
            'name'               => 'شركة ' . fake()->unique()->company() . ' للمقاولات',
            'authorized_person'  => fake()->name(),
            'owner_name'         => fake()->name(),
            'commercial_register' => (string) fake()->unique()->numberBetween(100000, 999999),
            'license_number'     => (string) fake()->unique()->numberBetween(100000, 999999),
            'email'              => fake()->unique()->safeEmail(),
            'phone'              => '059' . fake()->unique()->numerify('#######'),
            'phone_verified_at'  => now(),
            'city'               => 'غزة',
            'address'            => fake()->streetAddress(),
            'status'             => 'active',
            'is_frozen'          => false,
            'profile_completed'  => false,
            'password'           => static::$password ??= Hash::make('password'),
            'remember_token'     => Str::random(10),
            'fcm_token'          => null,
        ];
    }

    /**
     * رقم عضوية بالهيكلية الجديدة (928_g فما فوق) — راجع App\Rules\MembershipNumber.
     * لا نستخدم Contractor::nextMembershipNumber() هنا لأنها تقرأ من القاعدة،
     * فتعطي نفس الرقم للسجلات المبنية دفعة واحدة قبل الحفظ.
     */
    private function newMembershipNumber(): string
    {
        return fake()->unique()->numberBetween(MembershipNumber::NEW_MIN, 999999)
            . MembershipNumber::NEW_SUFFIX;
    }

    /**
     * مقاول مجمّد — is_frozen وحده يقفل الدخول للتطبيق (راجع EnsureContractorIsActive).
     */
    public function frozen(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_frozen' => true,
        ]);
    }

    /**
     * مقاول موقوف — يمنع تجديد العضوية فقط، لا يقفل الدخول.
     */
    public function suspended(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'suspended',
        ]);
    }

    /**
     * مقاول لم يفتح حساباً على التطبيق بعد (لا كلمة مرور ولا تحقق من الجوال).
     */
    public function withoutAppAccount(): static
    {
        return $this->state(fn (array $attributes) => [
            'password'          => null,
            'phone_verified_at' => null,
        ]);
    }

    /**
     * مقاول لديه رمز جهاز صالح لاستقبال إشعارات FCM.
     */
    public function withFcmToken(?string $token = null): static
    {
        return $this->state(fn (array $attributes) => [
            'fcm_token' => $token ?? 'test-fcm-token-' . Str::random(16),
        ]);
    }
}

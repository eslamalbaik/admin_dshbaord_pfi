<?php

namespace Database\Seeders;

use App\Models\Contractor;
use App\Models\Document;
use App\Models\Equipment;
use App\Models\EquipmentType;
use App\Models\Membership;
use App\Models\Payment;
use App\Models\Penalty;
use App\Models\Tender;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class UnionSeeder extends Seeder
{
    public function run(): void
    {
        // ===========================
        // 1. Admin User
        // ===========================
        $admin = User::firstOrCreate(
            ['email' => 'admin@union.ps'],
            [
                'name'     => 'مدير الاتحاد',
                'password' => bcrypt('admin123'),
                'role'     => 'admin',
            ]
        );

        // ===========================
        // 2. Accountant User
        // ===========================
        User::firstOrCreate(
            ['email' => 'accountant@union.ps'],
            [
                'name'     => 'محاسب الاتحاد',
                'password' => bcrypt('accountant123'),
                'role'     => 'accountant',
            ]
        );

        // ===========================
        // 3. Demo Contractors
        // ===========================
        $contractorsData = [
            ['name' => 'شركة المدار للمقاولات العامة',  'trade' => 'إنشاءات عامة',  'classification' => 'أ', 'status' => 'active',   'city' => 'غزة',    'phone' => '0599-100001', 'license_number' => 'LIC-001'],
            ['name' => 'مؤسسة النور للإنشاءات',          'trade' => 'كهرباء',         'classification' => 'ب', 'status' => 'active',   'city' => 'رفح',    'phone' => '0599-100002', 'license_number' => 'LIC-002'],
            ['name' => 'شركة الفارابي للطرق والمدنية',   'trade' => 'طرق ومدنية',     'classification' => 'أ', 'status' => 'active',   'city' => 'خانيونس','phone' => '0599-100003', 'license_number' => 'LIC-003'],
            ['name' => 'مقاولات عمر خليل',               'trade' => 'سباكة',          'classification' => 'ج', 'status' => 'pending',  'city' => 'بيت لحم','phone' => '0599-100004', 'license_number' => 'LIC-004'],
            ['name' => 'شركة الأمل للتشطيبات',           'trade' => 'تشطيبات',        'classification' => 'ب', 'status' => 'active',   'city' => 'نابلس',  'phone' => '0599-100005', 'license_number' => 'LIC-005'],
            ['name' => 'مؤسسة البناء الحديث',            'trade' => 'إنشاءات عامة',  'classification' => 'ب', 'status' => 'expired',  'city' => 'رام الله','phone' => '0599-100006', 'license_number' => 'LIC-006'],
            ['name' => 'شركة التقنية للاتصالات',         'trade' => 'اتصالات',        'classification' => 'ج', 'status' => 'active',   'city' => 'جنين',   'phone' => '0599-100007', 'license_number' => 'LIC-007'],
            ['name' => 'مقاولات سارة المحمد',            'trade' => 'ميكانيكا',       'classification' => 'د', 'status' => 'pending',  'city' => 'طولكرم', 'phone' => '0599-100008', 'license_number' => 'LIC-008'],
        ];

        $contractors = [];
        foreach ($contractorsData as $data) {
            $data['email']      = strtolower(str_replace([' ', 'ا', 'ل'], ['', 'a', ''], $data['license_number'])) . '@union.ps';
            $data['owner_name'] = 'صاحب ' . $data['name'];
            $data['password']   = bcrypt('contractor123'); // كلمة مرور موحّدة للتجربة
            $contractors[]      = Contractor::firstOrCreate(['license_number' => $data['license_number']], $data);
        }

        // ===========================
        // 3. Memberships
        // ===========================
        foreach ($contractors as $i => $contractor) {
            $status     = $contractor->status === 'expired' ? 'expired' : ($contractor->status === 'pending' ? 'pending' : 'active');
            $startsAt   = Carbon::now()->subMonths(rand(1, 10));
            $expiresAt  = $status === 'expired'
                            ? Carbon::now()->subDays(rand(10, 60))
                            : Carbon::now()->addMonths(rand(2, 14));

            Membership::firstOrCreate(
                ['contractor_id' => $contractor->id],
                [
                    'type'        => 'new',
                    'status'      => $status,
                    'starts_at'   => $startsAt,
                    'expires_at'  => $expiresAt,
                    'amount'      => [500, 750, 1000, 1200][rand(0, 3)],
                    'reviewed_by' => $admin->id,
                    'reviewed_at' => $startsAt,
                ]
            );
        }

        // ===========================
        // 4. Payments
        // ===========================
        $paymentTypes = ['membership_fee', 'renewal_fee', 'other'];
        $methods      = ['bank_transfer', 'cash', 'card'];

        foreach ($contractors as $contractor) {
            $count = rand(1, 4);
            for ($j = 0; $j < $count; $j++) {
                Payment::create([
                    'contractor_id' => $contractor->id,
                    'amount'        => rand(400, 1500),
                    'type'          => $paymentTypes[array_rand($paymentTypes)],
                    'status'        => 'paid',
                    'method'        => $methods[array_rand($methods)],
                    'paid_at'       => Carbon::now()->subDays(rand(1, 90)),
                    'created_at'    => Carbon::now()->subDays(rand(1, 90)),
                ]);
            }
        }

        // ===========================
        // 5. Penalties
        // ===========================
        $penaltyReasons = [
            'مخالفة شروط السلامة في الموقع',
            'التأخر في تسليم المشروع',
            'استخدام مواد غير مطابقة للمواصفات',
            'عدم الالتزام بمعايير البيئة',
        ];

        foreach (array_slice($contractors, 0, 4) as $contractor) {
            Penalty::create([
                'contractor_id' => $contractor->id,
                'reason'        => $penaltyReasons[array_rand($penaltyReasons)],
                'amount'        => rand(200, 800),
                'status'        => rand(0, 1) ? 'paid' : 'unpaid',
                'created_at'    => Carbon::now()->subDays(rand(5, 60)),
            ]);
        }

        // ===========================
        // 6. Tenders
        // ===========================
        $tendersData = [
            ['title' => 'عطاء توسعة شبكة الطرق المحلية — غزة',        'budget' => 850000,  'status' => 'open'],
            ['title' => 'عطاء ترميم البنية التحتية — رفح',            'budget' => 1200000, 'status' => 'open'],
            ['title' => 'عطاء إنشاء مبنى إداري — خانيونس',            'budget' => 600000,  'status' => 'closed'],
            ['title' => 'عطاء تمديد شبكة الكهرباء — شمال غزة',        'budget' => 420000,  'status' => 'open'],
            ['title' => 'عطاء إنشاء ملعب رياضي — بيت لحم',            'budget' => 350000,  'status' => 'cancelled'],
        ];

        foreach ($tendersData as $data) {
            Tender::firstOrCreate(
                ['title' => $data['title']],
                array_merge($data, [
                    'description' => 'تفاصيل عطاء ' . $data['title'],
                    'deadline'    => Carbon::now()->addDays(rand(15, 60)),
                    'created_by'  => $admin->id,
                ])
            );
        }

        // ===========================
        // 7. Equipment Types — أنواع المعدات
        // ===========================
        $typesData = [
            ['name_ar' => 'حفارة',           'name_en' => 'Excavator',      'icon' => 'tabler-crane'],
            ['name_ar' => 'بلدوزر',          'name_en' => 'Bulldozer',      'icon' => 'tabler-bulldozer'],
            ['name_ar' => 'كرين',            'name_en' => 'Crane',          'icon' => 'tabler-crane'],
            ['name_ar' => 'لودر',            'name_en' => 'Loader',         'icon' => 'tabler-crane'],
            ['name_ar' => 'شاحنة قلاب',      'name_en' => 'Dump Truck',     'icon' => 'tabler-truck'],
            ['name_ar' => 'رافعة شوكية',      'name_en' => 'Forklift',       'icon' => 'tabler-crane'],
            ['name_ar' => 'ضاغطة أرضية',     'name_en' => 'Compactor',      'icon' => 'tabler-bulldozer'],
            ['name_ar' => 'خلاطة خرسانة',    'name_en' => 'Concrete Mixer', 'icon' => 'tabler-tools'],
            ['name_ar' => 'مضخة خرسانة',     'name_en' => 'Concrete Pump',  'icon' => 'tabler-tools'],
            ['name_ar' => 'حفر بئر',         'name_en' => 'Well Driller',   'icon' => 'tabler-tools'],
        ];

        $types = [];
        foreach ($typesData as $typeData) {
            $types[] = EquipmentType::firstOrCreate(
                ['name_ar' => $typeData['name_ar']],
                array_merge($typeData, ['is_active' => true])
            );
        }

        // ===========================
        // 8. Sample Equipment — آليات تجريبية
        // ===========================
        $governorates = ['غزة', 'رفح', 'خانيونس', 'الوسطى', 'شمال غزة', 'رام الله', 'نابلس', 'جنين'];
        $conditions   = ['excellent', 'good', 'fair'];
        $statuses     = ['visible', 'visible', 'visible', 'hidden', 'suspended'];

        $activeContractors = array_filter($contractors, fn($c) => $c->status === 'active');
        $activeContractors = array_values($activeContractors);

        if (count($activeContractors) > 0 && count($types) > 0) {
            $equipmentData = [
                ['name' => 'حفارة كوماتسو PC200',     'type_idx' => 0, 'year' => 2019, 'power' => '152 HP', 'price' => 450],
                ['name' => 'بلدوزر كاتربيلار D6',     'type_idx' => 1, 'year' => 2018, 'power' => '215 HP', 'price' => 600],
                ['name' => 'كرين تادانو 50 طن',        'type_idx' => 2, 'year' => 2020, 'power' => '350 HP', 'price' => 900],
                ['name' => 'لودر فولفو L90',           'type_idx' => 3, 'year' => 2021, 'power' => '175 HP', 'price' => 380],
                ['name' => 'شاحنة قلاب مرسيدس 2636',  'type_idx' => 4, 'year' => 2017, 'power' => '360 HP', 'price' => 280],
                ['name' => 'ضاغطة دبل درام 12 طن',     'type_idx' => 6, 'year' => 2022, 'power' => '130 HP', 'price' => 320],
                ['name' => 'خلاطة خرسانة 8 م³',        'type_idx' => 7, 'year' => 2020, 'power' => '240 HP', 'price' => 250],
                ['name' => 'مضخة خرسانة 42 متر',       'type_idx' => 8, 'year' => 2019, 'power' => '180 HP', 'price' => 700],
            ];

            foreach ($equipmentData as $i => $eq) {
                $contractor = $activeContractors[$i % count($activeContractors)];
                $type       = $types[$eq['type_idx'] % count($types)];
                $gov        = $governorates[$i % count($governorates)];

                Equipment::firstOrCreate(
                    ['name' => $eq['name'], 'contractor_id' => $contractor->id],
                    [
                        'equipment_type_id' => $type->id,
                        'description'       => 'آلية بحالة ممتازة جاهزة للإيجار الفوري في ' . $gov,
                        'manufacture_year'  => $eq['year'],
                        'power'             => $eq['power'],
                        'condition'         => $conditions[$i % 3],
                        'governorate'       => $gov,
                        'city'              => $gov,
                        'daily_price'       => $eq['price'],
                        'owner_phone'       => $contractor->phone ?? '0599-' . str_pad($i + 1, 6, '0', STR_PAD_LEFT),
                        'status'            => $statuses[$i % count($statuses)],
                    ]
                );
            }
        }

        $this->command->info('✅ تم إنشاء بيانات اتحاد المقاولين بنجاح!');
        $this->command->info('   المدير: admin@union.ps / admin123');
        $this->command->info('   المقاولون: ' . count($contractors));
        $this->command->info('   أنواع المعدات: ' . count($types));
    }
}

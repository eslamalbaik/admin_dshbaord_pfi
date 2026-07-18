<?php

namespace Database\Seeders;

use App\Models\Contractor;
use App\Models\Document;
use App\Models\Inspection;
use App\Models\Membership;
use App\Models\Payment;
use App\Models\Penalty;
use App\Models\Tender;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class DatabaseSeeder extends Seeder
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
        // 2. Demo Contractors
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
            ['title' => 'مناقصة توسعة شبكة الطرق المحلية — غزة',       'budget' => 850000,  'status' => 'open'],
            ['title' => 'مناقصة ترميم البنية التحتية — رفح',           'budget' => 1200000, 'status' => 'open'],
            ['title' => 'مناقصة إنشاء مبنى إداري — خانيونس',          'budget' => 600000,  'status' => 'closed'],
            ['title' => 'مناقصة تمديد شبكة الكهرباء — شمال غزة',      'budget' => 420000,  'status' => 'open'],
            ['title' => 'مناقصة إنشاء ملعب رياضي — بيت لحم',         'budget' => 350000,  'status' => 'cancelled'],
        ];

        foreach ($tendersData as $data) {
            Tender::firstOrCreate(
                ['title' => $data['title']],
                array_merge($data, [
                    'description' => 'تفاصيل مناقصة ' . $data['title'],
                    'deadline'    => Carbon::now()->addDays(rand(15, 60)),
                    'bids_count'  => rand(0, 8),
                    'created_by'  => $admin->id,
                ])
            );
        }

        // ===========================
        // 7. Inspections
        // ===========================
        $locations = [
            ['location' => 'مشروع الطريق الشمالي — غزة',     'lat' => 31.5204, 'lng' => 34.4569],
            ['location' => 'موقع البناء — شارع الوحدة',       'lat' => 31.5018, 'lng' => 34.4672],
            ['location' => 'مشروع التمديدات — منطقة رفح',    'lat' => 31.2969, 'lng' => 34.2499],
        ];

        $statuses = ['completed', 'scheduled', 'cancelled'];

        foreach (array_slice($contractors, 0, 5) as $i => $contractor) {
            $loc = $locations[$i % count($locations)];
            Inspection::create([
                'contractor_id' => $contractor->id,
                'location'      => $loc['location'],
                'lat'           => $loc['lat'],
                'lng'           => $loc['lng'],
                'scheduled_at'  => Carbon::now()->addDays(rand(-10, 15)),
                'status'        => $statuses[$i % count($statuses)],
                'inspector_id'  => $admin->id,
                'notes'         => 'جولة تفتيشية دورية على الموقع',
                'created_at'    => Carbon::now()->subDays(rand(1, 20)),
            ]);
        }

        $this->command->info('✅ تم إنشاء بيانات اتحاد المقاولين بنجاح!');
        $this->command->info('   المدير: admin@union.ps / admin123');
        $this->command->info('   المقاولون: ' . count($contractors));
    }
}

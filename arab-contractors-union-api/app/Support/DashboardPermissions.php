<?php

namespace App\Support;

/**
 * صلاحيات لوحة التحكم للمشرفين (role = supervisor).
 *
 * كل قسم رئيسي فيه أقسام داخلية، وكل قسم داخلي إله مفتاح (مثلاً finance.dues)
 * والإجراءات المتاحة عليه. الصلاحية المخزّنة على المستخدم بتكون "{مفتاح}.{إجراء}"،
 * مثلاً finance.dues.view أو tenders.list.delete.
 *
 * الأدمن دايماً عنده كل الصلاحيات، والمحاسب بيضل على نظام الأدوار القديم (role:...)
 * بدون أي تغيير. الصلاحيات هون بتنطبق على المشرفين فقط.
 *
 * لإضافة قسم جديد: أضفه في SECTIONS، وحط perm:{مفتاح} على الـ routes تبعته،
 * وأضف اسم صفحته لـ ROUTE_PERMISSIONS بالفرونت (utils/permissions.ts).
 */
class DashboardPermissions
{
    public const VIEW   = 'view';
    public const CREATE = 'create';
    public const UPDATE = 'update';
    public const DELETE = 'delete';

    public const ACTIONS = [
        self::VIEW   => 'عرض',
        self::CREATE => 'إضافة',
        self::UPDATE => 'تعديل',
        self::DELETE => 'حذف',
    ];

    private const CRUD = [self::VIEW, self::CREATE, self::UPDATE, self::DELETE];

    public const SECTIONS = [
        'dashboard' => [
            'label'    => 'لوحة التحكم',
            'children' => [
                'dashboard.home' => ['label' => 'الإحصائيات الرئيسية', 'actions' => [self::VIEW]],
            ],
        ],
        'contractors' => [
            'label'    => 'المقاولون',
            'children' => [
                'contractors.list'        => ['label' => 'قائمة المقاولين وتسجيلهم', 'actions' => self::CRUD],
                'contractors.memberships' => ['label' => 'طلبات الانتساب', 'actions' => [self::VIEW, self::CREATE, self::UPDATE]],
                'contractors.penalties'   => ['label' => 'الغرامات والمخالفات', 'actions' => self::CRUD],
            ],
        ],
        'finance' => [
            'label'    => 'الشؤون المالية',
            'children' => [
                'finance.payments'       => ['label' => 'سجل المدفوعات', 'actions' => [self::VIEW, self::CREATE, self::UPDATE]],
                'finance.dues'           => ['label' => 'الذمم المالية', 'actions' => self::CRUD],
                'finance.dues_discounts' => ['label' => 'الخصومات على الذمم', 'actions' => [self::UPDATE]],
                'finance.balances'       => ['label' => 'أرصدة المقاولين', 'actions' => [self::VIEW, self::UPDATE]],
                'finance.bank_accounts'  => ['label' => 'الحسابات البنكية', 'actions' => self::CRUD],
                'finance.exchange_rates' => ['label' => 'أسعار الصرف', 'actions' => [self::VIEW, self::UPDATE]],
                'finance.grade_fees'     => ['label' => 'رسوم الدرجات', 'actions' => [self::VIEW, self::UPDATE]],
            ],
        ],
        'tenders' => [
            'label'    => 'العطاءات',
            'children' => [
                'tenders.list'       => ['label' => 'قائمة العطاءات', 'actions' => self::CRUD],
                'tenders.categories' => ['label' => 'تصنيفات العطاءات', 'actions' => self::CRUD],
            ],
        ],
        'legal' => [
            'label'    => 'المكتبة القانونية',
            'children' => [
                'legal.library' => ['label' => 'الملفات والتصنيفات', 'actions' => self::CRUD],
            ],
        ],
        'marketplace' => [
            'label'    => 'سوق الآليات',
            'children' => [
                'marketplace.equipment' => ['label' => 'الآليات', 'actions' => self::CRUD],
                'marketplace.types'     => ['label' => 'أنواع المعدات', 'actions' => self::CRUD],
                'marketplace.packages'  => ['label' => 'باقات الاشتراك', 'actions' => self::CRUD],
                'marketplace.reports'   => ['label' => 'بلاغات السوق', 'actions' => [self::VIEW, self::UPDATE]],
            ],
        ],
        'documents' => [
            'label'    => 'الوثائق',
            'children' => [
                'documents.manage' => ['label' => 'إدارة الوثائق', 'actions' => [self::VIEW, self::CREATE, self::DELETE]],
            ],
        ],
        'services' => [
            'label'    => 'خدمات الأعضاء',
            'children' => [
                'services.support_tickets'         => ['label' => 'الدعم الفني والشكاوى', 'actions' => [self::VIEW, self::UPDATE, self::DELETE]],
                'services.certificate_requests'    => ['label' => 'طلبات الشهادات', 'actions' => [self::VIEW, self::UPDATE, self::DELETE]],
                'services.membership_certificates' => ['label' => 'شهادة العضوية', 'actions' => self::CRUD],
            ],
        ],
        'content' => [
            'label'    => 'الأخبار والفعاليات والتعميمات',
            'children' => [
                'content.news'                    => ['label' => 'الأخبار', 'actions' => self::CRUD],
                'content.events'                  => ['label' => 'الفعاليات', 'actions' => self::CRUD],
                'content.announcements'           => ['label' => 'التعميمات', 'actions' => self::CRUD],
                'content.announcement_categories' => ['label' => 'تصنيفات التعميمات', 'actions' => self::CRUD],
            ],
        ],
        'reports' => [
            'label'    => 'التقارير والإشعارات',
            'children' => [
                'reports.analytics' => ['label' => 'التقارير والتصدير', 'actions' => [self::VIEW]],
                'reports.broadcast' => ['label' => 'بث إشعار للمقاولين', 'actions' => [self::CREATE]],
            ],
        ],
        'settings' => [
            'label'    => 'الإعدادات',
            'children' => [
                'settings.terms'          => ['label' => 'الشروط والأحكام وسياسة الخصوصية', 'actions' => self::CRUD],
                'settings.app'            => ['label' => 'إعدادات التطبيق', 'actions' => [self::VIEW, self::UPDATE]],
                'settings.pages'          => ['label' => 'الصفحات الديناميكية', 'actions' => self::CRUD],
                'settings.lookups'        => ['label' => 'المجالات والاختصاصات والدرجات', 'actions' => self::CRUD],
                'settings.governorates'   => ['label' => 'المحافظات والمدن', 'actions' => self::CRUD],
                'settings.activity_log'   => ['label' => 'سجل النشاط', 'actions' => [self::VIEW]],
            ],
        ],
    ];

    /** كل الأقسام الداخلية بشكل مسطّح: مفتاح => تعريف */
    public static function leaves(): array
    {
        $leaves = [];
        foreach (self::SECTIONS as $section) {
            $leaves += $section['children'];
        }

        return $leaves;
    }

    /** كل الصلاحيات الممكنة: finance.dues.view ... */
    public static function all(): array
    {
        $all = [];
        foreach (self::leaves() as $key => $leaf) {
            foreach ($leaf['actions'] as $action) {
                $all[] = "{$key}.{$action}";
            }
        }

        return $all;
    }

    /**
     * تنظيف قائمة صلاحيات جاية من الواجهة: بيشيل أي مفتاح مش معرّف، وأي إجراء
     * (إضافة/تعديل/حذف) على قسم بدون "عرض" بيضيفله "عرض" تلقائياً لأنه بدونه ما
     * بيقدر يفتح الصفحة أصلاً.
     */
    public static function sanitize(array $permissions): array
    {
        $valid  = array_flip(self::all());
        $result = [];

        foreach ($permissions as $permission) {
            if (! is_string($permission) || ! isset($valid[$permission])) {
                continue;
            }
            $result[$permission] = true;

            [$leaf] = self::split($permission);
            if (isset($valid["{$leaf}.view"])) {
                $result["{$leaf}.view"] = true;
            }
        }

        return array_values(array_intersect(self::all(), array_keys($result)));
    }

    /** finance.dues.view => ['finance.dues', 'view'] */
    public static function split(string $permission): array
    {
        $pos = strrpos($permission, '.');

        return [substr($permission, 0, $pos), substr($permission, $pos + 1)];
    }

    /**
     * وصف مقروء لقائمة صلاحيات (لسجل المحددات الهامة):
     * "الذمم المالية: عرض، تعديل — العطاءات/قائمة العطاءات: عرض"
     */
    public static function describe(?array $permissions): string
    {
        $permissions = array_flip($permissions ?? []);
        $parts       = [];

        foreach (self::leaves() as $key => $leaf) {
            $actions = array_filter($leaf['actions'], fn ($a) => isset($permissions["{$key}.{$a}"]));
            if ($actions) {
                $parts[] = $leaf['label'] . ': ' . implode('، ', array_map(fn ($a) => self::ACTIONS[$a], $actions));
            }
        }

        return $parts ? implode(' — ', $parts) : 'بدون صلاحيات';
    }

    /** شكل الكتالوج للواجهة (مصفوفة الصلاحيات) */
    public static function catalog(): array
    {
        return [
            'actions'  => collect(self::ACTIONS)->map(fn ($label, $key) => ['key' => $key, 'label' => $label])->values(),
            'sections' => collect(self::SECTIONS)->map(fn ($section, $key) => [
                'key'      => $key,
                'label'    => $section['label'],
                'children' => collect($section['children'])->map(fn ($leaf, $leafKey) => [
                    'key'     => $leafKey,
                    'label'   => $leaf['label'],
                    'actions' => $leaf['actions'],
                ])->values(),
            ])->values(),
        ];
    }
}

import type { VerticalNavItems } from '@layouts/types'
import type { User } from '@/stores/authStore'
import { canAccessRoute, isSupervisor } from '@/utils/permissions'

type NavUser = Pick<User, 'role' | 'permissions'> | null | undefined

// قراءة المستخدم من localStorage مباشرة — يعمل قبل تهيئة Pinia
function getStoredUser(): NavUser {
  try {
    const stored = localStorage.getItem('userData')
    if (!stored)
      return null

    return JSON.parse(stored)
  }
  catch {
    return null
  }
}

/** الصفحات اللي بتنفتح من داخل صفحة الإعدادات (pages/settings/index.vue) */
const SETTINGS_INNER_ROUTES = [
  'settings-terms',
  'settings-privacy-policy',
  'settings-app-settings',
  'settings-exchange-rates',
  'settings-pages',
  'settings-grade-fees',
  'settings-contractor-lookups',
  'settings-governorates',
  'tenders-categories',
  'settings-activity-log',
]

/** بيشيل الصفحات الممنوعة، والمجموعات اللي فضيت، والعناوين اللي ما تحتها شي */
function filterNav(items: VerticalNavItems, user: NavUser): VerticalNavItems {
  const visible: any[] = []

  for (const item of items as any[]) {
    if ('heading' in item) {
      visible.push(item)
      continue
    }
    if (item.children) {
      const children = item.children.filter((child: any) => canAccessRoute(user, child.to))
      if (children.length)
        visible.push({ ...item, children })
      continue
    }

    // صفحة الإعدادات للمشرف بتظهر بس إذا عنده صلاحية على وحدة من صفحاتها الداخلية
    if (item.to === 'settings' && isSupervisor(user)) {
      if (SETTINGS_INNER_ROUTES.some(name => canAccessRoute(user, name)))
        visible.push(item)
      continue
    }
    if (canAccessRoute(user, item.to))
      visible.push(item)
  }

  return visible.filter((item, i, arr) =>
    !('heading' in item) || (i + 1 < arr.length && !('heading' in arr[i + 1]))) as VerticalNavItems
}

/**
 * القائمة الجانبية حسب المستخدم: المحاسب على قائمته القديمة كما هي، والمشرف بيشوف
 * بس الأقسام اللي عنده عليها صلاحية عرض (utils/permissions.ts).
 */
export function buildPcuNav(user: NavUser): VerticalNavItems {
  const role = user?.role ?? ''
  const isAccountant = role === 'accountant'

  const menuItems: VerticalNavItems = []

  // 1. لوحة التحكم (مشتركة)
  menuItems.push({
    title: 'لوحة التحكم',
    icon: { icon: 'tabler-layout-dashboard' },
    to: 'dashboards',
  })

  // 2. إدارة الأعضاء (للأدمن فقط)
  if (!isAccountant) {
    menuItems.push({ heading: 'إدارة الأعضاء' })
    menuItems.push({
      title: 'المقاولون',
      icon: { icon: 'tabler-building-factory-2' },
      children: [
        { title: 'قائمة المقاولين', to: 'contractors' },
        { title: 'تسجيل مقاول جديد', to: 'contractors-create' },
        { title: 'طلبات الانتساب', to: 'contractors-memberships' },
      ],
    })
  }

  // 3. الشؤون المالية (مشتركة)
  menuItems.push({ heading: 'الشؤون المالية' })
  menuItems.push({
    title: 'المدفوعات',
    icon: { icon: 'tabler-credit-card' },
    children: [
      { title: 'سجل المدفوعات', to: 'payments-transactions' },
      { title: 'الذمم المالية', to: 'dues' },
      { title: 'أرصدة المقاولين', to: 'balances' },
      { title: 'الغرامات والمخالفات', to: 'contractors-penalties' },
      { title: 'الحسابات البنكية', to: 'settings-bank-accounts' },
    ],
  })

  // 4. العطاءات وسوق الآليات والوثائق (للأدمن فقط)
  if (!isAccountant) {
    menuItems.push({ heading: 'العطاءات' })
    menuItems.push({
      title: 'العطاءات',
      icon: { icon: 'tabler-files' },
      children: [
        { title: 'قائمة العطاءات', to: 'tenders' },
        { title: 'تصنيفات العطاءات', to: 'tenders-categories' },
      ],
    })

    menuItems.push({
      title: 'المكتبة القانونية',
      icon: { icon: 'tabler-library' },
      to: 'legal-library',
    })

    menuItems.push({ heading: 'سوق الآليات' })
    menuItems.push({
      title: 'سوق الآليات',
      icon: { icon: 'tabler-tractor' },
      children: [
        { title: 'جميع الآليات', to: 'marketplace' },
        { title: 'إضافة آلية', to: 'marketplace-create' },
        { title: 'أنواع المعدات', to: 'marketplace-types' },
        { title: 'باقات الاشتراك', to: 'marketplace-packages' },
      ],
    })

    menuItems.push({ heading: 'الوثائق' })
    menuItems.push({
      title: 'إدارة الوثائق',
      icon: { icon: 'tabler-folder' },
      to: 'documents',
    })
  }

  // 4.5 خدمات الأعضاء (للأدمن فقط)
  if (!isAccountant) {
    menuItems.push({ heading: 'خدمات الأعضاء' })
    menuItems.push({
      title: 'الدعم الفني والشكاوى',
      icon: { icon: 'tabler-headset' },
      to: 'support-tickets',
    })
    menuItems.push({
      title: 'الشهادات',
      icon: { icon: 'tabler-certificate' },
      children: [
        { title: 'طلبات الشهادات', to: 'certificate-requests' },
        { title: 'شهادة العضوية', to: 'membership-certificates' },
      ],
    })
    menuItems.push({
      title: 'الأخبار',
      icon: { icon: 'tabler-news' },
      to: 'news',
    })
    menuItems.push({
      title: 'الفعاليات',
      icon: { icon: 'tabler-calendar-event' },
      to: 'events',
    })
    menuItems.push({
      title: 'التعميمات',
      icon: { icon: 'tabler-speakerphone' },
      children: [
        { title: 'جميع التعميمات', to: 'announcements' },
        { title: 'تصنيفات التعميمات', to: 'announcements-categories' },
      ],
    })
  }

  // 5. التقارير والإشعارات (مشتركة)
  menuItems.push({ heading: 'التقارير' })
  menuItems.push({
    title: 'التقارير',
    icon: { icon: 'tabler-chart-bar' },
    to: 'analytics',
  })
  menuItems.push({
    title: 'الإشعارات',
    icon: { icon: 'tabler-bell' },
    to: 'notifications',
  })

  // 6. الإعدادات (للأدمن فقط)
  if (!isAccountant) {
    menuItems.push({ heading: 'الإعدادات' })
    menuItems.push({
      title: 'الإعدادات',
      icon: { icon: 'tabler-settings' },
      to: 'settings',
    })

    // أدمن فقط (canAccessRoute بيخفيها عن غيره)
    menuItems.push({
      title: 'المشرفون والصلاحيات',
      icon: { icon: 'tabler-shield-check' },
      to: 'supervisors',
    })
  }

  return filterNav(menuItems, user)
}

export default buildPcuNav(getStoredUser())

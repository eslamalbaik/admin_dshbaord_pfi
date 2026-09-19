import type { VerticalNavItems } from '@layouts/types'

// قراءة الـ role من localStorage مباشرة — يعمل قبل تهيئة Pinia
function getUserRole(): string {
  try {
    const stored = localStorage.getItem('userData')
    if (!stored) return ''
    return JSON.parse(stored)?.role ?? ''
  }
  catch {
    return ''
  }
}

const role = getUserRole()
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
      // "طلبات الانتساب" (contractors-memberships) مخفية عمداً من القائمة — الميزة معطّلة
      // منتجياً (TASK-02) بينما تبقى الصفحة والراوت شغّالين لمن يملك الرابط المباشر.
      { title: 'طلبات تعديل اسم الشركة', to: 'contractors-name-change-requests' },
    ],
  })
}

// 3. الشؤون المالية (مشتركة)
menuItems.push({ heading: 'الشؤون المالية' })
menuItems.push({
  title: 'المدفوعات',
  icon: { icon: 'tabler-credit-card' },
  children: [
    // "سجل المدفوعات" (payments-transactions) مخفي عمداً من القائمة — الميزة معطّلة
    // منتجياً (TASK-04) بينما تبقى الصفحة والراوت شغّالين لمن يملك الرابط المباشر.
    { title: 'الذمم المالية', to: 'dues' },
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
    to: 'tenders',
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
}

export default menuItems

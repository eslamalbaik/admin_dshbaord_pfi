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
      { title: 'طلبات الانتساب', to: 'contractors-memberships' },
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
	    { title: 'سجل المدفوعات', to: 'payments-transactions' },
	  ],
	})
	menuItems.push({
	  title: 'الذمم المالية',
	  icon: { icon: 'tabler-receipt' },
	  to: 'dues',
	})
	menuItems.push({
	  title: 'الغرامات والمخالفات',
	  icon: { icon: 'tabler-alert-triangle' },
	  to: 'contractors-penalties',
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
    title: 'الشهادات',
    icon: { icon: 'tabler-certificate' },
    children: [
      { title: 'طلبات الشهادات', to: 'certificate-requests' },
      { title: 'شهادة العضوية', to: 'membership-certificates' },
    ],
  })
  menuItems.push({
    title: 'الأخبار والمناسبات',
    icon: { icon: 'tabler-news' },
    to: 'news',
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
  menuItems.push({
    title: 'الشروط والأحكام',
    icon: { icon: 'tabler-file-text' },
    to: 'settings-terms',
  })
  menuItems.push({
    title: 'المكتبة القانونية',
    icon: { icon: 'tabler-library' },
    to: 'settings-legal-library',
  })
  menuItems.push({
    title: 'إعدادات التطبيق',
    icon: { icon: 'tabler-adjustments' },
    to: 'settings-app-settings',
  })
  menuItems.push({
    title: 'الحسابات البنكية',
    icon: { icon: 'tabler-building-bank' },
    to: 'settings-bank-accounts',
  })
  menuItems.push({
    title: 'الصفحات الديناميكية',
    icon: { icon: 'tabler-file-plus' },
    to: 'settings-pages',
  })
}

export default menuItems

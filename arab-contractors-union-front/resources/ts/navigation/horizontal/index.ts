import type { HorizontalNavItems } from '@layouts/types'

/**
 * القائمة الأفقية — نسخة مختصرة من قائمة PCU الجانبية (navigation/vertical/pcu.ts).
 * تُستخدم فقط حين يكون نمط التخطيط أفقياً في themeConfig.
 * أي مسار هنا يجب أن يقابل صفحة فعلية تحت resources/ts/pages.
 */
export default [
  {
    title: 'لوحة التحكم',
    icon: { icon: 'tabler-layout-dashboard' },
    to: 'dashboards',
  },
  {
    title: 'المقاولون',
    icon: { icon: 'tabler-users' },
    children: [
      { title: 'قائمة المقاولين', to: 'contractors' },
      { title: 'تسجيل مقاول جديد', to: 'contractors-create' },
      { title: 'طلبات الانتساب', to: 'contractors-memberships' },
      { title: 'طلبات تعديل اسم الشركة', to: 'contractors-name-change-requests' },
      { title: 'طلبات تعديل البيانات', to: 'contractors-profile-update-requests' },
    ],
  },
  {
    title: 'الشؤون المالية',
    icon: { icon: 'tabler-credit-card' },
    children: [
      { title: 'سجل المدفوعات', to: 'payments-transactions' },
      { title: 'الذمم المالية', to: 'dues' },
      { title: 'الغرامات والمخالفات', to: 'contractors-penalties' },
    ],
  },
  {
    title: 'العطاءات وسوق الآليات',
    icon: { icon: 'tabler-briefcase' },
    children: [
      { title: 'العطاءات', to: 'tenders' },
      { title: 'جميع الآليات', to: 'marketplace' },
      { title: 'أنواع المعدات', to: 'marketplace-types' },
      { title: 'باقات الاشتراك', to: 'marketplace-packages' },
    ],
  },
  {
    title: 'الشهادات',
    icon: { icon: 'tabler-certificate' },
    children: [
      { title: 'طلبات الشهادات', to: 'certificate-requests' },
      { title: 'شهادة العضوية', to: 'membership-certificates' },
    ],
  },
  {
    title: 'المزيد',
    icon: { icon: 'tabler-dots' },
    children: [
      { title: 'إدارة الوثائق', to: 'documents', icon: { icon: 'tabler-folder' } },
      { title: 'الدعم الفني والشكاوى', to: 'support-tickets', icon: { icon: 'tabler-headset' } },
      { title: 'الأخبار', to: 'news', icon: { icon: 'tabler-news' } },
      { title: 'الفعاليات', to: 'events', icon: { icon: 'tabler-calendar-event' } },
      { title: 'التقارير', to: 'analytics', icon: { icon: 'tabler-chart-bar' } },
      { title: 'الإشعارات', to: 'notifications', icon: { icon: 'tabler-bell' } },
      { title: 'الإعدادات', to: 'settings', icon: { icon: 'tabler-settings' } },
    ],
  },
] as HorizontalNavItems

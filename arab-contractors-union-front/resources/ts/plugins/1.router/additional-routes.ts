import type { RouteRecordRaw } from 'vue-router/auto'

// 👉 Redirects
export const redirects: RouteRecordRaw[] = [
  {
    // الجذر (الدومين الرئيسي) قيد الإنشاء؛ صفحة الـ landing تُفتح مباشرةً عبر /landing.
    // لوحة التحكم يدخلها الأدمن من /admin/login أو /dashboards مباشرة.
    path: '/',
    name: 'home',
    redirect: '/under-construction',
  },
]

// 👉 Additional routes (non file-based)
export const routes: RouteRecordRaw[] = []

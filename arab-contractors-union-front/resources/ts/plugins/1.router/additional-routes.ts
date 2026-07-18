import type { RouteRecordRaw } from 'vue-router/auto'

// 👉 Redirects
export const redirects: RouteRecordRaw[] = [
  {
    // الصفحة الرئيسية للزوار هي الـ landing العامة؛
    // لوحة التحكم يدخلها الأدمن من /admin/login أو /dashboards مباشرة.
    path: '/',
    name: 'home',
    redirect: '/landing',
  },
]

// 👉 Additional routes (non file-based)
export const routes: RouteRecordRaw[] = []

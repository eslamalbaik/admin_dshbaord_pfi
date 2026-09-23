import type { RouteNamedMap, _RouterTyped } from 'unplugin-vue-router'
import { watch } from 'vue'
import NProgress from 'nprogress'
import 'nprogress/nprogress.css'
import { useAuthStore } from '@/stores/authStore'
import { themeConfig } from '@themeConfig'

const waitForAuthInit = (authStore: any) => {
  if (!authStore.isInitializing)
    return Promise.resolve()

  return new Promise<void>(resolve => {
    const unwatch = watch(
      () => authStore.isInitializing,
      val => {
        if (!val) {
          unwatch()
          resolve()
        }
      },
      { immediate: true }
    )
  })
}

const LANDING_URL = (import.meta.env.VITE_LANDING_URL || '').replace(/\/$/, '')

function redirectStudentToLanding(targetPath = '/student/dashboard') {
  // بدون VITE_LANDING_URL (وضع اتحاد المقاولين): الـ landing داخلية على نفس الدومين.
  if (!LANDING_URL) {
    window.location.href = '/landing'
    return
  }

  const url = new URL(`${LANDING_URL}/login`)
  url.searchParams.set('to', targetPath)
  if (typeof localStorage !== 'undefined') {
    const token = localStorage.getItem('accessToken')
    if (token) {
      url.searchParams.set('token', token)
    }
  }
  window.location.href = url.toString()
}

// Configure NProgress
NProgress.configure({ showSpinner: false, speed: 400 })

const SITE_TITLE = themeConfig.app.title

const ROUTE_TAB_TITLES: Record<string, string> = {
  'landing': 'اتحاد المقاولين الفلسطينيين',
  'login': 'تسجيل الدخول',
  'register': 'إنشاء حساب',
  'admin-login': 'دخول المشرف',
  'not-authorized': 'غير مصرح',
  'dashboards': 'لوحة التحكم',
  'contractors': 'المقاولون',
  'contractors-create': 'إضافة مقاول',
  'contractors-memberships': 'العضويات',
  'contractors-name-change-requests': 'طلبات تعديل اسم الشركة',
  'contractors-profile-update-requests': 'طلبات تعديل البيانات',
  'contractors-penalties': 'المخالفات',
  'contractors-qr': 'رمز QR',
  'tenders': 'العطاءات',
  'documents': 'الوثائق',
}

function resolveDocumentTitle(routeName: string | symbol | null | undefined): string {
  if (routeName == null || routeName === '')
    return SITE_TITLE

  const key = String(routeName)
  const page = ROUTE_TAB_TITLES[key]
    ?? key
      .split('-')
      .map(part => part.charAt(0).toUpperCase() + part.slice(1))
      .join(' ')

  return `${page} | ${SITE_TITLE}`
}

export const setupGuards = (router: _RouterTyped<RouteNamedMap & { [key: string]: any }>) => {
  router.beforeEach(async to => {
    // Start progress bar
    NProgress.start()

    /*
     * Public routes: accessible by everyone without any restrictions.
     */
    if (to.meta.public)
      return

    const authStore = useAuthStore()

    if (to.meta.unauthenticatedOnly && to.query.logout === '1') {
      await authStore.revokeSession()
      return {
        path: to.path,
        query: {},
        replace: true,
      }
    }

    // For unauthenticated-only pages (login), skip waiting for auth init
    // when there is no stored token — the page loads instantly.
    if (to.meta.unauthenticatedOnly) {
      const hasToken = typeof localStorage !== 'undefined' && !!localStorage.getItem('accessToken')
      if (!hasToken)
        return undefined

      // Token exists — wait for auth so we can redirect the logged-in user.
      await waitForAuthInit(authStore)
      if (authStore.isLoggedIn) {
        if (['admin', 'accountant'].includes(authStore.userRole ?? ''))
          return '/dashboards'
        redirectStudentToLanding()
        return false
      }
      return undefined
    }

    // Trigger auth initialization if it hasn't started yet, then wait for it
    authStore.fetchUser()
    await waitForAuthInit(authStore)

    const isLoggedIn = authStore.isLoggedIn
    const userRole = authStore.userRole

    // Redirect logged-in non-admins/non-accountants (students) to the landing-page student dashboard
    if (isLoggedIn && !['admin', 'accountant'].includes(userRole ?? '')) {
      redirectStudentToLanding()
      return false
    }

    if (!isLoggedIn && to.matched.length) {
      return {
        name: 'admin-login',
        query: {
          ...to.query,
          to: to.fullPath !== '/' ? to.path : undefined,
        },
      }
    }

    if (to.meta.requiresAdmin && !['admin', 'accountant'].includes(userRole ?? ''))
      return { name: 'not-authorized' }

    if (to.meta.adminOnly && userRole !== 'admin')
      return { name: 'not-authorized' }
  })

  router.afterEach(to => {
    document.title = resolveDocumentTitle(to.name)
    NProgress.done()
  })
}

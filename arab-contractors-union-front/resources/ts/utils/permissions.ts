/**
 * صلاحيات لوحة التحكم للمشرفين (role = supervisor).
 *
 * المصدر الحقيقي للصلاحيات هو الباك إند (App\Support\DashboardPermissions + middleware perm:...)؛
 * هون بس عشان نخفي عناصر القائمة والأزرار ونحوّل عن الصفحات الممنوعة.
 *
 * - الأدمن: كل شي.
 * - المحاسب: ما بيتأثر — بيضل على منطق الأدوار القديم (requiresAdmin/adminOnly والقائمة تبعه).
 * - المشرف: حسب قائمة permissions مثل "finance.dues.view".
 */

export type PermissionAction = 'view' | 'create' | 'update' | 'delete'

interface PermissionUser {
  role?: string | null
  permissions?: string[] | null
}

/** الأدوار اللي بتدخل لوحة التحكم */
export const DASHBOARD_ROLES = ['admin', 'accountant', 'supervisor']

/** اسم الصفحة (route name) → القسم الداخلي اللي بيلزمه "عرض" */
export const ROUTE_PERMISSIONS: Record<string, string> = {
  'dashboards': 'dashboard.home',
  'contractors': 'contractors.list',
  'contractors-edit-id': 'contractors.list',
  'contractors-create': 'contractors.list',
  'contractors-memberships': 'contractors.memberships',
  'contractors-penalties': 'contractors.penalties',
  'payments-transactions': 'finance.payments',
  'dues': 'finance.dues',
  'balances': 'finance.balances',
  'settings-bank-accounts': 'finance.bank_accounts',
  'settings-exchange-rates': 'finance.exchange_rates',
  'settings-grade-fees': 'finance.grade_fees',
  'tenders': 'tenders.list',
  'tenders-categories': 'tenders.categories',
  'legal-library': 'legal.library',
  'marketplace': 'marketplace.equipment',
  'marketplace-create': 'marketplace.equipment',
  'marketplace-types': 'marketplace.types',
  'marketplace-packages': 'marketplace.packages',
  'documents': 'documents.manage',
  'support-tickets': 'services.support_tickets',
  'certificate-requests': 'services.certificate_requests',
  'membership-certificates': 'services.membership_certificates',
  'news': 'content.news',
  'events': 'content.events',
  'announcements': 'content.announcements',
  'announcements-categories': 'content.announcement_categories',
  'analytics': 'reports.analytics',
  'settings-terms': 'settings.terms',
  'settings-privacy-policy': 'settings.terms',
  'settings-app-settings': 'settings.app',
  'settings-pages': 'settings.pages',
  'settings-contractor-lookups': 'settings.lookups',
  'settings-governorates': 'settings.governorates',
  'settings-activity-log': 'settings.activity_log',
}

/** صفحات بتحتاج إجراء غير العرض */
const ROUTE_ACTIONS: Record<string, PermissionAction> = {
  'contractors-create': 'create',
  'marketplace-create': 'create',
}

/** صفحات شخصية مفتوحة لأي مشرف (ملفي، الإشعارات) */
const PERSONAL_ROUTES = ['settings', 'notifications', 'not-authorized']

/** صفحات للأدمن فقط بغض النظر عن أي صلاحية */
export const ADMIN_ONLY_ROUTES = ['supervisors']

export const isSupervisor = (user?: PermissionUser | null) => user?.role === 'supervisor'

/**
 * هل المستخدم بيقدر ينفّذ الإجراء على القسم؟
 * لغير المشرفين بترجع true دايماً (منطق الأدوار القديم بيضل هو الحاكم).
 */
export function hasPermission(user: PermissionUser | null | undefined, section: string, action: PermissionAction = 'view'): boolean {
  if (!user)
    return false
  if (!isSupervisor(user))
    return true

  return (user.permissions ?? []).includes(`${section}.${action}`)
}

/** هل في أي صلاحية على أي من الأقسام (مثلاً لإظهار عنوان مجموعة بالقائمة) */
export function hasAnyPermission(user: PermissionUser | null | undefined, sections: string[]): boolean {
  return sections.some(section => hasPermission(user, section, 'view'))
}

/** هل المشرف بيقدر يفتح الصفحة؟ (لغير المشرفين: true، الحماية القديمة بتضل شغالة) */
export function canAccessRoute(user: PermissionUser | null | undefined, routeName: string | null | undefined): boolean {
  if (!routeName)
    return true
  const name = String(routeName)

  if (ADMIN_ONLY_ROUTES.includes(name))
    return user?.role === 'admin'

  if (!isSupervisor(user))
    return true

  if (PERSONAL_ROUTES.includes(name))
    return true

  const section = ROUTE_PERMISSIONS[name]
  if (!section)
    return false

  return hasPermission(user, section, ROUTE_ACTIONS[name] ?? 'view')
}

/** أول صفحة مسموحة للمشرف (لما ما يكون عنده عرض لوحة التحكم الرئيسية) */
export function firstAllowedRoute(user: PermissionUser | null | undefined): string {
  if (canAccessRoute(user, 'dashboards'))
    return 'dashboards'

  return Object.keys(ROUTE_PERMISSIONS).find(name => canAccessRoute(user, name)) ?? 'settings'
}

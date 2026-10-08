import type { App } from 'vue'
import { useAuthStore } from '@/stores/authStore'
import type { PermissionAction } from '@/utils/permissions'
import { hasPermission } from '@/utils/permissions'

/**
 * $can('finance.dues', 'create') بالقوالب — لإخفاء أزرار الإضافة/التعديل/الحذف عن المشرف
 * اللي ما عنده الصلاحية. للأدمن والمحاسب دايماً true (انظر utils/permissions.ts).
 */
export default function (app: App) {
  app.config.globalProperties.$can = (section: string, action: PermissionAction = 'view') =>
    hasPermission(useAuthStore().user, section, action)
}

declare module 'vue' {
  interface ComponentCustomProperties {
    $can: (section: string, action?: PermissionAction) => boolean
  }
}

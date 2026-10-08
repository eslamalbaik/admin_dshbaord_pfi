import { useAuthStore } from '@/stores/authStore'
import type { PermissionAction } from '@/utils/permissions'
import { hasPermission } from '@/utils/permissions'

/** نفس $can بس للاستخدام جوّا <script setup> */
export function useCan() {
  const authStore = useAuthStore()

  return (section: string, action: PermissionAction = 'view') => hasPermission(authStore.user, section, action)
}

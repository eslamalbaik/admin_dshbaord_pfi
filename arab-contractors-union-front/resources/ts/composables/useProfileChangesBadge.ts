import api from '@/plugins/axios'
import { canAccessRoute } from '@/utils/permissions'

/**
 * عدّاد «طلبات تعديل الملفات» بالقائمة الجانبية — عدد المقاولين اللي عندهم تعديلات
 * بانتظار الاعتماد. بيتحدّث كل 3 دقائق، وفوراً لما يوصل إشعار طلب جديد أو بعد قرار.
 */
export const profileChangesPending = ref(0)

let timer: ReturnType<typeof setInterval> | null = null

export async function refreshProfileChangesBadge() {
  try {
    const { data } = await api.get('/api/v1/dashboard/profile-changes/summary')

    profileChangesPending.value = Number(data?.items?.review_contractors ?? 0)
  }
  catch {
    // بدون صلاحية أو انقطاع — العدّاد بيضل على آخر قيمة
  }
}

export function startProfileChangesBadge(user: { role?: string | null; permissions?: string[] | null } | null | undefined) {
  if (timer || !user || user.role === 'accountant' || !canAccessRoute(user, 'contractors-profile-changes'))
    return

  refreshProfileChangesBadge()
  timer = setInterval(refreshProfileChangesBadge, 3 * 60 * 1000)
  window.addEventListener('profile-changes:refresh', refreshProfileChangesBadge)
}

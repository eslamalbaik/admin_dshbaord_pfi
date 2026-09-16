import { reactive, watch } from 'vue'
import { useQueryClient } from '@tanstack/vue-query'
import { useAuthStore } from '@/stores/authStore'
import { NOTIFICATIONS_KEY } from '@/composables/useNotifications'
import { connectEcho, disconnectEcho } from '@/plugins/echo'

export interface AdminToast {
  id: number
  text: string
}

// Shared toast queue — a single VSnackbar host in App.vue renders whatever lands here.
export const adminToasts = reactive<AdminToast[]>([])

let toastSeq = 0

function pushToast(text: string) {
  const id = ++toastSeq
  adminToasts.push({ id, text })
  setTimeout(() => {
    const index = adminToasts.findIndex(t => t.id === id)
    if (index !== -1)
      adminToasts.splice(index, 1)
  }, 6000)
}

// Short two-tone beep via the Web Audio API — no binary asset to ship/license.
function playBeep() {
  try {
    const AudioContextCtor = window.AudioContext || (window as any).webkitAudioContext
    if (!AudioContextCtor)
      return

    const ctx = new AudioContextCtor()
    const now = ctx.currentTime

    ;[880, 1175].forEach((freq, i) => {
      const osc = ctx.createOscillator()
      const gain = ctx.createGain()
      osc.type = 'sine'
      osc.frequency.value = freq

      const start = now + i * 0.14
      gain.gain.setValueAtTime(0, start)
      gain.gain.linearRampToValueAtTime(0.25, start + 0.02)
      gain.gain.exponentialRampToValueAtTime(0.001, start + 0.18)

      osc.connect(gain)
      gain.connect(ctx.destination)
      osc.start(start)
      osc.stop(start + 0.2)
    })

    setTimeout(() => ctx.close(), 500)
  }
  catch {
    // Autoplay restrictions or no Web Audio support — the toast + badge still update.
  }
}

const REALTIME_ROLES = ['admin', 'accountant']

// Module-level (not per-call) so the watcher and its Echo subscription are ever
// registered once per browser tab, even if the composable gets invoked more than
// once (e.g. HMR re-running App.vue's setup) — prevents stacking duplicate
// WebSocket listeners that would each fire independently for the same broadcast.
let watcherRegistered = false
let subscribedUserId: number | null = null

/**
 * Subscribes the current admin/accountant to their private Reverb channel and keeps
 * the bell badge, a toast, and a beep in sync with newly broadcast notifications.
 * Call once from App.vue — it reacts to login/logout on its own.
 */
export function useRealtimeAdminNotifications() {
  if (watcherRegistered)
    return
  watcherRegistered = true

  const queryClient = useQueryClient()
  const authStore = useAuthStore()

  watch(
    () => [authStore.isLoggedIn, authStore.user?.id, authStore.userRole, authStore.token] as const,
    ([isLoggedIn, userId, role, token]) => {
      const eligible = isLoggedIn && !!userId && !!token && REALTIME_ROLES.includes(role || '')

      if (!eligible) {
        if (subscribedUserId !== null) {
          disconnectEcho()
          subscribedUserId = null
        }
        return
      }

      if (subscribedUserId === userId)
        return

      disconnectEcho()
      const echo = connectEcho(token as string)
      echo.private(`App.Models.User.${userId}`).notification((notification: any) => {
        queryClient.invalidateQueries({ queryKey: NOTIFICATIONS_KEY })
        playBeep()
        pushToast(notification?.message || 'إشعار جديد')
      })
      subscribedUserId = userId as number
    },
    { immediate: true },
  )
}

import { reactive, ref, watch } from 'vue'
import { useQueryClient } from '@tanstack/vue-query'
import { useAuthStore } from '@/stores/authStore'
import { NOTIFICATIONS_KEY } from '@/composables/useNotifications'
import { connectEcho, disconnectEcho } from '@/plugins/echo'
import { normalizeNotificationType, notificationMessage, notificationMeta } from '@/utils/notificationMeta'

export interface AdminToast {
  id: number
  notificationId: string | null
  title: string
  text: string
  icon: string
  color: string
  financial: boolean
  data: Record<string, any>
  duration: number
}

// Shared toast queue — AdminNotificationToasts.vue (mounted in App.vue) renders whatever lands here.
export const adminToasts = reactive<AdminToast[]>([])

// يزيد مع كل إشعار جديد — جرس الشريط العلوي يراقبه ليهتز.
export const notificationPulse = ref(0)

const MAX_TOASTS = 4
let toastSeq = 0

export function dismissToast(id: number) {
  const index = adminToasts.findIndex(t => t.id === id)
  if (index !== -1)
    adminToasts.splice(index, 1)
}

function pushToast(notification: Record<string, any>) {
  const meta = notificationMeta(notification)

  adminToasts.unshift({
    id: ++toastSeq,
    notificationId: notification?.id ? String(notification.id) : null,
    title: meta.title,
    text: notificationMessage(notification, 'وصلك إشعار جديد'),
    icon: meta.icon,
    color: meta.color,
    financial: meta.financial,
    data: { ...notification, type: normalizeNotificationType(notification?.type) },

    // المالية تبقى أطول حتى لا تفوت المحاسب
    duration: meta.financial ? 12000 : 8000,
  })

  if (adminToasts.length > MAX_TOASTS)
    adminToasts.splice(MAX_TOASTS)
}

// ─── صوت التنبيه ────────────────────────────────────────────────────────────
// يُولَّد بالـ Web Audio API (بلا ملف صوتي). المتصفحات تمنع تشغيل الصوت قبل
// أول تفاعل للمستخدم مع الصفحة، فنُنشئ AudioContext واحداً ونُفعّله عند أول
// نقرة/ضغطة زر؛ قبل ذلك يفشل الصوت بصمت ويظهر التنبيه المرئي فقط.

const MUTE_KEY = 'adminNotificationSoundMuted'

function readMuted(): boolean {
  try {
    return localStorage.getItem(MUTE_KEY) === '1'
  }
  catch {
    return false
  }
}

export const notificationSoundMuted = ref(readMuted())

export function toggleNotificationSound() {
  notificationSoundMuted.value = !notificationSoundMuted.value
  try {
    localStorage.setItem(MUTE_KEY, notificationSoundMuted.value ? '1' : '0')
  }
  catch {}

  // معاينة الصوت عند إعادة تفعيله — النقرة نفسها تفتح قفل الصوت في المتصفح
  if (!notificationSoundMuted.value)
    playChime(false)
}

let audioCtx: AudioContext | null = null

function getAudioContext(): AudioContext | null {
  if (audioCtx)
    return audioCtx
  const AudioContextCtor = window.AudioContext || (window as any).webkitAudioContext
  if (!AudioContextCtor)
    return null
  audioCtx = new AudioContextCtor()

  return audioCtx
}

function unlockAudio() {
  try {
    const ctx = getAudioContext()
    if (ctx && ctx.state === 'suspended')
      ctx.resume().catch(() => {})
  }
  catch {}
}

if (typeof window !== 'undefined') {
  ;['pointerdown', 'keydown', 'touchstart'].forEach(evt =>
    window.addEventListener(evt, unlockAudio, { passive: true }),
  )
}

// نغمة واضحة: ثلاث نوتات صاعدة، والمالية نغمة مختلفة (أربع نوتات تشبه «رنّة النقود»)
// حتى يميّزها المحاسب بالأذن قبل أن ينظر للشاشة.
function playChime(financial: boolean) {
  try {
    const ctx = getAudioContext()
    if (!ctx)
      return
    if (ctx.state === 'suspended')
      ctx.resume().catch(() => {})

    const now = ctx.currentTime + 0.02

    // ضاغط + كسب رئيسي يرفعان الصوت بدون تشويه
    const master = ctx.createGain()

    master.gain.value = 0.9

    const compressor = ctx.createDynamicsCompressor()

    compressor.threshold.value = -10
    compressor.ratio.value = 4
    master.connect(compressor)
    compressor.connect(ctx.destination)

    const notes = financial
      ? [1046.5, 1318.5, 1568, 2093] // C6 E6 G6 C7
      : [784, 1046.5, 1318.5] // G5 C6 E6

    const step = financial ? 0.12 : 0.15
    const tail = 0.55

    notes.forEach((freq, i) => {
      const start = now + i * step

      // نغمة أساسية + نغمة أعلى خفيفة لصوت «جرس» أوضح من الصفارة البسيطة
      ;[[freq, 'triangle', 0.7], [freq * 2, 'sine', 0.25]].forEach(([f, type, level]) => {
        const osc = ctx.createOscillator()
        const gain = ctx.createGain()

        osc.type = type as OscillatorType
        osc.frequency.value = f as number
        gain.gain.setValueAtTime(0.0001, start)
        gain.gain.exponentialRampToValueAtTime(level as number, start + 0.015)
        gain.gain.exponentialRampToValueAtTime(0.0001, start + tail)
        osc.connect(gain)
        gain.connect(master)
        osc.start(start)
        osc.stop(start + tail + 0.05)
      })
    })
  }
  catch {
    // لا دعم للصوت أو ما زال محجوباً — التنبيه المرئي والعدّاد يكفيان
  }
}

/** للتجربة اليدوية من الكونسول وللقطات الشاشة: يحاكي وصول إشعار فوري. */
export function __simulateIncoming(notification: Record<string, any>) {
  handleIncoming(notification)
}

function handleIncoming(notification: Record<string, any>) {
  const meta = notificationMeta(notification)

  notificationPulse.value++
  if (!notificationSoundMuted.value)
    playChime(meta.financial)
  pushToast(notification)
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
 * the bell badge, a toast, and a chime in sync with newly broadcast notifications.
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
        handleIncoming(notification ?? {})
      })
      subscribedUserId = userId as number
    },
    { immediate: true },
  )
}

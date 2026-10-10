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
    playChime()
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

// صوت «آلة كاشير» لكل الإشعارات: طقّة قصيرة (ضجيج مُرشَّح) ثم رنّة جرس معدني.
// اختاره المستخدم من بين عدة نغمات جُرّبت على Staging.
let lastChimeAt = 0

function playChime() {
  // دفعة إشعارات متتالية (مثلاً عدة تحويلات معاً) تُصدر رنّة واحدة لا رنّات متراكبة
  if (Date.now() - lastChimeAt < 1500)
    return
  lastChimeAt = Date.now()

  try {
    const ctx = getAudioContext()
    if (!ctx)
      return
    if (ctx.state === 'suspended')
      ctx.resume().catch(() => {})

    const now = ctx.currentTime + 0.02

    // ضاغط + كسب رئيسي يرفعان الصوت بدون تشويه
    const master = ctx.createGain()

    master.gain.value = 0.7

    const compressor = ctx.createDynamicsCompressor()

    compressor.threshold.value = -10
    compressor.ratio.value = 4
    master.connect(compressor)
    compressor.connect(ctx.destination)

    // الطقّة: 60ms ضجيج أبيض يخفت تدريجياً، بعد مرشّح high-pass
    const clickBuffer = ctx.createBuffer(1, Math.floor(ctx.sampleRate * 0.06), ctx.sampleRate)
    const samples = clickBuffer.getChannelData(0)
    for (let i = 0; i < samples.length; i++)
      samples[i] = (Math.random() * 2 - 1) * (1 - i / samples.length)

    const click = ctx.createBufferSource()
    const highpass = ctx.createBiquadFilter()
    const clickGain = ctx.createGain()

    highpass.type = 'highpass'
    highpass.frequency.value = 2500
    clickGain.gain.value = 0.6
    click.buffer = clickBuffer
    click.connect(highpass)
    highpass.connect(clickGain)
    clickGain.connect(master)
    click.start(now)

    // الرنّة: نغمات جزئية غير متناغمة (نسب جرس معدني) فوق G6
    const bellStart = now + 0.1

    ;[[1, 0.6, 1.3], [2.76, 0.25, 0.9], [5.4, 0.12, 0.5]].forEach(([ratio, level, decay]) => {
      const osc = ctx.createOscillator()
      const gain = ctx.createGain()

      osc.type = 'sine'
      osc.frequency.value = 1568 * ratio
      gain.gain.setValueAtTime(0.0001, bellStart)
      gain.gain.exponentialRampToValueAtTime(level, bellStart + 0.012)
      gain.gain.exponentialRampToValueAtTime(0.0001, bellStart + decay)
      osc.connect(gain)
      gain.connect(master)
      osc.start(bellStart)
      osc.stop(bellStart + decay + 0.05)
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
  notificationPulse.value++
  if (!notificationSoundMuted.value)
    playChime()
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

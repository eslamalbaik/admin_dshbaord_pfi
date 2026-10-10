// فحص خفيف لوجود إصدار أحدث من الواجهة على السيرفر.
// كل بناء يكتب dist/version.json بمعرّف فريد ويخبز نفس المعرّف في الكود؛ لو اختلف
// الملف على السيرفر عن المعرّف المحمّل بالصفحة فهذا يعني أن نشراً جديداً تمّ،
// فنُظهر شريط «يتوفر تحديث جديد». طلب واحد صغير كل بضع دقائق وفقط والتبويب ظاهر.

const CHECK_EVERY_MS = 3 * 60 * 1000
const MIN_GAP_MS = 60 * 1000 // لا نفحص أكثر من مرة بالدقيقة حتى مع تبديل التبويبات
const SNOOZE_MS = 15 * 60 * 1000 // «لاحقاً» أو الضغط خارج النافذة

const updateAvailable = ref(false)
const snoozedUntil = ref(0)
const now = ref(Date.now())
let started = false
let lastCheck = 0

async function check() {
  if (updateAvailable.value || document.visibilityState !== 'visible')
    return
  if (Date.now() - lastCheck < MIN_GAP_MS)
    return
  lastCheck = Date.now()

  try {
    const res = await fetch(`/version.json?t=${Date.now()}`, { cache: 'no-store' })
    if (!res.ok)
      return
    const { build } = await res.json()
    if (build && build !== __APP_BUILD_ID__)
      updateAvailable.value = true
  }
  catch {
    // انقطاع أو ملف غير موجود — نحاول بالدورة القادمة بصمت
  }
}

export function useAppUpdateCheck() {
  if (!started && !import.meta.env.DEV) {
    started = true
    setInterval(() => {
      now.value = Date.now()
      check()
    }, CHECK_EVERY_MS)
    document.addEventListener('visibilitychange', () => {
      now.value = Date.now()
      check()
    })
  }

  const showUpdateBar = computed(() => updateAvailable.value && now.value >= snoozedUntil.value)

  function reloadNow() {
    window.location.reload()
  }

  function snooze() {
    snoozedUntil.value = Date.now() + SNOOZE_MS
    now.value = Date.now()
  }

  return { showUpdateBar, reloadNow, snooze }
}

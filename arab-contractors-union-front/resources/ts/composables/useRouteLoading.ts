import { ref } from 'vue'

/**
 * حالة التحميل أثناء التنقل بين الصفحات — بيشغّلها الـrouter guard وبيعرضها AppLoadingIndicator
 * (شعار الاتحاد المتحرّك). حالة واحدة على مستوى الوحدة، مش لكل مكوّن.
 *
 * التأخير قبل الإظهار يمنع وميض الشعار بالتنقلات السريعة (الصفحة محمّلة مسبقاً)، والحد الأدنى
 * للظهور بيخلّي الشعار يكمّل تجميع قطعه (≈0.9 ثانية من دورة الـ3.2 ثانية) ويثبت حوالي ثانية قبل ما يختفي.
 */
const SHOW_DELAY_MS = 150
const MIN_VISIBLE_MS = 2000

const isRouteLoading = ref(false)

let showTimer: ReturnType<typeof setTimeout> | undefined
let hideTimer: ReturnType<typeof setTimeout> | undefined
let shownAt = 0

function start(delay = SHOW_DELAY_MS) {
  clearTimeout(hideTimer)
  if (isRouteLoading.value || showTimer)
    return

  // delay = 0: يظهر فوراً (الموقع العام، حتى مع التنقل السريع المحمّل مسبقاً)
  if (delay === 0) {
    shownAt = Date.now()
    isRouteLoading.value = true

    return
  }

  showTimer = setTimeout(() => {
    showTimer = undefined
    shownAt = Date.now()
    isRouteLoading.value = true
  }, delay)
}

function done(minVisible = MIN_VISIBLE_MS) {
  clearTimeout(showTimer)
  showTimer = undefined
  if (!isRouteLoading.value)
    return

  const remaining = minVisible - (Date.now() - shownAt)

  hideTimer = setTimeout(() => {
    isRouteLoading.value = false
  }, Math.max(0, remaining))
}

export function useRouteLoading() {
  return { isRouteLoading, start, done }
}

import { ref } from 'vue'

/**
 * حالة التحميل أثناء التنقل بين الصفحات — بيشغّلها الـrouter guard وبيعرضها AppLoadingIndicator
 * (شعار الاتحاد المتحرّك). حالة واحدة على مستوى الوحدة، مش لكل مكوّن.
 *
 * التأخير قبل الإظهار يمنع وميض الشعار بالتنقلات السريعة (الصفحة محمّلة مسبقاً)، والحد الأدنى
 * للظهور يمنع اختفاءه بعد لحظة لو ظهر.
 */
const SHOW_DELAY_MS = 150
const MIN_VISIBLE_MS = 400

const isRouteLoading = ref(false)

let showTimer: ReturnType<typeof setTimeout> | undefined
let hideTimer: ReturnType<typeof setTimeout> | undefined
let shownAt = 0

function start() {
  clearTimeout(hideTimer)
  if (isRouteLoading.value || showTimer)
    return

  showTimer = setTimeout(() => {
    showTimer = undefined
    shownAt = Date.now()
    isRouteLoading.value = true
  }, SHOW_DELAY_MS)
}

function done() {
  clearTimeout(showTimer)
  showTimer = undefined
  if (!isRouteLoading.value)
    return

  const remaining = MIN_VISIBLE_MS - (Date.now() - shownAt)

  hideTimer = setTimeout(() => {
    isRouteLoading.value = false
  }, Math.max(0, remaining))
}

export function useRouteLoading() {
  return { isRouteLoading, start, done }
}

import { onMounted, ref, watch } from 'vue'
import { idbDelete, idbGet, idbSet, isIdbAvailable } from '@/utils/idbStore'

/**
 * الحفظ التلقائي للنماذج كمسودة (Draft Auto-Save).
 *
 * يراقب حقول النموذج ويحفظها في IndexedDB بعد كل تعديل بفاصل قصير، فإذا انقطع
 * الإنترنت أو أُغلق التبويب أو انهار المتصفح لا يفقد المستخدم ما كتبه. عند إعادة
 * فتح الصفحة تُستعاد المسودة تلقائياً.
 *
 * المرفقات تُحفظ كما هي (File) لأن IndexedDB يدعم ذلك — راجع utils/idbStore.
 *
 * ⚠️ لا تمرّر حقولاً حسّاسة (كلمة مرور، رمز OTP، بيانات بطاقة) عبر `data`:
 * المسودة تُكتب على قرص المستخدم وتبقى بعد إغلاق المتصفح.
 */

export interface DraftRecord<T> {
  data: T
  file: File | null
  savedAt: number
}

export interface UseDraftAutoSaveOptions<T> {
  /** مفتاح تخزين فريد للنموذج. */
  key: string
  /** لقطة من حقول النموذج القابلة للحفظ. */
  data: () => T
  /** المرفق الحالي إن وُجد. */
  file?: () => File | null
  /** إعادة تعبئة النموذج من مسودة مستعادة. */
  apply: (data: T, file: File | null) => void
  /** اعتبار المسودة فارغة فلا تُحفظ. الافتراضي: كل الحقول فارغة ولا مرفق. */
  isEmpty?: (data: T, file: File | null) => boolean
  debounceMs?: number
  /** عمر أقصى للمسودة، تُهمل بعده. الافتراضي 7 أيام. */
  maxAgeMs?: number
}

const DEFAULT_DEBOUNCE_MS = 500
const DEFAULT_MAX_AGE_MS = 7 * 24 * 60 * 60 * 1000

function defaultIsEmpty(data: unknown, file: File | null): boolean {
  if (file)
    return false

  return Object.values(data as Record<string, unknown>).every(
    value => value === null || value === undefined || value === '',
  )
}

export function useDraftAutoSave<T extends Record<string, any>>(
  options: UseDraftAutoSaveOptions<T>,
) {
  const {
    key,
    data,
    file,
    apply,
    isEmpty = defaultIsEmpty,
    debounceMs = DEFAULT_DEBOUNCE_MS,
    maxAgeMs = DEFAULT_MAX_AGE_MS,
  } = options

  const savedAt = ref<number | null>(null)
  const restoredFromDraft = ref(false)
  const isSupported = isIdbAvailable()

  let timer: ReturnType<typeof setTimeout> | null = null
  // يمنع الحفظ الذي يطلقه الاستعادة نفسها من الكتابة فوق المسودة فوراً.
  let suspended = true

  async function clearDraft() {
    if (timer) {
      clearTimeout(timer)
      timer = null
    }
    savedAt.value = null
    restoredFromDraft.value = false
    await idbDelete(key).catch(() => {})
  }

  /** يكتب المسودة فوراً بلا انتظار — يُستخدم قبل إخفاء الصفحة. */
  async function saveNow() {
    const snapshot = data()
    const attachment = file?.() ?? null

    if (isEmpty(snapshot, attachment)) {
      await idbDelete(key).catch(() => {})
      savedAt.value = null

      return
    }

    const record: DraftRecord<T> = {
      // نسخة عادية — الكائنات التفاعلية (Proxy) لا تجتاز structured clone.
      data: JSON.parse(JSON.stringify(snapshot)),
      file: attachment,
      savedAt: Date.now(),
    }

    await idbSet(key, record).catch(() => {})
    savedAt.value = record.savedAt
  }

  function scheduleSave() {
    if (suspended || !isSupported)
      return

    if (timer)
      clearTimeout(timer)

    timer = setTimeout(() => { void saveNow() }, debounceMs)
  }

  onMounted(async () => {
    if (!isSupported)
      return

    const record = await idbGet<DraftRecord<T>>(key).catch(() => undefined)

    if (record && Date.now() - record.savedAt <= maxAgeMs) {
      apply(record.data, record.file ?? null)
      savedAt.value = record.savedAt
      restoredFromDraft.value = true
    }
    else if (record) {
      await idbDelete(key).catch(() => {})
    }

    // الحفظ يبدأ بعد الاستعادة حتى لا تُمسح المسودة بقيم النموذج الفارغة.
    suspended = false
  })

  watch(() => [data(), file?.() ?? null], scheduleSave, { deep: true })

  // إغلاق التبويب أو تبديله لا يترك مهلة الـ debounce معلّقة.
  if (typeof document !== 'undefined') {
    document.addEventListener('visibilitychange', () => {
      if (document.visibilityState === 'hidden' && !suspended)
        void saveNow()
    })
  }

  return { savedAt, restoredFromDraft, clearDraft, saveNow, isSupported }
}

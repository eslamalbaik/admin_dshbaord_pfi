import { ref } from 'vue'
import api from '@/plugins/axios'

/**
 * مقدمات الدول وأعلامها لخانات الجوال (إعدادات ← مقدمات الدول). نفس منطق التحقق في الخادم
 * (App\Models\CountryDialCode::isValidPhone) حتى لا يقبل أحدهما ما يرفضه الآخر.
 */
export interface DialCode {
  id: number
  name: string
  iso_code: string | null
  dial_code: string
  flag_url: string | null
  min_digits: number
  max_digits: number
  starts_with: string | null
  is_default: boolean
  is_active?: boolean
}

export const dialCodes = ref<DialCode[]>([])

let loading: Promise<DialCode[]> | null = null

/** يجلب المقدمات المفعّلة مرة وحدة للصفحة (force بعد تعديلها من الإعدادات). */
export const loadDialCodes = (force = false) => {
  if (!loading || force) {
    loading = api.get('/api/v1/app/country-codes')
      .then(({ data }) => (dialCodes.value = data.items ?? []))
      .catch(() => {
        loading = null

        return dialCodes.value
      })
  }

  return loading
}

export const defaultDialCode = (codes = dialCodes.value) =>
  codes.find(c => c.is_default) ?? codes.find(c => c.dial_code === '970') ?? codes[0]

/** هل الرقم المحلي (بعد المقدمة) مطابق لقاعدة الدولة؟ */
export const acceptsNational = (code: DialCode, national: string) => {
  if (!/^[0-9]+$/.test(national) || national.length < code.min_digits || national.length > code.max_digits)
    return false
  const prefixes = (code.starts_with ?? '').split(',').map(p => p.trim()).filter(Boolean)

  return !prefixes.length || prefixes.some(p => national.startsWith(p))
}

/** وصف قاعدة الدولة للمستخدم: "9 أرقام تبدأ بـ 5" */
export const describeRule = (code: DialCode) => {
  const len = code.min_digits === code.max_digits ? `${code.min_digits} أرقام` : `${code.min_digits} إلى ${code.max_digits} أرقام`
  const prefixes = (code.starts_with ?? '').split(',').map(p => p.trim()).filter(Boolean)

  return prefixes.length ? `${len} تبدأ بـ ${prefixes.join(' أو ')}` : len
}

/**
 * يفكّ أي رقم محفوظ (+970599123456 / 00970… / 970599123456 / 0599123456 / 599123456) لمقدمة ورقم محلي.
 * الرقم بدون مقدمة دولية بينحسب على المقدمة الافتراضية.
 */
export const parsePhone = (value: string | null | undefined, codes = dialCodes.value): { code?: DialCode, national: string, international: boolean } => {
  const raw = String(value ?? '').trim()
  let digits = raw.replace(/\D+/g, '')
  const international = raw.startsWith('+') || digits.startsWith('00')
  if (digits.startsWith('00'))
    digits = digits.slice(2)

  const sorted = [...codes].sort((a, b) => b.dial_code.length - a.dial_code.length)
  if (international || !digits.startsWith('0')) {
    for (const code of sorted) {
      if (!digits.startsWith(code.dial_code))
        continue
      let national = digits.slice(code.dial_code.length)
      if (!acceptsNational(code, national) && national.startsWith('0') && acceptsNational(code, national.slice(1)))
        national = national.slice(1)
      if (acceptsNational(code, national))
        return { code, national, international: true }
    }
    if (international)
      return { code: undefined, national: digits, international: true }
  }

  return { code: defaultDialCode(codes), national: digits.startsWith('0') ? digits.slice(1) : digits, international: false }
}

/** صحة رقم جوال بأي صيغة — قبل تحميل المقدمات بنكتفي بالشكل العام. */
export const isValidPhone = (value: string, codes = dialCodes.value) => {
  if (/[^\d+\s\-()]/.test(value))
    return false
  if (!codes.length)
    return /^\+?[0-9]{9,15}$/.test(value.replace(/[\s\-()]+/g, ''))
  const { code, national } = parsePhone(value, codes)

  return !!code && acceptsNational(code, national)
}

/** الرقم كما بيتخزن ويتعرض: +970599123456 */
export const formatPhone = (code: DialCode | undefined, national: string) =>
  national ? `+${code?.dial_code ?? ''}${national}` : ''

/** للعرض: رقم محفوظ بأي صيغة ← +970 599123456 */
export const displayPhone = (value: string | null | undefined) => {
  if (!value)
    return ''
  const { code, national } = parsePhone(value)

  return code ? `+${code.dial_code} ${national}` : String(value)
}

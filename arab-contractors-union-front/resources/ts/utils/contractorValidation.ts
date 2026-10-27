import type { Directive } from 'vue'

/**
 * تحقق خانات نموذجَي إضافة وتعديل المقاول — نفس الأنماط في الخادم
 * (ContractorController::PHONE_PATTERN وأخواتها) حتى لا يقبل أحدهما ما يرفضه الآخر.
 */

export type NumericMode = 'digits' | 'phone' | 'decimal'

const ARABIC_DIGITS: Record<string, string> = {
  '٠': '0', '١': '1', '٢': '2', '٣': '3', '٤': '4', '٥': '5', '٦': '6', '٧': '7', '٨': '8', '٩': '9',
  '۰': '0', '۱': '1', '۲': '2', '۳': '3', '۴': '4', '۵': '5', '۶': '6', '۷': '7', '۸': '8', '۹': '9',
}

export const toLatinDigits = (value: string) => value.replace(/[٠-٩۰-۹]/g, d => ARABIC_DIGITS[d])

/** يُبقي الأرقام فقط (و+ في أول رقم الجوال، ونقطة عشرية واحدة لرأس المال). */
export const sanitizeNumeric = (value: string, mode: NumericMode) => {
  const v = toLatinDigits(value ?? '')
  if (mode === 'phone')
    return (v.trimStart().startsWith('+') ? '+' : '') + v.replace(/\D+/g, '')
  if (mode === 'decimal') {
    const [int, ...rest] = v.replace(/[^\d.]+/g, '').split('.')
    return rest.length ? `${int}.${rest.join('')}` : int
  }
  return v.replace(/\D+/g, '')
}

/**
 * v-numeric="'digits' | 'phone' | 'decimal'" على VTextField: يحذف أي حرف عند الكتابة أو اللصق
 * قبل أن يصل لـ v-model. المستمع على الجذر بمرحلة capture فيسبق مستمع input الداخلي لـ Vuetify،
 * وتحترم القيمة المنظّفة خاصية maxlength.
 */
export const vNumeric: Directive<HTMLElement, NumericMode | undefined> = {
  mounted(el, binding) {
    const handler = (e: Event) => {
      const input = e.target as HTMLInputElement
      if (!(input instanceof HTMLInputElement))
        return
      const mode = (el as any).__numericMode as NumericMode
      let clean = sanitizeNumeric(input.value, mode)
      if (input.maxLength > 0)
        clean = clean.slice(0, input.maxLength)
      if (clean !== input.value) {
        const pos = Math.max(0, (input.selectionStart ?? clean.length) - (input.value.length - clean.length))
        input.value = clean
        input.setSelectionRange?.(pos, pos)
      }
    }

    // الأرقام تُكتب يساراً لليمين حتى تظهر + في أول الرقم الدولي لا آخره، مع بقاء المحاذاة يميناً
    const inner = el.querySelector('input')
    if (inner) {
      inner.style.direction = 'ltr'
      inner.style.textAlign = 'right'
    }

    ;(el as any).__numericMode = binding.value ?? 'digits'
    ;(el as any).__numericHandler = handler
    el.addEventListener('input', handler, true)
  },
  updated(el, binding) {
    (el as any).__numericMode = binding.value ?? 'digits'
  },
  unmounted(el) {
    el.removeEventListener('input', (el as any).__numericHandler, true)
  },
}

const PHONE_RE = /^\+?[0-9]{9,15}$/
const FAX_RE = /^\+?[0-9]{6,15}$/
const ID_RE = /^[0-9]{9}$/
const DECIMAL_RE = /^[0-9]+(\.[0-9]+)?$/
const HAS_DIGIT_RE = /[0-9٠-٩۰-۹]/
const EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]+$/

const empty = (v: unknown) => v === null || v === undefined || String(v).trim() === ''

/** تاريخ اليوم (Y-m-d) بتوقيت غزة — نفس "اليوم" الذي يقارن به الخادم، مهما كانت منطقة جهاز المستخدم. */
const today = () => new Intl.DateTimeFormat('en-CA', { timeZone: 'Asia/Gaza', year: 'numeric', month: '2-digit', day: '2-digit' }).format(new Date())

/** أمس بتوقيت غزة: أقصى قيمة لتاريخ التسجيل (خاصية max في خانة التاريخ). */
export const yesterday = () => {
  const d = new Date(`${today()}T00:00:00Z`)

  d.setUTCDate(d.getUTCDate() - 1)

  return d.toISOString().substring(0, 10)
}

/** قواعد Vuetify للخانات الاختيارية: فارغة = مقبولة، وإلا يجب أن تطابق الصيغة. */
export const contractorRules = {
  idNumber: (v: string) => empty(v) || ID_RE.test(v) || 'رقم الهوية يجب أن يتكون من 9 أرقام بالضبط',
  mobile: (v: string) => empty(v) || PHONE_RE.test(v) || 'أرقام فقط (9 إلى 15 رقماً، ويمكن أن يبدأ بـ +)',
  fax: (v: string) => empty(v) || FAX_RE.test(v) || 'أرقام فقط (6 إلى 15 رقماً)',
  capital: (v: string) => empty(v) || DECIMAL_RE.test(String(v)) || 'رأس المال يقبل أرقاماً فقط',
  personName: (v: string) => empty(v) || !HAS_DIGIT_RE.test(v) || 'الاسم لا يقبل أرقاماً',
  email: (v: string) => empty(v) || EMAIL_RE.test(v) || 'البريد الإلكتروني غير صحيح',
  notFuture: (v: string) => empty(v) || String(v).substring(0, 10) <= today() || 'لا يمكن أن يكون التاريخ في المستقبل',
  beforeToday: (v: string) => empty(v) || String(v).substring(0, 10) < today() || 'تاريخ التسجيل يجب أن يكون أمس أو أقدم',
  maxLen: (max: number) => (v: string) => empty(v) || String(v).length <= max || `يجب ألا يتجاوز ${max} حرفاً`,
}

/** أسماء الخانات المعبّأة بصيغة خاطئة في خطوة معيّنة — تمنع الانتقال والحفظ كما الحقول الإلزامية. */
export const invalidFormatFields = (f: Record<string, any>, step: number): string[] => {
  const checks: Record<number, [string, unknown, (v: any) => true | string][]> = {
    1: [
      ['رأس المال', f.capital, contractorRules.capital],
      ['تاريخ التسجيل', f.registration_date, contractorRules.beforeToday],
    ],
    2: [
      ['اسم صاحب المنشأة', f.owner_name, contractorRules.personName],
      ['اسم المفوض بالتوقيع', f.authorized_person, contractorRules.personName],
      ['رقم هوية المفوض', f.authorized_person_id_number, contractorRules.idNumber],
      ['رقم جوال المفوض', f.authorized_person_phone, contractorRules.mobile],
      ['رقم الواتساب للمفوض', f.authorized_person_whatsapp, contractorRules.mobile],
    ],
    3: [
      ['الهاتف / الفاكس', f.fax, contractorRules.fax],
      ['البريد الإلكتروني', f.email, contractorRules.email],
      ['تاريخ التأسيس', f.established_date, contractorRules.notFuture],
    ],
  }

  return (checks[step] ?? []).filter(([, value, rule]) => rule(value) !== true).map(([label]) => label)
}

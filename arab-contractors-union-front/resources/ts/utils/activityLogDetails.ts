// عرض تفاصيل سجل النشاط بشكل مفهوم بالعربي بدل JSON الخام: مسمّيات عربية للحقول،
// تنسيق المبالغ والتواريخ والحالات، واستخراج التغييرات «قبل ← بعد». أي حقل غير معروف
// بيظهر باسمه الأصلي مرتّباً بدل ما يختفي.

export interface DetailRow {
  key: string
  label: string
  value: string
  children?: DetailRow[]
  long?: boolean
}

export interface ChangeRow {
  key: string
  label: string
  before: string
  after: string
}

const FIELD_LABELS: Record<string, string> = {
  // المقاول
  contractor_id: 'رقم المقاول',
  contractor_ids: 'المقاولون',
  contractor_name: 'المقاول',
  membership_number: 'رقم العضوية',
  next_membership_number: 'رقم العضوية التالي',
  contractors: 'عدد المقاولين',
  contractors_created: 'مقاولون جدد',
  companies_total: 'إجمالي الشركات',
  is_frozen: 'تجميد الحساب',
  equipment_banned_at: 'تاريخ حظر المعدّات',

  // المبالغ والدفعات
  amount: 'المبلغ',
  amount_jod: 'المبلغ (د.أ)',
  original_amount_jod: 'المبلغ قبل الخصم (د.أ)',
  discount_amount_jod: 'إجمالي الخصم (د.أ)',
  used_amount_jod: 'المبلغ المستخدم (د.أ)',
  remaining_total_jod: 'المتبقي الإجمالي (د.أ)',
  total_sheet_jod: 'إجمالي الملف (د.أ)',
  total_matched_jod: 'إجمالي المطابَق (د.أ)',
  net_jod: 'الرصيد الصافي (د.أ)',
  registration_fee_jod: 'رسوم التسجيل (د.أ)',
  annual_fee_jod: 'الاشتراك السنوي (د.أ)',
  price: 'السعر',
  currency: 'العملة',
  exchange_rate: 'سعر الصرف',
  rate_to_jod: 'السعر مقابل الدينار',
  payment_id: 'رقم الدفعة',
  due_id: 'رقم الذمة',
  due_ids: 'الذمم',
  due: 'الذمة',
  credit_id: 'رقم الرصيد الدائن',
  adjustment_id: 'رقم التعديل',
  penalty_id: 'رقم الغرامة',
  discount_type: 'نوع الخصم',
  discount_value: 'قيمة الخصم',
  applied: 'عدد العناصر المتأثرة',
  receipt_image: 'صورة الإيصال',
  bank_name: 'اسم البنك',
  bank_name_en: 'اسم البنك (إنجليزي)',
  iban: 'رقم الآيبان',
  account_number: 'رقم الحساب',
  account_holder: 'اسم صاحب الحساب',

  // عام
  status: 'الحالة',
  old_status: 'الحالة السابقة',
  new_status: 'الحالة الجديدة',
  from: 'من',
  to: 'إلى',
  type: 'النوع',
  reason: 'السبب',
  year: 'السنة',
  mode: 'طريقة التحديد',
  criteria: 'المعايير',
  filters: 'الفلاتر',
  ids: 'العناصر المحددة',
  id: 'الرقم',
  user_id: 'رقم المستخدم',
  name: 'الاسم',
  name_ar: 'الاسم بالعربي',
  name_en: 'الاسم بالإنجليزي',
  label: 'المسمّى',
  label_en: 'المسمّى بالإنجليزي',
  title: 'العنوان',
  title_en: 'العنوان بالإنجليزي',
  description: 'الوصف',
  description_en: 'الوصف بالإنجليزي',
  excerpt: 'المقتطف',
  body: 'المحتوى',
  message: 'الرسالة',
  category: 'التصنيف',
  slug: 'الرابط المختصر',
  url: 'الرابط',
  external_url: 'رابط خارجي',
  video_url: 'رابط الفيديو',
  image: 'الصورة',
  images: 'الصور',
  photo: 'الصورة',
  logo: 'الشعار',
  logo_url: 'رابط الشعار',
  cover_image: 'صورة الغلاف',
  cover_image_url: 'رابط صورة الغلاف',
  remove_image: 'حذف الصورة',
  is_image: 'صورة',
  icon: 'الأيقونة',
  path: 'المسار',
  is_active: 'الحالة',
  sort: 'الترتيب',
  sort_order: 'الترتيب',
  level: 'المستوى',
  grade_label: 'الدرجة',
  grade_code: 'رمز الدرجة',
  eligible_field_codes: 'المجالات المسموحة',
  contractor_field_id: 'رقم المجال',
  governorate_id: 'رقم المحافظة',
  issued_at: 'تاريخ الإصدار',
  reviewed_by: 'راجعه',
  reviewed_at: 'تاريخ المراجعة',
  duration_days: 'المدة (أيام)',
  ads_limit: 'حد الإعلانات',
  is_keynote: 'متحدث رئيسي',
  announcement_id: 'رقم التعميم',
  recipients_count: 'عدد المستلمين',
  settings: 'الإعدادات',
  keys: 'الحقول',
  password: 'كلمة المرور',
  permissions: 'الصلاحيات',
  email: 'البريد الإلكتروني',
  phone: 'الهاتف',
  address: 'العنوان',
  decision_number: 'رقم قرار التصنيف',
  decision_date: 'تاريخ قرار التصنيف',
  certificate_file: 'ملف الشهادة',
  created_at: 'تاريخ الإنشاء',
  updated_at: 'تاريخ التعديل',
  due_date: 'تاريخ الاستحقاق',
  paid_at: 'تاريخ الدفع',

  // الاستيراد والعمليات بالجملة
  imported: 'تم استيراده',
  imported_count: 'عدد المستورَد',
  matched_count: 'عدد المطابَق',
  unmatched_count: 'عدد غير المطابَق',
  generated: 'تم توليده',
  created: 'تم إنشاؤه',
  deleted: 'تم حذفه',
  deleted_count: 'عدد المحذوف',
  create_missing: 'إنشاء المقاولين الناقصين',
  dry_run: 'تجربة بدون حفظ',
  force: 'إجبار',
  warning: 'تنبيه',

  // الأمان
  endpoint: 'المسار المطلوب',
  ip: 'عنوان IP',
  status_code: 'رمز الاستجابة',
  error_code: 'رمز الخطأ',
  required_roles: 'الأدوار المطلوبة',
  required_permission: 'الصلاحية المطلوبة',
}

const VALUE_LABELS: Record<string, string> = {
  // حالات الدفعات والذمم والغرامات
  pending: 'بانتظار الاعتماد',
  paid: 'مدفوعة / مسدَّدة',
  unpaid: 'غير مسدَّدة',
  partially_paid: 'مسدَّدة جزئياً',
  rejected: 'مرفوضة',
  refunded: 'مسترجعة',
  failed: 'فشلت',
  confirmed: 'مؤكَّدة',
  cancelled: 'ملغاة',
  approved: 'مقبولة',

  // حالات المقاول والعضوية
  active: 'فعّالة',
  inactive: 'غير فعّالة',
  expired: 'منتهية',
  suspended: 'موقوفة',
  frozen: 'مجمّد',

  // تذاكر الدعم والبلاغات
  open: 'مفتوحة',
  in_progress: 'قيد المعالجة',
  resolved: 'محلولة',
  closed: 'مغلقة',

  // أنواع الدفعات
  dues_payment: 'سداد ذمة',
  membership_fee: 'رسوم اشتراك',
  membership: 'رسوم اشتراك',
  penalty_payment: 'دفع غرامة',
  penalty: 'دفع غرامة',
  advance_payment: 'دفعة مقدمة',
  equipment_subscription: 'اشتراك باقة المعدات',

  // الخصومات
  percent: 'نسبة مئوية',
  fixed: 'مبلغ ثابت',

  // العملات
  JOD: 'دينار أردني',
  ILS: 'شيكل',
  USD: 'دولار',
}

const PAYMENT_STATUS: Record<string, string> = {
  pending: 'بانتظار اعتماد قسم المحاسبة',
  paid: 'مؤكَّدة',
  rejected: 'مرفوضة',
  refunded: 'مسترجعة',
  failed: 'فشلت',
}

const CURRENCY_SYMBOLS: Record<string, string> = { JOD: 'د.أ', ILS: '₪', USD: '$' }

// قيم بتتحوّل لتسميتها العربية بس لما تكون بهالحقول (عشان ما نترجم نص حر بالغلط)
const ENUM_KEYS = new Set(['status', 'old_status', 'new_status', 'from', 'to', 'type', 'discount_type', 'currency', 'mode'])

const SUBJECT_LABELS: Record<string, string> = {
  Payment: 'دفعة',
  ContractorDue: 'ذمة مالية',
  Penalty: 'غرامة',
  Contractor: 'مقاول',
  ContractorCredit: 'رصيد دائن',
  ContractorBalanceAdjustment: 'تعديل رصيد',
  Membership: 'عضوية',
  CertificateRequest: 'طلب شهادة',
  GradeFee: 'رسوم درجة',
  ExchangeRate: 'سعر صرف',
  User: 'مستخدم',
  News: 'خبر',
  Event: 'فعالية',
  Announcement: 'تعميم',
  AnnouncementCategory: 'تصنيف تعميمات',
  Tender: 'عطاء',
  TenderCategory: 'تصنيف عطاءات',
  TenderAttachment: 'مرفق عطاء',
  Term: 'نص شروط',
  LegalFile: 'ملف قانوني',
  LegalFileCategory: 'تصنيف ملفات قانونية',
  BankAccount: 'حساب بنكي',
  Page: 'صفحة',
  Setting: 'الإعدادات',
  SupportTicket: 'تذكرة دعم',
  Document: 'وثيقة',
  Equipment: 'آلية',
  EquipmentType: 'نوع آلية',
  EquipmentPackage: 'باقة اشتراك',
  EquipmentReport: 'بلاغ آلية',
  City: 'مدينة',
  Governorate: 'محافظة',
  ContractorField: 'مجال',
  ContractorSpecialization: 'تخصص',
  ContractorGrade: 'درجة تصنيف',
}

export const subjectLabel = (type?: string | null, id?: number | string | null) => {
  if (!type)
    return null

  return `${SUBJECT_LABELS[type] ?? type}${id ? ` رقم ${id}` : ''}`
}

const humanize = (key: string) => key.replace(/_/g, ' ')

export const fieldLabel = (key: string) => FIELD_LABELS[key] ?? humanize(key)

const numberFormat = new Intl.NumberFormat('en-US', { maximumFractionDigits: 3 })

export const formatNumber = (v: number | string) => numberFormat.format(Number(v))

const isMoneyKey = (key: string) => /_jod$|^amount$|^price$|_amount$|^net$|^total/.test(key)

const ISO_DATE = /^\d{4}-\d{2}-\d{2}(?:[T ]\d{2}:\d{2}(?::\d{2}(?:\.\d+)?)?(?:Z|[+-]\d{2}:?\d{2})?)?$/

export const formatDateTime = (v: string) => {
  const hasTime = v.length > 10
  const d = new Date(hasTime ? v : `${v}T00:00:00`)
  if (Number.isNaN(d.getTime()))
    return v

  return d.toLocaleString('ar-PS', hasTime
    ? { year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit' }
    : { year: 'numeric', month: '2-digit', day: '2-digit' })
}

const EMPTY = '—'

/** تنسيق قيمة مفردة حسب اسم الحقل (مبلغ، تاريخ، حالة، نعم/لا...) */
export const formatValue = (key: string, value: any, meta: Record<string, any> = {}): string => {
  if (value === null || value === undefined || value === '')
    return EMPTY

  if (typeof value === 'boolean' || ((key.startsWith('is_') || key === 'force' || key === 'dry_run') && (value === 0 || value === 1 || value === '0' || value === '1'))) {
    const on = value === true || value === 1 || value === '1'
    if (key === 'is_active')
      return on ? 'فعّال' : 'معطّل'

    return on ? 'نعم' : 'لا'
  }

  if (key === 'password')
    return 'تم تغييرها'

  const str = String(value)

  if (ENUM_KEYS.has(key) && VALUE_LABELS[str])
    return VALUE_LABELS[str]

  if (key === 'discount_value' && meta.discount_type === 'percent' && !Number.isNaN(Number(value)))
    return `${formatNumber(value)}%`

  if (isMoneyKey(key) && str.trim() !== '' && !Number.isNaN(Number(value))) {
    const symbol = key.endsWith('_jod') ? 'د.أ' : (CURRENCY_SYMBOLS[meta.currency] ?? '')

    return `${formatNumber(value)}${symbol ? ` ${symbol}` : ''}`
  }

  if (/(?:^|_)id$/.test(key) && /^\d+$/.test(str))
    return `#${str}`

  if (typeof value === 'number')
    return formatNumber(value)

  if (typeof value === 'string' && ISO_DATE.test(value))
    return formatDateTime(value)

  return str
}

const isPlainObject = (v: any) => v !== null && typeof v === 'object' && !Array.isArray(v)

/** يحوّل الـ meta لقائمة صفوف «الحقل: القيمة» (الكائنات المتداخلة بتصير صفوف فرعية) */
export const buildRows = (data: Record<string, any>, hidden: string[] = [], meta: Record<string, any> = data): DetailRow[] =>
  Object.entries(data ?? {})
    .filter(([k, v]) => !hidden.includes(k) && v !== null && v !== undefined && v !== '')
    .map(([key, value]): DetailRow => {
      const label = fieldLabel(key)

      if (Array.isArray(value)) {
        if (value.every(v => !isPlainObject(v) && !Array.isArray(v))) {
          const items = value.map(v => formatValue(key.replace(/s$/, ''), v, meta))

          return { key, label, value: value.length ? `${items.join('، ')}${value.length > 3 ? ` (${value.length})` : ''}` : EMPTY }
        }

        return {
          key,
          label,
          value: `${value.length} عنصر`,
          children: value.map((v, i) => ({ key: `${key}.${i}`, label: `${i + 1}`, value: '', children: buildRows(v, [], v) })),
        }
      }

      if (isPlainObject(value))
        return { key, label, value: '', children: buildRows(value, [], value) }

      const formatted = formatValue(key, value, meta)

      return { key, label, value: formatted, long: formatted.length > 80 }
    })

/** مثل formatValue، بس القوائم والكائنات بتتحوّل لسطر واحد مقروء (للتغييرات قبل/بعد) */
export const formatAny = (key: string, value: any, meta: Record<string, any> = {}): string => {
  if (Array.isArray(value) && value.every(v => !isPlainObject(v) && !Array.isArray(v)))
    return value.length ? value.map(v => formatValue(key.replace(/s$/, ''), v, meta)).join('، ') : EMPTY

  if (isPlainObject(value) || Array.isArray(value))
    return buildRows(isPlainObject(value) ? value : { [key]: value }).map(r => r.children ? r.label : `${r.label}: ${r.value}`).join('، ') || EMPTY

  return formatValue(key, value, meta)
}

/**
 * التغييرات قبل ← بعد من الـ meta: before/after، old_status/new_status، أو from/to
 * لتغيير الحالة. بترجع كمان الحقول اللي انعرضت عشان ما تتكرر بقائمة التفاصيل.
 */
export const extractChanges = (action: string, meta: Record<string, any>): { changes: ChangeRow[]; used: string[] } => {
  const changes: ChangeRow[] = []
  const used: string[] = []

  if (isPlainObject(meta.before) || isPlainObject(meta.after)) {
    const before = meta.before ?? {}
    const after = meta.after ?? {}
    const keys = [...new Set([...Object.keys(before), ...Object.keys(after)])]
    const ctx = { ...meta, ...after }
    for (const k of keys)
      changes.push({ key: k, label: fieldLabel(k), before: formatAny(k, before[k], ctx), after: formatAny(k, after[k], ctx) })
    used.push('before', 'after')
  }

  if ('old_status' in meta || 'new_status' in meta) {
    changes.push({ key: 'status', label: 'الحالة', before: formatValue('status', meta.old_status), after: formatValue('status', meta.new_status) })
    used.push('old_status', 'new_status')
  }

  if (action.endsWith('status_changed') && ('from' in meta || 'to' in meta)) {
    // حالة الدفعة «paid» معناها مؤكَّدة (مش مسدَّدة متل الذمة)
    const status = (v: any) => action.startsWith('payment.') && PAYMENT_STATUS[v] ? PAYMENT_STATUS[v] : formatValue('status', v)
    changes.push({ key: 'status', label: 'الحالة', before: status(meta.from), after: status(meta.to) })
    used.push('from', 'to')
  }

  return { changes, used }
}

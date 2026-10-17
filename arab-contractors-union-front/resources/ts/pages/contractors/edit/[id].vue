<script setup lang="ts">
import ContractorWizardStepper from '@/components/contractors/ContractorWizardStepper.vue'
import type { WizardStepState } from '@/components/contractors/ContractorWizardStepper.vue'
import api from '@/plugins/axios'
import { UPLOAD_TIMEOUT_MS, describeRequestError } from '@/utils/requestError'
import { contractorDocumentLabels } from '@/utils/contractorDocuments'
import type { ContractorDocumentKey } from '@/utils/contractorDocuments'
import { useRoute, useRouter } from 'vue-router'
import { useDisplay } from 'vuetify'

definePage({ meta: { requiresAdmin: true, adminOnly: true } })

const route = useRoute()
const router = useRouter()
const { smAndDown } = useDisplay()
const step = ref(1)
const loading = ref(false)
const initLoading = ref(true)
const errorMsg = ref('')

const validationErrors = ref<Record<string, string[]>>({})

const form = ref({
  // Step 1: معلومات العضوية والتأسيس
  membership_number: '',
  commercial_register: '',
  name: '',
  capital: '',
  registration_date: '',
  legal_form: '',
  company_purposes: '',

  // Step 2: بيانات الإدارة والشركاء
  owner_name: '',
  partners: [] as string[],
  authorized_person: '',
  authorized_person_id_number: '',
  authorized_person_phone: '',
  authorized_person_whatsapp: '',

  // Step 3: العنوان وبيانات الاتصال
  phone: '',
  fax: '',
  email: '',
  governorate_id: null as number | null,
  city_id: null as number | null,
  district: '',
  address: '',
  building: '',
  floor: '',
  specialties: [
    { field_lk_type: null as number | null, specialization_lk_type: null as number | null, classification: '' }
  ] as Array<{ field_lk_type: number | null, specialization_lk_type: number | null, classification: string }>,
  established_date: '',
  license_number: '',
  classification: '',

  // Step 4: الوثائق والمستندات المطلوبة
  lease_or_ownership_contract: null as File | null,
  company_approval_letter: null as File | null,
  municipal_license: null as File | null,
  company_register: null as File | null,
  cr_file: null as File | null,
  id_file: null as File | null,
  articles_of_association: null as File | null,
  internal_bylaws: null as File | null,
  bank_dealing_letter: null as File | null,
  secretary_contract: null as File | null,
  full_time_engineer_certificate: null as File | null,
  accountant_certificate_or_contract: null as File | null,
  partners_ids: null as File | null,
  authorization_letter: null as File | null,
  authorized_signature: null as File | null,
  notes: '',
})

const newPartnerName = ref('')

const addPartner = () => {
  const name = newPartnerName.value.trim()
  if (name) {
    if (!form.value.partners) {
      form.value.partners = []
    }
    if (!form.value.partners.includes(name)) {
      form.value.partners.push(name)
    }
    newPartnerName.value = ''
  }
}

const removePartner = (index: number) => {
  form.value.partners.splice(index, 1)
}

const addSpecialty = () => {
  form.value.specialties.push({ field_lk_type: null, specialization_lk_type: null, classification: '' })
}

const removeSpecialtyDialog = ref(false)
const removingSpecialtyIndex = ref<number | null>(null)

const confirmRemoveSpecialty = (index: number) => {
  removingSpecialtyIndex.value = index
  removeSpecialtyDialog.value = true
}

const removeSpecialty = () => {
  if (removingSpecialtyIndex.value !== null)
    form.value.specialties.splice(removingSpecialtyIndex.value, 1)
  removeSpecialtyDialog.value = false
  removingSpecialtyIndex.value = null
}

// المجالات/الاختصاصات/الدرجات وربط المحافظات-المدن تُجلب من الخادم (مصدر واحد REQ-01 #6،
// بدل تكرار القوائم هنا ثابتة يدوياً كما كان سابقاً).
const fieldOptions = ref<Array<{ title: string, value: number }>>([])
const specializationOptions = ref<Array<{ title: string, value: number }>>([])
const fieldSpecializations = ref<Record<number, number[]>>({})
const gradeOptions = ref<Array<{ title: string, value: string }>>([])
const topTierFields = ref<number[]>([])
// التصنيف العام (contractors.classification) — منفصل عن تصنيف كل مجال/تخصص، ويقدر المقاول
// يعدّله من التطبيق (dashboard.vue: editClassification) لكن كان غير معروض إطلاقاً بفورم لوحة
// الأدمن، فيبدو للأدمن أن تعديل المقاول من التطبيق "لم ينعكس" رغم نجاح الحفظ فعلياً (TASK-03).
const overallGradeOptions = ref<Array<{ title: string, value: string }>>([])

const specializationOptionsFor = (fieldLkType: number | null) => {
  if (!fieldLkType || !fieldSpecializations.value[fieldLkType])
    return specializationOptions.value
  const allowed = fieldSpecializations.value[fieldLkType]
  return specializationOptions.value.filter(s => allowed.includes(s.value))
}

const gradeOptionsFor = (fieldLkType: number | null) =>
  topTierFields.value.includes(fieldLkType as number) ? gradeOptions.value : gradeOptions.value.filter(g => g.value !== 'اولى أ')

const governorates = ref<Array<{ id: number, name: string, cities: Array<{ id: number, name: string }> }>>([])
const citiesForGovernorate = (governorateId: number | null) =>
  governorates.value.find(g => g.id === governorateId)?.cities ?? []


// قيود رفع المستندات (TASK-16 #2) — تُجلب من الخادم لأن السقف الحقيقي هو
// upload_max_filesize في ini لا رقم ثابت هنا؛ رقم ثابت يفارق الخادم بصمت.
const uploadLimits = ref({ max_file_kb: 0, max_file_mb: 0, max_post_mb: 0, allowed_extensions: [] as string[] })

const fileHint = computed(() => uploadLimits.value.max_file_mb
  ? `الحد الأقصى ${uploadLimits.value.max_file_mb} ميجابايت للملف — الصيغ المسموحة: ${uploadLimits.value.allowed_extensions.join('، ')}`
  : '')

// الرفض هنا قبل بدء الرفع هو ما يمنع انتظار رفع ملف سيُرفض أصلاً (TASK-16 #3):
// المتصفح يرسل الجسم كاملاً ثم يُسقطه PHP، فالتحقق بعد الوصول لا يوفّر الانتظار.
const fileSizeRule = (v: any) => {
  const max = uploadLimits.value.max_file_kb
  if (!max) return true
  const files = Array.isArray(v) ? v : (v ? [v] : [])
  const tooBig = files.find((f: any) => f instanceof File && f.size / 1024 > max)
  return tooBig
    ? `حجم الملف ${(tooBig.size / 1024 / 1024).toFixed(1)} ميجابايت — يتجاوز الحد الأقصى ${uploadLimits.value.max_file_mb} ميجابايت.`
    : true
}

const totalAttachmentsMb = computed(() => {
  const vals = Object.values(form.value).filter((v: any) => v instanceof File) as File[]
  return vals.reduce((sum, f) => sum + f.size, 0) / 1024 / 1024
})

const attachmentsTooLarge = computed(() =>
  !!uploadLimits.value.max_post_mb && totalAttachmentsMb.value > uploadLimits.value.max_post_mb)

const fetchCatalog = async () => {
  try {
    const { data } = await api.get('/api/v1/app/specialties-catalog')
    const items = data.items ?? data
    fieldOptions.value = (items.fields ?? []).map((f: any) => ({ title: f.name, value: f.id }))
    specializationOptions.value = (items.specializations ?? []).map((s: any) => ({ title: s.name, value: s.id }))
    fieldSpecializations.value = items.field_specializations ?? {}
    gradeOptions.value = (items.grades ?? []).map((g: any) => ({ title: g.label, value: g.value }))
    topTierFields.value = (items.grades ?? []).find((g: any) => g.eligible_fields)?.eligible_fields ?? []
    overallGradeOptions.value = (items.overall_grades ?? []).map((g: any) => ({ title: g.label, value: g.value }))
    if (items.upload_limits) uploadLimits.value = items.upload_limits
  } catch (err) {
    console.error('Failed to fetch specialties catalog', err)
  }
}

const fetchGovernorates = async () => {
  try {
    const { data } = await api.get('/api/v1/app/governorates')
    governorates.value = (data.items ?? data).governorates ?? []
  } catch (err) {
    console.error('Failed to fetch governorates', err)
  }
}

// ── الخطوات (نفس شكل صفحة التسجيل) ─────────────────────────────────────
const steps = [
  { title: 'العضوية والتأسيس', subtitle: 'رقم العضوية وبيانات المنشأة', icon: 'tabler-id-badge-2' },
  { title: 'الإدارة والشركاء', subtitle: 'صاحب المنشأة والمفوض بالتوقيع', icon: 'tabler-users' },
  { title: 'العنوان والاتصال', subtitle: 'التواصل والعنوان والمجالات', icon: 'tabler-map-pin' },
  { title: 'الوثائق والمراجعة', subtitle: 'تحديث المستندات ومراجعة التعديلات', icon: 'tabler-file-upload' },
]

// نفس ترتيب صفحة التسجيل + صورة الهوية (موجودة عند بعض المقاولين القدامى فقط).
const documentKeys: ContractorDocumentKey[] = [
  'cr_file', 'id_file', 'company_register', 'municipal_license', 'bank_dealing_letter',
  'articles_of_association', 'internal_bylaws', 'lease_or_ownership_contract', 'partners_ids',
  'authorization_letter', 'company_approval_letter', 'full_time_engineer_certificate',
  'accountant_certificate_or_contract', 'secretary_contract', 'authorized_signature',
]

// روابط الملفات المرفوعة مسبقاً — تُعرض للمعاينة/التحميل جنب كل حقل رفع، بدل ما يظهر الحقل فاضي
const existingFiles = ref<Record<string, string | null>>({})

// ── حذف مستند قائم (TASK-16 #4) ──
// سابقاً كان الاستبدال هو السبيل الوحيد لإزالة مستند. يُجمَع المحدَّد للحذف محلياً
// ويُرسَل عند الحفظ كـ remove_documents[]، لا فوراً — حتى يبقى "إلغاء" ممكناً قبل الحفظ،
// ولأن الحذف على الخادم لا رجعة فيه (يُمحى الملف من القرص).
const documentLabels: Record<string, string> = contractorDocumentLabels

const documentsToRemove = ref<string[]>([])
const removeDocumentDialog = ref(false)
const removingDocumentKey = ref<string | null>(null)

const confirmRemoveDocument = (key: string) => {
  removingDocumentKey.value = key
  removeDocumentDialog.value = true
}

const removeDocument = () => {
  if (removingDocumentKey.value && !documentsToRemove.value.includes(removingDocumentKey.value))
    documentsToRemove.value.push(removingDocumentKey.value)
  removeDocumentDialog.value = false
  removingDocumentKey.value = null
}

const undoRemoveDocument = (key: string) => {
  documentsToRemove.value = documentsToRemove.value.filter(k => k !== key)
}

const formDocs = form.value as unknown as Record<string, File | File[] | null | undefined>
const hasNewFile = (key: string) => {
  const v = formDocs[key]
  return Array.isArray(v) ? v.length > 0 : v instanceof File
}
const newFileName = (key: string) => {
  const v = formDocs[key]
  const f = Array.isArray(v) ? v[0] : v
  return f instanceof File ? f.name : ''
}

// حالة كل مستند: ملف جديد مختار / سيُحذف / موجود على الخادم / غير مرفوع
const docState = (key: string): 'new' | 'removed' | 'existing' | 'empty' => {
  if (hasNewFile(key)) return 'new'
  if (existingFiles.value[key] && documentsToRemove.value.includes(key)) return 'removed'
  if (existingFiles.value[key]) return 'existing'
  return 'empty'
}
const docPresent = (key: string) => ['new', 'existing'].includes(docState(key))
const presentDocsCount = computed(() => documentKeys.filter(k => docPresent(k)).length)

const fetchContractor = async () => {
  try {
    const { data } = await api.get(`/api/v1/contractors/${route.params.id}`)
    const contractor = data.data || data.items || data

    // Populate text fields only
    const textFields = [
      'membership_number', 'commercial_register', 'name', 'capital', 'registration_date',
      'legal_form', 'company_purposes', 'owner_name', 'authorized_person',
      'authorized_person_id_number', 'authorized_person_phone', 'authorized_person_whatsapp',
      'phone', 'fax', 'email', 'governorate_id', 'city_id', 'district', 'building', 'floor',
      'address', 'established_date', 'license_number', 'classification', 'notes'
    ]

    const dateFields = ['established_date', 'registration_date']

    textFields.forEach(field => {
      if (contractor[field] !== null && contractor[field] !== undefined) {
        (form.value as any)[field] = dateFields.includes(field)
          ? String(contractor[field]).substring(0, 10)
          : contractor[field]
      }
    })

    // Handle specialties mapping (multiple or single fallback)
    if (contractor.specialties) {
      form.value.specialties = typeof contractor.specialties === 'string'
        ? JSON.parse(contractor.specialties)
        : contractor.specialties
    } else {
      form.value.specialties = [
        {
          field_lk_type: contractor.field_lk_type,
          specialization_lk_type: contractor.specialization_lk_type,
          classification: contractor.classification
        }
      ]
    }

    // Handle partners mapping from comma-separated string to array
    if (contractor.partners) {
      form.value.partners = typeof contractor.partners === 'string'
        ? contractor.partners.split(',').filter(Boolean)
        : contractor.partners
    } else {
      form.value.partners = []
    }

    documentKeys.forEach(key => {
      existingFiles.value[key] = contractor[`${key}_url`] ?? contractor[key] ?? null
    })
  } catch (err) {
    errorMsg.value = 'فشل جلب بيانات المقاول.'
  } finally {
    // ننتظر أن يُفرَّغ الـ watch الخاص بـ governorate_id (يُجدوَل كـ microtask) قبل رفع initLoading،
    // وإلا يقرأ الـ watch القيمة الجديدة لـ initLoading قبل تنفيذه فعلياً ويمسح city_id المحمَّل لتوّه.
    await nextTick()
    initLoading.value = false
  }
}

onMounted(() => {
  fetchContractor()
  fetchCatalog()
  fetchGovernorates()
})

// لا يُفرَّغ city_id إلا عند تغيير المحافظة يدوياً بعد تحميل الملف — وإلا يُمسح city_id
// المحمَّل من fetchContractor فوراً لأن ضبط governorate_id يُطلق هذا الـ watch أيضاً.
watch(() => form.value.governorate_id, () => {
  if (!initLoading.value)
    form.value.city_id = null
})

// أي خطوة يتبع لها كل حقل — لإرجاع المستخدم للخطوة الصحيحة عند خطأ من الخادم
// ولتعليم الخطوة بعلامة خطأ في الشريط.
const STEP_FIELDS: Record<number, string[]> = {
  1: ['membership_number', 'commercial_register', 'name', 'capital', 'registration_date', 'legal_form', 'company_purposes'],
  2: ['owner_name', 'partners', 'authorized_person', 'authorized_person_id_number', 'authorized_person_phone', 'authorized_person_whatsapp'],
  3: ['phone', 'fax', 'email', 'governorate_id', 'city_id', 'district', 'address', 'building', 'floor', 'license_number', 'established_date', 'classification', 'specialties'],
}
const stepOfField = (key: string) =>
  Number(Object.keys(STEP_FIELDS).find(s => STEP_FIELDS[Number(s)].includes(key)) ?? 4)

const stepHasServerError = (s: number) =>
  Object.keys(validationErrors.value).some(k => stepOfField(k) === s)

const filled = (v: any) => v !== null && v !== undefined && String(v).trim() !== ''
const membershipValid = (v: string) => /^[0-9]+(_g)?$/.test(v ?? '') && Number.parseInt(v) >= 1

// الحقول التي يرفض الخادم الحفظ بدونها — نفس قاعدة صفحة التسجيل.
const blockingMissing = (s: number): string[] => {
  const f = form.value
  const missing: string[] = []
  if (s === 1) {
    if (!filled(f.name)) missing.push('اسم طالب الانتساب')
    if (!membershipValid(f.membership_number)) missing.push('رقم العضوية')
    if (!/^[0-9]{9}$/.test(f.commercial_register ?? '')) missing.push('رقم السجل التجاري')
  }
  if (s === 3) {
    if (!f.governorate_id) missing.push('المحافظة')
    if (!f.city_id) missing.push('المدينة')
    if (!filled(f.district)) missing.push('الحي')
    if (!filled(f.building)) missing.push('العمارة')
    if (!filled(f.floor)) missing.push('الطابق')
  }
  return missing
}

// نسبة اكتمال الحقول المعلّمة بـ* في كل خطوة — تغذي حالة الشريط ومؤشر الاكتمال.
const starredChecks = computed<Record<number, boolean[]>>(() => {
  const f = form.value
  return {
    1: [filled(f.name), membershipValid(f.membership_number), /^[0-9]{9}$/.test(f.commercial_register ?? '')],
    2: [],
    3: [
      /^[0-9]{9}$/.test(f.phone ?? ''), !!f.governorate_id, !!f.city_id, filled(f.district), filled(f.address),
      filled(f.building), filled(f.floor), /^[0-9]{5}$/.test(f.license_number ?? ''), filled(f.established_date),
      f.specialties.every(sp => !!sp.field_lk_type && !!sp.specialization_lk_type && !!sp.classification),
    ],
    4: documentKeys.filter(k => k !== 'id_file' && k !== 'authorized_signature').map(k => docPresent(k)),
  }
})

const overallProgress = computed(() => {
  const all = Object.values(starredChecks.value).flat()
  return all.length ? Math.round(all.filter(Boolean).length / all.length * 100) : 0
})

// بالتعديل كل الخطوات معبّأة مسبقاً، فتظهر حالتها (مكتملة/ناقصة) من البداية.
const maxReached = ref(steps.length)

const stepState = (s: number): WizardStepState => {
  if (stepHasServerError(s)) return s === step.value ? 'current' : 'error'
  if (s === step.value) return 'current'
  if (s > maxReached.value) return 'upcoming'
  return starredChecks.value[s].every(Boolean) ? 'done' : 'partial'
}

const formRef = ref()
const stepNotice = ref('')

const scrollToTop = () => window.scrollTo({ top: 0, behavior: 'smooth' })

const scrollToFirstError = () => nextTick(() => {
  document.querySelector('.contractor-wizard .v-input--error')
    ?.scrollIntoView({ behavior: 'smooth', block: 'center' })
})

// ينقل للخطوة المطلوبة؛ عند التقدّم للأمام يتوقف عند أول خطوة ناقصة الحقول الإلزامية.
const goToStep = async (target: number) => {
  if (target === step.value) return
  if (target > step.value) {
    await formRef.value?.validate()
    for (let s = step.value; s < target; s++) {
      const missing = blockingMissing(s)
      if (missing.length) {
        stepNotice.value = `أكمل الحقول المطلوبة قبل المتابعة: ${missing.join('، ')}`
        if (s !== step.value) step.value = s
        await nextTick()
        await formRef.value?.validate()
        scrollToFirstError()
        return
      }
    }
  }
  stepNotice.value = ''
  step.value = target
  scrollToTop()
}

const nextStep = () => { if (step.value < 4) goToStep(step.value + 1) }
const prevStep = () => { if (step.value > 1) goToStep(step.value - 1) }

const handleSave = async () => {
  for (const s of [1, 2, 3]) {
    if (blockingMissing(s).length) {
      await goToStep(4) // يوقف عند أول خطوة ناقصة ويعرض الرسالة
      return
    }
  }
  stepNotice.value = ''
  await submit()
}

// ملخص المراجعة قبل الحفظ
const governorateName = computed(() => governorates.value.find(g => g.id === form.value.governorate_id)?.name ?? '')
const cityName = computed(() => citiesForGovernorate(form.value.governorate_id).find(c => c.id === form.value.city_id)?.name ?? '')
const reviewItems = computed(() => [
  { step: 1, label: 'اسم المنشأة', value: form.value.name },
  { step: 1, label: 'رقم العضوية', value: form.value.membership_number },
  { step: 1, label: 'السجل التجاري', value: form.value.commercial_register },
  { step: 2, label: 'المفوض بالتوقيع', value: form.value.authorized_person },
  { step: 2, label: 'عدد الشركاء', value: form.value.partners.length ? String(form.value.partners.length) : '' },
  { step: 3, label: 'الجوال', value: form.value.phone ? `+970${form.value.phone}` : '', ltr: true },
  { step: 3, label: 'العنوان', value: [governorateName.value, cityName.value, form.value.district].filter(Boolean).join(' - ') },
  { step: 3, label: 'عدد المجالات', value: String(form.value.specialties.filter(sp => sp.field_lk_type).length) },
])

const uploadProgress = ref(0)

const submit = async () => {
  // فحص المجموع قبل أي رفع (TASK-16 #3): المتصفح يرفع الجسم كاملاً ثم يُسقطه PHP
  // لتجاوزه post_max_size — فبدون هذا الفحص ينتظر المستخدم رفع كل الملفات ثم يفشل.
  if (attachmentsTooLarge.value) {
    errorMsg.value = `مجموع أحجام المرفقات ${totalAttachmentsMb.value.toFixed(1)} ميجابايت `
      + `ويتجاوز الحد الأقصى ${uploadLimits.value.max_post_mb} ميجابايت للطلب الواحد. `
      + 'يرجى تقليل حجم بعض الملفات قبل الحفظ.'
    step.value = 4
    scrollToTop()

    return
  }

  loading.value = true
  uploadProgress.value = 0
  errorMsg.value = ''
  validationErrors.value = {}
  try {
    const fd = new FormData()
    fd.append('_method', 'PUT') // Laravel uses PUT for file updates via POST request

    Object.entries(form.value).forEach(([k, v]) => {
      // VFileInput يُعيد [] (وليس null) عند تفريغ حقل الملف — بدون هذا الفحص كان يُرسَل
      // مصفوفة فارغة كقيمة للحقل فيرفضها الخادم بخطأ "يجب أن يكون ملفاً" رغم عدم تعديل
      // المستخدم للملف أصلاً، وهو سبب الخطأ المُبلَّغ عنه بعد فشل التحديث وحذف كل المرفقات (REQ-01 #4).
      if (Array.isArray(v) && v.length === 0 && k !== 'partners' && k !== 'specialties')
        return
      // v-model لحقل ملف مُفرَّغ (زر X) قد يصير undefined لا null فقط — v !== null وحدها
      // كانت تسمح بمرور undefined فيُرسَل كنص "undefined" حرفياً فيرفضه الخادم (سبب
      // ظهور الحقل "مطلوب" رغم أن المستخدم لم يقصد تعديل هذا المستند أصلاً).
      if (v != null) {
        if (k === 'partners' && Array.isArray(v)) {
          fd.append(k, v.join(','))
        } else if (k === 'specialties' && Array.isArray(v)) {
          fd.append(k, JSON.stringify(v))
        } else if (!(v instanceof File) || v.size > 0) {
          fd.append(k, v as any)
        }
      }
    })
    // المستندات المحدَّدة للحذف — قائمة صريحة، لا "قيمة فارغة تعني الحذف": VFileInput
    // يُرجع [] عند تفريغه والفحص أعلاه يتخطّاها عمداً (إصلاح REQ-01 #4).
    documentsToRemove.value.forEach(key => {
      // رفعُ بديل لنفس الحقل يُلغي علامة الحذف — الخادم يُرجّح الرفع أيضاً، لكن عدم
      // إرسالها أصلاً أوضح من الاتّكال على ترتيب المعالجة هناك.
      if (!hasNewFile(key))
        fd.append('remove_documents[]', key)
    })

    await api.post(`/api/v1/contractors/${route.params.id}`, fd, {
      headers: { 'Content-Type': 'multipart/form-data' },
      timeout: UPLOAD_TIMEOUT_MS,
      onUploadProgress: e => {
        uploadProgress.value = e.total ? Math.round((e.loaded / e.total) * 100) : 0
      },
    })
    router.push({ name: 'contractors' })
  }
  catch (err: any) {
    // 413 = أسقط PHP جسم الطلب لتجاوزه post_max_size؛ رسالة الخادم تشرح الحد
    // بدقة، والرسالة العامة هنا كانت تُخفي أن السبب هو الحجم لا المدخلات (TASK-16 #3).
    if (err?.response?.status === 413) {
      errorMsg.value = err?.response?.data?.message
        || 'حجم المرفقات يتجاوز الحد الأقصى المسموح به. يرجى تقليل حجم الملفات أو رفعها على دفعات.'
      step.value = 4
      scrollToTop()

      return
    }

    errorMsg.value = describeRequestError(err, 'فشل تحديث المقاول. يرجى التحقق من المدخلات.')

    const errors = err?.response?.data?.errors
    if (errors) {
      validationErrors.value = errors
      // الانتقال لأول خطوة فيها خطأ
      step.value = Math.min(...Object.keys(errors).map(stepOfField))
    }
    scrollToTop()
  }
  finally {
    loading.value = false
  }
}
</script>

<template>
  <div
    v-if="!initLoading"
    class="contractor-wizard"
  >
    <!-- العنوان -->
    <div class="d-flex align-start justify-space-between flex-wrap gap-4 mb-6">
      <div>
        <h1 class="text-h4 font-weight-bold mb-1">
          تعديل بيانات المقاول
        </h1>
        <p class="text-body-1 text-medium-emphasis mb-0">
          <template v-if="form.name">
            <span class="font-weight-medium text-high-emphasis">{{ form.name }}</span>
            <span v-if="form.membership_number"> · رقم العضوية {{ form.membership_number }}</span>
            <br>
          </template>
          حدّث البيانات على أربع خطوات. الوثائق القديمة تبقى كما هي إن لم ترفع بديلاً لها.
        </p>
      </div>
      <VBtn
        variant="tonal"
        prepend-icon="tabler-list"
        :to="{ name: 'contractors' }"
      >
        قائمة المقاولين
      </VBtn>
    </div>

    <ContractorWizardStepper
      :steps="steps"
      :step="step"
      :max-reached="maxReached"
      :step-state="stepState"
      @go="goToStep"
    />

    <VAlert
      v-if="errorMsg"
      type="error"
      variant="tonal"
      class="mb-4"
      closable
      @click:close="errorMsg = ''"
    >
      {{ errorMsg }}
    </VAlert>
    <VAlert
      v-if="stepNotice"
      type="warning"
      variant="tonal"
      class="mb-4"
      closable
      @click:close="stepNotice = ''"
    >
      {{ stepNotice }}
    </VAlert>

    <VForm
      ref="formRef"
      validate-on="blur lazy"
      @submit.prevent
    >
      <!-- Step 1: معلومات العضوية والتأسيس -->
      <VCard v-if="step === 1">
        <VCardText class="pa-6">
          <div class="section-head">
            <VAvatar
              color="primary"
              variant="tonal"
              size="36"
              rounded
            >
              <VIcon
                icon="tabler-id-badge-2"
                size="20"
              />
            </VAvatar>
            <div>
              <h2>معلومات العضوية</h2>
              <p>رقم العضوية بالاتحاد وتاريخ التسجيل.</p>
            </div>
          </div>

          <div class="membership-box mb-8">
            <VRow>
              <VCol
                cols="12"
                sm="6"
              >
                <VTextField
                  v-model="form.membership_number"
                  label="رقم العضوية بالاتحاد *"
                  readonly
                  prepend-inner-icon="tabler-lock"
                  hint="رقم العضوية ثابت ولا يُعدَّل من هنا"
                  persistent-hint
                  :error-messages="validationErrors.membership_number"
                  :rules="[
                    v => !!v || 'رقم العضوية مطلوب',
                    v => {
                      // كل الأرقام بصيغة _g — الرقم بدون لاحقة يُكمَل تلقائياً في الخادم
                      if (/^[0-9]+(_g)?$/.test(v)) return parseInt(v) >= 1 || 'رقم العضوية يجب أن يكون 1 أو أكثر'
                      return 'صيغة غير صحيحة (مثال: 184_g)'
                    }
                  ]"
                />
              </VCol>
              <VCol
                cols="12"
                sm="6"
              >
                <VTextField
                  v-model="form.registration_date"
                  label="تاريخ التسجيل"
                  type="date"
                  :error-messages="validationErrors.registration_date"
                />
              </VCol>
            </VRow>
          </div>

          <div class="section-head">
            <VAvatar
              color="primary"
              variant="tonal"
              size="36"
              rounded
            >
              <VIcon
                icon="tabler-building"
                size="20"
              />
            </VAvatar>
            <div>
              <h2>بيانات المنشأة</h2>
              <p>الاسم الرسمي والسجل التجاري والشكل القانوني.</p>
            </div>
          </div>
          <VRow>
            <VCol
              cols="12"
              md="8"
            >
              <VTextField
                v-model="form.name"
                label="اسم طالب الانتساب (الشركة / المؤسسة) *"
                :rules="[v => !!v || 'اسم طالب الانتساب مطلوب']"
                :error-messages="validationErrors.name"
              />
            </VCol>
            <VCol
              cols="12"
              md="4"
            >
              <VTextField
                v-model="form.commercial_register"
                label="رقم السجل التجاري *"
                inputmode="numeric"
                maxlength="9"
                :error-messages="validationErrors.commercial_register"
                :rules="[
                  v => !!v || 'رقم السجل التجاري مطلوب',
                  v => /^[0-9]{9}$/.test(v) || 'يجب أن يتكون من 9 أرقام بالضبط'
                ]"
              />
            </VCol>
            <VCol
              cols="12"
              md="6"
            >
              <VTextField
                v-model="form.legal_form"
                label="الشكل القانوني للشركة"
                placeholder="مثال: مساهمة، تضامن، إلخ"
                :error-messages="validationErrors.legal_form"
              />
            </VCol>
            <VCol
              cols="12"
              md="6"
            >
              <VTextField
                v-model="form.capital"
                label="رأس مال الشركة"
                :error-messages="validationErrors.capital"
              />
            </VCol>
            <VCol cols="12">
              <VTextarea
                v-model="form.company_purposes"
                label="غايات الشركة"
                rows="3"
                auto-grow
                :error-messages="validationErrors.company_purposes"
              />
            </VCol>
          </VRow>
        </VCardText>
      </VCard>

      <!-- Step 2: بيانات الإدارة والشركاء -->
      <VCard v-if="step === 2">
        <VCardText class="pa-6">
          <div class="section-head">
            <VAvatar
              color="primary"
              variant="tonal"
              size="36"
              rounded
            >
              <VIcon
                icon="tabler-user-check"
                size="20"
              />
            </VAvatar>
            <div>
              <h2>صاحب المنشأة والشركاء</h2>
              <p>أضف اسم كل شريك ثم اضغط «إضافة» أو Enter.</p>
            </div>
          </div>
          <VRow class="mb-6">
            <VCol
              cols="12"
              md="6"
            >
              <VTextField
                v-model="form.owner_name"
                label="اسم صاحب المنشأة"
                :error-messages="validationErrors.owner_name"
              />
            </VCol>
            <VCol
              cols="12"
              md="6"
            >
              <div class="d-flex align-start gap-2">
                <VTextField
                  v-model="newPartnerName"
                  label="اسم شريك"
                  placeholder="اكتب الاسم واضغط Enter"
                  :error-messages="validationErrors.partners"
                  @keydown.enter.prevent="addPartner"
                />
                <VBtn
                  color="primary"
                  variant="tonal"
                  height="48"
                  prepend-icon="tabler-plus"
                  :disabled="!newPartnerName.trim()"
                  @click="addPartner"
                >
                  إضافة
                </VBtn>
              </div>
            </VCol>
            <VCol cols="12">
              <div
                v-if="form.partners && form.partners.length > 0"
                class="d-flex flex-wrap gap-2"
              >
                <VChip
                  v-for="(partner, index) in form.partners"
                  :key="index"
                  closable
                  color="primary"
                  variant="tonal"
                  prepend-icon="tabler-user"
                  @click:close="removePartner(index)"
                >
                  {{ partner }}
                </VChip>
              </div>
              <div
                v-else
                class="empty-hint"
              >
                <VIcon
                  icon="tabler-user-plus"
                  size="18"
                />
                لم تُضف أي شريك بعد
              </div>
            </VCol>
          </VRow>

          <div class="section-head">
            <VAvatar
              color="primary"
              variant="tonal"
              size="36"
              rounded
            >
              <VIcon
                icon="tabler-signature"
                size="20"
              />
            </VAvatar>
            <div>
              <h2>المفوض بالتوقيع</h2>
              <p>الشخص المعتمد للتوقيع عن المنشأة وبيانات التواصل معه.</p>
            </div>
          </div>
          <VRow>
            <VCol
              cols="12"
              md="6"
            >
              <VTextField
                v-model="form.authorized_person"
                label="اسم المفوض بالتوقيع"
                :error-messages="validationErrors.authorized_person"
              />
            </VCol>
            <VCol
              cols="12"
              md="6"
            >
              <VTextField
                v-model="form.authorized_person_id_number"
                label="رقم هوية المفوض"
                inputmode="numeric"
                :error-messages="validationErrors.authorized_person_id_number"
              />
            </VCol>
            <VCol
              cols="12"
              md="6"
            >
              <VTextField
                v-model="form.authorized_person_phone"
                label="رقم جوال المفوض"
                inputmode="tel"
                prepend-inner-icon="tabler-phone"
                :error-messages="validationErrors.authorized_person_phone"
              />
            </VCol>
            <VCol
              cols="12"
              md="6"
            >
              <VTextField
                v-model="form.authorized_person_whatsapp"
                label="رقم الواتساب للمفوض"
                inputmode="tel"
                prepend-inner-icon="tabler-brand-whatsapp"
                :error-messages="validationErrors.authorized_person_whatsapp"
              />
            </VCol>
          </VRow>
        </VCardText>
      </VCard>

      <!-- Step 3: العنوان وبيانات الاتصال -->
      <VCard v-if="step === 3">
        <VCardText class="pa-6">
          <div class="section-head">
            <VAvatar
              color="primary"
              variant="tonal"
              size="36"
              rounded
            >
              <VIcon
                icon="tabler-phone"
                size="20"
              />
            </VAvatar>
            <div>
              <h2>بيانات الاتصال</h2>
              <p>رقم الجوال يُستخدم للتواصل مع المقاول.</p>
            </div>
          </div>
          <VRow class="mb-6">
            <VCol
              cols="12"
              md="4"
            >
              <VTextField
                v-model="form.phone"
                label="رقم الجوال *"
                prefix="+970"
                placeholder="59XXXXXXX"
                inputmode="numeric"
                maxlength="9"
                :error-messages="validationErrors.phone"
                :rules="[v => !!v || 'رقم الجوال مطلوب', v => /^[0-9]{9}$/.test(v) || 'يجب إدخال 9 أرقام (مثال: 599123456)']"
              />
            </VCol>
            <VCol
              cols="12"
              md="4"
            >
              <VTextField
                v-model="form.fax"
                label="الهاتف / الفاكس"
                :error-messages="validationErrors.fax"
              />
            </VCol>
            <VCol
              cols="12"
              md="4"
            >
              <VTextField
                v-model="form.email"
                label="البريد الإلكتروني"
                type="email"
                :error-messages="validationErrors.email"
              />
            </VCol>
          </VRow>

          <div class="section-head">
            <VAvatar
              color="primary"
              variant="tonal"
              size="36"
              rounded
            >
              <VIcon
                icon="tabler-map-pin"
                size="20"
              />
            </VAvatar>
            <div>
              <h2>العنوان</h2>
              <p>اختر المحافظة أولاً لتظهر مدنها.</p>
            </div>
          </div>
          <VRow class="mb-6">
            <VCol
              cols="12"
              sm="6"
              md="4"
            >
              <VSelect
                v-model="form.governorate_id"
                :items="governorates"
                item-title="name"
                item-value="id"
                label="المحافظة *"
                :error-messages="validationErrors.governorate_id"
                :rules="[v => !!v || 'المحافظة مطلوبة']"
              />
            </VCol>
            <VCol
              cols="12"
              sm="6"
              md="4"
            >
              <VSelect
                v-model="form.city_id"
                :items="citiesForGovernorate(form.governorate_id)"
                item-title="name"
                item-value="id"
                label="المدينة *"
                :disabled="!form.governorate_id"
                :error-messages="validationErrors.city_id"
                :rules="[v => !!v || 'المدينة مطلوبة']"
              />
            </VCol>
            <VCol
              cols="12"
              md="4"
            >
              <VTextField
                v-model="form.district"
                label="الحي *"
                :error-messages="validationErrors.district"
                :rules="[v => !!v || 'الحي مطلوب']"
              />
            </VCol>
            <VCol
              cols="12"
              md="6"
            >
              <VTextField
                v-model="form.address"
                label="العنوان التفصيلي (الشارع) *"
                :error-messages="validationErrors.address"
                :rules="[v => !!v || 'العنوان التفصيلي مطلوب']"
              />
            </VCol>
            <VCol
              cols="6"
              md="3"
            >
              <VTextField
                v-model="form.building"
                label="العمارة *"
                :error-messages="validationErrors.building"
                :rules="[v => !!v || 'العمارة مطلوبة']"
              />
            </VCol>
            <VCol
              cols="6"
              md="3"
            >
              <VTextField
                v-model="form.floor"
                label="الطابق *"
                :error-messages="validationErrors.floor"
                :rules="[v => !!v || 'الطابق مطلوب']"
              />
            </VCol>
          </VRow>

          <div class="section-head">
            <VAvatar
              color="primary"
              variant="tonal"
              size="36"
              rounded
            >
              <VIcon
                icon="tabler-certificate"
                size="20"
              />
            </VAvatar>
            <div>
              <h2>الترخيص والتأسيس</h2>
              <p>رخصة البلدية وتاريخ التأسيس والتصنيف العام.</p>
            </div>
          </div>
          <VRow class="mb-6">
            <VCol
              cols="12"
              md="4"
            >
              <VTextField
                v-model="form.license_number"
                label="رقم رخصة البلدية *"
                inputmode="numeric"
                maxlength="5"
                :error-messages="validationErrors.license_number"
                :rules="[
                  v => !!v || 'رقم رخصة البلدية مطلوب',
                  v => /^[0-9]{5}$/.test(v) || 'يجب أن يتكون من 5 أرقام بالضبط'
                ]"
              />
            </VCol>
            <VCol
              cols="12"
              md="4"
            >
              <VTextField
                v-model="form.established_date"
                label="تاريخ التأسيس *"
                type="date"
                :error-messages="validationErrors.established_date"
                :rules="[v => !!v || 'تاريخ التأسيس مطلوب']"
              />
            </VCol>
            <VCol
              cols="12"
              md="4"
            >
              <VSelect
                v-model="form.classification"
                :items="overallGradeOptions"
                item-title="title"
                item-value="value"
                label="التصنيف العام"
                clearable
                hint="يقدر المقاول يعدّله من التطبيق أيضاً"
                persistent-hint
                :error-messages="validationErrors.classification"
              />
            </VCol>
          </VRow>

          <!-- التخصصات والتصنيفات المتعددة -->
          <div class="section-head">
            <VAvatar
              color="primary"
              variant="tonal"
              size="36"
              rounded
            >
              <VIcon
                icon="tabler-category"
                size="20"
              />
            </VAvatar>
            <div>
              <h2>مجالات وتصنيفات المقاول</h2>
              <p>لكل مجال تخصص وتصنيف — أضف أكثر من مجال إذا لزم.</p>
            </div>
          </div>
          <div
            v-for="(spec, index) in form.specialties"
            :key="index"
            class="specialty-row"
          >
            <div class="d-flex align-center justify-space-between mb-3">
              <VChip
                size="small"
                color="primary"
                variant="tonal"
                label
              >
                المجال {{ index + 1 }}
              </VChip>
              <VBtn
                icon
                size="small"
                variant="text"
                color="error"
                title="حذف هذا المجال"
                @click="confirmRemoveSpecialty(index)"
              >
                <VIcon icon="tabler-trash" />
              </VBtn>
            </div>
            <VRow>
              <VCol
                cols="12"
                md="4"
              >
                <VSelect
                  v-model="spec.field_lk_type"
                  :items="fieldOptions"
                  item-title="title"
                  item-value="value"
                  label="المجال *"
                  :rules="[v => !!v || 'المجال مطلوب']"
                  @update:model-value="spec.specialization_lk_type = null"
                />
              </VCol>
              <VCol
                cols="12"
                md="4"
              >
                <VSelect
                  v-model="spec.specialization_lk_type"
                  :items="specializationOptionsFor(spec.field_lk_type)"
                  item-title="title"
                  item-value="value"
                  label="التخصص *"
                  :rules="[v => !!v || 'التخصص مطلوب']"
                />
              </VCol>
              <VCol
                cols="12"
                md="4"
              >
                <VSelect
                  v-model="spec.classification"
                  :items="gradeOptionsFor(spec.field_lk_type)"
                  item-title="title"
                  item-value="value"
                  label="تصنيف المقاول لهذا المجال *"
                  :rules="[v => !!v || 'التصنيف مطلوب']"
                />
              </VCol>
            </VRow>
          </div>
          <div
            v-if="!form.specialties.length"
            class="empty-hint mb-4"
          >
            <VIcon
              icon="tabler-category"
              size="18"
            />
            لا يوجد مجال/تصنيف مضاف
          </div>
          <VBtn
            prepend-icon="tabler-plus"
            variant="tonal"
            color="primary"
            @click="addSpecialty"
          >
            إضافة مجال/تصنيف آخر
          </VBtn>
          <VAlert
            v-if="validationErrors.specialties"
            type="error"
            variant="tonal"
            class="mt-4"
            density="compact"
          >
            {{ validationErrors.specialties[0] }}
          </VAlert>
        </VCardText>
      </VCard>


      <!-- Step 4: الوثائق والمستندات + المراجعة -->
      <template v-if="step === 4">
        <VCard class="mb-6">
          <VCardText class="pa-6">
            <div class="d-flex align-start justify-space-between flex-wrap gap-3 mb-2">
              <div class="section-head mb-0">
                <VAvatar
                  color="primary"
                  variant="tonal"
                  size="36"
                  rounded
                >
                  <VIcon
                    icon="tabler-files"
                    size="20"
                  />
                </VAvatar>
                <div>
                  <h2>الوثائق والمستندات</h2>
                  <p>ارفع ملفاً جديداً فقط إذا أردت استبدال المستند الحالي. {{ fileHint }}</p>
                </div>
              </div>
              <VChip
                :color="presentDocsCount === documentKeys.length ? 'success' : 'primary'"
                variant="tonal"
                label
                prepend-icon="tabler-paperclip"
              >
                مرفوع {{ presentDocsCount }} من {{ documentKeys.length }}
              </VChip>
            </div>
            <VProgressLinear
              :model-value="presentDocsCount / documentKeys.length * 100"
              :color="presentDocsCount === documentKeys.length ? 'success' : 'primary'"
              rounded
              height="6"
              class="mb-6"
            />

            <VRow>
              <VCol
                v-for="key in documentKeys"
                :key="key"
                cols="12"
                md="6"
              >
                <div
                  class="doc-tile"
                  :class="{
                    'doc-tile--done': docState(key) === 'new',
                    'doc-tile--existing': docState(key) === 'existing',
                    'doc-tile--error': validationErrors[key] || docState(key) === 'removed',
                  }"
                >
                  <div class="doc-tile__head">
                    <VAvatar
                      :color="{ new: 'success', existing: 'primary', removed: 'error', empty: undefined }[docState(key)]"
                      variant="tonal"
                      size="36"
                      rounded
                    >
                      <VIcon
                        :icon="{ new: 'tabler-circle-check', existing: 'tabler-paperclip', removed: 'tabler-file-off', empty: 'tabler-file-text' }[docState(key)]"
                        size="20"
                      />
                    </VAvatar>
                    <div class="doc-tile__label">
                      {{ contractorDocumentLabels[key] }}
                      <div
                        v-if="docState(key) === 'new'"
                        class="text-caption text-success text-truncate"
                      >
                        {{ newFileName(key) }}{{ existingFiles[key] ? ' — سيستبدل الملف الحالي' : '' }}
                      </div>
                      <div
                        v-else-if="docState(key) === 'removed'"
                        class="text-caption text-error"
                      >
                        سيُحذف عند الحفظ
                      </div>
                      <div
                        v-else-if="docState(key) === 'existing'"
                        class="text-caption text-primary"
                      >
                        مرفوع حالياً
                      </div>
                      <div
                        v-else
                        class="text-caption text-disabled"
                      >
                        غير مرفوع
                      </div>
                    </div>
                    <template v-if="docState(key) === 'existing'">
                      <VBtn
                        icon="tabler-eye"
                        size="small"
                        variant="text"
                        color="primary"
                        title="عرض الملف الحالي"
                        :href="existingFiles[key]!"
                        target="_blank"
                        rel="noopener noreferrer"
                      />
                      <VBtn
                        icon="tabler-trash"
                        size="small"
                        variant="text"
                        color="error"
                        title="حذف المستند"
                        @click="confirmRemoveDocument(key)"
                      />
                    </template>
                    <VBtn
                      v-else-if="docState(key) === 'removed'"
                      size="small"
                      variant="tonal"
                      prepend-icon="tabler-refresh"
                      @click="undoRemoveDocument(key)"
                    >
                      تراجع
                    </VBtn>
                  </div>
                  <VFileInput
                    v-model="formDocs[key]"
                    :aria-label="contractorDocumentLabels[key]"
                    accept=".pdf,.doc,.docx,image/*"
                    density="compact"
                    prepend-icon=""
                    prepend-inner-icon="tabler-cloud-upload"
                    :placeholder="existingFiles[key] ? 'اختر ملفاً جديداً للاستبدال' : 'اختر الملف أو اسحبه هنا'"
                    persistent-placeholder
                    :color="hasNewFile(key) ? 'success' : 'primary'"
                    :error-messages="validationErrors[key]"
                    :rules="[fileSizeRule]"
                  />
                </div>
              </VCol>
            </VRow>

            <div
              v-if="uploadLimits.max_post_mb"
              class="text-body-2 mt-4"
              :class="attachmentsTooLarge ? 'text-error' : 'text-medium-emphasis'"
            >
              <VIcon
                :icon="attachmentsTooLarge ? 'tabler-alert-triangle' : 'tabler-database'"
                size="16"
              />
              مجموع الملفات الجديدة {{ totalAttachmentsMb.toFixed(1) }} من {{ uploadLimits.max_post_mb }} ميجابايت مسموحة للطلب الواحد
            </div>

            <VTextarea
              v-model="form.notes"
              label="ملاحظات إضافية للمراجعة"
              rows="3"
              auto-grow
              class="mt-6"
            />
          </VCardText>
        </VCard>

        <!-- ملخص المراجعة -->
        <VCard>
          <VCardText class="pa-6">
            <div class="section-head">
              <VAvatar
                color="primary"
                variant="tonal"
                size="36"
                rounded
              >
                <VIcon
                  icon="tabler-clipboard-check"
                  size="20"
                />
              </VAvatar>
              <div>
                <h2>مراجعة قبل الحفظ</h2>
                <p>تأكد من البيانات الأساسية — اضغط على أي خطوة لتعديلها.</p>
              </div>
            </div>
            <div class="review-grid">
              <div
                v-for="item in reviewItems"
                :key="item.label"
                class="review-item"
              >
                <div class="text-caption text-medium-emphasis">
                  {{ item.label }}
                </div>
                <div
                  class="text-body-1 font-weight-medium"
                  :class="{ 'text-disabled': !item.value }"
                  :dir="item.ltr && item.value ? 'ltr' : undefined"
                  :style="item.ltr ? 'text-align: end' : undefined"
                >
                  {{ item.value || 'غير مُدخل' }}
                </div>
                <VBtn
                  variant="text"
                  size="x-small"
                  color="primary"
                  class="review-edit"
                  @click="goToStep(item.step)"
                >
                  تعديل
                </VBtn>
              </div>
            </div>
          </VCardText>
        </VCard>
      </template>
    </VForm>

    <!-- شريط الأزرار السفلي الثابت -->
    <div class="wizard-actions">
      <VCard elevation="6">
        <VCardText class="d-flex align-center gap-3 py-3 px-4">
          <VBtn
            v-if="step > 1"
            variant="tonal"
            prepend-icon="tabler-arrow-right"
            @click="prevStep"
          >
            السابق
          </VBtn>
          <div class="flex-grow-1 wizard-progress">
            <template v-if="loading">
              <div class="d-flex justify-space-between text-caption mb-1">
                <span class="font-weight-medium">جاري حفظ التعديلات…</span>
                <span class="text-medium-emphasis">{{ uploadProgress }}%</span>
              </div>
              <VProgressLinear
                :model-value="uploadProgress"
                color="success"
                rounded
                height="6"
              />
            </template>
            <template v-else>
              <div class="d-flex justify-space-between text-caption mb-1">
                <span class="font-weight-medium">الخطوة {{ step }} من {{ steps.length }}</span>
                <span class="text-medium-emphasis">اكتمال الملف {{ overallProgress }}%</span>
              </div>
              <VProgressLinear
                :model-value="overallProgress"
                color="primary"
                rounded
                height="6"
              />
            </template>
          </div>
          <VBtn
            v-if="step < steps.length"
            variant="tonal"
            color="success"
            :loading="loading"
            prepend-icon="tabler-device-floppy"
            title="حفظ التعديلات"
            @click="handleSave"
          >
            حفظ
          </VBtn>
          <VBtn
            v-if="step < steps.length"
            color="primary"
            append-icon="tabler-arrow-left"
            @click="nextStep"
          >
            التالي
          </VBtn>
          <VBtn
            v-else
            color="success"
            :loading="loading"
            prepend-icon="tabler-device-floppy"
            @click="handleSave"
          >
            حفظ التعديلات
          </VBtn>
        </VCardText>
      </VCard>
    </div>

    <!-- Confirm remove specialty dialog -->
    <VDialog
      v-model="removeSpecialtyDialog"
      max-width="400"
    >
      <VCard>
        <VCardTitle class="d-flex align-center gap-2">
          <VIcon
            icon="tabler-alert-triangle"
            color="error"
          />
          تأكيد الحذف
        </VCardTitle>
        <VCardText>
          هل أنت متأكد من حذف هذا المجال والتصنيف؟ لا يمكن التراجع عن هذا الإجراء.
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn
            variant="tonal"
            @click="removeSpecialtyDialog = false"
          >
            إلغاء
          </VBtn>
          <VBtn
            color="error"
            @click="removeSpecialty"
          >
            حذف
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- تأكيد حذف مستند (TASK-16 #4) — الحذف الفعلي يقع عند الحفظ لا الآن -->
    <VDialog
      v-model="removeDocumentDialog"
      max-width="440"
    >
      <VCard>
        <VCardTitle class="d-flex align-center gap-2">
          <VIcon
            icon="tabler-alert-triangle"
            color="error"
          />
          تأكيد حذف المستند
        </VCardTitle>
        <VCardText>
          سيُحذف مستند «{{ documentLabels[removingDocumentKey ?? ''] ?? '' }}» نهائياً من الخادم عند حفظ التعديلات،
          ولا يمكن استرجاعه بعدها. يمكنك التراجع قبل الحفظ.
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn
            variant="tonal"
            @click="removeDocumentDialog = false"
          >
            إلغاء
          </VBtn>
          <VBtn
            color="error"
            @click="removeDocument"
          >
            تحديد للحذف
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
  <div
    v-else
    class="d-flex justify-center align-center h-100 mt-10"
  >
    <VProgressCircular
      indeterminate
      color="primary"
    />
  </div>
</template>

<style scoped src="@/views/contractors/contractor-wizard.css"></style>

<script setup lang="ts">
import { ref, onMounted, onUnmounted, computed, watch } from 'vue'
import { useRouter } from 'vue-router'
import axios from 'axios'
import PublicNavbar from '@/components/PublicNavbar.vue'
import {
  User, ClipboardList, CreditCard, FileText,
  Award, Phone, Mail, Building2,
  CalendarDays, CheckCircle, AlertCircle, Clock,
  Download, RefreshCw, ChevronRight, Wallet,
  FileCheck, FileClock, MessageSquare, Truck, HelpCircle, Camera,
  Pencil, X, UploadCloud, Save, Paperclip, Check,
} from 'lucide-vue-next'

definePage({
  meta: { layout: 'pure', public: true, unauthenticatedOnly: false },
})

const router = useRouter()

// ─── Auth ───
const token = ref<string | null>(null)
const authError = ref(false)

function getToken() { return localStorage.getItem('contractor_token') }
function apiHeaders() { return { Authorization: `Bearer ${token.value}` } }

function logout() {
  localStorage.removeItem('contractor_token')
  router.push('/landing')
}

onMounted(() => window.addEventListener('contractor-logged-out', logout))
onUnmounted(() => window.removeEventListener('contractor-logged-out', logout))

// ─── Data ───
interface Contractor {
  id: number; name: string; membership_number: string
  commercial_register: string; authorized_person: string
  trade: string; classification: string
  email: string; phone: string; city: string; address: string
  status: string; is_frozen: boolean
}
interface Membership {
  id: number; type: string; status: string
  starts_at: string; expires_at: string | null
  amount: string; notes: string | null; expiring_soon: boolean
}
interface Payment {
  id: number; amount: string; type: string; status: string
  method: string; reference_number: string | null
  notes: string | null; paid_at: string | null; created_at: string
  membership: { type: string; expires_at: string | null } | null
}
interface Document {
  id: number; title: string; type: string
  url: string; mime_type: string; formatted_size: string; created_at: string
}
interface Stats {
  total_payments: string; pending_payments: number; total_documents: number;
  total_equipment: number; certificate_requests: number; open_tickets: number;
}

interface AccountStatusBanner {
  type: string; severity: 'error' | 'warning' | 'success'
  message: string; cta: string | null; cta_label: string | null
}
interface AccountStatus {
  banner: AccountStatusBanner | null
  membership: { badge: string; expires_at: string | null }
  dues: { has_pending: boolean; outstanding_amount: number; badge: string | null }
  certificates: { locked: boolean; lock_reason: string | null }
  equipment_subscription: { active: boolean; badge: string; expires_at: string | null }
}
const accountStatus = ref<AccountStatus | null>(null)

const contractor  = ref<Contractor | null>(null)
const membership  = ref<Membership | null>(null)
const memberships = ref<Membership[]>([])
const payments    = ref<Payment[]>([])
const documents   = ref<Document[]>([])
const stats       = ref<Stats | null>(null)

const isLoading   = ref(true)
const activeTab   = ref<'profile' | 'membership' | 'payments' | 'documents' | 'tenders' | 'news'>('profile')

// إشعار popup بعد تفعيل الحساب (قادم من صفحة التسجيل)
const showActivatedPopup = ref(false)

const tabs = [
  { id: 'profile',    label: 'حسابي',            icon: User },
  { id: 'membership', label: 'العضوية والشهادات',icon: Award },
  { id: 'payments',   label: 'المعاملات المالية', icon: CreditCard },
  { id: 'documents',  label: 'الوثائق',          icon: FileText },
  { id: 'tenders',    label: 'العطاءات',         icon: ClipboardList },
  { id: 'news',       label: 'الأخبار',          icon: MessageSquare },
]

// ─── Fetch ───
const BASE = import.meta.env.VITE_API_BASE_URL ?? ''

async function fetchDashboard() {
  try {
    // timeout 20s — بلا مهلة يعلّق التحميل للأبد لو تجمّد الاتصال بالسيرفر
    const r = await axios.get(`${BASE}/api/v1/contractor/dashboard`, { headers: apiHeaders(), timeout: 20000 })
    contractor.value = r.data.items.contractor
    membership.value = r.data.items.membership
    stats.value      = r.data.items.stats
  } catch (e: any) {
    if (e?.response?.status === 401) authError.value = true
  }
}

async function fetchTab(tab: typeof activeTab.value) {
  activeTab.value = tab
  if (tab === 'membership' && !memberships.value.length) {
    const r = await axios.get(`${BASE}/api/v1/contractor/memberships`, { headers: apiHeaders() })
    memberships.value = r.data.items
  }
  if (tab === 'payments' && !payments.value.length) {
    const r = await axios.get(`${BASE}/api/v1/contractor/payments`, { headers: apiHeaders() })
    payments.value = r.data.items
  }
  if (tab === 'documents' && !documents.value.length) {
    const r = await axios.get(`${BASE}/api/v1/contractor/documents`, { headers: apiHeaders() })
    documents.value = r.data.items ?? []
  }
  if ((tab === 'tenders' || tab === 'news') && !latestTenders.value.length && !latestNews.value.length)
    await fetchPublicFeeds()
}

// ─── Financial ───
interface DueRow {
  id: number; year: number | null; description: string; amount_jod: string; paid_jod: string;
  remaining_jod: number; status: string; status_label: string;
}
const dues = ref<DueRow[]>([])
const outstandingDues = ref(0)
const duesTotalJod = ref(0)
const duesPaidJod = ref(0)
const duesPaidPercentage = ref(0)
const duesCounts = ref({ unpaid: 0, pending_review: 0 })

interface PendingDuesPayment {
  id: number; description: string; amount: string; currency: string
  reference_number: string | null; receipt_image_url: string | null; submitted_at: string | null
}
const pendingDuesPayments = ref<PendingDuesPayment[]>([])

interface Obligation {
  date: string | null; description: string; type: 'payment' | 'penalty' | 'due'
  direction: string; amount: string; status: string; reference: string | null
}
const obligations = ref<Obligation[]>([])
const obligationTypeLabel: Record<string, string> = {
  payment: 'دفعة معلّقة', penalty: 'غرامة تأخير', due: 'ذمة مالية سابقة',
}

async function fetchFinancial() {
  try {
    const r = await axios.get(`${BASE}/api/v1/contractor/financial`, { headers: apiHeaders() })
    const items = r.data.items
    dues.value = items?.dues ?? []
    outstandingDues.value = Number(items?.summary?.outstanding_dues_jod ?? 0)
    obligations.value = items?.obligations ?? []
    duesTotalJod.value = Number(items?.summary?.dues_total_jod ?? 0)
    duesPaidJod.value = Number(items?.summary?.dues_paid_jod ?? 0)
    duesPaidPercentage.value = Number(items?.summary?.dues_paid_percentage ?? 0)
    duesCounts.value = items?.dues_counts ?? { unpaid: 0, pending_review: 0 }
    pendingDuesPayments.value = items?.pending_dues_payments ?? []
  } catch {}
}

// ─── Profile Edit — بيانات إضافية وملفات المطابقة لمتطلبات تسجيل المقاول في لوحة الأدمن ───
interface ProfileExtra {
  owner_name: string | null; fax: string | null; capital: string | null
  registration_date: string | null; legal_form: string | null; company_purposes: string | null
  notes: string | null; license_number: string | null
  governorate: { id: number; name: string } | null
  city: { id: number; name: string } | null
}
interface FieldSpecialization {
  spec_id: number | null; spec_name: string
  grade: string | null; grade_label: string | null; grade_level: number | null
}
interface FieldGroup { field_id: number | null; field_name: string; specializations: FieldSpecialization[] }
const contractorFields = ref<FieldGroup[]>([])
const gradeLevelClass: Record<number, string> = { 1: 'grade-1', 2: 'grade-2', 3: 'grade-3', 4: 'grade-4', 5: 'grade-5' }
const docFields = [
  { key: 'cr_file',                        label: 'السجل التجاري' },
  { key: 'id_file',                        label: 'الهوية' },
  { key: 'company_register',               label: 'مستخرج سجل الشركة' },
  { key: 'municipal_license',               label: 'رخصة المهن (البلدية)' },
  { key: 'bank_dealing_letter',             label: 'شهادة تعامل بنكي' },
  { key: 'articles_of_association',        label: 'عقد التأسيس' },
  { key: 'internal_bylaws',                 label: 'النظام الداخلي' },
  { key: 'lease_or_ownership_contract',     label: 'عقد الإيجار / الملكية' },
  { key: 'partners_ids',                    label: 'صور هويات الشركاء' },
  { key: 'authorization_letter',            label: 'كتاب تفويض المفوّض' },
  { key: 'company_approval_letter',         label: 'كتاب موافقة الشركة' },
  { key: 'full_time_engineer_certificate',  label: 'شهادة مهندس متفرغ' },
  { key: 'secretary_contract',              label: 'عقد سكرتير' },
] as const

const profileExtra = ref<ProfileExtra | null>(null)
const logoUrl = ref<string | null>(null)
const logoInput = ref<HTMLInputElement | null>(null)
const isUploadingLogo = ref(false)
const logoUploadProgress = ref(0)
const logoUploadDone = ref(false)
const logoError = ref('')
const fileUrls = ref<Record<string, string | null>>({})
const profileDataComplete = ref(true)
const missingProfileFields = ref<string[]>([])
const governorates = ref<{ id: number; name: string; cities: { id: number; name: string }[] }[]>([])
const editMode = ref(false)
const isSaving = ref(false)
const saveError = ref('')
const editForm = ref({
  authorized_person: '', owner_name: '', email: '', phone: '', fax: '',
  capital: '', legal_form: '', registration_date: '', company_purposes: '',
  address: '', notes: '', governorate_id: null as number | null, city_id: null as number | null,
})

// ─── محرّر التخصصات والتصنيفات ───
interface SpecRow { field_lk_type: number | null; specialization_lk_type: number | null; classification: string | null }
interface CatalogItem { id: number; name: string }
interface GradeItem { value: string; label: string; level: number | null }
const rawSpecialties = ref<SpecRow[]>([])
const editClassification = ref<string>('')
const editSpecialties = ref<SpecRow[]>([])
const catalog = ref<{ fields: CatalogItem[]; specializations: CatalogItem[]; grades: GradeItem[] }>({ fields: [], specializations: [], grades: [] })

async function fetchCatalog() {
  if (catalog.value.fields.length) return
  try {
    const r = await axios.get(`${BASE}/api/v1/app/specialties-catalog`)
    catalog.value = r.data.items
  } catch {}
}
function addSpecRow() {
  editSpecialties.value.push({ field_lk_type: null, specialization_lk_type: null, classification: null })
}
function removeSpecRow(i: number) {
  editSpecialties.value.splice(i, 1)
}
const docsSaveError = ref<Record<string, string>>({})
const docUploadingKeys = ref<Set<string>>(new Set())
const docUploadProgress = ref<Record<string, number>>({})
const docUploadedKeys = ref<Set<string>>(new Set())

const citiesForSelectedGovernorate = computed(() => {
  return governorates.value.find(g => g.id === editForm.value.governorate_id)?.cities ?? []
})

async function fetchProfile() {
  try {
    // ‎_ts يكسر كاش المتصفح/البروكسي فيرجع أحدث بيانات بعد الحفظ‎
    const r = await axios.get(`${BASE}/api/v1/contractor/auth/profile`, { headers: apiHeaders(), params: { _ts: Date.now() } })
    const items = r.data.items
    profileExtra.value = {
      owner_name: items.owner_name, fax: items.fax, capital: items.capital,
      registration_date: items.registration_date, legal_form: items.legal_form,
      company_purposes: items.company_purposes, notes: items.notes,
      license_number: items.license_number,
      governorate: items.governorate, city: items.city,
    }
    docFields.forEach(f => { fileUrls.value[f.key] = items[`${f.key}_url`] ?? null })
    accountStatus.value = items.account_status ?? null
    profileDataComplete.value = items.profile_data_complete ?? true
    missingProfileFields.value = items.missing_profile_fields ?? []
    logoUrl.value = items.logo_url ?? null
    contractorFields.value = items.fields ?? []
    rawSpecialties.value = Array.isArray(items.specialties) ? items.specialties : []
    if (contractor.value && items.classification != null) contractor.value.classification = items.classification
  } catch {}
}

async function fetchGovernorates() {
  try {
    const r = await axios.get(`${BASE}/api/v1/app/governorates`)
    governorates.value = r.data.items?.governorates ?? []
  } catch {}
}

function startEdit() {
  if (!contractor.value || !profileExtra.value) return
  editForm.value = {
    authorized_person: contractor.value.authorized_person ?? '',
    owner_name: profileExtra.value.owner_name ?? '',
    email: contractor.value.email ?? '',
    phone: contractor.value.phone ?? '',
    fax: profileExtra.value.fax ?? '',
    capital: profileExtra.value.capital ?? '',
    legal_form: profileExtra.value.legal_form ?? '',
    registration_date: profileExtra.value.registration_date ?? '',
    company_purposes: profileExtra.value.company_purposes ?? '',
    address: contractor.value.address ?? '',
    notes: profileExtra.value.notes ?? '',
    governorate_id: profileExtra.value.governorate?.id ?? null,
    city_id: profileExtra.value.city?.id ?? null,
  }
  // نسخة قابلة للتعديل من التخصصات + التصنيف العام
  editClassification.value = contractor.value.classification ?? ''
  editSpecialties.value = rawSpecialties.value.map(s => ({
    field_lk_type: s.field_lk_type ?? null,
    specialization_lk_type: s.specialization_lk_type ?? null,
    classification: s.classification ?? null,
  }))
  fetchCatalog()
  saveError.value = ''
  editMode.value = true
  if (!governorates.value.length) fetchGovernorates()
}

function cancelEdit() {
  editMode.value = false
  saveError.value = ''
}

async function onDocFileChange(key: string, e: Event) {
  const input = e.target as HTMLInputElement
  const file = input.files?.[0] ?? null
  if (!file) return

  docsSaveError.value = { ...docsSaveError.value, [key]: '' }
  docUploadedKeys.value.delete(key)
  docUploadingKeys.value.add(key)
  docUploadProgress.value = { ...docUploadProgress.value, [key]: 0 }

  try {
    const fd = new FormData()
    fd.append(key, file)
    const r = await axios.post(`${BASE}/api/v1/contractor/auth/profile/update`, fd, {
      // لا نحدّد Content-Type يدوياً: المتصفح يضبط multipart/form-data مع الـ boundary تلقائياً
      headers: apiHeaders(),
      onUploadProgress: (evt) => {
        if (evt.total) docUploadProgress.value = { ...docUploadProgress.value, [key]: Math.round((evt.loaded / evt.total) * 100) }
      },
    })
    applyProfileResponse(r.data.items)
    docUploadedKeys.value.add(key)
    setTimeout(() => docUploadedKeys.value.delete(key), 3000)
  } catch (err: any) {
    docsSaveError.value = { ...docsSaveError.value, [key]: err?.response?.data?.message || 'تعذّر رفع الملف، حاول مرة أخرى.' }
  } finally {
    docUploadingKeys.value.delete(key)
    input.value = ''
  }
}

function applyProfileResponse(items: any) {
  if (contractor.value) {
    contractor.value.name = items.name
    contractor.value.authorized_person = items.authorized_person
    contractor.value.email = items.email
    contractor.value.phone = items.phone
    contractor.value.address = items.address
    contractor.value.city = items.city?.name ?? contractor.value.city
    if (items.classification != null) contractor.value.classification = items.classification
  }
  // تحديث شجرة التخصصات + النسخة الخام بعد الحفظ
  if (items.fields !== undefined) contractorFields.value = items.fields ?? []
  if (items.specialties !== undefined) rawSpecialties.value = Array.isArray(items.specialties) ? items.specialties : []
  profileExtra.value = {
    owner_name: items.owner_name, fax: items.fax, capital: items.capital,
    registration_date: items.registration_date, legal_form: items.legal_form,
    company_purposes: items.company_purposes, notes: items.notes,
    license_number: items.license_number,
    governorate: items.governorate, city: items.city,
  }
  docFields.forEach(f => { fileUrls.value[f.key] = items[`${f.key}_url`] ?? null })
  profileDataComplete.value = items.profile_data_complete ?? true
  missingProfileFields.value = items.missing_profile_fields ?? []
  logoUrl.value = items.logo_url ?? logoUrl.value
}

async function onLogoChange(e: Event) {
  const file = (e.target as HTMLInputElement).files?.[0] ?? null
  if (!file) return
  logoError.value = ''
  logoUploadDone.value = false
  isUploadingLogo.value = true
  logoUploadProgress.value = 0
  try {
    const fd = new FormData()
    fd.append('logo', file)
    const r = await axios.post(`${BASE}/api/v1/contractor/auth/logo`, fd, {
      // لا نحدّد Content-Type يدوياً: المتصفح يضبط multipart/form-data مع الـ boundary تلقائياً
      headers: apiHeaders(),
      onUploadProgress: (evt) => {
        if (evt.total) logoUploadProgress.value = Math.round((evt.loaded / evt.total) * 100)
      },
    })
    logoUrl.value = r.data.items?.logo_url ?? logoUrl.value
    logoUploadDone.value = true
    setTimeout(() => { logoUploadDone.value = false }, 2500)
  } catch (e: any) {
    logoError.value = e?.response?.data?.message || 'تعذّر رفع الشعار، حاول مرة أخرى.'
  } finally {
    isUploadingLogo.value = false
    if (logoInput.value) logoInput.value.value = ''
  }
}

async function saveProfile() {
  isSaving.value = true
  saveError.value = ''
  try {
    const fd = new FormData()
    Object.entries(editForm.value).forEach(([k, v]) => {
      // نرسل القيم الفارغة أيضاً (سلسلة فارغة) حتى يتمكّن المستخدم من تفريغ حقل؛
      // ConvertEmptyStringsToNull في الباك يحوّلها إلى null فيُمسح العمود فعلاً.
      if (v !== null && v !== undefined) fd.append(k, String(v))
    })
    // التصنيف العام
    fd.append('classification', editClassification.value ?? '')
    // التخصصات: نرسل الصفوف المكتملة فقط (مجال + اختصاص) كـ JSON
    const cleanSpecs = editSpecialties.value.filter(s => s.field_lk_type && s.specialization_lk_type)
    fd.append('specialties', JSON.stringify(cleanSpecs))
    const r = await axios.post(`${BASE}/api/v1/contractor/auth/profile/update`, fd, {
      // لا نحدّد Content-Type يدوياً: المتصفح يضبط multipart/form-data مع الـ boundary تلقائياً
      headers: apiHeaders(),
    })
    applyProfileResponse(r.data.items)
    editMode.value = false
  } catch (e: any) {
    saveError.value = e?.response?.data?.message || 'تعذّر حفظ التعديلات، حاول مرة أخرى.'
  } finally {
    isSaving.value = false
  }
}

// ─── Name Change Request — تعديل اسم الشركة يتطلب موافقة الإدارة ───
interface NameChangeRequest {
  id: number; status: 'pending' | 'approved' | 'rejected'; requested_name: string
  reject_reason: string | null; created_at: string
}
const nameChangeRequest = ref<NameChangeRequest | null>(null)
const showNameChangeForm = ref(false)
const nameChangeForm = ref({ requested_name: '', file: null as File | null })
const nameChangeError = ref('')
const isSubmittingNameChange = ref(false)
const nameChangeUploadProgress = ref(0)

const isDownloadingCompanyFile = ref(false)

async function downloadCompanyFile() {
  if (!profileDataComplete.value || isDownloadingCompanyFile.value) return
  isDownloadingCompanyFile.value = true
  try {
    const r = await axios.get(`${BASE}/api/v1/contractor/auth/profile/pdf`, {
      headers: apiHeaders(), responseType: 'blob',
    })
    const url = window.URL.createObjectURL(new Blob([r.data]))
    const a = document.createElement('a')
    a.href = url
    a.download = `ملف_الشركة_${contractor.value?.membership_number ?? ''}.pdf`
    a.click()
    window.URL.revokeObjectURL(url)
  } catch {}
  finally {
    isDownloadingCompanyFile.value = false
  }
}

async function fetchNameChangeRequest() {
  try {
    const r = await axios.get(`${BASE}/api/v1/contractor/auth/name-change-request`, { headers: apiHeaders() })
    nameChangeRequest.value = r.data.items ?? null
  } catch {}
}

function onNameChangeFileChange(e: Event) {
  nameChangeForm.value.file = (e.target as HTMLInputElement).files?.[0] ?? null
}

async function submitNameChangeRequest() {
  nameChangeError.value = ''
  if (!nameChangeForm.value.requested_name || !nameChangeForm.value.file) {
    nameChangeError.value = 'يرجى تعبئة الاسم الجديد وإرفاق الكتاب الرسمي.'
    return
  }
  isSubmittingNameChange.value = true
  nameChangeUploadProgress.value = 0
  try {
    const fd = new FormData()
    fd.append('requested_name', nameChangeForm.value.requested_name)
    fd.append('supporting_document', nameChangeForm.value.file)
    const r = await axios.post(`${BASE}/api/v1/contractor/auth/name-change-request`, fd, {
      // لا نحدّد Content-Type يدوياً: المتصفح يضبط multipart/form-data مع الـ boundary تلقائياً
      headers: apiHeaders(),
      onUploadProgress: (evt) => {
        if (evt.total) nameChangeUploadProgress.value = Math.round((evt.loaded / evt.total) * 100)
      },
    })
    nameChangeRequest.value = r.data.items
    showNameChangeForm.value = false
    nameChangeForm.value = { requested_name: '', file: null }
  } catch (e: any) {
    nameChangeError.value = e?.response?.data?.message || 'تعذّر إرسال الطلب، حاول مرة أخرى.'
  } finally {
    isSubmittingNameChange.value = false
  }
}

// ─── Feeds ───
const latestTenders = ref<any[]>([])
const latestNews = ref<any[]>([])

// فلاتر العطاءات — نفس النمط المستخدم في صفحة العطاءات العامة
const tenderSearch = ref('')
const tenderStatus = ref('open')
const tenderCategory = ref('')
const tenderCategories = ref<string[]>([])
const tenderStatuses = [
  { value: '', label: 'كل الحالات' },
  { value: 'open', label: 'مفتوحة' },
  { value: 'closed', label: 'مغلقة' },
  { value: 'cancelled', label: 'ملغاة' },
]
let tenderSearchTimer: ReturnType<typeof setTimeout> | null = null

async function fetchTenders() {
  try {
    const params = new URLSearchParams()
    if (tenderStatus.value) params.set('status', tenderStatus.value)
    if (tenderCategory.value) params.set('category', tenderCategory.value)
    if (tenderSearch.value) params.set('search', tenderSearch.value)
    params.set('per_page', '10')

    const r = await fetch(`${BASE}/api/v1/tenders-public?${params}`).then(res => res.json())
    latestTenders.value = r.items ?? []
    for (const t of latestTenders.value) {
      if (t.category && !tenderCategories.value.includes(t.category))
        tenderCategories.value.push(t.category)
    }
  } catch {}
}

watch([tenderStatus, tenderCategory], fetchTenders)
watch(tenderSearch, () => {
  if (tenderSearchTimer) clearTimeout(tenderSearchTimer)
  tenderSearchTimer = setTimeout(fetchTenders, 400)
})

async function fetchPublicFeeds() {
  try {
    const [, nr] = await Promise.all([
      fetchTenders(),
      fetch(`${BASE}/api/v1/news/latest`).then(r => r.json()),
    ])
    latestNews.value = nr.items ?? nr.data ?? []
  } catch {}
}

onMounted(async () => {
  token.value = getToken()
  if (!token.value) { authError.value = true; isLoading.value = false; return }
  // إشعار التفعيل الناجح — يُعرض مرة واحدة ثم يختفي
  if (localStorage.getItem('contractor_activated')) {
    localStorage.removeItem('contractor_activated')
    showActivatedPopup.value = true
    setTimeout(() => { showActivatedPopup.value = false }, 5000)
  }
  fetchPublicFeeds()
  fetchFinancial()
  fetchProfile()
  fetchNameChangeRequest()
  await fetchDashboard()
  const requestedTab = new URLSearchParams(window.location.search).get('tab')
  if (requestedTab && tabs.some(t => t.id === requestedTab))
    await fetchTab(requestedTab as typeof activeTab.value)
  isLoading.value = false
})

// ─── Helpers ───
function fmtDate(d: string | null) {
  if (!d) return '—'
  return new Date(d).toLocaleDateString('ar-PS', { year: 'numeric', month: 'long', day: 'numeric' })
}
function fmtMoney(v: string | number | null) {
  if (!v) return '—'
  return Number(v).toLocaleString('ar-PS') + ' ₪'
}

const statusLabel: Record<string, string> = {
  active: 'نشط', suspended: 'موقوف', inactive: 'غير نشط',
  paid: 'مدفوع', pending: 'معلّق', cancelled: 'ملغى',
  annual: 'سنوية', lifetime: 'مدى الحياة',
  new: 'جديدة', renewal: 'تجديد', upgrade: 'ترقية',
}

function statusClass(s: string) {
  if (['active','paid'].includes(s)) return 'badge-green'
  if (['pending'].includes(s)) return 'badge-yellow'
  return 'badge-red'
}

function docIcon(mime: string) {
  if (mime?.includes('pdf')) return FileCheck
  if (mime?.includes('image')) return FileText
  return FileClock
}

function downloadDoc(url: string, title: string) {
  const a = document.createElement('a')
  a.href = url; a.download = title; a.target = '_blank'
  a.click()
}
</script>

<template>
  <div dir="rtl" class="md-layout">

    <!-- إشعار popup: تم التفعيل بنجاح -->
    <Transition name="toast-fade">
      <div v-if="showActivatedPopup" class="activated-toast">
        <CheckCircle :size="22" />
        <span>تم تفعيل حسابك بنجاح، مرحباً بك!</span>
      </div>
    </Transition>

    <div v-if="authError" class="auth-wall">
      <img src="/logo.png" alt="الاتحاد" class="aw-logo" />
      <h2>يجب تسجيل الدخول أولاً</h2>
      <RouterLink to="/contractor/login" class="md-btn primary">تسجيل الدخول</RouterLink>
    </div>

    <div v-else-if="isLoading" class="md-loading">
      <div class="spinner" />
      <p>جاري التحميل...</p>
    </div>

    <template v-else-if="contractor">
      
      <PublicNavbar />

      <main class="md-main md-container">

        <!-- Profile Hero Card (ثابتة فوق كل التبويبات) -->
        <div class="md-profile-card">
          <div class="pr-content">
            <div class="pr-avatar-wrap">
              <img v-if="logoUrl" :src="logoUrl" alt="" class="pr-avatar pr-avatar-img" />
              <div v-else class="pr-avatar">{{ contractor.name.charAt(0) }}</div>
              <button class="pr-camera-btn" :disabled="isUploadingLogo" @click="logoInput?.click()">
                <Check v-if="logoUploadDone" :size="14" />
                <span v-else-if="isUploadingLogo" class="pr-camera-progress">{{ logoUploadProgress }}%</span>
                <Camera v-else :size="14" />
              </button>
              <input ref="logoInput" type="file" accept="image/png,image/jpeg,image/webp" hidden @change="onLogoChange" />
            </div>
            <div class="pr-info">
              <h3 class="pr-name">{{ contractor.name }}</h3>
              <p class="pr-email">{{ contractor.email || 'لا يوجد بريد مسجل' }}</p>
              <div class="pr-badge">مقاول {{ contractor.classification || 'مسجل' }}</div>
              <p class="pr-hint">تستطيع تحديث شعار شركتك بالضغط على أيقونة الكاميرا.</p>
              <p v-if="logoError" class="pr-logo-err">{{ logoError }}</p>
            </div>
          </div>
        </div>

        <!-- بانر حالة الحساب (عضوية منتهية / ذمم مستحقة / عضوية سارية) — بأولوية محسوبة من الباك اند -->
        <div v-if="accountStatus?.banner" class="account-status-banner" :class="`severity-${accountStatus.banner.severity}`">
          <AlertCircle :size="18" />
          <div>
            <strong>{{ accountStatus.banner.message }}</strong>
            <p v-if="accountStatus.certificates.locked">{{ accountStatus.certificates.lock_reason }}</p>
          </div>
        </div>
        <!-- تنبيه ذمم مالية مستحقة (احتياطي — يظهر بس لو ما وصل account_status لأي سبب) -->
        <div v-else-if="outstandingDues > 0" class="dues-warning-banner">
          <AlertCircle :size="18" />
          <div>
            <strong>لديك ذمم مالية مستحقة بقيمة {{ outstandingDues }} د.أ</strong>
            <p v-if="membership?.status !== 'active'">علماً بأن عضويتك الحالية غير فعالة — يرجى التواصل مع إدارة الاتحاد لتسوية الذمم وتفعيل العضوية.</p>
            <p v-else>يرجى مراجعة إدارة الاتحاد لتسوية الذمم المستحقة.</p>
          </div>
        </div>

        <!-- KPIs Grid (ثابتة فوق كل التبويبات) -->
        <div class="page-title-area">
          <h2>مؤشرات الأداء</h2>
        </div>
        <div class="md-kpi-grid">
          <div class="kpi-card">
            <div class="kpi-icon blue"><Truck :size="24" /></div>
            <div class="kpi-data">
              <span class="kpi-val">{{ stats?.total_equipment || 0 }}</span>
              <span class="kpi-lbl">الآليات المُسجلة</span>
            </div>
          </div>
          <div class="kpi-card">
            <div class="kpi-icon green"><Award :size="24" /></div>
            <div class="kpi-data">
              <span class="kpi-val">{{ stats?.certificate_requests || 0 }}</span>
              <span class="kpi-lbl">طلبات التراخيص والشهادات</span>
            </div>
          </div>
          <div class="kpi-card">
            <div class="kpi-icon purple"><HelpCircle :size="24" /></div>
            <div class="kpi-data">
              <span class="kpi-val">{{ stats?.open_tickets || 0 }}</span>
              <span class="kpi-lbl">تذاكر الدعم المفتوحة</span>
            </div>
          </div>
          <div class="kpi-card" :class="{ 'warning-kpi': outstandingDues > 0 }">
            <div class="kpi-icon red"><Wallet :size="24" /></div>
            <div class="kpi-data">
              <span class="kpi-val">{{ outstandingDues }} د.أ</span>
              <span class="kpi-lbl">الذمم المستحقة</span>
            </div>
          </div>
        </div>

        <!-- Navigation Pills (تحت مؤشرات الأداء) -->
        <nav class="md-nav-pills">
          <button
            v-for="tab in tabs" :key="tab.id"
            class="nav-pill"
            :class="{ active: activeTab === tab.id }"
            @click="fetchTab(tab.id as any)"
          >
            <component :is="tab.icon" :size="16" />
            {{ tab.label }}
          </button>
        </nav>

        <transition name="fade-slide" mode="out-in">

          <!-- ══ Tab: Profile ══ -->
          <div v-if="activeTab === 'profile'" key="profile" class="tab-pane">

            <!-- تنبيه اكتمال الملف الشخصي -->
            <div v-if="!profileDataComplete" class="profile-incomplete-banner">
              <AlertCircle :size="18" />
              <div>
                <strong>يجب إكمال ملفك الشخصي</strong>
                <p>لا يمكنك طلب شهادة عضوية قبل إكمال البيانات والمستندات التالية:</p>
                <div class="missing-fields-list">
                  <span v-for="f in missingProfileFields" :key="f" class="missing-field-chip">{{ f }}</span>
                </div>
              </div>
            </div>

            <!-- اسم الشركة — تعديله يتطلب موافقة الإدارة -->
            <div class="page-title-area">
              <h2>اسم الشركة</h2>
            </div>
            <div class="name-change-card">
              <div class="name-row">
                <div class="md-input-group name-row-field">
                  <label>الاسم الحالي</label>
                  <div class="md-input-read">{{ contractor.name }}</div>
                </div>
                <button
                  type="button" class="md-action-btn outline company-file-btn"
                  :disabled="!profileDataComplete || isDownloadingCompanyFile"
                  :title="!profileDataComplete ? 'أكمل بيانات ملفك الشخصي أولاً' : ''"
                  @click="downloadCompanyFile"
                >
                  <Download :size="16" /> {{ isDownloadingCompanyFile ? 'جاري التحميل...' : 'تحميل ملف الشركة' }}
                </button>
              </div>

              <div v-if="nameChangeRequest?.status === 'pending'" class="name-change-banner pending">
                <Clock :size="16" />
                <span>طلبك لتعديل الاسم إلى "{{ nameChangeRequest.requested_name }}" قيد المراجعة من الإدارة.</span>
              </div>
              <div v-else-if="nameChangeRequest?.status === 'rejected'" class="name-change-banner rejected">
                <AlertCircle :size="16" />
                <span>تم رفض طلب تعديل الاسم السابق{{ nameChangeRequest.reject_reason ? `: ${nameChangeRequest.reject_reason}` : '.' }}</span>
              </div>

              <button
                v-if="!showNameChangeForm && nameChangeRequest?.status !== 'pending'"
                class="md-action-btn outline name-change-toggle"
                @click="showNameChangeForm = true"
              >
                <Pencil :size="15" /> تقديم طلب تعديل اسم الشركة
              </button>

              <form v-if="showNameChangeForm" class="md-edit-form name-change-form" @submit.prevent="submitNameChangeRequest">
                <p class="docs-hint">يتطلب تعديل اسم الشركة إرفاق كتاب رسمي من وزارة الاقتصاد الوطني/التجارة يثبت تغيير الاسم.</p>
                <div v-if="nameChangeError" class="md-form-err"><AlertCircle :size="16" /> {{ nameChangeError }}</div>
                <div class="md-input-group">
                  <label>الاسم الجديد المطلوب *</label>
                  <input v-model="nameChangeForm.requested_name" type="text" class="md-fi" required />
                </div>
                <div class="md-input-group">
                  <label>الكتاب الرسمي المثبت للتغيير *</label>
                  <label class="md-doc-upload name-change-file">
                    <UploadCloud :size="15" />
                    {{ nameChangeForm.file ? nameChangeForm.file.name : 'اختر ملف (PDF أو صورة)' }}
                    <input type="file" accept=".pdf,image/*" hidden required @change="onNameChangeFileChange" />
                  </label>
                </div>
                <div v-if="isSubmittingNameChange" class="upload-progress-wrap">
                  <div class="upload-progress-bar"><div class="upload-progress-fill" :style="{ width: nameChangeUploadProgress + '%' }" /></div>
                  <span>{{ nameChangeUploadProgress }}%</span>
                </div>
                <div class="md-form-actions">
                  <button type="button" class="md-action-btn outline" @click="showNameChangeForm = false"><X :size="16" /> إلغاء</button>
                  <button type="submit" class="md-action-btn" :disabled="isSubmittingNameChange">
                    <Save :size="16" /> {{ isSubmittingNameChange ? 'جاري الإرسال...' : 'إرسال الطلب' }}
                  </button>
                </div>
              </form>
            </div>

            <!-- Account Details Form-like display -->
            <div class="page-title-area">
              <h2>تفاصيل الحساب</h2>
              <button v-if="!editMode" class="md-link edit-toggle" @click="startEdit">
                <Pencil :size="15" /> تعديل البيانات
              </button>
            </div>

            <!-- ══ عرض للقراءة فقط ══ -->
            <div v-if="!editMode" class="md-details-grid">
              <div class="md-input-group">
                <label>الاسم الأول (المفوض)</label>
                <div class="md-input-read">{{ contractor.authorized_person || '—' }}</div>
              </div>
              <div class="md-input-group">
                <label>رقم العضوية</label>
                <div class="md-input-read">{{ contractor.membership_number }}</div>
              </div>
              <div class="md-input-group">
                <label>البريد الإلكتروني</label>
                <div class="md-input-read">{{ contractor.email || '—' }}</div>
              </div>
              <div class="md-input-group">
                <label>رقم الهاتف</label>
                <div class="md-input-read" dir="ltr">{{ contractor.phone || '—' }}</div>
              </div>
              <div class="md-input-group">
                <label>السجل التجاري</label>
                <div class="md-input-read">{{ contractor.commercial_register || '—' }}</div>
              </div>
              <div class="md-input-group">
                <label>رقم رخصة البلدية</label>
                <div class="md-input-read">{{ profileExtra?.license_number || '—' }}</div>
              </div>
              <div class="md-input-group">
                <label>التخصص</label>
                <div class="md-input-read">{{ contractor.trade || '—' }}</div>
              </div>
              <div class="md-input-group">
                <label>المحافظة / المدينة</label>
                <div class="md-input-read">{{ profileExtra?.governorate?.name || '—' }} / {{ profileExtra?.city?.name || contractor.city || '—' }}</div>
              </div>
              <div class="md-input-group">
                <label>العنوان التفصيلي</label>
                <div class="md-input-read">{{ contractor.address || '—' }}</div>
              </div>
              <div class="md-input-group">
                <label>صاحب المنشأة</label>
                <div class="md-input-read">{{ profileExtra?.owner_name || '—' }}</div>
              </div>
              <div class="md-input-group">
                <label>الفاكس</label>
                <div class="md-input-read" dir="ltr">{{ profileExtra?.fax || '—' }}</div>
              </div>
              <div class="md-input-group">
                <label>رأس المال</label>
                <div class="md-input-read">{{ profileExtra?.capital || '—' }}</div>
              </div>
              <div class="md-input-group">
                <label>الشكل القانوني</label>
                <div class="md-input-read">{{ profileExtra?.legal_form || '—' }}</div>
              </div>
              <div class="md-input-group">
                <label>تاريخ التسجيل</label>
                <div class="md-input-read">{{ profileExtra?.registration_date ? fmtDate(profileExtra.registration_date) : '—' }}</div>
              </div>
              <div class="md-input-group full-span">
                <label>غايات الشركة</label>
                <div class="md-input-read">{{ profileExtra?.company_purposes || '—' }}</div>
              </div>
            </div>

            <!-- ══ نموذج التعديل ══ -->
            <form v-else class="md-edit-form" @submit.prevent="saveProfile">
              <div v-if="saveError" class="md-form-err"><AlertCircle :size="16" /> {{ saveError }}</div>

              <div class="md-details-grid">
                <div class="md-input-group">
                  <label>الاسم الأول (المفوض)</label>
                  <input v-model="editForm.authorized_person" type="text" class="md-fi" />
                </div>
                <div class="md-input-group">
                  <label>صاحب المنشأة</label>
                  <input v-model="editForm.owner_name" type="text" class="md-fi" />
                </div>
                <div class="md-input-group">
                  <label>البريد الإلكتروني</label>
                  <input v-model="editForm.email" type="email" class="md-fi" dir="ltr" />
                </div>
                <div class="md-input-group">
                  <label>رقم الهاتف</label>
                  <input v-model="editForm.phone" type="text" class="md-fi" dir="ltr" />
                </div>
                <div class="md-input-group">
                  <label>الفاكس</label>
                  <input v-model="editForm.fax" type="text" class="md-fi" dir="ltr" />
                </div>
                <div class="md-input-group">
                  <label>المحافظة</label>
                  <select v-model="editForm.governorate_id" class="md-fi" @change="editForm.city_id = null">
                    <option :value="null">اختر المحافظة</option>
                    <option v-for="g in governorates" :key="g.id" :value="g.id">{{ g.name }}</option>
                  </select>
                </div>
                <div class="md-input-group">
                  <label>المدينة</label>
                  <select v-model="editForm.city_id" class="md-fi" :disabled="!editForm.governorate_id">
                    <option :value="null">اختر المدينة</option>
                    <option v-for="c in citiesForSelectedGovernorate" :key="c.id" :value="c.id">{{ c.name }}</option>
                  </select>
                </div>
                <div class="md-input-group full-span">
                  <label>العنوان التفصيلي</label>
                  <input v-model="editForm.address" type="text" class="md-fi" />
                </div>
                <div class="md-input-group">
                  <label>رأس المال</label>
                  <input v-model="editForm.capital" type="text" class="md-fi" />
                </div>
                <div class="md-input-group">
                  <label>الشكل القانوني</label>
                  <input v-model="editForm.legal_form" type="text" class="md-fi" placeholder="مثال: مساهمة، تضامن" />
                </div>
                <div class="md-input-group">
                  <label>تاريخ التسجيل</label>
                  <input v-model="editForm.registration_date" type="date" class="md-fi" />
                </div>
                <div class="md-input-group full-span">
                  <label>غايات الشركة</label>
                  <textarea v-model="editForm.company_purposes" class="md-fi" rows="2" />
                </div>
                <div class="md-input-group full-span">
                  <label>ملاحظات إضافية</label>
                  <textarea v-model="editForm.notes" class="md-fi" rows="2" />
                </div>
              </div>

              <!-- محرّر التخصصات والتصنيفات -->
              <div class="spec-editor">
                <div class="spec-editor-head">
                  <h3>التخصصات والتصنيفات</h3>
                  <button type="button" class="md-action-btn outline sm" @click="addSpecRow">
                    <Award :size="15" /> إضافة تخصص
                  </button>
                </div>

                <div class="md-input-group" style="max-width:320px;margin-bottom:1rem">
                  <label>التصنيف العام</label>
                  <select v-model="editClassification" class="md-fi">
                    <option value="">— غير محدد —</option>
                    <option v-for="g in catalog.grades" :key="g.value" :value="g.value">{{ g.label }} ({{ g.value }})</option>
                  </select>
                </div>

                <p v-if="!editSpecialties.length" class="spec-empty">لا توجد تخصصات مضافة. اضغط "إضافة تخصص".</p>

                <div v-for="(row, i) in editSpecialties" :key="i" class="spec-edit-row">
                  <div class="md-input-group">
                    <label>المجال</label>
                    <select v-model.number="row.field_lk_type" class="md-fi">
                      <option :value="null">— اختر —</option>
                      <option v-for="f in catalog.fields" :key="f.id" :value="f.id">{{ f.name }}</option>
                    </select>
                  </div>
                  <div class="md-input-group">
                    <label>الاختصاص</label>
                    <select v-model.number="row.specialization_lk_type" class="md-fi">
                      <option :value="null">— اختر —</option>
                      <option v-for="s in catalog.specializations" :key="s.id" :value="s.id">{{ s.name }}</option>
                    </select>
                  </div>
                  <div class="md-input-group">
                    <label>الدرجة</label>
                    <select v-model="row.classification" class="md-fi">
                      <option :value="null">— غير محدد —</option>
                      <option v-for="g in catalog.grades" :key="g.value" :value="g.value">{{ g.label }}</option>
                    </select>
                  </div>
                  <button type="button" class="spec-row-remove" title="حذف" @click="removeSpecRow(i)"><X :size="16" /></button>
                </div>
              </div>

              <div class="md-form-actions">
                <button type="button" class="md-action-btn outline" @click="cancelEdit"><X :size="16" /> إلغاء</button>
                <button type="submit" class="md-action-btn" :disabled="isSaving">
                  <Save :size="16" /> {{ isSaving ? 'جاري الحفظ...' : 'حفظ التعديلات' }}
                </button>
              </div>
            </form>

            <!-- التخصصات والتصنيفات (عرض للقراءة — يختفي أثناء التعديل) -->
            <div v-if="!editMode && contractorFields.length" class="page-title-area">
              <h2>التخصصات والتصنيفات</h2>
            </div>
            <div v-if="!editMode && contractorFields.length" class="specialties-card">
              <template v-for="(group, gi) in contractorFields" :key="group.field_id ?? gi">
                <div class="specialty-group-header">
                  <Award :size="16" />
                  <strong>{{ group.field_name }}</strong>
                </div>
                <div
                  v-for="spec in group.specializations" :key="spec.spec_id ?? spec.spec_name"
                  class="specialty-row"
                >
                  <span>{{ spec.spec_name }}</span>
                  <span
                    v-if="spec.grade_label"
                    class="specialty-grade-badge"
                    :class="gradeLevelClass[spec.grade_level ?? 0] ?? 'grade-default'"
                  >
                    {{ spec.grade_label }}
                  </span>
                </div>
                <hr v-if="gi < contractorFields.length - 1" class="specialty-divider" />
              </template>
            </div>

          </div>

          <!-- ══ Tab: Membership ══ -->
          <div v-else-if="activeTab === 'membership'" key="membership" class="tab-pane">
            <div class="page-title-area">
              <h2>العضوية والشهادات</h2>
            </div>
            
            <div class="md-actions-row">
              <RouterLink to="/contractor/payment-gateway" class="md-action-btn">
                <RefreshCw :size="18"/> تجديد العضوية
              </RouterLink>
              <RouterLink to="/contractor/certificate-request" class="md-action-btn outline">
                <Award :size="18"/> طلب شهادة
              </RouterLink>
            </div>

            <div v-if="membership" class="mem-modern-card" :class="membership.expiring_soon ? 'warn' : 'ok'">
              <CheckCircle v-if="membership.status === 'active'" :size="32" class="mmc-icon" />
              <AlertCircle v-else :size="32" class="mmc-icon" />
              <div class="mmc-details">
                <h3>عضوية {{ statusLabel[membership.type] ?? membership.type }}</h3>
                <p>صالحة لغاية: {{ fmtDate(membership.expires_at) }}</p>
              </div>
              <div class="mmc-badge">{{ statusLabel[membership.status] }}</div>
            </div>

            <div class="md-table-wrapper" style="margin-top:2rem">
              <table class="md-table">
                <thead>
                  <tr><th>النوع</th><th>تاريخ البدء</th><th>تاريخ الانتهاء</th><th>المبلغ</th><th>الحالة</th></tr>
                </thead>
                <tbody>
                  <tr v-for="m in memberships" :key="m.id">
                    <td>{{ statusLabel[m.type] ?? m.type }}</td>
                    <td>{{ fmtDate(m.starts_at) }}</td>
                    <td>{{ fmtDate(m.expires_at) }}</td>
                    <td>{{ fmtMoney(m.amount) }}</td>
                    <td><span class="md-badge" :class="statusClass(m.status)">{{ statusLabel[m.status] ?? m.status }}</span></td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <!-- ══ Tab: Payments ══ -->
          <div v-else-if="activeTab === 'payments'" key="payments" class="tab-pane">
            <div class="page-title-area">
              <h2>المعاملات المالية</h2>
            </div>

            <!-- ملخص الذمم: الرصيد المستحق / المدفوع / إجمالي الرسوم + نسبة السداد -->
            <div v-if="duesTotalJod > 0" class="dues-summary-card">
              <div class="dsc-row">
                <div class="dsc-item">
                  <span class="dsc-label">الرصيد المستحق</span>
                  <span class="dsc-val danger">{{ outstandingDues.toLocaleString('ar-PS') }} د.أ</span>
                </div>
                <div class="dsc-item">
                  <span class="dsc-label">المدفوع</span>
                  <span class="dsc-val success">{{ duesPaidJod.toLocaleString('ar-PS') }} د.أ</span>
                </div>
                <div class="dsc-item">
                  <span class="dsc-label">إجمالي الرسوم</span>
                  <span class="dsc-val">{{ duesTotalJod.toLocaleString('ar-PS') }} د.أ</span>
                </div>
              </div>
              <div class="dsc-progress-track">
                <div class="dsc-progress-fill" :style="{ width: duesPaidPercentage + '%' }" />
              </div>
              <div class="dsc-badges">
                <span v-if="duesCounts.unpaid" class="md-badge badge-red">{{ duesCounts.unpaid }} غير مدفوع</span>
                <span v-if="duesCounts.pending_review" class="md-badge badge-yellow">{{ duesCounts.pending_review }} قيد المراجعة</span>
              </div>
            </div>

            <!-- تحويلات قيد المراجعة (بانتظار اعتماد المحاسبة) -->
            <template v-if="pendingDuesPayments.length">
              <div class="page-title-area obligations-title">
                <h3>قيد المراجعة</h3>
              </div>
              <div class="obligations-list">
                <div v-for="p in pendingDuesPayments" :key="p.id" class="obligation-row">
                  <div class="obligation-info">
                    <span class="obligation-type-badge obligation-due">بانتظار الاعتماد</span>
                    <span class="obligation-desc">{{ p.description }}</span>
                  </div>
                  <div class="obligation-meta">
                    <span class="obligation-date">{{ p.reference_number ?? '—' }}</span>
                    <span class="obligation-amount">{{ p.amount }} {{ p.currency }}</span>
                    <a v-if="p.receipt_image_url" :href="p.receipt_image_url" target="_blank" class="md-link">معاينة الإشعار</a>
                  </div>
                </div>
              </div>
            </template>

            <!-- الالتزامات المستحقة: غرامات + ذمم سابقة + دفعات معلّقة -->
            <template v-if="obligations.length">
              <div class="page-title-area obligations-title">
                <h3>الالتزامات المستحقة</h3>
              </div>
              <div class="obligations-list">
                <div v-for="(o, i) in obligations" :key="i" class="obligation-row">
                  <div class="obligation-info">
                    <span class="obligation-type-badge" :class="`obligation-${o.type}`">{{ obligationTypeLabel[o.type] ?? o.type }}</span>
                    <span class="obligation-desc">{{ o.description }}</span>
                  </div>
                  <div class="obligation-meta">
                    <span class="obligation-date">{{ fmtDate(o.date) }}</span>
                    <span class="obligation-amount">{{ fmtMoney(o.amount) }}</span>
                  </div>
                </div>
              </div>
            </template>

            <div class="page-title-area obligations-title">
              <h3>سجل المدفوعات</h3>
            </div>
            <div class="md-table-wrapper">
              <table class="md-table">
                <thead>
                  <tr><th>رقم المرجع</th><th>التاريخ</th><th>النوع</th><th>القيمة</th><th>الحالة</th></tr>
                </thead>
                <tbody>
                  <tr v-for="p in payments" :key="p.id">
                    <td>{{ p.reference_number || '—' }}</td>
                    <td>{{ fmtDate(p.paid_at ?? p.created_at) }}</td>
                    <td>{{ p.type === 'membership_fee' ? 'رسوم اشتراك' : p.type }}</td>
                    <td>{{ fmtMoney(p.amount) }}</td>
                    <td><span class="md-badge" :class="statusClass(p.status)">{{ statusLabel[p.status] ?? p.status }}</span></td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <!-- ══ Tab: Documents ══ -->
          <div v-else-if="activeTab === 'documents'" key="documents" class="tab-pane">
            <div class="page-title-area">
              <h2>وثائق التسجيل الرسمية</h2>
            </div>
            <p class="docs-hint">هذه المستندات مطلوبة عند تسجيل عضويتك — اختر ملفاً ليبدأ رفعه مباشرة.</p>

            <div class="md-docs-grid">
              <div v-for="f in docFields" :key="f.key" class="md-doc-row">
                <div class="md-doc-info">
                  <Paperclip :size="15" />
                  <span>{{ f.label }}</span>
                  <span v-if="docUploadingKeys.has(f.key)" class="md-doc-uploading">جاري الرفع...</span>
                  <a v-else-if="fileUrls[f.key]" :href="fileUrls[f.key]!" target="_blank" class="md-doc-current">عرض الملف الحالي</a>
                  <span v-else class="md-doc-missing">لم يُرفع بعد</span>
                </div>

                <div v-if="docUploadingKeys.has(f.key)" class="doc-row-progress">
                  <div class="upload-progress-bar"><div class="upload-progress-fill" :style="{ width: (docUploadProgress[f.key] ?? 0) + '%' }" /></div>
                  <span>{{ docUploadProgress[f.key] ?? 0 }}%</span>
                </div>
                <div v-else-if="docUploadedKeys.has(f.key)" class="md-doc-uploaded-badge">
                  <Check :size="14" /> تم الرفع
                </div>
                <label v-else class="md-doc-upload">
                  <UploadCloud :size="15" />
                  استبدال الملف
                  <input type="file" accept=".pdf,image/*" hidden @change="onDocFileChange(f.key, $event)" />
                </label>

                <p v-if="docsSaveError[f.key]" class="md-doc-row-err">{{ docsSaveError[f.key] }}</p>
              </div>
            </div>

            <div class="page-title-area docs-title">
              <h2>ملفات ومرفقات إضافية</h2>
            </div>
            <div class="doc-modern-grid">
              <div v-for="d in documents" :key="d.id" class="doc-modern-card">
                <component :is="docIcon(d.mime_type)" :size="32" class="dmc-icon" />
                <div class="dmc-info">
                  <h4>{{ d.title }}</h4>
                  <p>{{ d.formatted_size }} • {{ fmtDate(d.created_at) }}</p>
                </div>
                <button class="dmc-dl" @click="downloadDoc(d.url, d.title)"><Download :size="18"/></button>
              </div>
              <p v-if="!documents.length" class="docs-hint">لا توجد مرفقات إضافية بعد.</p>
            </div>
          </div>

          <!-- ══ Tab: Tenders ══ -->
          <div v-else-if="activeTab === 'tenders'" key="tenders" class="tab-pane">
            <div class="page-title-area">
              <h2>العطاءات</h2>
              <RouterLink to="/landing/public-tenders" class="md-link">استعراض الكل ←</RouterLink>
            </div>
            <div class="tender-filters-row">
              <input v-model="tenderSearch" type="text" class="md-fi tender-search" placeholder="بحث في العطاءات..." />
              <select v-model="tenderStatus" class="md-fi tender-filter-sel">
                <option v-for="s in tenderStatuses" :key="s.value" :value="s.value">{{ s.label }}</option>
              </select>
              <select v-model="tenderCategory" class="md-fi tender-filter-sel">
                <option value="">كل الفئات</option>
                <option v-for="c in tenderCategories" :key="c" :value="c">{{ c }}</option>
              </select>
            </div>
            <div v-if="!latestTenders.length" class="docs-hint">لا توجد عطاءات مطابقة لهذا الفلتر.</div>
            <div class="feed-list">
              <a v-for="t in latestTenders" :key="t.id" :href="t.external_url || '/landing/public-tenders'" class="feed-card" target="_blank">
                <h4>{{ t.title }}</h4>
                <div class="fc-meta">
                  <span><CalendarDays :size="14"/> إغلاق: {{ t.deadline ? new Date(t.deadline).toLocaleDateString('ar-EG') : '—' }}</span>
                  <span v-if="t.budget"><Wallet :size="14"/> {{ Number(t.budget).toLocaleString() }} $</span>
                </div>
              </a>
            </div>
          </div>

          <!-- ══ Tab: News ══ -->
          <div v-else-if="activeTab === 'news'" key="news" class="tab-pane">
            <div class="page-title-area">
              <h2>آخر الأخبار</h2>
            </div>
            <div class="feed-list">
              <RouterLink v-for="n in latestNews" :key="n.id" :to="`/landing/news/${n.slug}`" class="feed-card">
                <h4>{{ n.title }}</h4>
                <div class="fc-meta">
                  <span><CalendarDays :size="14"/> {{ n.published_at ? new Date(n.published_at).toLocaleDateString('ar-EG') : '' }}</span>
                </div>
              </RouterLink>
            </div>
          </div>

        </transition>
      </main>
    </template>
  </div>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800;900&display=swap');

:global(:root) {
  --primary: #0f172a;
  --primary-hover: #1e293b;
  --accent: #10b981;
  --accent-light: #d1fae5;
  --bg-main: #f8fafc;
  --bg-card: #ffffff;
  --text-dark: #0f172a;
  --text-muted: #64748b;
  --border: #e2e8f0;
}

* { box-sizing: border-box; }

.md-layout {
  font-family: 'Tajawal', sans-serif;
  direction: rtl;
  min-height: 100vh;
  background-color: var(--bg-main);
  color: var(--text-dark);
}

.md-container { max-width: 1000px; margin: 0 auto; padding: 0 1.5rem; }

/* ─── Nav Pills ─── */
.md-nav-pills {
  display: flex; gap: 0.3rem; overflow-x: auto; scrollbar-width: none;
  background: var(--bg-card); border: 1px solid var(--border); border-radius: 16px;
  padding: 0.5rem; margin-bottom: 1.5rem;
  box-shadow: 0 4px 20px rgba(0,0,0,0.02);
}
.nav-pill {
  display: flex; align-items: center; gap: 0.5rem;
  background: transparent; border: none;
  padding: 0.6rem 1rem; border-radius: 50px;
  color: var(--text-muted); font-family: inherit;
  font-weight: 700; font-size: 0.9rem; cursor: pointer;
  transition: all 0.3s ease;
  white-space: nowrap;
}
.nav-pill:hover { background: #f1f5f9; color: var(--text-dark); }
.nav-pill.active { background: var(--accent-light); color: #047857; }

/* ─── Main Content & Animations ─── */
.md-main { padding: 3rem 1.5rem; }

.fade-slide-enter-active, .fade-slide-leave-active { transition: opacity 0.3s ease, transform 0.3s ease; }
.fade-slide-enter-from { opacity: 0; transform: translateY(15px); }
.fade-slide-leave-to { opacity: 0; transform: translateY(-15px); }

.page-title-area { margin-bottom: 1.5rem; display: flex; justify-content: space-between; align-items: center; margin-top: 1rem; }
.page-title-area h2 { font-weight: 800; font-size: 1.25rem; color: var(--text-dark); }

/* ─── Profile Hero Card (Image Style) ─── */
.md-profile-card {
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: 20px;
  padding: 2rem;
  box-shadow: 0 4px 20px rgba(0,0,0,0.02);
  margin-bottom: 3rem;
  margin-top: 1rem;
}
.pr-content {
  display: flex; justify-content: space-between; align-items: center;
}
.pr-info { flex: 1; text-align: right; }
.pr-name { font-size: 1.4rem; font-weight: 900; color: var(--primary); margin-bottom: 0.2rem; }
.pr-email { color: var(--text-muted); font-size: 0.95rem; margin-bottom: 0.8rem; }
.pr-badge {
  display: inline-block;
  background: var(--accent-light); color: #047857;
  padding: 0.2rem 0.8rem; border-radius: 50px;
  font-size: 0.8rem; font-weight: 700; margin-bottom: 1rem;
}
.pr-hint { font-size: 0.75rem; color: #94a3b8; }

.pr-avatar-wrap { position: relative; margin-left: 2rem; flex-shrink: 0; }
.pr-avatar {
  width: 90px; height: 90px; border-radius: 24px;
  background: var(--primary); color: #fff;
  display: flex; align-items: center; justify-content: center;
  font-size: 2.5rem; font-weight: 900;
  box-shadow: 0 8px 16px rgba(15,23,42,0.15);
}
.pr-avatar-img { object-fit: cover; background: var(--bg-card); }
.pr-camera-btn {
  position: absolute; bottom: -8px; right: -8px;
  width: 32px; height: 32px; border-radius: 50%;
  background: var(--bg-card); border: 1px solid var(--border);
  color: var(--primary); display: flex; align-items: center; justify-content: center;
  cursor: pointer; box-shadow: 0 2px 5px rgba(0,0,0,0.1);
  transition: transform 0.2s;
}
.pr-camera-btn:hover { transform: scale(1.1); }
.pr-camera-btn:disabled { cursor: not-allowed; opacity: 0.8; }
.pr-camera-progress { font-size: 0.55rem; font-weight: 800; }
.pr-logo-err { font-size: 0.78rem; color: #dc2626; font-weight: 600; margin-top: 0.4rem; }

/* ─── Account Status Banner (severity من الباك اند) ─── */
.account-status-banner {
  display: flex; gap: 0.85rem; align-items: flex-start;
  border-radius: 14px; padding: 1.1rem 1.25rem; margin-bottom: 1.5rem;
  border: 1px solid;
}
.account-status-banner strong { display: block; font-size: 0.95rem; margin-bottom: 0.25rem; }
.account-status-banner p { font-size: 0.85rem; margin: 0; }
.account-status-banner.severity-error   { background: #fef2f2; border-color: #fecaca; color: #b91c1c; }
.account-status-banner.severity-warning { background: #fff7ed; border-color: #fed7aa; color: #c2410c; }
.account-status-banner.severity-success { background: #f0fdf4; border-color: #bbf7d0; color: #15803d; }

/* ─── Dues Warning Banner ─── */
.dues-warning-banner {
  display: flex; gap: 0.85rem; align-items: flex-start;
  background: #fff7ed; border: 1px solid #fed7aa; color: #c2410c;
  border-radius: 14px; padding: 1.1rem 1.25rem; margin-bottom: 1.5rem;
}
.dues-warning-banner strong { display: block; font-size: 0.95rem; margin-bottom: 0.25rem; }
.dues-warning-banner p { font-size: 0.85rem; margin: 0; }

/* ─── KPIs Grid ─── */
.md-kpi-grid {
  display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: 1.25rem; margin-bottom: 3rem;
}
.kpi-card {
  background: var(--bg-card); border: 1px solid var(--border);
  border-radius: 16px; padding: 1.25rem;
  display: flex; align-items: center; gap: 1rem;
  box-shadow: 0 2px 10px rgba(0,0,0,0.01);
  transition: transform 0.2s, box-shadow 0.2s;
}
.kpi-card:hover { transform: translateY(-3px); box-shadow: 0 8px 20px rgba(0,0,0,0.04); }
.warning-kpi { border-color: #fca5a5; background: #fef2f2; }
.kpi-icon {
  width: 48px; height: 48px; border-radius: 12px;
  display: flex; align-items: center; justify-content: center;
}
.kpi-icon.blue { background: #e0f2fe; color: #0284c7; }
.kpi-icon.green { background: #dcfce7; color: #16a34a; }
.kpi-icon.purple { background: #f3e8ff; color: #9333ea; }
.kpi-icon.red { background: #fee2e2; color: #dc2626; }
.kpi-data { display: flex; flex-direction: column; }
.kpi-val { font-size: 1.3rem; font-weight: 900; color: var(--primary); }
.kpi-lbl { font-size: 0.8rem; color: var(--text-muted); font-weight: 600; }

/* ─── Details Grid (Inputs like) ─── */
.md-details-grid {
  display: grid; grid-template-columns: repeat(2, 1fr);
  gap: 1.25rem; margin-bottom: 2rem;
}

/* ─── Specialties & Classifications ─── */
.specialties-card {
  background: var(--bg-card); border: 1px solid var(--border); border-radius: 16px;
  padding: 1.25rem; margin-bottom: 2rem;
}
.specialty-group-header { display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.75rem; color: var(--primary); font-size: 0.95rem; }
.specialty-row {
  display: flex; align-items: center; justify-content: space-between; gap: 0.75rem;
  padding: 0.85rem 1rem; border-radius: 12px; margin-bottom: 0.6rem;
  font-size: 0.9rem; font-weight: 600; color: var(--text-dark);
  background: #f8fafc;
}
.specialty-grade-badge { font-size: 0.78rem; font-weight: 800; padding: 0.3rem 0.9rem; border-radius: 50px; color: #fff; white-space: nowrap; }
.specialty-row.grade-1, .specialty-grade-badge.grade-1 {}
.specialty-row:has(.grade-1) { background: #ecfdf5; }
.specialty-row:has(.grade-2) { background: #fff7ed; }
.specialty-row:has(.grade-3) { background: #eff6ff; }
.specialty-row:has(.grade-4) { background: #fdf2f8; }
.specialty-row:has(.grade-5) { background: #f5f3ff; }
.specialty-grade-badge.grade-1 { background: #16a34a; }
.specialty-grade-badge.grade-2 { background: #ea580c; }
.specialty-grade-badge.grade-3 { background: #2563eb; }
.specialty-grade-badge.grade-4 { background: #db2777; }
.specialty-grade-badge.grade-5 { background: #7c3aed; }
.specialty-grade-badge.grade-default { background: var(--text-muted); }
.specialty-divider { border: none; border-top: 1px solid var(--border); margin: 1rem 0; }
.md-input-group label { display: block; font-size: 0.85rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.4rem; }
.md-input-read {
  background: var(--bg-card); border: 1px solid var(--border);
  padding: 0.85rem 1.25rem; border-radius: 12px;
  font-size: 0.95rem; font-weight: 700; color: var(--primary);
  min-height: 48px; display: flex; align-items: center;
}
.md-input-group.full-span { grid-column: 1 / -1; }

/* ─── Profile Incomplete Banner ─── */
.profile-incomplete-banner {
  display: flex; gap: 0.85rem; align-items: flex-start;
  background: #fee2e2; border: 1px solid #fecaca; color: #dc2626;
  border-radius: 14px; padding: 1.1rem 1.25rem; margin-bottom: 1.5rem;
}
.profile-incomplete-banner strong { display: block; font-size: 0.95rem; margin-bottom: 0.3rem; }
.profile-incomplete-banner p { font-size: 0.85rem; margin-bottom: 0.6rem; }
.missing-fields-list { display: flex; flex-wrap: wrap; gap: 0.4rem; }
.missing-field-chip { background: #fff; border: 1px solid #fecaca; color: #dc2626; font-size: 0.76rem; font-weight: 700; padding: 0.3rem 0.7rem; border-radius: 50px; }

/* ─── Name Change Request ─── */
.name-change-card {
  background: var(--bg-card); border: 1px solid var(--border); border-radius: 16px;
  padding: 1.5rem; margin-bottom: 2rem; display: flex; flex-direction: column; gap: 1rem;
}
.name-change-banner {
  display: flex; align-items: center; gap: 0.6rem;
  padding: 0.8rem 1.1rem; border-radius: 10px; font-size: 0.85rem; font-weight: 600;
}
.name-change-banner.pending { background: #fef9c3; color: #854d0e; }
.name-change-banner.rejected { background: #fee2e2; color: #dc2626; }
.name-change-toggle { align-self: flex-start; }
.name-change-form { margin-top: 0.5rem; }
.name-change-file { width: 100%; justify-content: flex-start; }

/* ─── Profile Edit Form ─── */
.edit-toggle { display: inline-flex; align-items: center; gap: 0.4rem; background: none; border: none; cursor: pointer; font-family: inherit; }
.md-form-err {
  display: flex; align-items: center; gap: 0.5rem;
  background: #fee2e2; color: #dc2626; border: 1px solid #fecaca;
  padding: 0.8rem 1.1rem; border-radius: 10px; font-size: 0.88rem; font-weight: 600;
  margin-bottom: 1.25rem;
}
.md-fi {
  width: 100%; font-family: inherit; font-size: 0.95rem;
  background: var(--bg-card); border: 1.5px solid var(--border);
  padding: 0.8rem 1.1rem; border-radius: 12px; color: var(--text-dark);
  transition: border-color 0.2s;
}
.md-fi:focus { outline: none; border-color: var(--primary); }
.md-fi:disabled { opacity: 0.6; cursor: not-allowed; }
textarea.md-fi { resize: vertical; }

.docs-title { margin-top: 2.5rem; }
.docs-hint { font-size: 0.85rem; color: var(--text-muted); margin-bottom: 1.25rem; }

/* ─── Dues Summary Card ─── */
.dues-summary-card {
  background: linear-gradient(135deg, #1e3a8a, #1e40af); color: #fff;
  border-radius: 16px; padding: 1.25rem 1.5rem; margin-bottom: 1.5rem;
}
.dsc-row { display: flex; justify-content: space-between; gap: 1rem; flex-wrap: wrap; margin-bottom: 1rem; }
.dsc-item { display: flex; flex-direction: column; gap: 0.2rem; }
.dsc-label { font-size: 0.78rem; opacity: 0.8; }
.dsc-val { font-size: 1.1rem; font-weight: 800; }
.dsc-val.danger { color: #fca5a5; }
.dsc-val.success { color: #86efac; }
.dsc-progress-track { height: 6px; background: rgba(255,255,255,0.2); border-radius: 999px; overflow: hidden; margin-bottom: 0.85rem; }
.dsc-progress-fill { height: 100%; background: #fff; border-radius: 999px; transition: width 0.3s; }
.dsc-badges { display: flex; gap: 0.5rem; flex-wrap: wrap; }

/* ─── Financial Obligations ─── */
.obligations-title h3 { font-weight: 800; font-size: 1.05rem; color: var(--text-dark); }
.obligations-list { display: flex; flex-direction: column; gap: 0.6rem; margin-bottom: 1.5rem; }
.obligation-row {
  display: flex; align-items: center; justify-content: space-between; gap: 0.75rem; flex-wrap: wrap;
  background: var(--bg-card); border: 1px solid var(--border); border-radius: 12px;
  padding: 0.85rem 1.1rem;
}
.obligation-info { display: flex; align-items: center; gap: 0.65rem; flex-wrap: wrap; }
.obligation-desc { font-size: 0.88rem; font-weight: 600; color: var(--text-dark); }
.obligation-type-badge { font-size: 0.74rem; font-weight: 800; padding: 0.25rem 0.7rem; border-radius: 50px; white-space: nowrap; }
.obligation-type-badge.obligation-penalty { background: #fee2e2; color: #dc2626; }
.obligation-type-badge.obligation-due { background: #fff7ed; color: #c2410c; }
.obligation-type-badge.obligation-payment { background: #eff6ff; color: #2563eb; }
.obligation-meta { display: flex; align-items: center; gap: 1rem; }
.obligation-date { font-size: 0.78rem; color: var(--text-muted); }
.obligation-amount { font-size: 0.92rem; font-weight: 800; color: #dc2626; }

/* ─── Tender Filters ─── */
.tender-filters-row { display: flex; gap: 0.75rem; margin-bottom: 1.5rem; flex-wrap: wrap; }
.tender-search { flex: 1; min-width: 200px; }
.tender-filter-sel { width: auto; min-width: 140px; }
.md-doc-missing { font-size: 0.78rem; font-weight: 600; color: #dc2626; }
.md-doc-uploading { font-size: 0.78rem; font-weight: 600; color: #2563eb; }
.md-docs-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 0.9rem; margin-bottom: 2rem; }
.md-doc-row {
  display: flex; align-items: center; justify-content: space-between; gap: 0.75rem; flex-wrap: wrap;
  background: var(--bg-card); border: 1px solid var(--border); border-radius: 12px;
  padding: 0.7rem 1rem;
}
.doc-row-progress { display: flex; align-items: center; gap: 0.5rem; flex-shrink: 0; width: 130px; }
.doc-row-progress .upload-progress-bar { flex: 1; }
.doc-row-progress span { font-size: 0.75rem; font-weight: 700; color: #16a34a; }
.md-doc-row-err { flex-basis: 100%; font-size: 0.78rem; color: #dc2626; font-weight: 600; margin: 0; }
.md-doc-info { display: flex; align-items: center; gap: 0.5rem; font-size: 0.85rem; font-weight: 600; color: var(--text-dark); flex-wrap: wrap; }
.md-doc-current { color: var(--accent); font-size: 0.78rem; font-weight: 700; text-decoration: none; }
.md-doc-current:hover { text-decoration: underline; }
.md-doc-upload {
  display: inline-flex; align-items: center; gap: 0.4rem; cursor: pointer;
  background: transparent; border: 1.5px dashed var(--primary); color: var(--primary);
  padding: 0.45rem 0.8rem; border-radius: 9px; font-size: 0.8rem; font-weight: 700;
  white-space: nowrap; transition: all 0.2s; flex-shrink: 0;
}
.md-doc-upload:hover { background: var(--primary); color: #fff; }
.md-doc-uploaded-badge {
  display: inline-flex; align-items: center; gap: 0.4rem;
  background: #dcfce7; color: #16a34a; font-size: 0.8rem; font-weight: 700;
  padding: 0.45rem 0.8rem; border-radius: 9px; flex-shrink: 0; white-space: nowrap;
}

.upload-progress-wrap { display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1rem; }
.upload-progress-bar { flex: 1; height: 8px; border-radius: 50px; background: var(--border); overflow: hidden; }
.upload-progress-fill { height: 100%; background: #16a34a; transition: width 0.2s ease; }
.upload-progress-wrap span { font-size: 0.8rem; font-weight: 700; color: #16a34a; min-width: 32px; }
.upload-done-msg {
  display: flex; align-items: center; gap: 0.5rem;
  color: #16a34a; background: #dcfce7; padding: 0.6rem 0.9rem; border-radius: 10px;
  font-size: 0.85rem; font-weight: 700; margin-bottom: 1rem;
}

.md-form-actions { display: flex; justify-content: flex-end; gap: 0.75rem; margin-top: 0.5rem; }

/* ─── Actions ─── */
.md-actions-row { display: flex; gap: 1rem; margin-bottom: 1.5rem; }
.md-action-btn {
  display: inline-flex; align-items: center; gap: 0.5rem;
  background: var(--primary); color: #fff;
  padding: 0.75rem 1.5rem; border-radius: 12px;
  font-weight: 700; text-decoration: none; font-size: 0.95rem;
  transition: all 0.2s;
}
.md-action-btn:hover { background: var(--primary-hover); transform: scale(1.02); }
.md-action-btn.outline { background: transparent; border: 2px solid var(--primary); color: var(--primary); }
.md-action-btn.outline:hover { background: var(--primary); color: #fff; }
.md-action-btn:disabled { opacity: 0.5; cursor: not-allowed; }

.name-row { display: flex; align-items: flex-end; justify-content: space-between; gap: 1rem; flex-wrap: wrap; }
.name-row-field { flex: 1; min-width: 200px; }
.company-file-btn { flex-shrink: 0; }

/* ─── Tables ─── */
.md-table-wrapper {
  background: var(--bg-card); border: 1px solid var(--border);
  border-radius: 16px; overflow: hidden; margin-bottom: 2rem;
}
.md-table { width: 100%; border-collapse: collapse; text-align: right; }
.md-table th { background: #f8fafc; padding: 1.2rem 1rem; font-size: 0.85rem; font-weight: 800; color: var(--text-muted); border-bottom: 1px solid var(--border); }
.md-table td { padding: 1.2rem 1rem; font-size: 0.9rem; font-weight: 600; border-bottom: 1px solid #f1f5f9; color: var(--primary); }
.md-badge { display: inline-block; padding: 0.2rem 0.6rem; border-radius: 50px; font-size: 0.75rem; font-weight: 700; }
.badge-green { background: #dcfce7; color: #16a34a; }
.badge-yellow { background: #fef9c3; color: #ca8a04; }
.badge-red { background: #fee2e2; color: #dc2626; }

/* ─── Documents Grid ─── */
.doc-modern-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 1rem; }
.doc-modern-card {
  background: var(--bg-card); border: 1px solid var(--border);
  border-radius: 16px; padding: 1.25rem;
  display: flex; align-items: center; gap: 1rem;
  transition: box-shadow 0.3s;
}
.doc-modern-card:hover { box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
.dmc-icon { color: var(--text-muted); }
.dmc-info { flex: 1; min-width: 0; }
.dmc-info h4 { font-size: 0.95rem; font-weight: 800; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-bottom: 0.2rem; color: var(--primary); }
.dmc-info p { font-size: 0.75rem; color: var(--text-muted); font-weight: 600; }
.dmc-dl { background: #f1f5f9; border: none; width: 36px; height: 36px; border-radius: 10px; display: flex; align-items: center; justify-content: center; cursor: pointer; color: var(--primary); transition: background 0.2s; }
.dmc-dl:hover { background: #e2e8f0; }

/* ─── Feeds ─── */
.feed-list { display: flex; flex-direction: column; gap: 1rem; }
.feed-card {
  display: block; background: var(--bg-card); border: 1px solid var(--border);
  border-radius: 14px; padding: 1.5rem; text-decoration: none; color: inherit;
  transition: all 0.2s;
}
.feed-card:hover { border-color: var(--text-muted); transform: translateX(-5px); }
.feed-card h4 { font-size: 1.05rem; font-weight: 800; margin-bottom: 0.5rem; color: var(--primary); }
.fc-meta { display: flex; gap: 1rem; font-size: 0.8rem; color: var(--text-muted); font-weight: 600; }
.fc-meta span { display: flex; align-items: center; gap: 0.3rem; }
.md-link { font-size: 0.9rem; font-weight: 700; color: var(--accent); text-decoration: none; }

/* ─── Utils ─── */
.auth-wall, .md-loading { display: flex; flex-direction: column; align-items: center; justify-content: center; height: 60vh; text-align: center; gap: 1rem; }
.aw-logo { height: 70px; }
.spinner { width: 40px; height: 40px; border: 3px solid var(--border); border-top-color: var(--primary); border-radius: 50%; animation: spin 1s linear infinite; }
@keyframes spin { 100% { transform: rotate(360deg); } }

/* Membership banner */
.mem-modern-card {
  display: flex; align-items: center; gap: 1rem;
  background: var(--bg-card); border: 1px solid var(--border);
  padding: 1.5rem; border-radius: 16px;
}
.mem-modern-card.ok { border-right: 5px solid #10b981; }
.mem-modern-card.warn { border-right: 5px solid #f59e0b; background: #fffbeb; }
.mmc-icon { color: var(--text-muted); }
.mem-modern-card.ok .mmc-icon { color: #10b981; }
.mem-modern-card.warn .mmc-icon { color: #f59e0b; }
.mmc-details { flex: 1; }
.mmc-details h3 { font-size: 1.1rem; font-weight: 800; margin-bottom: 0.2rem; color: var(--primary); }
.mmc-details p { font-size: 0.85rem; color: var(--text-muted); font-weight: 600; }
.mmc-badge { background: var(--primary); color: #fff; padding: 0.3rem 0.8rem; border-radius: 50px; font-size: 0.8rem; font-weight: 700; }

@media (max-width: 768px) {
  .pr-content { flex-direction: column; text-align: center; gap: 1.5rem; }
  .pr-info { text-align: center; }
  .pr-avatar-wrap { margin-right: 0; margin-left: 0; }
  .md-nav-pills { width: 100%; justify-content: flex-start; padding-bottom: 0.5rem; }
  .md-details-grid { grid-template-columns: 1fr; }
  .md-docs-grid { grid-template-columns: 1fr; }
  .md-doc-row { flex-direction: column; align-items: stretch; }
}

/* ─── محرّر التخصصات ─── */
.spec-editor { margin-top: 1.5rem; padding-top: 1.25rem; border-top: 1px dashed var(--border); }
.spec-editor-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 1rem; gap: 1rem; flex-wrap: wrap; }
.spec-editor-head h3 { font-size: 1.05rem; font-weight: 800; color: var(--primary); }
.md-action-btn.sm { padding: .45rem .8rem; font-size: .82rem; }
.spec-empty { font-size: .88rem; color: var(--text-muted); padding: .5rem 0 1rem; }
.spec-edit-row { display: grid; grid-template-columns: 1fr 1fr 1fr auto; gap: .75rem; align-items: end; margin-bottom: .85rem; }
.spec-row-remove { background: var(--red-light, #fce4ec); color: #c62828; border: none; border-radius: 8px; width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; cursor: pointer; flex-shrink: 0; }
.spec-row-remove:hover { background: #c62828; color: #fff; }
@media (max-width: 700px) { .spec-edit-row { grid-template-columns: 1fr 1fr; } .spec-row-remove { grid-column: 2; justify-self: end; } }

/* ─── إشعار التفعيل (toast) ─── */
.activated-toast {
  position: fixed; inset-block-start: 1.25rem; inset-inline: 0; margin-inline: auto;
  width: max-content; max-width: 90vw; z-index: 3000;
  display: flex; align-items: center; gap: .6rem;
  background: #16a34a; color: #fff; font-weight: 700; font-size: .95rem;
  padding: .85rem 1.4rem; border-radius: 12px; box-shadow: 0 12px 32px rgba(22,163,74,.35);
}
.toast-fade-enter-active, .toast-fade-leave-active { transition: opacity .35s, transform .35s; }
.toast-fade-enter-from, .toast-fade-leave-to { opacity: 0; transform: translateY(-14px); }
</style>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import axios from 'axios'
import {
  LogOut, Send, CheckCircle, AlertCircle, Award, FileCheck,
  Clock, XCircle, CheckCircle2, File, Download, ArrowRight,
} from 'lucide-vue-next'

definePage({
  meta: { layout: 'pure', public: true, unauthenticatedOnly: false },
})

const router = useRouter()
const token = ref<string | null>(null)
const authError = ref(false)
const isLoading = ref(true)
const isSubmitting = ref(false)

interface Contractor {
  name: string
  membership_number: string
  status: string
  is_frozen: boolean
}

interface CertificateRequest {
  id: number
  type: string
  status: string
  request_date: string
  issue_date: string | null
  certificate_url: string | null
  reject_reason: string | null
  notes: string | null
}

interface RequirementIssue {
  type: string
  description: string
  amount: string | null
  due_date: string | null
}

interface MembershipStatus {
  eligible: boolean
  paid_percentage: number
  required_percent: number
  remaining_to_95_jod: number
  current_year: number
}

const contractor = ref<Contractor | null>(null)
const requests = ref<CertificateRequest[]>([])
const requirementIssues = ref<RequirementIssue[]>([])
const canRequest = ref(true)
const profileDataComplete = ref(true)
const missingProfileFields = ref<string[]>([])
const showForm = ref(false)
const successMessage = ref('')
const errorMessage = ref('')
const membershipStatus = ref<MembershipStatus | null>(null)

const form = ref({
  type: '',
  notes: '',
})

const certificateTypes = [
  { value: 'membership', label: 'شهادة العضوية' },
  { value: 'good_standing', label: 'شهادة حسن السير والسلوك' },
  { value: 'classification', label: 'شهادة التصنيف' },
  { value: 'experience', label: 'شهادة الخبرة والمشاريع' },
]

const BASE = import.meta.env.VITE_API_BASE_URL ?? ''

// مرفق اختياري مع الطلب (صورة أو PDF حتى 5MB)
const attachmentFile = ref<File | null>(null)

function onAttachmentChange(e: Event) {
  const file = (e.target as HTMLInputElement).files?.[0] ?? null
  if (file && file.size > 5 * 1024 * 1024) {
    errorMessage.value = 'حجم المرفق يتجاوز 5 ميغابايت'
    return
  }
  attachmentFile.value = file
}

function getToken() {
  return localStorage.getItem('contractor_token')
}

function apiHeaders() {
  return { Authorization: `Bearer ${token.value}` }
}

function logout() {
  localStorage.removeItem('contractor_token')
  router.push('/landing')
}

async function fetchData() {
  try {
    const r = await axios.get(`${BASE}/api/v1/contractor/certificate-requests`, { headers: apiHeaders() })
    contractor.value = r.data.items?.contractor ?? null
    requests.value = r.data.items?.requests ?? []
    requirementIssues.value = r.data.items?.requirement_issues ?? []
    canRequest.value = r.data.items?.can_request ?? true
    profileDataComplete.value = r.data.items?.profile_data_complete ?? true
    missingProfileFields.value = r.data.items?.missing_profile_fields ?? []

    const s = await axios.get(`${BASE}/api/v1/contractor/certificates/status`, { headers: apiHeaders() })
    membershipStatus.value = s.data.items?.membership ?? null
  } catch (e: any) {
    if (e?.response?.status === 401) authError.value = true
  } finally {
    isLoading.value = false
  }
}

async function submitRequest() {
  if (!form.value.type) {
    errorMessage.value = 'الرجاء اختيار نوع الشهادة المطلوبة'
    return
  }

  if (form.value.type === 'membership' && membershipStatus.value && !membershipStatus.value.eligible) {
    errorMessage.value = `يتبقى لك سداد ${membershipStatus.value.remaining_to_95_jod} دينار للوصول إلى حد الـ 95% واستخراج شهادتك تلقائياً.`
    return
  }

  isSubmitting.value = true
  errorMessage.value = ''
  successMessage.value = ''

  try {
    // الإرسال بصيغة multipart/form-data (مع مرفق اختياري)
    const fd = new FormData()
    fd.append('type', form.value.type)
    if (form.value.notes) fd.append('notes', form.value.notes)
    if (attachmentFile.value) fd.append('attachment', attachmentFile.value)

    const r = await axios.post(`${BASE}/api/v1/contractor/certificate-requests`, fd, {
      headers: apiHeaders(),
    })
    successMessage.value = r.data.message ?? 'تم تقديم طلب الشهادة بنجاح.'
    form.value = { type: '', notes: '' }
    attachmentFile.value = null
    showForm.value = false
    if (r.data.items) requests.value.unshift(r.data.items)
    setTimeout(() => { successMessage.value = '' }, 5000)
  } catch (e: any) {
    if (e?.response?.data?.error === 'profile_incomplete') {
      profileDataComplete.value = false
      missingProfileFields.value = e.response.data.errors?.missing_profile_fields ?? []
      canRequest.value = false
    }
    errorMessage.value = e?.response?.data?.message ?? 'حدث خطأ أثناء تقديم الطلب'
  } finally {
    isSubmitting.value = false
  }
}

function downloadCertificate(url: string, id: number) {
  window.open(url, '_blank')
}

const showMembershipEligibility = computed(() => form.value.type === 'membership' && membershipStatus.value !== null)
const membershipEligible = computed(() => membershipStatus.value?.eligible ?? false)

const typeLabel = computed(() => {
  const labels: Record<string, string> = {
    membership: 'شهادة الانتساب',
    good_standing: 'شهادة حسن السير',
    classification: 'شهادة التصنيف',
    experience: 'شهادة الخبرة',
  }
  return labels
})

const statusLabel: Record<string, string> = {
  pending: 'قيد المراجعة',
  approved: 'موافق عليه',
  issued: 'تم الإصدار',
  rejected: 'مرفوض',
}

const statusIcon: Record<string, any> = {
  pending: Clock,
  approved: CheckCircle2,
  issued: FileCheck,
  rejected: XCircle,
}

function fmtDate(d: string | null) {
  if (!d) return '—'
  return new Date(d).toLocaleDateString('ar-PS', { year: 'numeric', month: 'long', day: 'numeric' })
}

onMounted(async () => {
  token.value = getToken()
  if (!token.value) { authError.value = true; isLoading.value = false; return }
  await fetchData()
})

function requirementTypeLabel(type: string): string {
  const labels: Record<string, string> = {
    late_fees: 'غرامات التأخير',
    overdue_subscription: 'رسوم الاشتراك المتأخرة',
    pending_dispute: 'نزاع قيد المعالجة',
    missing_documents: 'وثائق مفقودة',
    other: 'متطلب آخر',
  }
  return labels[type] ?? type
}

function fmtMoney(v: string | number | null) {
  if (!v) return '—'
  return Number(v).toLocaleString('ar-PS') + ' ₪'
}
</script>

<template>
  <div dir="rtl" class="cr-page">
    <!-- ─── Unauthenticated ─── -->
    <div v-if="authError" class="auth-wall">
      <img src="/logo.png" alt="الاتحاد" class="aw-logo" />
      <h2>يجب تسجيل الدخول أولاً</h2>
      <p>سجّل دخولك للوصول إلى خدمة طلب الشهادات.</p>
      <RouterLink to="/contractor/login" class="aw-btn">تسجيل الدخول</RouterLink>
    </div>

    <!-- ─── Loading ─── -->
    <div v-else-if="isLoading" class="cr-loading">
      <div class="spinner" />
      <p>جاري تحميل البيانات...</p>
    </div>

    <!-- ─── Certificate Request Page ─── -->
    <template v-else>
      <!-- Header -->
      <header class="cr-header">
        <div class="cr-container hdr-inner">
          <RouterLink to="/landing" class="hdr-brand">
            <img src="/logo.png" alt="الاتحاد" class="hdr-logo" />
            <div>
              <span class="hdr-title">اتحاد المقاولين الفلسطينيين</span>
              <span class="hdr-sub">طلب شهادة العضوية</span>
            </div>
          </RouterLink>
          <div class="hdr-user" v-if="contractor">
            <div class="hdr-av">{{ contractor.name.charAt(0) }}</div>
            <div class="hdr-info">
              <span class="hdr-name">{{ contractor.name }}</span>
              <span class="hdr-mem">{{ contractor.membership_number }}</span>
            </div>
            <button class="hdr-logout" @click="logout">
              <LogOut :size="15" /> خروج
            </button>
          </div>
        </div>
      </header>

      <div class="cr-container cr-body">
        <!-- Alert Messages -->
        <div v-if="errorMessage" class="alert alert-error">
          <AlertCircle :size="18" />
          <span>{{ errorMessage }}</span>
        </div>
        <div v-if="successMessage" class="alert alert-success">
          <CheckCircle :size="18" />
          <span>{{ successMessage }}</span>
        </div>

        <!-- Profile Incomplete Section -->
        <div v-if="!profileDataComplete" class="profile-incomplete-section">
          <div class="req-header">
            <AlertCircle :size="20" class="req-icon" />
            <div>
              <h3 class="req-title">يجب إكمال الملف الشخصي أولاً</h3>
              <p class="req-desc">لا يمكن تقديم طلب شهادة قبل إكمال البيانات والمستندات التالية:</p>
            </div>
          </div>
          <div class="missing-fields-list">
            <span v-for="f in missingProfileFields" :key="f" class="missing-field-chip">{{ f }}</span>
          </div>
          <RouterLink to="/contractor/dashboard?tab=profile" class="req-note complete-profile-link">
            <ArrowRight :size="16" /> الانتقال إلى لوحتي لإكمال البيانات
          </RouterLink>
        </div>

        <!-- Requirement Issues Section -->
        <div v-if="requirementIssues.length" class="requirements-section">
          <div class="req-header">
            <AlertCircle :size="20" class="req-icon" />
            <div>
              <h3 class="req-title">متطلبات قبل الطلب</h3>
              <p class="req-desc">يجب تسوية المتطلبات التالية قبل تقديم طلب الشهادة:</p>
            </div>
          </div>
          <div class="requirements-list">
            <div v-for="issue in requirementIssues" :key="issue.type" class="req-item">
              <div class="req-item-header">
                <span class="req-type">{{ requirementTypeLabel(issue.type) }}</span>
                <span v-if="issue.amount" class="req-amount">{{ fmtMoney(issue.amount) }}</span>
              </div>
              <p class="req-description">{{ issue.description }}</p>
              <p v-if="issue.due_date" class="req-due">الموعد النهائي: {{ fmtDate(issue.due_date) }}</p>
            </div>
          </div>
          <p class="req-note">
            <ArrowRight :size="16" /> تواصل مع إدارة الاتحاد لتسوية هذه المتطلبات أو الاستفسار عن تفاصيلها.
          </p>
        </div>

        <!-- Request Form Section -->
        <div v-if="canRequest" class="form-section">
          <div class="form-header">
            <Award :size="20" />
            <div>
              <h2 class="form-title">طلب شهادة جديدة</h2>
              <p class="form-desc">اختر نوع الشهادة التي تريدها واضغط على "إرسال الطلب"</p>
            </div>
          </div>

          <form @submit.prevent="submitRequest" class="request-form">
            <!-- Certificate Type -->
            <div class="form-group">
              <label>نوع الشهادة المطلوبة *</label>
              <div class="cert-type-grid">
                <div
                  v-for="type in certificateTypes"
                  :key="type.value"
                  class="cert-type-option"
                  :class="{ selected: form.type === type.value }"
                  @click="form.type = type.value"
                >
                  <div class="cert-option-radio">
                    <input
                      type="radio"
                      :value="type.value"
                      v-model="form.type"
                      hidden
                    />
                  </div>
                  <FileCheck :size="24" class="cert-icon" />
                  <span class="cert-label">{{ type.label }}</span>
                </div>
              </div>
            </div>

            <!-- Membership 95% Eligibility Banner -->
            <div v-if="showMembershipEligibility && !membershipEligible" class="alert alert-error">
              <AlertCircle :size="18" />
              <span>يتبقى لك سداد {{ membershipStatus?.remaining_to_95_jod }} دينار للوصول إلى حد الـ 95% واستخراج شهادتك تلقائياً.</span>
            </div>
            <div v-else-if="showMembershipEligibility && membershipEligible" class="alert alert-success">
              <CheckCircle :size="18" />
              <span>نسبة سداد ذمم {{ membershipStatus?.current_year }} — {{ membershipStatus?.paid_percentage }}% — مؤهل لتقديم طلب شهادة العضوية.</span>
            </div>

            <!-- Notes -->
            <div class="form-group">
              <label>ملاحظات إضافية (اختياري)</label>
              <textarea
                v-model="form.notes"
                placeholder="مثل: شهادة معترف بها دولياً، أو أي متطلبات خاصة..."
                class="form-textarea"
              />
            </div>

            <!-- Attachment (اختياري) -->
            <div class="form-group">
              <label>مرفق داعم للطلب (اختياري — صورة أو PDF حتى 5MB)</label>
              <input
                type="file"
                accept=".png,.jpg,.jpeg,.webp,.pdf"
                class="form-textarea"
                style="min-height: auto; padding: .6rem 1rem;"
                @change="onAttachmentChange"
              />
              <p v-if="attachmentFile" class="notes-text" style="margin-top: .4rem;">
                الملف المختار: {{ attachmentFile.name }}
              </p>
            </div>

            <!-- Submit -->
            <div class="form-actions">
              <button
                type="submit"
                class="submit-btn"
                :disabled="isSubmitting || (showMembershipEligibility && !membershipEligible)"
              >
                <Send v-if="!isSubmitting" :size="16" />
                <span class="spinner-small" v-else></span>
                {{ isSubmitting ? 'جاري الإرسال...' : 'تقديم الطلب' }}
              </button>
              <button type="button" class="cancel-btn" @click="form = { type: '', notes: '' }">
                إلغاء
              </button>
            </div>
          </form>
        </div>

        <!-- Cannot Request Message -->
        <div v-else class="cannot-request-section">
          <XCircle :size="48" class="cannot-icon" />
          <h3>لا يمكن تقديم طلب جديد</h3>
          <p>عذراً، لا يمكنك تقديم طلب شهادة جديدة حالياً. تواصل مع إدارة الاتحاد للمساعدة.</p>
        </div>

        <!-- Previous Requests Section -->
        <div class="requests-section">
          <h3 class="section-title">
            <FileCheck :size="20" />
            <span>الطلبات السابقة</span>
          </h3>

          <div v-if="!requests.length" class="empty-state">
            <File :size="44" class="es-ico" />
            <p>لا توجد طلبات سابقة</p>
            <p class="es-sub">سيظهر هنا جميع طلبات الشهادات التي قدمتها</p>
          </div>

          <div v-else class="requests-list">
            <div v-for="req in requests" :key="req.id" class="request-card">
              <div class="request-header">
                <div class="request-info">
                  <h4 class="request-type">{{ typeLabel[req.type] ?? req.type }}</h4>
                  <p class="request-date">{{ fmtDate(req.request_date) }}</p>
                </div>
                <div class="status-wrapper">
                  <component :is="statusIcon[req.status] ?? FileCheck" :size="20" class="status-icon" :class="req.status" />
                  <span class="status-text" :class="req.status">{{ statusLabel[req.status] ?? req.status }}</span>
                </div>
              </div>

              <div class="request-body">
                <div v-if="req.notes" class="request-notes">
                  <p class="notes-label">الملاحظات:</p>
                  <p class="notes-text">{{ req.notes }}</p>
                </div>

                <div v-if="req.reject_reason" class="rejection-notice">
                  <XCircle :size="16" />
                  <div>
                    <p class="rejection-label">سبب الرفض:</p>
                    <p class="rejection-reason">{{ req.reject_reason }}</p>
                  </div>
                </div>
              </div>

              <div class="request-footer">
                <div class="footer-meta">
                  <span v-if="req.issue_date" class="meta-item">
                    <span class="label">تاريخ الإصدار:</span>
                    <span class="value">{{ fmtDate(req.issue_date) }}</span>
                  </span>
                  <span class="meta-item">
                    <span class="label">الحالة:</span>
                    <span class="value" :class="req.status">{{ statusLabel[req.status] ?? req.status }}</span>
                  </span>
                </div>

                <div v-if="req.certificate_url" class="download-action">
                  <button class="download-btn" @click="downloadCertificate(req.certificate_url, req.id)">
                    <Download :size="16" />
                    تحميل الشهادة
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Footer -->
      <footer class="cr-footer">
        <p>اتحاد المقاولين الفلسطينيين © {{ new Date().getFullYear() }}</p>
      </footer>
    </template>
  </div>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800&display=swap');

:global(:root) {
  --navy: #1a237e;
  --navy-mid: #3949ab;
  --navy-light: #e8eaf6;
  --navy-soft: #f5f7ff;
  --green: #2e7d32;
  --green-light: #e8f5e9;
  --red: #c62828;
  --red-light: #fce4ec;
  --text-h: #1a1a3e;
  --text-b: #424242;
  --text-m: #757575;
  --border: #e0e0e0;
}

* { box-sizing: border-box; margin: 0; padding: 0; }

.cr-page { font-family: 'Tajawal', sans-serif; direction: rtl; min-height: 100vh; background: #f5f7ff; color: var(--text-b); }
.cr-container { max-width: 1000px; margin: 0 auto; padding: 0 1.5rem; }

/* Auth & Loading */
.auth-wall { display: flex; flex-direction: column; align-items: center; justify-content: center; min-height: 100vh; gap: 1rem; padding: 2rem; text-align: center; }
.aw-logo { height: 80px; margin-bottom: .5rem; }
.auth-wall h2 { font-size: 1.5rem; font-weight: 800; color: var(--text-h); }
.auth-wall p { color: var(--text-m); font-size: .95rem; }
.aw-btn { background: var(--navy); color: #fff; border: none; border-radius: 10px; padding: .75rem 2rem; font-size: .95rem; font-weight: 700; text-decoration: none; font-family: inherit; cursor: pointer; }

.cr-loading { display: flex; flex-direction: column; align-items: center; justify-content: center; min-height: 100vh; gap: 1rem; color: var(--text-m); }
.spinner { width: 40px; height: 40px; border: 3px solid var(--navy-light); border-top-color: var(--navy); border-radius: 50%; animation: spin .8s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }

/* Header */
.cr-header { background: var(--navy); padding: .85rem 0; position: sticky; top: 0; z-index: 100; }
.hdr-inner { display: flex; align-items: center; justify-content: space-between; gap: 1rem; }
.hdr-brand { display: flex; align-items: center; gap: .65rem; text-decoration: none; }
.hdr-logo { height: 42px; filter: brightness(0) invert(1); }
.hdr-title { display: block; font-size: .88rem; font-weight: 800; color: #fff; }
.hdr-sub { display: block; font-size: .7rem; color: rgba(255,255,255,.6); }
.hdr-user { display: flex; align-items: center; gap: .75rem; }
.hdr-av { width: 36px; height: 36px; border-radius: 50%; background: rgba(255,255,255,.2); border: 2px solid rgba(255,255,255,.4); color: #fff; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.hdr-name { display: block; font-size: .85rem; font-weight: 700; color: #fff; }
.hdr-mem { display: block; font-size: .72rem; color: rgba(255,255,255,.6); }
.hdr-logout { display: flex; align-items: center; gap: .35rem; background: rgba(255,255,255,.12); border: 1px solid rgba(255,255,255,.25); border-radius: 7px; color: rgba(255,255,255,.85); font-size: .8rem; padding: .4rem .9rem; cursor: pointer; font-family: inherit; transition: background .2s; }
.hdr-logout:hover { background: rgba(255,255,255,.22); }

/* Body */
.cr-body { padding: 2rem 1.5rem 3rem; }

/* Alerts */
.alert { display: flex; align-items: center; gap: 1rem; padding: 1rem 1.25rem; border-radius: 12px; margin-bottom: 1.5rem; font-size: .95rem; font-weight: 600; }
.alert-error { background: var(--red-light); color: var(--red); border: 1px solid #ef9a9a; }
.alert-success { background: var(--green-light); color: var(--green); border: 1px solid #a5d6a7; }

/* Profile Incomplete Section */
.profile-incomplete-section { background: var(--red-light); border: 1.5px solid #ef9a9a; border-radius: 14px; padding: 1.5rem; margin-bottom: 2rem; }
.profile-incomplete-section .req-icon { color: var(--red); }
.profile-incomplete-section .req-desc { color: var(--red); }
.missing-fields-list { display: flex; flex-wrap: wrap; gap: .5rem; margin-bottom: 1rem; }
.missing-field-chip { background: #fff; border: 1px solid #ef9a9a; color: var(--red); font-size: .8rem; font-weight: 700; padding: .35rem .8rem; border-radius: 50px; }
.complete-profile-link { text-decoration: none; color: var(--red); background: rgba(198,40,40,.06); cursor: pointer; }
.complete-profile-link:hover { text-decoration: underline; }

/* Requirements Section */
.requirements-section { background: #fff8e1; border: 1.5px solid #ffca28; border-radius: 14px; padding: 1.5rem; margin-bottom: 2rem; }
.req-header { display: flex; gap: 1rem; align-items: flex-start; margin-bottom: 1.5rem; }
.req-icon { color: #e65100; flex-shrink: 0; }
.req-title { font-size: 1rem; font-weight: 800; color: var(--text-h); margin-bottom: .25rem; }
.req-desc { font-size: .9rem; color: #e65100; }

.requirements-list { display: flex; flex-direction: column; gap: 1rem; margin-bottom: 1rem; }
.req-item { background: #fff; border-radius: 10px; padding: 1rem; border-left: 4px solid #ffca28; }
.req-item-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: .5rem; }
.req-type { font-weight: 700; color: var(--text-h); }
.req-amount { font-size: .95rem; font-weight: 800; color: var(--red); }
.req-description { font-size: .9rem; color: var(--text-m); line-height: 1.5; margin-bottom: .35rem; }
.req-due { font-size: .82rem; color: var(--text-m); }

.req-note { display: flex; align-items: flex-start; gap: .75rem; font-size: .9rem; color: #e65100; background: rgba(230, 81, 0, .05); padding: .75rem; border-radius: 8px; }

/* Form Section */
.form-section, .cannot-request-section { background: #fff; border: 1.5px solid var(--border); border-radius: 16px; padding: 2rem; margin-bottom: 2rem; }
.form-header { display: flex; align-items: flex-start; gap: 1rem; margin-bottom: 1.75rem; padding-bottom: 1rem; border-bottom: 2px solid var(--navy-light); }
.form-header svg { color: var(--navy); flex-shrink: 0; }
.form-title { font-size: 1.1rem; font-weight: 800; color: var(--text-h); margin-bottom: .25rem; }
.form-desc { font-size: .9rem; color: var(--text-m); }

.request-form { display: flex; flex-direction: column; gap: 1.5rem; }
.form-group { display: flex; flex-direction: column; gap: .75rem; }
.form-group label { font-size: .85rem; font-weight: 700; color: var(--text-h); }

.cert-type-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem; }
.cert-type-option { padding: 1.25rem; border: 2px solid var(--border); border-radius: 12px; cursor: pointer; text-align: center; transition: all .2s; }
.cert-type-option:hover { border-color: var(--navy); background: var(--navy-soft); }
.cert-type-option.selected { border-color: var(--navy); background: var(--navy-light); }
.cert-option-radio { margin-bottom: .75rem; }
.cert-icon { color: var(--navy); margin: 0 auto .5rem; display: block; }
.cert-label { display: block; font-size: .9rem; font-weight: 700; color: var(--text-h); }

.form-textarea { font-family: inherit; font-size: .95rem; padding: .75rem 1rem; border: 1.5px solid var(--border); border-radius: 10px; resize: vertical; min-height: 100px; transition: border-color .2s; }
.form-textarea:focus { outline: none; border-color: var(--navy); }

.form-actions { display: flex; gap: 1rem; }
.submit-btn, .cancel-btn { padding: .875rem 1.5rem; border-radius: 10px; font-size: .95rem; font-weight: 700; cursor: pointer; font-family: inherit; border: none; display: flex; align-items: center; justify-content: center; gap: .5rem; transition: all .2s; }
.submit-btn { background: var(--navy); color: #fff; flex: 1; }
.submit-btn:hover:not(:disabled) { background: var(--navy-mid); }
.submit-btn:disabled { opacity: .6; cursor: not-allowed; }
.cancel-btn { background: #f0f0f0; color: var(--text-b); }
.cancel-btn:hover { background: #e0e0e0; }
.spinner-small { width: 16px; height: 16px; border: 2px solid rgba(255,255,255,.3); border-top-color: #fff; border-radius: 50%; animation: spin .8s linear infinite; }

/* Cannot Request */
.cannot-request-section { text-align: center; }
.cannot-icon { width: 48px; height: 48px; color: var(--red); margin: 0 auto 1rem; }
.cannot-request-section h3 { font-size: 1.1rem; font-weight: 800; color: var(--text-h); margin-bottom: .5rem; }
.cannot-request-section p { color: var(--text-m); font-size: .95rem; }

/* Requests Section */
.requests-section { background: #fff; border: 1.5px solid var(--border); border-radius: 16px; padding: 2rem; }
.section-title { display: flex; align-items: center; gap: .75rem; font-size: 1.1rem; font-weight: 800; color: var(--text-h); margin-bottom: 1.75rem; }

/* Empty State */
.empty-state { text-align: center; padding: 3rem 2rem; }
.es-ico { width: 48px; height: 48px; color: var(--text-m); margin: 0 auto 1rem; opacity: .4; }
.empty-state p { font-size: .95rem; font-weight: 600; color: var(--text-h); margin-bottom: .5rem; }
.es-sub { font-size: .85rem; color: var(--text-m); }

/* Requests List */
.requests-list { display: flex; flex-direction: column; gap: 1rem; }
.request-card { border: 1.5px solid var(--border); border-radius: 12px; padding: 1.5rem; transition: all .2s; }
.request-card:hover { box-shadow: 0 4px 16px rgba(26,35,126,.08); border-color: #c5cae9; }

.request-header { display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem; margin-bottom: 1rem; }
.request-info { flex: 1; }
.request-type { font-size: 1rem; font-weight: 800; color: var(--text-h); margin-bottom: .25rem; }
.request-date { font-size: .82rem; color: var(--text-m); }

.status-wrapper { display: flex; align-items: center; gap: .5rem; }
.status-icon { flex-shrink: 0; }
.status-icon.pending { color: #e65100; }
.status-icon.approved { color: var(--navy); }
.status-icon.issued { color: var(--green); }
.status-icon.rejected { color: var(--red); }

.status-text { font-size: .85rem; font-weight: 700; padding: .2rem .75rem; border-radius: 50px; }
.status-text.pending { background: #fff8e1; color: #e65100; }
.status-text.approved { background: var(--navy-light); color: var(--navy); }
.status-text.issued { background: var(--green-light); color: var(--green); }
.status-text.rejected { background: var(--red-light); color: var(--red); }

.request-body { margin: 1rem 0; }
.request-notes { background: var(--navy-soft); padding: 1rem; border-radius: 10px; margin-bottom: 1rem; }
.notes-label { font-size: .82rem; font-weight: 800; color: var(--navy); text-transform: uppercase; margin-bottom: .3rem; }
.notes-text { font-size: .9rem; color: var(--text-b); line-height: 1.5; }

.rejection-notice { display: flex; align-items: flex-start; gap: .75rem; background: var(--red-light); padding: 1rem; border-radius: 10px; color: var(--red); }
.rejection-label { font-size: .82rem; font-weight: 800; text-transform: uppercase; margin-bottom: .3rem; }
.rejection-reason { font-size: .9rem; line-height: 1.5; }

.request-footer { display: flex; align-items: center; justify-content: space-between; padding-top: 1rem; border-top: 1px solid var(--border); }
.footer-meta { display: flex; gap: 1.5rem; font-size: .85rem; }
.meta-item { display: flex; align-items: center; gap: .35rem; }
.meta-item .label { color: var(--text-m); font-weight: 600; }
.meta-item .value { color: var(--text-h); font-weight: 700; }

.download-action {}
.download-btn { display: flex; align-items: center; gap: .5rem; background: var(--green); color: #fff; border: none; padding: .6rem 1.25rem; border-radius: 8px; font-size: .9rem; font-weight: 700; cursor: pointer; font-family: inherit; transition: background .2s; }
.download-btn:hover { background: var(--green); opacity: .9; }

/* Footer */
.cr-footer { text-align: center; padding: 1.25rem; font-size: .78rem; color: var(--text-m); border-top: 1px solid var(--border); background: #fff; }

/* Responsive */
@media (max-width: 768px) {
  .hdr-info { display: none; }
  .cert-type-grid { grid-template-columns: 1fr; }
  .form-actions { flex-direction: column; }
  .request-header { flex-direction: column; }
  .request-footer { flex-direction: column; gap: 1rem; align-items: flex-start; }
}
</style>

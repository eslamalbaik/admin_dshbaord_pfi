<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import axios from 'axios'
import {
  LogOut, DollarSign, Building2, Copy, Check,
  AlertCircle, Upload, Send, Clock, CheckCircle, FileText,
  Award, CalendarDays, RefreshCw,
} from 'lucide-vue-next'

definePage({
  meta: { layout: 'pure', public: true, unauthenticatedOnly: false },
})

const router = useRouter()
const token = ref<string | null>(null)
const authError = ref(false)
const isLoading = ref(true)
const isSending = ref(false)
const copiedIban = ref<string | null>(null)

interface BankAccount {
  id: number
  bank_name: string
  bank_name_en: string | null
  logo_url: string | null
  iban: string
  account_number: string | null
  account_holder: string | null
  swift: string | null
  notes: string | null
}

interface Payment {
  id: number
  amount: string
  type: string
  status: string
  method: string
  reference_number: string | null
  receipt_image_url: string | null
  rejection_reason: string | null
  notes: string | null
  submitted_at: string | null
  confirmed_at: string | null
  paid_at: string | null
  created_at: string
}

interface Membership {
  id: number
  type: string
  status: string
  starts_at: string
  expires_at: string | null
  amount: string
  expiring_soon: boolean
}

const bankAccounts = ref<BankAccount[]>([])
const transfers = ref<Payment[]>([])
const contractor = ref<{ name: string; membership_number: string } | null>(null)
const membership = ref<Membership | null>(null)

const form = ref({
  amount: '',
  currency: 'ILS',
  receipt_file: null as File | null,
  notes: '',
})

const currencies = [
  { value: 'ILS', label: 'شيكل ₪', symbol: '₪' },
  { value: 'JOD', label: 'دينار أردني', symbol: 'د.أ' },
  { value: 'USD', label: 'دولار $', symbol: '$' },
]

const currencySymbol = computed(() =>
  currencies.find(c => c.value === form.value.currency)?.symbol ?? '₪')

// أهلية التجديد — تُمنع مع ذمم مالية غير مسدَّدة
const renewalBlocked = ref(false)
const renewalIssues = ref<{ type: string; description: string; amount: number | null }[]>([])
const outstandingDues = ref(0)

// تبديل يدوي لوضع "سداد الذمم" حين تكون العضوية لا تزال نشطة رغم وجود ذمم سابقة —
// بدونه لا طريقة لإرسال دفعة type=dues_payment ويبقى المستخدم عالقاً عند رسالة الحظر
const payDuesMode = ref(false)

// هل هذه دفعة سداد ذمم؟ (لا عضوية نشطة، أو المستخدم بدّل الوضع يدوياً) — لا تُحظر مثل دفعة التجديد
const isDuesPayment = computed(() => outstandingDues.value > 0 && (!membership.value || payDuesMode.value))

function togglePayDuesMode(on: boolean) {
  payDuesMode.value = on
  if (on) {
    form.value.amount = String(outstandingDues.value)
    form.value.currency = 'JOD'
  } else if (membership.value?.amount) {
    form.value.amount = String(Number(membership.value.amount))
  }
}

async function checkEligibility() {
  try {
    const r = await axios.get(`${BASE}/api/v1/contractor/renewal-eligibility`, { headers: apiHeaders() })
    renewalBlocked.value = !(r.data.items?.can_renew ?? true)
    renewalIssues.value = r.data.items?.issues ?? []
    outstandingDues.value = r.data.items?.outstanding_total_jod ?? 0

    // بلا عضوية نشطة: المبلغ المقترح = إجمالي الذمم (بالدينار الأردني)
    if (!membership.value && outstandingDues.value > 0 && !form.value.amount) {
      form.value.amount = String(outstandingDues.value)
      form.value.currency = 'JOD'
    }
  }
  catch {}
}

const previewImage = ref<string | null>(null)
const submitted = ref(false)
const successMessage = ref('')
const errorMessage = ref('')

const currentPayment = computed<Payment | null>(() => transfers.value[0] ?? null)

const BASE = import.meta.env.VITE_API_BASE_URL ?? ''

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

async function fetchGateway() {
  try {
    const [dashRes, bankRes, trRes] = await Promise.all([
      axios.get(`${BASE}/api/v1/contractor/dashboard`, { headers: apiHeaders() }),
      axios.get(`${BASE}/api/v1/bank-accounts`),
      axios.get(`${BASE}/api/v1/contractor/payments/transfer`, { headers: apiHeaders() }),
    ])
    contractor.value = dashRes.data.items?.contractor ?? null
    membership.value = dashRes.data.items?.membership ?? null
    bankAccounts.value = bankRes.data.items?.bank_accounts ?? []
    transfers.value = trRes.data.items ?? []
    // المبلغ يُملأ تلقائياً بقيمة اشتراك العضوية الحالية
    if (membership.value?.amount && Number(membership.value.amount) > 0)
      form.value.amount = String(Number(membership.value.amount))
  } catch (e: any) {
    if (e?.response?.status === 401) authError.value = true
  } finally {
    isLoading.value = false
  }
}

function onFileSelected(event: Event) {
  const input = event.target as HTMLInputElement
  const file = input.files?.[0]
  if (!file) return

  const okTypes = ['image/png', 'image/jpeg', 'image/jpg', 'image/webp', 'application/pdf']
  if (!okTypes.includes(file.type)) {
    errorMessage.value = 'الرجاء اختيار صورة (PNG/JPG/WebP) أو ملف PDF'
    return
  }

  form.value.receipt_file = file
  errorMessage.value = ''
  if (file.type.startsWith('image/')) {
    const reader = new FileReader()
    reader.onload = (e) => { previewImage.value = e.target?.result as string }
    reader.readAsDataURL(file)
  } else {
    previewImage.value = null
  }
}

function copyIban(iban: string) {
  navigator.clipboard.writeText(iban)
  copiedIban.value = iban
  setTimeout(() => { if (copiedIban.value === iban) copiedIban.value = null }, 2000)
}

async function submitPayment() {
  // دفعة التجديد تُحظر مع ذمم قائمة — أما دفعة سداد الذمم نفسها فمسموحة دائماً
  if (renewalBlocked.value && !isDuesPayment.value) {
    errorMessage.value = 'لا يمكن تجديد العضوية قبل تسوية الذمم المالية المستحقّة — سدّد الذمم أولاً.'
    return
  }
  if (!form.value.amount || !form.value.receipt_file) {
    errorMessage.value = 'الرجاء إدخال المبلغ وإرفاق صورة الإشعار'
    return
  }

  isSending.value = true
  errorMessage.value = ''
  successMessage.value = ''

  const fd = new FormData()
  fd.append('amount', form.value.amount)
  fd.append('currency', form.value.currency)
  fd.append('receipt_image', form.value.receipt_file)
  if (membership.value && !isDuesPayment.value) fd.append('membership_id', String(membership.value.id))
  fd.append('type', isDuesPayment.value ? 'dues_payment' : 'membership_fee')
  fd.append('notes', form.value.notes || (isDuesPayment.value ? 'سداد ذمم مالية مستحقّة' : 'دفع رسوم تجديد العضوية'))

  try {
    const r = await axios.post(`${BASE}/api/v1/contractor/payments/transfer`, fd, {
      headers: apiHeaders(),
    })
    successMessage.value = r.data.message ?? 'تم إرسال إشعار التحويل بنجاح.'
    submitted.value = true
    if (r.data.items) transfers.value.unshift(r.data.items)
    form.value = { amount: '', receipt_file: null, notes: '' }
    previewImage.value = null
  } catch (e: any) {
    if (e?.response?.status === 422 && e.response.data?.errors) {
      errorMessage.value = Object.values(e.response.data.errors).flat().join(' — ')
    } else {
      errorMessage.value = e?.response?.data?.message ?? 'حدث خطأ أثناء إرسال الطلب'
    }
  } finally {
    isSending.value = false
  }
}

onMounted(async () => {
  token.value = getToken()
  if (!token.value) { authError.value = true; isLoading.value = false; return }
  await fetchGateway()
  await checkEligibility()
})

const statusLabel: Record<string, string> = {
  pending: 'قيد المراجعة', paid: 'مؤكّد', rejected: 'مرفوض',
  refunded: 'مُعاد', failed: 'فشل',
}

function fmtDate(d: string | null) {
  if (!d) return '—'
  return new Date(d).toLocaleDateString('ar-PS', { year: 'numeric', month: 'long', day: 'numeric' })
}

function fmtMoney(v: string | number | null) {
  if (!v) return '—'
  return Number(v).toLocaleString('ar-PS') + ' ₪'
}
</script>

<template>
  <div dir="rtl" class="pg-page">
    <!-- ─── Unauthenticated ─── -->
    <div v-if="authError" class="auth-wall">
      <img src="/logo.png" alt="الاتحاد" class="aw-logo" />
      <h2>يجب تسجيل الدخول أولاً</h2>
      <p>سجّل دخولك لتجديد عضويتك ودفع رسوم الاشتراك.</p>
      <RouterLink to="/contractor/login" class="aw-btn">تسجيل الدخول</RouterLink>
    </div>

    <!-- ─── Loading ─── -->
    <div v-else-if="isLoading" class="pg-loading">
      <div class="spinner" />
      <p>جاري تحميل صفحة تجديد العضوية...</p>
    </div>

    <!-- ─── Payment Gateway ─── -->
    <template v-else>
      <!-- Header -->
      <header class="pg-header">
        <div class="pg-container hdr-inner">
          <RouterLink to="/landing" class="hdr-brand">
            <img src="/logo.png" alt="الاتحاد" class="hdr-logo" />
            <div>
              <span class="hdr-title">اتحاد المقاولين الفلسطينيين</span>
              <span class="hdr-sub">تجديد العضوية — دفع رسوم الاشتراك</span>
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

      <div class="pg-container pg-body">
        <!-- Alert Messages -->
        <div v-if="errorMessage" class="alert alert-error">
          <AlertCircle :size="18" />
          <span>{{ errorMessage }}</span>
        </div>
        <div v-if="successMessage" class="alert alert-success">
          <CheckCircle :size="18" />
          <span>{{ successMessage }}</span>
        </div>

        <!-- Membership being renewed -->
        <div v-if="membership" class="renewal-banner" :class="{ expiring: membership.expiring_soon }">
          <div class="rb-icon"><RefreshCw :size="24" /></div>
          <div class="rb-info">
            <p class="rb-title">تجديد العضوية الحالية</p>
            <p class="rb-meta">
              <Award :size="13" /> عضوية {{ membership.type === 'annual' ? 'سنوية' : membership.type === 'new' ? 'جديدة' : membership.type }}
              <span class="rb-sep">·</span>
              <CalendarDays :size="13" /> تنتهي في {{ fmtDate(membership.expires_at) }}
            </p>
            <p v-if="membership.expiring_soon" class="rb-warn">
              <AlertCircle :size="13" /> عضويتك تنتهي قريباً — سارع بالتجديد
            </p>
          </div>
          <div class="rb-amount">
            <span class="rb-amount-val">{{ fmtMoney(membership.amount) }}</span>
            <span class="rb-amount-label">رسوم الاشتراك</span>
          </div>
        </div>
        <!-- لا عضوية نشطة + عليه ذمم: سداد الذمم هو طريق تفعيل العضوية -->
        <div v-else-if="outstandingDues > 0" class="renewal-banner expiring">
          <div class="rb-icon"><AlertCircle :size="24" /></div>
          <div class="rb-info">
            <p class="rb-title">تفعيل العضوية — سداد الذمم المالية المستحقّة</p>
            <p class="rb-meta">
              عليك ذمم مالية عن سنوات سابقة. سدّدها عبر التحويل البنكي أدناه،
              وبعد تأكيد المحاسبة سيتم تفعيل/تجديد عضويتك.
            </p>
            <ul class="rb-meta" style="margin: .4rem 1rem 0 0;">
              <li v-for="(iss, i) in renewalIssues.filter(x => x.type === 'unpaid_dues')" :key="i">
                {{ iss.description }} — {{ iss.amount }} د.أ
              </li>
            </ul>
          </div>
          <div class="rb-amount">
            <span class="rb-amount-val">{{ outstandingDues }} د.أ</span>
            <span class="rb-amount-label">إجمالي الذمم المستحقّة</span>
          </div>
        </div>
        <div v-else class="alert alert-error">
          <AlertCircle :size="18" />
          <span>لا توجد عضوية مسجّلة باسمك — تواصل مع إدارة الاتحاد لتفعيل عضويتك قبل الدفع.</span>
        </div>

        <!-- عضوية نشطة لكن عليه ذمم سابقة — التجديد محظور حتى يسدّدها، فنُتيح له التبديل لوضع سداد الذمم -->
        <div v-if="membership && outstandingDues > 0" class="renewal-banner expiring" style="margin-top: .75rem;">
          <div class="rb-icon"><AlertCircle :size="24" /></div>
          <div class="rb-info">
            <p class="rb-title">عليك ذمم مالية مستحقّة من سنوات سابقة</p>
            <p class="rb-meta">
              لا يمكن دفع رسوم التجديد قبل تسويتها. اختر "سداد الذمم المستحقّة" أدناه لإرسال إشعار تحويل لتسويتها أولاً.
            </p>
          </div>
          <div class="rb-amount">
            <span class="rb-amount-val">{{ outstandingDues }} د.أ</span>
            <span class="rb-amount-label">إجمالي الذمم المستحقّة</span>
          </div>
        </div>

        <!-- Main Content -->
        <div class="pg-grid">
          <!-- Bank Info Section -->
          <section class="pg-section bank-section">
            <h2 class="pg-section-title"><Building2 :size="20" /> بيانات التحويل البنكي</h2>

            <div v-if="bankAccounts.length" class="bank-list">
              <div v-for="b in bankAccounts" :key="b.id" class="bank-card">
                <div class="bank-header">
                  <img v-if="b.logo_url" :src="b.logo_url" :alt="b.bank_name" class="bank-icon" />
                  <div v-else class="bank-icon-placeholder"><Building2 :size="32" /></div>
                  <div class="bank-info-text">
                    <p class="bank-name">{{ b.bank_name }}</p>
                    <p v-if="b.account_holder" class="account-holder">باسم: {{ b.account_holder }}</p>
                  </div>
                </div>

                <div class="bank-details">
                  <!-- IBAN -->
                  <div class="detail-item">
                    <span class="detail-label">رقم الآيبان (IBAN)</span>
                    <div class="detail-value-wrap">
                      <span class="detail-value mono" dir="ltr">{{ b.iban }}</span>
                      <button class="copy-btn" @click="copyIban(b.iban)" :title="copiedIban === b.iban ? 'تم النسخ!' : 'انسخ'">
                        <Check v-if="copiedIban === b.iban" :size="16" />
                        <Copy v-else :size="16" />
                      </button>
                    </div>
                  </div>

                  <div v-if="b.account_number" class="detail-item">
                    <span class="detail-label">رقم الحساب</span>
                    <span class="detail-value mono" dir="ltr">{{ b.account_number }}</span>
                  </div>

                  <div v-if="b.swift" class="detail-item">
                    <span class="detail-label">كود SWIFT</span>
                    <span class="detail-value mono" dir="ltr">{{ b.swift }}</span>
                  </div>

                  <div v-if="b.notes" class="detail-item">
                    <span class="detail-label">ملاحظات</span>
                    <span class="detail-value">{{ b.notes }}</span>
                  </div>
                </div>
              </div>

              <div class="bank-note">
                <AlertCircle :size="16" />
                <p>يُرجى التأكد من صحة البيانات قبل التحويل. احفظ إشعار التحويل للمراجعة.</p>
              </div>
            </div>
            <div v-else class="empty-hint">لا توجد حسابات بنكية متاحة حالياً.</div>
          </section>

          <!-- Payment Form Section -->
          <section class="pg-section payment-form-section">
            <h2 class="pg-section-title">
              <DollarSign :size="20" /> {{ isDuesPayment ? 'سداد الذمم المالية المستحقّة' : 'دفع رسوم تجديد العضوية' }}
            </h2>

            <div v-if="submitted" class="success-state">
              <CheckCircle :size="48" class="success-ico" />
              <h3>{{ isDuesPayment ? 'تم إرسال إشعار سداد الذمم بنجاح!' : 'تم إرسال طلب التجديد بنجاح!' }}</h3>
              <p>سيتم مراجعة إشعار الدفع من قبل قسم المحاسبة {{ isDuesPayment ? 'وتسوية الذمم المستحقّة' : 'وتجديد عضويتك' }} خلال 24 ساعة.</p>
              <button class="reset-btn" @click="submitted = false">إرسال إشعار آخر</button>
            </div>

            <form v-else @submit.prevent="submitPayment" class="payment-form">
              <!-- تبديل الوضع: عضوية نشطة + ذمم سابقة — التجديد محظور فنُتيح التبديل لسداد الذمم بدلاً منه -->
              <div v-if="membership && outstandingDues > 0" class="form-group">
                <label>نوع الدفعة *</label>
                <div style="display: flex; gap: .6rem;">
                  <button
                    type="button"
                    class="form-input"
                    style="cursor: pointer; text-align: center; flex: 1;"
                    :style="!payDuesMode
                      ? 'border-color: var(--navy, #1a237e); background: #e8eaf6; font-weight: 700;'
                      : ''"
                    @click="togglePayDuesMode(false)"
                  >
                    تجديد العضوية
                  </button>
                  <button
                    type="button"
                    class="form-input"
                    style="cursor: pointer; text-align: center; flex: 1;"
                    :style="payDuesMode
                      ? 'border-color: var(--navy, #1a237e); background: #e8eaf6; font-weight: 700;'
                      : ''"
                    @click="togglePayDuesMode(true)"
                  >
                    سداد الذمم المستحقّة
                  </button>
                </div>
              </div>

              <!-- تنبيه الذمم غير المسدَّدة -->
              <div
                v-if="renewalBlocked && !isDuesPayment"
                class="form-group"
                style="background: #fff3e0; border: 1.5px solid #ffb74d; border-radius: 12px; padding: 1rem;"
              >
                <strong style="color: #e65100;">لا يمكن تجديد العضوية قبل تسوية الذمم المالية المستحقّة</strong>
                <ul style="margin: .5rem 1rem 0 0; color: #6b7280; font-size: .88rem;">
                  <li v-for="(iss, i) in renewalIssues" :key="i">
                    {{ iss.description }}<template v-if="iss.amount"> — {{ iss.amount }} د.أ</template>
                  </li>
                </ul>
                <p style="margin-top: .5rem; font-size: .85rem; color: #6b7280;">
                  الإجمالي المستحق: <strong>{{ outstandingDues }} دينار أردني</strong> — يُرجى مراجعة الدائرة المالية في الاتحاد.
                </p>
              </div>

              <!-- Currency -->
              <div class="form-group">
                <label>عملة الدفع *</label>
                <div style="display: flex; gap: .6rem;">
                  <button
                    v-for="c in currencies"
                    :key="c.value"
                    type="button"
                    class="form-input"
                    style="cursor: pointer; text-align: center; flex: 1;"
                    :style="form.currency === c.value
                      ? 'border-color: var(--navy, #1a237e); background: #e8eaf6; font-weight: 700;'
                      : ''"
                    @click="form.currency = c.value"
                  >
                    {{ c.label }}
                  </button>
                </div>
                <p v-if="form.currency !== 'JOD'" style="font-size: .8rem; color: #9ca3af; margin-top: .35rem;">
                  رسوم الاشتراك مقوَّمة بالدينار الأردني — سيُحتسب المعادل بسعر الصرف المعتمد عند تأكيد المحاسب.
                </p>
              </div>

              <!-- Amount -->
              <div class="form-group">
                <label>{{ isDuesPayment ? 'المبلغ المحول (سداد الذمم)' : 'المبلغ المحول (رسوم الاشتراك)' }} *</label>
                <div class="amount-input-wrap">
                  <span class="currency-symbol">{{ currencySymbol }}</span>
                  <input
                    v-model="form.amount"
                    type="number"
                    placeholder="0.00"
                    step="0.01"
                    min="0"
                    required
                    class="form-input"
                  />
                </div>
              </div>

              <!-- Receipt -->
              <div class="form-group">
                <label>صورة إشعار التحويل *</label>
                <div class="file-upload-area" @click="$refs.fileInput?.click()">
                  <Upload :size="32" class="upload-icon" />
                  <p class="upload-text">اضغط هنا لاختيار الملف</p>
                  <p class="upload-hint">PNG, JPG, WebP أو PDF (أقصى حجم 5MB)</p>
                  <input
                    ref="fileInput"
                    type="file"
                    accept="image/*,application/pdf"
                    hidden
                    @change="onFileSelected"
                  />
                </div>

                <!-- Image Preview -->
                <div v-if="previewImage" class="image-preview">
                  <img :src="previewImage" alt="receipt" />
                  <button type="button" class="remove-btn" @click="previewImage = null; form.receipt_file = null">
                    ✕
                  </button>
                </div>
                <!-- Non-image file chip -->
                <div v-else-if="form.receipt_file" class="file-chip">
                  <FileText :size="16" />
                  <span class="file-chip-name">{{ form.receipt_file.name }}</span>
                  <button type="button" class="file-chip-remove" @click="form.receipt_file = null">✕</button>
                </div>
              </div>

              <!-- Notes -->
              <div class="form-group">
                <label>ملاحظات إضافية (اختياري)</label>
                <textarea
                  v-model="form.notes"
                  :placeholder="isDuesPayment ? 'سداد ذمم مالية مستحقّة' : 'دفع رسوم تجديد العضوية'"
                  class="form-textarea"
                />
              </div>

              <!-- Submit -->
              <button type="submit" class="submit-btn" :disabled="isSending">
                <Send v-if="!isSending" :size="16" />
                <span class="spinner-small" v-else></span>
                {{ isSending ? 'جاري الإرسال...' : (isDuesPayment ? 'إرسال إشعار سداد الذمم' : 'إرسال طلب تجديد العضوية') }}
              </button>
            </form>
          </section>
        </div>

        <!-- Current Payment Status -->
        <div v-if="currentPayment" class="pg-section current-payment">
          <h3 class="section-title"><Clock :size="18" /> آخر إشعار تم إرساله</h3>
          <div class="payment-status-card" :class="currentPayment.status">
            <div class="status-badge" :class="currentPayment.status">
              {{ statusLabel[currentPayment.status] ?? currentPayment.status }}
            </div>
            <div class="payment-details">
              <div class="detail">
                <span class="label">المبلغ</span>
                <span class="value">{{ fmtMoney(currentPayment.amount) }}</span>
              </div>
              <div class="detail">
                <span class="label">رقم المرجع</span>
                <span class="value mono">{{ currentPayment.reference_number || '—' }}</span>
              </div>
              <div class="detail">
                <span class="label">التاريخ</span>
                <span class="value">{{ fmtDate(currentPayment.submitted_at ?? currentPayment.created_at) }}</span>
              </div>
              <div v-if="currentPayment.confirmed_at" class="detail">
                <span class="label">تم التأكيد</span>
                <span class="value">{{ fmtDate(currentPayment.confirmed_at) }}</span>
              </div>
              <div v-if="currentPayment.rejection_reason" class="detail full">
                <span class="label">سبب الرفض</span>
                <span class="value">{{ currentPayment.rejection_reason }}</span>
              </div>
              <div v-if="currentPayment.notes" class="detail full">
                <span class="label">ملاحظات</span>
                <span class="value">{{ currentPayment.notes }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Footer -->
      <footer class="pg-footer">
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

.pg-page { font-family: 'Tajawal', sans-serif; direction: rtl; min-height: 100vh; background: #f5f7ff; color: var(--text-b); }
.pg-container { max-width: 1200px; margin: 0 auto; padding: 0 1.5rem; }

/* Auth & Loading */
.auth-wall { display: flex; flex-direction: column; align-items: center; justify-content: center; min-height: 100vh; gap: 1rem; padding: 2rem; text-align: center; }
.aw-logo { height: 80px; margin-bottom: .5rem; }
.auth-wall h2 { font-size: 1.5rem; font-weight: 800; color: var(--text-h); }
.auth-wall p { color: var(--text-m); font-size: .95rem; }
.aw-btn { background: var(--navy); color: #fff; border: none; border-radius: 10px; padding: .75rem 2rem; font-size: .95rem; font-weight: 700; text-decoration: none; font-family: inherit; }

.pg-loading { display: flex; flex-direction: column; align-items: center; justify-content: center; min-height: 100vh; gap: 1rem; color: var(--text-m); }
.spinner { width: 40px; height: 40px; border: 3px solid var(--navy-light); border-top-color: var(--navy); border-radius: 50%; animation: spin .8s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }

/* Header */
.pg-header { background: var(--navy); padding: .85rem 0; position: sticky; top: 0; z-index: 100; }
.hdr-inner { display: flex; align-items: center; justify-content: space-between; gap: 1rem; }
.hdr-brand { display: flex; align-items: center; gap: .65rem; text-decoration: none; }
.hdr-logo { height: 42px; filter: brightness(0) invert(1); }
.hdr-title { display: block; font-size: .88rem; font-weight: 800; color: #fff; }
.hdr-sub { display: block; font-size: .7rem; color: rgba(255,255,255,.6); }
.hdr-user { display: flex; align-items: center; gap: .75rem; }
.hdr-av { width: 36px; height: 36px; border-radius: 50%; background: rgba(255,255,255,.2); border: 2px solid rgba(255,255,255,.4); color: #fff; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.hdr-name { display: block; font-size: .85rem; font-weight: 700; color: #fff; }
.hdr-mem { display: block; font-size: .72rem; color: rgba(255,255,255,.6); }
.hdr-logout { display: flex; align-items: center; gap: .35rem; background: rgba(255,255,255,.12); border: 1px solid rgba(255,255,255,.25); border-radius: 7px; color: rgba(255,255,255,.85); font-size: .8rem; padding: .4rem .9rem; cursor: pointer; font-family: inherit; transition: background .2s; white-space: nowrap; }
.hdr-logout:hover { background: rgba(255,255,255,.22); }

/* Alerts */
.alert { display: flex; align-items: center; gap: 1rem; padding: 1rem 1.25rem; border-radius: 12px; margin-bottom: 1.5rem; font-size: .95rem; font-weight: 600; }
.alert-error { background: var(--red-light); color: var(--red); border: 1px solid #ef9a9a; }
.alert-success { background: var(--green-light); color: var(--green); border: 1px solid #a5d6a7; }

/* Renewal Banner */
.renewal-banner { display: flex; align-items: center; gap: 1.25rem; background: #fff; border: 1.5px solid var(--navy-light); border-right: 4px solid var(--navy); border-radius: 14px; padding: 1.25rem 1.5rem; margin-bottom: 1.75rem; }
.renewal-banner.expiring { border-right-color: #e65100; background: #fffdf5; }
.rb-icon { width: 48px; height: 48px; border-radius: 12px; background: var(--navy-light); color: var(--navy); display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.renewal-banner.expiring .rb-icon { background: #fff8e1; color: #e65100; }
.rb-info { flex: 1; }
.rb-title { font-size: 1rem; font-weight: 800; color: var(--text-h); margin-bottom: .25rem; }
.rb-meta { display: flex; align-items: center; gap: .4rem; font-size: .83rem; color: var(--text-m); flex-wrap: wrap; }
.rb-sep { color: var(--border); }
.rb-warn { display: flex; align-items: center; gap: .35rem; font-size: .8rem; color: #e65100; font-weight: 700; margin-top: .35rem; }
.rb-amount { text-align: center; flex-shrink: 0; }
.rb-amount-val { display: block; font-size: 1.3rem; font-weight: 800; color: var(--navy); }
.rb-amount-label { display: block; font-size: .72rem; color: var(--text-m); margin-top: .15rem; }

/* Body & Grid */
.pg-body { padding: 2rem 1.5rem 3rem; }
.pg-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-bottom: 2rem; }

/* Section */
.pg-section { background: #fff; border: 1.5px solid var(--border); border-radius: 16px; padding: 2rem; }
.pg-section-title { display: flex; align-items: center; gap: .75rem; font-size: 1.1rem; font-weight: 800; color: var(--text-h); margin-bottom: 1.75rem; padding-bottom: 1rem; border-bottom: 2px solid var(--navy-light); }

/* Bank Section */
.bank-card { background: #f5f7ff; border: 1.5px solid var(--navy-light); border-radius: 14px; padding: 1.5rem; }
.bank-header { display: flex; align-items: center; gap: 1.25rem; margin-bottom: 1.75rem; }
.bank-icon { height: 48px; width: auto; object-fit: contain; }
.bank-icon-placeholder { width: 48px; height: 48px; background: var(--navy-light); color: var(--navy); border-radius: 10px; display: flex; align-items: center; justify-content: center; }
.bank-name { font-size: 1rem; font-weight: 800; color: var(--navy); }
.account-holder { font-size: .85rem; color: var(--text-m); margin-top: .2rem; }

.bank-details { display: flex; flex-direction: column; gap: 1.5rem; margin-bottom: 1.75rem; }
.detail-item { display: flex; flex-direction: column; gap: .5rem; }
.detail-label { font-size: .82rem; font-weight: 700; color: var(--text-m); text-transform: uppercase; letter-spacing: .03em; }
.detail-value-wrap { display: flex; align-items: center; gap: .75rem; }
.detail-value { font-size: 1rem; font-weight: 600; color: var(--text-h); }
.mono { font-family: monospace; letter-spacing: .05em; font-size: .95rem; }
.copy-btn { width: 32px; height: 32px; border-radius: 8px; background: var(--navy-light); color: var(--navy); border: none; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all .2s; flex-shrink: 0; }
.copy-btn:hover { background: var(--navy); color: #fff; }

.bank-note { display: flex; align-items: flex-start; gap: .75rem; padding: 1rem; background: #fff8e1; border: 1px solid #ffca28; border-radius: 10px; color: #e65100; font-size: .85rem; line-height: 1.5; }
.bank-list { display: flex; flex-direction: column; gap: 1rem; }
.empty-hint { text-align: center; color: var(--text-m); font-size: .9rem; padding: 2rem 1rem; }
.file-chip { display: flex; align-items: center; gap: .6rem; margin-top: 1rem; padding: .65rem .9rem; background: var(--navy-light); border-radius: 10px; font-size: .85rem; color: var(--navy); }
.file-chip-name { flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.file-chip-remove { width: 24px; height: 24px; border-radius: 50%; background: rgba(198,40,40,.9); color: #fff; border: none; cursor: pointer; flex-shrink: 0; }

/* Payment Form */
.payment-form { display: flex; flex-direction: column; gap: 1.5rem; }
.form-group { display: flex; flex-direction: column; gap: .5rem; }
.form-group label { font-size: .85rem; font-weight: 700; color: var(--text-h); }
.form-input, .form-textarea { font-family: inherit; font-size: .95rem; padding: .75rem 1rem; border: 1.5px solid var(--border); border-radius: 10px; transition: border-color .2s; }
.form-input:focus, .form-textarea:focus { outline: none; border-color: var(--navy); }

.amount-input-wrap { position: relative; display: flex; align-items: center; }
.currency-symbol { position: absolute; right: 1rem; font-size: 1.1rem; font-weight: 700; color: var(--text-m); pointer-events: none; }
.amount-input-wrap .form-input { padding-right: 2.5rem; }

.file-upload-area { border: 2px dashed var(--navy-light); border-radius: 12px; padding: 2rem; text-align: center; cursor: pointer; transition: all .2s; }
.file-upload-area:hover { border-color: var(--navy); background: var(--navy-soft); }
.upload-icon { width: 48px; height: 48px; color: var(--navy); margin: 0 auto 1rem; opacity: .6; }
.upload-text { font-size: .95rem; font-weight: 700; color: var(--text-h); margin-bottom: .25rem; }
.upload-hint { font-size: .8rem; color: var(--text-m); }

.image-preview { position: relative; margin-top: 1rem; border-radius: 12px; overflow: hidden; border: 1.5px solid var(--border); }
.image-preview img { width: 100%; height: auto; display: block; max-height: 300px; object-fit: cover; }
.remove-btn { position: absolute; top: .75rem; right: .75rem; width: 32px; height: 32px; border-radius: 50%; background: rgba(198, 40, 40, .9); color: #fff; border: none; cursor: pointer; font-size: 1.2rem; display: flex; align-items: center; justify-content: center; transition: background .2s; }
.remove-btn:hover { background: var(--red); }

.form-textarea { resize: vertical; min-height: 100px; }

.submit-btn { background: var(--navy); color: #fff; border: none; padding: 1rem; border-radius: 10px; font-size: .95rem; font-weight: 700; cursor: pointer; font-family: inherit; display: flex; align-items: center; justify-content: center; gap: .5rem; transition: background .2s; width: 100%; }
.submit-btn:hover:not(:disabled) { background: var(--navy-mid); }
.submit-btn:disabled { opacity: .6; cursor: not-allowed; }
.spinner-small { width: 16px; height: 16px; border: 2px solid rgba(255,255,255,.3); border-top-color: #fff; border-radius: 50%; animation: spin .8s linear infinite; }

.success-state { text-align: center; padding: 2rem 1rem; }
.success-ico { width: 48px; height: 48px; color: var(--green); margin: 0 auto 1rem; }
.success-state h3 { font-size: 1.2rem; font-weight: 800; color: var(--text-h); margin-bottom: .5rem; }
.success-state p { color: var(--text-m); margin-bottom: 1.5rem; }
.reset-btn { background: var(--navy-light); color: var(--navy); border: 1px solid #c5cae9; padding: .75rem 1.5rem; border-radius: 8px; font-size: .9rem; font-weight: 700; cursor: pointer; font-family: inherit; transition: all .2s; }
.reset-btn:hover { background: var(--navy); color: #fff; }

/* Current Payment Section */
.current-payment { margin-top: 2rem; }
.section-title { display: flex; align-items: center; gap: .75rem; font-size: 1rem; font-weight: 800; color: var(--text-h); margin-bottom: 1.25rem; }

.payment-status-card { border: 1.5px solid var(--border); border-radius: 12px; padding: 1.5rem; background: #fff; }
.payment-status-card.pending { border-color: #ffca28; background: #fffde7; }
.payment-status-card.paid { border-color: #a5d6a7; background: var(--green-light); }
.payment-status-card.rejected { border-color: #ef9a9a; background: var(--red-light); }

.status-badge { display: inline-block; padding: .35rem .75rem; border-radius: 50px; font-size: .8rem; font-weight: 700; margin-bottom: 1rem; }
.status-badge.pending { background: #fff8e1; color: #e65100; }
.status-badge.paid { background: var(--green-light); color: var(--green); }
.status-badge.rejected { background: var(--red-light); color: var(--red); }

.payment-details { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
.detail { display: flex; flex-direction: column; gap: .25rem; }
.detail.full { grid-column: 1 / -1; }
.detail .label { font-size: .82rem; font-weight: 600; color: var(--text-m); text-transform: uppercase; letter-spacing: .02em; }
.detail .value { font-size: .95rem; font-weight: 600; color: var(--text-h); }

/* Footer */
.pg-footer { text-align: center; padding: 1.25rem; font-size: .78rem; color: var(--text-m); border-top: 1px solid var(--border); background: #fff; }

/* Responsive */
@media (max-width: 900px) {
  .pg-grid { grid-template-columns: 1fr; gap: 1.5rem; }
  .payment-details { grid-template-columns: 1fr; }
}

@media (max-width: 600px) {
  .hdr-info { display: none; }
  .pg-section { padding: 1.25rem; }
}
</style>

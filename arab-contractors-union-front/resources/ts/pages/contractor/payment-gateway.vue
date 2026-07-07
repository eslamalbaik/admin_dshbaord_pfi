<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import axios from 'axios'
import {
  ArrowRight, LogOut, DollarSign, Building2, Copy, Check,
  AlertCircle, Upload, Send, Clock, CheckCircle,
} from 'lucide-vue-next'

definePage({
  meta: { layout: 'pure', public: true, unauthenticatedOnly: false },
})

const router = useRouter()
const token = ref<string | null>(null)
const authError = ref(false)
const isLoading = ref(true)
const isSending = ref(false)
const copySuccess = ref(false)

interface UnionBank {
  bank_name: string
  bank_icon_url: string
  account_holder: string
  iban: string
  swift_code: string | null
}

interface PaymentRequest {
  id: number
  amount: string
  reference_number: string | null
  status: string
  notes: string | null
  proof_image_url: string | null
  created_at: string
  verified_at: string | null
}

const unionBank = ref<UnionBank | null>(null)
const currentPayment = ref<PaymentRequest | null>(null)
const contractor = ref<{ name: string; membership_number: string } | null>(null)

const form = ref({
  amount: '',
  proof_file: null as File | null,
  notes: '',
})

const previewImage = ref<string | null>(null)
const submitted = ref(false)
const successMessage = ref('')
const errorMessage = ref('')

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

async function fetchPaymentGateway() {
  try {
    const r = await axios.get(`${BASE}/api/v1/contractor/payment-gateway`, { headers: apiHeaders() })
    unionBank.value = r.data.items?.union_bank ?? null
    currentPayment.value = r.data.items?.current_payment ?? null
    contractor.value = r.data.items?.contractor ?? null
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

  if (!file.type.startsWith('image/')) {
    errorMessage.value = 'الرجاء اختيار صورة فقط'
    return
  }

  form.value.proof_file = file
  const reader = new FileReader()
  reader.onload = (e) => {
    previewImage.value = e.target?.result as string
  }
  reader.readAsDataURL(file)
  errorMessage.value = ''
}

function copyIBAN() {
  if (!unionBank.value?.iban) return
  navigator.clipboard.writeText(unionBank.value.iban)
  copySuccess.value = true
  setTimeout(() => { copySuccess.value = false }, 2000)
}

async function submitPayment() {
  if (!form.value.amount || !form.value.proof_file) {
    errorMessage.value = 'الرجاء ملء جميع الحقول المطلوبة'
    return
  }

  isSending.value = true
  errorMessage.value = ''
  successMessage.value = ''

  const formData = new FormData()
  formData.append('amount', form.value.amount)
  formData.append('proof_image', form.value.proof_file)
  if (form.value.notes) formData.append('notes', form.value.notes)

  try {
    const r = await axios.post(`${BASE}/api/v1/contractor/payment-submit`, formData, {
      headers: { ...apiHeaders(), 'Content-Type': 'multipart/form-data' },
    })
    successMessage.value = 'تم إرسال إشعار الدفع بنجاح! سيتم التحقق منها قريباً.'
    submitted.value = true
    form.value = { amount: '', proof_file: null, notes: '' }
    previewImage.value = null
    currentPayment.value = r.data.items?.payment ?? null
  } catch (e: any) {
    errorMessage.value = e?.response?.data?.message ?? 'حدث خطأ أثناء إرسال الطلب'
  } finally {
    isSending.value = false
  }
}

onMounted(async () => {
  token.value = getToken()
  if (!token.value) { authError.value = true; isLoading.value = false; return }
  await fetchPaymentGateway()
})
</script>

<template>
  <div dir="rtl" class="pg-page">
    <!-- ─── Unauthenticated ─── -->
    <div v-if="authError" class="auth-wall">
      <img src="/logo.png" alt="الاتحاد" class="aw-logo" />
      <h2>يجب تسجيل الدخول أولاً</h2>
      <p>سجّل دخولك من الصفحة الرئيسية للوصول إلى بوابة الدفع.</p>
      <RouterLink to="/landing" class="aw-btn">العودة للصفحة الرئيسية</RouterLink>
    </div>

    <!-- ─── Loading ─── -->
    <div v-else-if="isLoading" class="pg-loading">
      <div class="spinner" />
      <p>جاري تحميل بوابة الدفع...</p>
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
              <span class="hdr-sub">بوابة الدفع</span>
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

        <!-- Main Content -->
        <div class="pg-grid">
          <!-- Bank Info Section -->
          <section class="pg-section bank-section">
            <h2 class="pg-section-title"><Building2 :size="20" /> بيانات التحويل البنكي</h2>

            <div v-if="unionBank" class="bank-card">
              <div class="bank-header">
                <img v-if="unionBank.bank_icon_url" :src="unionBank.bank_icon_url" :alt="unionBank.bank_name" class="bank-icon" />
                <div v-else class="bank-icon-placeholder"><Building2 :size="32" /></div>
                <div class="bank-info-text">
                  <p class="bank-name">{{ unionBank.bank_name }}</p>
                  <p class="account-holder">باسم: {{ unionBank.account_holder }}</p>
                </div>
              </div>

              <div class="bank-details">
                <!-- IBAN -->
                <div class="detail-item">
                  <span class="detail-label">رقم الحساب (IBAN)</span>
                  <div class="detail-value-wrap">
                    <span class="detail-value mono" dir="ltr">{{ unionBank.iban }}</span>
                    <button class="copy-btn" @click="copyIBAN" :title="copySuccess ? 'تم النسخ!' : 'انسخ'">
                      <Check v-if="copySuccess" :size="16" />
                      <Copy v-else :size="16" />
                    </button>
                  </div>
                </div>

                <!-- SWIFT (if available) -->
                <div v-if="unionBank.swift_code" class="detail-item">
                  <span class="detail-label">كود SWIFT</span>
                  <span class="detail-value mono" dir="ltr">{{ unionBank.swift_code }}</span>
                </div>
              </div>

              <div class="bank-note">
                <AlertCircle :size="16" />
                <p>يُرجى التأكد من صحة البيانات قبل التحويل. احفظ إشعار التحويل للمراجعة.</p>
              </div>
            </div>
          </section>

          <!-- Payment Form Section -->
          <section class="pg-section payment-form-section">
            <h2 class="pg-section-title"><DollarSign :size="20" /> تسجيل الدفع</h2>

            <div v-if="submitted" class="success-state">
              <CheckCircle :size="48" class="success-ico" />
              <h3>تم الإرسال بنجاح!</h3>
              <p>سيتم مراجعة إشعار الدفع الخاص بك من قبل قسم المحاسبة خلال 24 ساعة.</p>
              <button class="reset-btn" @click="submitted = false">إرسال دفع آخر</button>
            </div>

            <form v-else @submit.prevent="submitPayment" class="payment-form">
              <!-- Amount -->
              <div class="form-group">
                <label>المبلغ المحول *</label>
                <div class="amount-input-wrap">
                  <span class="currency-symbol">₪</span>
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

              <!-- Proof Image -->
              <div class="form-group">
                <label>صورة إشعار التحويل *</label>
                <div class="file-upload-area" @click="$refs.fileInput?.click()">
                  <Upload :size="32" class="upload-icon" />
                  <p class="upload-text">اضغط هنا أو اسحب الصورة</p>
                  <p class="upload-hint">PNG, JPG أو WebP (أقصى حجم 5MB)</p>
                  <input
                    ref="fileInput"
                    type="file"
                    accept="image/*"
                    hidden
                    @change="onFileSelected"
                  />
                </div>

                <!-- Image Preview -->
                <div v-if="previewImage" class="image-preview">
                  <img :src="previewImage" :alt="form.amount" />
                  <button type="button" class="remove-btn" @click="previewImage = null; form.proof_file = null">
                    ✕
                  </button>
                </div>
              </div>

              <!-- Notes -->
              <div class="form-group">
                <label>ملاحظات إضافية (اختياري)</label>
                <textarea
                  v-model="form.notes"
                  placeholder="مثل: تحويل لتجديد الاشتراك..."
                  class="form-textarea"
                />
              </div>

              <!-- Submit -->
              <button type="submit" class="submit-btn" :disabled="isSending">
                <Send v-if="!isSending" :size="16" />
                <span class="spinner-small" v-else></span>
                {{ isSending ? 'جاري الإرسال...' : 'إرسال إشعار الدفع' }}
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
                <span class="value">{{ fmtDate(currentPayment.created_at) }}</span>
              </div>
              <div v-if="currentPayment.verified_at" class="detail">
                <span class="label">تم التحقق</span>
                <span class="value">{{ fmtDate(currentPayment.verified_at) }}</span>
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

<script setup lang="ts">
const statusLabel: Record<string, string> = {
  pending: 'قيد الانتظار', verified: 'موثّق', rejected: 'مرفوض',
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
.payment-status-card.verified { border-color: #a5d6a7; background: var(--green-light); }
.payment-status-card.rejected { border-color: #ef9a9a; background: var(--red-light); }

.status-badge { display: inline-block; padding: .35rem .75rem; border-radius: 50px; font-size: .8rem; font-weight: 700; margin-bottom: 1rem; }
.status-badge.pending { background: #fff8e1; color: #e65100; }
.status-badge.verified { background: var(--green-light); color: var(--green); }
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

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { watch } from 'vue'
import axios from 'axios'
import {
  LogOut, Send, CheckCircle, AlertCircle, MessageSquare,
  Phone, Mail, MessageCircle, FileText,
} from 'lucide-vue-next'

definePage({
  meta: { layout: 'pure', public: true, unauthenticatedOnly: false },
})

const router = useRouter()
const token = ref<string | null>(null)
const authError = ref(false)
const isLoading = ref(true)
const isSending = ref(false)

interface SupportTicketMessage {
  id: number
  sender_type: 'contractor' | 'admin'
  sender_name: string | null
  message: string
  attachment_url: string | null
  created_at: string
}

interface SupportTicket {
  id: number
  subject: string
  category: string
  category_label: string
  message: string
  attachment_url: string | null
  status: string
  status_label: string
  reply: string | null
  replied_by: string | null
  replied_at: string | null
  created_at: string
  messages?: SupportTicketMessage[]
}

interface Contractor {
  name: string
  membership_number: string
  email?: string
  phone?: string
}

const contractor = ref<Contractor | null>(null)
const tickets = ref<SupportTicket[]>([])
const activeTab = ref<'new' | 'tickets'>('new')
const showSuccess = ref(false)
const errorMessage = ref('')
// Ticket detail view state
const selectedTicket = ref<SupportTicket | null>(null)
const isTicketDetailOpen = ref(false)
const replyMessage = ref('')
const isReplySending = ref(false)
const replyError = ref('')

const form = ref({
  subject: '',
  category: '',
  message: '',
  attachment: null as File | null,
})

const categories = [
  { value: 'technical', label: 'مشكلة فنية' },
  { value: 'complaint', label: 'شكوى' },
  { value: 'inquiry', label: 'استفسار' },
  { value: 'suggestion', label: 'اقتراح' },
  { value: 'other', label: 'أخرى' },
]

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

async function fetchData() {
  try {
    const [dashRes, tRes] = await Promise.all([
      axios.get(`${BASE}/api/v1/contractor/dashboard`, { headers: apiHeaders() }),
      axios.get(`${BASE}/api/v1/contractor/support-tickets`, { headers: apiHeaders() }),
    ])
    contractor.value = dashRes.data.items?.contractor ?? null
    tickets.value = tRes.data.items ?? []
  } catch (e: any) {
    if (e?.response?.status === 401) authError.value = true
  } finally {
    isLoading.value = false
  }
}

function onAttachmentSelected(event: Event) {
  const input = event.target as HTMLInputElement
  const file = input.files?.[0]
  if (!file) return

  const okTypes = ['image/png', 'image/jpeg', 'image/jpg', 'image/webp', 'application/pdf']
  if (!okTypes.includes(file.type)) {
    errorMessage.value = 'الرجاء اختيار صورة (PNG/JPG/WebP) أو ملف PDF'
    return
  }
  if (file.size > 5 * 1024 * 1024) {
    errorMessage.value = `الملف ${file.name} كبير جداً (أقصى 5MB)`
    return
  }
  form.value.attachment = file
  errorMessage.value = ''
}

function removeAttachment() {
  form.value.attachment = null
}

async function submitTicket() {
  if (!form.value.subject || !form.value.category || !form.value.message) {
    errorMessage.value = 'الرجاء ملء جميع الحقول المطلوبة'
    return
  }

  isSending.value = true
  errorMessage.value = ''

  const formData = new FormData()
  formData.append('subject', form.value.subject)
  formData.append('category', form.value.category)
  formData.append('message', form.value.message)
  if (form.value.attachment) formData.append('attachment', form.value.attachment)

  try {
    const r = await axios.post(`${BASE}/api/v1/contractor/support-tickets`, formData, {
      headers: apiHeaders(),
    })
    showSuccess.value = true
    form.value = { subject: '', category: '', message: '', attachment: null }
    if (r.data.items) tickets.value.unshift(r.data.items)
    setTimeout(() => { activeTab.value = 'tickets'; showSuccess.value = false }, 2000)
  } catch (e: any) {
    if (e?.response?.status === 422 && e.response.data?.errors) {
      errorMessage.value = Object.values(e.response.data.errors).flat().join(' — ')
    } else {
      errorMessage.value = e?.response?.data?.message ?? 'حدث خطأ أثناء إنشاء الطلب'
    }
  } finally {
    isSending.value = false
  }
}

async function sendReply() {
  if (!selectedTicket.value || !replyMessage.value.trim()) return

  isReplySending.value = true
  replyError.value = ''

  try {
    const r = await axios.post(
      `${BASE}/api/v1/contractor/support-tickets/${selectedTicket.value.id}/reply`,
      { message: replyMessage.value },
      { headers: apiHeaders() },
    )
    const updated = r.data.items as SupportTicket
    selectedTicket.value = updated
    const idx = tickets.value.findIndex(t => t.id === updated.id)
    if (idx !== -1) tickets.value[idx] = updated
    replyMessage.value = ''
  } catch (e: any) {
    replyError.value = e?.response?.data?.message ?? 'تعذّر إرسال رسالتك'
  } finally {
    isReplySending.value = false
  }
}

function openWhatsApp() {
  const message = `مرحباً، أنا ${contractor.value?.name} (رقم العضوية: ${contractor.value?.membership_number}) بحاجة إلى دعم فني.`
  const encoded = encodeURIComponent(message)
  window.open(`https://wa.me/970200000000?text=${encoded}`, '_blank')
}

// ─── حفظ الحالة في رابط الصفحة: التبويب والتذكرة المفتوحة تنجوان من الـ refresh ───
const route = useRoute()

function switchTab(tab: 'new' | 'tickets') {
  activeTab.value = tab
  router.replace({ query: { ...route.query, tab, ticket: undefined } })
}

onMounted(async () => {
  token.value = getToken()
  if (!token.value) { authError.value = true; isLoading.value = false; return }

  // استرجاع الحالة من الرابط قبل جلب البيانات
  if (route.query.tab === 'tickets')
    activeTab.value = 'tickets'

  await fetchData()

  // إعادة فتح التذكرة التي كانت مفتوحة قبل الـ refresh
  const ticketId = Number(route.query.ticket)
  if (ticketId) {
    const t = tickets.value.find(x => x.id === ticketId)
    if (t) {
      activeTab.value = 'tickets'
      selectedTicket.value = t
      isTicketDetailOpen.value = true
    }
  }
})

function statusClass(s: string) {
  if (s === 'answered') return 'resolved'
  if (s === 'closed') return 'closed'
  if (s === 'in_progress') return 'in_progress'
  return 'open'
}

function formatDate(date: string) {
  return new Date(date).toLocaleDateString('ar-PS', { year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit' })
}

// --- Ticket detail modal --- (الحالة معرّفة أعلى الملف)
function openTicketDetail(ticket: SupportTicket) {
  selectedTicket.value = ticket
  isTicketDetailOpen.value = true
  replyMessage.value = ''
  replyError.value = ''
  // تثبيت رقم التذكرة في الرابط حتى تبقى مفتوحة بعد الـ refresh
  router.replace({ query: { ...route.query, tab: 'tickets', ticket: String(ticket.id) } })
}

function closeTicketDetail() {
  selectedTicket.value = null
  isTicketDetailOpen.value = false
  router.replace({ query: { ...route.query, ticket: undefined } })
}
</script>

<template>
  <div dir="rtl" class="sp-page">
    <!-- ─── Unauthenticated ─── -->
    <div v-if="authError" class="auth-wall">
      <img src="/logo.png" alt="الاتحاد" class="aw-logo" />
      <h2>يجب تسجيل الدخول أولاً</h2>
      <p>سجّل دخولك للوصول إلى خدمة الدعم الفني.</p>
      <RouterLink to="/contractor/login" class="aw-btn">تسجيل الدخول</RouterLink>
    </div>

    <!-- ─── Loading ─── -->
    <div v-else-if="isLoading" class="sp-loading">
      <div class="spinner" />
      <p>جاري تحميل خدمة الدعم...</p>
    </div>

    <!-- ─── Support Page ─── -->
    <template v-else>
      <!-- Header -->
      <header class="sp-header">
        <div class="sp-container hdr-inner">
          <RouterLink to="/landing" class="hdr-brand">
            <img src="/logo.png" alt="الاتحاد" class="hdr-logo" />
            <div>
              <span class="hdr-title">اتحاد المقاولين الفلسطينيين</span>
              <span class="hdr-sub">الدعم الفني والشكاوي</span>
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

      <div class="sp-container sp-body">
        <!-- Quick Contact Section -->
        <div class="quick-contact">
          <div class="qc-card">
            <Phone :size="24" class="qc-icon" />
            <div>
              <p class="qc-label">الهاتف</p>
              <a href="tel:+97020000000" class="qc-value">+970 2 000 0000</a>
            </div>
          </div>
          <div class="qc-card">
            <Mail :size="24" class="qc-icon" />
            <div>
              <p class="qc-label">البريد الإلكتروني</p>
              <a href="mailto:support@pcu.ps" class="qc-value">support@pcu.ps</a>
            </div>
          </div>
          <div class="qc-card whatsapp-card" @click="openWhatsApp">
            <MessageCircle :size="24" class="qc-icon" />
            <div>
              <p class="qc-label">واتساب</p>
              <p class="qc-value">تواصل معنا الآن</p>
            </div>
          </div>
        </div>

        <!-- Tabs -->
        <div class="tabs-header">
          <button
            class="tab-btn"
            :class="{ active: activeTab === 'new' }"
            @click="activeTab = 'new'"
          >
            <Send :size="16" />
            <span>إنشاء طلب جديد</span>
          </button>
          <button
            class="tab-btn"
            :class="{ active: activeTab === 'tickets' }"
            @click="activeTab = 'tickets'"
          >
            <FileText :size="16" />
            <span>الطلبات السابقة</span>
            <span class="badge">{{ tickets.length }}</span>
          </button>
        </div>

        <!-- Alert Messages -->
        <div v-if="errorMessage" class="alert alert-error">
          <AlertCircle :size="18" />
          <span>{{ errorMessage }}</span>
        </div>
        <div v-if="showSuccess" class="alert alert-success">
          <CheckCircle :size="18" />
          <span>تم إنشاء طلبك بنجاح! سيتم الرد عليك في أقرب وقت.</span>
        </div>

        <!-- Tab Content: New Ticket -->
        <div v-if="activeTab === 'new'" class="tab-content">
          <form @submit.prevent="submitTicket" class="support-form">
            <!-- Subject -->
            <div class="form-group">
              <label>الموضوع *</label>
              <input
                v-model="form.subject"
                type="text"
                placeholder="ملخص المشكلة أو الشكوى"
                required
                class="form-input"
              />
            </div>

            <!-- Category -->
            <div class="form-group">
              <label>النوع *</label>
              <select v-model="form.category" required class="form-input">
                <option value="">اختر النوع...</option>
                <option v-for="cat in categories" :key="cat.value" :value="cat.value">
                  {{ cat.label }}
                </option>
              </select>
            </div>

            <!-- Message -->
            <div class="form-group full">
              <label>التفاصيل *</label>
              <textarea
                v-model="form.message"
                placeholder="شرح تفصيلي للمشكلة أو الشكوى..."
                required
                rows="8"
                class="form-textarea"
              />
            </div>

            <!-- Attachment (single) -->
            <div class="form-group full">
              <label>مرفق (اختياري)</label>
              <div class="file-upload-area" @click="$refs.fileInput?.click()">
                <FileText :size="32" class="upload-icon" />
                <p class="upload-text">اضغط لاختيار ملف</p>
                <p class="upload-hint">PNG, JPG, WebP أو PDF · أقصى حجم 5MB</p>
                <input
                  ref="fileInput"
                  type="file"
                  accept="image/*,application/pdf"
                  hidden
                  @change="onAttachmentSelected"
                />
              </div>

              <!-- Attachment chip -->
              <div v-if="form.attachment" class="attachments-list">
                <div class="attachment-item">
                  <FileText :size="16" />
                  <span class="file-name">{{ form.attachment.name }}</span>
                  <button type="button" class="remove-btn" @click="removeAttachment">✕</button>
                </div>
              </div>
            </div>

            <!-- Submit -->
            <button type="submit" class="submit-btn" :disabled="isSending">
              <Send v-if="!isSending" :size="16" />
              <span class="spinner-small" v-else></span>
              {{ isSending ? 'جاري الإرسال...' : 'إرسال الطلب' }}
            </button>
          </form>
        </div>

        <!-- Tab Content: Previous Tickets -->
        <div v-else-if="activeTab === 'tickets'" class="tab-content">
          <div v-if="!tickets.length" class="empty-state">
            <MessageSquare :size="44" class="es-ico" />
            <p>لا توجد طلبات سابقة</p>
            <p class="es-sub">سيظهر هنا جميع طلبات الدعم الفني والشكاوي التي أرسلتها</p>
          </div>

          <div v-else class="tickets-list">
            <div
              v-for="ticket in tickets"
              :key="ticket.id"
              class="ticket-card"
              @click="openTicketDetail(ticket)"
            >
              <div class="ticket-header">
                <div>
                  <h3 class="ticket-subject">{{ ticket.subject }}</h3>
                  <p class="ticket-meta">{{ formatDate(ticket.created_at) }}</p>
                </div>
                <span class="status-badge" :class="statusClass(ticket.status)">
                  {{ ticket.status_label ?? ticket.status }}
                </span>
              </div>

              <div class="ticket-content">
                <p>{{ ticket.message }}</p>
              </div>

              <div class="ticket-footer">
                <div class="ticket-info">
                  <span class="info-item">
                    <span class="label">الفئة:</span>
                    <span class="value">{{ ticket.category_label ?? ticket.category }}</span>
                  </span>
                  <span v-if="ticket.attachment_url" class="info-item">
                    <span class="label">المرفق:</span>
                    <a :href="ticket.attachment_url" target="_blank" class="value attach-link">عرض المرفق</a>
                  </span>
                </div>
                <div v-if="ticket.reply" class="response-section">
                  <p class="response-label">الرد من الإدارة:</p>
                  <p class="response-text">{{ ticket.reply }}</p>
                  <p class="response-date">
                    <template v-if="ticket.replied_by">{{ ticket.replied_by }} · </template>{{ formatDate(ticket.replied_at!) }}
                  </p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Footer -->
      <footer class="sp-footer">
        <p>اتحاد المقاولين الفلسطينيين © {{ new Date().getFullYear() }}</p>
      </footer>
    </template>
  </div>

  <!-- Ticket Detail Modal -->
  <div v-if="isTicketDetailOpen" class="modal-overlay" @click="closeTicketDetail">
    <div class="modal-content" @click.stop>
      <div class="modal-header">
        <h3>تذكرة #{{ selectedTicket?.id }}</h3>
        <span class="badge" :class="statusClass(selectedTicket?.status)">
          {{ selectedTicket?.status_label ?? selectedTicket?.status }}
        </span>
      </div>
      <div class="modal-body">
        <p><strong>الموضوع:</strong> {{ selectedTicket?.subject }}</p>
        <p><strong>الفئة:</strong> {{ selectedTicket?.category_label ?? selectedTicket?.category }}</p>

        <!-- ─── المحادثة: الرسالة الأصلية ثم كل الردود بالترتيب الزمني ─── -->
        <div class="thread">
          <div class="thread-bubble thread-bubble--me">
            <div class="thread-bubble-meta">
              <span>أنت</span>
              <span>{{ formatDate(selectedTicket?.created_at) }}</span>
            </div>
            <p class="thread-bubble-text">{{ selectedTicket?.message }}</p>
            <a v-if="selectedTicket?.attachment_url" :href="selectedTicket.attachment_url" target="_blank" class="attachment-link">عرض المرفق</a>
          </div>

          <div
            v-for="m in selectedTicket?.messages ?? []"
            :key="m.id"
            class="thread-bubble"
            :class="m.sender_type === 'admin' ? 'thread-bubble--admin' : 'thread-bubble--me'"
          >
            <div class="thread-bubble-meta">
              <span>{{ m.sender_name ?? (m.sender_type === 'admin' ? 'الإدارة' : 'أنت') }}</span>
              <span>{{ formatDate(m.created_at) }}</span>
            </div>
            <p class="thread-bubble-text">{{ m.message }}</p>
            <a v-if="m.attachment_url" :href="m.attachment_url" target="_blank" class="attachment-link">عرض المرفق</a>
          </div>
        </div>

        <!-- ─── صندوق الرد — مخفي لو الطلب مغلق ─── -->
        <div v-if="selectedTicket?.status !== 'closed'" class="reply-box">
          <p v-if="replyError" class="reply-box-error">{{ replyError }}</p>
          <textarea
            v-model="replyMessage"
            rows="3"
            placeholder="اكتب رسالتك..."
            class="form-textarea"
          />
          <button class="reply-send-btn" :disabled="isReplySending || !replyMessage.trim()" @click="sendReply">
            <Send :size="15" />
            {{ isReplySending ? 'جاري الإرسال...' : 'إرسال' }}
          </button>
        </div>
        <p v-else class="thread-closed-note">هذا الطلب مغلق ولا يمكن إضافة رسائل جديدة عليه.</p>
      </div>
      <div class="modal-footer">
        <button class="btn-close" @click="closeTicketDetail">إغلاق</button>
      </div>
    </div>
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

.sp-page { font-family: 'Tajawal', sans-serif; direction: rtl; min-height: 100vh; background: #f5f7ff; color: var(--text-b); }
.sp-container { max-width: 1000px; margin: 0 auto; padding: 0 1.5rem; }

/* Auth & Loading */
.auth-wall { display: flex; flex-direction: column; align-items: center; justify-content: center; min-height: 100vh; gap: 1rem; padding: 2rem; text-align: center; }
.aw-logo { height: 80px; margin-bottom: .5rem; }
.auth-wall h2 { font-size: 1.5rem; font-weight: 800; color: var(--text-h); }
.auth-wall p { color: var(--text-m); font-size: .95rem; }
.aw-btn { background: var(--navy); color: #fff; border: none; border-radius: 10px; padding: .75rem 2rem; font-size: .95rem; font-weight: 700; text-decoration: none; font-family: inherit; cursor: pointer; }

.sp-loading { display: flex; flex-direction: column; align-items: center; justify-content: center; min-height: 100vh; gap: 1rem; color: var(--text-m); }
.spinner { width: 40px; height: 40px; border: 3px solid var(--navy-light); border-top-color: var(--navy); border-radius: 50%; animation: spin .8s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }

/* Header */
.sp-header { background: var(--navy); padding: .85rem 0; position: sticky; top: 0; z-index: 100; }
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
.sp-body { padding: 2rem 1.5rem 3rem; }

/* Quick Contact */
.quick-contact { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; margin-bottom: 2rem; }
.qc-card { background: #fff; border: 1.5px solid var(--border); border-radius: 12px; padding: 1.25rem; display: flex; align-items: center; gap: 1rem; transition: all .2s; }
.qc-card:hover { box-shadow: 0 4px 16px rgba(26,35,126,.1); }
.qc-card.whatsapp-card { cursor: pointer; border-color: #25d366; background: #f0fdf4; }
.qc-card.whatsapp-card:hover { background: #ddfce7; }
.qc-icon { color: var(--navy); flex-shrink: 0; }
.qc-card.whatsapp-card .qc-icon { color: #25d366; }
.qc-label { font-size: .8rem; color: var(--text-m); text-transform: uppercase; letter-spacing: .02em; margin-bottom: .25rem; }
.qc-value { font-size: .95rem; font-weight: 700; color: var(--text-h); text-decoration: none; }
.qc-value:hover { text-decoration: underline; }

/* Tabs */
.tabs-header { display: flex; gap: .5rem; border-bottom: 2px solid var(--border); margin-bottom: 1.75rem; }
.tab-btn { background: none; border: none; padding: .75rem 1.25rem 1rem; font-family: inherit; font-size: .95rem; font-weight: 600; color: var(--text-m); cursor: pointer; position: relative; display: flex; align-items: center; gap: .5rem; transition: color .2s; }
.tab-btn.active { color: var(--navy); font-weight: 800; }
.tab-btn.active::after { content: ''; position: absolute; bottom: -2px; inset: 0; height: 3px; background: var(--navy); }
.badge { background: var(--navy-light); color: var(--navy); font-size: .75rem; font-weight: 700; padding: .15rem .45rem; border-radius: 50px; }

/* Alerts */
.alert { display: flex; align-items: center; gap: 1rem; padding: 1rem 1.25rem; border-radius: 12px; margin-bottom: 1.5rem; font-size: .95rem; font-weight: 600; }
.alert-error { background: var(--red-light); color: var(--red); border: 1px solid #ef9a9a; }
.alert-success { background: var(--green-light); color: var(--green); border: 1px solid #a5d6a7; }

/* Tab Content */
.tab-content { background: #fff; border: 1.5px solid var(--border); border-radius: 16px; padding: 2rem; }

/* Form */
.support-form { display: flex; flex-direction: column; gap: 1.5rem; }
.form-group { display: flex; flex-direction: column; gap: .5rem; }
.form-group.full { grid-column: 1 / -1; }
.form-group label { font-size: .85rem; font-weight: 700; color: var(--text-h); }
.form-input, .form-textarea { font-family: inherit; font-size: .95rem; padding: .75rem 1rem; border: 1.5px solid var(--border); border-radius: 10px; transition: border-color .2s; }
.form-input:focus, .form-textarea:focus { outline: none; border-color: var(--navy); }
.form-textarea { resize: vertical; min-height: 150px; }

.file-upload-area { border: 2px dashed var(--navy-light); border-radius: 12px; padding: 2rem; text-align: center; cursor: pointer; transition: all .2s; }
.file-upload-area:hover { border-color: var(--navy); background: var(--navy-soft); }
.upload-icon { width: 48px; height: 48px; color: var(--navy); margin: 0 auto 1rem; opacity: .6; }
.upload-text { font-size: .95rem; font-weight: 700; color: var(--text-h); margin-bottom: .25rem; }
.upload-hint { font-size: .8rem; color: var(--text-m); }

.attachments-list { margin-top: 1rem; display: flex; flex-direction: column; gap: .5rem; }
.attachment-item { display: flex; align-items: center; gap: .75rem; padding: .75rem 1rem; background: var(--navy-light); border-radius: 10px; font-size: .9rem; }
.file-name { flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.remove-btn { width: 24px; height: 24px; border-radius: 50%; background: rgba(198, 40, 40, .9); color: #fff; border: none; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 1rem; transition: background .2s; flex-shrink: 0; }
.remove-btn:hover { background: var(--red); }

.submit-btn { background: var(--navy); color: #fff; border: none; padding: 1rem; border-radius: 10px; font-size: .95rem; font-weight: 700; cursor: pointer; font-family: inherit; display: flex; align-items: center; justify-content: center; gap: .5rem; transition: background .2s; width: 100%; }
.submit-btn:hover:not(:disabled) { background: var(--navy-mid); }
.submit-btn:disabled { opacity: .6; cursor: not-allowed; }
.spinner-small { width: 16px; height: 16px; border: 2px solid rgba(255,255,255,.3); border-top-color: #fff; border-radius: 50%; animation: spin .8s linear infinite; }

/* Empty State */
.empty-state { text-align: center; padding: 3rem 2rem; color: var(--text-m); }
.es-ico { width: 48px; height: 48px; color: var(--text-m); margin: 0 auto 1rem; opacity: .4; }
.empty-state p { font-size: .95rem; font-weight: 600; color: var(--text-h); margin-bottom: .5rem; }
.es-sub { font-size: .85rem; color: var(--text-m); margin-top: .25rem; }

/* Tickets List */
.tickets-list { display: flex; flex-direction: column; gap: 1rem; }

.ticket-card { border: 1.5px solid var(--border); border-radius: 12px; padding: 1.5rem; background: #fff; transition: all .2s; }
.ticket-card:hover { box-shadow: 0 4px 16px rgba(26,35,126,.08); border-color: #c5cae9; }

.ticket-header { display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem; margin-bottom: 1rem; }
.ticket-subject { font-size: 1rem; font-weight: 800; color: var(--text-h); margin-bottom: .25rem; }
.ticket-meta { font-size: .82rem; color: var(--text-m); }

.status-badge { display: inline-block; padding: .35rem .75rem; border-radius: 50px; font-size: .8rem; font-weight: 700; }
.status-badge.open { background: #fff8e1; color: #e65100; }
.status-badge.in_progress { background: var(--navy-light); color: var(--navy); }
.status-badge.resolved { background: var(--green-light); color: var(--green); }
.status-badge.closed { background: #f0f0f0; color: var(--text-m); }

.ticket-content { font-size: .9rem; color: var(--text-b); line-height: 1.6; margin-bottom: 1rem; padding: 1rem; background: #f5f5f5; border-radius: 8px; }

.ticket-footer { display: flex; flex-direction: column; gap: 1rem; }

.ticket-info { display: flex; flex-wrap: wrap; gap: 1.5rem; font-size: .85rem; }
.info-item { display: flex; align-items: center; gap: .35rem; }
.info-item .label { color: var(--text-m); font-weight: 600; }
.info-item .value { color: var(--text-h); font-weight: 700; }
.priority-low { color: #2e7d32; }
.priority-medium { color: #e65100; }
.priority-high { color: #c62828; }
.priority-urgent { color: #b71c1c; font-weight: 800; }

.response-section { padding: 1rem; background: var(--green-light); border-radius: 8px; border-left: 4px solid var(--green); }
.response-label { font-size: .82rem; font-weight: 800; color: var(--green); text-transform: uppercase; margin-bottom: .5rem; }
.response-text { font-size: .9rem; color: var(--text-b); line-height: 1.6; margin-bottom: .5rem; }
.response-date { font-size: .78rem; color: var(--text-m); }

/* Footer */
.sp-footer { text-align: center; padding: 1.25rem; font-size: .78rem; color: var(--text-m); border-top: 1px solid var(--border); background: #fff; }

/* Responsive */
@media (max-width: 768px) {
  .quick-contact { grid-template-columns: 1fr; }
  .hdr-info { display: none; }
  .tab-content { padding: 1.25rem; }
  .ticket-header { flex-direction: column; }
  .status-badge { align-self: flex-start; }
}

/* Ticket Detail Modal */
.modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0,0,0,0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
}
.modal-content {
  background: white;
  border-radius: 12px;
  width: 90%;
  max-width: 500px;
  max-height: 90vh;
  overflow-y: auto;
  position: relative;
}
.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1.5rem;
  border-bottom: 1px solid var(--border);
}
.modal-header h3 {
  margin: 0;
  font-size: 1.25rem;
  font-weight: 700;
  color: var(--text-h);
}
.modal-body {
  padding: 1.5rem;
}
.modal-footer {
  padding: 1.5rem;
  text-align: right;
  border-top: 1px solid var(--border);
}
.btn-close {
  background: var(--navy);
  color: white;
  border: none;
  padding: 0.5rem 1.5rem;
  border-radius: 6px;
  cursor: pointer;
}
.btn-close:hover {
  background: var(--navy-mid);
}
.attachment-link {
  color: var(--navy);
  text-decoration: underline;
  font-size: .85rem;
}
.attachment-link:hover {
  opacity: 0.8;
}

/* Conversation thread */
.thread {
  display: flex;
  flex-direction: column;
  gap: .75rem;
  max-height: 320px;
  overflow-y: auto;
  margin: 1rem 0;
  padding: .25rem;
}
.thread-bubble {
  max-width: 85%;
  padding: .75rem 1rem;
  border-radius: 10px;
}
.thread-bubble--me {
  align-self: flex-start;
  background: var(--navy-soft);
  border-inline-start: 3px solid var(--navy);
}
.thread-bubble--admin {
  align-self: flex-end;
  background: var(--green-light);
  border-inline-end: 3px solid var(--green);
}
.thread-bubble-meta {
  display: flex;
  justify-content: space-between;
  gap: .75rem;
  font-size: .78rem;
  font-weight: 700;
  color: var(--text-m);
  margin-bottom: .35rem;
}
.thread-bubble-text {
  white-space: pre-wrap;
  word-wrap: break-word;
  line-height: 1.6;
  font-size: .9rem;
  color: var(--text-b);
}
.thread-closed-note {
  font-size: .85rem;
  color: var(--text-m);
  text-align: center;
  padding: .75rem;
  background: #f5f5f5;
  border-radius: 8px;
}

/* Reply box */
.reply-box {
  border-top: 1px solid var(--border);
  padding-top: 1rem;
  display: flex;
  flex-direction: column;
  gap: .75rem;
}
.reply-box-error {
  color: var(--red);
  font-size: .85rem;
  font-weight: 600;
}
.reply-send-btn {
  align-self: flex-end;
  display: flex;
  align-items: center;
  gap: .4rem;
  background: var(--navy);
  color: #fff;
  border: none;
  padding: .6rem 1.25rem;
  border-radius: 8px;
  font-family: inherit;
  font-size: .85rem;
  font-weight: 700;
  cursor: pointer;
  transition: background .2s;
}
.reply-send-btn:hover:not(:disabled) {
  background: var(--navy-mid);
}
.reply-send-btn:disabled {
  opacity: .6;
  cursor: not-allowed;
}
</style>

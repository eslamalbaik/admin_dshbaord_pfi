<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import axios from 'axios'
import { LogOut, Phone, Mail, MessageCircle, Plus, ArrowRight, Paperclip, Send } from 'lucide-vue-next'

definePage({
  meta: { layout: 'pure', public: true, unauthenticatedOnly: false },
})

const router = useRouter()
const token = ref<string | null>(null)
const authError = ref(false)
const isLoading = ref(true)

interface Contractor {
  name: string
  membership_number: string
  phone: string | null
}

const contractor = ref<Contractor | null>(null)

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
    const r = await axios.get(`${BASE}/api/v1/contractor/dashboard`, {
      headers: apiHeaders(),
    })
    contractor.value = r.data.items?.contractor ?? null
  } catch (e: any) {
    if (e?.response?.status === 401) authError.value = true
  } finally {
    isLoading.value = false
  }
}

function openWhatsApp() {
  const message = contractor.value
    ? `مرحباً، أنا ${contractor.value.name} (رقم العضوية: ${contractor.value.membership_number}) بحاجة إلى دعم فني.`
    : 'مرحباً، بحاجة إلى دعم فني.'
  const encoded = encodeURIComponent(message)
  window.open(`https://wa.me/970200000000?text=${encoded}`, '_blank')
}

// ─── تذاكر الدعم الفني ───
type TicketView = 'list' | 'new' | 'detail'
const view = ref<TicketView>('list')

interface TicketMessage {
  id: number
  sender_type: 'contractor' | 'admin'
  sender_name: string | null
  message: string
  attachment_url: string | null
  created_at?: string
}
interface Ticket {
  id: number
  subject: string
  whatsapp_phone: string
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
  messages: TicketMessage[]
}

const categories = [
  { value: 'technical',  label: 'دعم فني' },
  { value: 'complaint',  label: 'شكوى' },
  { value: 'inquiry',    label: 'استفسار' },
  { value: 'suggestion', label: 'اقتراح' },
  { value: 'other',      label: 'أخرى' },
]

const statusClass: Record<string, string> = {
  open: 'st-open', in_progress: 'st-progress', answered: 'st-answered', closed: 'st-closed',
}

const tickets = ref<Ticket[]>([])
const ticketsLoading = ref(false)
const selectedTicket = ref<Ticket | null>(null)

async function fetchTickets() {
  ticketsLoading.value = true
  try {
    const r = await axios.get(`${BASE}/api/v1/contractor/support-tickets`, { headers: apiHeaders() })
    tickets.value = r.data.items ?? []
  } catch {}
  finally { ticketsLoading.value = false }
}

async function openTicket(id: number) {
  view.value = 'detail'
  selectedTicket.value = null
  try {
    const r = await axios.get(`${BASE}/api/v1/contractor/support-tickets/${id}`, { headers: apiHeaders() })
    selectedTicket.value = r.data.items
  } catch {
    view.value = 'list'
  }
}

// ─── فورم تذكرة جديدة ───
const newTicket = ref({ subject: '', whatsapp_phone: '', category: 'technical', message: '' })
const newAttachment = ref<File | null>(null)
const submitting = ref(false)
const submitError = ref('')

function openNewTicketForm() {
  newTicket.value = { subject: '', whatsapp_phone: contractor.value?.phone ?? '', category: 'technical', message: '' }
  newAttachment.value = null
  submitError.value = ''
  view.value = 'new'
}

function onAttachmentChange(e: Event) {
  const file = (e.target as HTMLInputElement).files?.[0] ?? null
  newAttachment.value = file
}

async function submitTicket() {
  submitError.value = ''
  if (!newTicket.value.subject || !newTicket.value.whatsapp_phone || !newTicket.value.message) {
    submitError.value = 'يرجى تعبئة جميع الحقول المطلوبة.'
    return
  }
  submitting.value = true
  try {
    const form = new FormData()
    form.append('subject', newTicket.value.subject)
    form.append('whatsapp_phone', newTicket.value.whatsapp_phone)
    form.append('category', newTicket.value.category)
    form.append('message', newTicket.value.message)
    if (newAttachment.value) form.append('attachment', newAttachment.value)

    await axios.post(`${BASE}/api/v1/contractor/support-tickets`, form, {
      headers: { ...apiHeaders(), 'Content-Type': 'multipart/form-data' },
    })
    await fetchTickets()
    view.value = 'list'
  } catch (e: any) {
    submitError.value = e?.response?.data?.message ?? 'تعذّر إرسال الطلب، حاول مرة أخرى.'
  } finally {
    submitting.value = false
  }
}

// ─── الرد على تذكرة ───
const replyMessage = ref('')
const replying = ref(false)

async function sendReply() {
  if (!selectedTicket.value || !replyMessage.value.trim()) return
  replying.value = true
  try {
    const r = await axios.post(
      `${BASE}/api/v1/contractor/support-tickets/${selectedTicket.value.id}/reply`,
      { message: replyMessage.value },
      { headers: apiHeaders() },
    )
    selectedTicket.value = r.data.items
    replyMessage.value = ''
  } catch {}
  finally { replying.value = false }
}

function fmtDate(d: string | null | undefined) {
  if (!d) return '—'
  return new Date(d).toLocaleDateString('ar-PS', { year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit' })
}

function backToList() {
  view.value = 'list'
  selectedTicket.value = null
}

onMounted(async () => {
  token.value = getToken()
  if (!token.value) { authError.value = true; isLoading.value = false; return }
  await fetchData()
  await fetchTickets()
})
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
        <h1 class="sp-heading">تواصل معنا</h1>
        <p class="sp-hint">لأي استفسار أو شكوى أو مشكلة فنية، تواصل معنا مباشرة عبر إحدى القنوات التالية.</p>

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

        <!-- ═══ تذاكر الدعم الفني ═══ -->
        <div class="tickets-section">
          <!-- ── قائمة التذاكر ── -->
          <template v-if="view === 'list'">
            <div class="ts-head">
              <h2 class="sp-heading">تذاكري</h2>
              <button class="btn-primary" @click="openNewTicketForm">
                <Plus :size="16" /> تذكرة جديدة
              </button>
            </div>

            <div v-if="ticketsLoading" class="ts-empty">جاري التحميل...</div>
            <div v-else-if="!tickets.length" class="ts-empty">لا توجد تذاكر دعم فني حتى الآن.</div>
            <div v-else class="ticket-list">
              <button v-for="t in tickets" :key="t.id" class="ticket-row" @click="openTicket(t.id)">
                <div class="tr-main">
                  <span class="tr-subject">{{ t.subject }}</span>
                  <span class="tr-cat">{{ t.category_label }}</span>
                </div>
                <div class="tr-meta">
                  <span class="tr-date">{{ fmtDate(t.created_at) }}</span>
                  <span class="st-badge" :class="statusClass[t.status]">{{ t.status_label }}</span>
                </div>
              </button>
            </div>
          </template>

          <!-- ── تذكرة جديدة ── -->
          <template v-else-if="view === 'new'">
            <div class="ts-head">
              <button class="btn-back" @click="backToList"><ArrowRight :size="16" /> رجوع</button>
              <h2 class="sp-heading">تذكرة دعم جديدة</h2>
            </div>

            <form class="ticket-form" @submit.prevent="submitTicket">
              <div class="tf-row">
                <label>الموضوع *</label>
                <input v-model="newTicket.subject" type="text" maxlength="255" required />
              </div>
              <div class="tf-row">
                <label>رقم واتساب للتواصل *</label>
                <input v-model="newTicket.whatsapp_phone" type="tel" maxlength="20" required />
              </div>
              <div class="tf-row">
                <label>نوع الطلب *</label>
                <select v-model="newTicket.category">
                  <option v-for="c in categories" :key="c.value" :value="c.value">{{ c.label }}</option>
                </select>
              </div>
              <div class="tf-row">
                <label>تفاصيل الطلب *</label>
                <textarea v-model="newTicket.message" rows="5" maxlength="5000" required />
              </div>
              <div class="tf-row">
                <label>مرفق (اختياري)</label>
                <input type="file" accept=".png,.jpg,.jpeg,.webp,.pdf" @change="onAttachmentChange" />
              </div>

              <p v-if="submitError" class="tf-error">{{ submitError }}</p>

              <button type="submit" class="btn-primary" :disabled="submitting">
                <Send :size="16" /> {{ submitting ? 'جاري الإرسال...' : 'إرسال الطلب' }}
              </button>
            </form>
          </template>

          <!-- ── تفاصيل التذكرة ── -->
          <template v-else-if="view === 'detail'">
            <div class="ts-head">
              <button class="btn-back" @click="backToList"><ArrowRight :size="16" /> رجوع</button>
              <h2 class="sp-heading">{{ selectedTicket?.subject ?? 'تفاصيل الطلب' }}</h2>
            </div>

            <div v-if="!selectedTicket" class="ts-empty">جاري التحميل...</div>
            <template v-else>
              <div class="td-meta">
                <span class="st-badge" :class="statusClass[selectedTicket.status]">{{ selectedTicket.status_label }}</span>
                <span class="td-cat">{{ selectedTicket.category_label }}</span>
                <span class="td-date">{{ fmtDate(selectedTicket.created_at) }}</span>
              </div>

              <div class="chat-thread">
                <div
                  v-for="m in selectedTicket.messages" :key="m.id"
                  class="chat-bubble" :class="m.sender_type === 'contractor' ? 'bubble-mine' : 'bubble-admin'"
                >
                  <span class="bubble-sender">{{ m.sender_type === 'contractor' ? 'أنت' : (m.sender_name ?? 'فريق الدعم') }}</span>
                  <p class="bubble-text">{{ m.message }}</p>
                  <a v-if="m.attachment_url" :href="m.attachment_url" target="_blank" class="bubble-attachment">
                    <Paperclip :size="13" /> عرض المرفق
                  </a>
                  <span v-if="m.created_at" class="bubble-time">{{ fmtDate(m.created_at) }}</span>
                </div>
              </div>

              <form v-if="selectedTicket.status !== 'closed'" class="reply-box" @submit.prevent="sendReply">
                <textarea v-model="replyMessage" rows="3" placeholder="اكتب ردك هنا..." />
                <button type="submit" class="btn-primary" :disabled="replying || !replyMessage.trim()">
                  <Send :size="16" /> إرسال
                </button>
              </form>
              <p v-else class="ts-empty">هذا الطلب مغلق ولا يمكن إضافة ردود جديدة.</p>
            </template>
          </template>
        </div>
      </div>

      <!-- Footer -->
      <footer class="sp-footer">
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
.sp-body { padding: 2.5rem 1.5rem 3rem; }
.sp-heading { font-size: 1.4rem; font-weight: 800; color: var(--text-h); margin-bottom: .5rem; }
.sp-hint { font-size: .95rem; color: var(--text-m); margin-bottom: 2rem; }

/* Quick Contact */
.quick-contact { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; }
.qc-card { background: #fff; border: 1.5px solid var(--border); border-radius: 12px; padding: 1.25rem; display: flex; align-items: center; gap: 1rem; transition: all .2s; }
.qc-card:hover { box-shadow: 0 4px 16px rgba(26,35,126,.1); }
.qc-card.whatsapp-card { cursor: pointer; border-color: #25d366; background: #f0fdf4; }
.qc-card.whatsapp-card:hover { background: #ddfce7; }
.qc-icon { color: var(--navy); flex-shrink: 0; }
.qc-card.whatsapp-card .qc-icon { color: #25d366; }
.qc-label { font-size: .8rem; color: var(--text-m); text-transform: uppercase; letter-spacing: .02em; margin-bottom: .25rem; }
.qc-value { font-size: .95rem; font-weight: 700; color: var(--text-h); text-decoration: none; }
.qc-value:hover { text-decoration: underline; }

/* Footer */
.sp-footer { text-align: center; padding: 1.25rem; font-size: .78rem; color: var(--text-m); border-top: 1px solid var(--border); background: #fff; }

/* ─── تذاكر الدعم ─── */
.tickets-section { margin-top: 2.5rem; }
.ts-head { display: flex; align-items: center; justify-content: space-between; gap: 1rem; margin-bottom: 1.25rem; }
.ts-head .sp-heading { margin: 0; }
.ts-empty { text-align: center; padding: 2.5rem 1rem; color: var(--text-m); background: #fff; border: 1.5px dashed var(--border); border-radius: 12px; }

.btn-primary { display: inline-flex; align-items: center; gap: .4rem; background: var(--navy); color: #fff; border: none; border-radius: 9px; padding: .65rem 1.25rem; font-size: .88rem; font-weight: 700; cursor: pointer; font-family: inherit; white-space: nowrap; }
.btn-primary:hover { background: var(--navy-mid); }
.btn-primary:disabled { opacity: .6; cursor: not-allowed; }
.btn-back { display: inline-flex; align-items: center; gap: .35rem; background: none; border: none; color: var(--navy); font-size: .85rem; font-weight: 700; cursor: pointer; font-family: inherit; padding: 0; }

/* Ticket list */
.ticket-list { display: flex; flex-direction: column; gap: .75rem; }
.ticket-row { display: flex; align-items: center; justify-content: space-between; gap: 1rem; background: #fff; border: 1.5px solid var(--border); border-radius: 12px; padding: 1rem 1.25rem; cursor: pointer; text-align: right; font-family: inherit; transition: box-shadow .2s; }
.ticket-row:hover { box-shadow: 0 4px 16px rgba(26,35,126,.1); }
.tr-main { display: flex; align-items: center; gap: .6rem; }
.tr-subject { font-weight: 700; color: var(--text-h); font-size: .92rem; }
.tr-cat { font-size: .75rem; color: var(--text-m); background: var(--navy-soft); padding: .2rem .6rem; border-radius: 999px; }
.tr-meta { display: flex; align-items: center; gap: .75rem; }
.tr-date { font-size: .78rem; color: var(--text-m); }

.st-badge { font-size: .75rem; font-weight: 700; padding: .3rem .7rem; border-radius: 999px; white-space: nowrap; }
.st-badge.st-open { background: #fff7ed; color: #c2410c; }
.st-badge.st-progress { background: #eff6ff; color: #1d4ed8; }
.st-badge.st-answered { background: #f0fdf4; color: #15803d; }
.st-badge.st-closed { background: #f3f4f6; color: #6b7280; }

/* Ticket form */
.ticket-form { background: #fff; border: 1.5px solid var(--border); border-radius: 12px; padding: 1.5rem; display: flex; flex-direction: column; gap: 1.1rem; max-width: 600px; }
.tf-row { display: flex; flex-direction: column; gap: .4rem; }
.tf-row label { font-size: .85rem; font-weight: 700; color: var(--text-h); }
.tf-row input, .tf-row select, .tf-row textarea { border: 1.5px solid var(--border); border-radius: 8px; padding: .6rem .75rem; font-size: .88rem; font-family: inherit; color: var(--text-b); }
.tf-row textarea { resize: vertical; }
.tf-error { color: #dc2626; font-size: .85rem; }

/* Ticket detail */
.td-meta { display: flex; align-items: center; gap: .75rem; margin-bottom: 1.25rem; }
.td-cat, .td-date { font-size: .8rem; color: var(--text-m); }

.chat-thread { display: flex; flex-direction: column; gap: .9rem; margin-bottom: 1.5rem; }
.chat-bubble { max-width: 75%; padding: .75rem 1rem; border-radius: 12px; }
.bubble-mine { align-self: flex-start; background: var(--navy-soft); border: 1px solid var(--navy-light); }
.bubble-admin { align-self: flex-end; background: #fff; border: 1.5px solid var(--border); }
.bubble-sender { display: block; font-size: .75rem; font-weight: 700; color: var(--navy); margin-bottom: .25rem; }
.bubble-text { font-size: .88rem; color: var(--text-b); white-space: pre-wrap; }
.bubble-attachment { display: inline-flex; align-items: center; gap: .3rem; margin-top: .4rem; font-size: .78rem; color: var(--navy); text-decoration: none; }
.bubble-time { display: block; margin-top: .35rem; font-size: .7rem; color: var(--text-m); }

.reply-box { display: flex; flex-direction: column; gap: .75rem; background: #fff; border: 1.5px solid var(--border); border-radius: 12px; padding: 1.25rem; max-width: 600px; }
.reply-box textarea { border: 1.5px solid var(--border); border-radius: 8px; padding: .6rem .75rem; font-size: .88rem; font-family: inherit; resize: vertical; }
.reply-box .btn-primary { align-self: flex-start; }

/* Responsive */
@media (max-width: 768px) {
  .quick-contact { grid-template-columns: 1fr; }
  .hdr-info { display: none; }
  .chat-bubble { max-width: 90%; }
  .ts-head { flex-wrap: wrap; }
}
</style>

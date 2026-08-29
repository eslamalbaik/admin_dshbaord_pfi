<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import axios from 'axios'
import { LogOut, Phone, Mail, MessageCircle } from 'lucide-vue-next'

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
}

const contractor = ref<Contractor | null>(null)

const BASE = import.meta.env.VITE_API_BASE_URL ?? ''

function getToken() {
  return localStorage.getItem('contractor_token')
}

function logout() {
  localStorage.removeItem('contractor_token')
  router.push('/landing')
}

async function fetchData() {
  try {
    const r = await axios.get(`${BASE}/api/v1/contractor/dashboard`, {
      headers: { Authorization: `Bearer ${token.value}` },
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

onMounted(async () => {
  token.value = getToken()
  if (!token.value) { authError.value = true; isLoading.value = false; return }
  await fetchData()
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

/* Responsive */
@media (max-width: 768px) {
  .quick-contact { grid-template-columns: 1fr; }
  .hdr-info { display: none; }
}
</style>

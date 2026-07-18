<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useRouter } from 'vue-router'
import axios from 'axios'
import {
  User, ClipboardList, CreditCard, FileText,
  LogOut, Award, MapPin, Phone, Mail, Building2,
  CalendarDays, CheckCircle, AlertCircle, Clock,
  Download, RefreshCw, ChevronRight, Wallet,
  FileCheck, FileClock, TrendingUp, MessageSquare,
} from 'lucide-vue-next'

definePage({
  meta: { layout: 'pure', public: true, unauthenticatedOnly: false },
})

const router = useRouter()

// ─── Auth ─────────────────────────────────────────────────────────────────────
const token = ref<string | null>(null)
const authError = ref(false)

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

// ─── Data ─────────────────────────────────────────────────────────────────────
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
interface Stats { total_payments: string; pending_payments: number; total_documents: number }

const contractor  = ref<Contractor | null>(null)
const membership  = ref<Membership | null>(null)
const memberships = ref<Membership[]>([])
const payments    = ref<Payment[]>([])
const documents   = ref<Document[]>([])
const stats       = ref<Stats | null>(null)

const isLoading   = ref(true)
const activeTab   = ref<'profile' | 'membership' | 'payments' | 'documents'>('profile')

// ─── Fetch ─────────────────────────────────────────────────────────────────────
const BASE = import.meta.env.VITE_API_BASE_URL ?? ''

async function fetchDashboard() {
  try {
    const r = await axios.get(`${BASE}/api/v1/contractor/dashboard`, { headers: apiHeaders() })
    // { status, message, status_code, items: { contractor, membership, stats } }
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
    documents.value = r.data.items
  }
}

onMounted(async () => {
  token.value = getToken()
  if (!token.value) { authError.value = true; isLoading.value = false; return }
  await fetchDashboard()
  const requestedTab = new URLSearchParams(window.location.search).get('tab')
  if (requestedTab && ['profile', 'membership', 'payments', 'documents'].includes(requestedTab))
    await fetchTab(requestedTab as typeof activeTab.value)
  isLoading.value = false
})

// ─── Helpers ──────────────────────────────────────────────────────────────────
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

const daysUntilExpiry = computed(() => {
  if (!membership.value?.expires_at) return null
  const diff = new Date(membership.value.expires_at).getTime() - Date.now()
  return Math.ceil(diff / (1000 * 60 * 60 * 24))
})
</script>

<template>
  <div dir="rtl" class="cpd">

    <!-- ─── Unauthenticated ─── -->
    <div v-if="authError" class="auth-wall">
      <img src="/logo.png" alt="الاتحاد" class="aw-logo" />
      <h2>يجب تسجيل الدخول أولاً</h2>
      <p>سجّل دخولك للوصول إلى لوحتك الشخصية.</p>
      <RouterLink to="/contractor/login" class="aw-btn">تسجيل الدخول</RouterLink>
    </div>

    <!-- ─── Loading ─── -->
    <div v-else-if="isLoading" class="cpd-loading">
      <div class="spinner" />
      <p>جاري تحميل بياناتك...</p>
    </div>

    <!-- ─── Dashboard ─── -->
    <template v-else-if="contractor">

      <!-- Header -->
      <header class="cpd-header">
        <div class="cpd-container hdr-inner">
          <RouterLink to="/landing" class="hdr-brand">
            <img src="/logo.png" alt="الاتحاد" class="hdr-logo" />
            <div>
              <span class="hdr-title">اتحاد المقاولين الفلسطينيين</span>
              <span class="hdr-sub">لوحة المقاول</span>
            </div>
          </RouterLink>
          <div class="hdr-user">
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

      <div class="cpd-container cpd-body">

        <!-- ─── Stats Strip ─── -->
        <div class="stats-strip">
          <div class="ss-card">
            <div class="ss-ico" style="--sic:#e8eaf6;--sicc:#1a237e"><Wallet :size="20" /></div>
            <div>
              <p class="ss-label">إجمالي المدفوعات</p>
              <p class="ss-val">{{ fmtMoney(stats?.total_payments) }}</p>
            </div>
          </div>
          <div class="ss-card">
            <div class="ss-ico" style="--sic:#fff8e1;--sicc:#e65100"><AlertCircle :size="20" /></div>
            <div>
              <p class="ss-label">مدفوعات معلّقة</p>
              <p class="ss-val">{{ stats?.pending_payments ?? 0 }}</p>
            </div>
          </div>
          <div class="ss-card">
            <div class="ss-ico" style="--sic:#e8f5e9;--sicc:#2e7d32"><FileText :size="20" /></div>
            <div>
              <p class="ss-label">الوثائق</p>
              <p class="ss-val">{{ stats?.total_documents ?? 0 }} ملف</p>
            </div>
          </div>
          <div class="ss-card" :class="{ 'ss-expiring': membership?.expiring_soon }">
            <div class="ss-ico" style="--sic:#e3f2fd;--sicc:#0d47a1"><CalendarDays :size="20" /></div>
            <div>
              <p class="ss-label">انتهاء العضوية</p>
              <p class="ss-val">
                <template v-if="daysUntilExpiry !== null">
                  {{ daysUntilExpiry > 0 ? `بعد ${daysUntilExpiry} يوم` : 'منتهية' }}
                </template>
                <template v-else>—</template>
              </p>
            </div>
          </div>
        </div>

        <!-- ─── Quick Links (الخدمات) ─── -->
        <div class="quick-links">
          <RouterLink to="/contractor/payment-gateway" class="ql-card">
            <div class="ql-ico" style="--qic:#fce4ec;--qicc:#c62828"><RefreshCw :size="20" /></div>
            <div class="ql-txt">
              <p class="ql-label">تجديد العضوية</p>
              <p class="ql-desc">دفع رسوم الاشتراك</p>
            </div>
            <ChevronRight :size="16" class="ql-arrow" />
          </RouterLink>
          <RouterLink to="/contractor/support" class="ql-card">
            <div class="ql-ico" style="--qic:#f3e5f5;--qicc:#6a1b9a"><MessageSquare :size="20" /></div>
            <div class="ql-txt">
              <p class="ql-label">الدعم الفني</p>
              <p class="ql-desc">شكوى أو استفسار</p>
            </div>
            <ChevronRight :size="16" class="ql-arrow" />
          </RouterLink>
          <RouterLink to="/contractor/certificate-request" class="ql-card">
            <div class="ql-ico" style="--qic:#e8f5e9;--qicc:#2e7d32"><Award :size="20" /></div>
            <div class="ql-txt">
              <p class="ql-label">طلب شهادة</p>
              <p class="ql-desc">شهادة الانتساب</p>
            </div>
            <ChevronRight :size="16" class="ql-arrow" />
          </RouterLink>
        </div>

        <!-- ─── Layout: Sidebar + Content ─── -->
        <div class="cpd-layout">

          <!-- Sidebar -->
          <aside class="cpd-sidebar">
            <nav class="side-nav">
              <button
                v-for="tab in [
                  { id:'profile',    label:'الملف الشخصي',       icon: User },
                  { id:'membership', label:'العضوية والاشتراك',  icon: Award },
                  { id:'payments',   label:'المعاملات المالية',  icon: CreditCard },
                  { id:'documents',  label:'الوثائق والملفات',   icon: FileText },
                ]"
                :key="tab.id"
                class="side-btn"
                :class="{ active: activeTab === tab.id }"
                @click="fetchTab(tab.id as any)"
              >
                <component :is="tab.icon" :size="18" />
                <span>{{ tab.label }}</span>
                <ChevronRight :size="14" class="side-arr" />
              </button>
            </nav>

            <!-- Membership card in sidebar -->
            <div v-if="membership" class="side-mem-card" :class="membership.expiring_soon ? 'expiring' : 'active'">
              <p class="smc-type">{{ statusLabel[membership.type] ?? membership.type }}</p>
              <p class="smc-exp">
                <CalendarDays :size="12" />
                {{ fmtDate(membership.expires_at) }}
              </p>
              <span class="smc-badge" :class="statusClass(membership.status)">
                {{ statusLabel[membership.status] ?? membership.status }}
              </span>
              <div v-if="membership.expiring_soon" class="smc-warn">
                <AlertCircle :size="13" /> تنتهي قريباً
              </div>
            </div>
          </aside>

          <!-- Content -->
          <main class="cpd-main">

            <!-- ══ Profile ══ -->
            <div v-if="activeTab === 'profile'" class="tab-content">
              <h2 class="tab-title"><User :size="20" /> الملف الشخصي</h2>

              <div class="profile-grid">
                <div class="pf-card">
                  <h3 class="pf-sec">البيانات الأساسية</h3>
                  <div class="pf-row"><span class="pf-label">الاسم الكامل</span><span class="pf-val">{{ contractor.name }}</span></div>
                  <div class="pf-row"><span class="pf-label">المفوَّض</span><span class="pf-val">{{ contractor.authorized_person || '—' }}</span></div>
                  <div class="pf-row"><span class="pf-label">رقم العضوية</span><span class="pf-val mono">{{ contractor.membership_number }}</span></div>
                  <div class="pf-row"><span class="pf-label">السجل التجاري</span><span class="pf-val mono">{{ contractor.commercial_register || '—' }}</span></div>
                  <div class="pf-row">
                    <span class="pf-label">الحالة</span>
                    <span class="badge" :class="statusClass(contractor.status)">
                      {{ statusLabel[contractor.status] ?? contractor.status }}
                    </span>
                  </div>
                </div>

                <div class="pf-card">
                  <h3 class="pf-sec">التخصص والتصنيف</h3>
                  <div class="pf-row"><span class="pf-label">التخصص</span><span class="pf-val">{{ contractor.trade || '—' }}</span></div>
                  <div class="pf-row"><span class="pf-label">الدرجة</span><span class="pf-val"><span class="deg-chip">{{ contractor.classification || '—' }}</span></span></div>
                  <div class="pf-row"><span class="pf-label">المدينة</span><span class="pf-val">{{ contractor.city || '—' }}</span></div>
                  <div class="pf-row"><span class="pf-label">العنوان</span><span class="pf-val">{{ contractor.address || '—' }}</span></div>
                </div>

                <div class="pf-card">
                  <h3 class="pf-sec">بيانات التواصل</h3>
                  <div class="pf-row">
                    <span class="pf-label"><Phone :size="13" /> الهاتف</span>
                    <span class="pf-val" dir="ltr">{{ contractor.phone || '—' }}</span>
                  </div>
                  <div class="pf-row">
                    <span class="pf-label"><Mail :size="13" /> البريد</span>
                    <span class="pf-val">{{ contractor.email || '—' }}</span>
                  </div>
                </div>
              </div>
            </div>

            <!-- ══ Membership ══ -->
            <div v-else-if="activeTab === 'membership'" class="tab-content">
              <h2 class="tab-title"><Award :size="20" /> العضوية والاشتراك</h2>

              <!-- Active membership banner -->
              <div v-if="membership" class="mem-banner" :class="membership.expiring_soon ? 'warn' : 'ok'">
                <div class="mb-icon">
                  <CheckCircle v-if="membership.status === 'active'" :size="28" />
                  <AlertCircle v-else :size="28" />
                </div>
                <div class="mb-info">
                  <p class="mb-type">عضوية {{ statusLabel[membership.type] ?? membership.type }}</p>
                  <p class="mb-dates">
                    من {{ fmtDate(membership.starts_at) }} — إلى {{ fmtDate(membership.expires_at) }}
                  </p>
                  <p v-if="membership.expiring_soon" class="mb-warn-text">
                    <AlertCircle :size="13" /> تنتهي عضويتك خلال أقل من 30 يوماً — يُرجى التجديد
                  </p>
                </div>
                <div class="mb-amount">
                  <span class="mb-amount-val">{{ fmtMoney(membership.amount) }}</span>
                  <span class="mb-amount-label">قيمة الاشتراك</span>
                </div>
              </div>
              <div v-else class="empty-state">
                <Award :size="44" class="es-ico" />
                <p>لا توجد عضوية نشطة حالياً</p>
                <p class="es-sub">تواصل مع إدارة الاتحاد لتفعيل عضويتك</p>
              </div>

              <!-- Renewal request -->
              <div class="renew-box">
                <div class="renew-icon"><RefreshCw :size="22" /></div>
                <div class="renew-text">
                  <p class="renew-title">تجديد العضوية</p>
                  <p class="renew-sub">جدّد عضويتك إلكترونياً — حوّل رسوم الاشتراك بنكياً وارفع إشعار التحويل، وسيتم التأكيد خلال 24 ساعة.</p>
                  <div class="renew-contacts">
                    <RouterLink to="/contractor/payment-gateway" class="renew-btn">
                      <RefreshCw :size="14" /> تجديد العضوية الآن
                    </RouterLink>
                    <a href="tel:+97020000000" class="renew-link"><Phone :size="13" /> للاستفسار: +970 2 000 0000</a>
                  </div>
                </div>
              </div>

              <!-- History -->
              <h3 class="sec-title">سجل العضويات</h3>
              <div v-if="memberships.length" class="mem-table">
                <div class="mt-head">
                  <span>النوع</span><span>تاريخ البدء</span><span>تاريخ الانتهاء</span><span>المبلغ</span><span>الحالة</span>
                </div>
                <div v-for="m in memberships" :key="m.id" class="mt-row">
                  <span>{{ statusLabel[m.type] ?? m.type }}</span>
                  <span>{{ fmtDate(m.starts_at) }}</span>
                  <span>{{ fmtDate(m.expires_at) }}</span>
                  <span class="mono">{{ fmtMoney(m.amount) }}</span>
                  <span><span class="badge" :class="statusClass(m.status)">{{ statusLabel[m.status] ?? m.status }}</span></span>
                </div>
              </div>
              <div v-else class="empty-state small">
                <Clock :size="28" class="es-ico" /><p>لا يوجد سجل عضويات بعد</p>
              </div>
            </div>

            <!-- ══ Payments ══ -->
            <div v-else-if="activeTab === 'payments'" class="tab-content">
              <h2 class="tab-title"><CreditCard :size="20" /> المعاملات المالية</h2>

              <div v-if="payments.length" class="pay-list">
                <div v-for="p in payments" :key="p.id" class="pay-card">
                  <div class="pay-icon" :class="p.status">
                    <CheckCircle v-if="p.status === 'paid'" :size="20" />
                    <Clock v-else-if="p.status === 'pending'" :size="20" />
                    <AlertCircle v-else :size="20" />
                  </div>
                  <div class="pay-info">
                    <div class="pay-top">
                      <span class="pay-type">{{ p.type === 'membership_fee' ? 'رسوم اشتراك' : p.type === 'renewal' ? 'تجديد عضوية' : p.type }}</span>
                      <span class="pay-amount">{{ fmtMoney(p.amount) }}</span>
                    </div>
                    <div class="pay-meta">
                      <span v-if="p.reference_number" class="pay-ref">مرجع: {{ p.reference_number }}</span>
                      <span>{{ p.method ?? '—' }}</span>
                      <span>{{ fmtDate(p.paid_at ?? p.created_at) }}</span>
                    </div>
                    <p v-if="p.membership" class="pay-mem-note">
                      <Award :size="11" /> عضوية {{ p.membership.type }} — تنتهي {{ fmtDate(p.membership.expires_at) }}
                    </p>
                  </div>
                  <span class="badge" :class="statusClass(p.status)">
                    {{ statusLabel[p.status] ?? p.status }}
                  </span>
                </div>
              </div>
              <div v-else class="empty-state">
                <CreditCard :size="44" class="es-ico" />
                <p>لا توجد معاملات مالية بعد</p>
                <p class="es-sub">ستظهر هنا جميع مدفوعاتك ورسوم الاشتراك</p>
              </div>
            </div>

            <!-- ══ Documents ══ -->
            <div v-else-if="activeTab === 'documents'" class="tab-content">
              <h2 class="tab-title"><FileText :size="20" /> الوثائق والملفات</h2>

              <div v-if="documents.length" class="doc-grid">
                <div v-for="d in documents" :key="d.id" class="doc-card">
                  <div class="doc-icon">
                    <component :is="docIcon(d.mime_type)" :size="28" />
                  </div>
                  <div class="doc-info">
                    <p class="doc-title">{{ d.title }}</p>
                    <p class="doc-meta">{{ d.type }} · {{ d.formatted_size }} · {{ fmtDate(d.created_at) }}</p>
                  </div>
                  <button class="doc-dl" @click="downloadDoc(d.url, d.title)" title="تنزيل">
                    <Download :size="16" />
                  </button>
                </div>
              </div>
              <div v-else class="empty-state">
                <FileText :size="44" class="es-ico" />
                <p>لا توجد وثائق مرفوعة بعد</p>
                <p class="es-sub">ستظهر هنا وثائقك الرسمية وشهادات التصنيف</p>
              </div>
            </div>

          </main>
        </div>
      </div>

      <!-- Footer -->
      <footer class="cpd-footer">
        <p>اتحاد المقاولين الفلسطينيين © {{ new Date().getFullYear() }}</p>
      </footer>

    </template>
  </div>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800&display=swap');

:global(:root) {
  --navy:        #1a237e;
  --navy-mid:    #3949ab;
  --navy-light:  #e8eaf6;
  --navy-soft:   #f5f7ff;
  --gold:        #fdd835;
  --gold-dark:   #f9a825;
  --green:       #2e7d32;
  --green-light: #e8f5e9;
  --red:         #c62828;
  --red-light:   #fce4ec;
  --text-h:      #1a1a3e;
  --text-b:      #424242;
  --text-m:      #757575;
  --border:      #e0e0e0;
}

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

.cpd {
  font-family: 'Tajawal', sans-serif;
  direction: rtl;
  min-height: 100vh;
  background: #f5f7ff;
  color: var(--text-b);
}

.cpd-container { max-width: 1200px; margin: 0 auto; padding: 0 1.5rem; }

/* ─── Auth Wall ──────────────────────────────────────────────────────────── */
.auth-wall { display: flex; flex-direction: column; align-items: center; justify-content: center; min-height: 100vh; gap: 1rem; padding: 2rem; text-align: center; }
.aw-logo { height: 80px; margin-bottom: .5rem; }
.auth-wall h2 { font-size: 1.5rem; font-weight: 800; color: var(--text-h); }
.auth-wall p { color: var(--text-m); font-size: .95rem; }
.aw-btn { background: var(--navy); color: #fff; border: none; border-radius: 10px; padding: .75rem 2rem; font-size: .95rem; font-weight: 700; text-decoration: none; font-family: inherit; }

/* ─── Loading ─────────────────────────────────────────────────────────────── */
.cpd-loading { display: flex; flex-direction: column; align-items: center; justify-content: center; min-height: 100vh; gap: 1rem; color: var(--text-m); }
.spinner { width: 40px; height: 40px; border: 3px solid var(--navy-light); border-top-color: var(--navy); border-radius: 50%; animation: spin .8s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }

/* ─── Header ─────────────────────────────────────────────────────────────── */
.cpd-header { background: var(--navy); padding: .85rem 0; position: sticky; top: 0; z-index: 100; }
.hdr-inner { display: flex; align-items: center; justify-content: space-between; gap: 1rem; }
.hdr-brand { display: flex; align-items: center; gap: .65rem; text-decoration: none; }
.hdr-logo { height: 42px; width: auto; filter: brightness(0) invert(1); object-fit: contain; }
.hdr-title { display: block; font-size: .88rem; font-weight: 800; color: #fff; }
.hdr-sub { display: block; font-size: .7rem; color: rgba(255,255,255,.6); }
.hdr-user { display: flex; align-items: center; gap: .75rem; }
.hdr-av { width: 36px; height: 36px; border-radius: 50%; background: rgba(255,255,255,.2); border: 2px solid rgba(255,255,255,.4); color: #fff; display: flex; align-items: center; justify-content: center; font-size: .95rem; font-weight: 700; flex-shrink: 0; }
.hdr-name { display: block; font-size: .85rem; font-weight: 700; color: #fff; }
.hdr-mem { display: block; font-size: .72rem; color: rgba(255,255,255,.6); }
.hdr-logout { display: flex; align-items: center; gap: .35rem; background: rgba(255,255,255,.12); border: 1px solid rgba(255,255,255,.25); border-radius: 7px; color: rgba(255,255,255,.85); font-size: .8rem; padding: .4rem .9rem; cursor: pointer; font-family: inherit; transition: background .2s; white-space: nowrap; }
.hdr-logout:hover { background: rgba(255,255,255,.22); }

/* ─── Body ───────────────────────────────────────────────────────────────── */
.cpd-body { padding: 1.75rem 1.5rem 3rem; }

/* ─── Stats Strip ─────────────────────────────────────────────────────────── */
.stats-strip { display: grid; grid-template-columns: repeat(4,1fr); gap: 1rem; margin-bottom: 1.75rem; }
.ss-card { background: #fff; border: 1.5px solid var(--border); border-radius: 14px; padding: 1.1rem 1.25rem; display: flex; align-items: center; gap: .875rem; transition: box-shadow .2s; }
.ss-card:hover { box-shadow: 0 4px 16px rgba(26,35,126,.08); }
.ss-card.ss-expiring { border-color: #ffca28; background: #fffde7; }
.ss-ico { width: 44px; height: 44px; border-radius: 11px; background: var(--sic); color: var(--sicc); display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.ss-label { font-size: .75rem; color: var(--text-m); margin-bottom: .2rem; }
.ss-val { font-size: 1.1rem; font-weight: 800; color: var(--text-h); }

/* ─── Quick Links ─────────────────────────────────────────────────────────── */
.quick-links { display: grid; grid-template-columns: repeat(3,1fr); gap: 1rem; margin-bottom: 1.75rem; }
.ql-card { display: flex; align-items: center; gap: 1rem; background: #fff; border: 1.5px solid var(--border); border-radius: 14px; padding: 1.1rem 1.25rem; text-decoration: none; color: inherit; transition: box-shadow .2s, border-color .2s; }
.ql-card:hover { box-shadow: 0 6px 20px rgba(26,35,126,.12); border-color: #c5cae9; }
.ql-ico { width: 44px; height: 44px; border-radius: 11px; background: var(--qic); color: var(--qicc); display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.ql-txt { flex: 1; min-width: 0; }
.ql-label { font-size: .92rem; font-weight: 800; color: var(--text-h); }
.ql-desc { font-size: .78rem; color: var(--text-m); margin-top: .15rem; }
.ql-arrow { color: var(--text-m); opacity: .5; flex-shrink: 0; }
.ql-card:hover .ql-arrow { opacity: 1; color: var(--navy); }

/* ─── Layout ─────────────────────────────────────────────────────────────── */
.cpd-layout { display: grid; grid-template-columns: 240px 1fr; gap: 1.5rem; align-items: start; }

/* ─── Sidebar ─────────────────────────────────────────────────────────────── */
.cpd-sidebar { position: sticky; top: 80px; }
.side-nav { background: #fff; border: 1.5px solid var(--border); border-radius: 14px; overflow: hidden; margin-bottom: 1rem; }
.side-btn { width: 100%; display: flex; align-items: center; gap: .75rem; padding: .875rem 1rem; background: none; border: none; border-bottom: 1px solid var(--border); cursor: pointer; font-family: inherit; font-size: .875rem; color: var(--text-b); font-weight: 500; transition: background .15s, color .15s; text-align: right; }
.side-btn:last-child { border-bottom: none; }
.side-btn.active { background: var(--navy-light); color: var(--navy); font-weight: 700; }
.side-btn:hover:not(.active) { background: #f5f5f5; }
.side-arr { margin-inline-start: auto; opacity: 0; transition: opacity .15s; }
.side-btn.active .side-arr, .side-btn:hover .side-arr { opacity: 1; }

.side-mem-card { background: var(--navy); border-radius: 14px; padding: 1.25rem; }
.side-mem-card.expiring { background: #e65100; }
.smc-type { font-size: .82rem; font-weight: 700; color: rgba(255,255,255,.8); margin-bottom: .3rem; }
.smc-exp { display: flex; align-items: center; gap: .35rem; font-size: .78rem; color: rgba(255,255,255,.7); margin-bottom: .6rem; }
.smc-warn { display: flex; align-items: center; gap: .35rem; font-size: .75rem; color: #ffca28; margin-top: .5rem; }

/* ─── Main Content ─────────────────────────────────────────────────────────── */
.cpd-main { min-width: 0; }
.tab-content { background: #fff; border: 1.5px solid var(--border); border-radius: 16px; padding: 2rem; }
.tab-title { display: flex; align-items: center; gap: .6rem; font-size: 1.1rem; font-weight: 800; color: var(--text-h); margin-bottom: 1.75rem; padding-bottom: 1rem; border-bottom: 2px solid var(--navy-light); }

/* ─── Profile ──────────────────────────────────────────────────────────────── */
.profile-grid { display: flex; flex-direction: column; gap: 1.25rem; }
.pf-card { border: 1.5px solid var(--border); border-radius: 12px; padding: 1.25rem; }
.pf-sec { font-size: .82rem; font-weight: 700; color: var(--navy); text-transform: uppercase; letter-spacing: .04em; margin-bottom: 1rem; }
.pf-row { display: flex; align-items: center; justify-content: space-between; padding: .5rem 0; border-bottom: 1px dashed #f0f0f0; }
.pf-row:last-child { border-bottom: none; }
.pf-label { display: flex; align-items: center; gap: .35rem; font-size: .82rem; color: var(--text-m); }
.pf-val { font-size: .88rem; font-weight: 600; color: var(--text-h); }
.mono { font-family: monospace; letter-spacing: .05em; }
.deg-chip { background: var(--navy-light); color: var(--navy); border: 1px solid #c5cae9; border-radius: 6px; padding: .2rem .65rem; font-size: .82rem; font-weight: 700; }

/* ─── Badges ──────────────────────────────────────────────────────────────── */
.badge { display: inline-flex; align-items: center; padding: .2rem .75rem; border-radius: 50px; font-size: .75rem; font-weight: 700; }
.badge-green { background: var(--green-light); color: var(--green); }
.badge-yellow { background: #fff8e1; color: #e65100; }
.badge-red { background: var(--red-light); color: var(--red); }

/* ─── Membership ──────────────────────────────────────────────────────────── */
.mem-banner { display: flex; align-items: center; gap: 1.25rem; border-radius: 14px; padding: 1.5rem; margin-bottom: 1.5rem; border: 1.5px solid; flex-wrap: wrap; }
.mem-banner.ok { background: var(--green-light); border-color: #a5d6a7; color: var(--green); }
.mem-banner.warn { background: #fff8e1; border-color: #ffca28; color: #e65100; }
.mb-icon { flex-shrink: 0; }
.mb-info { flex: 1; }
.mb-type { font-size: 1rem; font-weight: 700; margin-bottom: .25rem; }
.mb-dates { font-size: .82rem; opacity: .8; }
.mb-warn-text { display: flex; align-items: center; gap: .35rem; font-size: .8rem; margin-top: .4rem; }
.mb-amount { text-align: center; }
.mb-amount-val { display: block; font-size: 1.4rem; font-weight: 900; }
.mb-amount-label { display: block; font-size: .72rem; opacity: .7; }

.renew-box { display: flex; align-items: flex-start; gap: 1rem; background: var(--navy-soft); border: 1.5px solid #c5cae9; border-radius: 12px; padding: 1.25rem; margin-bottom: 1.75rem; }
.renew-icon { width: 44px; height: 44px; border-radius: 10px; background: var(--navy-light); color: var(--navy); display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.renew-title { font-size: .9rem; font-weight: 700; color: var(--text-h); margin-bottom: .25rem; }
.renew-sub { font-size: .82rem; color: var(--text-m); line-height: 1.6; margin-bottom: .75rem; }
.renew-contacts { display: flex; gap: 1rem; flex-wrap: wrap; }
.renew-link { display: flex; align-items: center; gap: .35rem; color: var(--navy); font-size: .82rem; font-weight: 600; text-decoration: none; }
.renew-link:hover { text-decoration: underline; }
.renew-btn { display: inline-flex; align-items: center; gap: .45rem; background: var(--navy); color: #fff; border-radius: 9px; padding: .55rem 1.25rem; font-size: .85rem; font-weight: 700; text-decoration: none; transition: background .2s; }
.renew-btn:hover { background: var(--navy-mid, #3949ab); }

.sec-title { font-size: .9rem; font-weight: 700; color: var(--text-h); margin-bottom: 1rem; }

.mem-table { border: 1.5px solid var(--border); border-radius: 12px; overflow: hidden; }
.mt-head { display: grid; grid-template-columns: 1fr 1fr 1fr 1fr 100px; padding: .65rem 1rem; background: var(--navy-light); font-size: .78rem; font-weight: 700; color: var(--navy); }
.mt-row { display: grid; grid-template-columns: 1fr 1fr 1fr 1fr 100px; padding: .75rem 1rem; border-top: 1px solid var(--border); font-size: .83rem; align-items: center; }
.mt-row:hover { background: #fafafa; }

/* ─── Payments ────────────────────────────────────────────────────────────── */
.pay-list { display: flex; flex-direction: column; gap: .875rem; }
.pay-card { display: flex; align-items: center; gap: 1rem; padding: 1rem 1.25rem; border: 1.5px solid var(--border); border-radius: 12px; transition: box-shadow .2s; }
.pay-card:hover { box-shadow: 0 3px 12px rgba(26,35,126,.07); }
.pay-icon { width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.pay-icon.paid { background: var(--green-light); color: var(--green); }
.pay-icon.pending { background: #fff8e1; color: #e65100; }
.pay-icon.cancelled { background: var(--red-light); color: var(--red); }
.pay-info { flex: 1; min-width: 0; }
.pay-top { display: flex; align-items: center; justify-content: space-between; margin-bottom: .3rem; }
.pay-type { font-size: .88rem; font-weight: 700; color: var(--text-h); }
.pay-amount { font-size: 1rem; font-weight: 800; color: var(--navy); }
.pay-meta { display: flex; gap: .875rem; font-size: .76rem; color: var(--text-m); flex-wrap: wrap; }
.pay-ref { font-family: monospace; }
.pay-mem-note { display: flex; align-items: center; gap: .3rem; font-size: .76rem; color: var(--text-m); margin-top: .3rem; }

/* ─── Documents ───────────────────────────────────────────────────────────── */
.doc-grid { display: flex; flex-direction: column; gap: .75rem; }
.doc-card { display: flex; align-items: center; gap: 1rem; padding: .875rem 1.25rem; border: 1.5px solid var(--border); border-radius: 12px; transition: box-shadow .2s; }
.doc-card:hover { box-shadow: 0 3px 12px rgba(26,35,126,.07); }
.doc-icon { width: 44px; height: 44px; border-radius: 10px; background: var(--navy-light); color: var(--navy); display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.doc-info { flex: 1; min-width: 0; }
.doc-title { font-size: .88rem; font-weight: 700; color: var(--text-h); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.doc-meta { font-size: .75rem; color: var(--text-m); margin-top: .2rem; }
.doc-dl { width: 36px; height: 36px; border-radius: 8px; background: var(--navy-light); color: var(--navy); border: none; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: background .2s; flex-shrink: 0; }
.doc-dl:hover { background: var(--navy); color: #fff; }

/* ─── Empty State ─────────────────────────────────────────────────────────── */
.empty-state { text-align: center; padding: 3rem 2rem; color: var(--text-m); }
.empty-state.small { padding: 1.5rem; }
.es-ico { margin: 0 auto .75rem; display: block; opacity: .4; }
.empty-state p { font-size: .9rem; font-weight: 600; color: var(--text-b); }
.es-sub { font-size: .82rem; color: var(--text-m); margin-top: .35rem; font-weight: 400; }

/* ─── Footer ──────────────────────────────────────────────────────────────── */
.cpd-footer { text-align: center; padding: 1.25rem; font-size: .78rem; color: var(--text-m); border-top: 1px solid var(--border); background: #fff; }

/* ─── Responsive ──────────────────────────────────────────────────────────── */
@media (max-width: 900px) {
  .stats-strip { grid-template-columns: repeat(2,1fr); }
  .quick-links { grid-template-columns: 1fr; }
  .cpd-layout { grid-template-columns: 1fr; }
  .cpd-sidebar { position: static; }
  .side-nav { display: grid; grid-template-columns: repeat(2,1fr); }
  .side-btn { border-bottom: none; border-left: 1px solid var(--border); }
  .side-btn:nth-child(odd) { border-left: none; }
  .side-mem-card { display: none; }
  .mt-head, .mt-row { grid-template-columns: 1fr 1fr 1fr; }
  .mt-head span:nth-child(4), .mt-row span:nth-child(4) { display: none; }
}
@media (max-width: 600px) {
  .stats-strip { grid-template-columns: 1fr 1fr; }
  .hdr-info { display: none; }
  .tab-content { padding: 1.25rem; }
  .mem-banner { flex-direction: column; }
  .mt-head, .mt-row { grid-template-columns: 1fr 1fr; }
  .mt-head span:nth-child(n+3), .mt-row span:nth-child(n+3) { display: none; }
}
</style>

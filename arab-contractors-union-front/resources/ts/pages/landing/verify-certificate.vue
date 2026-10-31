<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import api from '@/plugins/axios'
import {
  AlertTriangle,
  BadgeCheck,
  Building2,
  CalendarCheck,
  CalendarClock,
  CheckCircle2,
  Clipboard,
  ClipboardCheck,
  FileText,
  Hash,
  IdCard,
  Link2,
  QrCode,
  RefreshCw,
  Search,
  ShieldCheck,
  TextCursorInput,
  XCircle,
} from 'lucide-vue-next'

definePage({ meta: { layout: 'landing', public: true, unauthenticatedOnly: false } })

type VerifyState = 'valid' | 'expired' | 'revoked' | 'not_found'

interface VerifyResult {
  state: VerifyState
  state_label: string
  found: boolean
  contractor_name?: string
  membership_number?: string
  serial_number?: string
  type_label?: string
  issued_at?: string | null
  valid_until?: string | null
  revoked_at?: string | null
}

const route = useRoute()
const serial = ref('')
const isLoading = ref(false)
const result = ref<VerifyResult | null>(null)
const failed = ref(false)
const checkedAt = ref<Date | null>(null)
const copied = ref<'serial' | 'link' | null>(null)

const STATE_META: Record<VerifyState, { tone: 'ok' | 'warn' | 'bad'; title: string; desc: string }> = {
  valid: { tone: 'ok', title: 'شهادة سارية', desc: 'الشهادة صادرة رسمياً عن اتحاد المقاولين الفلسطينيين وما زالت صالحة.' },
  expired: { tone: 'warn', title: 'شهادة منتهية', desc: 'الشهادة صحيحة وصادرة عن الاتحاد، لكن انتهت مدة صلاحيتها.' },
  revoked: { tone: 'bad', title: 'شهادة ملغاة', desc: 'تم إلغاء هذه الشهادة من قبل الاتحاد ولا يُعتدّ بها.' },
  not_found: { tone: 'bad', title: 'شهادة غير موجودة', desc: 'الرقم التسلسلي غير مسجّل في النظام. تأكد من كتابته كما هو أسفل الشهادة، أو امسح رمز QR الموجود عليها.' },
}

const meta = computed(() => (result.value ? STATE_META[result.value.state] : null))

const daysLeft = computed(() => {
  const until = result.value?.valid_until
  if (!until || result.value?.state !== 'valid') return null
  const ms = new Date(`${until}T23:59:59`).getTime() - Date.now()

  return Math.max(0, Math.ceil(ms / 86400000))
})

const checkedAtLabel = computed(() =>
  checkedAt.value
    ? checkedAt.value.toLocaleString('ar-PS-u-nu-latn', { dateStyle: 'long', timeStyle: 'short' })
    : '')

function fmtDate(d?: string | null) {
  if (!d) return '—'

  return new Date(`${d}T00:00:00`).toLocaleDateString('ar-PS-u-nu-latn', { year: 'numeric', month: 'long', day: 'numeric' })
}

async function copyText(text: string, what: 'serial' | 'link') {
  try {
    await navigator.clipboard.writeText(text)
    copied.value = what
    setTimeout(() => { copied.value = null }, 1600)
  }
  catch { /* المتصفح منع النسخ: المستخدم ينسخ يدوياً */ }
}

function shareLink() {
  const s = result.value?.serial_number
  if (!s) return
  copyText(`${window.location.origin}/landing/verify-certificate?serial=${encodeURIComponent(s)}`, 'link')
}

async function pasteFromClipboard() {
  try {
    const text = await navigator.clipboard.readText()
    if (text) {
      serial.value = text.trim()
      verify()
    }
  }
  catch { /* بدون إذن الحافظة: يلصق المستخدم يدوياً */ }
}

async function verify() {
  const value = serial.value.trim()
  if (!value || isLoading.value) return
  isLoading.value = true
  failed.value = false
  result.value = null
  try {
    // بدون كاش: كل تحقق يقرأ من قاعدة البيانات مباشرة
    const r = await api.get('/api/v1/certificates/check', { params: { serial: value, _: Date.now() } })
    result.value = r.data.items ?? null
    checkedAt.value = new Date()
  }
  catch {
    failed.value = true
  }
  finally {
    isLoading.value = false
  }
}

function reset() {
  serial.value = ''
  result.value = null
  failed.value = false
}

onMounted(() => {
  const q = route.query.serial
  if (typeof q === 'string' && q.trim()) {
    serial.value = q
    verify()
  }
})
</script>

<template>
  <div dir="rtl" class="vp">
    <!-- ═══ Hero ═══ -->
    <header class="vp-hero">
      <div class="vp-hero-glow vp-hero-glow--a" />
      <div class="vp-hero-glow vp-hero-glow--b" />
      <div class="pub-cont vp-hero-inner">
        <nav class="vp-crumb" aria-label="مسار الصفحة">
          <RouterLink to="/landing">الرئيسية</RouterLink>
          <span>/</span>
          <span>التحقق من شهادة</span>
        </nav>
        <div class="vp-hero-badge"><ShieldCheck :size="16" /> النظام الرسمي للتحقق</div>
        <h1 class="vp-title">التحقق من شهادة العضوية</h1>
        <p class="vp-sub">تأكد خلال ثوانٍ من أن الشهادة صادرة فعلاً عن اتحاد المقاولين الفلسطينيين وما زالت سارية.</p>
      </div>
    </header>

    <main class="pub-cont vp-main">
      <!-- ═══ Search ═══ -->
      <form class="vp-search" role="search" @submit.prevent="verify">
        <label for="serial" class="vp-label">الرقم التسلسلي للشهادة</label>
        <div class="vp-field" :class="{ 'is-busy': isLoading }">
          <TextCursorInput :size="20" class="vp-field-icon" />
          <input
            id="serial"
            v-model="serial"
            dir="ltr"
            class="vp-input"
            placeholder="XXXXXXXXXXXX"
            inputmode="text"
            autocomplete="off"
            autocapitalize="characters"
            autocorrect="off"
            spellcheck="false"
            enterkeyhint="search"
          >
          <button type="button" class="vp-paste" title="لصق من الحافظة" @click="pasteFromClipboard">
            <Clipboard :size="18" /><span>لصق</span>
          </button>
        </div>
        <button type="submit" class="vp-btn" :disabled="isLoading || !serial.trim()">
          <span v-if="isLoading" class="vp-spin" aria-hidden="true" />
          <Search v-else :size="20" />
          <span>{{ isLoading ? 'جاري التحقق...' : 'تحقق من الشهادة' }}</span>
        </button>
        <p class="vp-hint">تجده أسفل الشهادة بجانب رمز QR — حروف لاتينية، ولا يهم حجم الأحرف أو وجود شرطات.</p>
      </form>

      <!-- ═══ Loading skeleton ═══ -->
      <section v-if="isLoading" class="vp-card vp-skel" aria-busy="true" aria-label="جاري التحقق">
        <div class="sk sk-band" />
        <div class="sk sk-line w60" />
        <div class="sk sk-line w90" />
        <div class="vp-grid">
          <div class="sk sk-box" /><div class="sk sk-box" /><div class="sk sk-box" /><div class="sk sk-box" />
        </div>
      </section>

      <!-- ═══ Connection error ═══ -->
      <section v-else-if="failed" class="vp-card vp-error" role="alert">
        <AlertTriangle :size="40" />
        <h2>تعذّر الاتصال بالنظام</h2>
        <p>لم نتمكن من الوصول إلى خادم التحقق. تأكد من اتصالك بالإنترنت ثم حاول مرة أخرى.</p>
        <button type="button" class="vp-btn vp-btn--ghost" @click="verify"><RefreshCw :size="18" /> إعادة المحاولة</button>
      </section>

      <!-- ═══ Result ═══ -->
      <section v-else-if="result && meta" class="vp-card vp-result" :class="`tone-${meta.tone}`" aria-live="polite">
        <div class="vp-status">
          <div class="vp-status-icon" :class="`pop tone-${meta.tone}`">
            <CheckCircle2 v-if="result.state === 'valid'" :size="40" />
            <CalendarClock v-else-if="result.state === 'expired'" :size="40" />
            <XCircle v-else :size="40" />
          </div>
          <div class="vp-status-text">
            <span class="vp-pill"><BadgeCheck :size="14" /> {{ result.state_label }}</span>
            <h2>{{ meta.title }}</h2>
            <p>{{ meta.desc }}</p>
          </div>
        </div>

        <template v-if="result.found">
          <div v-if="daysLeft !== null" class="vp-days">
            <CalendarCheck :size="18" />
            <span>
              <template v-if="daysLeft > 0">تبقّى على انتهاء الصلاحية <b>{{ daysLeft }}</b> {{ daysLeft === 1 ? 'يوم' : 'يوماً' }}</template>
              <template v-else>تنتهي صلاحيتها اليوم</template>
            </span>
          </div>

          <div class="vp-company">
            <span class="vp-ico"><Building2 :size="22" /></span>
            <div>
              <small>اسم الشركة / المقاول</small>
              <strong>{{ result.contractor_name }}</strong>
            </div>
          </div>

          <div class="vp-grid">
            <div class="vp-cell">
              <span class="vp-ico"><IdCard :size="18" /></span>
              <small>رقم العضوية</small>
              <strong dir="ltr">{{ result.membership_number }}</strong>
            </div>
            <div class="vp-cell">
              <span class="vp-ico"><FileText :size="18" /></span>
              <small>نوع الشهادة</small>
              <strong>{{ result.type_label }}</strong>
            </div>
            <div class="vp-cell">
              <span class="vp-ico"><CalendarCheck :size="18" /></span>
              <small>تاريخ الإصدار</small>
              <strong>{{ fmtDate(result.issued_at) }}</strong>
            </div>
            <div class="vp-cell">
              <span class="vp-ico"><CalendarClock :size="18" /></span>
              <small>تاريخ انتهاء الصلاحية</small>
              <strong>{{ fmtDate(result.valid_until) }}</strong>
            </div>
            <div v-if="result.state === 'revoked'" class="vp-cell vp-cell--wide">
              <span class="vp-ico"><XCircle :size="18" /></span>
              <small>تاريخ الإلغاء</small>
              <strong>{{ fmtDate(result.revoked_at) }}</strong>
            </div>
          </div>

          <div class="vp-serial">
            <span class="vp-ico"><Hash :size="18" /></span>
            <div class="vp-serial-body">
              <small>الرقم التسلسلي للشهادة</small>
              <strong dir="ltr">{{ result.serial_number }}</strong>
            </div>
            <button type="button" class="vp-chip" @click="copyText(result.serial_number!, 'serial')">
              <ClipboardCheck v-if="copied === 'serial'" :size="16" />
              <Clipboard v-else :size="16" />
              {{ copied === 'serial' ? 'تم النسخ' : 'نسخ' }}
            </button>
            <button type="button" class="vp-chip" @click="shareLink">
              <ClipboardCheck v-if="copied === 'link'" :size="16" />
              <Link2 v-else :size="16" />
              {{ copied === 'link' ? 'تم النسخ' : 'رابط التحقق' }}
            </button>
          </div>
        </template>

        <footer class="vp-seal">
          <ShieldCheck :size="20" />
          <div>
            <strong>تحقق صادر عن النظام الرسمي للاتحاد</strong>
            <small>قراءة مباشرة من قاعدة بيانات الاتحاد · {{ checkedAtLabel }}</small>
          </div>
          <img src="/images/landing/logo-white.png" alt="اتحاد المقاولين الفلسطينيين" class="vp-seal-logo">
        </footer>

        <button type="button" class="vp-again" @click="reset">التحقق من شهادة أخرى</button>
      </section>

      <!-- ═══ Idle: how it works ═══ -->
      <section v-else class="vp-steps" aria-label="طريقة التحقق">
        <article class="vp-step">
          <span class="vp-step-n">1</span>
          <QrCode :size="26" />
          <h3>امسح الرمز أو انسخ الرقم</h3>
          <p>على كل شهادة رمز QR ورقم تسلسلي أسفلها. الماسح يفتح هذه الصفحة ويتحقق فوراً.</p>
        </article>
        <article class="vp-step">
          <span class="vp-step-n">2</span>
          <Search :size="26" />
          <h3>ابحث في سجل الاتحاد</h3>
          <p>نقرأ الرقم من قاعدة بيانات الاتحاد مباشرة دون أي بيانات مخزّنة مؤقتاً.</p>
        </article>
        <article class="vp-step">
          <span class="vp-step-n">3</span>
          <ShieldCheck :size="26" />
          <h3>اعرف حالة الشهادة</h3>
          <p>سارية، منتهية، ملغاة أو غير موجودة، مع بيانات الشركة وتاريخَي الإصدار والانتهاء.</p>
        </article>
      </section>
    </main>
  </div>
</template>

<style scoped>
.vp { --navy: #0d1b4b; --blue: #1a237e; --gold: #f9a825; --ink: #1a1a3e; --muted: #6b7280; --line: #e7e9f3;
  --ok: #1b8a4b; --ok-bg: #e8f6ee; --warn: #c76a00; --warn-bg: #fff3e0; --bad: #c62828; --bad-bg: #fdecec;
  font-family: 'Tajawal', sans-serif; background: #f4f6fc; min-height: 100vh; }

/* Hero */
.vp-hero { position: relative; overflow: hidden; background: linear-gradient(145deg, var(--navy) 0%, var(--blue) 60%, #283593 100%);
  margin-top: calc(-1 * var(--pub-nav-h, 114px)); padding: calc(var(--pub-nav-h, 114px) + 2.25rem) 0 5.5rem; color: #fff; }
.vp-hero-glow { position: absolute; border-radius: 50%; filter: blur(70px); opacity: .45; pointer-events: none; }
.vp-hero-glow--a { width: 340px; height: 340px; background: #3949ab; top: -120px; inset-inline-start: -80px; }
.vp-hero-glow--b { width: 280px; height: 280px; background: var(--gold); opacity: .18; bottom: -140px; inset-inline-end: 8%; }
.vp-hero-inner { position: relative; z-index: 1; }
.vp-crumb { display: flex; gap: .5rem; font-size: .85rem; color: rgba(255,255,255,.7); margin-bottom: 1rem; }
.vp-crumb a { color: rgba(255,255,255,.9); text-decoration: none; }
.vp-hero-badge { display: inline-flex; align-items: center; gap: .4rem; background: rgba(255,255,255,.12); border: 1px solid rgba(255,255,255,.2);
  padding: .3rem .8rem; border-radius: 999px; font-size: .8rem; font-weight: 700; color: #ffd54f; margin-bottom: .9rem; }
.vp-title { font-size: clamp(1.7rem, 4.5vw, 2.4rem); font-weight: 800; margin: 0 0 .6rem; line-height: 1.25; }
.vp-sub { margin: 0; max-width: 560px; color: rgba(255,255,255,.82); font-size: 1.02rem; line-height: 1.7; }

/* Layout */
.vp-main { max-width: 760px; margin: -3.4rem auto 0; position: relative; z-index: 2; padding-bottom: 4rem; }

/* Search */
.vp-search { background: #fff; border-radius: 20px; padding: 1.4rem; box-shadow: 0 18px 50px rgba(13,27,75,.16); border: 1px solid var(--line); }
.vp-label { display: block; font-weight: 800; color: var(--ink); margin-bottom: .6rem; }
.vp-field { display: flex; align-items: center; gap: .5rem; border: 2px solid #d6daf0; border-radius: 14px; padding: .2rem .3rem .2rem .7rem;
  background: #fafbff; transition: border-color .15s, box-shadow .15s; }
.vp-field:focus-within { border-color: var(--blue); box-shadow: 0 0 0 4px rgba(26,35,126,.12); background: #fff; }
.vp-field-icon { color: #8a92c4; flex: none; margin-inline-start: .4rem; }
.vp-input { flex: 1; min-width: 0; border: 0; outline: 0; background: transparent; padding: .85rem .4rem; font-size: 1.25rem; letter-spacing: .12em;
  text-transform: uppercase; font-family: ui-monospace, 'SFMono-Regular', Menlo, monospace; color: var(--ink); text-align: left; }
.vp-input::placeholder { color: #b8bdd8; letter-spacing: .12em; }
.vp-paste { display: inline-flex; align-items: center; gap: .3rem; flex: none; border: 0; background: #eef0fb; color: var(--blue); font-weight: 700;
  font-family: inherit; font-size: .85rem; padding: .55rem .8rem; border-radius: 10px; cursor: pointer; }
.vp-paste:hover { background: #e1e5f8; }
.vp-btn { margin-top: .9rem; width: 100%; display: inline-flex; align-items: center; justify-content: center; gap: .55rem; border: 0; cursor: pointer;
  background: linear-gradient(135deg, var(--blue), #3949ab); color: #fff; font-family: inherit; font-weight: 800; font-size: 1.05rem;
  padding: .95rem 1.2rem; border-radius: 14px; box-shadow: 0 8px 20px rgba(26,35,126,.3); transition: transform .12s, box-shadow .12s, opacity .12s; }
.vp-btn:hover:not(:disabled) { transform: translateY(-1px); box-shadow: 0 12px 26px rgba(26,35,126,.36); }
.vp-btn:disabled { opacity: .55; cursor: not-allowed; box-shadow: none; }
.vp-btn--ghost { width: auto; background: #fff; color: var(--blue); border: 2px solid #d6daf0; box-shadow: none; }
.vp-hint { margin: .8rem 0 0; font-size: .85rem; color: var(--muted); line-height: 1.6; }
.vp-spin { width: 18px; height: 18px; border-radius: 50%; border: 3px solid rgba(255,255,255,.4); border-top-color: #fff; animation: vp-rot .7s linear infinite; }
@keyframes vp-rot { to { transform: rotate(360deg); } }

/* Cards */
.vp-card { margin-top: 1.25rem; background: #fff; border-radius: 20px; box-shadow: 0 14px 40px rgba(13,27,75,.1); border: 1px solid var(--line); overflow: hidden; }

/* Result */
.vp-result { border-top: 6px solid var(--tone); animation: vp-in .35s ease both; }
.tone-ok { --tone: var(--ok); --tone-bg: var(--ok-bg); }
.tone-warn { --tone: var(--warn); --tone-bg: var(--warn-bg); }
.tone-bad { --tone: var(--bad); --tone-bg: var(--bad-bg); }
.vp-status { display: flex; gap: 1rem; align-items: center; padding: 1.5rem 1.4rem 1.1rem; background: linear-gradient(180deg, var(--tone-bg), #fff); }
.vp-status-icon { flex: none; width: 74px; height: 74px; border-radius: 50%; display: grid; place-items: center; background: #fff; color: var(--tone);
  box-shadow: 0 0 0 8px color-mix(in srgb, var(--tone) 14%, transparent); }
.pop { animation: vp-pop .5s cubic-bezier(.2,1.4,.4,1) both; }
.vp-pill { display: inline-flex; align-items: center; gap: .3rem; font-size: .78rem; font-weight: 800; color: var(--tone);
  background: #fff; border: 1.5px solid var(--tone); padding: .15rem .6rem; border-radius: 999px; }
.vp-status-text h2 { margin: .45rem 0 .25rem; font-size: 1.6rem; font-weight: 800; color: var(--tone); }
.vp-status-text p { margin: 0; color: #4b5563; line-height: 1.65; font-size: .95rem; }

.vp-days { display: flex; align-items: center; gap: .5rem; margin: 0 1.4rem; padding: .65rem .9rem; border-radius: 12px; background: var(--ok-bg); color: var(--ok); font-weight: 600; font-size: .92rem; }

.vp-company { display: flex; gap: .85rem; align-items: center; margin: 1rem 1.4rem 0; padding: 1rem; border-radius: 14px; background: #f7f8fe; border: 1px solid var(--line); }
.vp-company small, .vp-cell small, .vp-serial small { display: block; color: var(--muted); font-size: .78rem; font-weight: 600; margin-bottom: .15rem; }
.vp-company strong { font-size: 1.2rem; color: var(--ink); font-weight: 800; line-height: 1.4; }
.vp-ico { flex: none; width: 40px; height: 40px; border-radius: 12px; display: grid; place-items: center; background: #e8ebfb; color: var(--blue); }

.vp-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .75rem; margin: .75rem 1.4rem 0; }
.vp-cell { border: 1px solid var(--line); border-radius: 14px; padding: .8rem .9rem; background: #fff; }
.vp-cell .vp-ico { width: 32px; height: 32px; border-radius: 10px; margin-bottom: .45rem; }
.vp-cell strong { color: var(--ink); font-weight: 800; font-size: 1rem; word-break: break-word; }
.vp-cell--wide { grid-column: 1 / -1; }

.vp-serial { display: flex; align-items: center; flex-wrap: wrap; gap: .6rem; margin: .75rem 1.4rem 0; padding: .85rem .9rem; border-radius: 14px; background: #0d1b4b; color: #fff; }
.vp-serial .vp-ico { background: rgba(255,255,255,.12); color: #ffd54f; }
.vp-serial small { color: rgba(255,255,255,.65); }
.vp-serial-body { flex: 1 1 140px; min-width: 0; }
.vp-serial strong { font-family: ui-monospace, Menlo, monospace; font-size: 1.2rem; letter-spacing: .12em; font-weight: 700; display: block; text-align: left; }
.vp-chip { display: inline-flex; align-items: center; gap: .35rem; border: 1px solid rgba(255,255,255,.3); background: rgba(255,255,255,.1); color: #fff;
  font-family: inherit; font-weight: 700; font-size: .82rem; padding: .45rem .75rem; border-radius: 10px; cursor: pointer; }
.vp-chip:hover { background: rgba(255,255,255,.2); }

.vp-seal { display: flex; align-items: center; gap: .75rem; margin-top: 1.2rem; padding: 1rem 1.4rem; background: linear-gradient(135deg, var(--navy), var(--blue)); color: #ffd54f; }
.vp-seal > div { flex: 1; min-width: 0; }
.vp-seal strong { display: block; font-size: .92rem; color: #ffd54f; }
.vp-seal small { display: block; color: rgba(255,255,255,.7); font-size: .75rem; margin-top: .1rem; }
.vp-seal-logo { height: 40px; width: auto; flex: none; opacity: .95; }
.vp-again { display: block; width: 100%; border: 0; background: #f4f6fc; color: var(--blue); font-family: inherit; font-weight: 700; padding: .9rem; cursor: pointer; }
.vp-again:hover { background: #e9edfa; }

/* Error */
.vp-error { text-align: center; padding: 2rem 1.4rem; color: var(--bad); border-top: 6px solid var(--bad); }
.vp-error h2 { margin: .6rem 0 .3rem; font-size: 1.3rem; }
.vp-error p { margin: 0 0 1rem; color: #4b5563; }

/* Skeleton */
.vp-skel { padding: 1.4rem; }
.sk { background: linear-gradient(90deg, #eceff8 25%, #f6f7fc 37%, #eceff8 63%); background-size: 400% 100%; animation: vp-shimmer 1.3s ease infinite; border-radius: 10px; }
.sk-band { height: 74px; margin-bottom: 1rem; }
.sk-line { height: 14px; margin-bottom: .6rem; }
.w60 { width: 60%; } .w90 { width: 90%; }
.sk-box { height: 74px; }
.vp-skel .vp-grid { margin: 1rem 0 0; }

/* Steps */
.vp-steps { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 1rem; margin-top: 1.5rem; }
.vp-step { position: relative; background: #fff; border: 1px solid var(--line); border-radius: 18px; padding: 1.3rem 1.1rem 1.1rem; color: var(--blue); }
.vp-step h3 { margin: .7rem 0 .35rem; font-size: 1.02rem; color: var(--ink); font-weight: 800; }
.vp-step p { margin: 0; font-size: .87rem; line-height: 1.65; color: var(--muted); }
.vp-step-n { position: absolute; top: .9rem; inset-inline-end: 1rem; font-size: 1.6rem; font-weight: 800; color: #dfe3f6; }

@keyframes vp-in { from { opacity: 0; transform: translateY(12px); } to { opacity: 1; transform: none; } }
@keyframes vp-pop { from { transform: scale(.4); opacity: 0; } to { transform: scale(1); opacity: 1; } }
@keyframes vp-shimmer { 0% { background-position: 100% 50%; } 100% { background-position: 0 50%; } }
@media (prefers-reduced-motion: reduce) { .vp-result, .pop, .sk, .vp-spin { animation: none; } }

@media (max-width: 640px) {
  .vp-hero { padding-bottom: 5rem; }
  .vp-main { margin-top: -3rem; }
  .vp-search { padding: 1.1rem; border-radius: 18px; }
  .vp-paste span { display: none; }
  .vp-input { font-size: 1.1rem; letter-spacing: .08em; }
  .vp-status { flex-direction: column; text-align: center; padding-top: 1.4rem; }
  .vp-status-icon { width: 68px; height: 68px; }
  .vp-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .6rem; margin-inline: 1rem; }
  .vp-cell { padding: .7rem .75rem; }
  .vp-cell strong { font-size: .92rem; }
  .vp-company, .vp-days, .vp-serial { margin-inline: 1rem; }
  .vp-steps { grid-template-columns: 1fr; }
  .vp-seal { flex-wrap: wrap; }
  .vp-seal-logo { display: none; }
}
</style>

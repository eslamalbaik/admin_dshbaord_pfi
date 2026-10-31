<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import api from '@/plugins/axios'
import { BadgeCheck, CheckCircle2, Clock, Search, ShieldCheck, XCircle } from 'lucide-vue-next'

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

const STATE_META: Record<VerifyState, { color: string; title: string; desc: string }> = {
  valid: { color: 'ok', title: 'شهادة سارية', desc: 'الشهادة صادرة رسمياً عن الاتحاد وما زالت صالحة.' },
  expired: { color: 'warn', title: 'شهادة منتهية', desc: 'الشهادة صحيحة وصادرة عن الاتحاد، لكن انتهت مدة صلاحيتها.' },
  revoked: { color: 'bad', title: 'شهادة ملغاة', desc: 'تم إلغاء هذه الشهادة من قبل الاتحاد ولا يُعتدّ بها.' },
  not_found: { color: 'bad', title: 'شهادة غير موجودة', desc: 'الرقم التسلسلي غير مسجّل في النظام. تأكد من كتابته كما هو أسفل الشهادة.' },
}

const copied = ref(false)

async function copySerial() {
  if (!result.value?.serial_number) return
  try {
    await navigator.clipboard.writeText(result.value.serial_number)
    copied.value = true
    setTimeout(() => { copied.value = false }, 1500)
  }
  catch { /* المتصفح منع النسخ: المستخدم ينسخ يدوياً */ }
}

const meta = computed(() => (result.value ? STATE_META[result.value.state] : null))

function fmtDate(d?: string | null) {
  if (!d) return '—'
  return new Date(`${d}T00:00:00`).toLocaleDateString('ar-PS-u-nu-latn', { year: 'numeric', month: 'long', day: 'numeric' })
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
  }
  catch {
    failed.value = true
  }
  finally {
    isLoading.value = false
  }
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
  <div dir="rtl" class="pub-page">
    <div class="page-hero">
      <div class="pub-cont page-hero-inner">
        <div class="page-breadcrumb">
          <RouterLink to="/landing">الرئيسية</RouterLink>
          <span>/</span>
          <span>التحقق من شهادة</span>
        </div>
        <h1 class="page-hero-title">التحقق من شهادة العضوية</h1>
        <p class="page-hero-desc">أدخل الرقم التسلسلي المطبوع أسفل الشهادة (أو امسح رمز QR) للتأكد من صحتها.</p>
      </div>
    </div>

    <section class="vc-section">
      <div class="pub-cont vc-wrap">
        <form class="vc-form" @submit.prevent="verify">
          <label for="serial" class="vc-label">الرقم التسلسلي للشهادة</label>
          <div class="vc-input-row">
            <input
              id="serial"
              v-model="serial"
              dir="ltr"
              class="vc-input"
              placeholder="XXXXXXXXXXXX"
              autocomplete="off"
              autocapitalize="characters"
              spellcheck="false"
            >
            <button type="submit" class="vc-btn" :disabled="isLoading || !serial.trim()">
              <Search :size="18" />
              <span>{{ isLoading ? 'جاري التحقق...' : 'تحقق' }}</span>
            </button>
          </div>
        </form>

        <p v-if="failed" class="vc-error">تعذّر الاتصال بالنظام، حاول مرة أخرى بعد قليل.</p>

        <div v-if="result && meta" class="vc-card" :class="`is-${meta.color}`" aria-live="polite">
          <div class="vc-card-head">
            <img src="/images/landing/logo-white.png" alt="اتحاد المقاولين الفلسطينيين" class="vc-logo">
            <div class="vc-official">
              <ShieldCheck :size="18" />
              <span>تحقق صادر عن النظام الرسمي للاتحاد</span>
            </div>
          </div>

          <div class="vc-status">
            <CheckCircle2 v-if="result.state === 'valid'" :size="52" />
            <Clock v-else-if="result.state === 'expired'" :size="52" />
            <XCircle v-else :size="52" />
            <h2>{{ meta.title }}</h2>
            <p>{{ meta.desc }}</p>
          </div>

          <dl v-if="result.found" class="vc-details">
            <div><dt>اسم الشركة / المقاول</dt><dd>{{ result.contractor_name }}</dd></div>
            <div><dt>رقم العضوية</dt><dd dir="ltr">{{ result.membership_number }}</dd></div>
            <div><dt>الرقم التسلسلي للشهادة</dt><dd dir="ltr">{{ result.serial_number }}<button type="button" class="vc-copy" @click="copySerial">{{ copied ? 'تم النسخ' : 'نسخ' }}</button></dd></div>
            <div><dt>نوع الشهادة</dt><dd>{{ result.type_label }}</dd></div>
            <div><dt>تاريخ الإصدار</dt><dd>{{ fmtDate(result.issued_at) }}</dd></div>
            <div><dt>تاريخ انتهاء الصلاحية</dt><dd>{{ fmtDate(result.valid_until) }}</dd></div>
            <div v-if="result.state === 'revoked'"><dt>تاريخ الإلغاء</dt><dd>{{ fmtDate(result.revoked_at) }}</dd></div>
            <div><dt>الحالة الحالية</dt><dd><span class="vc-badge"><BadgeCheck :size="14" />{{ result.state_label }}</span></dd></div>
          </dl>
        </div>
      </div>
    </section>
  </div>
</template>

<style scoped>
.page-hero { background: linear-gradient(145deg, #0d1b4b 0%, #1a237e 60%, #283593 100%); margin-top: calc(-1 * var(--pub-nav-h, 114px)); padding: calc(var(--pub-nav-h, 114px) + 2.5rem) 0 3rem; position: relative; overflow: hidden; }
.page-hero-inner { position: relative; z-index: 1; }
.page-breadcrumb { display: flex; gap: .5rem; color: rgba(255,255,255,.7); font-size: .85rem; margin-bottom: .75rem; }
.page-breadcrumb a { color: rgba(255,255,255,.85); text-decoration: none; }
.page-hero-title { color: #fff; font-size: 2rem; font-weight: 800; margin: 0 0 .5rem; }
.page-hero-desc { color: rgba(255,255,255,.8); margin: 0; }

.vc-section { padding: 3rem 0 4rem; background: #f5f7ff; min-height: 50vh; }
.vc-wrap { max-width: 720px; margin: 0 auto; }
.vc-form { background: #fff; border-radius: 16px; padding: 1.5rem; box-shadow: 0 8px 30px rgba(0,0,0,.06); }
.vc-label { display: block; font-weight: 700; color: #1a1a3e; margin-bottom: .6rem; }
.vc-input-row { display: flex; gap: .75rem; flex-wrap: wrap; }
.vc-input { flex: 1 1 240px; border: 1.5px solid #c5cae9; border-radius: 10px; padding: .75rem 1rem; font-size: 1.1rem; letter-spacing: .08em; text-transform: uppercase; font-family: monospace; outline: none; }
.vc-input:focus { border-color: #1a237e; }
.vc-btn { display: inline-flex; align-items: center; gap: .4rem; background: #1a237e; color: #fff; border: 0; border-radius: 10px; padding: .75rem 1.5rem; font-weight: 700; cursor: pointer; font-family: inherit; }
.vc-btn:disabled { opacity: .55; cursor: not-allowed; }
.vc-error { color: #c62828; margin-top: 1rem; text-align: center; }

.vc-card { margin-top: 1.5rem; background: #fff; border-radius: 16px; overflow: hidden; box-shadow: 0 8px 30px rgba(0,0,0,.08); border-top: 6px solid var(--vc); }
.vc-card.is-ok { --vc: #2e7d32; }
.vc-card.is-warn { --vc: #ef6c00; }
.vc-card.is-bad { --vc: #c62828; }
.vc-card-head { background: linear-gradient(145deg, #0d1b4b, #283593); padding: 1rem 1.5rem; display: flex; align-items: center; justify-content: space-between; gap: 1rem; flex-wrap: wrap; }
.vc-logo { height: 52px; width: auto; }
.vc-official { display: inline-flex; align-items: center; gap: .4rem; color: #ffd54f; font-size: .85rem; font-weight: 700; }
.vc-status { text-align: center; padding: 1.75rem 1.5rem 1rem; color: var(--vc); }
.vc-status h2 { margin: .5rem 0 .25rem; font-size: 1.5rem; font-weight: 800; }
.vc-status p { margin: 0; color: #616161; font-size: .95rem; }
.vc-details { margin: 0; padding: .5rem 1.5rem 1.5rem; display: grid; gap: 0; }
.vc-details > div { display: flex; justify-content: space-between; gap: 1rem; padding: .75rem 0; border-bottom: 1px solid #eceff1; }
.vc-details dt { color: #757575; font-weight: 600; font-size: .9rem; }
.vc-details dd { margin: 0; color: #1a1a3e; font-weight: 700; text-align: left; }
.vc-copy { margin-inline-start: .6rem; border: 1px solid #c5cae9; background: #fff; color: #1a237e; border-radius: 6px; padding: .1rem .5rem; font-size: .8rem; cursor: pointer; font-family: inherit; }
.vc-badge { display: inline-flex; align-items: center; gap: .3rem; color: var(--vc); }
@media (max-width: 600px) { .vc-details > div { flex-direction: column; gap: .15rem; } .vc-details dd { text-align: right; } }
</style>

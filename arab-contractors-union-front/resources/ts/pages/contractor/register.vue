<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import api from '@/plugins/axios'
import {
  Eye, EyeOff, Check, PartyPopper, RefreshCw,
  ShieldCheck, Award, Handshake, ArrowLeft,
} from 'lucide-vue-next'

definePage({
  meta: { layout: 'pure', public: true, unauthenticatedOnly: false },
})

const router = useRouter()

// التدفق: verify(phone + موافقة الشروط) → verify-otp → set-password → success
// حساب مفعّل مسبقاً → خطوة login
type Step = 'verify' | 'set-password' | 'verify-otp' | 'login' | 'success'
const step = ref<Step>('verify')

const verifyForm   = ref({ phone: '', terms_accepted: false })
const passwordForm = ref({ password: '', password_confirmation: '' })
const otpForm      = ref({ otp: '' })
const otpPreview   = ref('')
const loginForm    = ref({ password: '' })

const resendTimer = ref(0)
let timerInterval: any = null

function startResendTimer(seconds: number) {
  resendTimer.value = seconds
  clearInterval(timerInterval)
  timerInterval = setInterval(() => {
    if (resendTimer.value > 0) resendTimer.value--
    else clearInterval(timerInterval)
  }, 1000)
}

const showPwd      = ref(false)
const showPwdC     = ref(false)
const showLoginPwd = ref(false)

const showTermsModal = ref(false)

const foundContractor = ref<{ name: string; trade: string; classification: string; membership_number?: string } | null>(null)
const isLoading = ref(false)
const errorMsg  = ref('')

const degLabel: Record<string, string> = {
  A1: 'الدرجة الأولى أ', A2: 'الدرجة الأولى ب',
  B: 'الثانية', C: 'الثالثة', D: 'الرابعة', E: 'الخامسة',
}

// رقم الخطوة الظاهر في المؤشّر العلوي (تُخفى خطوة "login" الطارئة)
const stepIndex = computed(() => {
  if (step.value === 'verify-otp') return 2
  if (step.value === 'set-password' || step.value === 'success') return 3
  return 1
})

onMounted(() => {
  if (localStorage.getItem('contractor_token'))
    router.replace('/contractor/dashboard')
})

async function persistLogin(token: string) {
  localStorage.setItem('contractor_token', token)
  window.dispatchEvent(new CustomEvent('contractor-logged-in'))
}

async function handleVerify() {
  errorMsg.value = ''
  if (!verifyForm.value.phone)
    return (errorMsg.value = 'يرجى إدخال رقم الجوال.')
  if (!verifyForm.value.terms_accepted)
    return (errorMsg.value = 'يجب الموافقة على الشروط والأحكام وسياسة الخصوصية.')
    
  isLoading.value = true
  try {
    const payload = { phone: verifyForm.value.phone, terms_accepted: 1 }
    const r = await api.post('/api/v1/contractor/auth/verify-identity', payload)
    foundContractor.value = r.data.items
    
    if (r.data.items?.otp_required) {
      otpPreview.value = r.data.items.otp_preview ?? ''
      otpForm.value = { otp: '' }
      if (r.data.items.expires_in) startResendTimer(r.data.items.expires_in)
      step.value = 'verify-otp'
    } else {
      step.value = 'verify-otp' // fallback
    }
  } catch (e: any) {
    if (e?.response?.data?.error === 'already_registered') {
      foundContractor.value = e.response.data.items ?? null
      step.value = 'login'
    } else if (e?.response?.data?.error === 'otp_cooldown') {
      errorMsg.value = e.response.data.message
      if (e.response.data.items?.expires_in) startResendTimer(e.response.data.items.expires_in)
    } else {
      errorMsg.value = e?.response?.data?.message || 'لم يتم العثور على المقاول.'
    }
  } finally { isLoading.value = false }
}

async function handleLogin() {
  errorMsg.value = ''
  if (!loginForm.value.password) return (errorMsg.value = 'أدخل كلمة المرور.')
  isLoading.value = true
  try {
    const r = await api.post('/api/v1/contractor/auth/login', {
      membership_number: foundContractor.value?.membership_number,
      password: loginForm.value.password,
    })
    await persistLogin(r.data.items?.token)
    router.push('/contractor/dashboard')
  } catch (e: any) {
    errorMsg.value = e?.response?.data?.message || 'كلمة المرور غير صحيحة.'
  } finally { isLoading.value = false }
}

async function handleSetPassword() {
  errorMsg.value = ''
  if (passwordForm.value.password.length < 8)
    return (errorMsg.value = 'كلمة المرور 8 أحرف على الأقل.')
  if (passwordForm.value.password !== passwordForm.value.password_confirmation)
    return (errorMsg.value = 'كلمتا المرور غير متطابقتين.')
  isLoading.value = true
  try {
    const r = await api.post('/api/v1/contractor/auth/set-password', { phone: verifyForm.value.phone, ...passwordForm.value })
    if (r.data.items?.token) {
      await persistLogin(r.data.items.token)
      // نقل مباشر إلى لوحة المقاول + إشعار popup "تم التفعيل بنجاح" تقرأه اللوحة عند الفتح
      localStorage.setItem('contractor_activated', '1')
      router.push('/contractor/dashboard')
    }
  } catch (e: any) {
    errorMsg.value = e?.response?.data?.message || 'حدث خطأ.'
  } finally { isLoading.value = false }
}

async function handleVerifyOtp() {
  errorMsg.value = ''
  if (!otpForm.value.otp) return (errorMsg.value = 'أدخل رمز التحقق.')
  if (otpForm.value.otp.length !== 6) return (errorMsg.value = 'الرمز يجب أن يكون 6 أرقام بالضبط.')
  isLoading.value = true
  try {
    await api.post('/api/v1/contractor/auth/verify-otp', {
      phone: verifyForm.value.phone,
      otp: otpForm.value.otp,
    })
    step.value = 'set-password'
  } catch (e: any) {
    errorMsg.value = e?.response?.data?.message || 'رمز التحقق غير صحيح أو منتهي.'
  } finally { isLoading.value = false }
}

async function handleResendOtp() {
  if (resendTimer.value > 0) return
  errorMsg.value = ''
  isLoading.value = true
  try {
    const r = await api.post('/api/v1/contractor/auth/resend-otp', {
      phone: verifyForm.value.phone,
    })
    otpPreview.value = r.data.items?.otp_preview ?? otpPreview.value
    if (r.data.items?.expires_in) startResendTimer(r.data.items.expires_in)
  } catch (e: any) {
    if (e?.response?.data?.error === 'otp_cooldown') {
      errorMsg.value = e.response.data.message
      if (e.response.data.items?.expires_in) startResendTimer(e.response.data.items.expires_in)
    } else {
      errorMsg.value = e?.response?.data?.message || 'تعذر إعادة إرسال الرمز.'
    }
  } finally { isLoading.value = false }
}
</script>

<template>
  <div dir="rtl" class="rg-page">
    <div class="rg-shell">

      <!-- لوحة العلامة التجارية -->
      <aside class="rg-brand">
        <div class="rg-brand-inner">
          <div class="rg-logo-wrap"><img src="/logo.png" alt="اتحاد المقاولين الفلسطينيين" class="rg-logo" /></div>
          <h1 class="rg-brand-title">اتحاد المقاولين الفلسطينيين</h1>
          <p class="rg-brand-sub">تفعيل عضوية المقاول</p>

          <ul class="rg-perks">
            <li><span class="rg-perk-ico"><ShieldCheck :size="18" /></span> بوابة موحّدة لمتابعة عضويتك وذممك المالية</li>
            <li><span class="rg-perk-ico"><Award :size="18" /></span> إصدار شهادات العضوية إلكترونياً</li>
            <li><span class="rg-perk-ico"><Handshake :size="18" /></span> اطّلاع مباشر على العطاءات والأخبار</li>
          </ul>

          <RouterLink to="/landing" class="rg-back">
            <ArrowLeft :size="15" /> العودة للصفحة الرئيسية
          </RouterLink>
        </div>
      </aside>

      <!-- بطاقة النموذج -->
      <main class="rg-card">

        <!-- مؤشر الخطوات -->
        <div v-if="step !== 'login'" class="rg-steps">
          <div class="rg-step" :class="{ active: stepIndex === 1, done: stepIndex > 1 }">
            <span v-if="stepIndex > 1"><Check :size="14" /></span><span v-else>١</span>
          </div>
          <div class="rg-step-line" :class="{ filled: stepIndex > 1 }" />
          <div class="rg-step" :class="{ active: stepIndex === 2, done: stepIndex > 2 }">
            <span v-if="stepIndex > 2"><Check :size="14" /></span><span v-else>٢</span>
          </div>
          <div class="rg-step-line" :class="{ filled: stepIndex > 2 }" />
          <div class="rg-step" :class="{ active: stepIndex === 3 }">٣</div>
        </div>

        <!-- verify -->
        <div v-if="step === 'verify'" class="rg-body">
          <h2 class="rg-title">تفعيل عضوية المقاول</h2>
          <p class="rg-hint">أدخل رقم جوالك المسجّل للتحقق من هويتك.</p>
          <div v-if="errorMsg" class="rg-err">{{ errorMsg }}</div>
          <form class="rg-form" @submit.prevent="handleVerify">
            <div class="rg-fg">
              <label>رقم الجوال *</label>
              <input v-model="verifyForm.phone" type="text" inputmode="tel" dir="ltr" placeholder="مثال: 0590000000" class="rg-fi" style="text-align: right;" />
            </div>
            <div class="rg-fg rg-terms">
              <label class="rg-checkbox-label">
                <input v-model="verifyForm.terms_accepted" type="checkbox" />
                <span>موافقة على <a href="#" class="rg-terms-link" @click.prevent="showTermsModal = true">الشروط والأحكام وسياسة الخصوصية</a></span>
              </label>
            </div>
            <div class="rg-actions">
              <button type="submit" class="rg-btn-p rg-btn-block" :disabled="isLoading">
                <span v-if="isLoading" class="rg-spin" /><span v-else>التحقق من الهوية</span>
              </button>
            </div>
          </form>

          <div class="rg-test-hint">
            بيانات تجريبية للاختبار: رقم الجوال <strong>0590000000</strong> — كلمة المرور <strong>Test@12345</strong>
          </div>

          <div class="rg-divider"><span>أو</span></div>
          <p class="rg-switch">
            لديك عضوية مفعّلة بالفعل؟
            <RouterLink to="/contractor/login">تسجيل الدخول</RouterLink>
          </p>
        </div>

        <!-- login (بعد اكتشاف أن الحساب مفعّل مسبقاً) -->
        <div v-else-if="step === 'login'" class="rg-body">
          <h2 class="rg-title">تسجيل الدخول</h2>
          <div class="rg-found-banner">
            <div class="rg-found-av">{{ foundContractor?.name?.charAt(0) ?? '?' }}</div>
            <div>
              <strong>{{ foundContractor?.name }}</strong>
              <p>حسابك مفعّل — أدخل كلمة المرور للدخول</p>
            </div>
          </div>
          <div v-if="errorMsg" class="rg-err">{{ errorMsg }}</div>
          <form class="rg-form" @submit.prevent="handleLogin">
            <div class="rg-fg">
              <label>كلمة المرور *</label>
              <div class="rg-pw-wrap">
                <input v-model="loginForm.password" :type="showLoginPwd ? 'text' : 'password'" placeholder="••••••••" class="rg-fi" />
                <button type="button" class="rg-pw-eye" @click="showLoginPwd = !showLoginPwd">
                  <EyeOff v-if="showLoginPwd" :size="16" /><Eye v-else :size="16" />
                </button>
              </div>
            </div>
            <div class="rg-actions">
              <button type="submit" class="rg-btn-p rg-btn-block" :disabled="isLoading">
                <span v-if="isLoading" class="rg-spin" /><span v-else>تسجيل الدخول</span>
              </button>
            </div>
          </form>
        </div>

        <!-- set-password -->
        <div v-else-if="step === 'set-password'" class="rg-body">
          <h2 class="rg-title">إنشاء كلمة المرور</h2>
          <div v-if="foundContractor" class="rg-found-banner rg-found-success">
            <div class="rg-found-av">{{ foundContractor.name.charAt(0) }}</div>
            <div>
              <strong>{{ foundContractor.name }}</strong>
              <p>{{ foundContractor.trade }} — {{ degLabel[foundContractor.classification] ?? foundContractor.classification }}</p>
            </div>
          </div>
          <p class="rg-hint">أنشئ كلمة مرور قوية — ٨ أحرف على الأقل.</p>
          <div v-if="errorMsg" class="rg-err">{{ errorMsg }}</div>
          <form class="rg-form" @submit.prevent="handleSetPassword">
            <div class="rg-fg">
              <label>كلمة المرور *</label>
              <div class="rg-pw-wrap">
                <input v-model="passwordForm.password" :type="showPwd ? 'text' : 'password'" placeholder="••••••••" class="rg-fi" />
                <button type="button" class="rg-pw-eye" @click="showPwd = !showPwd">
                  <EyeOff v-if="showPwd" :size="16" /><Eye v-else :size="16" />
                </button>
              </div>
            </div>
            <div class="rg-fg">
              <label>تأكيد كلمة المرور *</label>
              <div class="rg-pw-wrap">
                <input v-model="passwordForm.password_confirmation" :type="showPwdC ? 'text' : 'password'" placeholder="••••••••" class="rg-fi" />
                <button type="button" class="rg-pw-eye" @click="showPwdC = !showPwdC">
                  <EyeOff v-if="showPwdC" :size="16" /><Eye v-else :size="16" />
                </button>
              </div>
            </div>
            <div class="rg-actions">
              <button type="submit" class="rg-btn-p" :disabled="isLoading">
                <span v-if="isLoading" class="rg-spin" /><span v-else>تفعيل الحساب</span>
              </button>
            </div>
          </form>
        </div>

        <!-- verify-otp -->
        <div v-else-if="step === 'verify-otp'" class="rg-body">
          <h2 class="rg-title">رمز التحقق</h2>
          <p class="rg-hint">
            أرسلنا رمز تحقق إلى جوالك المسجّل. أدخله لتفعيل الحساب.
            <span v-if="otpPreview"> (رمز الاختبار: <strong>{{ otpPreview }}</strong>)</span>
          </p>
          <div v-if="errorMsg" class="rg-err">{{ errorMsg }}</div>
          <form class="rg-form" @submit.prevent="handleVerifyOtp">
            <div class="rg-fg">
              <label>رمز التحقق *</label>
              <input v-model="otpForm.otp" type="text" inputmode="numeric" placeholder="000000" maxlength="6" class="rg-fi rg-fi-otp" />
            </div>
            <button class="rg-resend" type="button" :disabled="isLoading || resendTimer > 0" @click="handleResendOtp">
              <RefreshCw :size="13" :class="{ 'rg-spin': isLoading }" /> 
              <span v-if="resendTimer > 0">إعادة الإرسال بعد {{ resendTimer }} ثانية</span>
              <span v-else>إعادة إرسال الرمز</span>
            </button>
            <div class="rg-actions">
              <button type="submit" class="rg-btn-p rg-btn-block" :disabled="isLoading">
                <span v-if="isLoading" class="rg-spin" /><span v-else>تفعيل الحساب</span>
              </button>
            </div>
          </form>
        </div>

        <!-- success -->
        <div v-else-if="step === 'success'" class="rg-body rg-success">
          <div class="rg-success-icon"><PartyPopper :size="52" /></div>
          <h2 class="rg-title">تم تفعيل حسابك بنجاح!</h2>
          <p class="rg-hint">مرحباً بك في اتحاد المقاولين الفلسطينيين.</p>
          <button class="rg-btn-p rg-btn-block" @click="router.push('/contractor/dashboard')">الانتقال إلى لوحتي</button>
        </div>

      </main>
      
      <!-- Terms Modal -->
      <div v-if="showTermsModal" class="rg-modal-overlay" @click.self="showTermsModal = false">
        <div class="rg-modal">
          <div class="rg-modal-header">
            <h3 class="rg-modal-title">سياسة الخصوصية والشروط والأحكام</h3>
            <button class="rg-modal-close" @click="showTermsModal = false">&times;</button>
          </div>
          <div class="rg-modal-body">
            <p>نحن في اتحاد المقاولين الفلسطينيين نحترم خصوصيتك ونلتزم بحماية معلوماتك الشخصية. تهدف هذه السياسة إلى توضيح كيفية جمع البيانات واستخدامها وحمايتها عند استخدامك لخدماتنا.</p>
            <p>باستخدامك لهذه البوابة، فإنك توافق على الشروط والأحكام المعتمدة لدى الاتحاد والالتزام باللوائح المنظمة لعمل المقاولين.</p>
            <p>يتم التعامل مع جميع بياناتك وسجلاتك المالية بسرية تامة، وتُستخدم فقط لأغراض تقديم الخدمات المتاحة لك من خلال البوابة.</p>
          </div>
          <div class="rg-modal-footer">
            <button class="rg-btn-p rg-btn-block" @click="showTermsModal = false">فهمت ذلك</button>
          </div>
        </div>
      </div>

    </div>
  </div>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800;900&family=Tajawal:wght@400;500;700;800&display=swap');

:global(:root) {
  --navy:        #0f1f5c;
  --navy-mid:    #17307e;
  --navy-bright: #2b4bb0;
  --navy-light:  #e8eaf6;
  --navy-soft:   #f5f7ff;
  --gold:        #d9a441;
  --gold-dark:   #b3781f;
  --gold-light:  #fdf3e1;
  --green:       #2e7d32;
  --green-light: #e8f5e9;
  --red:         #c62828;
  --red-light:   #fce4ec;
  --text-h:      #0d1b3e;
  --text-b:      #374151;
  --text-m:      #6b7280;
  --border:      #e5e7eb;
}

* { box-sizing: border-box; }

.rg-page {
  min-height: 100vh;
  font-family: 'Tajawal', 'Cairo', sans-serif;
  background: var(--navy-soft);
  display: flex; align-items: center; justify-content: center;
  padding: 2rem 1.5rem;
}

.rg-shell {
  width: 100%; max-width: 1040px;
  display: grid; grid-template-columns: 2fr 3fr;
  border-radius: 24px; overflow: hidden;
  box-shadow: 0 30px 90px rgba(15,31,92,.2);
  background: #fff;
  min-height: 620px;
}

/* ─── Brand panel (أبيض + شعار بألوانه الأصلية) ───────────── */
.rg-brand {
  background: #fff;
  color: var(--text-h); padding: 3rem 2.25rem;
  display: flex; flex-direction: column; justify-content: center; position: relative;
  overflow: hidden; border-inline-end: 1px solid var(--border);
}
.rg-brand-inner { position: relative; z-index: 1; }
.rg-logo-wrap { display: flex; justify-content: center; margin-bottom: 1.75rem; }
.rg-logo { height: 108px; width: auto; }
.rg-brand-title { font-size: 1.35rem; font-weight: 900; line-height: 1.5; color: var(--text-h); font-family: 'Cairo', sans-serif; }
.rg-brand-sub { font-size: .9rem; color: var(--text-m); margin-top: .4rem; margin-bottom: 2rem; }

.rg-perks { list-style: none; padding: 0; margin: 0 0 2.5rem; display: flex; flex-direction: column; gap: 1.1rem; }
.rg-perks li { display: flex; align-items: center; gap: .85rem; font-size: .84rem; line-height: 1.6; color: var(--text-b); }
.rg-perk-ico {
  width: 38px; height: 38px; border-radius: 50%; flex-shrink: 0;
  background: var(--navy-light); color: var(--navy-mid);
  display: flex; align-items: center; justify-content: center;
  transition: background .25s, color .25s, transform .25s;
}
.rg-perks li:hover .rg-perk-ico { background: var(--navy-mid); color: #fff; transform: scale(1.08) rotate(-6deg); }

.rg-back { display: inline-flex; align-items: center; gap: .4rem; color: var(--text-m); font-size: .82rem; text-decoration: none; font-weight: 600; transition: color .2s; }
.rg-back:hover { color: var(--navy); }

/* ─── Form card (غامق) ────────────────────────────────────── */
.rg-card {
  background: linear-gradient(160deg, var(--navy) 0%, var(--navy-mid) 100%);
  padding: 3rem 2.75rem; display: flex; flex-direction: column; justify-content: center; position: relative;
  overflow: hidden;
}
.rg-card::after {
  content: ''; position: absolute; inset: 0;
  background: radial-gradient(circle at 85% 110%, rgba(217,164,65,.16), transparent 55%);
}
.rg-body { width: 100%; position: relative; z-index: 1; }

.rg-steps { display: flex; align-items: center; justify-content: center; margin-bottom: 2rem; }
.rg-step { width: 32px; height: 32px; border-radius: 50%; border: 2px solid rgba(255,255,255,.25); display: flex; align-items: center; justify-content: center; font-size: .82rem; font-weight: 800; color: rgba(255,255,255,.6); flex-shrink: 0; transition: all .2s; }
.rg-step.active { border-color: var(--gold); color: var(--gold); background: rgba(217,164,65,.15); }
.rg-step.done { border-color: var(--green); background: var(--green); color: #fff; }
.rg-step-line { flex: 1; max-width: 90px; height: 2px; background: rgba(255,255,255,.2); margin: 0 .5rem; }
.rg-step-line.filled { background: var(--gold); }

.rg-title { font-size: 1.3rem; font-weight: 900; color: #fff; font-family: 'Cairo', sans-serif; margin-bottom: .5rem; }
.rg-hint { font-size: .875rem; color: rgba(255,255,255,.65); line-height: 1.75; margin-bottom: 1.5rem; }

.rg-err { background: rgba(198,40,40,.18); color: #ffcdd2; border: 1px solid rgba(239,154,154,.4); border-radius: 10px; padding: .8rem 1rem; font-size: .85rem; margin-bottom: 1.25rem; }

.rg-form { display: flex; flex-direction: column; }
.rg-fg { margin-bottom: 1.1rem; }
.rg-fg label { display: block; font-size: .82rem; font-weight: 700; color: rgba(255,255,255,.85); margin-bottom: .4rem; }
.rg-fi {
  width: 100%; font-family: inherit; font-size: .92rem; color: #fff;
  background: rgba(255,255,255,.07); padding: .8rem 1rem; border: 1.5px solid rgba(255,255,255,.2); border-radius: 10px;
  transition: border-color .2s, background .2s;
}
.rg-fi::placeholder { color: rgba(255,255,255,.4); }
.rg-fi:focus { outline: none; border-color: var(--gold); background: rgba(255,255,255,.1); }
.rg-fi-otp { text-align: center; letter-spacing: .4em; font-size: 1.1rem; font-weight: 700; }

.rg-pw-wrap { position: relative; display: flex; align-items: center; }
.rg-pw-wrap .rg-fi { padding-inline-end: 2.75rem; }
.rg-pw-eye { position: absolute; inset-inline-end: .8rem; background: none; border: none; cursor: pointer; color: rgba(255,255,255,.6); display: flex; align-items: center; transition: color .2s; }
.rg-pw-eye:hover { color: #fff; }

.rg-terms { margin-bottom: 1.5rem; margin-top: .5rem; }
.rg-checkbox-label { display: flex; align-items: center; gap: .6rem; font-size: .85rem; font-weight: 600; color: rgba(255,255,255,.85); cursor: pointer; }
.rg-checkbox-label input { width: 16px; height: 16px; accent-color: var(--gold); cursor: pointer; }
.rg-terms-link { color: var(--gold); text-decoration: underline; text-underline-offset: 3px; }
.rg-terms-link:hover { color: #fff; }

.rg-actions { display: flex; gap: .75rem; justify-content: flex-end; margin-top: .5rem; }
.rg-btn-p {
  background: linear-gradient(135deg, var(--gold), var(--gold-dark)); color: #fff; border: none;
  border-radius: 10px; padding: .75rem 1.6rem; font-size: .9rem; font-weight: 700;
  cursor: pointer; font-family: inherit; display: flex; align-items: center; justify-content: center; gap: .5rem;
  transition: all .2s;
}
.rg-btn-p:hover:not(:disabled) { transform: translateY(-1px); box-shadow: 0 6px 18px rgba(0,0,0,.25); }
.rg-btn-p:disabled { opacity: .6; cursor: not-allowed; }
.rg-btn-block { width: 100%; }
.rg-btn-s {
  background: transparent; color: rgba(255,255,255,.85); border: 1.5px solid rgba(255,255,255,.3); border-radius: 10px;
  padding: .75rem 1.4rem; font-size: .9rem; font-weight: 600; cursor: pointer; font-family: inherit; transition: all .2s;
}
.rg-btn-s:hover { border-color: var(--gold); color: var(--gold); }

.rg-spin { width: 16px; height: 16px; border: 2px solid rgba(255,255,255,.35); border-top-color: #fff; border-radius: 50%; animation: rg-sp .7s linear infinite; }
@keyframes rg-sp { to { transform: rotate(360deg); } }

.rg-test-hint { background: rgba(217,164,65,.14); border: 1px dashed rgba(217,164,65,.5); color: #f5dfa0; border-radius: 10px; padding: .7rem .9rem; font-size: .78rem; line-height: 1.7; margin-top: 1.1rem; }

.rg-found-banner { display: flex; align-items: center; gap: 1rem; background: rgba(255,255,255,.08); border: 1px solid rgba(255,255,255,.18); border-radius: 14px; padding: 1rem; margin-bottom: 1.5rem; }
.rg-found-banner.rg-found-success { background: rgba(46,125,50,.2); border-color: rgba(165,214,167,.4); }
.rg-found-av { width: 46px; height: 46px; border-radius: 50%; background: linear-gradient(135deg, var(--gold), var(--gold-dark)); color: var(--navy); display: flex; align-items: center; justify-content: center; font-size: 1.2rem; font-weight: 800; flex-shrink: 0; font-family: 'Cairo', sans-serif; }
.rg-found-banner strong { display: block; font-weight: 800; font-size: .92rem; color: #fff; }
.rg-found-banner p { margin: .2rem 0 0; font-size: .8rem; color: rgba(255,255,255,.7); }

.rg-resend { display: flex; align-items: center; gap: .4rem; background: none; border: none; color: var(--gold); font-size: .82rem; font-weight: 700; cursor: pointer; font-family: inherit; margin-bottom: 1.25rem; }
.rg-resend:hover { text-decoration: underline; }
.rg-resend:disabled { opacity: .6; cursor: not-allowed; }

.rg-success { text-align: center; padding: 1rem 0; }
.rg-success-icon { color: var(--gold); margin-bottom: 1rem; display: flex; justify-content: center; }
.rg-success .rg-title { margin-bottom: .5rem; }
.rg-success .rg-hint { margin-bottom: 2rem; }

.rg-divider { display: flex; align-items: center; text-align: center; margin: 1.5rem 0 1.1rem; color: rgba(255,255,255,.5); font-size: .78rem; }
.rg-divider::before, .rg-divider::after { content: ''; flex: 1; height: 1px; background: rgba(255,255,255,.18); }
.rg-divider span { padding: 0 .9rem; }

.rg-switch { text-align: center; font-size: .85rem; color: rgba(255,255,255,.65); }
.rg-switch a { color: var(--gold); font-weight: 700; text-decoration: none; margin-inline-start: .3rem; }
.rg-switch a:hover { text-decoration: underline; }

/* ─── Modal Styles ────────────────────────────────────────── */
.rg-modal-overlay {
  position: fixed; inset: 0; background: rgba(15,31,92,.8); backdrop-filter: blur(4px);
  display: flex; align-items: center; justify-content: center; z-index: 9999;
  padding: 1rem; animation: rgFadeIn .25s ease;
}
@keyframes rgFadeIn { from { opacity: 0; } to { opacity: 1; } }

.rg-modal {
  background: #fff; border-radius: 18px; width: 100%; max-width: 450px;
  overflow: hidden; box-shadow: 0 20px 60px rgba(0,0,0,.15);
  animation: rgSlideUp .3s cubic-bezier(0.16, 1, 0.3, 1);
}
@keyframes rgSlideUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }

.rg-modal-header {
  display: flex; align-items: center; justify-content: space-between;
  padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--border);
}
.rg-modal-title { font-size: 1.15rem; font-weight: 800; color: var(--text-h); margin: 0; font-family: 'Cairo', sans-serif; }
.rg-modal-close {
  background: var(--navy-light); border: none; width: 32px; height: 32px; border-radius: 50%;
  font-size: 1.2rem; color: var(--navy); display: flex; align-items: center; justify-content: center;
  cursor: pointer; transition: background .2s;
}
.rg-modal-close:hover { background: var(--border); }

.rg-modal-body { padding: 1.5rem; color: var(--text-b); font-size: .9rem; line-height: 1.8; max-height: 50vh; overflow-y: auto; }
.rg-modal-body p { margin-bottom: 1rem; }
.rg-modal-body p:last-child { margin-bottom: 0; }

.rg-modal-footer { padding: 1rem 1.5rem 1.5rem; }

@media (max-width: 860px) {
  .rg-shell { grid-template-columns: 1fr; min-height: 0; }
  .rg-brand { padding: 2.25rem 1.75rem; border-inline-end: none; border-bottom: 1px solid var(--border); }
  .rg-perks { margin-bottom: 1.5rem; }
  .rg-back { display: none; }
  .rg-card { padding: 2.25rem 1.75rem; }
}
</style>

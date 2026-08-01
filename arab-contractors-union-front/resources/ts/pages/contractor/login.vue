<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import axios from 'axios'
import { User, Lock, Eye, EyeOff, AlertCircle, ShieldCheck, Award, Handshake, ArrowLeft } from 'lucide-vue-next'

definePage({
  meta: { layout: 'pure', public: true, unauthenticatedOnly: false },
})

const router = useRouter()

const form = ref({
  membership_number: '',
  password: '',
})

const showPassword = ref(false)
const isLoading = ref(false)
const errorMessage = ref('')

const BASE = import.meta.env.VITE_API_BASE_URL ?? ''

async function submitLogin() {
  if (!form.value.membership_number || !form.value.password) {
    errorMessage.value = 'الرجاء إدخال رقم العضوية وكلمة المرور'
    return
  }

  isLoading.value = true
  errorMessage.value = ''

  try {
    const r = await axios.post(`${BASE}/api/v1/contractor/auth/login`, {
      membership_number: form.value.membership_number.trim(),
      password: form.value.password,
    }, { headers: { Accept: 'application/json' } })

    const token = r.data.items?.token
    if (!token) {
      errorMessage.value = 'تعذّر الحصول على رمز الدخول، حاول مرة أخرى'
      return
    }

    localStorage.setItem('contractor_token', token)
    window.dispatchEvent(new CustomEvent('contractor-logged-in'))
    router.replace('/contractor/dashboard')
  } catch (e: any) {
    errorMessage.value = e?.response?.data?.message ?? 'حدث خطأ أثناء تسجيل الدخول'
  } finally {
    isLoading.value = false
  }
}

onMounted(() => {
  // إن كان المقاول مسجّلاً بالفعل، ادخله مباشرةً على لوحته
  if (localStorage.getItem('contractor_token')) {
    router.replace('/contractor/dashboard')
  }
})
</script>

<template>
  <div dir="rtl" class="rg-page">
    <div class="rg-shell">

      <!-- لوحة العلامة التجارية -->
      <aside class="rg-brand">
        <div class="rg-brand-inner">
          <div class="rg-logo-wrap"><img src="/logo.png" alt="اتحاد المقاولين الفلسطينيين" class="rg-logo" /></div>
          <h1 class="rg-brand-title">اتحاد المقاولين الفلسطينيين</h1>
          <p class="rg-brand-sub">تسجيل دخول المقاول</p>

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

      <!-- بطاقة تسجيل الدخول -->
      <main class="rg-card">
        <div class="rg-body">
          <h2 class="rg-title">تسجيل الدخول إلى حسابك</h2>
          <p class="rg-hint">أدخل رقم عضويتك وكلمة المرور للوصول إلى لوحتك.</p>

          <div v-if="errorMessage" class="rg-err">
            <AlertCircle :size="16" /> {{ errorMessage }}
          </div>

          <form class="rg-form" @submit.prevent="submitLogin">
            <div class="rg-fg">
              <label>رقم العضوية *</label>
              <div class="rg-input-wrap">
                <User :size="17" class="rg-input-ico" />
                <input
                  v-model="form.membership_number"
                  type="text"
                  dir="ltr"
                  placeholder="928_g"
                  class="rg-fi rg-fi-ico"
                  autocomplete="username"
                />
              </div>
            </div>

            <div class="rg-fg">
              <label>كلمة المرور *</label>
              <div class="rg-input-wrap">
                <Lock :size="17" class="rg-input-ico" />
                <input
                  v-model="form.password"
                  :type="showPassword ? 'text' : 'password'"
                  dir="ltr"
                  placeholder="••••••••"
                  class="rg-fi rg-fi-ico rg-fi-ico-both"
                  autocomplete="current-password"
                />
                <button type="button" class="rg-pw-eye" @click="showPassword = !showPassword" :aria-label="showPassword ? 'إخفاء' : 'إظهار'">
                  <EyeOff v-if="showPassword" :size="16" /><Eye v-else :size="16" />
                </button>
              </div>
            </div>

            <div class="rg-actions">
              <button type="submit" class="rg-btn-p rg-btn-block" :disabled="isLoading">
                <span v-if="isLoading" class="rg-spin" /><span v-else>تسجيل الدخول</span>
              </button>
            </div>
          </form>

          <div class="rg-divider"><span>أو</span></div>

          <p class="rg-switch">
            ليس لديك عضوية؟
            <RouterLink to="/contractor/register">فعّل عضويتك الآن</RouterLink>
          </p>
        </div>
      </main>
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
  min-height: 580px;
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

.rg-title { font-size: 1.3rem; font-weight: 900; color: #fff; font-family: 'Cairo', sans-serif; margin-bottom: .5rem; }
.rg-hint { font-size: .875rem; color: rgba(255,255,255,.65); line-height: 1.75; margin-bottom: 1.5rem; }

.rg-err { display: flex; align-items: center; gap: .5rem; background: rgba(198,40,40,.18); color: #ffcdd2; border: 1px solid rgba(239,154,154,.4); border-radius: 10px; padding: .8rem 1rem; font-size: .85rem; margin-bottom: 1.25rem; }

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

.rg-input-wrap { position: relative; display: flex; align-items: center; }
.rg-input-ico { position: absolute; right: .85rem; color: rgba(255,255,255,.55); pointer-events: none; }
.rg-fi-ico { padding-inline-end: 2.6rem; direction: ltr; text-align: right; }
.rg-fi-ico-both { padding-inline-start: 2.6rem; }
.rg-pw-eye { position: absolute; inset-inline-end: .8rem; background: none; border: none; cursor: pointer; color: rgba(255,255,255,.6); display: flex; align-items: center; transition: color .2s; }
.rg-pw-eye:hover { color: #fff; }

.rg-actions { margin-top: .5rem; }
.rg-btn-p {
  background: linear-gradient(135deg, var(--gold), var(--gold-dark)); color: #fff; border: none;
  border-radius: 10px; padding: .8rem 1.6rem; font-size: .92rem; font-weight: 700;
  cursor: pointer; font-family: inherit; display: flex; align-items: center; justify-content: center; gap: .5rem;
  transition: all .2s;
}
.rg-btn-p:hover:not(:disabled) { transform: translateY(-1px); box-shadow: 0 6px 18px rgba(0,0,0,.25); }
.rg-btn-p:disabled { opacity: .6; cursor: not-allowed; }
.rg-btn-block { width: 100%; }

.rg-spin { width: 16px; height: 16px; border: 2px solid rgba(255,255,255,.35); border-top-color: #fff; border-radius: 50%; animation: rg-sp .7s linear infinite; }
@keyframes rg-sp { to { transform: rotate(360deg); } }

.rg-divider { display: flex; align-items: center; text-align: center; margin: 1.75rem 0 1.25rem; color: rgba(255,255,255,.5); font-size: .78rem; }
.rg-divider::before, .rg-divider::after { content: ''; flex: 1; height: 1px; background: rgba(255,255,255,.18); }
.rg-divider span { padding: 0 .9rem; }

.rg-switch { text-align: center; font-size: .85rem; color: rgba(255,255,255,.65); }
.rg-switch a { color: var(--gold); font-weight: 700; text-decoration: none; margin-inline-start: .3rem; }
.rg-switch a:hover { text-decoration: underline; }

@media (max-width: 860px) {
  .rg-shell { grid-template-columns: 1fr; min-height: 0; }
  .rg-brand { padding: 2.25rem 1.75rem; border-inline-end: none; border-bottom: 1px solid var(--border); }
  .rg-perks { margin-bottom: 1.5rem; }
  .rg-back { display: none; }
  .rg-card { padding: 2.25rem 1.75rem; }
}
</style>

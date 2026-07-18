<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import axios from 'axios'
import { LogIn, User, Lock, Eye, EyeOff, AlertCircle } from 'lucide-vue-next'

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
  <div dir="rtl" class="cl-page">
    <div class="cl-card">
      <div class="cl-head">
        <img src="/logo.png" alt="الاتحاد" class="cl-logo" />
        <h1 class="cl-title">اتحاد المقاولين الفلسطينيين</h1>
        <p class="cl-sub">تسجيل دخول المقاول</p>
      </div>

      <div v-if="errorMessage" class="cl-alert">
        <AlertCircle :size="18" />
        <span>{{ errorMessage }}</span>
      </div>

      <form class="cl-form" @submit.prevent="submitLogin">
        <div class="cl-group">
          <label>رقم العضوية</label>
          <div class="cl-input-wrap">
            <User :size="18" class="cl-input-ico" />
            <input
              v-model="form.membership_number"
              type="text"
              inputmode="text"
              dir="ltr"
              placeholder="928_g"
              class="cl-input cl-ltr"
              autocomplete="username"
            />
          </div>
        </div>

        <div class="cl-group">
          <label>كلمة المرور</label>
          <div class="cl-input-wrap">
            <Lock :size="18" class="cl-input-ico" />
            <input
              v-model="form.password"
              :type="showPassword ? 'text' : 'password'"
              dir="ltr"
              placeholder="••••••••"
              class="cl-input cl-ltr"
              autocomplete="current-password"
            />
            <button type="button" class="cl-eye" @click="showPassword = !showPassword" :aria-label="showPassword ? 'إخفاء' : 'إظهار'">
              <EyeOff v-if="showPassword" :size="18" />
              <Eye v-else :size="18" />
            </button>
          </div>
        </div>

        <button type="submit" class="cl-submit" :disabled="isLoading">
          <LogIn v-if="!isLoading" :size="18" />
          <span v-else class="cl-spinner"></span>
          {{ isLoading ? 'جاري الدخول...' : 'تسجيل الدخول' }}
        </button>
      </form>

      <RouterLink to="/landing" class="cl-back">العودة إلى الصفحة الرئيسية</RouterLink>
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
  --red: #c62828;
  --red-light: #fce4ec;
  --text-h: #1a1a3e;
  --text-b: #424242;
  --text-m: #757575;
  --border: #e0e0e0;
}

* { box-sizing: border-box; margin: 0; padding: 0; }

.cl-page {
  font-family: 'Tajawal', sans-serif;
  direction: rtl;
  min-height: 100vh;
  background: linear-gradient(135deg, #f5f7ff 0%, #e8eaf6 100%);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 1.5rem;
}

.cl-card {
  width: 100%;
  max-width: 420px;
  background: #fff;
  border: 1.5px solid var(--border);
  border-radius: 18px;
  padding: 2.25rem 2rem;
  box-shadow: 0 10px 40px rgba(26, 35, 126, .1);
}

.cl-head { text-align: center; margin-bottom: 1.75rem; }
.cl-logo { height: 64px; margin-bottom: .85rem; }
.cl-title { font-size: 1.1rem; font-weight: 800; color: var(--text-h); }
.cl-sub { font-size: .9rem; color: var(--text-m); margin-top: .25rem; }

.cl-alert {
  display: flex; align-items: center; gap: .6rem;
  background: var(--red-light); color: var(--red);
  border: 1px solid #ef9a9a; border-radius: 10px;
  padding: .75rem 1rem; font-size: .85rem; font-weight: 600;
  margin-bottom: 1.25rem;
}

.cl-form { display: flex; flex-direction: column; gap: 1.15rem; }
.cl-group { display: flex; flex-direction: column; gap: .5rem; }
.cl-group label { font-size: .85rem; font-weight: 700; color: var(--text-h); }

.cl-input-wrap { position: relative; display: flex; align-items: center; }
.cl-input-ico { position: absolute; right: .85rem; color: var(--text-m); pointer-events: none; }
.cl-input {
  width: 100%;
  font-family: inherit; font-size: .95rem;
  padding: .8rem 2.5rem .8rem 1rem;
  border: 1.5px solid var(--border); border-radius: 10px;
  transition: border-color .2s;
}
.cl-input:focus { outline: none; border-color: var(--navy); }
.cl-ltr { direction: ltr; text-align: right; }
.cl-eye {
  position: absolute; left: .6rem;
  background: none; border: none; cursor: pointer;
  color: var(--text-m); display: flex; padding: .3rem;
}
.cl-eye:hover { color: var(--navy); }

.cl-submit {
  display: flex; align-items: center; justify-content: center; gap: .5rem;
  background: var(--navy); color: #fff; border: none;
  padding: .9rem; border-radius: 10px;
  font-family: inherit; font-size: .95rem; font-weight: 700;
  cursor: pointer; transition: background .2s; margin-top: .35rem;
}
.cl-submit:hover:not(:disabled) { background: var(--navy-mid); }
.cl-submit:disabled { opacity: .6; cursor: not-allowed; }

.cl-spinner {
  width: 18px; height: 18px;
  border: 2px solid rgba(255,255,255,.3); border-top-color: #fff;
  border-radius: 50%; animation: spin .8s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }

.cl-back {
  display: block; text-align: center; margin-top: 1.5rem;
  font-size: .85rem; color: var(--text-m); text-decoration: none;
}
.cl-back:hover { color: var(--navy); text-decoration: underline; }
</style>

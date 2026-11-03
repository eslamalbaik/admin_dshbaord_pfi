<script setup lang="ts">
import { ref } from 'vue'
import { useRoute } from 'vue-router'
import axios from 'axios'
import { CheckCircle2, XCircle } from 'lucide-vue-next'

// pcu.org.ps/verify — التحقق اليدوي من شهادة العضوية بالرقم التسلسلي المطبوع تحت QR الشهادة.
// المسار الثابت هذا بيتقدّم على صفحات [slug].vue الديناميكية بنفس المستوى.

definePage({
  meta: { layout: 'pure', public: true, unauthenticatedOnly: false },
})

interface VerifyResult {
  valid: boolean
  contractor_name?: string
  membership_number?: string
  type_label?: string
  serial?: string
  issued_at?: string
  membership_valid_until?: string | null
}

const BASE = import.meta.env.VITE_API_BASE_URL ?? ''

const route = useRoute()
const serial = ref((route.query.serial as string) ?? '')
const isLoading = ref(false)
const result = ref<VerifyResult | null>(null)
const errorMessage = ref('')

function fmtDate(d: string | null | undefined) {
  if (!d)
    return '—'

  return new Date(d).toLocaleDateString('ar-PS', { year: 'numeric', month: 'long', day: 'numeric' })
}

async function verify() {
  const value = serial.value.trim()
  if (!value)
    return

  isLoading.value = true
  errorMessage.value = ''
  result.value = null
  try {
    const r = await axios.get(`${BASE}/api/v1/certificates/verify-serial/${encodeURIComponent(value)}`)

    result.value = r.data.items ?? { valid: false }
  }
  catch (e: any) {
    if (e?.response?.status === 429)
      errorMessage.value = 'محاولات كثيرة، يرجى المحاولة بعد دقيقة.'
    else
      result.value = { valid: false }
  }
  finally {
    isLoading.value = false
  }
}

if (serial.value)
  verify()
</script>

<template>
  <div
    dir="rtl"
    class="v-page"
  >
    <div
      class="v-card"
      :class="result ? (result.valid ? 'valid' : 'invalid') : ''"
    >
      <h2>التحقق من شهادة العضوية</h2>
      <p class="v-sub">
        أدخل الرقم التسلسلي المطبوع على الشهادة تحت رمز QR
      </p>

      <form
        class="v-form"
        @submit.prevent="verify"
      >
        <input
          v-model="serial"
          dir="ltr"
          class="v-input"
          placeholder="MC-000123"
          autocomplete="off"
        >
        <button
          type="submit"
          class="v-btn"
          :disabled="isLoading || !serial.trim()"
        >
          {{ isLoading ? 'جاري التحقق...' : 'تحقّق' }}
        </button>
      </form>

      <p
        v-if="errorMessage"
        class="v-sub v-error"
      >
        {{ errorMessage }}
      </p>

      <template v-if="result">
        <CheckCircle2
          v-if="result.valid"
          :size="48"
          class="v-icon"
        />
        <XCircle
          v-else
          :size="48"
          class="v-icon"
        />

        <h3 v-if="result.valid">
          شهادة صحيحة وسارية
        </h3>
        <h3 v-else>
          شهادة غير صالحة
        </h3>

        <p
          v-if="!result.valid"
          class="v-sub"
        >
          لم يتم العثور على شهادة بهذا الرقم أو أنها لم تعد سارية.
        </p>

        <div
          v-else
          class="v-details"
        >
          <div class="v-row">
            <span class="label">اسم المقاول</span><span class="value">{{ result.contractor_name }}</span>
          </div>
          <div class="v-row">
            <span class="label">رقم العضوية</span><span class="value">{{ result.membership_number }}</span>
          </div>
          <div class="v-row">
            <span class="label">نوع الشهادة</span><span class="value">{{ result.type_label }}</span>
          </div>
          <div class="v-row">
            <span class="label">الرقم التسلسلي</span><span class="value">{{ result.serial }}</span>
          </div>
          <div class="v-row">
            <span class="label">تاريخ الإصدار</span><span class="value">{{ fmtDate(result.issued_at) }}</span>
          </div>
          <div class="v-row">
            <span class="label">سارية حتى</span><span class="value">{{ fmtDate(result.membership_valid_until) }}</span>
          </div>
        </div>
      </template>
    </div>
  </div>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800&display=swap');

.v-page { font-family: 'Tajawal', sans-serif; direction: rtl; min-height: 100vh; display: flex; align-items: center; justify-content: center; background: #f5f7ff; padding: 2rem; }

.v-card { background: #fff; border-radius: 16px; padding: 2.5rem; max-width: 420px; width: 100%; text-align: center; box-shadow: 0 8px 30px rgba(0,0,0,.08); border-top: 5px solid #2e3192; }
.v-card.valid { border-top-color: #2e7d32; }
.v-card.invalid { border-top-color: #c62828; }
.v-card.valid .v-icon { color: #2e7d32; }
.v-card.invalid .v-icon { color: #c62828; }
.v-icon { margin: 1.5rem 0 .5rem; }
.v-card h2 { font-size: 1.2rem; font-weight: 800; color: #2e3192; margin-bottom: .5rem; }
.v-card h3 { font-size: 1.05rem; font-weight: 800; color: #1a1a3e; margin-bottom: .5rem; }
.v-sub { color: #757575; font-size: .9rem; }
.v-error { color: #c62828; margin-top: .75rem; }

.v-form { display: flex; gap: .5rem; margin-top: 1.25rem; }
.v-input { flex: 1; min-width: 0; border: 1px solid #c5cae9; border-radius: 10px; padding: .65rem .9rem; font-size: 1rem; font-family: inherit; text-align: center; }
.v-input:focus { outline: none; border-color: #2e3192; }
.v-btn { background: #2e3192; color: #fff; border: 0; border-radius: 10px; padding: .65rem 1.2rem; font-family: inherit; font-weight: 700; cursor: pointer; }
.v-btn:disabled { opacity: .6; cursor: default; }

.v-details { margin-top: 1.5rem; display: flex; flex-direction: column; gap: .75rem; text-align: right; }
.v-row { display: flex; justify-content: space-between; border-bottom: 1px solid #e0e0e0; padding-bottom: .5rem; font-size: .9rem; }
.v-row .label { color: #757575; font-weight: 600; }
.v-row .value { color: #1a1a3e; font-weight: 700; }
</style>

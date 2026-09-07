<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import axios from 'axios'
import { CheckCircle2, XCircle } from 'lucide-vue-next'

definePage({
  meta: { layout: 'pure', public: true, unauthenticatedOnly: false },
})

const route = useRoute()
const isLoading = ref(true)

interface VerifyResult {
  valid: boolean
  contractor_name?: string
  membership_number?: string
  type_label?: string
  serial?: string
  issued_at?: string
  membership_valid_until?: string | null
}

const result = ref<VerifyResult>({ valid: false })

const BASE = import.meta.env.VITE_API_BASE_URL ?? ''

function fmtDate(d: string | null | undefined) {
  if (!d) return '—'
  return new Date(d).toLocaleDateString('ar-PS', { year: 'numeric', month: 'long', day: 'numeric' })
}

onMounted(async () => {
  try {
    const token = route.params.token as string
    const r = await axios.get(`${BASE}/api/v1/certificates/verify/${token}`)
    result.value = r.data.items ?? { valid: false }
  } catch {
    result.value = { valid: false }
  } finally {
    isLoading.value = false
  }
})
</script>

<template>
  <div dir="rtl" class="v-page">
    <div v-if="isLoading" class="v-loading">
      <div class="spinner" />
      <p>جاري التحقق من الشهادة...</p>
    </div>

    <template v-else>
      <div class="v-card" :class="result.valid ? 'valid' : 'invalid'">
        <CheckCircle2 v-if="result.valid" :size="56" class="v-icon" />
        <XCircle v-else :size="56" class="v-icon" />

        <h2 v-if="result.valid">شهادة صحيحة وسارية</h2>
        <h2 v-else>شهادة غير صالحة</h2>

        <p v-if="!result.valid" class="v-sub">
          لم يتم العثور على هذه الشهادة أو أنها لم تعد سارية.
        </p>

        <div v-else class="v-details">
          <div class="v-row"><span class="label">اسم المقاول</span><span class="value">{{ result.contractor_name }}</span></div>
          <div class="v-row"><span class="label">رقم العضوية</span><span class="value">{{ result.membership_number }}</span></div>
          <div class="v-row"><span class="label">نوع الشهادة</span><span class="value">{{ result.type_label }}</span></div>
          <div class="v-row"><span class="label">الرقم المرجعي</span><span class="value">{{ result.serial }}</span></div>
          <div class="v-row"><span class="label">تاريخ الإصدار</span><span class="value">{{ fmtDate(result.issued_at) }}</span></div>
          <div class="v-row"><span class="label">سارية حتى</span><span class="value">{{ fmtDate(result.membership_valid_until) }}</span></div>
        </div>
      </div>
    </template>
  </div>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800&display=swap');

.v-page { font-family: 'Tajawal', sans-serif; direction: rtl; min-height: 100vh; display: flex; align-items: center; justify-content: center; background: #f5f7ff; padding: 2rem; }

.v-loading { display: flex; flex-direction: column; align-items: center; gap: 1rem; color: #757575; }
.spinner { width: 40px; height: 40px; border: 3px solid #e8eaf6; border-top-color: #1a237e; border-radius: 50%; animation: spin .8s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }

.v-card { background: #fff; border-radius: 16px; padding: 2.5rem; max-width: 420px; width: 100%; text-align: center; box-shadow: 0 8px 30px rgba(0,0,0,.08); }
.v-card.valid { border-top: 5px solid #2e7d32; }
.v-card.invalid { border-top: 5px solid #c62828; }
.v-card.valid .v-icon { color: #2e7d32; }
.v-card.invalid .v-icon { color: #c62828; }
.v-icon { margin-bottom: 1rem; }
.v-card h2 { font-size: 1.2rem; font-weight: 800; color: #1a1a3e; margin-bottom: .5rem; }
.v-sub { color: #757575; font-size: .9rem; }

.v-details { margin-top: 1.5rem; display: flex; flex-direction: column; gap: .75rem; text-align: right; }
.v-row { display: flex; justify-content: space-between; border-bottom: 1px solid #e0e0e0; padding-bottom: .5rem; font-size: .9rem; }
.v-row .label { color: #757575; font-weight: 600; }
.v-row .value { color: #1a1a3e; font-weight: 700; }
</style>

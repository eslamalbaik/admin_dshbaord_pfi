<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import api from '@/plugins/axios'

/**
 * تعديل رصيد مقاول يدوياً من صفحة الأرصدة: زيادة، إنقاص، أو تحديد رصيد جديد، مع سبب إلزامي.
 * الباك إند بينفّذه كرصيد دائن أو ذمة "تعديل رصيد يدوي" وبيسجّله بسجل المحددات الهامة.
 * POST dashboard/balances/{id}/adjust — والسجل من GET dashboard/balances/{id}/adjustments.
 */
interface Adjustment {
  id: number
  mode: 'increase' | 'decrease' | 'set'
  mode_label: string
  amount_jod: number
  balance_before_jod: number
  balance_after_jod: number
  reason: string
  created_by: string | null
  created_at: string | null
}

const props = defineProps<{
  contractor: { contractor_id: number; name: string; membership_number: string; net_jod: number } | null
}>()

const emit = defineEmits<{ saved: [] }>()

const open = defineModel<boolean>({ default: false })

const modes = [
  { value: 'increase', title: 'زيادة الرصيد (له)', icon: 'tabler-plus' },
  { value: 'decrease', title: 'إنقاص الرصيد (عليه)', icon: 'tabler-minus' },
  { value: 'set', title: 'تحديد رصيد جديد', icon: 'tabler-equal' },
] as const

const mode = ref<'increase' | 'decrease' | 'set'>('increase')
const amount = ref<number | string>('')
const reason = ref('')
const saving = ref(false)
const confirming = ref(false)
const error = ref('')
const history = ref<Adjustment[]>([])
const historyLoading = ref(false)

const current = computed(() => Number(props.contractor?.net_jod ?? 0))

const amountNum = computed(() => {
  const n = Number(amount.value)

  return amount.value === '' || Number.isNaN(n) ? null : n
})

const after = computed(() => {
  if (amountNum.value === null)
    return null
  if (mode.value === 'set')
    return round(amountNum.value)

  return round(current.value + (mode.value === 'increase' ? Math.abs(amountNum.value) : -Math.abs(amountNum.value)))
})

const delta = computed(() => after.value === null ? null : round(after.value - current.value))

const amountError = computed(() => {
  if (amountNum.value === null)
    return ''
  if (mode.value !== 'set' && amountNum.value <= 0)
    return 'المبلغ لازم يكون أكبر من صفر.'
  if (delta.value !== null && Math.abs(delta.value) < 0.01)
    return 'الرصيد الجديد نفس الرصيد الحالي.'

  return ''
})

const canSave = computed(() => amountNum.value !== null && !amountError.value && reason.value.trim().length >= 3)

function round(v: number) {
  return Math.round(v * 100) / 100
}

function money(v: number | null | undefined) {
  if (v === null || v === undefined)
    return '—'

  return Number(v).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

function netColor(v: number | null) {
  return v === null ? '' : v < 0 ? 'text-error' : v > 0 ? 'text-success' : 'text-medium-emphasis'
}

function statusOf(v: number) {
  return v < 0 ? 'منتهية' : 'فعّالة'
}

function formatDate(v: string | null) {
  return v ? new Date(v).toLocaleString('en-GB', { dateStyle: 'short', timeStyle: 'short' }) : '—'
}

async function loadHistory() {
  if (!props.contractor)
    return
  historyLoading.value = true
  try {
    history.value = (await api.get(`/api/v1/dashboard/balances/${props.contractor.contractor_id}/adjustments`)).data.items ?? []
  }
  catch {
    history.value = []
  }
  finally {
    historyLoading.value = false
  }
}

watch(open, v => {
  if (!v)
    return
  mode.value = 'increase'
  amount.value = ''
  reason.value = ''
  error.value = ''
  confirming.value = false
  loadHistory()
})

async function save() {
  if (!props.contractor || !canSave.value)
    return
  saving.value = true
  error.value = ''
  try {
    await api.post(`/api/v1/dashboard/balances/${props.contractor.contractor_id}/adjust`, {
      mode: mode.value,
      amount: amountNum.value,
      reason: reason.value.trim(),
    })
    confirming.value = false
    open.value = false
    emit('saved')
  }
  catch (e: any) {
    const errors = e?.response?.data?.errors

    error.value = (errors && Object.values(errors).flat()[0] as string) || e?.response?.data?.message || 'فشل تعديل الرصيد.'
    confirming.value = false
  }
  finally {
    saving.value = false
  }
}
</script>

<template>
  <VDialog v-model="open" max-width="620" scrollable>
    <VCard v-if="contractor">
      <VCardTitle class="d-flex align-center justify-space-between pt-4 px-6">
        <span>تعديل رصيد: {{ contractor.name }}</span>
        <IconBtn @click="open = false">
          <VIcon icon="tabler-x" />
        </IconBtn>
      </VCardTitle>

      <VCardText class="px-6">
        <div class="d-flex justify-space-between align-center mb-4 pa-3 rounded bg-var-theme-background">
          <span class="text-medium-emphasis">الرصيد الحالي ({{ contractor.membership_number }})</span>
          <strong dir="ltr" :class="netColor(current)">{{ money(current) }} د.أ</strong>
        </div>

        <VAlert v-if="error" type="error" variant="tonal" class="mb-4">
          {{ error }}
        </VAlert>

        <VBtnToggle v-model="mode" mandatory divided color="primary" variant="outlined" class="mb-4 flex-wrap h-auto">
          <VBtn v-for="m in modes" :key="m.value" :value="m.value" :prepend-icon="m.icon">
            {{ m.title }}
          </VBtn>
        </VBtnToggle>

        <VTextField
          v-model="amount"
          type="number"
          step="0.01"
          :label="mode === 'set' ? 'الرصيد الجديد (د.أ) — سالب يعني عليه' : 'المبلغ (د.أ)'"
          :error-messages="amountError"
          dir="ltr"
          class="mb-4"
        />

        <VTextarea
          v-model="reason"
          label="سبب التعديل (إلزامي)"
          rows="2"
          auto-grow
          counter="500"
          maxlength="500"
          class="mb-2"
        />

        <div v-if="after !== null && !amountError" class="pa-3 rounded border">
          <div class="d-flex justify-space-between mb-1">
            <span>الرصيد بعد التعديل</span>
            <strong dir="ltr" :class="netColor(after)">{{ money(after) }} د.أ</strong>
          </div>
          <div class="d-flex justify-space-between mb-1 text-body-2">
            <span class="text-medium-emphasis">التغيير</span>
            <span dir="ltr">{{ (delta ?? 0) > 0 ? '+' : '' }}{{ money(delta) }}</span>
          </div>
          <div class="d-flex justify-space-between text-body-2">
            <span class="text-medium-emphasis">حالة العضوية بعد التعديل</span>
            <span :class="after < 0 ? 'text-error' : 'text-success'">{{ statusOf(after) }}</span>
          </div>
          <p class="text-caption text-medium-emphasis mt-2 mb-0">
            {{ (delta ?? 0) > 0
              ? 'ستُضاف كرصيد دائن للشركة، وإن كان عليها ذمم مفتوحة تُسدَّد منه تلقائياً.'
              : 'ستُضاف كذمة "تعديل رصيد يدوي" على الشركة، ويُخصم منها أي رصيد دائن موجود.' }}
            الدفعات والذمم الأصلية لا تتغيّر، والتعديل يُسجَّل في سجل المحددات الهامة.
          </p>
        </div>

        <VDivider class="my-4" />

        <h6 class="text-h6 mb-2">سجل تعديلات الرصيد</h6>
        <VProgressLinear v-if="historyLoading" indeterminate color="primary" class="mb-2" />
        <p v-else-if="!history.length" class="text-body-2 text-medium-emphasis mb-0">
          لا توجد تعديلات سابقة.
        </p>
        <div v-else class="overflow-x-auto">
          <VTable density="compact">
            <thead>
              <tr>
                <th>التاريخ</th>
                <th>النوع</th>
                <th>قبل</th>
                <th>بعد</th>
                <th>السبب</th>
                <th>بواسطة</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="h in history" :key="h.id">
                <td dir="ltr" class="text-no-wrap">{{ formatDate(h.created_at) }}</td>
                <td class="text-no-wrap">{{ h.mode_label }}</td>
                <td dir="ltr" :class="netColor(h.balance_before_jod)">{{ money(h.balance_before_jod) }}</td>
                <td dir="ltr" :class="netColor(h.balance_after_jod)">{{ money(h.balance_after_jod) }}</td>
                <td style="min-width: 140px;">{{ h.reason }}</td>
                <td class="text-no-wrap">{{ h.created_by ?? '—' }}</td>
              </tr>
            </tbody>
          </VTable>
        </div>
      </VCardText>

      <VCardActions class="px-6 pb-4">
        <VSpacer />
        <VBtn variant="tonal" color="secondary" @click="open = false">
          إلغاء
        </VBtn>
        <VBtn color="primary" variant="elevated" :disabled="!canSave" @click="confirming = true">
          حفظ التعديل
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>

  <VDialog v-model="confirming" max-width="460">
    <VCard>
      <VCardTitle class="pt-4 px-6">
        تأكيد تعديل الرصيد
      </VCardTitle>
      <VCardText class="px-6">
        سيتغيّر رصيد <strong>{{ contractor?.name }}</strong> من
        <strong dir="ltr" :class="netColor(current)">{{ money(current) }}</strong> إلى
        <strong dir="ltr" :class="netColor(after)">{{ money(after) }}</strong> د.أ،
        وستصبح حالة العضوية <strong>{{ after !== null ? statusOf(after) : '' }}</strong>.
      </VCardText>
      <VCardActions class="px-6 pb-4">
        <VSpacer />
        <VBtn variant="tonal" color="secondary" @click="confirming = false">
          رجوع
        </VBtn>
        <VBtn color="primary" variant="elevated" :loading="saving" @click="save">
          تأكيد
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

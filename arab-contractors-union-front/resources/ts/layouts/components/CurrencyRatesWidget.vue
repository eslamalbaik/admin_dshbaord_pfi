<script lang="ts" setup>
import { computed } from 'vue'
import { useExchangeRates } from '@/composables/useExchangeRates'

// Live top-bar exchange rate widget (REQ-17) — zero manual axios calls, all
// fetching/polling handled by useExchangeRates (TanStack Vue Query).
const { ils, usd, isLoading } = useExchangeRates()

const sourceLabels: Record<string, string> = {
  pma_direct: 'سلطة النقد (مباشر)',
  supabase_proxy: 'سلطة النقد (مصدر مساعد)',
  manual: 'إدخال يدوي',
  api: 'مصدر عام',
}

const sourceLabel = (source?: string) => (source ? sourceLabels[source] ?? source : '')

const formatTime = (dateStr?: string) => {
  if (!dateStr)
    return ''
  return new Date(dateStr).toLocaleString(undefined, {
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}

const chips = computed(() => [
  { code: 'ILS', rate: ils.value },
  { code: 'USD', rate: usd.value },
])
</script>

<template>
  <div
    v-if="!isLoading"
    class="d-flex align-center gap-1 me-2"
  >
    <VTooltip
      v-for="chip in chips"
      :key="chip.code"
      location="bottom"
    >
      <template #activator="{ props }">
        <VChip
          v-bind="props"
          size="small"
          variant="tonal"
          color="primary"
          :prepend-icon="chip.code === 'ILS' ? 'tabler-currency-shekel' : 'tabler-currency-dollar'"
        >
          <!-- المخزَّن بالباك اند rate_to_jod = "1 [عملة] = ? JOD" (لازم لحساب تحويل الدفعات JOD).
               للعرض هنا نقلبها: "1 JOD = ? [عملة]" — نفس الاتجاه المعتمَد محلياً وبمصدر سلطة النقد نفسه. -->
          {{ chip.rate ? `1 JOD = ${(1 / Number(chip.rate.rate_to_jod)).toFixed(4)} ${chip.code}` : `${chip.code} —` }}
        </VChip>
      </template>
      <span v-if="chip.rate">
        {{ sourceLabel(chip.rate.source) }} · {{ formatTime(chip.rate.fetched_at) }}
      </span>
      <span v-else>لا يوجد سعر محفوظ بعد</span>
    </VTooltip>
  </div>
</template>

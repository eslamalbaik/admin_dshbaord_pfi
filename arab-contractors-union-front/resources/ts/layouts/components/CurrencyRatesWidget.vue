<script lang="ts" setup>
import { computed } from 'vue'
import { useDisplay } from 'vuetify'
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

const chipLabel = (chip: { code: string; rate?: { rate_to_jod: number | string } | null }) =>
  chip.rate ? `1 JOD = ${(1 / Number(chip.rate.rate_to_jod)).toFixed(4)} ${chip.code}` : `${chip.code} —`

// تحت md (أقل من 960px) ما في مكان بالـ navbar لشريحتين عريضتين: نعرض أيقونة وحدة
// بتفتح قائمة فيها نفس الأسعار، بدل ما يصير الـ navbar أعرض من الشاشة.
const display = useDisplay()
</script>

<template>
  <IconBtn
    v-if="!isLoading && !display.mdAndUp.value"
    class="me-1"
  >
    <VIcon icon="tabler-currency-dollar" />
    <VMenu
      activator="parent"
      location="bottom end"
      offset="12px"
      max-width="calc(100vw - 32px)"
    >
      <VList density="compact">
        <VListItem
          v-for="chip in chips"
          :key="chip.code"
          :prepend-icon="chip.code === 'ILS' ? 'tabler-currency-shekel' : 'tabler-currency-dollar'"
          :subtitle="chip.rate ? `${sourceLabel(chip.rate.source)} · ${formatTime(chip.rate.fetched_at)}` : 'لا يوجد سعر محفوظ بعد'"
        >
          <VListItemTitle dir="ltr" class="text-end">
            {{ chipLabel(chip) }}
          </VListItemTitle>
        </VListItem>
      </VList>
    </VMenu>
  </IconBtn>
  <div
    v-else-if="!isLoading"
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
          {{ chipLabel(chip) }}
        </VChip>
      </template>
      <span v-if="chip.rate">
        {{ sourceLabel(chip.rate.source) }} · {{ formatTime(chip.rate.fetched_at) }}
      </span>
      <span v-else>لا يوجد سعر محفوظ بعد</span>
    </VTooltip>
  </div>
</template>

<script setup lang="ts">
import { useDashboardStore } from '@/stores/dashboardStore'

const dashboardStore = useDashboardStore()
const seriesData = computed(() => dashboardStore.revenueChart.map(v => Number(v)))

const series = computed(() => [
  {
    name: 'الإيرادات',
    data: seriesData.value.length ? seriesData.value : [0],
  },
])

const growthLabel = computed(() => {
  const data = seriesData.value
  if (data.length < 2) return null
  const first = data[0]
  const last = data[data.length - 1]
  if (!first) return null
  const pct = ((last - first) / first) * 100
  return `${pct >= 0 ? '+' : ''}${pct.toFixed(1)}%`
})

const monthNames = ['يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو', 'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر']

// آخر N شهر تنتهي بالشهر الحالي — بعدد نفس عناصر revenueChart القادمة من الباك اند
const categories = computed(() => {
  const count = seriesData.value.length
  const now = new Date()
  return Array.from({ length: count }, (_, i) => {
    const d = new Date(now.getFullYear(), now.getMonth() - (count - 1 - i), 1)
    return monthNames[d.getMonth()]
  })
})

const chartOptions = computed(() => ({
  chart: {
    type: 'area',
    toolbar: { show: false },
    sparkline: { enabled: false },
    fontFamily: 'Cairo, sans-serif',
  },
  colors: ['#1B4D3E'],
  fill: {
    type: 'gradient',
    gradient: {
      shadeIntensity: 1,
      opacityFrom: 0.4,
      opacityTo: 0.1,
      stops: [0, 90, 100],
    },
  },
  stroke: {
    curve: 'smooth' as const,
    width: 3,
  },
  xaxis: {
    categories: categories.value,
    axisBorder: { show: false },
    axisTicks: { show: false },
    labels: {
      style: { colors: '#9e9e9e', fontFamily: 'Cairo, sans-serif' },
    },
  },
  yaxis: {
    labels: {
      formatter: (val: number) => `₪ ${(val / 1000).toFixed(1)}k`,
      style: { colors: '#9e9e9e', fontFamily: 'Cairo, sans-serif' },
    },
  },
  grid: {
    borderColor: '#e0e0e0',
    strokeDashArray: 4,
  },
  tooltip: {
    y: {
      formatter: (val: number) => `₪ ${val.toLocaleString()}`,
    },
  },
  dataLabels: { enabled: false },
}))
</script>

<template>
  <VCard>
    <VCardItem>
      <VCardTitle style="font-family:Cairo,sans-serif">نمو الإيرادات</VCardTitle>
      <VCardSubtitle style="font-family:Cairo,sans-serif">نظرة عامة على الإيرادات الشهرية</VCardSubtitle>

      <template v-if="growthLabel" #append>
        <VChip
          :color="growthLabel.startsWith('-') ? 'error' : 'success'"
          size="small"
          label
        >
          <VIcon
            :icon="growthLabel.startsWith('-') ? 'tabler-arrow-down' : 'tabler-arrow-up'"
            size="14"
            start
          />
          {{ growthLabel }}
        </VChip>
      </template>
    </VCardItem>

    <VCardText>
      <VueApexCharts
        v-if="seriesData && seriesData.length > 0"
        type="area"
        :height="320"
        :options="chartOptions"
        :series="series"
      />
      <p v-else class="text-center text-medium-emphasis py-8" style="font-family:Cairo,sans-serif">
        لا توجد بيانات إيرادات بعد
      </p>
    </VCardText>
  </VCard>
</template>

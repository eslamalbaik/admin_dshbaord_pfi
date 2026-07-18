<script setup lang="ts">
const seriesData = ref<number[]>([3200, 4100, 3800, 5200, 4900, 6300, 5800, 6900, 7400, 8100, 7600, 9800])

const series = computed(() => [
  {
    name: 'الإيرادات',
    data: seriesData.value || [0],
  },
])

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
    categories: ['يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو', 'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر'],
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

      <template #append>
        <VChip
          color="success"
          size="small"
          label
        >
          <VIcon
            icon="tabler-arrow-up"
            size="14"
            start
          />
          +18.2% سنوياً
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
    </VCardText>
  </VCard>
</template>

<script setup lang="ts">
const seriesData = ref([
  {
    name: 'Watch Time (hrs)',
    data: [120, 145, 98, 168, 155, 189, 210, 195, 230, 245, 220, 260],
  },
  {
    name: 'Completions',
    data: [35, 42, 28, 55, 48, 62, 71, 65, 78, 82, 75, 95],
  },
])

const series = computed(() => seriesData.value || [])

const chartOptions = computed(() => ({
  chart: {
    type: 'line',
    toolbar: { show: false },
    fontFamily: 'inherit',
  },
  colors: ['#FF9F43', '#28C76F'],
  stroke: {
    curve: 'smooth' as const,
    width: [3, 3],
    dashArray: [0, 5],
  },
  xaxis: {
    categories: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
    axisBorder: { show: false },
    axisTicks: { show: false },
    labels: {
      style: {
        colors: '#9e9e9e',
      },
    },
  },
  yaxis: {
    labels: {
      style: {
        colors: '#9e9e9e',
      },
    },
  },
  grid: {
    borderColor: '#e0e0e0',
    strokeDashArray: 4,
  },
  legend: {
    position: 'top' as const,
    horizontalAlign: 'right' as const,
    labels: {
      colors: '#9e9e9e',
    },
  },
  dataLabels: { enabled: false },
}))
</script>

<template>
  <VCard>
    <VCardItem>
      <VCardTitle>{{ $t('Course Engagement') }}</VCardTitle>
      <VCardSubtitle>{{ $t('Active vs Inactive students') }}</VCardSubtitle>
    </VCardItem>

    <VCardText>
      <VueApexCharts
        v-if="seriesData && seriesData.length > 0"
        type="line"
        :height="280"
        :options="chartOptions"
        :series="series"
      />
    </VCardText>
  </VCard>
</template>

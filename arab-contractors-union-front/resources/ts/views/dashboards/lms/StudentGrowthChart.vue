<script setup lang="ts">
const seriesData = ref<number[]>([45, 52, 38, 65, 73, 55, 68, 81, 96, 89, 78, 110])

const series = computed(() => [
  {
    name: 'New Students',
    data: seriesData.value || [0],
  },
])

const chartOptions = computed(() => ({
  chart: {
    type: 'bar',
    toolbar: { show: false },
    fontFamily: 'inherit',
  },
  colors: ['#00CFE8'],
  plotOptions: {
    bar: {
      borderRadius: 6,
      columnWidth: '50%',
      distributed: false,
    },
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
  dataLabels: { enabled: false },
  tooltip: {
    y: {
      formatter: (val: number) => `${val} students`,
    },
  },
}))
</script>

<template>
  <VCard>
    <VCardItem>
      <VCardTitle>{{ $t('Student Growth') }}</VCardTitle>
      <VCardSubtitle>{{ $t('New enrollments per month') }}</VCardSubtitle>
    </VCardItem>

    <VCardText>
      <VueApexCharts
        v-if="seriesData && seriesData.length > 0 && !seriesData.includes(undefined as any)"
        type="bar"
        :height="280"
        :options="chartOptions"
        :series="series"
      />
    </VCardText>
  </VCard>
</template>

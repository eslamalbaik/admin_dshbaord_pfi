<script setup lang="ts">
const seriesData = ref<number[]>([73])

const series = computed(() => seriesData.value || [0])

const chartOptions = computed(() => ({
  chart: {
    type: 'radialBar',
    fontFamily: 'inherit',
  },
  colors: ['#7367F0'],
  plotOptions: {
    radialBar: {
      startAngle: -135,
      endAngle: 135,
      hollow: {
        size: '65%',
      },
      track: {
        background: '#e0e0e0',
        strokeWidth: '100%',
      },
      dataLabels: {
        name: {
          fontSize: '14px',
          color: '#9e9e9e',
          offsetY: 70,
        },
        value: {
          fontSize: '36px',
          fontWeight: 600,
          color: '#4b465c',
          offsetY: -10,
          formatter: (val: number) => `${val}%`,
        },
      },
    },
  },
  labels: ['Completion Rate'],
  stroke: {
    lineCap: 'round' as const,
  },
}))

const courseStats = ref([
  { label: 'Pharmacology', rate: 82, color: 'success' },
  { label: 'Clinical Pharmacy', rate: 71, color: 'primary' },
  { label: 'Drug Interactions', rate: 65, color: 'warning' },
  { label: 'Pharmaceutical Chemistry', rate: 58, color: 'info' },
])
</script>

<template>
  <VCard class="h-100">
    <VCardItem>
      <VCardTitle>{{ $t('Completion Rate') }}</VCardTitle>
      <VCardSubtitle>{{ $t('Average course completion') }}</VCardSubtitle>
    </VCardItem>

    <VCardText class="d-flex flex-column align-center">
      <VueApexCharts
        v-if="seriesData && seriesData.length > 0 && !seriesData.includes(undefined as any)"
        type="radialBar"
        :height="220"
        :options="chartOptions"
        :series="series"
      />

      <div class="w-100 mt-4">
        <div
          v-for="course in courseStats"
          :key="course.label"
          class="d-flex align-center justify-space-between mb-3"
        >
          <div class="d-flex align-center gap-2">
            <VIcon
              icon="tabler-circle-filled"
              :color="course.color"
              size="10"
            />
            <span class="text-body-2">{{ course.label }}</span>
          </div>
          <div class="d-flex align-center gap-3" style="min-inline-size: 120px;">
            <VProgressLinear
              :model-value="course.rate"
              :color="course.color"
              rounded
              height="6"
            />
            <span class="text-body-2 font-weight-medium" style="min-inline-size: 32px;">{{ course.rate }}%</span>
          </div>
        </div>
      </div>
    </VCardText>
  </VCard>
</template>

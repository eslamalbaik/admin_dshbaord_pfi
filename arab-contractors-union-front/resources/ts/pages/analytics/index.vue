<script setup lang="ts">
import api from '@/plugins/axios'

definePage({
  meta: { requiresAdmin: true },
})

// ─── Types ────────────────────────────────────────────────────
interface ReportSummary {
  revenue: number
  newContractors: number
  activeMemberships: number
  tenders: number
  penaltiesIssued: number
  penaltiesAmount: number
  penaltiesPaid: number
  revenueChart: number[]
  contractorsChart: number[]
  paymentTypes: Record<string, number>
}

// ─── State ────────────────────────────────────────────────────
const period    = ref<'monthly' | 'annual'>('monthly')
const year      = ref(new Date().getFullYear())
const month     = ref(new Date().getMonth() + 1)
const loading   = ref(false)
const exporting = ref<'pdf' | 'excel' | null>(null)

const summary = ref<ReportSummary | null>(null)

// ─── Helpers ──────────────────────────────────────────────────
const currentYear = new Date().getFullYear()
const years = Array.from({ length: 5 }, (_, i) => currentYear - i)

const arabicMonths = [
  { value: 1,  label: 'يناير' },
  { value: 2,  label: 'فبراير' },
  { value: 3,  label: 'مارس' },
  { value: 4,  label: 'أبريل' },
  { value: 5,  label: 'مايو' },
  { value: 6,  label: 'يونيو' },
  { value: 7,  label: 'يوليو' },
  { value: 8,  label: 'أغسطس' },
  { value: 9,  label: 'سبتمبر' },
  { value: 10, label: 'أكتوبر' },
  { value: 11, label: 'نوفمبر' },
  { value: 12, label: 'ديسمبر' },
]

const monthLabels = arabicMonths.map(m => m.label)

const formatCurrency = (val: number) =>
  new Intl.NumberFormat('ar-SA', {
    style: 'currency',
    currency: 'ILS',
    maximumFractionDigits: 0,
  }).format(val)

// ─── Fetch ────────────────────────────────────────────────────
async function fetchSummary() {
  loading.value = true
  try {
    const params: Record<string, any> = { period: period.value, year: year.value }
    if (period.value === 'monthly')
      params.month = month.value

    const res = await api.get('/api/v1/reports/summary', { params })
    const d = res.data
    // Laravel returns decimal columns as strings — cast to number
    summary.value = {
      ...d,
      revenue:           Number(d.revenue),
      penaltiesAmount:   Number(d.penaltiesAmount),
      penaltiesPaid:     Number(d.penaltiesPaid),
      revenueChart:      (d.revenueChart as any[]).map(Number),
      contractorsChart:  (d.contractorsChart as any[]).map(Number),
      paymentTypes:      Object.fromEntries(
        Object.entries(d.paymentTypes as Record<string, any>).map(([k, v]) => [k, Number(v)]),
      ),
    }
  }
  catch (e) {
    console.error(e)
    summary.value = null
  }
  finally {
    loading.value = false
  }
}

// ─── Export ───────────────────────────────────────────────────
async function exportReport(type: 'pdf' | 'excel') {
  exporting.value = type
  try {
    const params: Record<string, any> = { period: period.value, year: year.value }
    if (period.value === 'monthly')
      params.month = month.value

    const endpoint = type === 'pdf'
      ? '/api/v1/reports/export/pdf'
      : '/api/v1/reports/export/excel'

    const resp = await api.get(endpoint, { params, responseType: 'blob' })

    const ext      = type === 'pdf' ? 'pdf' : 'xlsx'
    const filename = period.value === 'monthly'
      ? `تقرير_${year.value}_${month.value}.${ext}`
      : `تقرير_سنوي_${year.value}.${ext}`

    const url = URL.createObjectURL(new Blob([resp.data]))
    const a   = document.createElement('a')
    a.href     = url
    a.download = filename
    a.click()
    URL.revokeObjectURL(url)
  }
  catch (e) {
    console.error(e)
  }
  finally {
    exporting.value = null
  }
}

// ─── Chart Options ────────────────────────────────────────────
const revenueChartOptions = computed(() => ({
  chart: {
    type: 'bar',
    height: 280,
    toolbar: { show: false },
    fontFamily: 'Cairo, sans-serif',
  },
  plotOptions: {
    bar: { borderRadius: 6, columnWidth: '55%' },
  },
  dataLabels: { enabled: false },
  xaxis: {
    categories: monthLabels,
    labels: { style: { fontFamily: 'Cairo, sans-serif', fontSize: '11px' } },
  },
  yaxis: {
    labels: {
      formatter: (v: number) => formatCurrency(v),
      style: { fontFamily: 'Cairo, sans-serif', fontSize: '11px' },
    },
  },
  tooltip: {
    y: { formatter: (v: number) => formatCurrency(v) },
  },
  colors: ['#1565C0'],
  grid: { borderColor: '#E0E0E0', strokeDashArray: 4 },
}))

const revenueChartSeries = computed(() => [
  { name: 'الإيرادات', data: summary.value?.revenueChart ?? Array(12).fill(0) },
])

const contractorsChartOptions = computed(() => ({
  chart: {
    type: 'line',
    height: 260,
    toolbar: { show: false },
    fontFamily: 'Cairo, sans-serif',
  },
  stroke: { curve: 'smooth', width: 3 },
  markers: { size: 5 },
  xaxis: {
    categories: monthLabels,
    labels: { style: { fontFamily: 'Cairo, sans-serif', fontSize: '11px' } },
  },
  yaxis: {
    labels: {
      formatter: (v: number) => Math.round(v).toString(),
      style: { fontFamily: 'Cairo, sans-serif', fontSize: '11px' },
    },
  },
  colors: ['#2E7D32'],
  grid: { borderColor: '#E0E0E0', strokeDashArray: 4 },
  tooltip: { y: { formatter: (v: number) => `${Math.round(v)} مقاول` } },
}))

const contractorsChartSeries = computed(() => [
  { name: 'مقاولون جدد', data: summary.value?.contractorsChart ?? Array(12).fill(0) },
])

const penaltiesDonutOptions = computed(() => ({
  chart: {
    type: 'donut',
    height: 240,
    fontFamily: 'Cairo, sans-serif',
  },
  labels: ['مدفوع', 'معلق'],
  colors: ['#2E7D32', '#C62828'],
  legend: { fontFamily: 'Cairo, sans-serif', position: 'bottom' },
  dataLabels: {
    formatter: (val: number) => `${val.toFixed(1)}%`,
  },
  tooltip: {
    y: { formatter: (v: number) => formatCurrency(v) },
  },
}))

const penaltiesDonutSeries = computed(() => {
  const paid    = summary.value?.penaltiesPaid ?? 0
  const total   = summary.value?.penaltiesAmount ?? 0
  const pending = Math.max(0, total - paid)
  return [paid, pending]
})

const paymentTypesOptions = computed(() => {
  const typeLabels: Record<string, string> = {
    membership_fee: 'رسوم عضوية',
    renewal_fee:    'رسوم تجديد',
    other:          'أخرى',
  }
  const types = summary.value?.paymentTypes ?? {}
  return {
    chart: {
      type: 'pie',
      height: 240,
      fontFamily: 'Cairo, sans-serif',
    },
    labels: Object.keys(types).map(k => typeLabels[k] ?? k),
    colors: ['#1565C0', '#7B1FA2', '#E65100'],
    legend: { fontFamily: 'Cairo, sans-serif', position: 'bottom' },
    tooltip: {
      y: { formatter: (v: number) => formatCurrency(v) },
    },
  }
})

const paymentTypesSeries = computed(() =>
  Object.values(summary.value?.paymentTypes ?? {}) as number[],
)

// ─── Watchers ─────────────────────────────────────────────────
watch([period, year, month], fetchSummary)
onMounted(fetchSummary)
</script>

<template>
  <div style="font-family: Cairo, sans-serif;">
    <!-- ─── Header ─── -->
    <VRow class="mb-4" align="center">
      <VCol>
        <h4 class="text-h5 font-weight-bold">
          <VIcon icon="tabler-chart-bar" class="me-2 text-primary" />
          التقارير والإحصائيات
        </h4>
        <p class="text-body-2 text-medium-emphasis mt-1 mb-0">
          تحليلات مالية وإدارية قابلة للتصدير
        </p>
      </VCol>

      <!-- Export -->
      <VCol cols="auto">
        <VBtn
          variant="tonal"
          color="error"
          class="me-2"
          :loading="exporting === 'pdf'"
          prepend-icon="tabler-file-type-pdf"
          @click="exportReport('pdf')"
        >
          PDF تصدير
        </VBtn>
        <VBtn
          variant="tonal"
          color="success"
          :loading="exporting === 'excel'"
          prepend-icon="tabler-table-export"
          @click="exportReport('excel')"
        >
          Excel (.xlsx) تصدير
        </VBtn>
      </VCol>
    </VRow>

    <!-- ─── Filters ─── -->
    <VCard class="mb-5" elevation="1">
      <VCardText>
        <VRow align="center">
          <VCol cols="12" sm="auto">
            <div class="d-flex gap-2">
              <VBtn
                :variant="period === 'monthly' ? 'flat' : 'tonal'"
                :color="period === 'monthly' ? 'primary' : 'default'"
                size="small"
                prepend-icon="tabler-calendar-month"
                @click="period = 'monthly'"
              >
                شهري
              </VBtn>
              <VBtn
                :variant="period === 'annual' ? 'flat' : 'tonal'"
                :color="period === 'annual' ? 'primary' : 'default'"
                size="small"
                prepend-icon="tabler-calendar"
                @click="period = 'annual'"
              >
                سنوي
              </VBtn>
            </div>
          </VCol>

          <VDivider vertical class="d-none d-sm-block mx-3" />

          <VCol cols="6" sm="2">
            <VSelect
              v-model="year"
              :items="years"
              label="السنة"
              density="compact"
              hide-details
              variant="outlined"
            />
          </VCol>

          <VCol v-if="period === 'monthly'" cols="6" sm="3">
            <VSelect
              v-model="month"
              :items="arabicMonths"
              item-title="label"
              item-value="value"
              label="الشهر"
              density="compact"
              hide-details
              variant="outlined"
            />
          </VCol>
        </VRow>
      </VCardText>
    </VCard>

    <!-- Loading -->
    <div v-if="loading" class="d-flex justify-center align-center py-16">
      <VProgressCircular indeterminate color="primary" size="52" />
    </div>

    <template v-else-if="summary">
      <!-- ─── KPI Cards ─── -->
      <VRow class="mb-4">
        <VCol cols="12" sm="6" md="3">
          <VCard elevation="1">
            <VCardText class="text-center py-5">
              <VIcon icon="tabler-currency-shekel" size="38" color="primary" class="mb-2" />
              <div class="text-h5 font-weight-bold text-primary">
                {{ formatCurrency(summary.revenue) }}
              </div>
              <div class="text-body-2 text-medium-emphasis mt-1">إجمالي الإيرادات</div>
            </VCardText>
          </VCard>
        </VCol>

        <VCol cols="12" sm="6" md="3">
          <VCard elevation="1">
            <VCardText class="text-center py-5">
              <VIcon icon="tabler-building-factory-2" size="38" color="success" class="mb-2" />
              <div class="text-h5 font-weight-bold text-success">
                {{ summary.newContractors }}
              </div>
              <div class="text-body-2 text-medium-emphasis mt-1">
                {{ period === 'monthly' ? 'مقاولون جدد هذا الشهر' : 'مقاولون جدد هذه السنة' }}
              </div>
            </VCardText>
          </VCard>
        </VCol>

        <VCol cols="12" sm="6" md="3">
          <VCard elevation="1">
            <VCardText class="text-center py-5">
              <VIcon icon="tabler-id-badge" size="38" color="info" class="mb-2" />
              <div class="text-h5 font-weight-bold text-info">
                {{ summary.activeMemberships }}
              </div>
              <div class="text-body-2 text-medium-emphasis mt-1">عضويات نشطة</div>
            </VCardText>
          </VCard>
        </VCol>

        <VCol cols="12" sm="6" md="3">
          <VCard elevation="1">
            <VCardText class="text-center py-5">
              <VIcon icon="tabler-alert-triangle" size="38" color="error" class="mb-2" />
              <div class="text-h5 font-weight-bold text-error">
                {{ summary.penaltiesIssued }}
              </div>
              <div class="text-body-2 text-medium-emphasis mt-1">
                غرامات صادرة ({{ formatCurrency(summary.penaltiesAmount) }})
              </div>
            </VCardText>
          </VCard>
        </VCol>
      </VRow>

      <!-- ─── Charts Row 1 ─── -->
      <VRow class="mb-4">
        <!-- Revenue Bar -->
        <VCol cols="12" md="8">
          <VCard elevation="1">
            <VCardTitle class="pa-4 pb-0 text-body-1 font-weight-bold">
              <VIcon icon="tabler-chart-bar" class="me-2 text-primary" size="20" />
              الإيرادات الشهرية
            </VCardTitle>
            <VCardText class="pt-2">
              <VueApexCharts
                type="bar"
                height="280"
                :options="revenueChartOptions"
                :series="revenueChartSeries"
              />
            </VCardText>
          </VCard>
        </VCol>

        <!-- Penalties Donut -->
        <VCol cols="12" md="4">
          <VCard elevation="1" height="100%">
            <VCardTitle class="pa-4 pb-0 text-body-1 font-weight-bold">
              <VIcon icon="tabler-chart-donut" class="me-2 text-error" size="20" />
              حالة الغرامات
            </VCardTitle>
            <VCardText class="pt-2">
              <VueApexCharts
                v-if="summary.penaltiesAmount > 0"
                type="donut"
                height="240"
                :options="penaltiesDonutOptions"
                :series="penaltiesDonutSeries"
              />
              <div
                v-else
                class="d-flex flex-column align-center justify-center"
                style="height:240px;"
              >
                <VIcon icon="tabler-check-circle" size="48" color="success" class="mb-2" />
                <span class="text-body-2 text-medium-emphasis">لا توجد غرامات في هذه الفترة</span>
              </div>

              <VDivider class="my-3" />
              <div class="d-flex justify-space-between text-body-2 mb-1">
                <span class="text-medium-emphasis">مدفوع</span>
                <span class="text-success font-weight-medium">{{ formatCurrency(summary.penaltiesPaid) }}</span>
              </div>
              <div class="d-flex justify-space-between text-body-2">
                <span class="text-medium-emphasis">معلق</span>
                <span class="text-error font-weight-medium">
                  {{ formatCurrency(Math.max(0, summary.penaltiesAmount - summary.penaltiesPaid)) }}
                </span>
              </div>
            </VCardText>
          </VCard>
        </VCol>
      </VRow>

      <!-- ─── Charts Row 2 ─── -->
      <VRow>
        <!-- Contractors Line -->
        <VCol cols="12" md="7">
          <VCard elevation="1">
            <VCardTitle class="pa-4 pb-0 text-body-1 font-weight-bold">
              <VIcon icon="tabler-users" class="me-2 text-success" size="20" />
              المقاولون الجدد
            </VCardTitle>
            <VCardText class="pt-2">
              <VueApexCharts
                type="line"
                height="260"
                :options="contractorsChartOptions"
                :series="contractorsChartSeries"
              />
            </VCardText>
          </VCard>
        </VCol>

        <!-- Payment Types Pie -->
        <VCol cols="12" md="5">
          <VCard elevation="1" height="100%">
            <VCardTitle class="pa-4 pb-0 text-body-1 font-weight-bold">
              <VIcon icon="tabler-chart-pie" class="me-2 text-secondary" size="20" />
              توزيع أنواع المدفوعات
            </VCardTitle>
            <VCardText class="pt-2">
              <VueApexCharts
                v-if="paymentTypesSeries.length > 0 && paymentTypesSeries.some(v => v > 0)"
                type="pie"
                height="240"
                :options="paymentTypesOptions"
                :series="paymentTypesSeries"
              />
              <div
                v-else
                class="d-flex flex-column align-center justify-center"
                style="height:240px;"
              >
                <VIcon icon="tabler-database-off" size="48" color="secondary" class="mb-2" />
                <span class="text-body-2 text-medium-emphasis">لا توجد مدفوعات في هذه الفترة</span>
              </div>
            </VCardText>
          </VCard>
        </VCol>
      </VRow>
    </template>

    <!-- Error / No data -->
    <div v-else class="d-flex flex-column align-center justify-center py-16">
      <VIcon icon="tabler-chart-bar-off" size="64" color="secondary" class="mb-4 opacity-40" />
      <p class="text-body-1 text-medium-emphasis">تعذّر تحميل بيانات التقارير</p>
      <VBtn variant="tonal" class="mt-3" @click="fetchSummary">إعادة المحاولة</VBtn>
    </div>
  </div>
</template>

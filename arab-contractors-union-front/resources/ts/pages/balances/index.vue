<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useQuery } from '@tanstack/vue-query'
import api from '@/plugins/axios'

definePage({ meta: { requiresAdmin: true } })

interface BalanceRow {
  contractor_id: number
  name: string
  membership_number: string
  status: string
  credit_jod: number
  dues_jod: number
  penalties_jod: number
  debit_jod: number
  net_jod: number
}

const statusLabels: Record<string, { text: string; color: string }> = {
  active: { text: 'فعّالة', color: 'success' },
  expired: { text: 'منتهية', color: 'error' },
  pending: { text: 'قيد المراجعة', color: 'warning' },
  suspended: { text: 'موقوفة', color: 'secondary' },
}

const filters = [
  { value: 'all', title: 'الكل' },
  { value: 'owes', title: 'عليهم (سالب)' },
  { value: 'credit', title: 'لهم (موجب)' },
  { value: 'zero', title: 'رصيد صفر' },
]

const search = ref('')
const filter = ref('all')
const sort = ref<'asc' | 'desc'>('asc')
const page = ref(1)

watch([search, filter, sort], () => page.value = 1)

function params(extra: Record<string, unknown> = {}) {
  return {
    search: search.value || undefined,
    filter: filter.value === 'all' ? undefined : filter.value,
    sort: sort.value,
    ...extra,
  }
}

const { data, isLoading } = useQuery({
  queryKey: ['contractor-balances', search, filter, sort, page],
  queryFn: async () => (await api.get('/api/v1/dashboard/balances', { params: params({ page: page.value }) })).data,
})

const { data: summary } = useQuery({
  queryKey: ['contractor-balances-summary'],
  queryFn: async () => (await api.get('/api/v1/dashboard/balances/summary')).data,
})

function money(v: number | undefined | null) {
  if (v === undefined || v === null)
    return '—'

  return Number(v).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

function netColor(v: number) {
  return v < 0 ? 'text-error' : v > 0 ? 'text-success' : 'text-medium-emphasis'
}

// ─── سجل المدفوعات التفصيلي لشركة (كشف حساب: ذمم، غرامات، دفعات، المتبقي بعد كل حركة) ───
interface StatementEntry {
  kind: 'due' | 'penalty' | 'payment' | 'credit' | 'settlement'
  id: number
  date: string | null
  title: string
  reference_number: string | null
  bank_reference_number?: string | null
  direction: 'debit' | 'credit'
  amount_jod: number
  status: string
  status_label: string
  counts_in_balance: boolean
  balance_after_jod: number
  notes?: string | null
  allocations?: { kind: string; id: number; title: string; amount_jod: number }[]
}

const kindLabels: Record<string, string> = {
  due: 'ذمة',
  penalty: 'غرامة',
  payment: 'دفعة',
  credit: 'رصيد دائن',
  settlement: 'تسديد سابق',
}

const statementFor = ref<BalanceRow | null>(null)
const statementOpen = ref(false)

const statementContractorId = computed(() => statementFor.value?.contractor_id)

const { data: statement, isFetching: statementLoading } = useQuery({
  queryKey: ['contractor-statement', statementContractorId],
  queryFn: async () => (await api.get(`/api/v1/dashboard/balances/${statementContractorId.value}/statement`)).data,
  enabled: computed(() => statementOpen.value && !!statementContractorId.value),
})

function openStatement(r: BalanceRow) {
  statementFor.value = r
  statementOpen.value = true
}

// ─── تصدير الجدول (حسب الفلتر الحالي) إلى ملف يفتح بالإكسل ───
const exporting = ref(false)

async function exportCsv() {
  exporting.value = true
  try {
    const rows: BalanceRow[] = []
    let p = 1
    let last = 1
    do {
      const r = (await api.get('/api/v1/dashboard/balances', { params: params({ page: p, per_page: 500 }) })).data
      rows.push(...(r.items ?? []))
      last = r.meta?.last_page ?? 1
      p++
    } while (p <= last)

    const head = ['رقم العضوية', 'الشركة', 'حالة العضوية', 'له (د.أ)', 'ذمم (د.أ)', 'غرامات (د.أ)', 'عليه (د.أ)', 'الصافي (د.أ)']
    const lines = rows.map(r => [
      r.membership_number, r.name, statusLabels[r.status]?.text ?? r.status,
      r.credit_jod, r.dues_jod, r.penalties_jod, r.debit_jod, r.net_jod,
    ].map(v => `"${String(v ?? '').replace(/"/g, '""')}"`).join(','))

    const blob = new Blob([`﻿${[head.join(','), ...lines].join('\n')}`], { type: 'text/csv;charset=utf-8' })
    const a = document.createElement('a')

    a.href = URL.createObjectURL(blob)
    a.download = `أرصدة-المقاولين-${new Date().toISOString().slice(0, 10)}.csv`
    a.click()
    URL.revokeObjectURL(a.href)
  }
  finally {
    exporting.value = false
  }
}
</script>

<template>
  <div>
    <div class="d-flex align-center justify-space-between flex-wrap gap-4 mb-6">
      <div>
        <h1 class="text-h4 font-weight-bold">أرصدة المقاولين</h1>
        <p class="text-body-2 text-medium-emphasis mb-0">
          الصافي = ما للشركة عند الاتحاد − ما عليها (ذمم + غرامات). السالب يعني أن الشركة مطلوب منها للاتحاد.
        </p>
      </div>
      <VBtn
        variant="tonal"
        color="success"
        prepend-icon="tabler-file-spreadsheet"
        :loading="exporting"
        @click="exportCsv"
      >
        تصدير إكسل
      </VBtn>
    </div>

    <!-- ─── الإجماليات ─── -->
    <VRow class="mb-2">
      <VCol cols="12" md="4">
        <VCard>
          <VCardText>
            <p class="text-body-2 text-medium-emphasis mb-1">
              إجمالي ما على الشركات ({{ summary?.items?.debit_count ?? '—' }} شركة)
            </p>
            <h3 class="text-h5 text-error" dir="ltr">{{ money(summary?.items?.debit_total_jod) }} د.أ</h3>
          </VCardText>
        </VCard>
      </VCol>
      <VCol cols="12" md="4">
        <VCard>
          <VCardText>
            <p class="text-body-2 text-medium-emphasis mb-1">
              إجمالي ما للشركات ({{ summary?.items?.credit_count ?? '—' }} شركة)
            </p>
            <h3 class="text-h5 text-success" dir="ltr">{{ money(summary?.items?.credit_total_jod) }} د.أ</h3>
          </VCardText>
        </VCard>
      </VCol>
      <VCol cols="12" md="4">
        <VCard>
          <VCardText>
            <p class="text-body-2 text-medium-emphasis mb-1">الصافي</p>
            <h3 class="text-h5" :class="netColor(summary?.items?.net_total_jod ?? 0)" dir="ltr">
              {{ money(summary?.items?.net_total_jod) }} د.أ
            </h3>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <VCard>
      <VCardText class="d-flex gap-4 flex-wrap align-center">
        <VTextField
          v-model="search"
          placeholder="بحث باسم الشركة أو رقم العضوية..."
          prepend-inner-icon="tabler-search"
          density="compact"
          style="max-width: 320px;"
        />
        <!-- شرائح بتلف لسطر جديد بدل أزرار متلاصقة كانت تنضغط وتتداخل نصوصها -->
        <VChipGroup v-model="filter" mandatory column selected-class="text-primary">
          <VChip v-for="f in filters" :key="f.value" :value="f.value" variant="outlined" filter>
            {{ f.title }}
          </VChip>
        </VChipGroup>
        <VSelect
          v-model="sort"
          :items="[{ value: 'asc', title: 'الأكثر مديونية أولاً' }, { value: 'desc', title: 'الأكبر رصيداً أولاً' }]"
          density="compact"
          hide-details
          style="max-width: 220px;"
        />
      </VCardText>

      <VProgressLinear v-if="isLoading" indeterminate color="primary" />

      <div class="overflow-x-auto">
        <VTable>
          <thead>
            <tr>
              <th>الشركة</th>
              <th>رقم العضوية</th>
              <th>حالة العضوية</th>
              <th>له (د.أ)</th>
              <th>ذمم (د.أ)</th>
              <th>غرامات (د.أ)</th>
              <th>الصافي (د.أ)</th>
              <th />
            </tr>
          </thead>
          <tbody>
            <tr v-for="r in (data?.items ?? []) as BalanceRow[]" :key="r.contractor_id">
              <td>{{ r.name }}</td>
              <td>{{ r.membership_number }}</td>
              <td>
                <VChip size="small" :color="statusLabels[r.status]?.color ?? 'default'" label>
                  {{ statusLabels[r.status]?.text ?? r.status }}
                </VChip>
              </td>
              <td dir="ltr" class="text-success">{{ money(r.credit_jod) }}</td>
              <td dir="ltr">{{ money(r.dues_jod) }}</td>
              <td dir="ltr">{{ money(r.penalties_jod) }}</td>
              <td dir="ltr" class="font-weight-bold" :class="netColor(r.net_jod)">{{ money(r.net_jod) }}</td>
              <td>
                <VBtn size="small" variant="text" prepend-icon="tabler-list-details" @click="openStatement(r)">
                  سجل المدفوعات
                </VBtn>
              </td>
            </tr>
            <tr v-if="!isLoading && !(data?.items ?? []).length">
              <td colspan="8" class="text-center text-medium-emphasis py-6">
                لا توجد نتائج
              </td>
            </tr>
          </tbody>
        </VTable>
      </div>

      <VCardText class="d-flex justify-center">
        <VPagination v-model="page" :length="data?.meta?.last_page ?? 1" :total-visible="$vuetify.display.xs ? 5 : 7" />
      </VCardText>
    </VCard>

    <VDialog v-model="statementOpen" max-width="1100" scrollable>
      <VCard>
        <VCardTitle class="d-flex align-center justify-space-between flex-wrap gap-2 pa-4">
          <span>سجل مدفوعات {{ statementFor?.name }} ({{ statementFor?.membership_number }})</span>
          <VBtn icon="tabler-x" variant="text" size="small" @click="statementOpen = false" />
        </VCardTitle>
        <VDivider />
        <VProgressLinear v-if="statementLoading" indeterminate color="primary" />
        <VCardText v-if="statement?.items">
          <VRow class="mb-2">
            <VCol cols="6" md="3">
              <p class="text-body-2 text-medium-emphasis mb-1">إجمالي الذمم والغرامات</p>
              <h4 class="text-h6" dir="ltr">{{ money(statement.items.summary.total_dues_jod + statement.items.summary.total_penalties_jod) }}</h4>
            </VCol>
            <VCol cols="6" md="3">
              <p class="text-body-2 text-medium-emphasis mb-1">إجمالي المدفوع</p>
              <h4 class="text-h6 text-success" dir="ltr">{{ money(statement.items.summary.total_paid_jod) }}</h4>
            </VCol>
            <VCol cols="6" md="3">
              <p class="text-body-2 text-medium-emphasis mb-1">المتبقي المطلوب</p>
              <h4 class="text-h6 text-error" dir="ltr">{{ money(statement.items.summary.amount_due_jod) }}</h4>
            </VCol>
            <VCol cols="6" md="3">
              <p class="text-body-2 text-medium-emphasis mb-1">الصافي</p>
              <h4 class="text-h6" :class="netColor(statement.items.summary.net_jod)" dir="ltr">{{ money(statement.items.summary.net_jod) }}</h4>
            </VCol>
          </VRow>
          <div class="overflow-x-auto">
            <VTable density="compact">
              <thead>
                <tr>
                  <th>التاريخ</th>
                  <th>النوع</th>
                  <th>البيان</th>
                  <th>الرقم المرجعي</th>
                  <th>عليه</th>
                  <th>له</th>
                  <th>الحالة</th>
                  <th>الرصيد بعدها</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="e in statement.items.entries as StatementEntry[]" :key="`${e.kind}-${e.id}`" :class="{ 'text-disabled': !e.counts_in_balance }">
                  <td dir="ltr">{{ e.date }}</td>
                  <td>{{ kindLabels[e.kind] ?? e.kind }}</td>
                  <td>
                    {{ e.title }}
                    <div v-if="e.allocations?.length" class="text-caption text-medium-emphasis">
                      انصرفت على: {{ e.allocations.map(a => `${a.title} (${money(a.amount_jod)})`).join('، ') }}
                    </div>
                    <div v-if="e.notes" class="text-caption text-medium-emphasis">{{ e.notes }}</div>
                  </td>
                  <td dir="ltr">
                    {{ e.reference_number ?? '—' }}
                    <div v-if="e.bank_reference_number" class="text-caption text-medium-emphasis">{{ e.bank_reference_number }}</div>
                  </td>
                  <td dir="ltr" class="text-error">{{ e.direction === 'debit' ? money(e.amount_jod) : '' }}</td>
                  <td dir="ltr" class="text-success">{{ e.direction === 'credit' ? money(e.amount_jod) : '' }}</td>
                  <td>{{ e.status_label }}</td>
                  <td dir="ltr" class="font-weight-bold" :class="netColor(e.balance_after_jod)">{{ money(e.balance_after_jod) }}</td>
                </tr>
                <tr v-if="!statement.items.entries.length">
                  <td colspan="8" class="text-center text-medium-emphasis py-6">لا توجد حركات</td>
                </tr>
              </tbody>
            </VTable>
          </div>
        </VCardText>
      </VCard>
    </VDialog>
  </div>
</template>

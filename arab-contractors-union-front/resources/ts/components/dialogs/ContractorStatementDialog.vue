<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useQuery } from '@tanstack/vue-query'
import api from '@/plugins/axios'

/**
 * سجل المدفوعات التفصيلي لشركة (كشف حساب): الذمم والغرامات والدفعات والأرصدة، مع
 * وقت وصول كل حوالة والرصيد بعد كل حركة. البيانات من GET dashboard/balances/{id}/statement.
 */
interface Allocation {
  kind: 'due' | 'penalty'
  id: number
  title: string
  reference_number: string | null
  amount_jod: number
}

interface StatementEntry {
  kind: 'due' | 'penalty' | 'payment' | 'credit' | 'settlement'
  id: number
  date: string | null
  datetime: string | null
  time: string | null
  title: string
  reference_number: string | null
  bank_reference_number?: string | null
  direction: 'debit' | 'credit'
  amount_jod: number
  status: string
  status_label: string
  counts_in_balance: boolean
  balance_after_jod: number
  type_label?: string
  notes?: string | null
  received_at?: string | null
  received_time?: string | null
  confirmed_at?: string | null
  allocations?: Allocation[]
}

interface Summary {
  total_dues_jod: number
  total_penalties_jod: number
  total_obligations_jod?: number
  total_paid_jod: number
  other_payments_jod?: number
  pending_payments_jod: number
  credit_jod: number
  amount_due_jod: number
  net_jod: number
  position: 'credit' | 'owes' | 'settled'
}

const props = defineProps<{
  contractor: { contractor_id: number; name: string; membership_number: string } | null
}>()

const open = defineModel<boolean>({ default: false })

const contractorId = computed(() => props.contractor?.contractor_id)

const { data, isFetching } = useQuery({
  queryKey: ['contractor-statement', contractorId],
  queryFn: async () => (await api.get(`/api/v1/dashboard/balances/${contractorId.value}/statement`)).data,
  enabled: computed(() => open.value && !!contractorId.value),
})

const summary = computed<Summary | null>(() => data.value?.items?.summary ?? null)
const entries = computed<StatementEntry[]>(() => data.value?.items?.entries ?? [])

const kinds: Record<StatementEntry['kind'], { label: string; icon: string; color: string }> = {
  payment: { label: 'دفعة', icon: 'tabler-cash', color: 'success' },
  due: { label: 'ذمة', icon: 'tabler-file-invoice', color: 'primary' },
  penalty: { label: 'غرامة', icon: 'tabler-alert-triangle', color: 'warning' },
  credit: { label: 'رصيد دائن', icon: 'tabler-wallet', color: 'info' },
  settlement: { label: 'تسديد سابق', icon: 'tabler-history', color: 'secondary' },
}

const statusColors: Record<string, string> = {
  paid: 'success',
  partially_paid: 'warning',
  unpaid: 'error',
  pending: 'info',
  rejected: 'error',
  refunded: 'secondary',
  failed: 'error',
}

const filters = [
  { value: 'all', title: 'كل الحركات' },
  { value: 'payment', title: 'الدفعات' },
  { value: 'due', title: 'الذمم' },
  { value: 'penalty', title: 'الغرامات' },
]

const filter = ref('all')

watch(contractorId, () => filter.value = 'all')

const visibleEntries = computed(() => filter.value === 'all'
  ? entries.value
  : entries.value.filter(e => e.kind === filter.value))

const counts = computed(() => ({
  all: entries.value.length,
  payment: entries.value.filter(e => e.kind === 'payment').length,
  due: entries.value.filter(e => e.kind === 'due').length,
  penalty: entries.value.filter(e => e.kind === 'penalty').length,
}) as Record<string, number>)

const tiles = computed(() => {
  const s = summary.value
  if (!s)
    return []

  // "المدفوع" = اللي انحسب له على الذمم والغرامات بس، فـ"الذمم − المدفوع" = المتبقي.
  // رسوم العضوية/باقات المعدات بتطلع كملاحظة تحت الرقم، مش جوّاه.
  const hints = [
    s.other_payments_jod ? `+ ${money(s.other_payments_jod)} رسوم عضوية/أخرى` : null,
    s.pending_payments_jod > 0 ? `+ ${money(s.pending_payments_jod)} قيد المراجعة` : null,
  ].filter(Boolean).join(' · ')

  return [
    { title: 'إجمالي الذمم والغرامات', value: s.total_obligations_jod ?? s.total_dues_jod + s.total_penalties_jod, icon: 'tabler-file-invoice', color: 'primary' },
    { title: 'إجمالي المدفوع', value: s.total_paid_jod, icon: 'tabler-cash', color: 'success', hint: hints || null },
    { title: 'الرصيد الدائن', value: s.credit_jod, icon: 'tabler-wallet', color: 'info' },
    { title: 'المتبقي المطلوب', value: s.amount_due_jod, icon: 'tabler-receipt-2', color: s.amount_due_jod > 0 ? 'error' : 'success' },
  ]
})

const position = computed(() => {
  const s = summary.value
  if (!s)
    return null

  return {
    owes: { text: 'عليه للاتحاد', color: 'error' },
    credit: { text: 'له رصيد', color: 'success' },
    settled: { text: 'متوازن', color: 'secondary' },
  }[s.position]
})

function money(v: number | undefined | null) {
  if (v === undefined || v === null)
    return '—'

  return Number(v).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

function balanceClass(v: number) {
  return v < 0 ? 'text-error' : v > 0 ? 'text-success' : 'text-medium-emphasis'
}

// الساعة:الدقيقة بتوقيت غزة من التاريخ الكامل (أو الجاهزة من السيرفر)
function clock(iso: string | null | undefined, fallback?: string | null) {
  if (!iso)
    return fallback ?? null

  return new Date(iso).toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit', timeZone: 'Asia/Gaza' })
}

function exportCsv() {
  const head = ['التاريخ', 'الوقت', 'النوع', 'البيان', 'الرقم المرجعي', 'رقم الحوالة البنكي', 'عليه', 'له', 'الحالة', 'الرصيد بعدها']

  const lines = entries.value.map(e => [
    e.date,
    e.kind === 'payment' ? clock(e.received_at, e.received_time) : clock(e.datetime, e.time),
    kinds[e.kind]?.label ?? e.kind,
    e.title,
    e.reference_number,
    e.bank_reference_number,
    e.direction === 'debit' ? e.amount_jod : '',
    e.direction === 'credit' ? e.amount_jod : '',
    e.status_label,
    e.balance_after_jod,
  ].map(v => `"${String(v ?? '').replace(/"/g, '""')}"`).join(','))

  const blob = new Blob([`${String.fromCharCode(0xFEFF)}${[head.join(','), ...lines].join('\n')}`], { type: 'text/csv;charset=utf-8' })
  const a = document.createElement('a')

  a.href = URL.createObjectURL(blob)
  a.download = `سجل-مدفوعات-${props.contractor?.membership_number ?? ''}.csv`
  a.click()
  URL.revokeObjectURL(a.href)
}
</script>

<template>
  <VDialog
    v-model="open"
    max-width="1200"
    scrollable
    :fullscreen="$vuetify.display.xs"
  >
    <VCard class="statement-card">
      <!-- ─── الرأس ─── -->
      <div class="statement-header d-flex align-center gap-4 pa-5">
        <VAvatar
          color="primary"
          variant="tonal"
          size="48"
          rounded
        >
          <VIcon
            icon="tabler-report-money"
            size="28"
          />
        </VAvatar>
        <div class="flex-grow-1">
          <div class="text-overline text-medium-emphasis">
            سجل المدفوعات
          </div>
          <h4 class="text-h5 font-weight-bold mb-1">
            {{ contractor?.name }}
          </h4>
          <div class="d-flex align-center flex-wrap gap-2">
            <VChip
              size="small"
              label
              variant="outlined"
              prepend-icon="tabler-id-badge-2"
            >
              <bdi dir="ltr">{{ contractor?.membership_number }}</bdi>
            </VChip>
            <VChip
              v-if="position"
              size="small"
              label
              :color="position.color"
            >
              {{ position.text }}&nbsp;
              <span
                class="font-weight-bold"
                dir="ltr"
              >{{ money(summary?.net_jod) }}</span>
            </VChip>
          </div>
        </div>
        <VBtn
          variant="tonal"
          color="success"
          size="small"
          prepend-icon="tabler-file-spreadsheet"
          :disabled="!entries.length"
          @click="exportCsv"
        >
          تصدير
        </VBtn>
        <VBtn
          icon="tabler-x"
          variant="text"
          size="small"
          @click="open = false"
        />
      </div>

      <VDivider />
      <VProgressLinear
        v-if="isFetching"
        indeterminate
        color="primary"
      />

      <VCardText
        v-if="summary"
        class="pa-5"
      >
        <!-- ─── بطاقات الملخص ─── -->
        <VRow class="mb-4">
          <VCol
            v-for="t in tiles"
            :key="t.title"
            cols="6"
            md="3"
          >
            <div class="summary-tile d-flex align-center gap-3 pa-3 pa-sm-4 rounded-lg h-100">
              <VAvatar
                class="d-none d-sm-flex"
                :color="t.color"
                variant="tonal"
                rounded
                size="42"
              >
                <VIcon :icon="t.icon" />
              </VAvatar>
              <div class="min-w-0">
                <div class="text-body-2 text-medium-emphasis">
                  {{ t.title }}
                </div>
                <div
                  class="text-h6 font-weight-bold"
                  :class="`text-${t.color}`"
                  dir="ltr"
                  style="text-align: end;"
                >
                  {{ money(t.value) }} <span class="text-caption">د.أ</span>
                </div>
                <div
                  v-if="t.hint"
                  class="text-caption text-info"
                >
                  {{ t.hint }}
                </div>
              </div>
            </div>
          </VCol>
        </VRow>

        <!-- ─── فلتر الحركات ─── -->
        <VChipGroup
          v-model="filter"
          mandatory
          selected-class="text-primary"
          class="mb-3"
        >
          <VChip
            v-for="f in filters"
            :key="f.value"
            :value="f.value"
            variant="outlined"
            filter
          >
            {{ f.title }}
            <span class="ms-1 text-medium-emphasis">({{ counts[f.value] }})</span>
          </VChip>
        </VChipGroup>

        <!-- ─── الجدول ─── -->
        <div class="statement-table-wrap rounded-lg">
          <VTable
            density="comfortable"
            fixed-header
            height="460"
            class="statement-table"
          >
            <thead>
              <tr>
                <th>التاريخ والوقت</th>
                <th>الحركة</th>
                <th>البيان</th>
                <th>الرقم المرجعي</th>
                <th class="text-end">
                  عليه
                </th>
                <th class="text-end">
                  له
                </th>
                <th>الحالة</th>
                <th class="text-end">
                  الرصيد بعدها
                </th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="e in visibleEntries"
                :key="`${e.kind}-${e.id}`"
                :class="{ 'row-muted': !e.counts_in_balance }"
              >
                <td class="text-no-wrap">
                  <div
                    class="font-weight-medium"
                    dir="ltr"
                    style="text-align: end;"
                  >
                    {{ e.date }}
                  </div>
                  <div
                    v-if="e.kind === 'payment' && (e.received_at || e.received_time)"
                    class="text-caption text-medium-emphasis d-flex align-center gap-1"
                  >
                    <VIcon
                      icon="tabler-clock"
                      size="14"
                    />
                    وصلت
                    <span dir="ltr">{{ clock(e.received_at, e.received_time) }}</span>
                  </div>
                  <div
                    v-else-if="e.datetime || e.time"
                    class="text-caption text-medium-emphasis d-flex align-center gap-1"
                  >
                    <VIcon
                      icon="tabler-clock"
                      size="14"
                    />
                    <span dir="ltr">{{ clock(e.datetime, e.time) }}</span>
                  </div>
                  <div
                    v-if="e.kind === 'payment' && e.confirmed_at && e.status === 'paid'"
                    class="text-caption text-success d-flex align-center gap-1"
                  >
                    <VIcon
                      icon="tabler-circle-check"
                      size="14"
                    />
                    اعتُمدت
                    <span dir="ltr">{{ clock(e.confirmed_at) }}</span>
                  </div>
                </td>
                <td>
                  <VChip
                    size="small"
                    label
                    variant="tonal"
                    :color="kinds[e.kind]?.color"
                    :prepend-icon="kinds[e.kind]?.icon"
                  >
                    {{ kinds[e.kind]?.label ?? e.kind }}
                  </VChip>
                </td>
                <td style="min-width: 220px;">
                  <div class="font-weight-medium">
                    {{ e.title }}
                  </div>
                  <div
                    v-if="e.type_label && e.type_label !== e.title"
                    class="text-caption text-medium-emphasis"
                  >
                    {{ e.type_label }}
                  </div>
                  <div
                    v-if="e.allocations?.length"
                    class="d-flex flex-wrap gap-1 mt-1"
                  >
                    <VChip
                      v-for="a in e.allocations"
                      :key="`${a.kind}-${a.id}`"
                      size="x-small"
                      variant="outlined"
                      :color="a.kind === 'penalty' ? 'warning' : 'primary'"
                    >
                      {{ a.title }}:&nbsp;<span dir="ltr">{{ money(a.amount_jod) }}</span>
                    </VChip>
                  </div>
                  <div
                    v-if="e.notes"
                    class="text-caption text-medium-emphasis mt-1 d-flex align-center gap-1"
                  >
                    <VIcon
                      icon="tabler-note"
                      size="14"
                    />
                    {{ e.notes }}
                  </div>
                </td>
                <td class="text-no-wrap">
                  <div
                    class="ref-code"
                    dir="ltr"
                  >
                    {{ e.reference_number ?? '—' }}
                  </div>
                  <div
                    v-if="e.bank_reference_number"
                    class="text-caption text-medium-emphasis"
                    dir="ltr"
                  >
                    {{ e.bank_reference_number }}
                  </div>
                </td>
                <td
                  class="text-end text-error font-weight-medium text-no-wrap"
                  dir="ltr"
                >
                  {{ e.direction === 'debit' ? money(e.amount_jod) : '' }}
                </td>
                <td
                  class="text-end text-success font-weight-medium text-no-wrap"
                  dir="ltr"
                >
                  {{ e.direction === 'credit' ? money(e.amount_jod) : '' }}
                </td>
                <td>
                  <VChip
                    size="small"
                    variant="tonal"
                    :color="statusColors[e.status] ?? 'default'"
                  >
                    {{ e.status_label }}
                  </VChip>
                </td>
                <td
                  class="text-end font-weight-bold text-no-wrap"
                  :class="balanceClass(e.balance_after_jod)"
                  dir="ltr"
                >
                  {{ money(e.balance_after_jod) }}
                </td>
              </tr>
              <tr v-if="!visibleEntries.length">
                <td
                  colspan="8"
                  class="text-center text-medium-emphasis py-10"
                >
                  <VIcon
                    icon="tabler-receipt-off"
                    size="32"
                    class="mb-2"
                  />
                  <div>لا توجد حركات</div>
                </td>
              </tr>
            </tbody>
          </VTable>
        </div>

        <div class="text-caption text-medium-emphasis mt-3 d-flex align-center gap-1">
          <VIcon
            icon="tabler-info-circle"
            size="14"
          />
          الحركات الباهتة (قيد المراجعة أو مرفوضة) ما بتأثر على الرصيد. الأحدث أولاً.
        </div>
      </VCardText>
    </VCard>
  </VDialog>
</template>

<style scoped>
.statement-header {
  background: linear-gradient(135deg, rgba(var(--v-theme-primary), 0.08), transparent 70%);
}

.summary-tile {
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  background: rgb(var(--v-theme-surface));
}

.statement-table-wrap {
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  overflow: hidden;
}

.statement-table :deep(th) {
  font-weight: 600 !important;
  white-space: nowrap;
  background: rgba(var(--v-theme-on-surface), 0.04) !important;
}

/* خطوط شبكية: فاصل عمودي بين الأعمدة وأفقي بين الصفوف */
.statement-table :deep(th),
.statement-table :deep(td) {
  border-block-end: thin solid rgba(var(--v-border-color), var(--v-border-opacity)) !important;
  border-inline-end: thin solid rgba(var(--v-border-color), var(--v-border-opacity));
}

.statement-table :deep(th:last-child),
.statement-table :deep(td:last-child) {
  border-inline-end: none;
}

.statement-table :deep(tbody tr:hover) {
  background: rgba(var(--v-theme-primary), 0.04);
}

.row-muted {
  opacity: 0.55;
}

.ref-code {
  font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
  font-size: 0.85rem;
  text-align: end;
}

.min-w-0 {
  min-inline-size: 0;
}
</style>

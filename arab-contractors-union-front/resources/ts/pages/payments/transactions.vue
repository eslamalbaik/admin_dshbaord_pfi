<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { useQuery, useMutation, useQueryClient, keepPreviousData } from '@tanstack/vue-query'
import { useRoute, useRouter } from 'vue-router'
import { useDisplay } from 'vuetify'
import api from '@/plugins/axios'

definePage({ meta: { requiresAdmin: true } })

const queryClient = useQueryClient()

interface Payment {
  id: number
  transaction_number: string | null
  contractor: string | null
  contractor_id: number
  contractor_membership_number?: string | null
  contractor_deleted?: boolean
  amount: string
  currency: string
  exchange_rate: string | null
  amount_jod: string | null
  type: string
  status: string
  method: string | null
  reference_number: string | null
  receipt_image_url: string | null
  rejection_reason: string | null
  notes: string | null
  last_status_change?: {
    from_status: string
    to_status: string
    reason: string
    changed_by: string | null
    changed_at: string
  } | null
  status_change_blocker?: string | null
  submitted_at: string | null
  confirmed_at: string | null
  created_at: string
}

// VFileInput (بدون multiple) يرجّع File واحد مش مصفوفة — نطبّع القيمتين
function firstFile(v: File | File[] | null | undefined): File | null {
  return (Array.isArray(v) ? v[0] : v) ?? null
}

function fmtDateTime(d: string | null) {
  return d ? new Date(d).toLocaleString('ar-EG', { dateStyle: 'short', timeStyle: 'short' }) : '—'
}

// ─── عرض إيصال الدفعة داخل الموقع (popup) بدل فتحه بتبويب جديد ───
const receiptViewer = ref<{ url: string; title: string } | null>(null)
const receiptViewerOpen = ref(false)
const receiptIsPdf = computed(() => isPdf(receiptViewer.value?.url ?? null))

function openReceipt(p: Payment) {
  if (!p.receipt_image_url)
    return
  receiptViewer.value = {
    url: p.receipt_image_url,
    title: [p.contractor, p.transaction_number].filter(Boolean).join(' — '),
  }
  receiptViewerOpen.value = true
}

// صفحة أرصدة المقاولين تفتح سجل المدفوعات (كشف الحساب) لهذا المقاول مباشرة
function balanceLink(p: Payment) {
  return {
    path: '/balances',
    query: {
      contractor_id: String(p.contractor_id),
      name: p.contractor ?? undefined,
      membership_number: p.contractor_membership_number ?? undefined,
    },
  }
}

const successMessage = ref('')
const errorMessage = ref('')

function flash(msg: string, isError = false) {
  if (isError) {
    errorMessage.value = msg
  }
  else {
    successMessage.value = msg
    errorMessage.value = ''
    setTimeout(() => successMessage.value = '', 4000)
  }
}

const search = ref('')
const statusFilter = ref('')
const page = ref(1)

// فتح الصفحة من إشعار (‎?id=‎) يعرض تلك الدفعة وحدها حتى يلغي المستخدم الفلتر
const route = useRoute()
const router = useRouter()
const paymentIdFilter = ref<number | null>(null)
watch(() => route.query.id, (id) => {
  const n = Number(id)
  paymentIdFilter.value = Number.isInteger(n) && n > 0 ? n : null
  page.value = 1
}, { immediate: true })
const clearPaymentIdFilter = () => {
  const { id: _id, ...rest } = route.query
  router.replace({ query: rest })
}

watch([search, statusFilter], () => page.value = 1)

const { data, isLoading, isFetching } = useQuery({
  queryKey: ['payments-transactions', search, statusFilter, page, paymentIdFilter],
  placeholderData: keepPreviousData,
  queryFn: async () => (await api.get('/api/v1/payments/transactions', {
    params: {
      id: paymentIdFilter.value || undefined,
      search: search.value || undefined,
      status: statusFilter.value || undefined,
      page: page.value,
    },
  })).data,
})

// أسعار الصرف المعتمدة (لاقتراح السعر في dialog التأكيد)
const { data: rates } = useQuery({
  queryKey: ['exchange-rates'],
  queryFn: async () => (await api.get('/api/v1/dashboard/exchange-rates')).data,
})

const statusColor: Record<string, string> = {
  pending: 'warning', paid: 'success', rejected: 'error', refunded: 'info', failed: 'error',
}

const statusLabel: Record<string, string> = {
  pending: 'قيد المراجعة', paid: 'مؤكّدة', rejected: 'مرفوضة', refunded: 'مُعادة', failed: 'فاشلة',
}

// ─── تغيير الحالة (من قائمة الثلاث نقاط) — السبب إجباري وبينحفظ مع مين غيّر ───
const statusDialog = ref(false)
const changing = ref<Payment | null>(null)
const targetStatus = ref<string | null>(null)
const changeReason = ref('')
const confirmRate = ref('')
const confirmReceiptFile = ref<File | File[] | null>(null)
const confirmReceiptPreview = ref('')

const statusTargets = computed(() => [
  { title: 'مؤكّدة', value: 'paid' },
  { title: 'قيد المراجعة', value: 'pending' },
  { title: 'مرفوضة', value: 'rejected' },
].filter(o => o.value !== changing.value?.status))

function openStatusChange(p: Payment) {
  changing.value = p
  targetStatus.value = null
  changeReason.value = ''
  confirmRate.value = p.currency !== 'JOD'
    ? String(rates.value?.items?.latest?.[p.currency]?.rate_to_jod ?? '')
    : ''
  confirmReceiptFile.value = null
  confirmReceiptPreview.value = ''
  statusDialog.value = true
}

watch(confirmReceiptFile, files => {
  if (confirmReceiptPreview.value)
    URL.revokeObjectURL(confirmReceiptPreview.value)

  const file = firstFile(files)

  confirmReceiptPreview.value = file && file.type.startsWith('image/') ? URL.createObjectURL(file) : ''
})

const jodEquivalent = computed(() => {
  if (!changing.value) return null
  if (changing.value.currency === 'JOD') return Number(changing.value.amount)
  const rate = Number(confirmRate.value)
  if (!rate) return null
  return Math.round(Number(changing.value.amount) * rate * 100) / 100
})

const canSubmitStatus = computed(() =>
  !!changing.value
  && !changing.value.status_change_blocker
  && !!targetStatus.value
  && !!changeReason.value.trim()
  && (targetStatus.value !== 'paid' || changing.value.currency === 'JOD' || Number(confirmRate.value) > 0),
)

const statusMutation = useMutation({
  mutationFn: async () => {
    const form = new FormData()

    form.append('status', targetStatus.value!)
    form.append('reason', changeReason.value.trim())
    if (targetStatus.value === 'paid') {
      if (changing.value!.currency !== 'JOD' && confirmRate.value)
        form.append('exchange_rate', confirmRate.value)
      const receipt = firstFile(confirmReceiptFile.value)
      if (receipt)
        form.append('receipt_image', receipt)
    }

    return (await api.post(`/api/v1/payments/transactions/${changing.value!.id}/status`, form, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })).data
  },
  onSuccess: () => {
    queryClient.invalidateQueries({ queryKey: ['payments-transactions'] })
    statusDialog.value = false
    flash(`تم تغيير حالة الدفعة إلى "${statusLabel[targetStatus.value!]}".`)
  },
  onError: (e: any) => {
    const errors = e?.response?.data?.errors
    const first = errors ? Object.values(errors).flat()[0] : null

    flash((first as string) || e?.response?.data?.message || 'فشل تغيير حالة الدفعة.', true)
  },
})

// ─── إضافة دفعة يدوياً (نيابةً عن المقاول) — تُسجَّل مؤكَّدة وتوزَّع على أقدم الذمم ───
const addDialog = ref(false)

const emptyNewPayment = () => ({
  contractor_id: null as number | null,
  amount: '',
  currency: 'JOD',
  exchange_rate: '',
  method: 'bank_transfer',
  reference_number: '',
  notes: '',
})

const newPayment = ref(emptyNewPayment())
const newReceiptFile = ref<File | File[] | null>(null)
const newReceiptPreview = ref('')

// اختيار المقاول بالبحث أثناء الكتابة (نفس نمط شاشة الغرامات)
const contractorSearch = ref('')
const contractorOptions = ref<{ id: number; name: string; membership_number: string }[]>([])
let contractorTimer: ReturnType<typeof setTimeout> | null = null
const justSelectedContractor = ref(false)

watch(() => newPayment.value.contractor_id, () => {
  justSelectedContractor.value = true
})

watch(contractorSearch, q => {
  if (justSelectedContractor.value) {
    justSelectedContractor.value = false

    return
  }
  if (contractorTimer)
    clearTimeout(contractorTimer)
  contractorTimer = setTimeout(async () => {
    if (!q || q.length < 2)
      return
    try {
      const r = await api.get('/api/v1/contractors', { params: { search: q, per_page: 10 } })

      contractorOptions.value = (r.data.data ?? r.data.items ?? []).map((c: any) => ({
        id: c.id,
        name: c.name,
        membership_number: c.membership_number,
      }))
    }
    catch {}
  }, 350)
})

function openAdd() {
  newPayment.value = emptyNewPayment()
  newReceiptFile.value = null
  contractorSearch.value = ''
  contractorOptions.value = []
  addDialog.value = true
}

// اقتراح سعر الصرف المعتمد عند تغيير العملة
watch(() => newPayment.value.currency, cur => {
  newPayment.value.exchange_rate = cur !== 'JOD'
    ? String(rates.value?.items?.latest?.[cur]?.rate_to_jod ?? '')
    : ''
})

watch(newReceiptFile, files => {
  if (newReceiptPreview.value)
    URL.revokeObjectURL(newReceiptPreview.value)

  const file = firstFile(files)

  newReceiptPreview.value = file && file.type.startsWith('image/') ? URL.createObjectURL(file) : ''
})

const newJodEquivalent = computed(() => {
  const amount = Number(newPayment.value.amount)
  if (!amount)
    return null
  if (newPayment.value.currency === 'JOD')
    return amount
  const rate = Number(newPayment.value.exchange_rate)
  if (!rate)
    return null

  return Math.round(amount * rate * 100) / 100
})

const canSubmitNew = computed(() =>
  !!newPayment.value.contractor_id
  && Number(newPayment.value.amount) > 0
  && (newPayment.value.currency === 'JOD' || Number(newPayment.value.exchange_rate) > 0)
  && (newPayment.value.method !== 'bank_transfer' || !!firstFile(newReceiptFile.value)),
)

const addMutation = useMutation({
  mutationFn: async () => {
    const p = newPayment.value
    const form = new FormData()

    form.append('contractor_id', String(p.contractor_id))
    form.append('amount', p.amount)
    form.append('currency', p.currency)
    form.append('method', p.method)
    if (p.currency !== 'JOD' && p.exchange_rate)
      form.append('exchange_rate', p.exchange_rate)
    if (p.reference_number)
      form.append('reference_number', p.reference_number)
    if (p.notes)
      form.append('notes', p.notes)
    const receipt = firstFile(newReceiptFile.value)
    if (receipt)
      form.append('receipt_image', receipt)

    return (await api.post('/api/v1/payments/transactions/manual', form, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })).data
  },
  onSuccess: (res: any) => {
    queryClient.invalidateQueries({ queryKey: ['payments-transactions'] })
    addDialog.value = false

    const applied = (res?.items?.applied ?? []).length
    const unapplied = Number(res?.items?.unapplied_jod ?? 0)

    flash(`تمت إضافة الدفعة وتسديد ${applied} ذمة${unapplied > 0 ? ` — وبقي ${unapplied} د.أ رصيداً للمقاول` : ''}.`)
  },
  onError: (e: any) => {
    const errors = e?.response?.data?.errors
    const first = errors ? Object.values(errors).flat()[0] : null

    flash((first as string) || e?.response?.data?.message || 'فشل إضافة الدفعة.', true)
  },
})

const currencySymbol: Record<string, string> = { JOD: 'د.أ', ILS: '₪', USD: '$' }

function fmtDate(d: string | null) {
  return d ? new Date(d).toLocaleDateString('ar-EG') : '—'
}

// ─── عرض الصفحة: ملخص، تنسيقات، موبايل ───
const display = useDisplay()
const isMobile = computed(() => display.smAndDown.value)
const items = computed<Payment[]>(() => data.value?.items ?? [])

const typeLabel: Record<string, string> = {
  membership_fee: 'رسوم عضوية',
  renewal_fee: 'رسوم تجديد',
  dues_payment: 'سداد ذمم',
  penalty: 'غرامة',
  penalty_payment: 'دفع غرامة',
  advance_payment: 'دفعة مقدمة',
  equipment_subscription: 'اشتراك سوق الآليات',
}

const statusIcon: Record<string, string> = {
  pending: 'tabler-clock', paid: 'tabler-circle-check', rejected: 'tabler-circle-x', refunded: 'tabler-arrow-back-up', failed: 'tabler-alert-circle',
}

// الملخص بيرجع من السيرفر حسب البحث الحالي (بدون فلتر الحالة) — بطاقة لكل حالة
const summaryCards = computed(() => {
  const sum = (data.value?.summary ?? {}) as Record<string, { count: number; total_jod: number }>

  const card = (value: string, title: string, color: string, icon: string) => {
    const rows = value ? [sum[value]] : Object.values(sum)

    return {
      value,
      title,
      color,
      icon,
      count: rows.reduce((n, r) => n + (r?.count ?? 0), 0),
      total: rows.reduce((n, r) => n + (r?.total_jod ?? 0), 0),
    }
  }

  return [
    card('', 'كل الدفعات', 'primary', 'tabler-receipt'),
    card('pending', 'قيد المراجعة', 'warning', 'tabler-clock'),
    card('paid', 'مؤكّدة', 'success', 'tabler-circle-check'),
    card('rejected', 'مرفوضة', 'error', 'tabler-circle-x'),
  ]
})

const hasFilters = computed(() => !!(search.value || statusFilter.value || paymentIdFilter.value))

function clearFilters() {
  search.value = ''
  statusFilter.value = ''
  if (paymentIdFilter.value)
    clearPaymentIdFilter()
}

const rangeLabel = computed(() => {
  const d = data.value
  if (!d?.total)
    return ''
  const from = (d.current_page - 1) * d.per_page + 1
  const to = Math.min(d.current_page * d.per_page, d.total)

  return `${from}–${to} من ${d.total}`
})

function fmtMoney(v: string | number | null | undefined) {
  const n = Number(v ?? 0)

  return n.toLocaleString('en-US', { minimumFractionDigits: n % 1 ? 2 : 0, maximumFractionDigits: 2 })
}

function fmtTime(d: string | null) {
  return d ? new Date(d).toLocaleTimeString('ar-EG', { hour: '2-digit', minute: '2-digit' }) : ''
}

function initial(name: string | null) {
  return (name ?? '؟').replace(/^(?:شركة|مؤسسة|مكتب)\s+/, '').trim().charAt(0) || '؟'
}

function isPdf(url: string | null) {
  return /\.pdf(?:$|\?)/i.test(url ?? '')
}
</script>

<template>
  <div class="payments-log">
    <div class="mb-6 d-flex align-center justify-space-between flex-wrap gap-4">
      <div>
        <h1 class="text-h4 font-weight-bold">سجل المدفوعات</h1>
        <p class="text-body-2 text-medium-emphasis mb-0">
          إشعارات التحويل الواردة من المقاولين — التأكيد يُثبّت سعر الصرف والمعادل بالدينار
        </p>
      </div>
      <VBtn v-if="$can('finance.payments', 'create')" color="primary" prepend-icon="tabler-plus" @click="openAdd">
        إضافة دفعة
      </VBtn>
    </div>

    <VAlert v-if="successMessage" type="success" variant="tonal" class="mb-4" closable @click:close="successMessage = ''">
      {{ successMessage }}
    </VAlert>
    <VAlert v-if="errorMessage" type="error" variant="tonal" class="mb-4" closable @click:close="errorMessage = ''">
      {{ errorMessage }}
    </VAlert>

    <!-- ─── ملخص الحالات (حسب البحث الحالي) — الضغط على البطاقة يفلتر الجدول ─── -->
    <VRow class="mb-2" dense>
      <VCol v-for="s in summaryCards" :key="s.value" cols="6" md="3">
        <VCard
          class="summary-card h-100"
          :class="{ 'summary-card--active': statusFilter === s.value }"
          :style="statusFilter === s.value ? `border-color: rgb(var(--v-theme-${s.color}))` : ''"
          variant="outlined"
          role="button"
          :aria-pressed="statusFilter === s.value"
          @click="statusFilter = s.value"
        >
          <VCardText class="d-flex align-center gap-3 pa-4">
            <VAvatar :color="s.color" variant="tonal" rounded size="42">
              <VIcon :icon="s.icon" size="24" />
            </VAvatar>
            <div class="min-w-0">
              <div class="text-body-2 text-medium-emphasis">{{ s.title }}</div>
              <div class="d-flex align-baseline gap-2 flex-wrap">
                <span class="text-h5 font-weight-bold">{{ s.count }}</span>
                <span class="text-caption text-medium-emphasis text-no-wrap" dir="ltr">{{ fmtMoney(s.total) }} د.أ</span>
              </div>
            </div>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <VCard>
      <VCardText class="d-flex gap-3 flex-wrap align-center">
        <VTextField
          v-model="search"
          placeholder="بحث باسم المقاول أو رقم العضوية أو الرقم المرجعي..."
          prepend-inner-icon="tabler-search"
          density="compact"
          clearable
          hide-details
          class="search-field"
        />
        <VChip
          v-if="statusFilter"
          :color="statusColor[statusFilter]"
          variant="tonal"
          closable
          @click:close="statusFilter = ''"
        >
          الحالة: {{ statusLabel[statusFilter] }}
        </VChip>
        <VChip
          v-if="paymentIdFilter"
          color="primary"
          variant="tonal"
          closable
          @click:close="clearPaymentIdFilter"
        >
          عرض الدفعة المرتبطة بالإشعار فقط
        </VChip>
        <VSpacer />
        <span v-if="data?.total" class="text-body-2 text-medium-emphasis">
          {{ rangeLabel }}
        </span>
      </VCardText>

      <VDivider />

      <VProgressLinear v-if="isFetching && !isLoading" indeterminate color="primary" height="2" />

      <!-- ─── تحميل ─── -->
      <div v-if="isLoading" class="pa-4">
        <VSkeletonLoader v-for="i in 6" :key="i" type="list-item-avatar-two-line" />
      </div>

      <!-- ─── لا نتائج ─── -->
      <div v-else-if="!items.length" class="empty-state text-center py-12 px-4">
        <VAvatar color="secondary" variant="tonal" size="64" class="mb-4">
          <VIcon icon="tabler-receipt-off" size="32" />
        </VAvatar>
        <h3 class="text-h6 mb-1">لا توجد معاملات</h3>
        <p class="text-body-2 text-medium-emphasis mb-4">
          {{ hasFilters ? 'ما في دفعات مطابقة للبحث أو الفلتر الحالي.' : 'لما يرسل مقاول إشعار تحويل رح يظهر هون.' }}
        </p>
        <VBtn v-if="hasFilters" variant="tonal" color="primary" prepend-icon="tabler-filter-off" @click="clearFilters">
          مسح الفلاتر
        </VBtn>
      </div>

      <!-- ─── موبايل: بطاقات ─── -->
      <div v-else-if="isMobile" class="pa-3 d-flex flex-column gap-3">
        <VCard
          v-for="p in items"
          :key="p.id"
          variant="outlined"
          class="payment-card"
          :class="{ 'payment-row--pending': p.status === 'pending' }"
        >
          <VCardText class="pa-4">
            <div class="d-flex align-start justify-space-between gap-2 mb-3">
              <div class="min-w-0">
                <div class="font-weight-medium text-high-emphasis text-truncate">{{ p.contractor ?? 'مقاول غير معروف' }}</div>
                <div class="d-flex align-center gap-2 mt-1">
                  <span class="text-caption text-medium-emphasis" dir="ltr">{{ p.contractor_membership_number ?? 'بدون رقم عضوية' }}</span>
                  <VChip v-if="p.contractor_deleted" size="x-small" color="error" variant="tonal">محذوف</VChip>
                </div>
              </div>
              <VChip :color="statusColor[p.status]" size="small" variant="tonal" :prepend-icon="statusIcon[p.status]">
                {{ statusLabel[p.status] ?? p.status }}
              </VChip>
            </div>

            <div class="d-flex align-end justify-space-between gap-2">
              <div>
                <div class="text-h6 font-weight-bold" dir="ltr">{{ fmtMoney(p.amount) }} {{ currencySymbol[p.currency] ?? p.currency }}</div>
                <div v-if="p.currency !== 'JOD' && p.amount_jod" class="text-caption text-medium-emphasis" dir="ltr">
                  ≈ {{ fmtMoney(p.amount_jod) }} د.أ
                </div>
                <div class="text-caption text-medium-emphasis mt-1">
                  {{ typeLabel[p.type] ?? p.type }} · {{ fmtDate(p.submitted_at ?? p.created_at) }}
                </div>
              </div>
              <div class="d-flex align-center">
                <VBtn v-if="p.receipt_image_url" icon size="small" variant="tonal" color="primary" aria-label="عرض الإيصال" @click="openReceipt(p)">
                  <VIcon :icon="isPdf(p.receipt_image_url) ? 'tabler-file-type-pdf' : 'tabler-photo'" size="20" />
                </VBtn>
                <VBtn v-if="p.contractor_id" icon size="small" variant="text" color="primary" :to="balanceLink(p)" aria-label="سجل مدفوعات المقاول ورصيده">
                  <VIcon icon="tabler-wallet" size="20" />
                </VBtn>
                <VMenu location="bottom end">
                  <template #activator="{ props }">
                    <VBtn icon size="small" variant="text" v-bind="props" aria-label="إجراءات">
                      <VIcon icon="tabler-dots-vertical" />
                    </VBtn>
                  </template>
                  <VList density="compact" min-width="230">
                    <VListSubheader>دفعة #{{ p.id }}</VListSubheader>
                    <VListItem prepend-icon="tabler-hash" title="الرقم المرجعي">
                      <VListItemSubtitle dir="ltr" class="text-start">{{ p.transaction_number ?? '—' }}</VListItemSubtitle>
                    </VListItem>
                    <VListItem
                      prepend-icon="tabler-clock"
                      title="آخر إجراء"
                      :subtitle="p.status !== 'pending' ? fmtDateTime(p.confirmed_at) : 'لا يوجد بعد'"
                    />
                    <VDivider class="my-1" />
                    <VListItem v-if="p.receipt_image_url" prepend-icon="tabler-photo" title="عرض الإيصال" @click="openReceipt(p)" />
                    <VListItem v-if="p.contractor_id" prepend-icon="tabler-wallet" title="كشف حساب المقاول" :to="balanceLink(p)" />
                    <VListItem v-if="$can('finance.payments', 'update')" prepend-icon="tabler-transfer" title="تغيير الحالة" @click="openStatusChange(p)" />
                  </VList>
                </VMenu>
              </div>
            </div>
          </VCardText>
        </VCard>
      </div>

      <!-- ─── ديسكتوب: جدول ─── -->
      <VTable v-else class="payments-table" hover>
        <thead>
          <tr>
            <th>المقاول</th>
            <th>المبلغ</th>
            <th>النوع</th>
            <th class="text-center">الإيصال</th>
            <th>الحالة</th>
            <th>التاريخ</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="p in items"
            :key="p.id"
            :class="{ 'payment-row--pending': p.status === 'pending' }"
          >
            <td>
              <div class="d-flex align-center gap-3 py-2">
                <VAvatar :color="p.contractor_deleted ? 'error' : 'primary'" variant="tonal" size="36">
                  <span class="text-body-1 font-weight-medium">{{ initial(p.contractor) }}</span>
                </VAvatar>
                <div class="min-w-0">
                  <div class="d-flex align-center gap-1">
                    <span class="font-weight-medium text-high-emphasis contractor-name">{{ p.contractor ?? 'مقاول غير معروف' }}</span>
                    <VChip v-if="p.contractor_deleted" size="x-small" color="error" variant="tonal">محذوف</VChip>
                  </div>
                  <div class="text-caption text-medium-emphasis" dir="ltr" style="text-align: end;">
                    {{ p.contractor_membership_number ?? 'بدون رقم عضوية' }}
                  </div>
                </div>
                <VBtn
                  v-if="p.contractor_id"
                  icon
                  size="x-small"
                  variant="text"
                  color="primary"
                  class="ms-auto"
                  :to="balanceLink(p)"
                  aria-label="سجل مدفوعات المقاول ورصيده"
                >
                  <VIcon icon="tabler-wallet" size="18" />
                  <VTooltip activator="parent">سجل مدفوعات المقاول ورصيده</VTooltip>
                </VBtn>
              </div>
            </td>
            <td class="text-no-wrap">
              <div class="font-weight-bold text-high-emphasis" dir="ltr" style="text-align: end;">
                {{ fmtMoney(p.amount) }} {{ currencySymbol[p.currency] ?? p.currency }}
              </div>
              <div v-if="p.currency !== 'JOD'" class="text-caption text-medium-emphasis" dir="ltr" style="text-align: end;">
                <template v-if="p.amount_jod">≈ {{ fmtMoney(p.amount_jod) }} د.أ</template>
                <template v-else>بانتظار سعر الصرف</template>
                <span v-if="p.exchange_rate" class="text-disabled"> (×{{ p.exchange_rate }})</span>
              </div>
            </td>
            <td>
              <VChip size="small" variant="tonal" color="secondary" label>
                {{ typeLabel[p.type] ?? p.type }}
              </VChip>
            </td>
            <td class="text-center">
              <button
                v-if="p.receipt_image_url"
                type="button"
                class="receipt-thumb"
                aria-label="عرض الإيصال"
                @click="openReceipt(p)"
              >
                <VIcon v-if="isPdf(p.receipt_image_url)" icon="tabler-file-type-pdf" size="22" color="error" />
                <img v-else :src="p.receipt_image_url" alt="" loading="lazy">
              </button>
              <span v-else class="text-disabled">—</span>
            </td>
            <td>
              <VChip :color="statusColor[p.status]" size="small" variant="tonal" :prepend-icon="statusIcon[p.status]">
                {{ statusLabel[p.status] ?? p.status }}
                <VIcon v-if="p.last_status_change || p.rejection_reason" icon="tabler-info-circle" size="14" class="ms-1" />
                <VTooltip v-if="p.last_status_change" activator="parent" max-width="320">
                  <div>من "{{ statusLabel[p.last_status_change.from_status] ?? p.last_status_change.from_status }}" إلى "{{ statusLabel[p.last_status_change.to_status] ?? p.last_status_change.to_status }}"</div>
                  <div>السبب: {{ p.last_status_change.reason }}</div>
                  <div class="text-caption">{{ p.last_status_change.changed_by ?? '—' }} · {{ fmtDateTime(p.last_status_change.changed_at) }}</div>
                </VTooltip>
                <VTooltip v-else-if="p.rejection_reason" activator="parent" max-width="320">
                  سبب الرفض: {{ p.rejection_reason }}
                </VTooltip>
              </VChip>
            </td>
            <td class="text-no-wrap">
              <div class="d-flex align-center justify-space-between gap-1">
                <div>
                  <div>{{ fmtDate(p.submitted_at ?? p.created_at) }}</div>
                  <div class="text-caption text-medium-emphasis">{{ fmtTime(p.submitted_at ?? p.created_at) }}</div>
                </div>
                <VMenu location="bottom end">
                  <template #activator="{ props }">
                    <VBtn icon size="small" variant="text" v-bind="props" aria-label="إجراءات">
                      <VIcon icon="tabler-dots-vertical" />
                    </VBtn>
                  </template>
                  <VList density="compact" min-width="230">
                    <VListSubheader>دفعة #{{ p.id }}</VListSubheader>
                    <VListItem prepend-icon="tabler-hash" title="الرقم المرجعي">
                      <VListItemSubtitle dir="ltr" class="text-start">{{ p.transaction_number ?? '—' }}</VListItemSubtitle>
                    </VListItem>
                    <VListItem
                      prepend-icon="tabler-clock"
                      title="آخر إجراء"
                      :subtitle="p.status !== 'pending' ? fmtDateTime(p.confirmed_at) : 'لا يوجد بعد'"
                    />
                    <VDivider class="my-1" />
                    <VListItem v-if="p.receipt_image_url" prepend-icon="tabler-photo" title="عرض الإيصال" @click="openReceipt(p)" />
                    <VListItem v-if="p.contractor_id" prepend-icon="tabler-wallet" title="كشف حساب المقاول" :to="balanceLink(p)" />
                    <VListItem v-if="$can('finance.payments', 'update')" prepend-icon="tabler-transfer" title="تغيير الحالة" @click="openStatusChange(p)" />
                  </VList>
                </VMenu>
              </div>
            </td>
          </tr>
        </tbody>
      </VTable>

      <template v-if="(data?.last_page ?? 1) > 1">
        <VDivider />
        <VCardText class="d-flex justify-center">
          <VPagination v-model="page" :length="data?.last_page ?? 1" :total-visible="$vuetify.display.xs ? 4 : 7" :density="$vuetify.display.xs ? 'compact' : 'default'" rounded="circle" />
        </VCardText>
      </template>
    </VCard>

    <!-- ─── Dialog تغيير الحالة ─── -->
    <VDialog v-model="statusDialog" max-width="500">
      <VCard title="تغيير حالة الدفعة">
        <VCardText>
          <p class="text-body-2 mb-4">
            {{ changing?.contractor }} —
            <strong dir="ltr">{{ changing?.amount }} {{ currencySymbol[changing?.currency ?? 'JOD'] }}</strong>
            <span class="ms-2">الحالة الحالية:</span>
            <VChip v-if="changing" :color="statusColor[changing.status]" size="small" class="ms-1">
              {{ statusLabel[changing.status] ?? changing.status }}
            </VChip>
          </p>

          <VAlert v-if="changing?.status_change_blocker" type="warning" variant="tonal" density="compact" class="mb-4">
            {{ changing.status_change_blocker }}
          </VAlert>

          <template v-else>
            <VSelect
              v-model="targetStatus"
              :items="statusTargets"
              label="الحالة الجديدة"
              class="mb-3"
            />

            <VAlert
              v-if="changing?.status === 'paid' && targetStatus && targetStatus !== 'paid'"
              type="warning"
              variant="tonal"
              density="compact"
              class="mb-3"
            >
              أي ذمم سدّدتها هالدفعة رح ترجع مستحقة على المقاول، ورصيد الدفعة بينشال.
              <template v-if="changing?.type === 'membership_fee'">
                والعضوية اللي جدّدتها رح تنلغى وترجع زي ما كانت قبل التأكيد.
              </template>
            </VAlert>

            <VTextarea
              v-model="changeReason"
              label="سبب تغيير الحالة"
              rows="3"
              dir="rtl"
              :hint="targetStatus === 'rejected' ? 'بيظهر للمقاول كسبب الرفض' : 'بينحفظ بسجل الدفعة مع اسمك والوقت'"
              persistent-hint
            />

            <template v-if="targetStatus === 'paid'">
              <template v-if="changing && changing.currency !== 'JOD'">
                <VTextField
                  v-model="confirmRate"
                  :label="`سعر الصرف (1 ${changing.currency} = ? د.أ)`"
                  type="number"
                  step="0.000001"
                  dir="ltr"
                  class="mt-4"
                  hint="السعر المقترح من آخر تحديث تلقائي — يمكن تعديله"
                  persistent-hint
                />
                <VAlert v-if="jodEquivalent" type="info" variant="tonal" density="compact" class="mt-3">
                  المعادل بالدينار الأردني: <strong>{{ jodEquivalent }} د.أ</strong>
                </VAlert>
                <VAlert v-else type="warning" variant="tonal" density="compact" class="mt-3">
                  لا يوجد سعر صرف معتمد — أدخل السعر يدوياً.
                </VAlert>
              </template>

              <a v-if="changing?.receipt_image_url" href="#" class="d-block mb-2 mt-3 text-body-2" @click.prevent="changing && openReceipt(changing)">
                عرض صورة الإشعار الحالية
              </a>
              <VFileInput
                v-model="confirmReceiptFile"
                label="صورة إثبات الدفع (اختياري — لاستبدال/إضافة الصورة عند التأكيد)"
                accept="image/png,image/jpeg,image/webp,application/pdf"
                prepend-icon="tabler-photo"
                show-size
                class="mt-3"
              />
              <VImg
                v-if="confirmReceiptPreview"
                :src="confirmReceiptPreview"
                max-height="220"
                class="mt-2 rounded border"
              />
            </template>
          </template>
        </VCardText>
        <VCardActions class="justify-end pb-4 px-6">
          <VBtn variant="tonal" color="secondary" @click="statusDialog = false">إلغاء</VBtn>
          <VBtn
            v-if="!changing?.status_change_blocker"
            :color="targetStatus ? statusColor[targetStatus] : 'primary'"
            :loading="statusMutation.isPending.value"
            :disabled="!canSubmitStatus"
            @click="statusMutation.mutate()"
          >
            حفظ
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- ─── Dialog إضافة دفعة ─── -->
    <VDialog v-model="addDialog" max-width="560">
      <VCard title="إضافة دفعة">
        <VCardText>
          <VAlert type="info" variant="tonal" density="compact" class="mb-4">
            تُسجَّل الدفعة مؤكَّدة مباشرة، ويُسدَّد بالمعادل بالدينار أقدم ذمم المقاول أولاً — والزائد يبقى رصيداً له.
          </VAlert>

          <VAutocomplete
            v-model="newPayment.contractor_id"
            v-model:search="contractorSearch"
            :items="contractorOptions"
            :item-title="(c: any) => `${c.name} (${c.membership_number})`"
            item-value="id"
            label="المقاول"
            placeholder="اكتب اسم المقاول أو رقم العضوية..."
            no-filter
            class="mb-3"
          />

          <VRow>
            <VCol cols="7">
              <VTextField v-model="newPayment.amount" label="المبلغ" type="number" min="0" step="0.01" dir="ltr" />
            </VCol>
            <VCol cols="5">
              <VSelect
                v-model="newPayment.currency"
                :items="[
                  { title: 'دينار أردني', value: 'JOD' },
                  { title: 'شيكل', value: 'ILS' },
                  { title: 'دولار', value: 'USD' },
                ]"
                label="العملة"
              />
            </VCol>
          </VRow>

          <VTextField
            v-if="newPayment.currency !== 'JOD'"
            v-model="newPayment.exchange_rate"
            :label="`سعر الصرف (1 ${newPayment.currency} = ? د.أ)`"
            type="number"
            step="0.000001"
            dir="ltr"
            class="mt-3"
            hint="السعر المقترح من آخر تحديث تلقائي — يمكن تعديله"
            persistent-hint
          />

          <VAlert v-if="newJodEquivalent" type="success" variant="tonal" density="compact" class="mt-3">
            المعادل بالدينار الأردني: <strong>{{ newJodEquivalent }} د.أ</strong>
          </VAlert>

          <VRow class="mt-1">
            <VCol cols="6">
              <VSelect
                v-model="newPayment.method"
                :items="[
                  { title: 'حوالة بنكية', value: 'bank_transfer' },
                  { title: 'نقداً', value: 'cash' },
                  { title: 'شيك', value: 'cheque' },
                ]"
                label="طريقة الدفع"
              />
            </VCol>
            <VCol cols="6">
              <VTextField v-model="newPayment.reference_number" label="رقم الحوالة / المرجع" dir="ltr" />
            </VCol>
          </VRow>

          <VTextarea v-model="newPayment.notes" label="التفاصيل" rows="2" dir="rtl" class="mt-3" />

          <VFileInput
            v-model="newReceiptFile"
            :label="newPayment.method === 'bank_transfer' ? 'صورة الإشعار (مطلوبة للحوالة)' : 'صورة الإشعار (اختياري)'"
            accept="image/png,image/jpeg,image/webp,application/pdf"
            prepend-icon="tabler-photo"
            show-size
            class="mt-3"
          />
          <VImg
            v-if="newReceiptPreview"
            :src="newReceiptPreview"
            max-height="220"
            class="mt-2 rounded border"
          />
        </VCardText>
        <VCardActions class="justify-end pb-4 px-6">
          <VBtn variant="tonal" color="secondary" @click="addDialog = false">إلغاء</VBtn>
          <VBtn
            color="primary"
            :loading="addMutation.isPending.value"
            :disabled="!canSubmitNew"
            @click="addMutation.mutate()"
          >
            حفظ الدفعة
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- إيصال الدفعة -->
    <VDialog v-model="receiptViewerOpen" max-width="900" scrollable>
      <VCard>
        <VCardTitle class="d-flex align-center pa-4">
          <span class="text-truncate">إيصال الدفعة<span v-if="receiptViewer?.title" class="text-medium-emphasis text-body-1"> — {{ receiptViewer.title }}</span></span>
          <VSpacer />
          <VBtn
            v-if="receiptViewer"
            :href="receiptViewer.url"
            target="_blank"
            rel="noopener"
            variant="text"
            size="small"
            prepend-icon="tabler-external-link"
          >
            فتح بتبويب جديد
          </VBtn>
          <VBtn icon variant="text" size="small" aria-label="إغلاق" @click="receiptViewerOpen = false">
            <VIcon icon="tabler-x" />
          </VBtn>
        </VCardTitle>
        <VDivider />
        <VCardText class="pa-2 text-center">
          <template v-if="receiptViewer">
            <iframe
              v-if="receiptIsPdf"
              :src="receiptViewer.url"
              title="إيصال الدفعة"
              style="inline-size: 100%; block-size: 75vh; border: 0;"
            />
            <img
              v-else
              :src="receiptViewer.url"
              alt="إيصال الدفعة"
              style="max-inline-size: 100%; max-block-size: 75vh; object-fit: contain;"
            >
          </template>
        </VCardText>
      </VCard>
    </VDialog>
  </div>
</template>

<style scoped>
.search-field {
  flex: 1 1 280px;
  max-inline-size: 420px;
}

.summary-card {
  cursor: pointer;
  transition: border-color 0.15s ease, box-shadow 0.15s ease, transform 0.15s ease;
}

.summary-card:hover {
  box-shadow: 0 4px 14px rgba(var(--v-shadow-key-umbra-color), 0.08);
  transform: translateY(-1px);
}

.summary-card--active {
  border-width: 2px;
}

.payments-table :deep(th) {
  font-size: 0.8125rem;
  font-weight: 600;
  white-space: nowrap;
}

.payments-table :deep(td) {
  padding-block: 6px;
}

/* تمييز الدفعات قيد المراجعة: خط جانبي وخلفية خفيفة */
.payment-row--pending {
  background: rgba(var(--v-theme-warning), 0.05);
}

.payments-table .payment-row--pending td:first-child {
  box-shadow: inset -3px 0 0 rgb(var(--v-theme-warning));
}

.payment-card.payment-row--pending {
  border-inline-start: 3px solid rgb(var(--v-theme-warning));
}

.contractor-name {
  display: inline-block;
  max-inline-size: 240px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.receipt-thumb {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  overflow: hidden;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 8px;
  background: rgba(var(--v-theme-on-surface), 0.04);
  block-size: 40px;
  cursor: zoom-in;
  inline-size: 40px;
  transition: transform 0.15s ease, box-shadow 0.15s ease;
}

.receipt-thumb:hover {
  box-shadow: 0 2px 8px rgba(var(--v-shadow-key-umbra-color), 0.15);
  transform: scale(1.06);
}

.receipt-thumb img {
  block-size: 100%;
  inline-size: 100%;
  object-fit: cover;
}

.min-w-0 {
  min-inline-size: 0;
}
</style>

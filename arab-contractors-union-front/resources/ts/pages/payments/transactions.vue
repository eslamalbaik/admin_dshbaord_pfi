<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { useQuery, useMutation, useQueryClient } from '@tanstack/vue-query'
import api from '@/plugins/axios'

definePage({ meta: { requiresAdmin: true } })

const queryClient = useQueryClient()

interface Payment {
  id: number
  transaction_number: string | null
  contractor: string | null
  contractor_id: number
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

watch([search, statusFilter], () => page.value = 1)

const { data, isLoading } = useQuery({
  queryKey: ['payments-transactions', search, statusFilter, page],
  queryFn: async () => (await api.get('/api/v1/payments/transactions', {
    params: {
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
</script>

<template>
  <div>
    <div class="mb-6 d-flex align-center justify-space-between flex-wrap gap-4">
      <div>
        <h1 class="text-h4 font-weight-bold">سجل المدفوعات</h1>
        <p class="text-body-2 text-medium-emphasis mb-0">
          إشعارات التحويل الواردة من المقاولين — التأكيد يُثبّت سعر الصرف والمعادل بالدينار
        </p>
      </div>
      <VBtn color="primary" prepend-icon="tabler-plus" @click="openAdd">
        إضافة دفعة
      </VBtn>
    </div>

    <VAlert v-if="successMessage" type="success" variant="tonal" class="mb-4">
      {{ successMessage }}
    </VAlert>
    <VAlert v-if="errorMessage" type="error" variant="tonal" class="mb-4">
      {{ errorMessage }}
    </VAlert>

    <VCard>
      <VCardText class="d-flex gap-4 flex-wrap">
        <VTextField
          v-model="search"
          placeholder="بحث باسم المقاول..."
          prepend-inner-icon="tabler-search"
          density="compact"
          style="max-width: 300px;"
        />
        <VSelect
          v-model="statusFilter"
          :items="[
            { title: 'كل الحالات', value: '' },
            { title: 'قيد المراجعة', value: 'pending' },
            { title: 'مؤكّدة', value: 'paid' },
            { title: 'مرفوضة', value: 'rejected' },
          ]"
          label="الحالة"
          density="compact"
          style="max-width: 170px;"
        />
      </VCardText>

      <VProgressLinear v-if="isLoading" indeterminate color="primary" />

      <VTable>
        <thead>
          <tr>
            <th>#</th>
            <th>الرقم المرجعي</th>
            <th>المقاول</th>
            <th>المبلغ</th>
            <th>المعادل (د.أ)</th>
            <th>النوع</th>
            <th>الإيصال</th>
            <th>الحالة</th>
            <th>التاريخ</th>
            <th>آخر إجراء</th>
            <th class="text-end">إجراءات</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="p in (data?.items ?? [])" :key="p.id">
            <td>{{ p.id }}</td>
            <td dir="ltr">{{ p.transaction_number ?? '—' }}</td>
            <td>{{ p.contractor ?? '—' }}</td>
            <td dir="ltr">{{ p.amount }} {{ currencySymbol[p.currency] ?? p.currency }}</td>
            <td dir="ltr">
              {{ p.amount_jod ?? '—' }}
              <span v-if="p.exchange_rate" class="text-disabled text-caption">({{ p.exchange_rate }})</span>
            </td>
            <td>{{ ({ membership_fee: 'رسوم عضوية', renewal_fee: 'رسوم تجديد', dues_payment: 'سداد ذمم', penalty: 'غرامة', equipment_subscription: 'اشتراك سوق الآليات' } as Record<string, string>)[p.type] ?? p.type }}</td>
            <td>
              <a v-if="p.receipt_image_url" :href="p.receipt_image_url" target="_blank" rel="noopener">
                <VIcon icon="tabler-photo" size="20" />
              </a>
              <span v-else>—</span>
            </td>
            <td>
              <VChip :color="statusColor[p.status]" size="small">
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
            <td>{{ fmtDate(p.submitted_at ?? p.created_at) }}</td>
            <td class="text-caption text-medium-emphasis">{{ p.status !== 'pending' ? fmtDateTime(p.confirmed_at) : '—' }}</td>
            <td class="text-end text-no-wrap">
              <VMenu location="bottom end">
                <template #activator="{ props }">
                  <VBtn icon size="small" variant="text" v-bind="props" aria-label="إجراءات">
                    <VIcon icon="tabler-dots-vertical" />
                  </VBtn>
                </template>
                <VList density="compact" min-width="160">
                  <VListItem prepend-icon="tabler-transfer" title="تغيير الحالة" @click="openStatusChange(p)" />
                </VList>
              </VMenu>
            </td>
          </tr>
          <tr v-if="!isLoading && !(data?.items ?? []).length">
            <td colspan="11" class="text-center text-medium-emphasis py-8">
              لا توجد معاملات
            </td>
          </tr>
        </tbody>
      </VTable>

      <VCardText v-if="(data?.last_page ?? 1) > 1" class="d-flex justify-center">
        <VPagination v-model="page" :length="data?.last_page ?? 1" :total-visible="$vuetify.display.xs ? 5 : 7" />
      </VCardText>
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

              <a v-if="changing?.receipt_image_url" :href="changing.receipt_image_url" target="_blank" rel="noopener" class="d-block mb-2 mt-3 text-body-2">
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

  </div>
</template>

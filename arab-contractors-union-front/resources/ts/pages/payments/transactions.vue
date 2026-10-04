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
  submitted_at: string | null
  confirmed_at: string | null
  created_at: string
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

// ─── تأكيد دفعة ───
const confirmDialog = ref(false)
const confirming = ref<Payment | null>(null)
const confirmRate = ref('')
const confirmReceiptFile = ref<File[]>([])
const confirmReceiptPreview = ref('')

function openConfirm(p: Payment) {
  confirming.value = p
  confirmRate.value = p.currency !== 'JOD'
    ? String(rates.value?.items?.latest?.[p.currency]?.rate_to_jod ?? '')
    : ''
  confirmReceiptFile.value = []
  confirmReceiptPreview.value = ''
  confirmDialog.value = true
}

watch(confirmReceiptFile, files => {
  if (confirmReceiptPreview.value)
    URL.revokeObjectURL(confirmReceiptPreview.value)

  const file = files[0]
  confirmReceiptPreview.value = file && file.type.startsWith('image/') ? URL.createObjectURL(file) : ''
})

const jodEquivalent = computed(() => {
  if (!confirming.value) return null
  if (confirming.value.currency === 'JOD') return Number(confirming.value.amount)
  const rate = Number(confirmRate.value)
  if (!rate) return null
  return Math.round(Number(confirming.value.amount) * rate * 100) / 100
})

const confirmMutation = useMutation({
  mutationFn: async () => {
    const form = new FormData()
    if (confirming.value!.currency !== 'JOD' && confirmRate.value)
      form.append('exchange_rate', confirmRate.value)
    if (confirmReceiptFile.value[0])
      form.append('receipt_image', confirmReceiptFile.value[0])

    return (await api.post(`/api/v1/payments/transactions/${confirming.value!.id}/confirm`, form, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })).data
  },
  onSuccess: () => {
    queryClient.invalidateQueries({ queryKey: ['payments-transactions'] })
    confirmDialog.value = false
    flash('تم تأكيد عملية الدفع بنجاح.')
  },
  onError: (e: any) => flash(e?.response?.data?.message || 'فشل تأكيد الدفعة.', true),
})

// ─── رفض دفعة ───
const rejectDialog = ref(false)
const rejecting = ref<Payment | null>(null)
const rejectReason = ref('')

function openReject(p: Payment) {
  rejecting.value = p
  rejectReason.value = ''
  rejectDialog.value = true
}

const rejectMutation = useMutation({
  mutationFn: async () => (await api.post(`/api/v1/payments/transactions/${rejecting.value!.id}/reject`, {
    rejection_reason: rejectReason.value,
  })).data,
  onSuccess: () => {
    queryClient.invalidateQueries({ queryKey: ['payments-transactions'] })
    rejectDialog.value = false
    flash('تم رفض إشعار التحويل.')
  },
  onError: (e: any) => flash(e?.response?.data?.message || 'فشل رفض الدفعة.', true),
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
const newReceiptFile = ref<File[]>([])
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
  newReceiptFile.value = []
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

  const file = files?.[0]

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
  && (newPayment.value.method !== 'bank_transfer' || !!newReceiptFile.value?.[0]),
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
    if (newReceiptFile.value?.[0])
      form.append('receipt_image', newReceiptFile.value[0])

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

const statusColor: Record<string, string> = {
  pending: 'warning', paid: 'success', rejected: 'error', refunded: 'info', failed: 'error',
}

const statusLabel: Record<string, string> = {
  pending: 'قيد المراجعة', paid: 'مؤكّدة', rejected: 'مرفوضة', refunded: 'مُعادة', failed: 'فاشلة',
}

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
              </VChip>
            </td>
            <td>{{ fmtDate(p.submitted_at ?? p.created_at) }}</td>
            <td class="text-caption text-medium-emphasis">{{ p.status !== 'pending' ? fmtDateTime(p.confirmed_at) : '—' }}</td>
            <td class="text-end text-no-wrap">
              <VBtn size="small" color="success" variant="tonal" class="me-1" @click="openConfirm(p)">
                تأكيد
              </VBtn>
              <VBtn size="small" color="error" variant="tonal" @click="openReject(p)">
                رفض
              </VBtn>
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

    <!-- ─── Dialog تأكيد ─── -->
    <VDialog v-model="confirmDialog" max-width="480">
      <VCard title="تأكيد استلام الدفعة">
        <VCardText>
          <p class="text-body-2 mb-4">
            {{ confirming?.contractor }} —
            <strong dir="ltr">{{ confirming?.amount }} {{ currencySymbol[confirming?.currency ?? 'JOD'] }}</strong>
          </p>

          <template v-if="confirming && confirming.currency !== 'JOD'">
            <VTextField
              v-model="confirmRate"
              :label="`سعر الصرف (1 ${confirming.currency} = ? د.أ)`"
              type="number"
              step="0.000001"
              dir="ltr"
              class="mb-2"
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

          <a v-if="confirming?.receipt_image_url" :href="confirming.receipt_image_url" target="_blank" rel="noopener" class="d-block mb-2 mt-3 text-body-2">
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
        </VCardText>
        <VCardActions class="justify-end pb-4 px-6">
          <VBtn variant="tonal" color="secondary" @click="confirmDialog = false">إلغاء</VBtn>
          <VBtn
            color="success"
            :loading="confirmMutation.isPending.value"
            :disabled="confirming?.currency !== 'JOD' && !confirmRate"
            @click="confirmMutation.mutate()"
          >
            تأكيد الدفع
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

    <!-- ─── Dialog رفض ─── -->
    <VDialog v-model="rejectDialog" max-width="480">
      <VCard title="رفض إشعار التحويل">
        <VCardText>
          <VTextarea v-model="rejectReason" label="سبب الرفض" rows="3" dir="rtl" />
        </VCardText>
        <VCardActions class="justify-end pb-4 px-6">
          <VBtn variant="tonal" color="secondary" @click="rejectDialog = false">إلغاء</VBtn>
          <VBtn
            color="error"
            :loading="rejectMutation.isPending.value"
            :disabled="!rejectReason"
            @click="rejectMutation.mutate()"
          >
            رفض
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { useQuery, useMutation, useQueryClient } from '@tanstack/vue-query'
import api from '@/plugins/axios'

definePage({ meta: { requiresAdmin: true } })

const queryClient = useQueryClient()

interface ContractorRow {
  contractor_id: number
  name: string
  membership_number: string
  dues_count: number
  total_jod: number
  paid_jod: number
  remaining_jod: number
}

interface DueItem {
  id: number
  year: number | null
  period: string | null
  description: string
  amount_jod: string
  paid_jod: string
  remaining_jod: number
  status: 'unpaid' | 'partially_paid' | 'paid'
  status_label: string
  source: string
  due_date: string | null
  notes: string | null
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
    setTimeout(() => successMessage.value = '', 5000)
  }
}

// ─── القائمة المجمّعة حسب المقاول ───
const search = ref('')
const outstandingOnly = ref(false)
const page = ref(1)

watch([search, outstandingOnly], () => page.value = 1)

const { data, isLoading } = useQuery({
  queryKey: ['dues-by-contractor', search, outstandingOnly, page],
  queryFn: async () => (await api.get('/api/v1/dashboard/dues/by-contractor', {
    params: {
      search: search.value || undefined,
      outstanding_only: outstandingOnly.value ? 1 : undefined,
      page: page.value,
    },
  })).data,
})

const { data: summary } = useQuery({
  queryKey: ['dashboard-dues-summary'],
  queryFn: async () => (await api.get('/api/v1/dashboard/dues/summary')).data,
})

const { data: rates } = useQuery({
  queryKey: ['exchange-rates'],
  queryFn: async () => (await api.get('/api/v1/dashboard/exchange-rates')).data,
})

// ─── تفاصيل مقاول (صف موسّع) ───
const expandedId = ref<number | null>(null)
const expandedDues = ref<DueItem[]>([])
const expandedLoading = ref(false)

async function toggleExpand(c: ContractorRow) {
  if (expandedId.value === c.contractor_id) {
    expandedId.value = null

    return
  }
  expandedId.value = c.contractor_id
  await loadDues(c.contractor_id)
}

async function loadDues(contractorId: number) {
  expandedLoading.value = true
  try {
    const r = await api.get('/api/v1/dashboard/dues', {
      params: { contractor_id: contractorId, per_page: 100 },
    })
    expandedDues.value = (r.data.items ?? []).sort((a: DueItem, b: DueItem) => (a.year ?? 0) - (b.year ?? 0))
  }
  finally {
    expandedLoading.value = false
  }
}

function refreshAll() {
  queryClient.invalidateQueries({ queryKey: ['dues-by-contractor'] })
  queryClient.invalidateQueries({ queryKey: ['dashboard-dues-summary'] })
  if (expandedId.value)
    loadDues(expandedId.value)
}

// ─── تسجيل دفعة واردة من الشركة ───
const payDialog = ref(false)
const paying = ref<ContractorRow | null>(null)
const payForm = ref({
  amount: '',
  currency: 'JOD',
  exchange_rate: '',
  method: 'cash',
  reference_number: '',
  notes: '',
})

const currencies = [
  { value: 'JOD', label: 'دينار أردني' },
  { value: 'ILS', label: 'شيكل' },
  { value: 'USD', label: 'دولار' },
]

function openPay(c: ContractorRow) {
  paying.value = c
  payForm.value = { amount: '', currency: 'JOD', exchange_rate: '', method: 'cash', reference_number: '', notes: '' }
  payDialog.value = true
}

// اقتراح سعر الصرف المعتمد عند تغيير العملة
watch(() => payForm.value.currency, cur => {
  payForm.value.exchange_rate = cur !== 'JOD'
    ? String(rates.value?.items?.latest?.[cur]?.rate_to_jod ?? '')
    : ''
})

const payJodEquivalent = computed(() => {
  const amount = Number(payForm.value.amount)
  if (!amount) return null
  if (payForm.value.currency === 'JOD') return amount
  const rate = Number(payForm.value.exchange_rate)
  if (!rate) return null

  return Math.round(amount * rate * 100) / 100
})

const payMutation = useMutation({
  mutationFn: async () => (await api.post(`/api/v1/dashboard/contractors/${paying.value!.contractor_id}/dues/pay`, {
    amount: payForm.value.amount,
    currency: payForm.value.currency,
    exchange_rate: payForm.value.currency !== 'JOD' && payForm.value.exchange_rate
      ? payForm.value.exchange_rate
      : undefined,
    method: payForm.value.method,
    reference_number: payForm.value.reference_number || undefined,
    notes: payForm.value.notes || undefined,
  })).data,
  onSuccess: (d: any) => {
    payDialog.value = false
    const applied = (d.items?.applied ?? []).length
    const excess = d.items?.unapplied_jod ?? 0
    flash(`تم تسجيل الدفعة وتوزيعها على ${applied} ذمة.${excess > 0 ? ` فائض غير موزَّع: ${excess} د.أ` : ''}`)
    refreshAll()
  },
  onError: (e: any) => flash(e?.response?.data?.message || 'فشل تسجيل الدفعة.', true),
})

// ─── إضافة/تعديل ذمة يدوية ───
const dueDialog = ref(false)
const editingDue = ref<DueItem | null>(null)
const dueForm = ref({
  contractor_id: null as number | null,
  description: '',
  amount_jod: '',
  year: '' as string | number,
  due_date: '',
  notes: '',
})

const contractorSearch = ref('')
const contractorOptions = ref<{ id: number; name: string; membership_number: string }[]>([])
let contractorTimer: ReturnType<typeof setTimeout> | null = null

watch(contractorSearch, q => {
  if (contractorTimer) clearTimeout(contractorTimer)
  contractorTimer = setTimeout(async () => {
    if (!q || q.length < 2) return
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

function openCreateDue(c?: ContractorRow) {
  editingDue.value = null
  dueForm.value = {
    contractor_id: c?.contractor_id ?? null,
    description: '',
    amount_jod: '',
    year: '',
    due_date: '',
    notes: '',
  }
  if (c)
    contractorOptions.value = [{ id: c.contractor_id, name: c.name, membership_number: c.membership_number }]

  dueDialog.value = true
}

function openEditDue(d: DueItem) {
  editingDue.value = d
  dueForm.value = {
    contractor_id: expandedId.value,
    description: d.description,
    amount_jod: d.amount_jod,
    year: d.year ?? '',
    due_date: d.due_date ?? '',
    notes: d.notes ?? '',
  }
  dueDialog.value = true
}

const saveDueMutation = useMutation({
  mutationFn: async () => {
    const payload: any = {
      description: dueForm.value.description,
      amount_jod: dueForm.value.amount_jod,
      year: dueForm.value.year || null,
      due_date: dueForm.value.due_date || null,
      notes: dueForm.value.notes || null,
    }
    if (editingDue.value)
      return (await api.patch(`/api/v1/dashboard/dues/${editingDue.value.id}`, payload)).data
    payload.contractor_id = dueForm.value.contractor_id

    return (await api.post('/api/v1/dashboard/dues', payload)).data
  },
  onSuccess: () => {
    dueDialog.value = false
    flash('تم حفظ الذمة المالية بنجاح.')
    refreshAll()
  },
  onError: (e: any) => flash(e?.response?.data?.message || 'فشل حفظ الذمة.', true),
})

const deleteDueMutation = useMutation({
  mutationFn: async (id: number) => (await api.delete(`/api/v1/dashboard/dues/${id}`)).data,
  onSuccess: () => {
    flash('تم حذف الذمة.')
    refreshAll()
  },
})

const statusColor: Record<string, string> = {
  unpaid: 'error',
  partially_paid: 'warning',
  paid: 'success',
}

// ─── استيراد كشف إكسل ───
const importDialog = ref(false)
const importFile = ref<File | null>(null)
const importForce = ref(false)
const importCreateMissing = ref(true)
const importResult = ref<any>(null)

function openImport() {
  importFile.value = null
  importForce.value = false
  importCreateMissing.value = true
  importResult.value = null
  importDialog.value = true
}

function onImportFileChange(files: File[] | File | null) {
  importFile.value = Array.isArray(files) ? (files[0] ?? null) : files
  importResult.value = null
}

const importMutation = useMutation({
  mutationFn: async (dryRun: boolean) => {
    const fd = new FormData()
    fd.append('file', importFile.value!)
    fd.append('dry_run', dryRun ? '1' : '0')
    fd.append('force', importForce.value ? '1' : '0')
    fd.append('create_missing', importCreateMissing.value ? '1' : '0')

    // مهلة أطول — قراءة ملفات الإكسل الكبيرة قد تستغرق وقتاً
    return (await api.post('/api/v1/dashboard/dues/import', fd, { timeout: 300000 })).data
  },
  onSuccess: (d: any) => {
    importResult.value = d.items
    if (!d.items?.dry_run) {
      flash(d.message ?? 'تم الاستيراد بنجاح.')
      refreshAll()
    }
  },
  onError: (e: any) => flash(e?.response?.data?.message || 'فشل الاستيراد.', true),
})
</script>

<template>
  <div>
    <div class="d-flex align-center justify-space-between flex-wrap gap-4 mb-6">
      <div>
        <h1 class="text-h4 font-weight-bold">الذمم المالية للمقاولين</h1>
        <p class="text-body-2 text-medium-emphasis mb-0">
          صف لكل شركة — اضغط على الشركة لعرض كل سنواتها، وسجّل الدفعات الواردة لتوزيعها تلقائياً
        </p>
      </div>
      <div class="d-flex gap-2">
        <VBtn
          variant="tonal"
          color="success"
          prepend-icon="tabler-file-spreadsheet"
          @click="openImport"
        >
          استيراد من إكسل
        </VBtn>
        <VBtn prepend-icon="tabler-plus" @click="openCreateDue()">
          إضافة ذمة
        </VBtn>
      </div>
    </div>

    <VAlert v-if="successMessage" type="success" variant="tonal" class="mb-4">
      {{ successMessage }}
    </VAlert>
    <VAlert v-if="errorMessage" type="error" variant="tonal" class="mb-4">
      {{ errorMessage }}
    </VAlert>

    <!-- ─── ملخص ─── -->
    <VRow class="mb-2">
      <VCol cols="12" md="3">
        <VCard>
          <VCardText>
            <p class="text-body-2 text-medium-emphasis mb-1">إجمالي الذمم القائمة</p>
            <h3 class="text-h5">{{ summary?.items?.outstanding_total_jod ?? '—' }} د.أ</h3>
          </VCardText>
        </VCard>
      </VCol>
      <VCol cols="12" md="3">
        <VCard>
          <VCardText>
            <p class="text-body-2 text-medium-emphasis mb-1">إجمالي المحصَّل</p>
            <h3 class="text-h5">{{ summary?.items?.collected_total_jod ?? '—' }} د.أ</h3>
          </VCardText>
        </VCard>
      </VCol>
      <VCol cols="12" md="3">
        <VCard>
          <VCardText>
            <p class="text-body-2 text-medium-emphasis mb-1">مقاولون عليهم ذمم</p>
            <h3 class="text-h5">{{ summary?.items?.contractors_with_dues ?? '—' }}</h3>
          </VCardText>
        </VCard>
      </VCol>
      <VCol cols="12" md="3">
        <VCard>
          <VCardText>
            <p class="text-body-2 text-medium-emphasis mb-1">أسعار الصرف → د.أ</p>
            <div class="d-flex gap-4">
              <span dir="ltr">ILS: {{ rates?.items?.latest?.ILS?.rate_to_jod ?? '—' }}</span>
              <span dir="ltr">USD: {{ rates?.items?.latest?.USD?.rate_to_jod ?? '—' }}</span>
            </div>
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
        <VSwitch
          v-model="outstandingOnly"
          label="عليهم ذمم قائمة فقط"
          color="warning"
          hide-details
        />
      </VCardText>

      <VProgressLinear v-if="isLoading" indeterminate color="primary" />

      <VTable>
        <thead>
          <tr>
            <th style="inline-size: 40px;" />
            <th>الشركة</th>
            <th>رقم العضوية</th>
            <th>عدد الذمم</th>
            <th>الإجمالي (د.أ)</th>
            <th>المسدَّد (د.أ)</th>
            <th>المتبقي (د.أ)</th>
            <th class="text-end">إجراءات</th>
          </tr>
        </thead>
        <tbody>
          <template v-for="c in (data?.items ?? [])" :key="c.contractor_id">
            <tr
              style="cursor: pointer;"
              @click="toggleExpand(c)"
            >
              <td>
                <VIcon
                  :icon="expandedId === c.contractor_id ? 'tabler-chevron-up' : 'tabler-chevron-down'"
                  size="18"
                />
              </td>
              <td class="font-weight-medium">{{ c.name }}</td>
              <td dir="ltr">{{ c.membership_number }}</td>
              <td>{{ c.dues_count }}</td>
              <td>{{ c.total_jod }}</td>
              <td class="text-success">{{ c.paid_jod }}</td>
              <td :class="c.remaining_jod > 0 ? 'text-error font-weight-bold' : 'text-success'">
                {{ c.remaining_jod }}
              </td>
              <td class="text-end text-no-wrap" @click.stop>
                <VBtn
                  v-if="c.remaining_jod > 0"
                  size="small"
                  color="success"
                  variant="tonal"
                  prepend-icon="tabler-cash"
                  class="me-1"
                  @click="openPay(c)"
                >
                  تسجيل دفعة
                </VBtn>
                <VBtn
                  size="small"
                  variant="text"
                  icon="tabler-plus"
                  title="إضافة ذمة لهذه الشركة"
                  @click="openCreateDue(c)"
                />
              </td>
            </tr>

            <!-- ─── الصف الموسّع: كل سنوات الشركة ─── -->
            <tr v-if="expandedId === c.contractor_id">
              <td colspan="8" style="background: rgba(var(--v-theme-primary), 0.03); padding: 0;">
                <VProgressLinear v-if="expandedLoading" indeterminate color="primary" />
                <VTable v-else density="compact" style="background: transparent;">
                  <thead>
                    <tr>
                      <th>السنة</th>
                      <th>البيان</th>
                      <th>المبلغ (د.أ)</th>
                      <th>المسدَّد</th>
                      <th>المتبقي</th>
                      <th>الحالة</th>
                      <th class="text-end">إجراءات</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="d in expandedDues" :key="d.id">
                      <td>{{ d.year ?? '—' }}</td>
                      <td>{{ d.description }}</td>
                      <td>{{ d.amount_jod }}</td>
                      <td>{{ d.paid_jod }}</td>
                      <td>{{ d.remaining_jod }}</td>
                      <td>
                        <VChip :color="statusColor[d.status]" size="small">
                          {{ d.status_label }}
                        </VChip>
                      </td>
                      <td class="text-end text-no-wrap">
                        <VBtn icon="tabler-edit" size="x-small" variant="text" @click="openEditDue(d)" />
                        <VBtn
                          icon="tabler-trash"
                          size="x-small"
                          variant="text"
                          color="error"
                          @click="deleteDueMutation.mutate(d.id)"
                        />
                      </td>
                    </tr>
                  </tbody>
                </VTable>
              </td>
            </tr>
          </template>

          <tr v-if="!isLoading && !(data?.items ?? []).length">
            <td colspan="8" class="text-center text-medium-emphasis py-8">
              لا توجد ذمم مسجَّلة
            </td>
          </tr>
        </tbody>
      </VTable>

      <VCardText v-if="(data?.last_page ?? 1) > 1" class="d-flex justify-center">
        <VPagination v-model="page" :length="data?.last_page ?? 1" :total-visible="7" />
      </VCardText>
    </VCard>

    <!-- ─── Dialog تسجيل دفعة واردة ─── -->
    <VDialog v-model="payDialog" max-width="560">
      <VCard title="تسجيل دفعة واردة من الشركة">
        <VCardText>
          <p class="text-body-2 mb-4">
            {{ paying?.name }} — المتبقي عليه:
            <strong>{{ paying?.remaining_jod }} د.أ</strong>
            <br>
            <span class="text-medium-emphasis">تُوزَّع الدفعة تلقائياً على الذمم غير المسدَّدة، الأقدم أولاً.</span>
          </p>
          <VRow>
            <VCol cols="12" md="6">
              <VTextField v-model="payForm.amount" label="المبلغ المستلم" type="number" dir="ltr" />
            </VCol>
            <VCol cols="12" md="6">
              <VSelect
                v-model="payForm.currency"
                :items="currencies"
                item-title="label"
                item-value="value"
                label="العملة"
              />
            </VCol>
            <VCol v-if="payForm.currency !== 'JOD'" cols="12" md="6">
              <VTextField
                v-model="payForm.exchange_rate"
                :label="`سعر الصرف (1 ${payForm.currency} = ? د.أ)`"
                type="number"
                step="0.000001"
                dir="ltr"
                hint="مقترح من آخر تحديث — قابل للتعديل"
                persistent-hint
              />
            </VCol>
            <VCol cols="12" :md="payForm.currency !== 'JOD' ? 6 : 12">
              <VSelect
                v-model="payForm.method"
                :items="[
                  { title: 'نقداً', value: 'cash' },
                  { title: 'تحويل بنكي', value: 'bank_transfer' },
                  { title: 'شيك', value: 'cheque' },
                ]"
                label="طريقة الدفع"
              />
            </VCol>
            <VCol cols="12" md="6">
              <VTextField v-model="payForm.reference_number" label="رقم مرجعي (اختياري)" dir="ltr" />
            </VCol>
            <VCol cols="12" md="6">
              <VTextField v-model="payForm.notes" label="ملاحظة (اختياري)" dir="rtl" />
            </VCol>
          </VRow>

          <VAlert v-if="payJodEquivalent" type="info" variant="tonal" density="compact" class="mt-2">
            المعادل بالدينار: <strong>{{ payJodEquivalent }} د.أ</strong>
          </VAlert>
        </VCardText>
        <VCardActions class="justify-end pb-4 px-6">
          <VBtn variant="tonal" color="secondary" @click="payDialog = false">إلغاء</VBtn>
          <VBtn
            color="success"
            :loading="payMutation.isPending.value"
            :disabled="!payForm.amount || (payForm.currency !== 'JOD' && !payForm.exchange_rate)"
            @click="payMutation.mutate()"
          >
            تسجيل الدفعة
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- ─── Dialog استيراد إكسل ─── -->
    <VDialog v-model="importDialog" max-width="760">
      <VCard title="استيراد كشف الذمم من ملف إكسل">
        <VCardText>
          <VAlert type="info" variant="tonal" density="compact" class="mb-4">
            الملف بنفس بنية "كشف المقاولين للأرشفة الإلكترونية": لكل شركة صفوف تخصصاتها
            وأعمدة رسوم 2020 (خصم 50%) و2021 (خصم 30%) وصف "المجموع".
            المطابقة تتم برقم العضوية.
          </VAlert>

          <VFileInput
            label="ملف الإكسل (.xlsx)"
            accept=".xlsx,.xls"
            prepend-icon="tabler-file-spreadsheet"
            @update:model-value="onImportFileChange"
          />

          <VSwitch
            v-model="importCreateMissing"
            label="إنشاء الشركات غير الموجودة تلقائياً كمقاولين جدد (بالاسم ورقم العضوية والمفوض والتصنيف)"
            color="success"
            hide-details
            class="mt-2"
          />
          <VSwitch
            v-model="importForce"
            label="استبدال الاستيراد السابق (يحذف كل الذمم المستوردة سابقاً ويعيد استيرادها)"
            color="error"
            hide-details
            class="mt-2"
          />

          <!-- ─── نتيجة المعاينة/الاستيراد ─── -->
          <template v-if="importResult">
            <VDivider class="my-4" />
            <VRow dense>
              <VCol cols="6" md="3">
                <p class="text-caption text-medium-emphasis mb-0">شركات في الكشف</p>
                <strong>{{ importResult.companies_total }}</strong>
              </VCol>
              <VCol cols="6" md="3">
                <p class="text-caption text-medium-emphasis mb-0">مطابَقة</p>
                <strong class="text-success">{{ importResult.matched_count }}</strong>
              </VCol>
              <VCol cols="6" md="3">
                <p class="text-caption text-medium-emphasis mb-0">غير مطابَقة</p>
                <strong class="text-error">{{ importResult.unmatched_count }}</strong>
              </VCol>
              <VCol cols="6" md="3">
                <p class="text-caption text-medium-emphasis mb-0">إجمالي الذمم (د.أ)</p>
                <strong>{{ importResult.total_matched_jod }}</strong>
                <span class="text-caption text-disabled"> / {{ importResult.total_sheet_jod }}</span>
              </VCol>
            </VRow>

            <VAlert
              v-if="!importResult.dry_run"
              type="success"
              variant="tonal"
              density="compact"
              class="mt-3"
            >
              تم استيراد {{ importResult.imported_count }} ذمة فعلياً<template v-if="importResult.contractors_created">
                وإنشاء {{ importResult.contractors_created }} شركة جديدة في النظام</template>.
            </VAlert>

            <template v-if="importResult.unmatched?.length">
              <p class="text-body-2 font-weight-bold mt-4 mb-2" :class="importCreateMissing ? 'text-success' : 'text-error'">
                {{ importCreateMissing
                  ? 'شركات غير موجودة في النظام — ستُنشأ تلقائياً مع ذممها عند الاستيراد الفعلي:'
                  : 'شركات غير مطابَقة (لن تُستورد — تُراجع يدوياً):' }}
              </p>
              <VTable density="compact">
                <thead>
                  <tr>
                    <th>م</th>
                    <th>رقم العضوية</th>
                    <th>اسم الشركة</th>
                    <th>ذمم (د.أ)</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="u in importResult.unmatched" :key="u.seq">
                    <td>{{ u.seq }}</td>
                    <td dir="ltr">{{ u.membership_number || '—' }}</td>
                    <td>{{ u.name }}</td>
                    <td>{{ u.dues?.reduce((s: number, d: any) => s + Number(d.amount_jod), 0) ?? 0 }}</td>
                  </tr>
                </tbody>
              </VTable>
            </template>
          </template>
        </VCardText>

        <VCardActions class="justify-end pb-4 px-6">
          <VBtn variant="tonal" color="secondary" @click="importDialog = false">إغلاق</VBtn>
          <VBtn
            variant="tonal"
            color="info"
            :disabled="!importFile"
            :loading="importMutation.isPending.value"
            @click="importMutation.mutate(true)"
          >
            معاينة (بدون كتابة)
          </VBtn>
          <VBtn
            color="success"
            :disabled="!importFile"
            :loading="importMutation.isPending.value"
            @click="importMutation.mutate(false)"
          >
            استيراد فعلي
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- ─── Dialog إضافة/تعديل ذمة ─── -->
    <VDialog v-model="dueDialog" max-width="640">
      <VCard :title="editingDue ? 'تعديل ذمة مالية' : 'إضافة ذمة مالية'">
        <VCardText>
          <VRow>
            <VCol v-if="!editingDue" cols="12">
              <VAutocomplete
                v-model="dueForm.contractor_id"
                v-model:search="contractorSearch"
                :items="contractorOptions"
                :item-title="(c: any) => `${c.name} (${c.membership_number})`"
                item-value="id"
                label="المقاول"
                placeholder="ابحث بالاسم أو رقم العضوية..."
                no-data-text="اكتب حرفين على الأقل للبحث"
              />
            </VCol>
            <VCol cols="12" md="8">
              <VTextField v-model="dueForm.description" label="البيان" dir="rtl" />
            </VCol>
            <VCol cols="12" md="4">
              <VTextField v-model="dueForm.amount_jod" label="المبلغ (د.أ)" type="number" dir="ltr" />
            </VCol>
            <VCol cols="12" md="4">
              <VTextField v-model="dueForm.year" label="السنة (اختياري)" type="number" dir="ltr" />
            </VCol>
            <VCol cols="12" md="4">
              <VTextField v-model="dueForm.due_date" label="تاريخ الاستحقاق (اختياري)" type="date" />
            </VCol>
            <VCol cols="12">
              <VTextarea v-model="dueForm.notes" label="ملاحظات" rows="2" dir="rtl" />
            </VCol>
          </VRow>
        </VCardText>
        <VCardActions class="justify-end pb-4 px-6">
          <VBtn variant="tonal" color="secondary" @click="dueDialog = false">إلغاء</VBtn>
          <VBtn
            color="primary"
            :loading="saveDueMutation.isPending.value"
            :disabled="!editingDue && !dueForm.contractor_id"
            @click="saveDueMutation.mutate()"
          >
            حفظ
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>

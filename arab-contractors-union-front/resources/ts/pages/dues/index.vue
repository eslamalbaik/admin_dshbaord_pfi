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
  discount_amount_jod: string | number | null
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
  // تاريخ الاستحقاق محصور بتاريخ اليوم أو بعده، إلا بتفعيل هذين صراحةً لتسجيل ذمة
  // متأخّرة سابقة (TASK-17 #7) — قرار واعٍ بسبب مكتوب، لا تاريخ مكتوب بالخطأ.
  allow_backdate: false,
  backdate_reason: '',
})

const todayIso = new Date().toISOString().slice(0, 10)

const contractorSearch = ref('')
const contractorOptions = ref<{ id: number; name: string; membership_number: string }[]>([])
let contractorTimer: ReturnType<typeof setTimeout> | null = null

// When a contractor is selected, Vuetify's VAutocomplete rewrites `contractorSearch`
// to the selected item's display label (e.g. "اسم (12345)"). Without this guard that
// rewrite re-triggers the debounced search below, which refetches contractorOptions
// with that label as the query, drops the selected id from the results, and makes the
// autocomplete render blank/"غير معرف" even though dueForm.contractor_id is still set.
const justSelectedContractor = ref(false)

watch(() => dueForm.value.contractor_id, () => {
  justSelectedContractor.value = true
})

watch(contractorSearch, q => {
  if (justSelectedContractor.value) {
    justSelectedContractor.value = false
    return
  }
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
    allow_backdate: false,
    backdate_reason: '',
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
    // التعديل لا يخضع لقاعدة التاريخ (مسار PATCH لا يفرضها): منع تعديل غير متعلّق بالتاريخ
    // على ذمة قديمة لأن تاريخها ماضٍ يكرّر نمط TASK-01 — قاعدة جديدة تقفل سجلات قائمة.
    allow_backdate: false,
    backdate_reason: '',
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
    if (dueForm.value.allow_backdate) {
      payload.allow_backdate = true
      payload.backdate_reason = dueForm.value.backdate_reason
    }

    return (await api.post('/api/v1/dashboard/dues', payload)).data
  },
  onSuccess: () => {
    dueDialog.value = false
    flash('تم حفظ الذمة المالية بنجاح.')
    refreshAll()
  },
  onError: (e: any) => flash(
    e?.response?.data?.errors?.due_date?.[0]
    || e?.response?.data?.errors?.backdate_reason?.[0]
    || e?.response?.data?.message
    || 'فشل حفظ الذمة.',
    true,
  ),
})

const deleteDueMutation = useMutation({
  mutationFn: async (id: number) => (await api.delete(`/api/v1/dashboard/dues/${id}`)).data,
  onSuccess: () => {
    flash('تم حذف الذمة.')
    refreshAll()
  },
  // بدون هذا كان فشل الحذف صامتاً تماماً — وهو تحديداً الشكل الذي يُقنع المستخدم أن
  // الصفوف حُذفت وأن بطاقات الإجمالي "لم تتحدّث" (TASK-17 #9).
  onError: (e: any) => flash(e?.response?.data?.message || 'فشل حذف الذمة — لم يُحذف شيء.', true),
})

const deleteDueDialog = ref(false)
const deletingDueId = ref<number | null>(null)

function confirmDeleteDue(id: number) {
  deletingDueId.value = id
  deleteDueDialog.value = true
}

function deleteDueConfirmed() {
  if (deletingDueId.value !== null)
    deleteDueMutation.mutate(deletingDueId.value)
  deleteDueDialog.value = false
  deletingDueId.value = null
}

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
      importDialog.value = false
      flash(d.message ?? 'تم الاستيراد بنجاح.')
      refreshAll()
    }
  },
  onError: (e: any) => flash(e?.response?.data?.message || 'فشل الاستيراد.', true),
})

// ─── احسب الرسوم (محرّك الاحتساب الآلي — المادة 37) ───
const calcDialog = ref(false)
const calculatingFor = ref<ContractorRow | null>(null)
const calcForm = ref({ year: new Date().getFullYear(), discount_type: '' as '' | 'percent' | 'fixed', discount_value: '', discount_reason: '' })
const calcPreview = ref<any>(null)
const calcError = ref('')

function openCalculateFees(c: ContractorRow) {
  calculatingFor.value = c
  calcForm.value = { year: new Date().getFullYear(), discount_type: '', discount_value: '', discount_reason: '' }
  calcPreview.value = null
  calcError.value = ''
  calcDialog.value = true
}

function calcPayload() {
  const payload: any = { year: calcForm.value.year }
  if (calcForm.value.discount_type) {
    payload.discount_type = calcForm.value.discount_type
    payload.discount_value = calcForm.value.discount_value
    payload.discount_reason = calcForm.value.discount_reason || undefined
  }

  return payload
}

const previewFeeMutation = useMutation({
  mutationFn: async () => (await api.post(
    `/api/v1/dashboard/contractors/${calculatingFor.value!.contractor_id}/dues/calculate-fee`,
    calcPayload(),
  )).data,
  onSuccess: (d: any) => {
    calcPreview.value = d.items
    calcError.value = ''
  },
  onError: (e: any) => {
    calcPreview.value = e?.response?.data?.errors?.breakdown ?? null
    calcError.value = e?.response?.data?.message || 'تعذّر احتساب الرسوم.'
  },
})

const generateFeeMutation = useMutation({
  mutationFn: async (force: boolean) => (await api.post(
    `/api/v1/dashboard/contractors/${calculatingFor.value!.contractor_id}/dues/generate-fee`,
    { ...calcPayload(), force },
  )).data,
  onSuccess: (d: any) => {
    calcDialog.value = false
    flash(d.items?.warning ? `${d.message} — ${d.items.warning}` : d.message)
    refreshAll()
  },
  onError: (e: any) => {
    if (e?.response?.status === 409) {
      calcError.value = `${e.response.data.message} (رقم الذمة: ${e.response.data.errors?.existing_due_id})`
    }
    else {
      calcError.value = e?.response?.data?.message || 'فشل توليد ذمة الرسوم.'
    }
  },
})

// ─── توليد للكل (دفعة واحدة) ───
const bulkGenDialog = ref(false)
const bulkGenForm = ref({ year: new Date().getFullYear() })
const bulkGenResult = ref<any>(null)

function openBulkGenerate() {
  bulkGenForm.value = { year: new Date().getFullYear() }
  bulkGenResult.value = null
  bulkGenDialog.value = true
}

const bulkGenMutation = useMutation({
  mutationFn: async (dryRun: boolean) => (await api.post('/api/v1/dashboard/dues/generate-fee/bulk', {
    year: bulkGenForm.value.year,
    dry_run: dryRun,
  }, { timeout: 300000 })).data,
  onSuccess: (d: any, dryRun: boolean) => {
    bulkGenResult.value = d.items
    if (dryRun) return

    // التطبيق الفعلي يُغلق المودل ويُبلّغ نتيجته دائماً — بما فيها "لم يُولَّد شيء". قبل ذلك
    // كان المودل يبقى مفتوحاً، ولا رسالة إطلاقاً عند created_count = 0 (TASK-17 #11).
    bulkGenDialog.value = false
    flash(d.items?.created_count
      ? `تم توليد ${d.items.created_count} ذمة رسوم.`
      : 'لم تُولَّد أي ذمة جديدة — الرسوم مولَّدة مسبقاً لهذه السنة.')
    refreshAll()
  },
  onError: (e: any) => flash(e?.response?.data?.message || 'فشل التوليد الجماعي.', true),
})

// ─── خصم جماعي — اختيار صريح (checkboxes) ───
const selectedDueIds = ref<number[]>([])

watch(expandedDues, () => selectedDueIds.value = [])

const allDuesSelected = computed(() =>
  expandedDues.value.length > 0 && selectedDueIds.value.length === expandedDues.value.length)

function toggleSelectAllDues() {
  selectedDueIds.value = allDuesSelected.value ? [] : expandedDues.value.map(d => d.id)
}

const selectedDiscountDialog = ref(false)
const selectedDiscountForm = ref({ discount_type: 'percent' as 'percent' | 'fixed', discount_value: '', discount_reason: '' })
const selectedDiscountPreview = ref<any>(null)

function openSelectedDiscount() {
  selectedDiscountForm.value = { discount_type: 'percent', discount_value: '', discount_reason: '' }
  selectedDiscountPreview.value = null
  selectedDiscountDialog.value = true
}

// dry_run مدعوم أصلاً بالباك (DuesDiscountService::applyBulk، مود ids ومعايير على حدّ سواء) —
// الفجوة كانت فقط بعدم عرض معاينة قبل التطبيق لمود "المحدَّد بالـ checkboxes" (سؤال #7 بالشيت).
const applySelectedDiscountMutation = useMutation({
  mutationFn: async (dryRun: boolean) => (await api.post('/api/v1/dashboard/dues/discount/bulk', {
    mode: 'ids',
    ids: selectedDueIds.value,
    ...selectedDiscountForm.value,
    dry_run: dryRun,
  })).data,
  onSuccess: (d: any) => {
    if (d.items?.is_dry_run) {
      selectedDiscountPreview.value = d.items
      return
    }
    selectedDiscountDialog.value = false
    selectedDueIds.value = []
    flash(`تم تطبيق الخصم على ${d.items?.applied_count ?? 0} ذمة.`)
    refreshAll()
  },
  onError: (e: any) => flash(e?.response?.data?.message || 'فشل تطبيق الخصم.', true),
})

// ─── خصم جماعي — بمعايير (سنة/حالة/مصدر) عبر كل المقاولين ───
const criteriaDiscountDialog = ref(false)
const criteriaForm = ref({
  contractor_ids: [] as number[],
  year: '' as string | number,
  status: '' as string,
  source: '' as string,
  discount_type: 'percent' as 'percent' | 'fixed',
  discount_value: '',
  discount_reason: '',
})
const criteriaPreview = ref<any>(null)

// بحث المقاولين لمود المعايير — منفصل عن قائمة نموذج الذمة حتى لا يبتلع أحدهما اختيار الآخر
const criteriaContractorSearch = ref('')
const criteriaContractorOptions = ref<{ id: number; name: string; membership_number: string }[]>([])
let criteriaContractorTimer: ReturnType<typeof setTimeout> | null = null

const contractorLabel = (c: { name: string; membership_number: string }) => `${c.name} (${c.membership_number})`

watch(criteriaContractorSearch, q => {
  if (criteriaContractorTimer) clearTimeout(criteriaContractorTimer)
  criteriaContractorTimer = setTimeout(async () => {
    if (!q || q.length < 2) return
    try {
      const r = await api.get('/api/v1/contractors', { params: { search: q, per_page: 10 } })
      const found = (r.data.items ?? r.data.data ?? []).map((c: any) => ({
        id: c.id,
        name: c.name,
        membership_number: c.membership_number,
      }))

      // الاختيارات المحدَّدة سابقاً تبقى ضمن items، وإلا اختفت رقائقها (chips) عند كل بحث جديد
      const kept = criteriaContractorOptions.value.filter(o => criteriaForm.value.contractor_ids.includes(o.id))
      const merged = [...kept]
      for (const c of found) {
        if (!merged.some(m => m.id === c.id)) merged.push(c)
      }
      criteriaContractorOptions.value = merged
    }
    catch {}
  }, 350)
})

// معيار واحد على الأقل إلزامي — الباك يرفض كائن معايير فارغاً (كان يطابق كل ذمم النظام)،
// والواجهة تمنع الإرسال قبل ذلك بدل ترك المستخدم يصطدم بـ422.
const criteriaHasAnyFilter = computed(() =>
  criteriaForm.value.contractor_ids.length > 0
  || !!criteriaForm.value.year
  || !!criteriaForm.value.status
  || !!criteriaForm.value.source)

const criteriaScopedToContractors = computed(() => criteriaForm.value.contractor_ids.length > 0)

function openCriteriaDiscount() {
  criteriaForm.value = { contractor_ids: [], year: '', status: '', source: '', discount_type: 'percent', discount_value: '', discount_reason: '' }
  criteriaPreview.value = null
  criteriaContractorSearch.value = ''
  criteriaContractorOptions.value = []
  criteriaDiscountDialog.value = true
}

function criteriaPayload() {
  return {
    mode: 'criteria',
    criteria: {
      contractor_ids: criteriaForm.value.contractor_ids.length ? criteriaForm.value.contractor_ids : undefined,
      year: criteriaForm.value.year || undefined,
      status: criteriaForm.value.status || undefined,
      source: criteriaForm.value.source || undefined,
    },
    discount_type: criteriaForm.value.discount_type,
    discount_value: criteriaForm.value.discount_value,
    discount_reason: criteriaForm.value.discount_reason || undefined,
  }
}

const criteriaDiscountMutation = useMutation({
  mutationFn: async (dryRun: boolean) => (await api.post('/api/v1/dashboard/dues/discount/bulk', {
    ...criteriaPayload(),
    dry_run: dryRun,
  })).data,
  onSuccess: (d: any) => {
    if (d.items?.is_dry_run) {
      criteriaPreview.value = d.items
      return
    }
    // تطبيق فعلي ناجح — يُغلق المودل فوراً ويحدّث الجدول بدل بقائه مفتوحاً بلا أي تغيير
    // ظاهر حتى يعيد المستخدم تحميل الصفحة يدوياً (شكوى الشيت #7).
    criteriaDiscountDialog.value = false
    criteriaPreview.value = null
    const skipped = d.items?.skipped?.length ?? 0
    flash(`تم تطبيق الخصم على ${d.items?.applied_count ?? 0} ذمة`
      + ` لدى ${d.items?.contractors_count ?? 0} مقاولاً.`
      + (skipped > 0 ? ` (${skipped} ذمة متخطّاة)` : ''))
    refreshAll()
  },
  onError: (e: any) => flash(
    e?.response?.data?.errors?.criteria?.[0] || e?.response?.data?.message || 'فشل تطبيق الخصم الجماعي.',
    true,
  ),
})

// أي تعديل على المعايير أو قيمة الخصم يُبطل المعاينة — وإلا أمكن معاينة مجموعة ثم تطبيق
// الخصم على مجموعة أخرى بالأرقام القديمة معروضة على الشاشة.
watch(criteriaForm, () => criteriaPreview.value = null, { deep: true })
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
      <div class="d-flex gap-2 flex-wrap">
        <VBtn
          variant="tonal"
          color="info"
          prepend-icon="tabler-percentage"
          @click="openCriteriaDiscount"
        >
          خصم جماعي بمعايير
        </VBtn>
        <VBtn
          variant="tonal"
          color="primary"
          prepend-icon="tabler-calculator"
          @click="openBulkGenerate"
        >
          توليد رسوم للكل
        </VBtn>
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
            <!-- عدد الذمم التي احتُسب منها الرقم: البطاقة على مستوى النظام كله بينما الجدول
                 مجمّع حسب المقاول ومقسّم على صفحات، فبدون العدد لا سبيل للتوفيق بينهما —
                 وهو ما قاد لظنّ أن كل الذمم حُذفت والبطاقة "لم تتحدّث" (TASK-17 #9). -->
            <p class="text-caption text-medium-emphasis mb-0">
              {{ summary?.items?.outstanding_dues_count ?? 0 }} ذمة قائمة من أصل
              {{ summary?.items?.dues_count ?? 0 }} في النظام
            </p>
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
      <VCol cols="12" md="3">
        <VCard>
          <VCardText>
            <p class="text-body-2 text-medium-emphasis mb-1">رسوم تسجيل (أول انتساب)</p>
            <h3 class="text-h5">{{ summary?.items?.registration_fees?.total_jod ?? '—' }} د.أ</h3>
            <p class="text-caption text-medium-emphasis mb-0">{{ summary?.items?.registration_fees?.dues_count ?? 0 }} ذمة</p>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <!-- ─── توزيع الذمم حسب السنة ─── -->
    <VCard v-if="summary?.items?.by_year?.length" class="mb-6">
      <VCardTitle>توزيع الذمم حسب السنة</VCardTitle>
      <VCardText>
        <VRow>
          <VCol v-for="year in summary.items.by_year" :key="year.year ?? 'accumulated'" cols="12" sm="6" md="4" lg="3">
            <VCard variant="outlined">
              <VCardText class="text-center">
                <p class="text-caption text-medium-emphasis mb-2">
                  {{ year.year ? `سنة ${year.year}` : 'رسوم متراكمة (قبل 2025)' }}
                </p>
                <p class="text-h6 mb-1">{{ Number(year.total_jod).toFixed(2) }} د.أ</p>
                <p class="text-caption" :class="year.outstanding_jod > 0 ? 'text-error' : 'text-success'">
                  متبقي: {{ Number(year.outstanding_jod).toFixed(2) }} د.أ
                </p>
              </VCardText>
            </VCard>
          </VCol>
        </VRow>
      </VCardText>
    </VCard>

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

      <div class="overflow-x-auto">
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
                  icon="tabler-calculator"
                  title="احسب الرسوم السنوية (محرّك الاحتساب الآلي)"
                  @click="openCalculateFees(c)"
                />
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
                <template v-else>
                <div v-if="selectedDueIds.length" class="d-flex align-center gap-3 pa-2" style="background: rgba(var(--v-theme-info), 0.08);">
                  <span class="text-body-2">{{ selectedDueIds.length }} ذمة محدَّدة</span>
                  <VBtn size="small" color="info" @click="openSelectedDiscount">تطبيق خصم على المحدَّد</VBtn>
                </div>
                <div class="overflow-x-auto">
                <VTable density="compact" style="background: transparent;">
                  <thead>
                    <tr>
                      <th style="inline-size: 36px;">
                        <VCheckboxBtn :model-value="allDuesSelected" @update:model-value="toggleSelectAllDues" />
                      </th>
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
                      <td><VCheckboxBtn v-model="selectedDueIds" :value="d.id" /></td>
                      <td>{{ d.year ?? '—' }}</td>
                      <td>
                        {{ d.description }}
                        <VChip v-if="d.source === 'fee_engine'" size="x-small" color="primary" variant="tonal" class="ms-1">محرّك الاحتساب</VChip>
                        <VChip v-if="d.discount_amount_jod" size="x-small" color="info" variant="tonal" class="ms-1">
                          خصم {{ d.discount_amount_jod }} د.أ
                        </VChip>
                      </td>
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
                          @click="confirmDeleteDue(d.id)"
                        />
                      </td>
                    </tr>
                  </tbody>
                </VTable>
                </div>
                </template>
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
      </div>

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
              <div class="overflow-x-auto">
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
              </div>
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
              <VTextField
                v-model="dueForm.due_date"
                label="تاريخ الاستحقاق (اختياري)"
                type="date"
                :min="!editingDue && !dueForm.allow_backdate ? todayIso : undefined"
              />
            </VCol>

            <!-- تسجيل ذمة متأخّرة سابقة — حالة مشروعة لكنها تستحق أن تكون قراراً واعياً
                 بسبب مكتوب، لا تاريخاً ماضياً مرَّ بالخطأ (TASK-17 #7). -->
            <VCol v-if="!editingDue" cols="12">
              <VCheckbox
                v-model="dueForm.allow_backdate"
                label="ذمة سابقة/متأخّرة — السماح بتاريخ استحقاق قبل اليوم"
                density="compact"
                hide-details
              />
            </VCol>
            <VCol v-if="!editingDue && dueForm.allow_backdate" cols="12">
              <VTextField
                v-model="dueForm.backdate_reason"
                label="سبب التاريخ السابق (إلزامي)"
                dir="rtl"
                :error="!dueForm.backdate_reason"
                hint="يُحفظ بملاحظات الذمة وبسجل المالية"
                persistent-hint
              />
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
            :disabled="saveDueMutation.isPending.value
              || (!editingDue && !dueForm.contractor_id)
              || (dueForm.allow_backdate && !dueForm.backdate_reason)"
            @click="saveDueMutation.mutate()"
          >
            حفظ
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- ─── Dialog احسب الرسوم (محرّك الاحتساب الآلي) ─── -->
    <VDialog v-model="calcDialog" max-width="720">
      <VCard :title="`احتساب رسوم العضوية — ${calculatingFor?.name ?? ''}`">
        <VCardText>
          <VAlert v-if="calcError" type="error" variant="tonal" density="compact" class="mb-4">
            {{ calcError }}
          </VAlert>

          <VRow dense>
            <VCol cols="12" md="4">
              <VTextField v-model.number="calcForm.year" label="السنة" type="number" dir="ltr" />
            </VCol>
            <VCol cols="12" md="4">
              <VSelect
                v-model="calcForm.discount_type"
                :items="[{ title: 'بدون خصم', value: '' }, { title: 'نسبة مئوية', value: 'percent' }, { title: 'مبلغ ثابت', value: 'fixed' }]"
                label="نوع الخصم (اختياري)"
              />
            </VCol>
            <VCol v-if="calcForm.discount_type" cols="12" md="4">
              <VTextField
                v-model="calcForm.discount_value"
                :label="calcForm.discount_type === 'percent' ? 'نسبة الخصم %' : 'مبلغ الخصم (د.أ)'"
                type="number"
                dir="ltr"
              />
            </VCol>
            <VCol v-if="calcForm.discount_type" cols="12">
              <VTextField v-model="calcForm.discount_reason" label="سبب الخصم" dir="rtl" />
            </VCol>
          </VRow>

          <VBtn
            class="mt-2"
            variant="tonal"
            color="primary"
            :loading="previewFeeMutation.isPending.value"
            @click="previewFeeMutation.mutate()"
          >
            احسب
          </VBtn>

          <template v-if="calcPreview">
            <VDivider class="my-4" />
            <div class="overflow-x-auto">
              <VTable density="compact">
                <thead>
                  <tr>
                    <th>المجال</th>
                    <th>الدرجة المعتمدة</th>
                    <th>النوع</th>
                    <th>النسبة</th>
                    <th>القيمة (د.أ)</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="f in calcPreview.breakdown?.fields ?? []" :key="f.field_id">
                    <td>{{ f.field_name }}</td>
                    <td>{{ f.counted_specialty?.grade_label ?? '—' }}</td>
                    <td>
                      <VChip v-if="f.fee_type === 'registration'" size="x-small" color="primary" variant="tonal">رسم تسجيل</VChip>
                      <span v-else>رسم سنوي</span>
                    </td>
                    <td>{{ f.rate_percent }}%</td>
                    <td>{{ f.amount_jod }}</td>
                  </tr>
                </tbody>
              </VTable>
            </div>
            <VAlert v-if="calcPreview.breakdown?.is_new_registration_year" type="info" variant="tonal" density="compact" class="mt-2">
              أول سنة انتساب — رسم التسجيل مُطبَّق على المجال الأعلى بدل الرسم السنوي.
            </VAlert>
            <VRow dense class="mt-2">
              <VCol cols="6" md="4">
                <p class="text-caption text-medium-emphasis mb-0">الإجمالي قبل الخصم</p>
                <strong>{{ calcPreview.total_before_discount_jod }} د.أ</strong>
              </VCol>
              <VCol v-if="calcPreview.discount_amount_jod" cols="6" md="4">
                <p class="text-caption text-medium-emphasis mb-0">قيمة الخصم</p>
                <strong class="text-info">{{ calcPreview.discount_amount_jod }} د.أ</strong>
              </VCol>
              <VCol cols="6" md="4">
                <p class="text-caption text-medium-emphasis mb-0">الإجمالي المستحَق</p>
                <strong class="text-success">{{ calcPreview.total_after_discount_jod }} د.أ</strong>
              </VCol>
            </VRow>
          </template>
        </VCardText>
        <VCardActions class="justify-end pb-4 px-6">
          <VBtn variant="tonal" color="secondary" @click="calcDialog = false">إلغاء</VBtn>
          <VBtn
            v-if="calcPreview && !calcPreview.unresolvable"
            color="success"
            :loading="generateFeeMutation.isPending.value"
            @click="generateFeeMutation.mutate(false)"
          >
            توليد الذمة
          </VBtn>
          <VBtn
            v-if="calcPreview && !calcPreview.unresolvable"
            variant="tonal"
            color="warning"
            :loading="generateFeeMutation.isPending.value"
            @click="generateFeeMutation.mutate(true)"
          >
            توليد (استبدال إن وُجدت)
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- ─── Dialog توليد رسوم للكل ─── -->
    <VDialog v-model="bulkGenDialog" max-width="720">
      <VCard title="توليد رسوم العضوية لجميع المقاولين">
        <VCardText>
          <VAlert type="info" variant="tonal" density="compact" class="mb-4">
            يُنشئ ذمة رسوم لكل مقاول لديه تخصصات مسجَّلة وليس لديه ذمة رسوم سابقة لنفس السنة.
            استخدم "معاينة" أولاً لمراجعة من سيُنشأ له ذمة ومن سيُستثنى (بيانات غير موحَّدة).
          </VAlert>

          <VTextField v-model.number="bulkGenForm.year" label="السنة" type="number" dir="ltr" style="max-width: 200px;" />

          <template v-if="bulkGenResult">
            <VDivider class="my-4" />
            <VRow dense>
              <VCol cols="4">
                <p class="text-caption text-medium-emphasis mb-0">سيُنشأ لهم / أُنشئ</p>
                <strong class="text-success">{{ bulkGenResult.would_create?.length ?? 0 }}</strong>
              </VCol>
              <VCol cols="4">
                <p class="text-caption text-medium-emphasis mb-0">لديهم ذمة مسبقاً (تُستثنى)</p>
                <strong>{{ bulkGenResult.would_skip_existing?.length ?? 0 }}</strong>
              </VCol>
              <VCol cols="4">
                <p class="text-caption text-medium-emphasis mb-0">بيانات غير موحَّدة (تُستثنى)</p>
                <strong class="text-error">{{ bulkGenResult.unresolvable?.length ?? 0 }}</strong>
              </VCol>
            </VRow>
          </template>
        </VCardText>
        <VCardActions class="justify-end pb-4 px-6">
          <VBtn variant="tonal" color="secondary" @click="bulkGenDialog = false">إغلاق</VBtn>
          <VBtn
            variant="tonal"
            color="info"
            :loading="bulkGenMutation.isPending.value"
            @click="bulkGenMutation.mutate(true)"
          >
            معاينة (بدون كتابة)
          </VBtn>
          <VBtn
            color="success"
            :loading="bulkGenMutation.isPending.value"
            @click="bulkGenMutation.mutate(false)"
          >
            توليد فعلي
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- ─── Dialog خصم على المحدَّد (checkboxes) ─── -->
    <VDialog v-model="selectedDiscountDialog" max-width="480">
      <VCard title="تطبيق خصم على الذمم المحدَّدة">
        <VCardText>
          <p class="text-body-2 mb-4">سيُطبَّق الخصم على {{ selectedDueIds.length }} ذمة محدَّدة.</p>
          <VRow dense>
            <VCol cols="12" md="6">
              <VSelect
                v-model="selectedDiscountForm.discount_type"
                :items="[{ title: 'نسبة مئوية', value: 'percent' }, { title: 'مبلغ ثابت', value: 'fixed' }]"
                label="نوع الخصم"
              />
            </VCol>
            <VCol cols="12" md="6">
              <VTextField
                v-model="selectedDiscountForm.discount_value"
                :label="selectedDiscountForm.discount_type === 'percent' ? 'نسبة الخصم %' : 'مبلغ الخصم (د.أ)'"
                type="number"
                dir="ltr"
              />
            </VCol>
            <VCol cols="12">
              <VTextField v-model="selectedDiscountForm.discount_reason" label="سبب الخصم" dir="rtl" />
            </VCol>
          </VRow>

          <template v-if="selectedDiscountPreview">
            <VDivider class="my-4" />
            <VRow dense>
              <VCol cols="6">
                <p class="text-caption text-medium-emphasis mb-0">عدد الذمم المطابقة</p>
                <strong>{{ selectedDiscountPreview.matched_count }}</strong>
              </VCol>
              <VCol cols="6">
                <p class="text-caption text-medium-emphasis mb-0">إجمالي أثر الخصم (د.أ)</p>
                <strong class="text-info">{{ selectedDiscountPreview.total_discount_impact_jod }}</strong>
              </VCol>
            </VRow>
          </template>
        </VCardText>
        <VCardActions class="justify-end pb-4 px-6">
          <VBtn variant="tonal" color="secondary" @click="selectedDiscountDialog = false">إلغاء</VBtn>
          <VBtn
            variant="tonal"
            color="info"
            :disabled="!selectedDiscountForm.discount_value"
            :loading="applySelectedDiscountMutation.isPending.value"
            @click="applySelectedDiscountMutation.mutate(true)"
          >
            معاينة (بدون تطبيق)
          </VBtn>
          <VBtn
            color="success"
            :disabled="!selectedDiscountForm.discount_value"
            :loading="applySelectedDiscountMutation.isPending.value"
            @click="applySelectedDiscountMutation.mutate(false)"
          >
            تطبيق الخصم
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- ─── Dialog تأكيد حذف الذمة ─── -->
    <VDialog v-model="deleteDueDialog" max-width="400">
      <VCard>
        <VCardTitle class="d-flex align-center gap-2">
          <VIcon icon="tabler-alert-triangle" color="error" />
          تأكيد الحذف
        </VCardTitle>
        <VCardText>
          هل أنت متأكد من حذف هذه الذمة؟ لا يمكن التراجع عن هذا الإجراء.
        </VCardText>
        <VCardActions class="justify-end pb-4 px-6">
          <VBtn variant="tonal" color="secondary" @click="deleteDueDialog = false">إلغاء</VBtn>
          <VBtn color="error" :loading="deleteDueMutation.isPending.value" @click="deleteDueConfirmed">حذف</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- ─── Dialog خصم جماعي بمعايير ─── -->
    <VDialog v-model="criteriaDiscountDialog" max-width="620">
      <VCard title="خصم جماعي بمعايير (مقاولون / سنة / حالة / مصدر)">
        <VCardText>
          <VAlert
            :type="criteriaScopedToContractors ? 'info' : 'warning'"
            variant="tonal"
            density="compact"
            class="mb-4"
          >
            <template v-if="criteriaScopedToContractors">
              الخصم محصور بالمقاولين المحدَّدين أدناه. استخدم "معاينة" للتأكد من العدد والأثر قبل الالتزام.
            </template>
            <template v-else>
              <strong>تنبيه:</strong> بدون تحديد مقاولين، يطبَّق الخصم على كل الذمم المطابقة عبر <strong>جميع</strong> المقاولين.
              حدِّد المقاولين إن كنت تقصد بعضهم فقط.
            </template>
          </VAlert>

          <VRow dense>
            <VCol cols="12">
              <!-- حصر الخصم بمقاولين محدَّدين: قبل إضافته كان مود المعايير يطابق ذمم كل
                   المقاولين بنفس السنة، فيظهر عدد أكبر بكثير من المقصود (TASK-17 #10). -->
              <VAutocomplete
                v-model="criteriaForm.contractor_ids"
                v-model:search="criteriaContractorSearch"
                :items="criteriaContractorOptions"
                :item-title="contractorLabel"
                item-value="id"
                label="المقاولون (اختياري — اتركه فارغاً ليشمل الجميع)"
                multiple
                chips
                closable-chips
                no-filter
                hide-details="auto"
                hint="اكتب حرفين على الأقل للبحث"
                persistent-hint
              />
            </VCol>
            <VCol cols="12" md="4">
              <VTextField v-model="criteriaForm.year" label="السنة (اختياري)" type="number" dir="ltr" />
            </VCol>
            <VCol cols="12" md="4">
              <VSelect
                v-model="criteriaForm.status"
                :items="[{ title: 'أي حالة (غير المسدَّدة بالكامل)', value: '' }, { title: 'غير مسدَّدة', value: 'unpaid' }, { title: 'مسدَّدة جزئياً', value: 'partially_paid' }]"
                label="الحالة (اختياري)"
                hint="الذمم المسدَّدة بالكامل مستثناة دائماً — لا خصم يُطبَّق عليها"
                persistent-hint
              />
            </VCol>
            <VCol cols="12" md="4">
              <VSelect
                v-model="criteriaForm.source"
                :items="[{ title: 'أي مصدر', value: '' }, { title: 'محرّك الاحتساب', value: 'fee_engine' }, { title: 'يدوي', value: 'manual' }, { title: 'استيراد قديم', value: 'legacy_import' }]"
                label="المصدر (اختياري)"
              />
            </VCol>
            <VCol cols="12" md="6">
              <VSelect
                v-model="criteriaForm.discount_type"
                :items="[{ title: 'نسبة مئوية', value: 'percent' }, { title: 'مبلغ ثابت', value: 'fixed' }]"
                label="نوع الخصم"
              />
            </VCol>
            <VCol cols="12" md="6">
              <VTextField
                v-model="criteriaForm.discount_value"
                :label="criteriaForm.discount_type === 'percent' ? 'نسبة الخصم %' : 'مبلغ الخصم (د.أ)'"
                type="number"
                dir="ltr"
              />
            </VCol>
            <VCol cols="12">
              <VTextField v-model="criteriaForm.discount_reason" label="سبب الخصم" dir="rtl" />
            </VCol>
          </VRow>

          <VAlert
            v-if="!criteriaHasAnyFilter"
            type="error"
            variant="tonal"
            density="compact"
            class="mt-4"
          >
            حدِّد معياراً واحداً على الأقل (مقاولون أو سنة أو حالة أو مصدر). الخصم الجماعي بلا معايير يشمل كل ذمم النظام.
          </VAlert>

          <template v-if="criteriaPreview && 'total_discount_impact_jod' in criteriaPreview">
            <VDivider class="my-4" />
            <VRow dense>
              <VCol cols="12" md="4">
                <p class="text-caption text-medium-emphasis mb-0">القابلة للخصم</p>
                <strong class="text-success">{{ criteriaPreview.applicable_count }}</strong>
                <span class="text-caption text-medium-emphasis"> من {{ criteriaPreview.matched_count }} مطابقة</span>
              </VCol>
              <VCol cols="12" md="4">
                <p class="text-caption text-medium-emphasis mb-0">عدد المقاولين المتأثّرين</p>
                <strong>{{ criteriaPreview.contractors_count }}</strong>
              </VCol>
              <VCol cols="12" md="4">
                <p class="text-caption text-medium-emphasis mb-0">إجمالي أثر الخصم (د.أ)</p>
                <strong class="text-info">{{ criteriaPreview.total_discount_impact_jod }}</strong>
              </VCol>
            </VRow>

            <!-- الذمم التي سيرفضها التطبيق — كانت تُعدّ ضمن "المطابقة" ويُضاف أثرها الكامل
                 للإجمالي، فيظهر للمستخدم رقم لا يتحقّق أبداً (TASK-17 #10). -->
            <VAlert
              v-if="criteriaPreview.skipped?.length"
              type="warning"
              variant="tonal"
              density="compact"
              class="mt-3"
            >
              {{ criteriaPreview.skipped.length }} ذمة لن يُطبَّق عليها الخصم:
              {{ criteriaPreview.skipped[0].reason }}
            </VAlert>
          </template>
        </VCardText>
        <VCardActions class="justify-end pb-4 px-6">
          <VBtn variant="tonal" color="secondary" @click="criteriaDiscountDialog = false">إغلاق</VBtn>
          <VBtn
            variant="tonal"
            color="info"
            :disabled="!criteriaForm.discount_value || !criteriaHasAnyFilter"
            :loading="criteriaDiscountMutation.isPending.value"
            @click="criteriaDiscountMutation.mutate(true)"
          >
            معاينة (بدون كتابة)
          </VBtn>
          <!-- المعاينة إلزامية قبل التطبيق في مود المعايير: هذا المسار يقدر يمسّ ذمم كل
               المقاولين بنداء واحد، فلا يصحّ أن يمرّ بلا رقم يراه المستخدم أولاً. -->
          <VBtn
            color="error"
            :disabled="!criteriaForm.discount_value || !criteriaHasAnyFilter || !criteriaPreview"
            :loading="criteriaDiscountMutation.isPending.value"
            @click="criteriaDiscountMutation.mutate(false)"
          >
            تطبيق فعلياً
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>

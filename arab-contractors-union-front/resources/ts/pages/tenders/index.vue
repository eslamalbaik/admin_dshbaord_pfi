<script setup lang="ts">
import api from '@/plugins/axios'
import { firstFile, toFileArray } from '@/utils/files'

definePage({ meta: { requiresAdmin: true,
    adminOnly: true } })

// ── الموعد النهائي: تاريخ + ساعة ودقيقة ──────────────
// يُدخَل بحقلين منفصلين (تاريخ / وقت) بالتوقيت المحلي، ويُرسَل ISO بتوقيت UTC (…Z) —
// الإرسال بلا منطقة زمنية كان يُخزَّن كأنه UTC فتظهر الساعة مزاحة بفرق التوقيت.
const pad2 = (n: number) => String(n).padStart(2, '0')
const localDateStr = (d: Date) => `${d.getFullYear()}-${pad2(d.getMonth() + 1)}-${pad2(d.getDate())}`

/** ISO من السيرفر → { date: 'YYYY-MM-DD', time: 'HH:mm' } بالتوقيت المحلي */
const splitDeadline = (value: string | null | undefined) => {
  if (!value)
    return { date: '', time: '' }
  const d = new Date(value)
  if (Number.isNaN(d.getTime()))
    return { date: '', time: '' }

  return { date: localDateStr(d), time: `${pad2(d.getHours())}:${pad2(d.getMinutes())}` }
}

/** تاريخ + وقت محليان → ISO UTC للإرسال ('' إن لم يُحدَّد تاريخ) */
const joinDeadline = (date: string, time: string) =>
  date ? new Date(`${date}T${time || '00:00'}`).toISOString() : ''

// أقرب تاريخ مسموح لانتهاء العطاء = تاريخ اليوم + 1 (نفس القاعدة مفروضة بالباك اند)
const tomorrowStr = () => {
  const d = new Date()
  d.setDate(d.getDate() + 1)

  return localDateStr(d)
}

const formatDeadline = (value: string | null | undefined) =>
  value
    ? new Date(value).toLocaleString('ar-PS', { year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit' })
    : '—'

const deadlineRules = [
  (v: string) => !v || v >= tomorrowStr() || 'يجب أن يكون تاريخ انتهاء العطاء غداً أو بعده',
]

// ── تصنيفات العطاءات (من جدول tender_categories — تُدار من صفحة التصنيفات) ──
const categories = ref<{ id: number, name: string, image_url: string | null, is_active: boolean }[]>([])

const fetchCategories = async () => {
  try {
    const { data } = await api.get('/api/v1/tender-categories')
    categories.value = data.items ?? []
  }
  catch (err) {
    console.error(err)
  }
}

const activeCategoryNames = computed(() => categories.value.filter(c => c.is_active).map(c => c.name))
const categoryImage = (name: string | null | undefined) => categories.value.find(c => c.name === name)?.image_url ?? null

// منتقي التعديل: الفعّالة + تصنيف العطاء الحالي حتى لو عُطِّل لاحقاً
const editCategoryOptions = computed(() => {
  const current = editTender.value.category
  const names = activeCategoryNames.value

  return current && !names.includes(current) ? [...names, current] : names
})

const loading = ref(false)
const tenders = ref<any[]>([])

// ── Snackbar (feedback) ───────────────────────────
const snackbar = ref(false)
const snackbarText = ref('')
const snackbarColor = ref('success')
const notify = (text: string, color: 'success' | 'error' = 'success') => {
  snackbarText.value = text
  snackbarColor.value = color
  snackbar.value = true
}
const search = ref('')
const statusFilter = ref('')
const categoryFilter = ref('')
const updatedFromFilter = ref('')
const sortFilter = ref('latest')

const sortOptions = [
  { title: 'الأحدث إضافة', value: 'latest' },
  { title: 'آخر تحديث', value: 'updated_desc' },
  { title: 'الأقرب موعداً', value: 'deadline_asc' },
  { title: 'الأعلى ميزانية', value: 'budget_desc' },
]
const page = ref(1)
const total = ref(0)

// ── Edit ──────────────────────────────────────────
const editDialog  = ref(false)
const editLoading = ref(false)
const editTender  = ref<any>({})

const openEdit = async (item: any) => {
  editTender.value = {
    id:               item.id,
    title:            item.title,
    description:      item.description    || '',
    union_notes:      item.union_notes    || '',
    category:         item.category       || '',
    deadline_date:    splitDeadline(item.deadline).date,
    deadline_time:    splitDeadline(item.deadline).time,
    status:           item.status,
    submission_types: Array.isArray(item.submission_types) ? [...item.submission_types] : [],
    submission_email: item.submission_email || '',
    submission_phone: item.submission_phone || '',
    submission_file:  null,
    external_url:     item.external_url || '',
    issuing_entity:   item.issuing_entity || '',
  }
  originalDeadline.value = item.deadline ? new Date(item.deadline).toISOString() : ''
  existingSubmissionFile.value = item.submission_file || ''
  attachments.value = Array.isArray(item.attachments) ? item.attachments.map(normalizeAttachment) : []
  editDialog.value = true

  // القائمة الإدارية ما بترجّع attachments (تفادياً لتحميل زايد على كل صف) — نجيبها وقت فتح التعديل
  try {
    const { data } = await api.get(`/api/v1/tenders/${item.id}`)
    attachments.value = (data.items?.attachments ?? []).map(normalizeAttachment)
  } catch (err) {
    console.error(err)
  }
}

// ملف التقديم الحالي المخزَّن (رابط) — يبقى محفوظاً بعد أي حفظ ما لم يُختر ملف بديل
const existingSubmissionFile = ref('')
const fileNameFromUrl = (url: string) => decodeURIComponent(url.split('/').pop() || 'ملف التقديم')

// الموعد الأصلي — تعديل عطاء انتهى موعده دون لمس الموعد لا يخضع لشرط "غداً أو بعده"
const originalDeadline = ref('')
const editDeadlineRules = [
  (v: string) => {
    const changed = joinDeadline(v, editTender.value.deadline_time) !== originalDeadline.value

    return !changed || !v || v >= tomorrowStr() || 'يجب أن يكون تاريخ انتهاء العطاء غداً أو بعده'
  },
]

// ── مرفقات العطاء (متعددة — مستندات وصور) ──────────
// GET tenders/{id} يرجّع المرفق بـ file_url بينما رفع مرفق يرجّعه بـ url — الاعتماد على url
// وحده كان يترك روابط المرفقات المحمَّلة من السيرفر فارغة (غير قابلة للفتح/المعاينة)
const normalizeAttachment = (a: any) => ({ ...a, url: a.url ?? a.file_url })

const attachments = ref<any[]>([])
const newAttachmentFiles = ref<File[]>([])
const attachmentUploading = ref(false)
const attachmentDeletingId = ref<number | null>(null)

const uploadAttachments = async () => {
  if (!editTender.value.id || !newAttachmentFiles.value.length)
    return
  attachmentUploading.value = true
  try {
    for (const file of newAttachmentFiles.value) {
      const fd = new FormData()
      fd.append('file', file)
      fd.append('label', file.name)
      const { data } = await api.post(`/api/v1/tenders/${editTender.value.id}/attachments`, fd)
      attachments.value.push(data.items)
    }
    newAttachmentFiles.value = []
    notify('تمت إضافة المرفقات بنجاح')
  } catch (err) {
    console.error(err)
    notify('تعذّر رفع أحد المرفقات', 'error')
  } finally {
    attachmentUploading.value = false
  }
}

// تأكيد قبل حذف مرفق (REQ-07 #4) — كان يُحذف فوراً بلا تأكيد
const deleteAttachmentDialog = ref(false)
const deletingAttachment = ref<any>(null)

const confirmRemoveAttachment = (att: any) => {
  deletingAttachment.value = att
  deleteAttachmentDialog.value = true
}

const removeAttachment = async () => {
  const attachmentId = deletingAttachment.value?.id
  if (!attachmentId)
    return
  attachmentDeletingId.value = attachmentId
  try {
    await api.delete(`/api/v1/tenders/${editTender.value.id}/attachments/${attachmentId}`)
    attachments.value = attachments.value.filter(a => a.id !== attachmentId)
    deleteAttachmentDialog.value = false
  } catch (err) {
    console.error(err)
    notify('تعذّر حذف المرفق', 'error')
  } finally {
    attachmentDeletingId.value = null
  }
}

const saveTender = async () => {
  const { valid } = await editForm.value?.validate() ?? { valid: true }
  if (!valid)
    return
  editLoading.value = true
  try {
    const formData = new FormData()
    formData.append('_method', 'PATCH')
    ;(['title','issuing_entity','description','union_notes','category','status','submission_email','submission_phone','external_url'] as const)
      .forEach(k => formData.append(k, (editTender.value as any)[k] ?? ''))
    formData.append('deadline', joinDeadline(editTender.value.deadline_date, editTender.value.deadline_time))
    // إرسال المصفوفة
    const types: string[] = editTender.value.submission_types ?? []
    types.forEach(t => formData.append('submission_types[]', t))
    if (editTender.value.submission_file)
      formData.append('submission_file', editTender.value.submission_file)
    await api.post(`/api/v1/tenders/${editTender.value.id}`, formData)
    editDialog.value = false
    notify('تم حفظ التعديلات بنجاح')
    fetchTenders()
  } catch (err: any) { console.error(err); notify(firstError(err) || 'تعذّر حفظ التعديلات', 'error') }
  finally { editLoading.value = false }
}

// ── View details (read-only) ───────────────────────
const viewDialog = ref(false)
const viewingItem = ref<any>(null)
const viewAttachments = ref<any[]>([])

const openView = async (item: any) => {
  viewingItem.value = item
  viewAttachments.value = Array.isArray(item.attachments) ? item.attachments.map(normalizeAttachment) : []
  viewDialog.value = true
  try {
    const { data } = await api.get(`/api/v1/tenders/${item.id}`)
    viewAttachments.value = (data.items?.attachments ?? []).map(normalizeAttachment)
  } catch (err) {
    console.error(err)
  }
}

// ── معاينة مرفق (صورة / PDF داخل النافذة، وغيرها فتح بتبويب جديد) ──
const previewDialog = ref(false)
const previewItem = ref<{ url: string, label: string, kind: 'image' | 'pdf' | 'other' } | null>(null)

const fileKind = (url: string, isImage?: boolean): 'image' | 'pdf' | 'other' => {
  const ext = (url ?? '').split('?')[0].split('.').pop()?.toLowerCase() ?? ''
  if (isImage || ['jpg', 'jpeg', 'png', 'webp', 'gif'].includes(ext))
    return 'image'

  return ext === 'pdf' ? 'pdf' : 'other'
}

const openPreview = (url: string, label: string, isImage?: boolean) => {
  if (!url) {
    notify('رابط المرفق غير متاح', 'error')

    return
  }
  const kind = fileKind(url, isImage)
  if (kind === 'other') {
    window.open(url, '_blank', 'noopener')

    return
  }
  previewItem.value = { url, label, kind }
  previewDialog.value = true
}

// أول رسالة تحقق من الباك اند (422) — بدل رسالة عامة لا تشرح السبب
const firstError = (err: any): string => {
  const errors = err?.response?.data?.errors
  if (errors && typeof errors === 'object') {
    const first = Object.values(errors)[0] as any

    return Array.isArray(first) ? first[0] : String(first)
  }

  return err?.response?.data?.message || ''
}

// ── Status ────────────────────────────────────────
const statusLoading = ref<number | null>(null)

const changeStatus = async (item: any, status: string) => {
  statusLoading.value = item.id
  try {
    await api.patch(`/api/v1/tenders/${item.id}`, { status })
    item.status = status
  } catch (err) { console.error(err) }
  finally { statusLoading.value = null }
}

// ── Delete ────────────────────────────────────────
const deleteDialog  = ref(false)
const deleteLoading = ref(false)
const deletingItem  = ref<any>(null)

const confirmDelete = (item: any) => { deletingItem.value = item; deleteDialog.value = true }

const deleteTender = async () => {
  deleteLoading.value = true
  try {
    await api.delete(`/api/v1/tenders/${deletingItem.value.id}`)
    deleteDialog.value = false
    fetchTenders()
  } catch (err) { console.error(err) }
  finally { deleteLoading.value = false }
}

const createDialog = ref(false)
const createLoading = ref(false)
const newTender = ref({
  title:            '',
  issuing_entity:   '',
  description:      '',
  union_notes:      '',
  category:         '',
  deadline_date:    '',
  deadline_time:    '',
  status:           'open' as string,
  submission_types: [] as string[],
  submission_email: '',
  submission_phone: '',
  submission_file:  null as File | null,
  external_url:     '',
})

// مرفق إضافي واحد فقط عند الإنشاء — تعدد المرفقات متاح من نافذة التعديل. يُرفع تلقائياً فور الإنشاء
const newAttachmentStaged = ref<File | null>(null)

const createForm = ref<any>(null)
const editForm = ref<any>(null)

const emptyNewTender = () => ({ title: '', issuing_entity: '', description: '', union_notes: '', category: '', deadline_date: '', deadline_time: '', status: 'open', submission_types: [] as string[], submission_email: '', submission_phone: '', submission_file: null as File | null, external_url: '' })

const openCreate = () => {
  newAttachmentStaged.value = null
  createDialog.value = true
}

const editTenderTyped = editTender as any

// toggle طريقة تقديم في نموذج الإنشاء
const toggleNewType = (val: string) => {
  const idx = newTender.value.submission_types.indexOf(val)
  if (idx === -1) newTender.value.submission_types.push(val)
  else newTender.value.submission_types.splice(idx, 1)
}

// toggle طريقة تقديم في نموذج التعديل
const toggleEditType = (val: string) => {
  const types: string[] = editTender.value.submission_types ?? []
  const idx = types.indexOf(val)
  if (idx === -1) types.push(val)
  else types.splice(idx, 1)
  editTender.value.submission_types = [...types]
}

const submissionOptions = [
  { label: 'بريد إلكتروني', value: 'email', icon: 'tabler-mail' },
  { label: 'رقم تواصل', value: 'phone', icon: 'tabler-phone' },
  { label: 'ملف مرفق', value: 'file', icon: 'tabler-file-upload' },
]


const headers = [
  { title: 'عنوان العطاء', key: 'title' },
  { title: 'التصنيف', key: 'category' },
  { title: 'ملاحظات الاتحاد', key: 'union_notes' },
  { title: 'تاريخ النشر', key: 'published_at' },
  { title: 'آخر موعد', key: 'deadline' },
  { title: 'المستجدات', key: 'display_status' },
  { title: 'الحالة', key: 'status' },
  { title: 'إجراءات', key: 'actions', sortable: false },
]

const displayStatusColor: Record<string, string> = {
  new: 'info',
  updated: 'primary',
  closing_soon: 'warning',
  closed: 'secondary',
}

const statusOptions = [
  { title: 'الكل', value: '' },
  { title: 'مفتوحة', value: 'open' },
  { title: 'مغلقة', value: 'closed' },
  { title: 'ملغية', value: 'cancelled' },
]

const fetchTenders = async () => {
  loading.value = true
  try {
    const { data } = await api.get('/api/v1/tenders', {
      params: {
        search: search.value,
        status: statusFilter.value,
        category: categoryFilter.value,
        updated_from: updatedFromFilter.value || undefined,
        sort: sortFilter.value || undefined,
        page: page.value,
      },
    })
    tenders.value = data.data || data || []
    total.value = data.total || tenders.value.length
  }
  catch {
    tenders.value = []
  }
  finally {
    loading.value = false
  }
}

const getStatusColor = (s: string) => ({ open: 'success', closed: 'error', cancelled: 'secondary' }[s] || 'info')
const getStatusLabel = (s: string) => ({ open: 'مفتوحة', closed: 'مغلقة', cancelled: 'ملغية' }[s] || s)

// ── تصدير كشف Excel لعطاءات طُرحت خلال فترة معيّنة (تاريخ النشر) ──
const exportDialog = ref(false)
const exportFrom = ref(new Date().toISOString().slice(0, 8) + '01') // أول الشهر الحالي افتراضياً
const exportTo = ref(new Date().toISOString().slice(0, 10))
const exporting = ref(false)

const runExport = async () => {
  exporting.value = true
  try {
    const { data } = await api.get('/api/v1/tenders', {
      params: {
        created_from: exportFrom.value || undefined,
        created_to: exportTo.value || undefined,
        per_page: 1000,
      },
    })
    const rows: any[] = data.data || data.items || []

    const headerRow = ['الرقم المرجعي', 'عنوان العطاء', 'الجهة المعلنة', 'التصنيف', 'تاريخ النشر', 'آخر موعد للتقديم', 'الحالة']
    const csvRows = [
      headerRow,
      ...rows.map(t => [
        t.reference_number ?? '',
        t.title ?? '',
        t.issuing_entity ?? '',
        t.category ?? '',
        t.published_at ? new Date(t.published_at).toLocaleDateString('ar-EG') : '',
        formatDeadline(t.deadline),
        getStatusLabel(t.status),
      ]),
    ]

    const csv = '﻿' + csvRows.map(r => r.map(cell => `"${String(cell).replace(/"/g, '""')}"`).join(',')).join('\n')
    const url = URL.createObjectURL(new Blob([csv], { type: 'text/csv;charset=utf-8;' }))
    const link = document.createElement('a')

    link.href = url
    link.download = `tenders-${exportFrom.value}-to-${exportTo.value}.csv`
    link.click()
    URL.revokeObjectURL(url)
    exportDialog.value = false
  } catch (err) {
    console.error(err)
    notify('تعذّر تصدير الكشف.', 'error')
  } finally {
    exporting.value = false
  }
}

const createTender = async () => {
  const { valid } = await createForm.value?.validate() ?? { valid: true }
  if (!valid)
    return
  if (newTender.value.deadline_date && !newTender.value.deadline_time) {
    notify('حدّد ساعة ودقيقة انتهاء العطاء', 'error')

    return
  }
  createLoading.value = true
  try {
    const formData = new FormData()
    formData.append('title',       newTender.value.title)
    formData.append('issuing_entity', newTender.value.issuing_entity)
    formData.append('description', newTender.value.description)
    formData.append('union_notes', newTender.value.union_notes)
    formData.append('category',    newTender.value.category)
    const deadline = joinDeadline(newTender.value.deadline_date, newTender.value.deadline_time)
    if (deadline)
      formData.append('deadline', deadline)
    formData.append('status',      newTender.value.status)
    if (newTender.value.external_url)
      formData.append('external_url', newTender.value.external_url)

    // إرسال المصفوفة
    newTender.value.submission_types.forEach(t => formData.append('submission_types[]', t))

    if (newTender.value.submission_types.includes('email'))
      formData.append('submission_email', newTender.value.submission_email)
    if (newTender.value.submission_types.includes('phone'))
      formData.append('submission_phone', newTender.value.submission_phone)
    if (newTender.value.submission_types.includes('file') && newTender.value.submission_file)
      formData.append('submission_file', newTender.value.submission_file)

    const { data } = await api.post('/api/v1/tenders', formData)
    const created = data.items

    // رفع المرفق الإضافي (إن اختير) تلقائياً فور الإنشاء
    let attachmentFailed = false
    if (newAttachmentStaged.value) {
      const fd = new FormData()
      fd.append('file', newAttachmentStaged.value)
      fd.append('label', newAttachmentStaged.value.name)
      try {
        await api.post(`/api/v1/tenders/${created.id}/attachments`, fd)
      } catch (attachErr) {
        console.error(attachErr)
        attachmentFailed = true
      }
    }

    // إغلاق نافذة الإضافة بعد الحفظ مباشرة
    createDialog.value = false
    newTender.value = emptyNewTender()
    newAttachmentStaged.value = null
    notify(attachmentFailed ? 'تم نشر العطاء، لكن تعذّر رفع المرفق — أضفه من نافذة التعديل' : 'تم نشر العطاء بنجاح', attachmentFailed ? 'error' : 'success')
    fetchTenders()
  }
  catch (err: any) {
    console.error(err)
    notify(firstError(err) || 'تعذّر نشر العطاء، حاول مرة أخرى', 'error')
  }
  finally {
    createLoading.value = false
  }
}

watchEffect(() => fetchTenders())
onMounted(fetchCategories)
</script>

<template>
  <div>
    <div class="d-flex justify-space-between align-center mb-6">
      <div>
        <h1 class="text-h4 font-weight-bold" style="font-family:Cairo,sans-serif">العطاءات</h1>
        <p class="text-body-2 text-medium-emphasis mb-0" style="font-family:Cairo,sans-serif">إدارة عطاءات الاتحاد ومتابعة العروض</p>
      </div>
      <div class="d-flex gap-2">
        <VBtn variant="tonal" prepend-icon="tabler-file-spreadsheet" @click="exportDialog = true">
          تصدير Excel
        </VBtn>
        <VBtn color="primary" prepend-icon="tabler-plus" @click="openCreate">
          عطاء جديد
        </VBtn>
      </div>
    </div>

    <VCard>
      <VCardText class="d-flex gap-4 flex-wrap">
        <VTextField
          v-model="search"
          placeholder="بحث في العطاءات..."
          prepend-inner-icon="tabler-search"
          density="compact"
          style="max-width:300px"
          @update:model-value="page = 1"
        />
        <VSelect
          v-model="statusFilter"
          :items="statusOptions"
          item-title="title"
          item-value="value"
          label="الحالة"
          density="compact"
          style="max-width:160px"
          @update:model-value="page = 1"
        />
        <VSelect
          v-model="categoryFilter"
          :items="[{ title: 'كل التصنيفات', value: '' }, ...categories.map(c => ({ title: c.name, value: c.name }))]"
          item-title="title"
          item-value="value"
          label="التصنيف"
          density="compact"
          clearable
          style="max-width:180px"
          @update:model-value="page = 1"
        />
        <VTextField
          v-model="updatedFromFilter"
          type="date"
          label="آخر تحديث منذ"
          density="compact"
          clearable
          style="max-width:180px"
          @update:model-value="page = 1"
        />
        <VSelect
          v-model="sortFilter"
          :items="sortOptions"
          item-title="title"
          item-value="value"
          label="الترتيب"
          density="compact"
          style="max-width:170px"
          @update:model-value="page = 1"
        />
      </VCardText>

      <VDataTableServer
        :headers="headers"
        :items="tenders"
        :items-length="total"
        :loading="loading"
        v-model:page="page"
        mobile-breakpoint="sm"
      >
        <template #item.title="{ item }">
          <div class="font-weight-medium" style="font-family:Cairo,sans-serif">{{ item.title }}</div>
        </template>

        <template #item.category="{ item }">
          <VChip v-if="item.category" color="primary" size="small" variant="tonal" label style="font-family:Cairo,sans-serif">
            {{ item.category }}
          </VChip>
          <span v-else class="text-medium-emphasis text-body-2">—</span>
        </template>

        <template #item.union_notes="{ item }">
          <span v-if="item.union_notes" class="text-body-2" style="font-family:Cairo,sans-serif;max-width:200px;display:block;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
            {{ item.union_notes }}
          </span>
          <span v-else class="text-medium-emphasis text-body-2">—</span>
        </template>

        <template #item.published_at="{ item }">
          {{ item.published_at ? new Date(item.published_at).toLocaleDateString('ar-PS') : '—' }}
        </template>

        <template #item.deadline="{ item }">
          <span>{{ formatDeadline(item.deadline) }}</span>
        </template>

        <template #item.display_status="{ item }">
          <VChip :color="displayStatusColor[item.display_status] ?? 'info'" size="small" label style="font-family:Cairo,sans-serif">
            {{ item.display_status_label }}
          </VChip>
        </template>

        <template #item.status="{ item }">
          <VChip :color="getStatusColor(item.status)" size="small" label style="font-family:Cairo,sans-serif">
            {{ getStatusLabel(item.status) }}
          </VChip>
        </template>

        <template #item.actions="{ item }">
          <div class="d-flex align-center gap-1">
            <!-- تغيير الحالة -->
            <VMenu>
              <template #activator="{ props }">
                <VBtn icon size="small" variant="text" v-bind="props" :loading="statusLoading === item.id">
                  <VIcon icon="tabler-transfer" />
                  <VTooltip activator="parent">تغيير الحالة</VTooltip>
                </VBtn>
              </template>
              <VList density="compact" min-width="150">
                <VListItem
                  v-for="opt in [{ label:'مفتوحة', value:'open', color:'success' }, { label:'مغلقة', value:'closed', color:'error' }, { label:'ملغية', value:'cancelled', color:'secondary' }]"
                  :key="opt.value"
                  :disabled="item.status === opt.value"
                  @click="changeStatus(item, opt.value)"
                >
                  <template #prepend>
                    <VIcon :icon="item.status === opt.value ? 'tabler-check' : 'tabler-circle'" size="16" :color="opt.color" />
                  </template>
                  <VListItemTitle style="font-family:Cairo,sans-serif">{{ opt.label }}</VListItemTitle>
                </VListItem>
              </VList>
            </VMenu>

            <!-- عرض التفاصيل -->
            <VBtn icon size="small" variant="text" color="info" @click="openView(item)">
              <VIcon icon="tabler-eye" />
              <VTooltip activator="parent">عرض التفاصيل</VTooltip>
            </VBtn>

            <!-- تعديل -->
            <VBtn icon size="small" variant="text" color="primary" @click="openEdit(item)">
              <VIcon icon="tabler-pencil" />
              <VTooltip activator="parent">تعديل</VTooltip>
            </VBtn>

            <!-- حذف -->
            <VBtn icon size="small" variant="text" color="error" @click="confirmDelete(item)">
              <VIcon icon="tabler-trash" />
              <VTooltip activator="parent">حذف</VTooltip>
            </VBtn>
          </div>
        </template>

        <template #no-data>
          <div class="text-center pa-6 text-medium-emphasis" style="font-family:Cairo,sans-serif">لا توجد عطاءات</div>
        </template>
      </VDataTableServer>
    </VCard>

    <!-- Export Dialog — كشف Excel لعطاءات فترة معيّنة (حسب تاريخ النشر) -->
    <VDialog v-model="exportDialog" max-width="420">
      <VCard>
        <VCardTitle style="font-family:Cairo,sans-serif">تصدير كشف Excel</VCardTitle>
        <VCardText>
          <p class="text-body-2 text-medium-emphasis mb-4" style="font-family:Cairo,sans-serif">
            يُصدَّر كل عطاء طُرح (تاريخ النشر) ضمن الفترة المحددة
          </p>
          <VTextField v-model="exportFrom" type="date" label="من تاريخ" class="mb-4" style="font-family:Cairo,sans-serif" />
          <VTextField v-model="exportTo" type="date" label="إلى تاريخ" style="font-family:Cairo,sans-serif" />
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn variant="text" @click="exportDialog = false">إلغاء</VBtn>
          <VBtn color="primary" :loading="exporting" @click="runExport">تصدير</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Create Tender Dialog -->
    <VDialog v-model="createDialog" max-width="520">
      <VCard>
        <VCardTitle style="font-family:Cairo,sans-serif">عطاء جديد</VCardTitle>
        <VCardText>
          <VForm ref="createForm" @submit.prevent>
          <VRow>
            <VCol cols="12">
              <VTextField v-model="newTender.title" label="عنوان العطاء" style="font-family:Cairo,sans-serif" />
            </VCol>
            <VCol cols="12">
              <VTextField v-model="newTender.issuing_entity" label="الجهة المعلنة" prepend-inner-icon="tabler-building" style="font-family:Cairo,sans-serif" />
            </VCol>
            <VCol cols="12">
              <VTextarea v-model="newTender.description" label="وصف العطاء" rows="3" style="font-family:Cairo,sans-serif" />
            </VCol>
            <VCol cols="12">
              <VTextarea
                v-model="newTender.union_notes"
                label="ملاحظات الاتحاد"
                placeholder="أي ملاحظات أو اشتراطات خاصة من الاتحاد بشأن هذا العطاء..."
                rows="3"
                style="font-family:Cairo,sans-serif"
                prepend-inner-icon="tabler-notes"
              />
            </VCol>
            <VCol cols="12">
              <VSelect
                v-model="newTender.category"
                :items="activeCategoryNames"
                label="تصنيف العطاء"
                prepend-inner-icon="tabler-category"
                clearable
                style="font-family:Cairo,sans-serif"
              >
                <template #item="{ props: itemProps, item }">
                  <VListItem v-bind="itemProps">
                    <template #prepend>
                      <VAvatar size="28" rounded :image="categoryImage(item.raw) ?? undefined" color="secondary" variant="tonal" class="me-2">
                        <VIcon v-if="!categoryImage(item.raw)" icon="tabler-photo-off" size="14" />
                      </VAvatar>
                    </template>
                  </VListItem>
                </template>
              </VSelect>
              <!-- الصورة الافتراضية للتصنيف المختار — هي ما سيظهر للعطاء بالتطبيق والموقع -->
              <div v-if="newTender.category" class="mt-2">
                <VImg v-if="categoryImage(newTender.category)" :src="categoryImage(newTender.category)!" height="120" cover class="rounded" />
                <div v-else class="text-caption text-medium-emphasis d-flex align-center gap-1" style="font-family:Cairo,sans-serif">
                  <VIcon icon="tabler-photo-off" size="14" /> لا توجد صورة افتراضية لهذا التصنيف — يمكن إضافتها من صفحة تصنيفات العطاءات
                </div>
              </div>
            </VCol>
            <VCol cols="12" md="7">
              <VTextField
                v-model="newTender.deadline_date"
                label="تاريخ انتهاء العطاء"
                type="date"
                :min="tomorrowStr()"
                :rules="deadlineRules"
                prepend-inner-icon="tabler-calendar"
                style="font-family:Cairo,sans-serif"
              />
            </VCol>
            <VCol cols="12" md="5">
              <VTextField
                v-model="newTender.deadline_time"
                label="ساعة الانتهاء (ساعة:دقيقة)"
                type="time"
                prepend-inner-icon="tabler-clock"
                :rules="[(v: string) => !newTender.deadline_date || !!v || 'حدّد الساعة والدقيقة']"
                style="font-family:Cairo,sans-serif"
              />
            </VCol>

            <!-- طريقة التقديم — متعددة الاختيار -->
            <VCol cols="12">
              <div class="text-body-2 font-weight-medium mb-2" style="font-family:Cairo,sans-serif">
                طريقة التقديم
                <span class="text-medium-emphasis">(اختياري — يمكن اختيار أكثر من طريقة)</span>
              </div>
              <div class="d-flex gap-3 flex-wrap">
                <VCard
                  v-for="opt in submissionOptions"
                  :key="opt.value"
                  :variant="newTender.submission_types.includes(opt.value) ? 'tonal' : 'outlined'"
                  :color="newTender.submission_types.includes(opt.value) ? 'primary' : undefined"
                  class="pa-3 cursor-pointer"
                  style="min-width:130px;flex:1;position:relative"
                  @click="toggleNewType(opt.value)"
                >
                  <VIcon
                    v-if="newTender.submission_types.includes(opt.value)"
                    icon="tabler-circle-check-filled"
                    color="primary"
                    size="16"
                    style="position:absolute;top:6px;left:6px"
                  />
                  <div class="d-flex align-center gap-2" style="font-family:Cairo,sans-serif">
                    <VIcon :icon="opt.icon" size="20" />
                    <span class="text-body-2">{{ opt.label }}</span>
                  </div>
                </VCard>
              </div>
            </VCol>

            <!-- حقل البريد الإلكتروني -->
            <VCol v-if="newTender.submission_types.includes('email')" cols="12">
              <VTextField
                v-model="newTender.submission_email"
                label="بريد التقديم الإلكتروني"
                prepend-inner-icon="tabler-mail"
                type="email"
                placeholder="tenders@union.ps"
                style="font-family:Cairo,sans-serif"
              />
            </VCol>

            <!-- حقل رقم التواصل -->
            <VCol v-if="newTender.submission_types.includes('phone')" cols="12">
              <VTextField
                v-model="newTender.submission_phone"
                label="رقم التواصل"
                prepend-inner-icon="tabler-phone"
                placeholder="+970 59 000 0000"
                style="font-family:Cairo,sans-serif"
              />
            </VCol>

            <!-- رفع ملف -->
            <VCol v-if="newTender.submission_types.includes('file')" cols="12">
              <VFileInput
                label="ملف التقديم (PDF أو Word)"
                prepend-inner-icon="tabler-file-upload"
                prepend-icon=""
                accept=".pdf,.doc,.docx"
                style="font-family:Cairo,sans-serif"
                :model-value="newTender.submission_file"
                @update:model-value="newTender.submission_file = firstFile($event as any)"
              />
            </VCol>

            <!-- رابط خارجي -->
            <VCol cols="12">
              <VTextField
                v-model="newTender.external_url"
                label="رابط خارجي (اختياري — تفاصيل العطاء أو رابط التقديم)"
                prepend-inner-icon="tabler-external-link"
                type="url"
                dir="ltr"
                placeholder="https://example.com/tender/123"
              />
            </VCol>

            <!-- مرفق إضافي واحد عند الإنشاء — لإضافة المزيد استخدم نافذة التعديل -->
            <VCol cols="12">
              <VFileInput
                label="مرفق العطاء (اختياري — ملف واحد)"
                hint="يمكن إضافة مرفقات أخرى لاحقاً من نافذة تعديل العطاء"
                persistent-hint
                prepend-inner-icon="tabler-paperclip"
                prepend-icon=""
                accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.webp"
                style="font-family:Cairo,sans-serif"
                :model-value="newAttachmentStaged"
                @update:model-value="newAttachmentStaged = firstFile($event as any)"
              />
            </VCol>
          </VRow>
          </VForm>
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn variant="tonal" @click="createDialog = false">إلغاء</VBtn>
          <VBtn color="primary" :loading="createLoading" @click="createTender">نشر العطاء</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
    <!-- Edit Tender Dialog -->
    <VDialog v-model="editDialog" max-width="520">
      <VCard>
        <VCardTitle style="font-family:Cairo,sans-serif">تعديل العطاء</VCardTitle>
        <VCardText>
          <VForm ref="editForm" @submit.prevent>
          <VRow>
            <VCol cols="12">
              <VTextField v-model="editTender.title" label="عنوان العطاء" style="font-family:Cairo,sans-serif" />
            </VCol>
            <VCol cols="12">
              <VTextField v-model="editTender.issuing_entity" label="الجهة المعلنة" prepend-inner-icon="tabler-building" style="font-family:Cairo,sans-serif" />
            </VCol>
            <VCol cols="12">
              <VTextarea v-model="editTender.description" label="وصف العطاء" rows="3" style="font-family:Cairo,sans-serif" />
            </VCol>
            <VCol cols="12">
              <VTextarea
                v-model="editTender.union_notes"
                label="ملاحظات الاتحاد"
                placeholder="أي ملاحظات أو اشتراطات خاصة من الاتحاد بشأن هذا العطاء..."
                rows="3"
                style="font-family:Cairo,sans-serif"
                prepend-inner-icon="tabler-notes"
              />
            </VCol>
            <VCol cols="12">
              <VSelect
                v-model="editTender.category"
                :items="editCategoryOptions"
                label="تصنيف العطاء"
                prepend-inner-icon="tabler-category"
                clearable
                style="font-family:Cairo,sans-serif"
              >
                <template #item="{ props: itemProps, item }">
                  <VListItem v-bind="itemProps">
                    <template #prepend>
                      <VAvatar size="28" rounded :image="categoryImage(item.raw) ?? undefined" color="secondary" variant="tonal" class="me-2">
                        <VIcon v-if="!categoryImage(item.raw)" icon="tabler-photo-off" size="14" />
                      </VAvatar>
                    </template>
                  </VListItem>
                </template>
              </VSelect>
              <VImg v-if="editTender.category && categoryImage(editTender.category)" :src="categoryImage(editTender.category)!" height="120" cover class="rounded mt-2" />
            </VCol>
            <VCol cols="12" md="4">
              <VTextField
                v-model="editTender.deadline_date"
                label="تاريخ انتهاء العطاء"
                type="date"
                :rules="editDeadlineRules"
                prepend-inner-icon="tabler-calendar"
                style="font-family:Cairo,sans-serif"
              />
            </VCol>
            <VCol cols="12" md="4">
              <VTextField
                v-model="editTender.deadline_time"
                label="ساعة الانتهاء"
                type="time"
                prepend-inner-icon="tabler-clock"
                :rules="[(v: string) => !editTender.deadline_date || !!v || 'حدّد الساعة والدقيقة']"
                style="font-family:Cairo,sans-serif"
              />
            </VCol>
            <VCol cols="12" md="4">
              <VSelect
                v-model="editTender.status"
                :items="[{ title:'مفتوحة', value:'open' }, { title:'مغلقة', value:'closed' }, { title:'ملغية', value:'cancelled' }]"
                item-title="title"
                item-value="value"
                label="الحالة"
                style="font-family:Cairo,sans-serif"
              />
            </VCol>
            <VCol cols="12">
              <div class="text-body-2 font-weight-medium mb-2" style="font-family:Cairo,sans-serif">
                طريقة التقديم
                <span class="text-medium-emphasis">(يمكن اختيار أكثر من طريقة)</span>
              </div>
              <div class="d-flex gap-3 flex-wrap">
                <VCard
                  v-for="opt in submissionOptions"
                  :key="opt.value"
                  :variant="(editTender.submission_types ?? []).includes(opt.value) ? 'tonal' : 'outlined'"
                  :color="(editTender.submission_types ?? []).includes(opt.value) ? 'primary' : undefined"
                  class="pa-3 cursor-pointer"
                  style="min-width:130px;flex:1;position:relative"
                  @click="toggleEditType(opt.value)"
                >
                  <VIcon
                    v-if="(editTender.submission_types ?? []).includes(opt.value)"
                    icon="tabler-circle-check-filled"
                    color="primary"
                    size="16"
                    style="position:absolute;top:6px;left:6px"
                  />
                  <div class="d-flex align-center gap-2" style="font-family:Cairo,sans-serif">
                    <VIcon :icon="opt.icon" size="20" />
                    <span class="text-body-2">{{ opt.label }}</span>
                  </div>
                </VCard>
              </div>
            </VCol>
            <VCol v-if="(editTender.submission_types ?? []).includes('email')" cols="12">
              <VTextField v-model="editTender.submission_email" label="بريد التقديم" prepend-inner-icon="tabler-mail" type="email" style="font-family:Cairo,sans-serif" />
            </VCol>
            <VCol v-if="(editTender.submission_types ?? []).includes('phone')" cols="12">
              <VTextField v-model="editTender.submission_phone" label="رقم التواصل" prepend-inner-icon="tabler-phone" style="font-family:Cairo,sans-serif" />
            </VCol>
            <VCol v-if="(editTender.submission_types ?? []).includes('file')" cols="12">
              <!-- الملف المحفوظ يبقى كما هو بعد الحفظ؛ لا يُستبدل إلا باختيار ملف جديد -->
              <div
                v-if="existingSubmissionFile"
                class="d-flex align-center gap-2 pa-2 mb-2"
                style="border:1px solid rgba(var(--v-border-color),var(--v-border-opacity));border-radius:8px"
              >
                <VIcon icon="tabler-file-check" size="20" color="success" />
                <div class="flex-grow-1 text-truncate" style="font-family:Cairo,sans-serif">
                  <div class="text-body-2 font-weight-medium">الملف المحفوظ حالياً</div>
                  <div class="text-caption text-medium-emphasis text-truncate" dir="ltr">{{ fileNameFromUrl(existingSubmissionFile) }}</div>
                </div>
                <VBtn size="small" variant="tonal" prepend-icon="tabler-eye" @click="openPreview(existingSubmissionFile, 'ملف التقديم')">معاينة</VBtn>
              </div>
              <VFileInput
                :label="existingSubmissionFile ? 'استبدال ملف التقديم (اختياري)' : 'ملف التقديم (PDF أو Word)'"
                :hint="editTender.submission_file ? 'سيُستبدل الملف المحفوظ بهذا الملف عند الحفظ' : ''"
                persistent-hint
                prepend-inner-icon="tabler-file-upload"
                prepend-icon=""
                accept=".pdf,.doc,.docx"
                style="font-family:Cairo,sans-serif"
                :model-value="editTender.submission_file"
                @update:model-value="editTender.submission_file = firstFile($event as any)"
              />
            </VCol>
            <VCol cols="12">
              <VTextField
                v-model="editTender.external_url"
                label="رابط خارجي (اختياري)"
                prepend-inner-icon="tabler-external-link"
                type="url"
                dir="ltr"
                placeholder="https://example.com/tender/123"
              />
            </VCol>

            <!-- مرفقات العطاء — متعددة، مستندات أو صور -->
            <VCol cols="12">
              <div class="text-body-2 font-weight-medium mb-2" style="font-family:Cairo,sans-serif">
                مرفقات العطاء
                <span class="text-medium-emphasis">(يمكن إضافة أكثر من ملف — مستند أو صورة)</span>
              </div>

              <div v-if="attachments.length" class="d-flex flex-wrap gap-3 mb-3">
                <div
                  v-for="att in attachments"
                  :key="att.id"
                  class="d-flex align-center gap-2 pa-2"
                  style="border:1px solid rgba(var(--v-border-color),var(--v-border-opacity));border-radius:8px;max-width:220px"
                >
                  <VAvatar v-if="att.is_image" :image="att.url" size="32" rounded />
                  <VAvatar v-else size="32" rounded color="secondary" variant="tonal">
                    <VIcon icon="tabler-file-text" size="16" />
                  </VAvatar>
                  <a
                    href="#"
                    class="text-body-2 text-truncate"
                    style="max-width:100px"
                    @click.prevent="openPreview(att.url, att.label || 'مرفق', att.is_image)"
                  >{{ att.label || 'ملف' }}</a>
                  <VBtn
                    icon
                    size="x-small"
                    variant="text"
                    color="error"
                    :loading="attachmentDeletingId === att.id"
                    @click="confirmRemoveAttachment(att)"
                  >
                    <VIcon icon="tabler-x" size="14" />
                  </VBtn>
                </div>
              </div>

              <div class="d-flex align-center gap-2">
                <VFileInput
                  label="إضافة مرفقات جديدة"
                  prepend-inner-icon="tabler-paperclip"
                  prepend-icon=""
                  multiple
                  accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.webp"
                  density="compact"
                  style="font-family:Cairo,sans-serif;flex:1"
                  :model-value="newAttachmentFiles"
                  @update:model-value="newAttachmentFiles = toFileArray($event)"
                />
                <VBtn
                  :disabled="!newAttachmentFiles.length"
                  :loading="attachmentUploading"
                  color="primary"
                  variant="tonal"
                  @click="uploadAttachments"
                >
                  رفع
                </VBtn>
              </div>
            </VCol>
          </VRow>
          </VForm>
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn variant="tonal" @click="editDialog = false">إلغاء</VBtn>
          <VBtn color="primary" :loading="editLoading" @click="saveTender">حفظ التعديلات</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- View Details Dialog -->
    <VDialog v-model="viewDialog" max-width="680" scrollable>
      <VCard v-if="viewingItem">
        <VCardTitle class="d-flex align-center justify-space-between flex-wrap gap-2" style="font-family:Cairo,sans-serif">
          <span>{{ viewingItem.title }}</span>
          <div class="d-flex gap-2">
            <VChip v-if="viewingItem.display_status_label" :color="displayStatusColor[viewingItem.display_status] ?? 'info'" size="small" label>
              {{ viewingItem.display_status_label }}
            </VChip>
            <VChip :color="getStatusColor(viewingItem.status)" size="small" label>
              {{ getStatusLabel(viewingItem.status) }}
            </VChip>
          </div>
        </VCardTitle>
        <VCardText>
          <VRow dense class="mb-3">
            <VCol cols="6" md="4">
              <span class="text-caption text-medium-emphasis d-block" style="font-family:Cairo,sans-serif">الرقم المرجعي</span>
              <span dir="ltr">{{ viewingItem.reference_number || '—' }}</span>
            </VCol>
            <VCol cols="6" md="4">
              <span class="text-caption text-medium-emphasis d-block" style="font-family:Cairo,sans-serif">الجهة المعلنة</span>
              <span style="font-family:Cairo,sans-serif">{{ viewingItem.issuing_entity || '—' }}</span>
            </VCol>
            <VCol cols="6" md="4">
              <span class="text-caption text-medium-emphasis d-block" style="font-family:Cairo,sans-serif">التصنيف</span>
              <span style="font-family:Cairo,sans-serif">{{ viewingItem.category || '—' }}</span>
            </VCol>
            <VCol cols="6" md="4">
              <span class="text-caption text-medium-emphasis d-block" style="font-family:Cairo,sans-serif">الميزانية</span>
              <span style="font-family:Cairo,sans-serif">{{ viewingItem.budget ?? '—' }}</span>
            </VCol>
            <VCol cols="6" md="4">
              <span class="text-caption text-medium-emphasis d-block" style="font-family:Cairo,sans-serif">آخر موعد</span>
              <span style="font-family:Cairo,sans-serif">{{ formatDeadline(viewingItem.deadline) }}</span>
            </VCol>
            <VCol cols="6" md="4">
              <span class="text-caption text-medium-emphasis d-block" style="font-family:Cairo,sans-serif">تاريخ النشر</span>
              <span style="font-family:Cairo,sans-serif">{{ viewingItem.published_at ? new Date(viewingItem.published_at).toLocaleDateString('ar-PS') : '—' }}</span>
            </VCol>
          </VRow>

          <VDivider class="mb-4" />

          <div v-if="viewingItem.description" class="mb-4">
            <label class="text-body-2 font-weight-medium mb-1 d-block" style="font-family:Cairo,sans-serif">الوصف</label>
            <p class="text-body-2" style="font-family:Cairo,sans-serif">{{ viewingItem.description }}</p>
          </div>
          <div v-if="viewingItem.union_notes" class="mb-4">
            <label class="text-body-2 font-weight-medium mb-1 d-block" style="font-family:Cairo,sans-serif">ملاحظات الاتحاد</label>
            <p class="text-body-2" style="font-family:Cairo,sans-serif">{{ viewingItem.union_notes }}</p>
          </div>

          <div class="mb-4">
            <label class="text-body-2 font-weight-medium mb-1 d-block" style="font-family:Cairo,sans-serif">طريقة التقديم</label>
            <div class="d-flex flex-wrap gap-2">
              <VChip v-if="(viewingItem.submission_types ?? []).includes('email')" size="small" prepend-icon="tabler-mail">
                {{ viewingItem.submission_email || 'بريد إلكتروني' }}
              </VChip>
              <VChip v-if="(viewingItem.submission_types ?? []).includes('phone')" size="small" prepend-icon="tabler-phone">
                {{ viewingItem.submission_phone || 'هاتف' }}
              </VChip>
              <VChip
                v-if="(viewingItem.submission_types ?? []).includes('file') && viewingItem.submission_file"
                size="small"
                color="primary"
                prepend-icon="tabler-eye"
                @click="openPreview(viewingItem.submission_file, 'ملف التقديم')"
              >
                معاينة ملف التقديم
              </VChip>
              <span v-if="!(viewingItem.submission_types ?? []).length" class="text-medium-emphasis text-body-2">—</span>
            </div>
          </div>

          <div v-if="viewAttachments.length" class="mb-2">
            <label class="text-body-2 font-weight-medium mb-2 d-block" style="font-family:Cairo,sans-serif">المرفقات</label>
            <div class="d-flex flex-wrap gap-3">
              <VCard
                v-for="att in viewAttachments"
                :key="att.id"
                variant="outlined"
                class="d-flex align-center gap-2 pa-2 cursor-pointer"
                style="max-width:260px"
                @click="openPreview(att.url, att.label || 'مرفق', att.is_image)"
              >
                <VAvatar v-if="att.is_image" :image="att.url" size="40" rounded />
                <VAvatar v-else size="40" rounded color="secondary" variant="tonal">
                  <VIcon icon="tabler-file-text" size="20" />
                </VAvatar>
                <span class="text-body-2 text-truncate flex-grow-1" style="font-family:Cairo,sans-serif">{{ att.label || 'مرفق' }}</span>
                <VBtn icon size="x-small" variant="text" :href="att.url" target="_blank" rel="noopener" download @click.stop>
                  <VIcon icon="tabler-download" size="16" />
                  <VTooltip activator="parent">تحميل</VTooltip>
                </VBtn>
              </VCard>
            </div>
          </div>
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn variant="tonal" color="primary" @click="viewDialog = false; openEdit(viewingItem)">تعديل</VBtn>
          <VBtn variant="tonal" @click="viewDialog = false">إغلاق</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Delete Confirm Dialog -->
    <VDialog v-model="deleteDialog" max-width="400">
      <VCard>
        <VCardTitle class="d-flex align-center gap-2" style="font-family:Cairo,sans-serif">
          <VIcon icon="tabler-alert-triangle" color="error" />
          تأكيد الحذف
        </VCardTitle>
        <VCardText style="font-family:Cairo,sans-serif">
          هل أنت متأكد من حذف العطاء
          <strong>{{ deletingItem?.title }}</strong>؟
          لا يمكن التراجع عن هذا الإجراء.
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn variant="tonal" @click="deleteDialog = false">إلغاء</VBtn>
          <VBtn color="error" :loading="deleteLoading" @click="deleteTender">حذف</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Delete Attachment Confirm Dialog -->
    <VDialog v-model="deleteAttachmentDialog" max-width="400">
      <VCard>
        <VCardTitle class="d-flex align-center gap-2" style="font-family:Cairo,sans-serif">
          <VIcon icon="tabler-alert-triangle" color="error" />
          تأكيد حذف المرفق
        </VCardTitle>
        <VCardText style="font-family:Cairo,sans-serif">
          هل أنت متأكد من حذف المرفق
          <strong>{{ deletingAttachment?.label || 'هذا الملف' }}</strong>؟
          لا يمكن التراجع عن هذا الإجراء.
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn variant="tonal" @click="deleteAttachmentDialog = false">إلغاء</VBtn>
          <VBtn color="error" :loading="attachmentDeletingId === deletingAttachment?.id" @click="removeAttachment">حذف</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Attachment Preview Dialog -->
    <VDialog v-model="previewDialog" max-width="900">
      <VCard v-if="previewItem">
        <VCardTitle class="d-flex align-center gap-2" style="font-family:Cairo,sans-serif">
          <VIcon :icon="previewItem.kind === 'image' ? 'tabler-photo' : 'tabler-file-type-pdf'" />
          <span class="text-truncate">{{ previewItem.label }}</span>
        </VCardTitle>
        <VCardText>
          <VImg v-if="previewItem.kind === 'image'" :src="previewItem.url" max-height="70vh" contain />
          <iframe v-else :src="previewItem.url" style="width:100%;height:70vh;border:0" />
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn variant="tonal" prepend-icon="tabler-external-link" :href="previewItem.url" target="_blank" rel="noopener">فتح بتبويب جديد</VBtn>
          <VBtn variant="tonal" @click="previewDialog = false">إغلاق</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Feedback Snackbar -->
    <VSnackbar
      v-model="snackbar"
      :timeout="3500"
      :color="snackbarColor"
      location="bottom end"
      variant="elevated"
    >
      <span style="font-family:Cairo,sans-serif">{{ snackbarText }}</span>
      <template #actions>
        <VBtn variant="text" size="small" @click="snackbar = false">إغلاق</VBtn>
      </template>
    </VSnackbar>

  </div>
</template>

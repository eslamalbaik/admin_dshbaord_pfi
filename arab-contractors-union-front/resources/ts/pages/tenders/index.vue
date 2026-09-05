<script setup lang="ts">
import api from '@/plugins/axios'

definePage({ meta: { requiresAdmin: true,
    adminOnly: true } })

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
    deadline:         item.deadline ? item.deadline.substring(0, 10) : '',
    status:           item.status,
    submission_types: Array.isArray(item.submission_types) ? [...item.submission_types] : [],
    submission_email: item.submission_email || '',
    submission_phone: item.submission_phone || '',
    submission_file:  null,
    external_url:     item.external_url || '',
  }
  attachments.value = Array.isArray(item.attachments) ? [...item.attachments] : []
  editDialog.value = true

  // القائمة الإدارية ما بترجّع attachments (تفادياً لتحميل زايد على كل صف) — نجيبها وقت فتح التعديل
  try {
    const { data } = await api.get(`/api/v1/tenders/${item.id}`)
    attachments.value = data.items?.attachments ?? []
  } catch (err) {
    console.error(err)
  }
}

// ── مرفقات العطاء (متعددة — مستندات وصور) ──────────
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

const removeAttachment = async (attachmentId: number) => {
  attachmentDeletingId.value = attachmentId
  try {
    await api.delete(`/api/v1/tenders/${editTender.value.id}/attachments/${attachmentId}`)
    attachments.value = attachments.value.filter(a => a.id !== attachmentId)
  } catch (err) {
    console.error(err)
    notify('تعذّر حذف المرفق', 'error')
  } finally {
    attachmentDeletingId.value = null
  }
}

const saveTender = async () => {
  editLoading.value = true
  try {
    const formData = new FormData()
    formData.append('_method', 'PATCH')
    ;(['title','description','union_notes','category','deadline','status','submission_email','submission_phone','external_url'] as const)
      .forEach(k => formData.append(k, (editTender.value as any)[k] ?? ''))
    // إرسال المصفوفة
    const types: string[] = editTender.value.submission_types ?? []
    types.forEach(t => formData.append('submission_types[]', t))
    if (editTender.value.submission_file)
      formData.append('submission_file', editTender.value.submission_file)
    await api.post(`/api/v1/tenders/${editTender.value.id}`, formData)
    editDialog.value = false
    notify('تم حفظ التعديلات بنجاح')
    fetchTenders()
  } catch (err) { console.error(err); notify('تعذّر حفظ التعديلات', 'error') }
  finally { editLoading.value = false }
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
  description:      '',
  union_notes:      '',
  category:         '',
  deadline:         '',
  status:           'open' as string,
  submission_types: [] as string[],
  submission_email: '',
  submission_phone: '',
  submission_file:  null as File | null,
  external_url:     '',
})

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

// لازم يطابق Tender::CATEGORIES بالباك اند — أي قيمة زيادة هون بترفضها الـvalidation (422)
const categoryOptions = [
  'مباني',
  'طرق',
  'بنية تحتية',
  'قطاع صحي',
  'قطاع تعليمي',
  'عام',
]

const headers = [
  { title: 'عنوان العطاء', key: 'title' },
  { title: 'التصنيف', key: 'category' },
  { title: 'ملاحظات الاتحاد', key: 'union_notes' },
  { title: 'تاريخ النشر', key: 'published_at' },
  { title: 'آخر موعد', key: 'deadline' },
  { title: 'الحالة', key: 'status' },
  { title: 'إجراءات', key: 'actions', sortable: false },
]

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

const createTender = async () => {
  createLoading.value = true
  try {
    const formData = new FormData()
    formData.append('title',       newTender.value.title)
    formData.append('description', newTender.value.description)
    formData.append('union_notes', newTender.value.union_notes)
    formData.append('category',    newTender.value.category)
    formData.append('deadline',    newTender.value.deadline)
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
    createDialog.value = false
    newTender.value = { title: '', description: '', union_notes: '', category: '', deadline: '', status: 'open', submission_types: [], submission_email: '', submission_phone: '', submission_file: null }
    notify('تم نشر العطاء بنجاح — أضف مرفقات العطاء الآن لو حابب')
    fetchTenders()
    // فتح نافذة التعديل مباشرة على العطاء الجديد — عشان إضافة المرفقات (بتحتاج id العطاء، مش متاحة وقت الإنشاء)
    openEdit(data.items)
  }
  catch (err) {
    console.error(err)
    notify('تعذّر نشر العطاء، حاول مرة أخرى', 'error')
  }
  finally {
    createLoading.value = false
  }
}

watchEffect(() => fetchTenders())
</script>

<template>
  <div>
    <div class="d-flex justify-space-between align-center mb-6">
      <div>
        <h1 class="text-h4 font-weight-bold" style="font-family:Cairo,sans-serif">العطاءات</h1>
        <p class="text-body-2 text-medium-emphasis mb-0" style="font-family:Cairo,sans-serif">إدارة عطاءات الاتحاد ومتابعة العروض</p>
      </div>
      <VBtn color="primary" prepend-icon="tabler-plus" @click="createDialog = true">
        عطاء جديد
      </VBtn>
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
          :items="[{ title: 'كل التصنيفات', value: '' }, ...categoryOptions.map(c => ({ title: c, value: c }))]"
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
          <div class="d-flex align-center gap-2">
            <span>{{ item.deadline ? new Date(item.deadline).toLocaleDateString('ar-PS') : '—' }}</span>
            <VChip v-if="item.closing_soon" color="warning" size="x-small" label style="font-family:Cairo,sans-serif">
              ينتهي قريباً
            </VChip>
          </div>
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

    <!-- Create Tender Dialog -->
    <VDialog v-model="createDialog" max-width="520">
      <VCard>
        <VCardTitle style="font-family:Cairo,sans-serif">عطاء جديد</VCardTitle>
        <VCardText>
          <VRow>
            <VCol cols="12">
              <VTextField v-model="newTender.title" label="عنوان العطاء" style="font-family:Cairo,sans-serif" />
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
                :items="categoryOptions"
                label="تصنيف العطاء"
                prepend-inner-icon="tabler-category"
                clearable
                style="font-family:Cairo,sans-serif"
              />
            </VCol>
            <VCol cols="12">
              <VTextField v-model="newTender.deadline" label="آخر موعد للتقديم" type="date" style="font-family:Cairo,sans-serif" />
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
                @update:model-value="newTender.submission_file = ($event as File | null) ?? null"
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
          </VRow>
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
          <VRow>
            <VCol cols="12">
              <VTextField v-model="editTender.title" label="عنوان العطاء" style="font-family:Cairo,sans-serif" />
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
                :items="categoryOptions"
                label="تصنيف العطاء"
                prepend-inner-icon="tabler-category"
                clearable
                style="font-family:Cairo,sans-serif"
              />
            </VCol>
            <VCol cols="12" md="6">
              <VTextField v-model="editTender.deadline" label="آخر موعد للتقديم" type="date" style="font-family:Cairo,sans-serif" />
            </VCol>
            <VCol cols="12" md="6">
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
              <VFileInput
                label="ملف التقديم (PDF أو Word)"
                prepend-inner-icon="tabler-file-upload"
                prepend-icon=""
                accept=".pdf,.doc,.docx"
                style="font-family:Cairo,sans-serif"
                :model-value="editTender.submission_file"
                @update:model-value="editTender.submission_file = $event ?? null"
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
                    :href="att.url"
                    target="_blank"
                    rel="noopener"
                    class="text-body-2 text-truncate"
                    style="max-width:100px"
                  >{{ att.label || 'ملف' }}</a>
                  <VBtn
                    icon
                    size="x-small"
                    variant="text"
                    color="error"
                    :loading="attachmentDeletingId === att.id"
                    @click="removeAttachment(att.id)"
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
                  @update:model-value="newAttachmentFiles = $event ?? []"
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
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn variant="tonal" @click="editDialog = false">إلغاء</VBtn>
          <VBtn color="primary" :loading="editLoading" @click="saveTender">حفظ التعديلات</VBtn>
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

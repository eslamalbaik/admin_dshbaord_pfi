<script setup lang="ts">
import api from '@/plugins/axios'

definePage({ meta: { requiresAdmin: true, adminOnly: true } })

const loading = ref(false)
const items = ref<any[]>([])
const page = ref(1)
const total = ref(0)
const categoryOptions = ref<string[]>([])

// ── Snackbar ──────────────────────────────────────
const snackbar = ref(false)
const snackbarText = ref('')
const snackbarColor = ref('success')
const notify = (text: string, color: 'success' | 'error' = 'success') => {
  snackbarText.value = text
  snackbarColor.value = color
  snackbar.value = true
}

const search = ref('')
const categoryFilter = ref('')
const pinnedFilter = ref('')
const publishedFilter = ref('')

const headers = [
  { title: 'التعميم', key: 'title' },
  { title: 'رقم التعميم', key: 'number' },
  { title: 'التصنيف', key: 'category' },
  { title: 'تاريخ النشر', key: 'published_at' },
  { title: 'الحالة', key: 'is_published' },
  { title: 'إجراءات', key: 'actions', sortable: false },
]

const fetchAnnouncements = async () => {
  loading.value = true
  try {
    const { data } = await api.get('/api/v1/admin/announcements', {
      params: {
        search: search.value || undefined,
        category: categoryFilter.value || undefined,
        is_pinned: pinnedFilter.value !== '' ? pinnedFilter.value : undefined,
        is_published: publishedFilter.value !== '' ? publishedFilter.value : undefined,
        page: page.value,
      },
    })
    items.value = data.items ?? []
    total.value = data.meta?.total ?? items.value.length
    categoryOptions.value = data.meta?.categories ?? []
  } catch (err) {
    console.error(err)
    items.value = []
  } finally {
    loading.value = false
  }
}

watchEffect(() => fetchAnnouncements())

// ── Create/Edit form ──────────────────────────────
const emptyForm = () => ({
  id: null as number | null,
  title: '',
  number: '',
  category: '',
  body: '',
  is_published: false,
  is_pinned: false,
  published_at: '',
  attachmentName: '' as string,
})

const formDialog = ref(false)
const formLoading = ref(false)
const isEditing = ref(false)
const form = ref(emptyForm())
const imageFile = ref<File[]>([])
const attachmentFile = ref<File[]>([])

const openCreate = () => {
  form.value = emptyForm()
  imageFile.value = []
  attachmentFile.value = []
  isEditing.value = false
  formDialog.value = true
}

const openEdit = (item: any) => {
  form.value = {
    id: item.id,
    title: item.title,
    number: item.number ?? '',
    category: item.category ?? '',
    body: item.body ?? '',
    is_published: !!item.is_published,
    is_pinned: !!item.is_pinned,
    published_at: item.published_at ? item.published_at.substring(0, 10) : '',
    attachmentName: item.attachment ? item.attachment.split('/').pop() : '',
  }
  imageFile.value = []
  attachmentFile.value = []
  isEditing.value = true
  formDialog.value = true
}

const saveAnnouncement = async () => {
  if (!form.value.title || !form.value.body) {
    notify('العنوان والمحتوى مطلوبان', 'error')
    return
  }
  formLoading.value = true
  try {
    const fd = new FormData()
    fd.append('title', form.value.title)
    fd.append('body', form.value.body)
    fd.append('is_published', form.value.is_published ? '1' : '0')
    fd.append('is_pinned', form.value.is_pinned ? '1' : '0')
    if (form.value.number) fd.append('number', form.value.number)
    if (form.value.category) fd.append('category', form.value.category)
    if (form.value.published_at) fd.append('published_at', form.value.published_at)
    if (imageFile.value[0]) fd.append('image', imageFile.value[0])
    if (attachmentFile.value[0]) fd.append('attachment', attachmentFile.value[0])

    if (isEditing.value) {
      fd.append('_method', 'PUT')
      await api.post(`/api/v1/admin/announcements/${form.value.id}`, fd)
      notify('تم تحديث التعميم بنجاح')
    } else {
      await api.post('/api/v1/admin/announcements', fd)
      notify('تم نشر التعميم بنجاح')
    }
    formDialog.value = false
    fetchAnnouncements()
  } catch (err: any) {
    console.error(err)
    notify(err?.response?.data?.message || 'تعذّر حفظ التعميم', 'error')
  } finally {
    formLoading.value = false
  }
}

// ── Delete ────────────────────────────────────────
const deleteDialog = ref(false)
const deleteLoading = ref(false)
const deletingItem = ref<any>(null)

const confirmDelete = (item: any) => { deletingItem.value = item; deleteDialog.value = true }

const deleteAnnouncement = async () => {
  deleteLoading.value = true
  try {
    await api.delete(`/api/v1/admin/announcements/${deletingItem.value.id}`)
    deleteDialog.value = false
    notify('تم حذف التعميم بنجاح')
    fetchAnnouncements()
  } catch (err) {
    console.error(err)
    notify('تعذّر حذف التعميم', 'error')
  } finally {
    deleteLoading.value = false
  }
}
</script>

<template>
  <div>
    <div class="d-flex justify-space-between align-center mb-6">
      <div>
        <h1 class="text-h4 font-weight-bold" style="font-family:Cairo,sans-serif">التعميمات</h1>
        <p class="text-body-2 text-medium-emphasis mb-0" style="font-family:Cairo,sans-serif">
          إدارة التعميمات — التثبيت "عاجل وهام" يظهر كـ Pop-up لمرة واحدة عند فتح تطبيق المقاول
        </p>
      </div>
      <VBtn color="primary" prepend-icon="tabler-plus" @click="openCreate">
        تعميم جديد
      </VBtn>
    </div>

    <VCard>
      <VCardText class="d-flex gap-4 flex-wrap">
        <VTextField
          v-model="search"
          placeholder="بحث بالعنوان أو رقم التعميم..."
          prepend-inner-icon="tabler-search"
          density="compact"
          style="max-width:280px"
          clearable
          @update:model-value="page = 1"
        />
        <VSelect
          v-model="categoryFilter"
          :items="[{ title: 'كل التصنيفات', value: '' }, ...categoryOptions.map(c => ({ title: c, value: c }))]"
          label="التصنيف"
          density="compact"
          clearable
          style="max-width:200px"
          @update:model-value="page = 1"
        />
        <VSelect
          v-model="pinnedFilter"
          :items="[{ title: 'الكل', value: '' }, { title: 'عاجل وهام فقط', value: '1' }]"
          label="الأولوية"
          density="compact"
          style="max-width:170px"
          @update:model-value="page = 1"
        />
        <VSelect
          v-model="publishedFilter"
          :items="[{ title: 'الكل', value: '' }, { title: 'منشور', value: '1' }, { title: 'مسودة', value: '0' }]"
          label="الحالة"
          density="compact"
          style="max-width:150px"
          @update:model-value="page = 1"
        />
      </VCardText>

      <VDataTableServer
        :headers="headers"
        :items="items"
        :items-length="total"
        :loading="loading"
        v-model:page="page"
        mobile-breakpoint="sm"
      >
        <template #item.title="{ item }">
          <div class="d-flex align-center gap-3">
            <VAvatar v-if="item.image" :image="item.image" size="40" rounded />
            <VAvatar v-else size="40" rounded :color="item.is_pinned ? 'error' : 'secondary'" variant="tonal">
              <VIcon icon="tabler-speakerphone" size="18" />
            </VAvatar>
            <div class="min-w-0">
              <div class="d-flex align-center gap-2">
                <span class="font-weight-medium text-truncate" style="font-family:Cairo,sans-serif">{{ item.title }}</span>
                <VChip v-if="item.is_pinned" color="error" size="x-small" label style="font-family:Cairo,sans-serif">
                  عاجل وهام
                </VChip>
              </div>
              <span v-if="item.attachment" class="text-caption text-medium-emphasis d-flex align-center gap-1">
                <VIcon icon="tabler-paperclip" size="12" /> يحتوي مرفق
              </span>
            </div>
          </div>
        </template>

        <template #item.number="{ item }">
          <span style="font-family:Cairo,sans-serif">{{ item.number || '—' }}</span>
        </template>

        <template #item.category="{ item }">
          <VChip v-if="item.category" color="info" size="small" variant="tonal" label style="font-family:Cairo,sans-serif">
            {{ item.category }}
          </VChip>
          <span v-else class="text-medium-emphasis">—</span>
        </template>

        <template #item.published_at="{ item }">
          {{ item.published_at ? new Date(item.published_at).toLocaleDateString('ar-PS') : '—' }}
        </template>

        <template #item.is_published="{ item }">
          <VChip :color="item.is_published ? 'success' : 'secondary'" size="small" label style="font-family:Cairo,sans-serif">
            {{ item.is_published ? 'منشور' : 'مسودة' }}
          </VChip>
        </template>

        <template #item.actions="{ item }">
          <div class="d-flex align-center gap-1">
            <VBtn icon size="small" variant="text" color="primary" @click="openEdit(item)">
              <VIcon icon="tabler-pencil" />
              <VTooltip activator="parent">تعديل</VTooltip>
            </VBtn>
            <VBtn icon size="small" variant="text" color="error" @click="confirmDelete(item)">
              <VIcon icon="tabler-trash" />
              <VTooltip activator="parent">حذف</VTooltip>
            </VBtn>
          </div>
        </template>

        <template #no-data>
          <div class="text-center pa-6 text-medium-emphasis" style="font-family:Cairo,sans-serif">لا توجد تعميمات بعد</div>
        </template>
      </VDataTableServer>
    </VCard>

    <!-- Create/Edit Dialog -->
    <VDialog v-model="formDialog" max-width="700" scrollable>
      <VCard>
        <VCardTitle style="font-family:Cairo,sans-serif">{{ isEditing ? 'تعديل التعميم' : 'تعميم جديد' }}</VCardTitle>
        <VCardText>
          <VRow>
            <VCol cols="12" md="8">
              <VTextField v-model="form.title" label="عنوان التعميم" style="font-family:Cairo,sans-serif" />
            </VCol>
            <VCol cols="12" md="4">
              <VTextField v-model="form.number" label="رقم التعميم (مثال: 2026/108)" dir="ltr" style="font-family:Cairo,sans-serif" />
            </VCol>
            <VCol cols="12" md="6">
              <VCombobox
                v-model="form.category"
                :items="categoryOptions"
                label="التصنيف (مثال: تعميمات الشؤون الفنية والتصنيف)"
                clearable
                style="font-family:Cairo,sans-serif"
              />
            </VCol>
            <VCol cols="12" md="6">
              <VTextField v-model="form.published_at" label="تاريخ النشر (اختياري — الآن افتراضياً)" type="date" style="font-family:Cairo,sans-serif" />
            </VCol>
            <VCol cols="12">
              <VTextarea v-model="form.body" label="نص التعميم" rows="6" style="font-family:Cairo,sans-serif" />
            </VCol>

            <VCol cols="12" md="6">
              <VFileInput
                v-model="imageFile"
                label="صورة التعميم (اختياري)"
                prepend-inner-icon="tabler-photo"
                prepend-icon=""
                accept="image/*"
                style="font-family:Cairo,sans-serif"
              />
            </VCol>
            <VCol cols="12" md="6">
              <VFileInput
                v-model="attachmentFile"
                :label="isEditing && form.attachmentName ? `استبدال المرفق (الحالي: ${form.attachmentName})` : 'مرفق PDF/Word (اختياري)'"
                prepend-inner-icon="tabler-paperclip"
                prepend-icon=""
                accept=".pdf,.doc,.docx"
                style="font-family:Cairo,sans-serif"
              />
            </VCol>

            <VCol cols="12" md="6" class="d-flex align-center">
              <VSwitch v-model="form.is_pinned" label="عاجل وهام (تثبيت + Pop-up أول فتح)" color="error" style="font-family:Cairo,sans-serif" />
            </VCol>
            <VCol cols="12" md="6" class="d-flex align-center">
              <VSwitch v-model="form.is_published" label="نشر مباشرة" color="success" style="font-family:Cairo,sans-serif" />
            </VCol>
          </VRow>
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn variant="tonal" @click="formDialog = false">إلغاء</VBtn>
          <VBtn color="primary" :loading="formLoading" @click="saveAnnouncement">{{ isEditing ? 'حفظ التعديلات' : 'نشر' }}</VBtn>
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
          هل أنت متأكد من حذف <strong>{{ deletingItem?.title }}</strong>؟ لا يمكن التراجع عن هذا الإجراء.
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn variant="tonal" @click="deleteDialog = false">إلغاء</VBtn>
          <VBtn color="error" :loading="deleteLoading" @click="deleteAnnouncement">حذف</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Feedback Snackbar -->
    <VSnackbar v-model="snackbar" :timeout="3500" :color="snackbarColor" location="bottom end" variant="elevated">
      <span style="font-family:Cairo,sans-serif">{{ snackbarText }}</span>
      <template #actions>
        <VBtn variant="text" size="small" @click="snackbar = false">إغلاق</VBtn>
      </template>
    </VSnackbar>
  </div>
</template>

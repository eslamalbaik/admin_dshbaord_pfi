<script setup lang="ts">
import api from '@/plugins/axios'

definePage({ meta: { requiresAdmin: true, adminOnly: true } })

const loading = ref(false)
const items = ref<any[]>([])
const page = ref(1)
const total = ref(0)

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
const publishedFilter = ref('')

const categoryOptions = [
  { title: 'خبر', value: 'news' },
  { title: 'إعلان', value: 'announcement' },
  { title: 'مناسبة', value: 'event' },
  { title: 'عطاء', value: 'tender' },
]

const headers = [
  { title: 'العنوان', key: 'title' },
  { title: 'التصنيف', key: 'category' },
  { title: 'تاريخ النشر', key: 'published_at' },
  { title: 'الحالة', key: 'is_published' },
  { title: 'إجراءات', key: 'actions', sortable: false },
]

const getCategoryLabel = (c: string) => categoryOptions.find(o => o.value === c)?.title ?? c
const getCategoryColor = (c: string) => ({ news: 'primary', announcement: 'info', event: 'success', tender: 'warning' }[c] || 'secondary')

const fetchNews = async () => {
  loading.value = true
  try {
    const { data } = await api.get('/api/v1/admin/news', {
      params: {
        search: search.value || undefined,
        category: categoryFilter.value || undefined,
        is_published: publishedFilter.value !== '' ? publishedFilter.value : undefined,
        page: page.value,
      },
    })
    items.value = data.items ?? []
    total.value = data.meta?.total ?? items.value.length
  } catch (err) {
    console.error(err)
    items.value = []
  } finally {
    loading.value = false
  }
}

watchEffect(() => fetchNews())

// ── Create/Edit form ──────────────────────────────
const emptyForm = () => ({
  id: null as number | null,
  title: '',
  excerpt: '',
  body: '',
  category: 'news',
  image: null as File | null,
  imagePreview: '' as string,
  gallery: [] as File[],
  video_url: '',
  external_url: '',
  event_date: '',
  event_location: '',
  is_published: false,
  published_at: '',
})

const formDialog = ref(false)
const formLoading = ref(false)
const isEditing = ref(false)
const form = ref(emptyForm())

const openCreate = () => {
  form.value = emptyForm()
  isEditing.value = false
  formDialog.value = true
}

const openEdit = (item: any) => {
  form.value = {
    id: item.id,
    title: item.title,
    excerpt: item.excerpt ?? '',
    body: item.body ?? '',
    category: item.category,
    image: null,
    imagePreview: item.image ?? '',
    gallery: [],
    video_url: item.video_url ?? '',
    external_url: item.external_url ?? '',
    event_date: item.event_date ? item.event_date.substring(0, 16) : '',
    event_location: item.event_location ?? '',
    is_published: !!item.is_published,
    published_at: item.published_at ? item.published_at.substring(0, 10) : '',
  }
  isEditing.value = true
  formDialog.value = true
}

const saveNews = async () => {
  if (!form.value.title || !form.value.body) {
    notify('العنوان والمحتوى مطلوبان', 'error')
    return
  }
  formLoading.value = true
  try {
    const fd = new FormData()
    fd.append('title', form.value.title)
    fd.append('excerpt', form.value.excerpt)
    fd.append('body', form.value.body)
    fd.append('category', form.value.category)
    fd.append('is_published', form.value.is_published ? '1' : '0')
    if (form.value.video_url) fd.append('video_url', form.value.video_url)
    if (form.value.external_url) fd.append('external_url', form.value.external_url)
    if (form.value.published_at) fd.append('published_at', form.value.published_at)
    if (form.value.category === 'event') {
      if (form.value.event_date) fd.append('event_date', form.value.event_date)
      if (form.value.event_location) fd.append('event_location', form.value.event_location)
    }
    if (form.value.image) fd.append('image', form.value.image)
    form.value.gallery.forEach(f => fd.append('gallery[]', f))

    if (isEditing.value) {
      fd.append('_method', 'PUT')
      await api.post(`/api/v1/admin/news/${form.value.id}`, fd)
      notify('تم تحديث الخبر بنجاح')
    } else {
      await api.post('/api/v1/admin/news', fd)
      notify('تم نشر الخبر بنجاح')
    }
    formDialog.value = false
    fetchNews()
  } catch (err: any) {
    console.error(err)
    notify(err?.response?.data?.message || 'تعذّر حفظ الخبر', 'error')
  } finally {
    formLoading.value = false
  }
}

// ── Delete ────────────────────────────────────────
const deleteDialog = ref(false)
const deleteLoading = ref(false)
const deletingItem = ref<any>(null)

const confirmDelete = (item: any) => { deletingItem.value = item; deleteDialog.value = true }

const deleteNews = async () => {
  deleteLoading.value = true
  try {
    await api.delete(`/api/v1/admin/news/${deletingItem.value.id}`)
    deleteDialog.value = false
    notify('تم حذف الخبر بنجاح')
    fetchNews()
  } catch (err) {
    console.error(err)
    notify('تعذّر حذف الخبر', 'error')
  } finally {
    deleteLoading.value = false
  }
}
</script>

<template>
  <div>
    <div class="d-flex justify-space-between align-center mb-6">
      <div>
        <h1 class="text-h4 font-weight-bold" style="font-family:Cairo,sans-serif">الأخبار والمناسبات</h1>
        <p class="text-body-2 text-medium-emphasis mb-0" style="font-family:Cairo,sans-serif">إدارة أخبار وإعلانات ومناسبات الاتحاد المنشورة على الموقع</p>
      </div>
      <VBtn color="primary" prepend-icon="tabler-plus" @click="openCreate">
        خبر / مناسبة جديدة
      </VBtn>
    </div>

    <VCard>
      <VCardText class="d-flex gap-4 flex-wrap">
        <VTextField
          v-model="search"
          placeholder="بحث في العنوان..."
          prepend-inner-icon="tabler-search"
          density="compact"
          style="max-width:280px"
          @update:model-value="page = 1"
        />
        <VSelect
          v-model="categoryFilter"
          :items="[{ title: 'كل التصنيفات', value: '' }, ...categoryOptions]"
          label="التصنيف"
          density="compact"
          clearable
          style="max-width:180px"
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
      >
        <template #item.title="{ item }">
          <div class="d-flex align-center gap-3">
            <VAvatar v-if="item.image" :image="item.image" size="40" rounded />
            <VAvatar v-else size="40" rounded color="secondary" variant="tonal">
              <VIcon icon="tabler-news" size="18" />
            </VAvatar>
            <span class="font-weight-medium" style="font-family:Cairo,sans-serif">{{ item.title }}</span>
          </div>
        </template>

        <template #item.category="{ item }">
          <VChip :color="getCategoryColor(item.category)" size="small" label style="font-family:Cairo,sans-serif">
            {{ getCategoryLabel(item.category) }}
          </VChip>
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
          <div class="text-center pa-6 text-medium-emphasis" style="font-family:Cairo,sans-serif">لا توجد أخبار أو مناسبات بعد</div>
        </template>
      </VDataTableServer>
    </VCard>

    <!-- Create/Edit Dialog -->
    <VDialog v-model="formDialog" max-width="680" scrollable>
      <VCard>
        <VCardTitle style="font-family:Cairo,sans-serif">{{ isEditing ? 'تعديل الخبر' : 'خبر / مناسبة جديدة' }}</VCardTitle>
        <VCardText>
          <VRow>
            <VCol cols="12" md="8">
              <VTextField v-model="form.title" label="العنوان" style="font-family:Cairo,sans-serif" />
            </VCol>
            <VCol cols="12" md="4">
              <VSelect
                v-model="form.category"
                :items="categoryOptions"
                label="التصنيف"
                style="font-family:Cairo,sans-serif"
              />
            </VCol>
            <VCol cols="12">
              <VTextarea v-model="form.excerpt" label="مقتطف مختصر (يظهر في القائمة)" rows="2" style="font-family:Cairo,sans-serif" />
            </VCol>
            <VCol cols="12">
              <label class="text-body-2 font-weight-medium mb-2 d-block" style="font-family:Cairo,sans-serif">المحتوى الكامل</label>
              <TiptapEditor v-model="form.body" placeholder="اكتب محتوى الخبر هنا..." class="border rounded" />
            </VCol>

            <VCol cols="12" md="6">
              <VFileInput
                label="الصورة الرئيسية"
                prepend-inner-icon="tabler-photo"
                prepend-icon=""
                accept="image/*"
                style="font-family:Cairo,sans-serif"
                :model-value="form.image ? [form.image] : []"
                @update:model-value="form.image = $event?.[0] ?? null"
              />
            </VCol>
            <VCol cols="12" md="6">
              <VFileInput
                label="معرض صور إضافي"
                prepend-inner-icon="tabler-photo-plus"
                prepend-icon=""
                accept="image/*"
                multiple
                style="font-family:Cairo,sans-serif"
                :model-value="form.gallery"
                @update:model-value="form.gallery = $event ?? []"
              />
            </VCol>

            <VCol cols="12" md="6">
              <VTextField v-model="form.video_url" label="رابط فيديو يوتيوب (اختياري)" prepend-inner-icon="tabler-brand-youtube" dir="ltr" />
            </VCol>
            <VCol cols="12" md="6">
              <VTextField v-model="form.external_url" label="رابط خارجي (اختياري)" prepend-inner-icon="tabler-external-link" dir="ltr" />
            </VCol>

            <template v-if="form.category === 'event'">
              <VCol cols="12" md="6">
                <VTextField v-model="form.event_date" label="موعد المناسبة" type="datetime-local" style="font-family:Cairo,sans-serif" />
              </VCol>
              <VCol cols="12" md="6">
                <VTextField v-model="form.event_location" label="مكان المناسبة" prepend-inner-icon="tabler-map-pin" style="font-family:Cairo,sans-serif" />
              </VCol>
            </template>

            <VCol cols="12" md="6">
              <VTextField v-model="form.published_at" label="تاريخ النشر (اختياري — الآن افتراضياً)" type="date" style="font-family:Cairo,sans-serif" />
            </VCol>
            <VCol cols="12" md="6" class="d-flex align-center">
              <VSwitch v-model="form.is_published" label="نشر مباشرة" color="success" style="font-family:Cairo,sans-serif" />
            </VCol>
          </VRow>
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn variant="tonal" @click="formDialog = false">إلغاء</VBtn>
          <VBtn color="primary" :loading="formLoading" @click="saveNews">{{ isEditing ? 'حفظ التعديلات' : 'نشر' }}</VBtn>
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
          <VBtn color="error" :loading="deleteLoading" @click="deleteNews">حذف</VBtn>
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

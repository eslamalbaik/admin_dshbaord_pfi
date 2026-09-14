<script setup lang="ts">
import api from '@/plugins/axios'
import { firstFile, toFileArray, type SingleFileModel } from '@/utils/files'

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
const publishedFilter = ref('')

const headers = [
  { title: 'العنوان', key: 'title' },
  { title: 'تاريخ النشر', key: 'published_at' },
  { title: 'الحالة', key: 'is_published' },
  { title: 'إجراءات', key: 'actions', sortable: false },
]

const fetchNews = async () => {
  loading.value = true
  try {
    const { data } = await api.get('/api/v1/admin/news', {
      params: {
        search: search.value || undefined,
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
  imagePreview: '' as string,
  gallery: [] as File[],
  existingGallery: [] as string[],
  video_url: '',
  external_url: '',
  is_published: false,
  published_at: '',
})

// حذف فوري بتأكيد — بدل نمط "علّم ثم احفظ" اللي كان يربك الأدمن (الصورة تفضل ظاهرة بعتامة لحد ما يحفظ الفورم كله)
const removeGalleryImageDialog = ref(false)
const removingGalleryImageUrl = ref<string | null>(null)

const confirmRemoveGalleryImage = (url: string) => {
  removingGalleryImageUrl.value = url
  removeGalleryImageDialog.value = true
}

const removeGalleryImageConfirmed = async () => {
  if (!removingGalleryImageUrl.value || !form.value.id) return
  try {
    await api.delete(`/api/v1/admin/news/${form.value.id}/gallery-image`, {
      data: { url: removingGalleryImageUrl.value },
    })
    form.value.existingGallery = form.value.existingGallery.filter(u => u !== removingGalleryImageUrl.value)
    notify('تم حذف الصورة.')
  } catch (err: any) {
    console.error(err)
    notify(err?.response?.data?.message || 'تعذّر حذف الصورة', 'error')
  } finally {
    removeGalleryImageDialog.value = false
    removingGalleryImageUrl.value = null
  }
}

const formDialog = ref(false)
const formLoading = ref(false)
const isEditing = ref(false)
const form = ref(emptyForm())

// VFileInput v-model must be a plain ref — binding it through a computed ternary
// (`form.image ? [form.image] : []`) silently breaks Vuetify's internal proxied
// model and the selected file never reaches form.image. Single-file VFileInput
// emits a bare `File` (not an array), so read it through firstFile().
const mainImageFile = ref<SingleFileModel>(null)

const openCreate = () => {
  form.value = emptyForm()
  mainImageFile.value = null
  isEditing.value = false
  formDialog.value = true
}

const openEdit = (item: any) => {
  form.value = {
    id: item.id,
    title: item.title,
    excerpt: item.excerpt ?? '',
    body: item.body ?? '',
    category: 'news',
    imagePreview: item.image ?? '',
    gallery: [],
    existingGallery: item.gallery ?? [],
    video_url: item.video_url ?? '',
    external_url: item.external_url ?? '',
    is_published: !!item.is_published,
    published_at: item.published_at ? item.published_at.substring(0, 10) : '',
  }
  mainImageFile.value = null
  isEditing.value = true
  formDialog.value = true
}

// ── View details (read-only) ───────────────────────
const viewDialog = ref(false)
const viewingItem = ref<any>(null)

const openView = (item: any) => {
  viewingItem.value = item
  viewDialog.value = true
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
    const mainImage = firstFile(mainImageFile.value)
    if (mainImage) fd.append('image', mainImage)
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
        <h1 class="text-h4 font-weight-bold" style="font-family:Cairo,sans-serif">الأخبار</h1>
        <p class="text-body-2 text-medium-emphasis mb-0" style="font-family:Cairo,sans-serif">إدارة أخبار وإعلانات وعطاءات الاتحاد المنشورة على الموقع</p>
      </div>
      <VBtn color="primary" prepend-icon="tabler-plus" @click="openCreate">
        خبر جديد
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
            <VAvatar v-else size="40" rounded color="secondary" variant="tonal">
              <VIcon icon="tabler-news" size="18" />
            </VAvatar>
            <span class="font-weight-medium" style="font-family:Cairo,sans-serif">{{ item.title }}</span>
          </div>
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
            <VBtn icon size="small" variant="text" color="info" @click="openView(item)">
              <VIcon icon="tabler-eye" />
              <VTooltip activator="parent">عرض التفاصيل</VTooltip>
            </VBtn>
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
          <div class="text-center pa-6 text-medium-emphasis" style="font-family:Cairo,sans-serif">لا توجد أخبار بعد</div>
        </template>
      </VDataTableServer>
    </VCard>

    <!-- Create/Edit Dialog -->
    <VDialog v-model="formDialog" max-width="680" scrollable>
      <VCard>
        <VCardTitle style="font-family:Cairo,sans-serif">{{ isEditing ? 'تعديل الخبر' : 'خبر جديد' }}</VCardTitle>
        <VCardText>
          <VRow>
            <VCol cols="12">
              <VTextField v-model="form.title" label="العنوان" style="font-family:Cairo,sans-serif" />
            </VCol>
            <VCol cols="12">
              <VTextarea v-model="form.excerpt" label="مقتطف مختصر (يظهر في القائمة)" rows="2" style="font-family:Cairo,sans-serif" />
            </VCol>
            <VCol cols="12">
              <label class="text-body-2 font-weight-medium mb-2 d-block" style="font-family:Cairo,sans-serif">المحتوى الكامل</label>
              <TiptapEditor v-model="form.body" placeholder="اكتب محتوى الخبر هنا..." class="border rounded" />
            </VCol>

            <VCol cols="12" md="6">
              <div v-if="form.imagePreview && !mainImageFile" class="d-flex align-center gap-2 mb-2">
                <VImg :src="form.imagePreview" width="48" height="48" cover rounded />
                <span class="text-caption text-medium-emphasis" style="font-family:Cairo,sans-serif">الصورة الحالية — اختر صورة جديدة لاستبدالها</span>
              </div>
              <VFileInput
                label="الصورة الرئيسية"
                prepend-inner-icon="tabler-photo"
                prepend-icon=""
                accept="image/*"
                style="font-family:Cairo,sans-serif"
                v-model="mainImageFile"
              />
            </VCol>
            <VCol cols="12" md="6">
              <VFileInput
                label="إضافة صور للمعرض"
                prepend-inner-icon="tabler-photo-plus"
                prepend-icon=""
                accept="image/*"
                multiple
                style="font-family:Cairo,sans-serif"
                :model-value="form.gallery"
                @update:model-value="form.gallery = toFileArray($event)"
              />
            </VCol>

            <VCol v-if="form.existingGallery.length" cols="12">
              <label class="text-body-2 font-weight-medium mb-2 d-block" style="font-family:Cairo,sans-serif">
                صور المعرض الحالية (اضغط أيقونة الحذف لإزالة صورة فوراً)
              </label>
              <div class="d-flex flex-wrap gap-3">
                <div
                  v-for="url in form.existingGallery"
                  :key="url"
                  class="position-relative"
                  style="width:80px;height:80px"
                >
                  <VImg :src="url" width="80" height="80" cover rounded />
                  <VIcon
                    icon="tabler-trash"
                    color="error"
                    size="18"
                    class="position-absolute"
                    style="top:2px;left:2px;background:white;border-radius:50%;padding:2px;cursor:pointer"
                    @click="confirmRemoveGalleryImage(url)"
                  />
                </div>
              </div>
            </VCol>

            <VCol cols="12" md="6">
              <VTextField v-model="form.video_url" label="رابط فيديو يوتيوب (اختياري)" prepend-inner-icon="tabler-brand-youtube" dir="ltr" />
            </VCol>
            <VCol cols="12" md="6">
              <VTextField v-model="form.external_url" label="رابط خارجي (اختياري)" prepend-inner-icon="tabler-external-link" dir="ltr" />
            </VCol>

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

    <!-- View Details Dialog -->
    <VDialog v-model="viewDialog" max-width="680" scrollable>
      <VCard v-if="viewingItem">
        <VCardTitle class="d-flex align-center justify-space-between" style="font-family:Cairo,sans-serif">
          <span>{{ viewingItem.title }}</span>
          <VChip :color="viewingItem.is_published ? 'success' : 'secondary'" size="small" label>
            {{ viewingItem.is_published ? 'منشور' : 'مسودة' }}
          </VChip>
        </VCardTitle>
        <VCardText>
          <VImg v-if="viewingItem.image" :src="viewingItem.image" max-height="280" class="mb-4 rounded" cover />

          <p v-if="viewingItem.excerpt" class="text-body-1 font-weight-medium mb-4" style="font-family:Cairo,sans-serif">
            {{ viewingItem.excerpt }}
          </p>

          <div class="text-body-2 mb-4" style="font-family:Cairo,sans-serif" v-html="viewingItem.body" />

          <div v-if="viewingItem.gallery?.length" class="mb-4">
            <label class="text-body-2 font-weight-medium mb-2 d-block" style="font-family:Cairo,sans-serif">صور المعرض</label>
            <div class="d-flex flex-wrap gap-3">
              <VImg
                v-for="url in (viewingItem.gallery ?? [])"
                :key="url"
                :src="url"
                width="90"
                height="90"
                cover
                rounded
              />
            </div>
          </div>

          <VDivider class="mb-4" />

          <VRow dense>
            <VCol v-if="viewingItem.video_url" cols="12" md="6">
              <span class="text-caption text-medium-emphasis d-block">رابط فيديو يوتيوب</span>
              <a :href="viewingItem.video_url" target="_blank" dir="ltr">{{ viewingItem.video_url }}</a>
            </VCol>
            <VCol v-if="viewingItem.external_url" cols="12" md="6">
              <span class="text-caption text-medium-emphasis d-block">رابط خارجي</span>
              <a :href="viewingItem.external_url" target="_blank" dir="ltr">{{ viewingItem.external_url }}</a>
            </VCol>
            <VCol cols="12" md="6">
              <span class="text-caption text-medium-emphasis d-block" style="font-family:Cairo,sans-serif">تاريخ النشر</span>
              <span style="font-family:Cairo,sans-serif">{{ viewingItem.published_at ? new Date(viewingItem.published_at).toLocaleDateString('ar-PS') : '—' }}</span>
            </VCol>
          </VRow>
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
          هل أنت متأكد من حذف <strong>{{ deletingItem?.title }}</strong>؟ لا يمكن التراجع عن هذا الإجراء.
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn variant="tonal" @click="deleteDialog = false">إلغاء</VBtn>
          <VBtn color="error" :loading="deleteLoading" @click="deleteNews">حذف</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Gallery Image Delete Confirm Dialog -->
    <VDialog v-model="removeGalleryImageDialog" max-width="400">
      <VCard>
        <VCardTitle class="d-flex align-center gap-2" style="font-family:Cairo,sans-serif">
          <VIcon icon="tabler-alert-triangle" color="error" />
          تأكيد حذف الصورة
        </VCardTitle>
        <VCardText style="font-family:Cairo,sans-serif">
          هل أنت متأكد من حذف هذه الصورة من المعرض؟ لا يمكن التراجع عن هذا الإجراء.
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn variant="tonal" @click="removeGalleryImageDialog = false">إلغاء</VBtn>
          <VBtn color="error" @click="removeGalleryImageConfirmed">حذف</VBtn>
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

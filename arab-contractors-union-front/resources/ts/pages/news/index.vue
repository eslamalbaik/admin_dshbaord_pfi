<script setup lang="ts">
import { useDisplay } from 'vuetify'
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
  imagePreview: '' as string,
  gallery: [] as File[],
  existingGallery: [] as string[],
  video_url: '',
  external_url: '',
  publishMode: 'now' as PublishMode,
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

// حد أقصى 5 صور بالمعرض بالمجموع (موجودة + مُضافة حديثاً) — نفس القيد اللي الباك اند يتحقق منه
const galleryTotalCount = computed(() => form.value.existingGallery.length + form.value.gallery.length)
const galleryOverLimitMessage = computed(() =>
  galleryTotalCount.value > 5 ? [`تجاوزت الحد الأقصى (${galleryTotalCount.value}/5) — احذف صور قبل الإضافة`] : [],
)

// ── قيود الصور — نفس قيود الباك اند (jpg/png/webp، 10 ميجا) وتُفحص فور الاختيار
const MAX_IMAGE_MB = 10
const MAX_TOTAL_UPLOAD_MB = 55
const MAX_GALLERY = 5
const ALLOWED_IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/webp']
const IMAGE_ACCEPT = ALLOWED_IMAGE_TYPES.join(',')

/** يرجّع سبب رفض الصورة (أو null لو مقبولة) */
const imageProblem = (file: File, label: string): string | null => {
  if (!ALLOWED_IMAGE_TYPES.includes(file.type))
    return `${label} "${file.name}" صيغتها غير مدعومة — المسموح: JPG أو PNG أو WEBP.`
  if (file.size > MAX_IMAGE_MB * 1024 * 1024)
    return `${label} "${file.name}" حجمها ${(file.size / 1024 / 1024).toFixed(1)} ميجابايت — يتجاوز الحد الأقصى ${MAX_IMAGE_MB} ميجابايت.`

  return null
}

// معاينات الصور المُختارة حديثاً (لسه ما انرفعت) — تُبنى من كائنات File مباشرة، وتُحرَّر
// (revokeObjectURL) عند تغيّر القائمة لمنع تسريب الذاكرة
const newGalleryPreviews = ref<string[]>([])

watch(() => form.value.gallery, files => {
  newGalleryPreviews.value.forEach(url => URL.revokeObjectURL(url))
  newGalleryPreviews.value = files.map(f => URL.createObjectURL(f))
}, { immediate: true })

const removeNewGalleryFile = (index: number) => {
  form.value.gallery = form.value.gallery.filter((_, i) => i !== index)
}

// حقل اختيار صور المعرض مجرد "زر إضافة": كل اختيار جديد ينضاف فوق الصور المختارة قبله
// (بدل ما يستبدلها)، وبعدها الحقل يرجع فاضي. القائمة الفعلية بـ form.gallery وبتنعرض بالمعاينة.
const galleryPicker = ref<File[]>([])

const onGalleryFilesSelected = async (files: File[]) => {
  if (!files.length)
    return

  const problems: string[] = []
  const isDuplicate = (f: File) => form.value.gallery.some(g => g.name === f.name && g.size === f.size && g.lastModified === f.lastModified)
  const accepted = files.filter(f => {
    const problem = imageProblem(f, 'الصورة')
    if (problem)
      problems.push(problem)

    return !problem && !isDuplicate(f)
  })

  const room = MAX_GALLERY - galleryTotalCount.value
  if (accepted.length > room)
    problems.push(`الحد الأقصى ${MAX_GALLERY} صور للمعرض — انضاف ${Math.max(room, 0)} من ${accepted.length} صور.`)

  form.value.gallery = [...form.value.gallery, ...accepted.slice(0, Math.max(room, 0))]
  if (problems.length)
    notify(problems.join(' — '), 'error')

  await nextTick()
  galleryPicker.value = []
}

// معاينة الصورة الرئيسية المختارة حديثاً — بالإضافة والتعديل (مش بس الصورة المحفوظة بالتعديل)
const mainImagePreview = ref('')

const onMainImageSelected = (value: SingleFileModel) => {
  const file = firstFile(value)
  const problem = file ? imageProblem(file, 'الصورة الرئيسية') : null

  if (problem)
    notify(problem, 'error')
  mainImageFile.value = file && !problem ? file : null
}

watch(mainImageFile, value => {
  if (mainImagePreview.value)
    URL.revokeObjectURL(mainImagePreview.value)
  const file = firstFile(value)
  mainImagePreview.value = file ? URL.createObjectURL(file) : ''
})

// طريقة النشر: مباشرة (بدون تاريخ — الخادم يعتمد وقت الحفظ) / جدولة (التاريخ مطلوب) / مسودة.
// is_published يُرسل 1 للمباشر والمجدول معاً لأن scopePublished بالموديل يتطلبه، والجدولة
// تعتمد على published_at لاحق.
type PublishMode = 'now' | 'schedule' | 'draft'

const publishModeOptions = [
  { value: 'now', label: 'نشر مباشرة', icon: 'tabler-send' },
  { value: 'schedule', label: 'جدولة', icon: 'tabler-calendar-time' },
  { value: 'draft', label: 'مسودة', icon: 'tabler-file-pencil' },
]

// تاريخ بصيغة YYYY-MM-DD بالتوقيت المحلي (toISOString كان يرجّع تاريخ UTC — غلط بعد منتصف الليل)
const localDateStr = (d: Date) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
const todayStr = () => localDateStr(new Date())

// حالة الخبر عند فتحه للتعديل — لتحديد شو لازم ينبعت للخادم عند الحفظ
const originalPublishMode = ref<PublishMode>('now')
const originalPublishedAt = ref('')

// تاريخ النشر يظهر بالجدولة بس (نفس فورم الفعاليات) — "نشر مباشرة" بيعتمد وقت الحفظ.
// أقل تاريخ مسموح اليوم، بالإضافة والتعديل؛ التاريخ المحفوظ أصلاً بيضل مقبول لو ما تغيّر.
const showPublishDate = computed(() => form.value.publishMode === 'schedule')

const openCreate = () => {
  form.value = emptyForm()
  originalPublishMode.value = 'now'
  originalPublishedAt.value = ''
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
    imagePreview: item.image ?? '',
    gallery: [],
    existingGallery: item.gallery ?? [],
    video_url: item.video_url ?? '',
    external_url: item.external_url ?? '',
    publishMode: !item.is_published
      ? 'draft'
      : (item.published_at && new Date(item.published_at) > new Date() ? 'schedule' : 'now'),
    published_at: item.published_at ? item.published_at.substring(0, 10) : '',
  }
  originalPublishMode.value = form.value.publishMode
  originalPublishedAt.value = form.value.published_at
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

const { xs } = useDisplay()

const plainText = (s?: string | null) => (s ?? '').replace(/\u00A0/g, ' ').replace(/\s+/g, ' ').trim()

// كثير أخبار بيبدأ محتواها بنفس العنوان (منسوخ من فيسبوك/الموقع) — بنشيله من العرض عشان ما يتكرر تحت العنوان
const viewBodyHtml = computed(() => {
  const item = viewingItem.value
  if (!item?.body)
    return ''
  const doc = new DOMParser().parseFromString(item.body, 'text/html')
  let first = doc.body.firstElementChild
  while (first && !plainText(first.textContent) && !first.querySelector('img,iframe,video')) {
    first.remove()
    first = doc.body.firstElementChild
  }
  if (first && plainText(first.textContent) === plainText(item.title))
    first.remove()

  return doc.body.innerHTML
})

// المقتطف بيظهر كمقدمة بس لو مش مكرر حرفياً بالمحتوى
const showViewExcerpt = computed(() => {
  const excerpt = plainText(viewingItem.value?.excerpt)
  if (!excerpt)
    return false
  const bodyText = plainText(new DOMParser().parseFromString(viewBodyHtml.value, 'text/html').body.textContent)

  return !bodyText.includes(excerpt)
})

const formatLongDate = (d?: string | null) =>
  d ? new Date(d).toLocaleDateString('ar-PS', { year: 'numeric', month: 'long', day: 'numeric' }) : ''

const viewStatus = computed(() => {
  const item = viewingItem.value
  if (!item?.is_published)
    return { label: 'مسودة', color: 'secondary', icon: 'tabler-file-pencil' }
  if (item.published_at && new Date(item.published_at) > new Date())
    return { label: 'مجدول', color: 'warning', icon: 'tabler-calendar-time' }

  return { label: 'منشور', color: 'success', icon: 'tabler-circle-check' }
})

const lightboxImage = ref<string | null>(null)

const saveNews = async () => {
  if (!form.value.title || !form.value.body) {
    notify('العنوان والمحتوى مطلوبان', 'error')
    return
  }
  const mode = form.value.publishMode
  if (mode === 'schedule' && !form.value.published_at) {
    notify('حدد تاريخ النشر للخبر المجدول', 'error')

    return
  }
  if (showPublishDate.value && form.value.published_at
    && form.value.published_at !== originalPublishedAt.value
    && form.value.published_at < todayStr()) {
    notify('يجب أن يكون تاريخ النشر اليوم أو بعده', 'error')

    return
  }
  if (galleryTotalCount.value > 5) {
    notify('تجاوزت الحد الأقصى لصور المعرض (5 صور)', 'error')
    return
  }
  // الخادم (post_max_size) بيقبل 60 ميجا للطلب كله — فوقها بيوصل الطلب فاضي وبيرجع خطأ بدون سبب واضح
  const totalUploadMb = [firstFile(mainImageFile.value), ...form.value.gallery]
    .reduce((sum, f) => sum + (f?.size ?? 0), 0) / 1024 / 1024
  if (totalUploadMb > MAX_TOTAL_UPLOAD_MB) {
    notify(`مجموع حجم الصور ${totalUploadMb.toFixed(1)} ميجابايت — الحد الأقصى ${MAX_TOTAL_UPLOAD_MB} ميجابايت بالمرة الواحدة. صغّر بعض الصور أو ضيفها بتعديل لاحق.`, 'error')

    return
  }
  formLoading.value = true
  try {
    const fd = new FormData()
    fd.append('title', form.value.title)
    fd.append('excerpt', form.value.excerpt)
    fd.append('body', form.value.body)
    fd.append('is_published', mode === 'draft' ? '0' : '1')
    if (form.value.video_url) fd.append('video_url', form.value.video_url)
    if (form.value.external_url) fd.append('external_url', form.value.external_url)
    if (mode === 'schedule')
      fd.append('published_at', form.value.published_at)
    // خبر كان مسودة/مجدول وصار "نشر مباشرة": تاريخ نشره يصير وقت الحفظ
    else if (mode === 'now' && isEditing.value && originalPublishMode.value !== 'now')
      fd.append('publish_now', '1')
    const mainImage = firstFile(mainImageFile.value)
    if (mainImage) fd.append('image', mainImage)
    form.value.gallery.forEach(f => fd.append('gallery[]', f))

    // رفع 6 صور (رئيسية + معرض) على اتصال بطيء ممكن يتجاوز مهلة الـ60 ثانية العامة
    // فينلغى الطلب (canceled) وما يوصل الخادم — مهلة أطول لطلب الحفظ بس
    const config = { timeout: 300000 }
    if (isEditing.value) {
      fd.append('_method', 'PUT')
      await api.post(`/api/v1/admin/news/${form.value.id}`, fd, config)
      notify('تم تحديث الخبر بنجاح')
    } else {
      await api.post('/api/v1/admin/news', fd, config)
      notify('تم نشر الخبر بنجاح')
    }
    formDialog.value = false
    fetchNews()
  } catch (err: any) {
    console.error(err)
    // الخادم يرجّع رسالة عامة بـmessage ("البيانات المدخلة غير صحيحة") وتفاصيل السبب الفعلي
    // (حجم صورة، تاريخ نشر...) بمفتاح errors — عرض message وحدها كان يُخفي السبب عن الأدمن
    const fieldErrors = err?.response?.data?.errors
    const reason = fieldErrors ? Object.values(fieldErrors).flat().join(' — ') : null
    const status = err?.response?.status
    const fallback = status === 413
      ? 'حجم الطلب كبير جداً — صغّر الصور وحاول مرة ثانية'
      : status
        ? `تعذّر حفظ الخبر (خطأ ${status})`
        : err?.code === 'ECONNABORTED'
          ? 'تعذّر حفظ الخبر — انتهت مهلة رفع الصور، تحقق من الاتصال أو صغّر الصور'
          : 'تعذّر حفظ الخبر — تحقق من الاتصال'
    notify(reason || err?.response?.data?.message || fallback, 'error')
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
    <div class="d-flex justify-space-between align-center flex-wrap gap-4 mb-6">
      <div>
        <h1 class="text-h4 font-weight-bold" style="font-family:Cairo,sans-serif">الأخبار</h1>
        <p class="text-body-2 text-medium-emphasis mb-0" style="font-family:Cairo,sans-serif">إدارة أخبار وإعلانات وعطاءات الاتحاد المنشورة على الموقع</p>
      </div>
      <VBtn v-if="$can('content.news', 'create')" color="primary" prepend-icon="tabler-plus" @click="openCreate">
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
            <VBtn v-if="$can('content.news', 'update')" icon size="small" variant="text" color="primary" @click="openEdit(item)">
              <VIcon icon="tabler-pencil" />
              <VTooltip activator="parent">تعديل</VTooltip>
            </VBtn>
            <VBtn v-if="$can('content.news', 'delete')" icon size="small" variant="text" color="error" @click="confirmDelete(item)">
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
                :accept="IMAGE_ACCEPT"
                :hint="`JPG أو PNG أو WEBP — الحد الأقصى ${MAX_IMAGE_MB} ميجابايت`"
                persistent-hint
                style="font-family:Cairo,sans-serif"
                :model-value="mainImageFile"
                @update:model-value="onMainImageSelected"
              />
              <VImg v-if="mainImagePreview" :src="mainImagePreview" max-height="140" class="mt-2 rounded border" cover />
            </VCol>
            <VCol cols="12" md="6">
              <VFileInput
                :label="`إضافة صور للمعرض (${galleryTotalCount}/${MAX_GALLERY})`"
                prepend-inner-icon="tabler-photo-plus"
                prepend-icon=""
                :accept="IMAGE_ACCEPT"
                multiple
                :disabled="galleryTotalCount >= MAX_GALLERY"
                :error-messages="galleryOverLimitMessage"
                :hint="galleryTotalCount >= MAX_GALLERY
                  ? `وصلت للحد الأقصى ${MAX_GALLERY} صور — احذف صورة لتضيف غيرها`
                  : `كل اختيار جديد بينضاف للصور المختارة — الحد الأقصى ${MAX_IMAGE_MB} ميجابايت لكل صورة`"
                persistent-hint
                style="font-family:Cairo,sans-serif"
                :model-value="galleryPicker"
                @update:model-value="onGalleryFilesSelected(toFileArray($event))"
              />
            </VCol>

            <VCol v-if="newGalleryPreviews.length" cols="12">
              <label class="text-body-2 font-weight-medium mb-2 d-block" style="font-family:Cairo,sans-serif">
                معاينة الصور المُختارة (لم تُنشر بعد — اضغط زر ✕ الأحمر فوق الصورة لإزالتها)
              </label>
              <div class="d-flex flex-wrap gap-3">
                <div
                  v-for="(url, index) in newGalleryPreviews"
                  :key="url"
                  class="position-relative"
                  style="width:96px;height:96px"
                >
                  <VImg :src="url" width="96" height="96" cover rounded class="border" />
                  <VBtn
                    icon
                    size="x-small"
                    color="error"
                    variant="elevated"
                    class="gallery-remove-btn"
                    aria-label="حذف الصورة"
                    @click="removeNewGalleryFile(index)"
                  >
                    <VIcon icon="tabler-x" size="16" />
                    <VTooltip activator="parent" location="top">حذف الصورة</VTooltip>
                  </VBtn>
                </div>
              </div>
            </VCol>

            <VCol v-if="form.existingGallery.length" cols="12">
              <label class="text-body-2 font-weight-medium mb-2 d-block" style="font-family:Cairo,sans-serif">
                صور المعرض الحالية (اضغط زر ✕ الأحمر فوق الصورة لحذفها نهائياً)
              </label>
              <div class="d-flex flex-wrap gap-3">
                <div
                  v-for="url in form.existingGallery"
                  :key="url"
                  class="position-relative"
                  style="width:96px;height:96px"
                >
                  <VImg :src="url" width="96" height="96" cover rounded class="border" />
                  <VBtn
                    icon
                    size="x-small"
                    color="error"
                    variant="elevated"
                    class="gallery-remove-btn"
                    aria-label="حذف الصورة"
                    @click="confirmRemoveGalleryImage(url)"
                  >
                    <VIcon icon="tabler-x" size="16" />
                    <VTooltip activator="parent" location="top">حذف الصورة</VTooltip>
                  </VBtn>
                </div>
              </div>
            </VCol>

            <VCol cols="12" md="6">
              <VTextField v-model="form.video_url" label="رابط فيديو يوتيوب (اختياري)" prepend-inner-icon="tabler-brand-youtube" dir="ltr" />
            </VCol>
            <VCol cols="12" md="6">
              <VTextField v-model="form.external_url" label="رابط خارجي (اختياري)" prepend-inner-icon="tabler-external-link" dir="ltr" />
            </VCol>

            <VCol cols="12">
              <label class="text-body-2 font-weight-medium mb-2 d-block" style="font-family:Cairo,sans-serif">طريقة النشر</label>
              <VBtnToggle
                v-model="form.publishMode"
                mandatory
                color="primary"
                variant="outlined"
                divided
                density="comfortable"
                class="publish-mode-toggle"
                style="font-family:Cairo,sans-serif"
              >
                <VBtn v-for="opt in publishModeOptions" :key="opt.value" :value="opt.value" :prepend-icon="opt.icon">
                  {{ opt.label }}
                </VBtn>
              </VBtnToggle>
            </VCol>
            <!-- تاريخ النشر يظهر بالجدولة بس — "نشر مباشرة" بيعتمد وقت الحفظ -->
            <VCol v-if="showPublishDate" cols="12" md="6">
              <VTextField
                v-model="form.published_at"
                label="تاريخ النشر المجدول"
                type="date"
                :min="todayStr()"
                prepend-inner-icon="tabler-calendar"
                hint="اليوم أو أي تاريخ بعده"
                persistent-hint
                style="font-family:Cairo,sans-serif"
              />
            </VCol>
          </VRow>
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn variant="tonal" @click="formDialog = false">إلغاء</VBtn>
          <VBtn color="primary" :loading="formLoading" :disabled="galleryTotalCount > 5" @click="saveNews">{{ isEditing ? 'حفظ التعديلات' : 'نشر' }}</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- View Details Dialog -->
    <VDialog v-model="viewDialog" max-width="780" scrollable :fullscreen="xs">
      <VCard v-if="viewingItem" class="news-view">
        <VBtn
          icon
          size="small"
          variant="elevated"
          color="surface"
          class="news-view__close"
          aria-label="إغلاق"
          @click="viewDialog = false"
        >
          <VIcon icon="tabler-x" size="20" />
        </VBtn>

        <VCardText class="pa-0">
          <div v-if="viewingItem.image" class="news-view__hero">
            <VImg :src="viewingItem.image" cover width="100%" :height="xs ? 220 : 360" />
          </div>

          <article class="news-view__article" :class="{ 'news-view__article--no-image': !viewingItem.image }">
            <div class="d-flex align-center flex-wrap gap-x-4 gap-y-2 mb-4">
              <VChip :color="viewStatus.color" size="small" label :prepend-icon="viewStatus.icon">
                {{ viewStatus.label }}
              </VChip>
              <span v-if="viewingItem.published_at" class="news-view__meta">
                <VIcon icon="tabler-calendar" size="16" />
                {{ formatLongDate(viewingItem.published_at) }}
              </span>
            </div>

            <h2 class="news-view__title">
              {{ viewingItem.title }}
            </h2>

            <p v-if="showViewExcerpt" class="news-view__excerpt">
              {{ viewingItem.excerpt }}
            </p>

            <div class="news-view__content" v-html="viewBodyHtml" />

            <section v-if="viewingItem.gallery?.length" class="mt-8">
              <h3 class="news-view__section-title">
                <VIcon icon="tabler-photo" size="18" />
                صور المعرض
                <span class="text-medium-emphasis">({{ viewingItem.gallery.length }})</span>
              </h3>
              <div class="news-view__gallery">
                <button
                  v-for="url in viewingItem.gallery"
                  :key="url"
                  type="button"
                  class="news-view__gallery-item"
                  aria-label="عرض الصورة بحجم كامل"
                  @click="lightboxImage = url"
                >
                  <VImg :src="url" cover aspect-ratio="1" />
                </button>
              </div>
            </section>

            <section v-if="viewingItem.video_url || viewingItem.external_url" class="mt-8">
              <h3 class="news-view__section-title">
                <VIcon icon="tabler-link" size="18" />
                روابط مرفقة
              </h3>
              <div class="d-flex flex-wrap gap-3">
                <VBtn
                  v-if="viewingItem.video_url"
                  :href="viewingItem.video_url"
                  target="_blank"
                  rel="noopener noreferrer"
                  color="error"
                  variant="tonal"
                  prepend-icon="tabler-brand-youtube"
                >
                  مشاهدة الفيديو
                </VBtn>
                <VBtn
                  v-if="viewingItem.external_url"
                  :href="viewingItem.external_url"
                  target="_blank"
                  rel="noopener noreferrer"
                  color="primary"
                  variant="tonal"
                  prepend-icon="tabler-external-link"
                >
                  فتح الرابط الخارجي
                </VBtn>
              </div>
            </section>
          </article>
        </VCardText>

        <VDivider />
        <VCardActions class="news-view__actions">
          <VSpacer />
          <VBtn variant="tonal" color="secondary" @click="viewDialog = false">إغلاق</VBtn>
          <VBtn
            v-if="$can('content.news', 'update')"
            variant="elevated"
            color="primary"
            prepend-icon="tabler-pencil"
            @click="viewDialog = false; openEdit(viewingItem)"
          >
            تعديل الخبر
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Gallery Lightbox -->
    <VDialog :model-value="!!lightboxImage" max-width="1100" @update:model-value="v => { if (!v) lightboxImage = null }">
      <VCard v-if="lightboxImage" class="news-view">
        <VBtn icon size="small" variant="elevated" color="surface" class="news-view__close" aria-label="إغلاق" @click="lightboxImage = null">
          <VIcon icon="tabler-x" size="20" />
        </VBtn>
        <VImg :src="lightboxImage" max-height="85vh" contain />
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

<style scoped>
/* أزرار طريقة النشر بعرض الفورم كامل وبعروض متساوية — كانت محشورة بنص عمود ويقطع النص */
.publish-mode-toggle {
  display: flex;
  inline-size: 100%;
}

.publish-mode-toggle .v-btn {
  flex: 1 1 0;
}

/* ── عرض الخبر ── */
.news-view {
  position: relative;
  font-family: Cairo, sans-serif;
}

.news-view__close {
  position: absolute;
  top: 12px;
  inset-inline-end: 12px;
  z-index: 2;
}

.news-view__hero {
  position: relative;
  background: rgba(var(--v-theme-on-surface), 0.04);
}

.news-view__article {
  padding: 24px 32px 32px;
}

.news-view__article--no-image {
  padding-block-start: 28px;
}

.news-view__meta {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity));
  font-size: 0.875rem;
}

/* العنوان كامل على أكثر من سطر — بدون قص */
.news-view__title {
  margin: 0 0 16px;
  color: rgba(var(--v-theme-on-surface), var(--v-high-emphasis-opacity));
  font-size: 1.6rem;
  font-weight: 700;
  line-height: 1.6;
  overflow-wrap: anywhere;
  white-space: normal;
}

.news-view__article--no-image .news-view__title {
  padding-inline-end: 40px;
}

.news-view__excerpt {
  margin: 0 0 20px;
  padding: 12px 16px;
  border-inline-start: 4px solid rgb(var(--v-theme-primary));
  border-radius: 4px;
  background: rgba(var(--v-theme-primary), 0.06);
  color: rgba(var(--v-theme-on-surface), var(--v-high-emphasis-opacity));
  font-size: 1.05rem;
  font-weight: 500;
  line-height: 1.9;
}

.news-view__content {
  color: rgba(var(--v-theme-on-surface), 0.85);
  font-size: 1rem;
  line-height: 2;
  overflow-wrap: anywhere;
}

.news-view__content :deep(p) {
  margin-block-end: 1em;
}

.news-view__content :deep(h1),
.news-view__content :deep(h2),
.news-view__content :deep(h3) {
  margin-block: 1.2em 0.6em;
  font-size: 1.15rem;
  line-height: 1.6;
}

.news-view__content :deep(img) {
  max-inline-size: 100%;
  block-size: auto;
  border-radius: 8px;
}

.news-view__content :deep(ul),
.news-view__content :deep(ol) {
  margin-block-end: 1em;
  padding-inline-start: 1.5em;
}

.news-view__content :deep(blockquote) {
  margin: 1em 0;
  padding-inline-start: 1em;
  border-inline-start: 3px solid rgba(var(--v-theme-on-surface), 0.2);
}

.news-view__section-title {
  display: flex;
  align-items: center;
  gap: 6px;
  margin-block-end: 12px;
  font-size: 1rem;
  font-weight: 600;
}

.news-view__gallery {
  display: grid;
  gap: 10px;
  grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
}

.news-view__gallery-item {
  overflow: hidden;
  padding: 0;
  border: 0;
  border-radius: 8px;
  cursor: zoom-in;
  transition: transform 0.15s, box-shadow 0.15s;
}

.news-view__gallery-item:hover {
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
  transform: translateY(-2px);
}

.news-view__actions {
  padding: 12px 24px;
}

@media (max-width: 599px) {
  .news-view__article {
    padding: 18px 16px 24px;
  }

  .news-view__title {
    font-size: 1.3rem;
  }

  .news-view__actions {
    padding: 10px 16px;
  }
}

.gallery-remove-btn {
  position: absolute;
  top: -8px;
  inset-inline-end: -8px;
  z-index: 1;
}
</style>

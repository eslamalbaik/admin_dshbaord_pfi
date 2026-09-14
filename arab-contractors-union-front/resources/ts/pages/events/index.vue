<script setup lang="ts">
import api from '@/plugins/axios'
import { firstFile, type SingleFileModel } from '@/utils/files'

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

const eventFormatOptions = [
  { title: 'وجاهي', value: 'onsite' },
  { title: 'أونلاين', value: 'online' },
  { title: 'وجاهي + أونلاين', value: 'hybrid' },
]

const eventTypeOptions = [
  { title: 'فعالية دولية', value: 'international' },
  { title: 'فعالية مؤسسات', value: 'institutional' },
  { title: 'فعالية محلية للاتحاد', value: 'local' },
]

const headers = [
  { title: 'العنوان', key: 'title' },
  { title: 'موعد الفعالية', key: 'event_date' },
  { title: 'المكان', key: 'event_location' },
  { title: 'المهتمون', key: 'registrations_count', sortable: false },
  { title: 'الحالة', key: 'is_published' },
  { title: 'إجراءات', key: 'actions', sortable: false },
]

const fetchEvents = async () => {
  loading.value = true
  try {
    const { data } = await api.get('/api/v1/admin/events', {
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

watchEffect(() => fetchEvents())

// ── Create/Edit form ──────────────────────────────
interface SpeakerForm { name: string; title: string; photo: string; is_keynote: boolean; photoFile: File | null }

const emptyForm = () => ({
  id: null as number | null,
  title: '',
  excerpt: '',
  body: '',
  imagePreview: '' as string,
  video_url: '',
  external_url: '',
  event_date: '',
  event_location: '',
  event_format: '' as string,
  event_type: 'local' as string,
  stream_url: '',
  speakers: [] as SpeakerForm[],
  is_published: false,
  published_at: '',
})

const addSpeaker = () => form.value.speakers.push({ name: '', title: '', photo: '', is_keynote: false, photoFile: null })

// متحدث رئيسي واحد بحد أقصى — تفعيل متحدث يُلغي تلقائياً أي متحدث رئيسي آخر (بند 9و)
const onKeynoteToggle = (index: number, value: boolean) => {
  if (value)
    form.value.speakers.forEach((sp, i) => { sp.is_keynote = i === index })
  else
    form.value.speakers[index].is_keynote = false
}

const speakerDeleteDialog = ref(false)
const speakerDeleteIndex = ref<number | null>(null)

const confirmRemoveSpeaker = (i: number) => { speakerDeleteIndex.value = i; speakerDeleteDialog.value = true }

const removeSpeaker = () => {
  if (speakerDeleteIndex.value !== null)
    form.value.speakers.splice(speakerDeleteIndex.value, 1)
  speakerDeleteDialog.value = false
  speakerDeleteIndex.value = null
}

const formDialog = ref(false)
const formLoading = ref(false)
const isEditing = ref(false)
const form = ref(emptyForm())

// VFileInput v-model must be a plain ref — binding it through a computed ternary
// silently breaks Vuetify's internal proxied model. Single-file VFileInput emits
// a bare `File` (not an array), so read it through firstFile().
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
    imagePreview: item.image ?? '',
    video_url: item.video_url ?? '',
    external_url: item.external_url ?? '',
    event_date: item.event_date ? item.event_date.substring(0, 16) : '',
    event_location: item.event_location ?? '',
    event_format: item.event_format ?? '',
    event_type: item.event_type ?? 'local',
    stream_url: item.stream_url ?? '',
    speakers: Array.isArray(item.speakers) ? item.speakers.map((s: any) => ({
      name: s.name ?? '', title: s.title ?? '', photo: s.photo ?? '', is_keynote: !!s.is_keynote, photoFile: null,
    })) : [],
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

const eventTypeLabel = (t: string) => eventTypeOptions.find(o => o.value === t)?.title ?? t
const eventFormatLabel = (f: string) => eventFormatOptions.find(o => o.value === f)?.title ?? f

const saveEvent = async () => {
  if (!form.value.title || !form.value.body) {
    notify('العنوان والمحتوى مطلوبان', 'error')
    return
  }
  if (form.value.event_format === 'onsite' && !form.value.event_location) {
    notify('مكان الفعالية مطلوب لأن نوع الحضور وجاهي', 'error')
    return
  }
  formLoading.value = true
  try {
    const fd = new FormData()
    fd.append('title', form.value.title)
    fd.append('excerpt', form.value.excerpt)
    fd.append('body', form.value.body)
    fd.append('is_published', form.value.is_published ? '1' : '0')
    if (form.value.video_url) fd.append('video_url', form.value.video_url)
    if (form.value.external_url) fd.append('external_url', form.value.external_url)
    if (form.value.published_at) fd.append('published_at', form.value.published_at)
    if (form.value.event_date) fd.append('event_date', form.value.event_date)
    if (form.value.event_location) fd.append('event_location', form.value.event_location)
    if (form.value.event_format) fd.append('event_format', form.value.event_format)
    fd.append('event_type', form.value.event_type)
    if (form.value.stream_url) fd.append('stream_url', form.value.stream_url)
    form.value.speakers.forEach((s, i) => {
      fd.append(`speakers[${i}][name]`, s.name)
      if (s.title) fd.append(`speakers[${i}][title]`, s.title)
      if (s.photo) fd.append(`speakers[${i}][photo]`, s.photo)
      fd.append(`speakers[${i}][is_keynote]`, s.is_keynote ? '1' : '0')
      if (s.photoFile) fd.append(`speaker_photos[${i}]`, s.photoFile)
    })
    const mainImage = firstFile(mainImageFile.value)
    if (mainImage) fd.append('image', mainImage)

    if (isEditing.value) {
      fd.append('_method', 'PUT')
      await api.post(`/api/v1/admin/events/${form.value.id}`, fd)
      notify('تم تحديث الفعالية بنجاح')
    } else {
      await api.post('/api/v1/admin/events', fd)
      notify('تم نشر الفعالية بنجاح')
    }
    formDialog.value = false
    fetchEvents()
  } catch (err: any) {
    console.error(err)
    notify(err?.response?.data?.message || 'تعذّر حفظ الفعالية', 'error')
  } finally {
    formLoading.value = false
  }
}

// ── Attendees (المهتمون بالانضمام) ─────────────────
interface Attendee {
  contractor_id: number
  name: string | null
  membership_number: string | null
  phone: string | null
  registered_at: string | null
}

const attendeesDialog = ref(false)
const attendeesLoading = ref(false)
const attendeesEvent = ref<any>(null)
const attendees = ref<Attendee[]>([])

const attendeesHeaders = [
  { title: 'المقاول', key: 'name' },
  { title: 'رقم العضوية', key: 'membership_number' },
  { title: 'الهاتف', key: 'phone' },
  { title: 'تاريخ التسجيل', key: 'registered_at' },
]

const openAttendees = async (item: any) => {
  attendeesEvent.value = item
  attendees.value = []
  attendeesDialog.value = true
  attendeesLoading.value = true
  try {
    const { data } = await api.get(`/api/v1/admin/events/${item.id}/attendees`)
    attendees.value = data.items?.attendees ?? []
  } catch (err) {
    console.error(err)
    notify('تعذّر جلب قائمة المهتمين', 'error')
  } finally {
    attendeesLoading.value = false
  }
}

const exportAttendees = () => {
  const rows = [
    ['الاسم', 'رقم العضوية', 'الهاتف', 'تاريخ التسجيل'],
    ...attendees.value.map(a => [
      a.name ?? '',
      a.membership_number ?? '',
      a.phone ?? '',
      a.registered_at ? new Date(a.registered_at).toLocaleString('ar-PS') : '',
    ]),
  ]

  // BOM لضمان قراءة Excel للعربية بترميز UTF-8
  const csv = '﻿' + rows.map(r => r.map(c => `"${String(c).replace(/"/g, '""')}"`).join(',')).join('\n')
  const url = URL.createObjectURL(new Blob([csv], { type: 'text/csv;charset=utf-8;' }))
  const link = document.createElement('a')

  link.href = url
  link.download = `attendees-event-${attendeesEvent.value?.id}.csv`
  link.click()
  URL.revokeObjectURL(url)
}

// ── Delete ────────────────────────────────────────
const deleteDialog = ref(false)
const deleteLoading = ref(false)
const deletingItem = ref<any>(null)

const confirmDelete = (item: any) => { deletingItem.value = item; deleteDialog.value = true }

const deleteEvent = async () => {
  deleteLoading.value = true
  try {
    await api.delete(`/api/v1/admin/events/${deletingItem.value.id}`)
    deleteDialog.value = false
    notify('تم حذف الفعالية بنجاح')
    fetchEvents()
  } catch (err) {
    console.error(err)
    notify('تعذّر حذف الفعالية', 'error')
  } finally {
    deleteLoading.value = false
  }
}
</script>

<template>
  <div>
    <div class="d-flex justify-space-between align-center mb-6">
      <div>
        <h1 class="text-h4 font-weight-bold" style="font-family:Cairo,sans-serif">الفعاليات</h1>
        <p class="text-body-2 text-medium-emphasis mb-0" style="font-family:Cairo,sans-serif">إدارة فعاليات الاتحاد المنشورة على الموقع والتطبيق</p>
      </div>
      <VBtn color="primary" prepend-icon="tabler-plus" @click="openCreate">
        فعالية جديدة
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
              <VIcon icon="tabler-calendar-event" size="18" />
            </VAvatar>
            <span class="font-weight-medium" style="font-family:Cairo,sans-serif">{{ item.title }}</span>
          </div>
        </template>

        <template #item.event_date="{ item }">
          {{ item.event_date ? new Date(item.event_date).toLocaleString('ar-PS', { dateStyle: 'medium', timeStyle: 'short' }) : '—' }}
        </template>

        <template #item.event_location="{ item }">
          {{ item.event_location || '—' }}
        </template>

        <template #item.registrations_count="{ item }">
          <VChip
            :color="item.registrations_count ? 'info' : 'secondary'"
            size="small"
            label
            :variant="item.registrations_count ? 'tonal' : 'outlined'"
            style="cursor:pointer;font-family:Cairo,sans-serif"
            @click="openAttendees(item)"
          >
            <VIcon icon="tabler-users" size="14" start />
            {{ item.registrations_count ?? 0 }}
          </VChip>
        </template>

        <template #item.is_published="{ item }">
          <VChip :color="item.is_published ? 'success' : 'secondary'" size="small" label style="font-family:Cairo,sans-serif">
            {{ item.is_published ? 'منشور' : 'مسودة' }}
          </VChip>
        </template>

        <template #item.actions="{ item }">
          <div class="d-flex align-center gap-1">
            <VBtn icon size="small" variant="text" color="info" @click="openAttendees(item)">
              <VIcon icon="tabler-users" />
              <VTooltip activator="parent">المهتمون بالانضمام</VTooltip>
            </VBtn>
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
          <div class="text-center pa-6 text-medium-emphasis" style="font-family:Cairo,sans-serif">لا توجد فعاليات بعد</div>
        </template>
      </VDataTableServer>
    </VCard>

    <!-- Create/Edit Dialog -->
    <VDialog v-model="formDialog" max-width="680" scrollable>
      <VCard>
        <VCardTitle style="font-family:Cairo,sans-serif">{{ isEditing ? 'تعديل الفعالية' : 'فعالية جديدة' }}</VCardTitle>
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
              <TiptapEditor v-model="form.body" placeholder="اكتب تفاصيل الفعالية هنا..." class="border rounded" />
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
              <p class="text-caption text-medium-emphasis mt-1" style="font-family:Cairo,sans-serif">
                صورة رئيسية واحدة فقط — لا يوجد معرض صور للفعاليات
              </p>
            </VCol>
            <VCol cols="12" md="6">
              <VTextField v-model="form.video_url" label="رابط فيديو يوتيوب (اختياري)" prepend-inner-icon="tabler-brand-youtube" dir="ltr" />
            </VCol>

            <VCol cols="12" md="6">
              <VTextField v-model="form.external_url" label="رابط خارجي (اختياري)" prepend-inner-icon="tabler-external-link" dir="ltr" />
            </VCol>
            <VCol cols="12" md="6">
              <VTextField v-model="form.event_date" label="موعد الفعالية" type="datetime-local" style="font-family:Cairo,sans-serif" />
            </VCol>

            <VCol cols="12" md="4">
              <VSelect
                v-model="form.event_type"
                :items="eventTypeOptions"
                label="نوع الفعالية *"
                style="font-family:Cairo,sans-serif"
              />
            </VCol>
            <VCol cols="12" md="4">
              <VSelect
                v-model="form.event_format"
                :items="eventFormatOptions"
                label="نوع الحضور"
                clearable
                style="font-family:Cairo,sans-serif"
              />
            </VCol>
            <VCol v-if="form.event_format === 'onsite'" cols="12" md="4">
              <VTextField
                v-model="form.event_location"
                label="مكان الفعالية *"
                prepend-inner-icon="tabler-map-pin"
                :rules="[v => !!v || 'مطلوب لأن نوع الحضور وجاهي']"
                style="font-family:Cairo,sans-serif"
              />
            </VCol>

            <VCol cols="12" md="6">
              <VTextField
                v-model="form.stream_url"
                label="رابط البث المباشر (Zoom)"
                prepend-inner-icon="tabler-video"
                dir="ltr"
                hint="يظهر للمقاولين يوم الفعالية فقط"
                persistent-hint
              />
            </VCol>

            <VCol cols="12">
              <div class="d-flex justify-space-between align-center mb-2">
                <span class="text-body-2 font-weight-medium" style="font-family:Cairo,sans-serif">المتحدثون</span>
                <VBtn size="small" variant="tonal" prepend-icon="tabler-plus" @click="addSpeaker">
                  إضافة متحدث
                </VBtn>
              </div>
              <VRow v-for="(sp, i) in form.speakers" :key="i" align="center" class="mb-1">
                <VCol cols="12" md="3">
                  <VTextField v-model="sp.name" label="الاسم" density="compact" style="font-family:Cairo,sans-serif" />
                </VCol>
                <VCol cols="12" md="3">
                  <VTextField v-model="sp.title" label="المسمى/الصفة" density="compact" style="font-family:Cairo,sans-serif" />
                </VCol>
                <VCol cols="12" md="3">
                  <div v-if="sp.photo && !sp.photoFile" class="d-flex align-center gap-1 mb-1">
                    <VImg :src="sp.photo" width="28" height="28" cover rounded />
                    <span class="text-caption text-medium-emphasis" style="font-family:Cairo,sans-serif">صورة حالية</span>
                  </div>
                  <VFileInput
                    v-model="sp.photoFile"
                    label="صورة المتحدث"
                    density="compact"
                    accept="image/*"
                    prepend-icon=""
                    style="font-family:Cairo,sans-serif"
                  />
                </VCol>
                <VCol cols="12" md="2">
                  <VSwitch
                    :model-value="sp.is_keynote"
                    label="متحدث رئيسي"
                    density="compact"
                    style="font-family:Cairo,sans-serif"
                    @update:model-value="onKeynoteToggle(i, $event as boolean)"
                  />
                </VCol>
                <VCol cols="12" md="1" class="text-center">
                  <VBtn icon="tabler-trash" size="small" variant="text" color="error" @click="confirmRemoveSpeaker(i)" />
                </VCol>
              </VRow>
              <p v-if="!form.speakers.length" class="text-body-2 text-medium-emphasis" style="font-family:Cairo,sans-serif">
                لا يوجد متحدثون مضافون.
              </p>
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
          <VBtn color="primary" :loading="formLoading" @click="saveEvent">{{ isEditing ? 'حفظ التعديلات' : 'نشر' }}</VBtn>
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

          <VRow dense class="mb-3">
            <VCol cols="6" md="4">
              <span class="text-caption text-medium-emphasis d-block" style="font-family:Cairo,sans-serif">موعد الفعالية</span>
              <span style="font-family:Cairo,sans-serif">{{ viewingItem.event_date ? new Date(viewingItem.event_date).toLocaleString('ar-PS', { dateStyle: 'medium', timeStyle: 'short' }) : '—' }}</span>
            </VCol>
            <VCol cols="6" md="4">
              <span class="text-caption text-medium-emphasis d-block" style="font-family:Cairo,sans-serif">المكان</span>
              <span style="font-family:Cairo,sans-serif">{{ viewingItem.event_location || '—' }}</span>
            </VCol>
            <VCol cols="6" md="4">
              <span class="text-caption text-medium-emphasis d-block" style="font-family:Cairo,sans-serif">نوع الحضور</span>
              <span style="font-family:Cairo,sans-serif">{{ viewingItem.event_format ? eventFormatLabel(viewingItem.event_format) : '—' }}</span>
            </VCol>
            <VCol cols="6" md="4">
              <span class="text-caption text-medium-emphasis d-block" style="font-family:Cairo,sans-serif">نوع الفعالية</span>
              <span style="font-family:Cairo,sans-serif">{{ viewingItem.event_type ? eventTypeLabel(viewingItem.event_type) : '—' }}</span>
            </VCol>
            <VCol v-if="viewingItem.stream_url" cols="6" md="4">
              <span class="text-caption text-medium-emphasis d-block" style="font-family:Cairo,sans-serif">رابط البث</span>
              <a :href="viewingItem.stream_url" target="_blank" dir="ltr">{{ viewingItem.stream_url }}</a>
            </VCol>
          </VRow>

          <VDivider class="mb-4" />

          <div class="text-body-2 mb-4" style="font-family:Cairo,sans-serif" v-html="viewingItem.body" />

          <div v-if="viewingItem.speakers?.length" class="mb-2">
            <label class="text-body-2 font-weight-medium mb-2 d-block" style="font-family:Cairo,sans-serif">المتحدثون</label>
            <div class="d-flex flex-wrap gap-3">
              <div v-for="(sp, i) in viewingItem.speakers" :key="i" class="d-flex align-center gap-2">
                <VAvatar v-if="sp.photo" :image="sp.photo" size="36" />
                <VAvatar v-else size="36" color="secondary" variant="tonal">
                  <VIcon icon="tabler-user" size="18" />
                </VAvatar>
                <div>
                  <div class="d-flex align-center gap-1">
                    <span class="text-body-2 font-weight-medium" style="font-family:Cairo,sans-serif">{{ sp.name }}</span>
                    <VChip v-if="sp.is_keynote" size="x-small" color="primary" label>رئيسي</VChip>
                  </div>
                  <span v-if="sp.title" class="text-caption text-medium-emphasis" style="font-family:Cairo,sans-serif">{{ sp.title }}</span>
                </div>
              </div>
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

    <!-- Speaker Delete Confirm Dialog -->
    <VDialog v-model="speakerDeleteDialog" max-width="400">
      <VCard>
        <VCardTitle class="d-flex align-center gap-2" style="font-family:Cairo,sans-serif">
          <VIcon icon="tabler-alert-triangle" color="error" />
          تأكيد حذف المتحدث
        </VCardTitle>
        <VCardText style="font-family:Cairo,sans-serif">
          هل أنت متأكد من حذف هذا المتحدث؟ لا يمكن التراجع عن هذا الإجراء.
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn variant="tonal" @click="speakerDeleteDialog = false">إلغاء</VBtn>
          <VBtn color="error" @click="removeSpeaker">حذف</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Attendees Dialog -->
    <VDialog v-model="attendeesDialog" max-width="760" scrollable>
      <VCard>
        <VCardTitle class="d-flex align-center justify-space-between flex-wrap gap-2" style="font-family:Cairo,sans-serif">
          <div>
            <div>المهتمون بالانضمام</div>
            <div class="text-body-2 text-medium-emphasis">{{ attendeesEvent?.title }}</div>
          </div>
          <VBtn
            size="small"
            variant="tonal"
            prepend-icon="tabler-download"
            :disabled="!attendees.length"
            @click="exportAttendees"
          >
            تصدير CSV
          </VBtn>
        </VCardTitle>

        <VCardText>
          <VProgressLinear v-if="attendeesLoading" indeterminate color="primary" class="mb-4" />

          <div v-else-if="!attendees.length" class="text-center pa-6 text-medium-emphasis" style="font-family:Cairo,sans-serif">
            لا يوجد مقاولون سجّلوا انضمامهم لهذه الفعالية بعد
          </div>

          <VDataTable
            v-else
            :headers="attendeesHeaders"
            :items="attendees"
            :items-per-page="10"
            mobile-breakpoint="sm"
          >
            <template #item.name="{ item }">
              <span class="font-weight-medium" style="font-family:Cairo,sans-serif">{{ item.name || '—' }}</span>
            </template>
            <template #item.membership_number="{ item }">
              <span dir="ltr">{{ item.membership_number || '—' }}</span>
            </template>
            <template #item.phone="{ item }">
              <a v-if="item.phone" :href="`tel:${item.phone}`" dir="ltr">{{ item.phone }}</a>
              <span v-else>—</span>
            </template>
            <template #item.registered_at="{ item }">
              {{ item.registered_at ? new Date(item.registered_at).toLocaleString('ar-PS', { dateStyle: 'medium', timeStyle: 'short' }) : '—' }}
            </template>
          </VDataTable>
        </VCardText>

        <VCardActions>
          <VSpacer />
          <VBtn variant="tonal" @click="attendeesDialog = false">إغلاق</VBtn>
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
          <VBtn color="error" :loading="deleteLoading" @click="deleteEvent">حذف</VBtn>
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

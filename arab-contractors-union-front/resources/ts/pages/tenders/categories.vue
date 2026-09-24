<script setup lang="ts">
import api from '@/plugins/axios'
import { firstFile } from '@/utils/files'

definePage({ meta: { requiresAdmin: true, adminOnly: true } })

interface TenderCategory {
  id: number
  name: string
  image_url: string | null
  is_active: boolean
  sort_order: number
  tenders_count: number
}

const categories = ref<TenderCategory[]>([])
const loading = ref(false)

// ── Snackbar ──────────────────────────────────────
const snackbar = ref(false)
const snackbarText = ref('')
const snackbarColor = ref('success')
const notify = (text: string, color: 'success' | 'error' = 'success') => {
  snackbarText.value = text
  snackbarColor.value = color
  snackbar.value = true
}

const errorText = (err: any, fallback: string): string => {
  const errors = err?.response?.data?.errors
  if (errors && typeof errors === 'object') {
    const first = Object.values(errors)[0] as any

    return Array.isArray(first) ? first[0] : String(first)
  }

  return err?.response?.data?.message || fallback
}

const fetchCategories = async () => {
  loading.value = true
  try {
    const { data } = await api.get('/api/v1/tender-categories')
    categories.value = data.items ?? []
  }
  catch (err) {
    console.error(err)
    notify('تعذّر تحميل التصنيفات', 'error')
  }
  finally {
    loading.value = false
  }
}

// ── إضافة / تعديل ────────────────────────────────
const formDialog = ref(false)
const saving = ref(false)
const form = ref({ id: null as number | null, name: '', is_active: true, image: null as File | null, image_url: null as string | null, remove_image: false })

const imagePreview = computed(() => {
  if (form.value.image)
    return URL.createObjectURL(form.value.image)

  return form.value.remove_image ? null : form.value.image_url
})

const openCreate = () => {
  form.value = { id: null, name: '', is_active: true, image: null, image_url: null, remove_image: false }
  formDialog.value = true
}

const openEdit = (c: TenderCategory) => {
  form.value = { id: c.id, name: c.name, is_active: c.is_active, image: null, image_url: c.image_url, remove_image: false }
  formDialog.value = true
}

const save = async () => {
  if (!form.value.name.trim()) {
    notify('اسم التصنيف مطلوب', 'error')

    return
  }
  saving.value = true
  try {
    const fd = new FormData()
    fd.append('name', form.value.name.trim())
    fd.append('is_active', form.value.is_active ? '1' : '0')
    if (form.value.image)
      fd.append('image', form.value.image)
    if (form.value.id) {
      fd.append('_method', 'PATCH')
      if (form.value.remove_image && !form.value.image)
        fd.append('remove_image', '1')
      await api.post(`/api/v1/tender-categories/${form.value.id}`, fd)
    }
    else {
      await api.post('/api/v1/tender-categories', fd)
    }
    formDialog.value = false
    notify(form.value.id ? 'تم تحديث التصنيف' : 'تمت إضافة التصنيف')
    fetchCategories()
  }
  catch (err) {
    console.error(err)
    notify(errorText(err, 'تعذّر حفظ التصنيف'), 'error')
  }
  finally {
    saving.value = false
  }
}

// ── تفعيل / تعطيل سريع ──────────────────────────
const togglingId = ref<number | null>(null)

const toggleActive = async (c: TenderCategory) => {
  togglingId.value = c.id
  try {
    await api.patch(`/api/v1/tender-categories/${c.id}`, { is_active: !c.is_active })
    c.is_active = !c.is_active
  }
  catch (err) {
    notify(errorText(err, 'تعذّر تغيير الحالة'), 'error')
  }
  finally {
    togglingId.value = null
  }
}

// ── حذف ───────────────────────────────────────────
const deleteDialog = ref(false)
const deleting = ref(false)
const deletingItem = ref<TenderCategory | null>(null)

const confirmDelete = (c: TenderCategory) => {
  deletingItem.value = c
  deleteDialog.value = true
}

const remove = async () => {
  if (!deletingItem.value)
    return
  deleting.value = true
  try {
    await api.delete(`/api/v1/tender-categories/${deletingItem.value.id}`)
    deleteDialog.value = false
    notify('تم حذف التصنيف')
    fetchCategories()
  }
  catch (err) {
    notify(errorText(err, 'تعذّر حذف التصنيف'), 'error')
  }
  finally {
    deleting.value = false
  }
}

onMounted(fetchCategories)
</script>

<template>
  <div>
    <div class="d-flex justify-space-between align-center flex-wrap gap-4 mb-6">
      <div>
        <h1 class="text-h4 font-weight-bold" style="font-family:Cairo,sans-serif">تصنيفات العطاءات</h1>
        <p class="text-body-2 text-medium-emphasis mb-0" style="font-family:Cairo,sans-serif">
          لكل تصنيف صورة افتراضية تُعرض للعطاءات التابعة له (العطاءات لا تحمل صوراً خاصة بها)
        </p>
      </div>
      <VBtn color="primary" prepend-icon="tabler-plus" @click="openCreate">
        تصنيف جديد
      </VBtn>
    </div>

    <div v-if="loading && !categories.length" class="d-flex justify-center pa-10">
      <VProgressCircular indeterminate color="primary" />
    </div>

    <VRow v-else>
      <VCol v-for="c in categories" :key="c.id" cols="12" sm="6" md="4" lg="3">
        <VCard :class="{ 'opacity-60': !c.is_active }">
          <VImg v-if="c.image_url" :src="c.image_url" height="150" cover />
          <div v-else class="d-flex flex-column align-center justify-center gap-1" style="height:150px;background:rgba(var(--v-theme-on-surface),0.04)">
            <VIcon icon="tabler-photo-off" size="32" color="secondary" />
            <span class="text-caption text-medium-emphasis" style="font-family:Cairo,sans-serif">بلا صورة افتراضية</span>
          </div>
          <VCardText>
            <div class="d-flex align-center justify-space-between gap-2 mb-1">
              <span class="font-weight-medium text-truncate" style="font-family:Cairo,sans-serif">{{ c.name }}</span>
              <VChip :color="c.is_active ? 'success' : 'secondary'" size="x-small" label style="font-family:Cairo,sans-serif">
                {{ c.is_active ? 'فعّال' : 'معطّل' }}
              </VChip>
            </div>
            <div class="text-caption text-medium-emphasis" style="font-family:Cairo,sans-serif">
              {{ c.tenders_count }} عطاء
            </div>
          </VCardText>
          <VCardActions>
            <VBtn size="small" variant="text" color="primary" prepend-icon="tabler-pencil" @click="openEdit(c)">تعديل</VBtn>
            <VBtn size="small" variant="text" :loading="togglingId === c.id" @click="toggleActive(c)">
              {{ c.is_active ? 'تعطيل' : 'تفعيل' }}
            </VBtn>
            <VSpacer />
            <VBtn icon size="small" variant="text" color="error" :disabled="c.tenders_count > 0" @click="confirmDelete(c)">
              <VIcon icon="tabler-trash" size="18" />
              <VTooltip activator="parent">{{ c.tenders_count > 0 ? 'مرتبط بعطاءات — عطّله بدلاً من حذفه' : 'حذف' }}</VTooltip>
            </VBtn>
          </VCardActions>
        </VCard>
      </VCol>
      <VCol v-if="!categories.length" cols="12">
        <div class="text-center pa-6 text-medium-emphasis" style="font-family:Cairo,sans-serif">لا توجد تصنيفات</div>
      </VCol>
    </VRow>

    <!-- إضافة / تعديل -->
    <VDialog v-model="formDialog" max-width="480">
      <VCard>
        <VCardTitle style="font-family:Cairo,sans-serif">{{ form.id ? 'تعديل التصنيف' : 'تصنيف جديد' }}</VCardTitle>
        <VCardText>
          <VTextField v-model="form.name" label="اسم التصنيف" class="mb-4" style="font-family:Cairo,sans-serif" />

          <div class="text-body-2 font-weight-medium mb-2" style="font-family:Cairo,sans-serif">الصورة الافتراضية</div>
          <VImg v-if="imagePreview" :src="imagePreview" height="160" cover class="rounded mb-2" />
          <div class="d-flex align-center gap-2 mb-4">
            <VFileInput
              :model-value="form.image"
              label="اختر صورة (JPG / PNG / WEBP — حتى 2MB)"
              accept="image/jpeg,image/png,image/webp"
              prepend-icon=""
              prepend-inner-icon="tabler-photo"
              density="compact"
              hide-details
              style="flex:1"
              @update:model-value="form.image = firstFile($event as any); form.remove_image = false"
            />
            <VBtn
              v-if="form.id && form.image_url && !form.remove_image && !form.image"
              variant="text"
              color="error"
              size="small"
              @click="form.remove_image = true"
            >
              إزالة الصورة
            </VBtn>
          </div>

          <VSwitch v-model="form.is_active" label="فعّال (يظهر عند إضافة عطاء)" color="success" hide-details style="font-family:Cairo,sans-serif" />
          <p v-if="form.id" class="text-caption text-medium-emphasis mt-2 mb-0" style="font-family:Cairo,sans-serif">
            تغيير الاسم يُطبَّق تلقائياً على كل العطاءات المرتبطة بهذا التصنيف.
          </p>
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn variant="tonal" @click="formDialog = false">إلغاء</VBtn>
          <VBtn color="primary" :loading="saving" @click="save">حفظ</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- تأكيد الحذف -->
    <VDialog v-model="deleteDialog" max-width="400">
      <VCard>
        <VCardTitle class="d-flex align-center gap-2" style="font-family:Cairo,sans-serif">
          <VIcon icon="tabler-alert-triangle" color="error" />
          تأكيد الحذف
        </VCardTitle>
        <VCardText style="font-family:Cairo,sans-serif">
          هل أنت متأكد من حذف التصنيف <strong>{{ deletingItem?.name }}</strong>؟ لا يمكن التراجع عن هذا الإجراء.
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn variant="tonal" @click="deleteDialog = false">إلغاء</VBtn>
          <VBtn color="error" :loading="deleting" @click="remove">حذف</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <VSnackbar v-model="snackbar" :timeout="3500" :color="snackbarColor" location="bottom end" variant="elevated">
      <span style="font-family:Cairo,sans-serif">{{ snackbarText }}</span>
    </VSnackbar>
  </div>
</template>

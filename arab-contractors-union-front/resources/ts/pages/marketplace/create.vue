<script setup lang="ts">
import api from '@/plugins/axios'
import { useRoute, useRouter } from 'vue-router'

definePage({ meta: { requiresAdmin: true,
    adminOnly: true } })

const route  = useRoute()
const router = useRouter()

const editId     = computed(() => route.query.id as string | undefined)
const isEdit     = computed(() => !!editId.value)
const pageTitle  = computed(() => isEdit.value ? 'تعديل بيانات الآلية' : 'إضافة آلية جديدة')

// ─── Reference Data ──────────────────────────────────────────────────────────
const types       = ref<any[]>([])
const contractors = ref<any[]>([])

// types يُجلب كاملاً (فعّال + مخفي)، ونفلتر هون للظاهر فقط — مع استثناء: لو كانت الآلية
// الحالية (وضع التعديل) مربوطة بنوع صار مخفياً بعدين، يبقى ظاهراً كخيار وإلا يختفي من
// الفورم عند فتح التعديل (نفس النمط المستخدم بمنتقي التعديل السريع في marketplace/index.vue)
const typeOptions = computed(() => {
  const active = types.value.filter((t: any) => t.is_active)
  const currentId = form.value.equipment_type_id
  if (currentId && !active.some((t: any) => String(t.id) === String(currentId))) {
    const current = types.value.find((t: any) => String(t.id) === String(currentId))
    if (current) return [...active, current]
  }

  return active
})

const governorates = [
  'غزة', 'شمال غزة', 'خانيونس', 'رفح', 'الوسطى',
  'رام الله والبيرة', 'نابلس', 'جنين', 'طولكرم', 'قلقيلية',
  'بيت لحم', 'الخليل', 'أريحا', 'سلفيت', 'طوباس',
]

// ─── Form ────────────────────────────────────────────────────────────────────
const saving  = ref(false)
const loading = ref(false)

const form = ref({
  contractor_id:     '',
  equipment_type_id: '',
  name:              '',
  brand:             '',
  description:       '',
  manufacture_year:  null as number | null,
  power:             '',
  condition:         'good',
  contract_type:     'daily',
  governorate:       '',
  city:              '',
  owner_phone:       '',
  status:            'visible',
  is_featured:       false,
  needs_maintenance: false,
  admin_notes:       '',
  publishMode:       'now' as PublishMode,
  published_at:      '',
})

// ─── تاريخ النشر (REQ-08 #17) ────────────────────────────────────────────────
// "مباشر" = تظهر بالسوق فور الحفظ، "مجدول" = ما بتظهر بالتطبيق قبل التاريخ المختار.
// التاريخ من بكرا وطالع (تاريخ اليوم +1) — نفس قاعدة فورم الفعاليات، والخادم بيفرضها كمان.
type PublishMode = 'now' | 'schedule'

const publishModeOptions = [
  { value: 'now', label: 'نشر مباشر', icon: 'tabler-send' },
  { value: 'schedule', label: 'نشر مجدول', icon: 'tabler-calendar-time' },
]

// تاريخ بصيغة YYYY-MM-DD بالتوقيت المحلي (toISOString يرجّع تاريخ UTC — غلط بعد منتصف الليل)
const localDateStr = (d: Date) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
const tomorrowStr = () => localDateStr(new Date(Date.now() + 24 * 60 * 60 * 1000))

const minDateHint = computed(() => {
  const [y, m, d] = tomorrowStr().split('-')

  return `ابتداءً من ${d}/${m}/${y} (تاريخ اليوم +1)`
})

// موعد النشر الأصلي بالتعديل — تعديل آلية مجدولة بدون تغيير موعدها ما لازم يرفضه قيد "من بكرا"
const originalPublishedAt = ref('')

const publishModeHint = computed(() => form.value.publishMode === 'schedule'
  ? 'الآلية بتنحفظ هلق، بس ما بتظهر بسوق الآليات بالتطبيق قبل تاريخ النشر.'
  : 'الآلية بتظهر بالسوق فور الحفظ (إذا كانت حالة الظهور "ظاهر في السوق").')

// ─── الصور الجديدة: معاينة بنفس الفورم + حذف كل صورة لحالها + اختيار الغلاف (REQ-08 #14–#16، #18)
const newImages      = ref<File[]>([])
const imagePreviews  = ref<string[]>([])
const coverIndex     = ref(0)
const imageErrors    = ref('')
const imagePicker    = ref<File[]>([])

// آلية مخفية بقرار المالك أو موقوفة بقرار الإدارة ما بتنعرض بالسوق، فخيار "تظهر أولاً في السوق"
// بيتلغى وبيتعطّل معها (REQ-08 #20) — والخادم بيفرض نفس القاعدة
const isSuspended = computed(() => ['hidden', 'suspended'].includes(form.value.status))

watch(isSuspended, suspended => {
  if (suspended)
    form.value.is_featured = false
})

// ─── Load reference data + edit data ─────────────────────────────────────────
const fetchRef = async () => {
  const [typesRes, contractorsRes] = await Promise.all([
    api.get('/api/v1/equipment-types'),
    api.get('/api/v1/contractors?per_page=200'),
  ])
  types.value       = typesRes.data
  contractors.value = contractorsRes.data.data ?? contractorsRes.data
}

const fetchEquipment = async () => {
  loading.value = true
  try {
    const { data } = await api.get(`/api/v1/equipment/${editId.value}`)
    form.value = {
      contractor_id:     String(data.contractor_id),
      equipment_type_id: String(data.equipment_type_id),
      name:              data.name,
      brand:             data.brand ?? '',
      description:       data.description ?? '',
      manufacture_year:  data.manufacture_year ?? null,
      power:             data.power ?? '',
      condition:         data.condition ?? 'good',
      contract_type:     data.contract_type ?? 'daily',
      governorate:       data.governorate ?? '',
      city:              data.city ?? '',
      owner_phone:       data.owner_phone ?? '',
      status:            data.status ?? 'visible',
      is_featured:       !!data.is_featured,
      needs_maintenance: !!data.needs_maintenance,
      admin_notes:       data.admin_notes ?? '',
      publishMode:       data.published_at && new Date(data.published_at) > new Date() ? 'schedule' : 'now',
      published_at:      data.published_at ? localDateStr(new Date(data.published_at)) : '',
    }
    originalPublishedAt.value = form.value.published_at
  }
  finally {
    loading.value = false
  }
}

onMounted(async () => {
  await fetchRef()
  if (isEdit.value) await fetchEquipment()
})

// ─── Image selection ─────────────────────────────────────────────────────────
const MAX_IMAGES   = 5
const MAX_IMAGE_MB = 5

// حقل الاختيار مجرد "زر إضافة": كل اختيار جديد ينضاف فوق الصور المختارة قبله (بدل ما يستبدلها)،
// وبعدها الحقل يرجع فاضي. القائمة الفعلية بـ newImages وبتنعرض كمعاينات تحت الحقل.
const onFilesSelected = async (value: File[] | File | null) => {
  const files = Array.isArray(value) ? value : value ? [value] : []
  if (!files.length) return

  const problems: string[] = []
  const isDuplicate = (f: File) => newImages.value.some(g => g.name === f.name && g.size === f.size && g.lastModified === f.lastModified)
  const accepted = files.filter(f => {
    if (f.size > MAX_IMAGE_MB * 1024 * 1024) {
      problems.push(`حجم الصورة "${f.name}" (${(f.size / 1024 / 1024).toFixed(1)} ميغابايت) يتجاوز الحد الأقصى ${MAX_IMAGE_MB} ميغابايت.`)

      return false
    }

    return !isDuplicate(f)
  })

  const room = MAX_IMAGES - newImages.value.length
  if (accepted.length > room)
    problems.push(`الحد الأقصى ${MAX_IMAGES} صور — انضاف ${Math.max(room, 0)} من ${accepted.length}.`)

  newImages.value = [...newImages.value, ...accepted.slice(0, Math.max(room, 0))]
  imageErrors.value = problems.join(' — ')

  await nextTick()
  imagePicker.value = []
}

// روابط المعاينة المحلية تتحرر (revokeObjectURL) عند تغيّر القائمة لمنع تسريب الذاكرة
watch(newImages, files => {
  imagePreviews.value.forEach(url => URL.revokeObjectURL(url))
  imagePreviews.value = files.map(f => URL.createObjectURL(f))
})

onBeforeUnmount(() => imagePreviews.value.forEach(url => URL.revokeObjectURL(url)))

const removeImage = (index: number) => {
  newImages.value = newImages.value.filter((_, i) => i !== index)
  imageErrors.value = ''

  // الغلاف يضل على نفس الصورة؛ ولو انحذف الغلاف نفسه بتصير أول صورة هي الغلاف
  if (index === coverIndex.value)
    coverIndex.value = 0
  else if (index < coverIndex.value)
    coverIndex.value--
}

// ─── Submit ───────────────────────────────────────────────────────────────────
const successDialog = ref(false)
const createdItem   = ref<any>(null)

const goToPreview = () => router.push({ name: 'marketplace-id', params: { id: String(createdItem.value.id) } })

// "إضافة آلية أخرى" — يفضّي الفورم والصور بدون ما يطلع من الصفحة
const resetForm = () => {
  const keepContractor = form.value.contractor_id
  form.value = {
    contractor_id: keepContractor, equipment_type_id: '', name: '', brand: '', description: '',
    manufacture_year: null, power: '', condition: 'good', contract_type: 'daily', governorate: '',
    city: '', owner_phone: '', status: 'visible', is_featured: false, needs_maintenance: false,
    admin_notes: '', publishMode: 'now', published_at: '',
  }
  newImages.value = []
  coverIndex.value = 0
  imageErrors.value = ''
  successDialog.value = false
  window.scrollTo({ top: 0, behavior: 'smooth' })
}

const submit = async () => {
  if (!form.value.name.trim() || !form.value.contractor_id || !form.value.equipment_type_id) {
    alert('يرجى ملء الحقول المطلوبة: المالك، النوع، الاسم')
    return
  }

  if (form.value.publishMode === 'schedule') {
    if (!form.value.published_at) {
      alert('حدد تاريخ النشر للآلية المجدولة')
      return
    }
    if (form.value.published_at !== originalPublishedAt.value && form.value.published_at < tomorrowStr()) {
      alert(`تاريخ النشر المجدول لازم يكون ${minDateHint.value}`)
      return
    }
  }

  saving.value = true
  try {
    const payload = new FormData()

    const { publishMode, published_at: publishedAt, ...fields } = form.value

    Object.entries(fields).forEach(([key, val]) => {
      if (val !== null && val !== undefined && val !== '') {
        if (typeof val === 'boolean')
          payload.append(key, val ? '1' : '0')
        else
          payload.append(key, String(val))
      }
    })

    payload.append('publish_mode', publishMode)
    if (publishMode === 'schedule')
      payload.append('published_at', publishedAt)

    if (!isEdit.value) {
      newImages.value.forEach(file => payload.append('images[]', file))
      if (newImages.value.length)
        payload.append('primary_index', String(coverIndex.value))
    }

    if (isEdit.value) {
      payload.append('_method', 'PATCH')
      await api.post(`/api/v1/equipment/${editId.value}`, payload, {
        headers: { 'Content-Type': 'multipart/form-data' },
      })
      router.push({ name: 'marketplace-id', params: { id: editId.value! } })
    }
    else {
      const { data } = await api.post('/api/v1/equipment', payload, {
        headers: { 'Content-Type': 'multipart/form-data' },
      })

      // بعد الإضافة: نافذة نجاح فيها زر "معاينة" ينقل لصفحة معاينة الآلية (REQ-08 #19)
      createdItem.value = data
      successDialog.value = true
    }
  }
  catch (e: any) {
    const errors = e?.response?.data?.errors
    if (errors) alert(Object.values(errors).flat().join('\n'))
    else alert(e?.response?.data?.message ?? 'حدث خطأ أثناء الحفظ')
  }
  finally {
    saving.value = false
  }
}

const statusOptions = [
  { title: 'ظاهر في السوق', value: 'visible' },
  { title: 'مخفي (بقرار المالك)', value: 'hidden' },
  { title: 'موقوف (بقرار الإدارة)', value: 'suspended' },
]

const conditionOptions = [
  { title: 'ممتازة', value: 'excellent' },
  { title: 'جيدة', value: 'good' },
  { title: 'بحاجة صيانة', value: 'needs_maintenance' },
]

const contractTypeOptions = [
  { title: 'تأجير يومي', value: 'daily' },
  { title: 'تأجير أسبوعي', value: 'weekly' },
  { title: 'تأجير شهري', value: 'monthly' },
]
</script>

<template>
  <div>
    <!-- Header -->
    <div class="d-flex align-center justify-space-between flex-wrap gap-4 mb-6">
      <div>
        <h1 class="text-h4 font-weight-bold" style="font-family:Cairo,sans-serif">{{ pageTitle }}</h1>
        <p class="text-body-2 text-medium-emphasis mb-0" style="font-family:Cairo,sans-serif">
          {{ isEdit ? 'تعديل بيانات الآلية ومواصفاتها' : 'تسجيل آلية جديدة في سوق الآليات' }}
        </p>
      </div>
      <VBtn variant="tonal" color="secondary" prepend-icon="tabler-arrow-right" :to="{ name: 'marketplace' }" style="font-family:Cairo,sans-serif">
        العودة للقائمة
      </VBtn>
    </div>

    <VCard :loading="loading">
      <VCardText>
        <VRow>
          <!-- Section: المعلومات الأساسية -->
          <VCol cols="12">
            <p class="text-subtitle-1 font-weight-bold mb-3" style="font-family:Cairo,sans-serif;color:#000269">
              <VIcon icon="tabler-info-circle" size="18" class="me-1" />
              المعلومات الأساسية
            </p>
          </VCol>

          <VCol cols="12" md="6">
            <VSelect
              v-model="form.contractor_id"
              :items="contractors"
              item-title="name"
              item-value="id"
              label="المالك (المقاول) *"
              variant="outlined"
              density="compact"
              style="font-family:Cairo,sans-serif"
            />
          </VCol>
          <VCol cols="12" md="6">
            <VSelect
              v-model="form.equipment_type_id"
              :items="typeOptions"
              item-title="name_ar"
              item-value="id"
              label="نوع المعدة *"
              variant="outlined"
              density="compact"
              style="font-family:Cairo,sans-serif"
            />
          </VCol>
          <VCol cols="12" md="6">
            <VTextField
              v-model="form.name"
              label="اسم الآلية *"
              placeholder="مثال: حفارة كوماتسو PC200"
              variant="outlined"
              density="compact"
              style="font-family:Cairo,sans-serif"
            />
          </VCol>
          <VCol cols="12" md="6">
            <VTextField
              v-model="form.brand"
              label="الماركة"
              placeholder="مثال: كاتربيلر"
              variant="outlined"
              density="compact"
              style="font-family:Cairo,sans-serif"
            />
          </VCol>
          <VCol cols="12" md="6">
            <WhatsappPhoneField v-model="form.owner_phone" />
          </VCol>
          <VCol cols="12">
            <VTextarea
              v-model="form.description"
              label="وصف الحالة الفنية للآلية"
              variant="outlined"
              density="compact"
              rows="3"
              maxlength="250"
              counter
              hint="حد أقصى 250 حرف"
              persistent-hint
              style="font-family:Cairo,sans-serif"
            />
          </VCol>

          <VDivider class="my-2" />

          <!-- Section: المواصفات التقنية -->
          <VCol cols="12">
            <p class="text-subtitle-1 font-weight-bold mb-3" style="font-family:Cairo,sans-serif;color:#000269">
              <VIcon icon="tabler-settings-2" size="18" class="me-1" />
              المواصفات التقنية
            </p>
          </VCol>

          <VCol cols="12" md="3">
            <VTextField
              v-model.number="form.manufacture_year"
              label="سنة الصنع"
              type="number"
              :min="1970"
              :max="new Date().getFullYear()"
              variant="outlined"
              density="compact"
              style="font-family:Cairo,sans-serif"
            />
          </VCol>
          <VCol cols="12" md="3">
            <VTextField
              v-model="form.power"
              label="القدرة / الطاقة"
              placeholder="مثال: 250 HP"
              variant="outlined"
              density="compact"
              style="font-family:Cairo,sans-serif"
            />
          </VCol>
          <VCol cols="12" md="3">
            <VSelect
              v-model="form.condition"
              :items="conditionOptions"
              label="حالة الآلية"
              variant="outlined"
              density="compact"
              style="font-family:Cairo,sans-serif"
            />
          </VCol>
          <VCol cols="12" md="3">
            <VSelect
              v-model="form.contract_type"
              :items="contractTypeOptions"
              label="نوع العقد *"
              variant="outlined"
              density="compact"
              style="font-family:Cairo,sans-serif"
            />
          </VCol>

          <VDivider class="my-2" />

          <!-- Section: الموقع — لا يوجد حقل سعر للآلية أصلاً؛ نوع العقد (يومي/أسبوعي/شهري) أعلاه
               هو فقط مدة التأجير، والتسعير الفعلي يتم خارج المنصة بين المالك والمستأجر مباشرة -->
          <VCol cols="12">
            <p class="text-subtitle-1 font-weight-bold mb-3" style="font-family:Cairo,sans-serif;color:#000269">
              <VIcon icon="tabler-map-pin" size="18" class="me-1" />
              الموقع
            </p>
          </VCol>

          <VCol cols="12" md="4">
            <VSelect
              v-model="form.governorate"
              :items="governorates"
              label="المحافظة"
              variant="outlined"
              density="compact"
              style="font-family:Cairo,sans-serif"
            />
          </VCol>
          <VCol cols="12" md="4">
            <VTextField
              v-model="form.city"
              label="المدينة / المنطقة"
              variant="outlined"
              density="compact"
              style="font-family:Cairo,sans-serif"
            />
          </VCol>
          <VDivider class="my-2" />

          <!-- Section: إعدادات الظهور -->
          <VCol cols="12">
            <p class="text-subtitle-1 font-weight-bold mb-3" style="font-family:Cairo,sans-serif;color:#000269">
              <VIcon icon="tabler-eye" size="18" class="me-1" />
              إعدادات الظهور والإدارة
            </p>
          </VCol>

          <VCol cols="12" md="6">
            <VSelect
              v-model="form.status"
              :items="statusOptions"
              label="حالة الظهور"
              variant="outlined"
              density="compact"
              style="font-family:Cairo,sans-serif"
            />
          </VCol>
          <VCol cols="12" md="6" class="d-flex align-center">
            <VSwitch
              v-model="form.is_featured"
              label="آلية مميزة (تظهر أولاً في السوق)"
              color="warning"
              :disabled="isSuspended"
              :hint="isSuspended ? 'غير متاح والآلية مخفية بقرار المالك أو موقوفة بقرار الإدارة' : undefined"
              :persistent-hint="isSuspended"
              style="font-family:Cairo,sans-serif"
            />
          </VCol>
          <VCol cols="12" md="6" class="d-flex align-center">
            <VSwitch
              v-model="form.needs_maintenance"
              label="بحاجة صيانة (تختفي من السوق مؤقتاً)"
              color="error"
              style="font-family:Cairo,sans-serif"
            />
          </VCol>
          <!-- تاريخ النشر: مباشر / مجدول -->
          <VCol cols="12" md="6">
            <p class="text-body-2 mb-2" style="font-family:Cairo,sans-serif">تاريخ نشر الآلية</p>
            <VBtnToggle
              v-model="form.publishMode"
              mandatory
              color="primary"
              variant="outlined"
              divided
              density="comfortable"
              style="font-family:Cairo,sans-serif"
            >
              <VBtn v-for="opt in publishModeOptions" :key="opt.value" :value="opt.value">
                <VIcon :icon="opt.icon" size="18" class="me-1" />
                {{ opt.label }}
              </VBtn>
            </VBtnToggle>
          </VCol>
          <VCol v-if="form.publishMode === 'schedule'" cols="12" md="6">
            <VTextField
              v-model="form.published_at"
              label="تاريخ النشر المجدول *"
              type="date"
              :min="isEdit && originalPublishedAt ? undefined : tomorrowStr()"
              :hint="`${minDateHint} — ${publishModeHint}`"
              persistent-hint
              variant="outlined"
              density="compact"
              style="font-family:Cairo,sans-serif"
            />
          </VCol>
          <VCol v-else cols="12" md="6" class="d-flex align-center">
            <p class="text-body-2 text-medium-emphasis mb-0" style="font-family:Cairo,sans-serif">{{ publishModeHint }}</p>
          </VCol>

          <VCol cols="12">
            <VTextarea
              v-model="form.admin_notes"
              label="ملاحظات داخلية (مرئية للمشرفين فقط)"
              variant="outlined"
              density="compact"
              rows="2"
              style="font-family:Cairo,sans-serif"
            />
          </VCol>

          <!-- Section: الصور (create only) -->
          <template v-if="!isEdit">
            <VDivider class="my-2" />
            <VCol cols="12">
              <p class="text-subtitle-1 font-weight-bold mb-3" style="font-family:Cairo,sans-serif;color:#000269">
                <VIcon icon="tabler-photo" size="18" class="me-1" />
                صور الآلية (حتى {{ MAX_IMAGES }} صور)
              </p>
              <VFileInput
                v-model="imagePicker"
                :label="newImages.length ? `إضافة صور (${newImages.length} من ${MAX_IMAGES})` : 'اختر الصور'"
                accept="image/jpeg,image/png,image/webp"
                multiple
                prepend-icon="tabler-upload"
                variant="outlined"
                density="compact"
                :disabled="newImages.length >= MAX_IMAGES"
                :error-messages="imageErrors"
                style="font-family:Cairo,sans-serif"
                @update:model-value="onFilesSelected"
              />
              <p class="text-caption text-medium-emphasis mt-1" style="font-family:Cairo,sans-serif">
                JPG، PNG، WebP — الحد الأقصى {{ MAX_IMAGES }} صور، وحتى {{ MAX_IMAGE_MB }} ميغابايت للصورة الواحدة. يمكنك إضافة المزيد من الصور لاحقاً.
              </p>

              <!-- معاينة الصور المختارة قبل الحفظ: حذف كل صورة لحالها + اختيار صورة الغلاف -->
              <div v-if="newImages.length" class="equip-previews mt-3">
                <div
                  v-for="(url, i) in imagePreviews"
                  :key="url"
                  class="equip-preview"
                  :class="{ 'equip-preview--cover': i === coverIndex }"
                >
                  <img :src="url" :alt="newImages[i]?.name">
                  <span v-if="i === coverIndex" class="equip-preview__badge">صورة الغلاف</span>
                  <div class="equip-preview__actions">
                    <VBtn
                      v-if="i !== coverIndex"
                      size="x-small"
                      variant="flat"
                      color="primary"
                      prepend-icon="tabler-star"
                      style="font-family:Cairo,sans-serif"
                      @click="coverIndex = i"
                    >
                      تعيين كغلاف
                    </VBtn>
                    <VBtn icon size="x-small" variant="flat" color="error" @click="removeImage(i)">
                      <VIcon icon="tabler-x" size="14" />
                      <VTooltip activator="parent">حذف الصورة</VTooltip>
                    </VBtn>
                  </div>
                </div>
              </div>
            </VCol>
          </template>

          <!-- Actions -->
          <VCol cols="12" class="d-flex justify-end gap-3 mt-2">
            <VBtn variant="tonal" color="secondary" :to="{ name: 'marketplace' }" style="font-family:Cairo,sans-serif">إلغاء</VBtn>
            <VBtn color="primary" :loading="saving" prepend-icon="tabler-device-floppy" @click="submit" style="font-family:Cairo,sans-serif">
              {{ isEdit ? 'حفظ التعديلات' : 'إضافة الآلية' }}
            </VBtn>
          </VCol>
        </VRow>
      </VCardText>
    </VCard>

    <!-- نافذة نجاح الإضافة — منها زر "معاينة" لصفحة معاينة الآلية (REQ-08 #19) -->
    <VDialog v-model="successDialog" max-width="440" persistent>
      <VCard>
        <VCardText class="pa-6 text-center">
          <VIcon icon="tabler-circle-check" size="52" color="success" class="mb-3" />
          <p class="text-h6 mb-1" style="font-family:Cairo,sans-serif">تمت إضافة الآلية</p>
          <p class="text-body-2 text-medium-emphasis mb-0" style="font-family:Cairo,sans-serif">
            {{ createdItem?.name }}
            <template v-if="createdItem?.published_at && new Date(createdItem.published_at) > new Date()">
              — مجدولة للنشر بتاريخ {{ new Date(createdItem.published_at).toLocaleDateString('ar-PS') }}
            </template>
          </p>
        </VCardText>
        <VCardActions class="justify-center flex-wrap gap-2 pb-5">
          <VBtn color="primary" variant="flat" prepend-icon="tabler-eye" style="font-family:Cairo,sans-serif" @click="goToPreview">
            معاينة
          </VBtn>
          <VBtn variant="tonal" color="primary" prepend-icon="tabler-plus" style="font-family:Cairo,sans-serif" @click="resetForm">
            إضافة آلية أخرى
          </VBtn>
          <VBtn variant="text" color="secondary" :to="{ name: 'marketplace' }" style="font-family:Cairo,sans-serif">
            العودة للقائمة
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>

<style scoped>
.equip-previews {
  display: grid;
  gap: 12px;
  grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
}

.equip-preview {
  position: relative;
  overflow: hidden;
  border: 2px solid #e5e7eb;
  border-radius: 10px;
  aspect-ratio: 4 / 3;
}

.equip-preview--cover {
  border-color: #000269;
}

.equip-preview img {
  display: block;
  block-size: 100%;
  inline-size: 100%;
  object-fit: cover;
}

.equip-preview__badge {
  position: absolute;
  inset-block-start: 6px;
  inset-inline-start: 6px;
  padding: 2px 8px;
  border-radius: 6px;
  background: #000269;
  color: #fff;
  font-family: Cairo, sans-serif;
  font-size: 11px;
}

.equip-preview__actions {
  position: absolute;
  display: flex;
  justify-content: center;
  padding: 6px;
  background: rgba(0, 0, 0, 55%);
  gap: 6px;
  inset-block-end: 0;
  inset-inline: 0;
}
</style>

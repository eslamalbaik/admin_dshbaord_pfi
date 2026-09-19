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
})

const newImages   = ref<File[]>([])
const imageErrors = ref('')

// ─── Load reference data + edit data ─────────────────────────────────────────
const fetchRef = async () => {
  const [typesRes, contractorsRes] = await Promise.all([
    api.get('/api/v1/equipment-types', { params: { active_only: 1 } }),
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
    }
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
const onFilesSelected = (e: Event) => {
  const input = e.target as HTMLInputElement
  if (!input.files) return
  imageErrors.value = ''
  const files = Array.from(input.files)
  for (const f of files) {
    if (f.size > 3 * 1024 * 1024) {
      imageErrors.value = 'حجم الصورة يجب ألا يتجاوز 3 ميغابايت'
      return
    }
  }
  if (files.length > 8)
    imageErrors.value = `الحد الأقصى 8 صور — تم اختيار أول 8 من أصل ${files.length}.`
  newImages.value = files.slice(0, 8)
}

// ─── Submit ───────────────────────────────────────────────────────────────────
const submit = async () => {
  if (!form.value.name.trim() || !form.value.contractor_id || !form.value.equipment_type_id) {
    alert('يرجى ملء الحقول المطلوبة: المالك، النوع، الاسم')
    return
  }

  saving.value = true
  try {
    const payload = new FormData()

    Object.entries(form.value).forEach(([key, val]) => {
      if (val !== null && val !== undefined && val !== '') {
        if (typeof val === 'boolean')
          payload.append(key, val ? '1' : '0')
        else
          payload.append(key, String(val))
      }
    })

    newImages.value.forEach(file => payload.append('images[]', file))

    if (isEdit.value) {
      payload.append('_method', 'PATCH')
      await api.post(`/api/v1/equipment/${editId.value}`, payload, {
        headers: { 'Content-Type': 'multipart/form-data' },
      })
    }
    else {
      await api.post('/api/v1/equipment', payload, {
        headers: { 'Content-Type': 'multipart/form-data' },
      })
    }

    router.push({ name: 'marketplace' })
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
    <div class="d-flex align-center justify-space-between mb-6">
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
              :items="types"
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
            <VTextField
              v-model="form.owner_phone"
              label="رقم هاتف المالك"
              placeholder="0599-XXXXXX"
              variant="outlined"
              density="compact"
              style="font-family:Cairo,sans-serif"
            />
          </VCol>
          <VCol cols="12">
            <VTextarea
              v-model="form.description"
              label="وصف الآلية"
              variant="outlined"
              density="compact"
              rows="3"
              maxlength="2000"
              counter
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

          <!-- Section: الموقع والسعر -->
          <VCol cols="12">
            <p class="text-subtitle-1 font-weight-bold mb-3" style="font-family:Cairo,sans-serif;color:#000269">
              <VIcon icon="tabler-map-pin" size="18" class="me-1" />
              الموقع والسعر
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
                صور الآلية (حتى 8 صور)
              </p>
              <VFileInput
                label="اختر الصور"
                accept="image/jpeg,image/png,image/webp"
                multiple
                prepend-icon="tabler-upload"
                variant="outlined"
                density="compact"
                :error-messages="imageErrors"
                style="font-family:Cairo,sans-serif"
                @change="onFilesSelected"
              />
              <p class="text-caption text-medium-emphasis mt-1" style="font-family:Cairo,sans-serif">
                JPG، PNG، WebP — الحد الأقصى 3 ميغابايت للصورة الواحدة. يمكنك إضافة المزيد من الصور لاحقاً.
              </p>
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
  </div>
</template>

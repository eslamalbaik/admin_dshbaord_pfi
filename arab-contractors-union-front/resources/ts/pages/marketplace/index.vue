<script setup lang="ts">
import api from '@/plugins/axios'

definePage({ meta: { requiresAdmin: true,
    adminOnly: true } })

// ─── Snackbar ──────────────────────────────────────
const snackbar = ref(false)
const snackbarText = ref('')
const snackbarColor = ref<'success' | 'error' | 'warning'>('success')
const notify = (text: string, color: 'success' | 'error' | 'warning' = 'success') => {
  snackbarText.value = text
  snackbarColor.value = color
  snackbar.value = true
}

// ─── State ──────────────────────────────────────────────────────────────────
const equipment   = ref<any[]>([])
const types       = ref<any[]>([])
const total       = ref(0)
const loading     = ref(false)
const stats       = ref({ total: 0, visible: 0, hidden: 0, suspended: 0 })

const page     = ref(1)
const perPage  = ref(15)
const search   = ref('')
const filterType   = ref('')
const filterStatus = ref('')
const filterGov    = ref('')
const filterContractType = ref('')

// ─── Dialogs ────────────────────────────────────────────────────────────────
const editDialog    = ref(false)
const deleteDialog  = ref(false)
const imagesDialog  = ref(false)
const calendarDialog = ref(false)
const selectedItem  = ref<any>(null)
const saving        = ref(false)
const deleting      = ref(false)

const editForm = ref({
  equipment_type_id: null as number | null,
  name: '', brand: '', description: '', manufacture_year: null as number | null,
  power: '', condition: 'good', contract_type: 'daily', governorate: '', city: '',
  owner_phone: '', status: 'visible', is_featured: false, needs_maintenance: false, admin_notes: '',
})

// image management
const imageUploading = ref(false)
const imageFiles     = ref<File[]>([])
const equipImages    = ref<any[]>([])

// calendar (blocked dates)
const blockedDates   = ref<any[]>([])
const calLoading     = ref(false)
const newDates       = ref<string[]>([])
const dateReason     = ref('booked')

// حجوزات فعلية طلبها مقاولون من التطبيق — للعرض فقط، مختلفة تماماً عن blockedDates
// (حجب يدوي يضيفه الأدمن). الفصل بينهم هو جواب REQ-08 #6
const reservations   = ref<any[]>([])

// ─── Options ────────────────────────────────────────────────────────────────
const statusOptions = [
  { title: 'الكل', value: '' },
  { title: 'ظاهر', value: 'visible' },
  { title: 'مخفي', value: 'hidden' },
  { title: 'موقوف', value: 'suspended' },
]

const conditionOptions = [
  { title: 'ممتازة', value: 'excellent' },
  { title: 'جيدة', value: 'good' },
  { title: 'بحاجة صيانة', value: 'needs_maintenance' },
]

const statusMeta: Record<string, { color: string; label: string }> = {
  visible:   { color: 'success', label: 'ظاهر' },
  hidden:    { color: 'warning', label: 'مخفي' },
  suspended: { color: 'error',   label: 'موقوف' },
}

const conditionMeta: Record<string, { color: string; label: string }> = {
  excellent:         { color: 'success', label: 'ممتازة' },
  good:              { color: 'primary', label: 'جيدة' },
  needs_maintenance: { color: 'warning', label: 'بحاجة صيانة' },
}

const contractTypeOptions = [
  { title: 'الكل', value: '' },
  { title: 'يومي', value: 'daily' },
  { title: 'أسبوعي', value: 'weekly' },
  { title: 'شهري', value: 'monthly' },
]

const contractTypeLabel: Record<string, string> = {
  daily: 'يومي', weekly: 'أسبوعي', monthly: 'شهري',
}

const governorates = [
  'غزة', 'شمال غزة', 'خانيونس', 'رفح', 'الوسطى',
  'رام الله والبيرة', 'نابلس', 'جنين', 'طولكرم',
  'بيت لحم', 'الخليل', 'أريحا',
]

// ─── Fetch ───────────────────────────────────────────────────────────────────
const fetchEquipment = async () => {
  loading.value = true
  try {
    const params: Record<string, any> = { page: page.value, per_page: perPage.value }
    if (search.value)       params.search      = search.value
    if (filterType.value)   params.type_id     = filterType.value
    if (filterStatus.value) params.status      = filterStatus.value
    if (filterGov.value)    params.governorate = filterGov.value
    if (filterContractType.value) params.contract_type = filterContractType.value

    const { data } = await api.get('/api/v1/equipment', { params })
    equipment.value = data.data
    total.value     = data.total
  }
  finally {
    loading.value = false
  }
}

const fetchStats = async () => {
  const { data } = await api.get('/api/v1/equipment/stats')
  stats.value = data
}

const fetchTypes = async () => {
  // فلتر القائمة بالأعلى يحتاج كل الأنواع (بما فيها المخفية) عشان يقدر يفلتر آليات قديمة مربوطة بنوع مخفي
  const { data } = await api.get('/api/v1/equipment-types')
  types.value = data
}

// خيارات منتقي التعديل: الأنواع الظاهرة فقط (نوع مخفي ما يظهرش كخيار قابل للاختيار من جديد) —
// مع استثناء: لو الآلية الحالية مربوطة بنوع مخفي بالفعل، يبقى ظاهراً بالقائمة عشان ما يختفيش من الفورم
const editTypeOptions = computed(() => {
  const active = types.value.filter((t: any) => t.is_active)
  const currentId = editForm.value.equipment_type_id
  if (currentId && !active.some((t: any) => t.id === currentId)) {
    const current = types.value.find((t: any) => t.id === currentId)
    if (current) return [...active, current]
  }

  return active
})

onMounted(() => {
  fetchTypes()
  fetchStats()
  fetchEquipment()
})

// reset page on filter change
watch([search, filterType, filterStatus, filterGov, filterContractType], () => {
  page.value = 1
  fetchEquipment()
})

// زر إلغاء الفلاتر المحددة بالبحث (REQ-08 #9)
const resetFilters = () => {
  search.value = ''
  filterType.value = ''
  filterStatus.value = ''
  filterGov.value = ''
  filterContractType.value = ''
}

watch(page, fetchEquipment)

// ─── Edit ────────────────────────────────────────────────────────────────────
const openEdit = (item: any) => {
  selectedItem.value = item
  editForm.value = {
    equipment_type_id: item.equipment_type_id ?? item.type?.id ?? null,
    name:             item.name,
    brand:            item.brand ?? '',
    description:      item.description ?? '',
    manufacture_year: item.manufacture_year ?? null,
    power:            item.power ?? '',
    condition:        item.condition ?? 'good',
    contract_type:    item.contract_type ?? 'daily',
    governorate:      item.governorate ?? '',
    city:             item.city ?? '',
    owner_phone:      item.owner_phone ?? '',
    status:           item.status ?? 'visible',
    is_featured:      !!item.is_featured,
    needs_maintenance: !!item.needs_maintenance,
    admin_notes:      item.admin_notes ?? '',
  }
  editDialog.value = true
}

const saveEdit = async () => {
  saving.value = true
  try {
    const { data } = await api.patch(`/api/v1/equipment/${selectedItem.value.id}`, editForm.value)
    const idx = equipment.value.findIndex(e => e.id === data.id)
    if (idx !== -1) equipment.value[idx] = { ...equipment.value[idx], ...data }
    editDialog.value = false
    fetchStats()
  }
  finally {
    saving.value = false
  }
}

// ─── Quick status toggle ──────────────────────────────────────────────────────
const cycleStatus = async (item: any) => {
  const next: Record<string, string> = { visible: 'hidden', hidden: 'suspended', suspended: 'visible' }
  const newStatus = next[item.status] ?? 'visible'
  try {
    const { data } = await api.patch(`/api/v1/equipment/${item.id}`, { status: newStatus })
    const idx = equipment.value.findIndex(e => e.id === item.id)
    if (idx !== -1) equipment.value[idx] = { ...equipment.value[idx], ...data }
    fetchStats()
  }
  catch {/* silent */}
}

// ─── Delete ──────────────────────────────────────────────────────────────────
const openDelete = (item: any) => {
  selectedItem.value = item
  deleteDialog.value = true
}

const confirmDelete = async () => {
  deleting.value = true
  try {
    await api.delete(`/api/v1/equipment/${selectedItem.value.id}`)
    equipment.value = equipment.value.filter(e => e.id !== selectedItem.value.id)
    total.value--
    deleteDialog.value = false
    fetchStats()
  }
  finally {
    deleting.value = false
  }
}

// ─── Images ──────────────────────────────────────────────────────────────────
const openImages = async (item: any) => {
  selectedItem.value = item
  const { data } = await api.get(`/api/v1/equipment/${item.id}`)
  equipImages.value  = data.images ?? []
  imagesDialog.value = true
}

const onImageFiles = (e: Event) => {
  const input = e.target as HTMLInputElement
  const files = input.files ? Array.from(input.files) : []
  const remaining = 8 - equipImages.value.length
  if (files.length > remaining) {
    notify(`الحد الأقصى 8 صور لكل آلية — لديها ${equipImages.value.length} حالياً، تم اختيار أول ${Math.max(remaining, 0)} فقط من ${files.length} صورة.`, 'warning')
  }
  imageFiles.value = files.slice(0, Math.max(remaining, 0))
}

const uploadImages = async () => {
  if (!imageFiles.value.length) return
  imageUploading.value = true
  try {
    const fd = new FormData()
    imageFiles.value.forEach(f => fd.append('images[]', f))
    const { data } = await api.post(`/api/v1/equipment/${selectedItem.value.id}/images`, fd, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    equipImages.value.push(...data)
    imageFiles.value = []
  } catch (err: any) {
    notify(err?.response?.data?.message || 'تعذّر رفع الصور', 'error')
  }
  finally {
    imageUploading.value = false
  }
}

// حماية ضد النقر المتكرر (REQ-08 #2 — كانت الصورة تحتاج أكثر من نقرة على أيقونة الحذف):
// بدون هالحماية كل نقرة قبل رجوع أول طلب كانت تطلق DELETE جديد بلا أي إشارة تحميل
const deletingImageId = ref<number | null>(null)

const deleteImage = async (img: any) => {
  if (deletingImageId.value !== null) return
  deletingImageId.value = img.id
  try {
    await api.delete(`/api/v1/equipment/${selectedItem.value.id}/images/${img.id}`)
    equipImages.value = equipImages.value.filter(i => i.id !== img.id)
  } catch (err: any) {
    notify(err?.response?.data?.message || 'تعذّر حذف الصورة', 'error')
  } finally {
    deletingImageId.value = null
  }
}

const setPrimary = async (img: any) => {
  await api.post(`/api/v1/equipment/${selectedItem.value.id}/images/${img.id}/primary`)
  equipImages.value.forEach(i => (i.is_primary = i.id === img.id))
}

// ─── Blocked Dates ────────────────────────────────────────────────────────────
const openCalendar = async (item: any) => {
  selectedItem.value  = item
  calLoading.value    = true
  calendarDialog.value = true
  newDates.value      = []
  try {
    const [blockedRes, reservationsRes] = await Promise.all([
      api.get(`/api/v1/equipment/${item.id}/blocked-dates`),
      api.get(`/api/v1/equipment/${item.id}/reservations`),
    ])
    blockedDates.value = blockedRes.data
    reservations.value = reservationsRes.data
  }
  finally {
    calLoading.value = false
  }
}

const addBlockedDates = async () => {
  if (!newDates.value.length) return
  const { data } = await api.post(`/api/v1/equipment/${selectedItem.value.id}/blocked-dates`, {
    dates: newDates.value,
    reason: dateReason.value,
  })
  blockedDates.value.push(...data)
  newDates.value = []
}

const removeDate = async (bd: any) => {
  await api.delete(`/api/v1/equipment/${selectedItem.value.id}/blocked-dates/${bd.id}`)
  blockedDates.value = blockedDates.value.filter(d => d.id !== bd.id)
}

const reasonLabel: Record<string, string> = {
  booked: 'محجوز', maintenance: 'صيانة', other: 'أخرى',
}

const reservationStatus: Record<string, { color: string; label: string }> = {
  confirmed: { color: 'success', label: 'مؤكَّد' },
  cancelled: { color: 'secondary', label: 'ملغى' },
}
</script>

<template>
  <div>
    <!-- Header -->
    <div class="d-flex align-center justify-space-between mb-6 flex-wrap gap-3">
      <div>
        <h1 class="text-h4 font-weight-bold" style="font-family:Cairo,sans-serif">سوق الآليات</h1>
        <p class="text-body-2 text-medium-emphasis mb-0" style="font-family:Cairo,sans-serif">
          إدارة جميع الآليات المسجّلة في المنصة
        </p>
      </div>
      <div class="d-flex gap-2">
        <VBtn variant="tonal" color="secondary" :to="{ name: 'marketplace-types' }" prepend-icon="tabler-list" style="font-family:Cairo,sans-serif">
          أنواع المعدات
        </VBtn>
        <VBtn color="primary" :to="{ name: 'marketplace-create' }" prepend-icon="tabler-plus" style="font-family:Cairo,sans-serif">
          إضافة آلية
        </VBtn>
      </div>
    </div>

    <!-- Stats Cards -->
    <VRow class="mb-4">
      <VCol v-for="card in [
        { label: 'إجمالي الآليات', value: stats.total,     icon: 'tabler-tractor',    color: 'primary' },
        { label: 'ظاهر في السوق',  value: stats.visible,   icon: 'tabler-eye',        color: 'success' },
        { label: 'مخفي',           value: stats.hidden,    icon: 'tabler-eye-off',    color: 'warning' },
        { label: 'موقوف',          value: stats.suspended, icon: 'tabler-ban',        color: 'error'   },
      ]" :key="card.label" cols="6" md="3">
        <VCard>
          <VCardText class="d-flex align-center justify-space-between pa-4">
            <div>
              <p class="text-body-2 text-medium-emphasis mb-1" style="font-family:Cairo,sans-serif">{{ card.label }}</p>
              <p class="text-h4 font-weight-bold mb-0" :style="`color:${card.color === 'primary' ? '#000269' : ''}`">{{ card.value }}</p>
            </div>
            <VAvatar :color="card.color" variant="tonal" size="44">
              <VIcon :icon="card.icon" size="24" />
            </VAvatar>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <!-- Filters -->
    <VCard class="mb-4">
      <VCardText>
        <VRow dense>
          <VCol cols="12" md="4">
            <VTextField
              v-model="search"
              label="بحث بالاسم أو المالك"
              prepend-inner-icon="tabler-search"
              variant="outlined"
              density="compact"
              clearable
              style="font-family:Cairo,sans-serif"
            />
          </VCol>
          <VCol cols="12" md="3">
            <VSelect
              v-model="filterType"
              :items="[{ id: '', name_ar: 'كل الأنواع' }, ...types]"
              item-title="name_ar"
              item-value="id"
              label="نوع المعدة"
              variant="outlined"
              density="compact"
              style="font-family:Cairo,sans-serif"
            />
          </VCol>
          <VCol cols="12" md="3">
            <VSelect
              v-model="filterStatus"
              :items="statusOptions"
              label="الحالة"
              variant="outlined"
              density="compact"
              style="font-family:Cairo,sans-serif"
            />
          </VCol>
          <VCol cols="12" md="2">
            <VSelect
              v-model="filterGov"
              :items="['', ...governorates]"
              label="المحافظة"
              variant="outlined"
              density="compact"
              style="font-family:Cairo,sans-serif"
              :placeholder="'الكل'"
            />
          </VCol>
          <VCol cols="12" md="2">
            <VSelect
              v-model="filterContractType"
              :items="contractTypeOptions"
              label="نوع العقد"
              variant="outlined"
              density="compact"
              style="font-family:Cairo,sans-serif"
            />
          </VCol>
          <VCol cols="12" class="d-flex justify-end">
            <VBtn
              variant="text"
              size="small"
              prepend-icon="tabler-filter-x"
              :disabled="!search && !filterType && !filterStatus && !filterGov && !filterContractType"
              style="font-family:Cairo,sans-serif"
              @click="resetFilters"
            >
              إلغاء الفلاتر
            </VBtn>
          </VCol>
        </VRow>
      </VCardText>
    </VCard>

    <!-- Table -->
    <VCard :loading="loading">
      <VTable>
        <thead>
          <tr>
            <th style="font-family:Cairo,sans-serif">الآلية</th>
            <th style="font-family:Cairo,sans-serif">النوع</th>
            <th style="font-family:Cairo,sans-serif">المالك</th>
            <th style="font-family:Cairo,sans-serif">المحافظة</th>
            <th style="font-family:Cairo,sans-serif">الحالة</th>
            <th style="font-family:Cairo,sans-serif">الإجراءات</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="!loading && equipment.length === 0">
            <td colspan="6" class="text-center pa-10 text-medium-emphasis" style="font-family:Cairo,sans-serif">
              لا توجد آليات مطابقة للبحث
            </td>
          </tr>
          <tr v-for="item in equipment" :key="item.id">
            <!-- Name + image -->
            <td>
              <div class="d-flex align-center gap-3">
                <VAvatar
                  :image="item.primary_image?.url"
                  :color="item.primary_image ? undefined : 'primary'"
                  variant="tonal"
                  size="40"
                  rounded="lg"
                >
                  <VIcon v-if="!item.primary_image" icon="tabler-tractor" size="20" />
                </VAvatar>
                <div>
                  <div class="font-weight-semibold d-flex align-center gap-1" style="font-family:Cairo,sans-serif">
                    {{ item.name }}
                    <VIcon v-if="item.is_featured" icon="tabler-star-filled" size="14" color="warning">
                      <VTooltip activator="parent">آلية مميزة</VTooltip>
                    </VIcon>
                    <VIcon v-if="item.needs_maintenance" icon="tabler-tool" size="14" color="error">
                      <VTooltip activator="parent">بحاجة صيانة</VTooltip>
                    </VIcon>
                  </div>
                  <div class="text-caption text-medium-emphasis" style="font-family:Cairo,sans-serif">
                    {{ item.manufacture_year ? `سنة ${item.manufacture_year}` : '' }}
                    {{ item.power ? `· ${item.power}` : '' }}
                    {{ item.contract_type ? `· ${contractTypeLabel[item.contract_type] ?? item.contract_type}` : '' }}
                  </div>
                </div>
              </div>
            </td>
            <!-- Type -->
            <td>
              <VChip size="x-small" color="primary" variant="tonal" style="font-family:Cairo,sans-serif">
                {{ item.type?.name_ar ?? '—' }}
              </VChip>
            </td>
            <!-- Contractor -->
            <td style="font-family:Cairo,sans-serif;color:#374151">{{ item.contractor?.name ?? '—' }}</td>
            <!-- Governorate -->
            <td style="font-family:Cairo,sans-serif;color:#6B7280">{{ item.governorate ?? '—' }}</td>
            <!-- Status -->
            <td>
              <VChip
                :color="statusMeta[item.status]?.color ?? 'default'"
                size="small"
                label
                variant="tonal"
                class="cursor-pointer"
                style="font-family:Cairo,sans-serif"
                @click="cycleStatus(item)"
              >
                {{ statusMeta[item.status]?.label ?? item.status }}
              </VChip>
            </td>
            <!-- Actions -->
            <td>
              <div class="d-flex gap-1 flex-wrap">
                <VBtn icon size="x-small" variant="tonal" color="primary" @click="openEdit(item)">
                  <VIcon icon="tabler-edit" size="16" />
                  <VTooltip activator="parent">تعديل</VTooltip>
                </VBtn>
                <VBtn icon size="x-small" variant="tonal" color="info" @click="openImages(item)">
                  <VIcon icon="tabler-photo" size="16" />
                  <VTooltip activator="parent">الصور</VTooltip>
                </VBtn>
                <VBtn icon size="x-small" variant="tonal" color="secondary" @click="openCalendar(item)">
                  <VIcon icon="tabler-calendar" size="16" />
                  <VTooltip activator="parent">تواريخ عدم التوفر (حجز/صيانة)</VTooltip>
                </VBtn>
                <VBtn
                  icon
                  size="x-small"
                  variant="tonal"
                  color="error"
                  :disabled="item.needs_maintenance"
                  @click="openDelete(item)"
                >
                  <VIcon icon="tabler-trash" size="16" />
                  <VTooltip activator="parent">
                    {{ item.needs_maintenance ? 'أزل حالة "بحاجة صيانة" أولاً قبل الحذف' : 'حذف' }}
                  </VTooltip>
                </VBtn>
              </div>
            </td>
          </tr>
        </tbody>
      </VTable>

      <!-- Pagination -->
      <VCardText v-if="total > perPage" class="d-flex justify-center pt-0">
        <VPagination
          v-model="page"
          :length="Math.ceil(total / perPage)"
          :total-visible="7"
        />
      </VCardText>
    </VCard>

    <!-- ═══════════════════════════════════════════════════════════════════
         Edit Dialog
    ════════════════════════════════════════════════════════════════════ -->
    <VDialog v-model="editDialog" max-width="720" scrollable>
      <VCard>
        <VCardTitle style="font-family:Cairo,sans-serif;font-size:18px;padding:20px 24px 0">
          تعديل: {{ selectedItem?.name }}
        </VCardTitle>
        <VCardText>
          <VRow class="mt-1">
            <VCol cols="12" md="6">
              <VTextField v-model="editForm.name" label="اسم الآلية" variant="outlined" density="compact" style="font-family:Cairo,sans-serif" />
            </VCol>
            <VCol cols="12" md="6">
              <VSelect
                v-model="editForm.equipment_type_id"
                :items="editTypeOptions"
                item-title="name_ar"
                item-value="id"
                label="نوع الآلية"
                variant="outlined"
                density="compact"
                style="font-family:Cairo,sans-serif"
              />
            </VCol>
            <VCol cols="12" md="6">
              <VTextField v-model="editForm.brand" label="الماركة" variant="outlined" density="compact" style="font-family:Cairo,sans-serif" />
            </VCol>
            <VCol cols="12" md="6">
              <VTextField v-model="editForm.owner_phone" label="هاتف المالك" variant="outlined" density="compact" style="font-family:Cairo,sans-serif" />
            </VCol>
            <VCol cols="12" md="3">
              <VTextField v-model.number="editForm.manufacture_year" label="سنة الصنع" type="number" variant="outlined" density="compact" style="font-family:Cairo,sans-serif" />
            </VCol>
            <VCol cols="12" md="3">
              <VTextField v-model="editForm.power" label="القدرة" variant="outlined" density="compact" style="font-family:Cairo,sans-serif" />
            </VCol>
            <VCol cols="12" md="3">
              <VSelect v-model="editForm.condition" :items="conditionOptions" label="الحالة" variant="outlined" density="compact" style="font-family:Cairo,sans-serif" />
            </VCol>
            <VCol cols="12" md="3">
              <VSelect
                v-model="editForm.contract_type"
                :items="contractTypeOptions.filter(o => o.value)"
                label="نوع العقد"
                variant="outlined"
                density="compact"
                style="font-family:Cairo,sans-serif"
              />
            </VCol>
            <VCol cols="12" md="4">
              <VSelect
                v-model="editForm.governorate"
                :items="governorates"
                label="المحافظة"
                variant="outlined"
                density="compact"
                style="font-family:Cairo,sans-serif"
              />
            </VCol>
            <VCol cols="12" md="4">
              <VTextField v-model="editForm.city" label="المدينة" variant="outlined" density="compact" style="font-family:Cairo,sans-serif" />
            </VCol>
            <VCol cols="12" md="6">
              <VSelect
                v-model="editForm.status"
                :items="[
                  { title: 'ظاهر في السوق', value: 'visible' },
                  { title: 'مخفي (بقرار المالك)', value: 'hidden' },
                  { title: 'موقوف (بقرار الإدارة)', value: 'suspended' },
                ]"
                label="حالة الظهور"
                variant="outlined"
                density="compact"
                style="font-family:Cairo,sans-serif"
              />
            </VCol>
            <VCol cols="12" md="6" class="d-flex align-center">
              <VSwitch v-model="editForm.is_featured" label="آلية مميزة (تظهر أولاً في السوق)" color="warning" style="font-family:Cairo,sans-serif" />
            </VCol>
            <VCol cols="12" md="6" class="d-flex align-center">
              <VSwitch v-model="editForm.needs_maintenance" label="بحاجة صيانة (تختفي من السوق مؤقتاً)" color="error" style="font-family:Cairo,sans-serif" />
            </VCol>
            <VCol cols="12">
              <VTextarea v-model="editForm.description" label="الوصف" variant="outlined" density="compact" rows="2" maxlength="2000" counter style="font-family:Cairo,sans-serif" />
            </VCol>
            <VCol cols="12">
              <VTextarea v-model="editForm.admin_notes" label="ملاحظات داخلية" variant="outlined" density="compact" rows="2" style="font-family:Cairo,sans-serif" />
            </VCol>
          </VRow>
        </VCardText>
        <VCardActions class="pa-4 pt-0 justify-end gap-2">
          <VBtn variant="tonal" color="secondary" @click="editDialog = false" style="font-family:Cairo,sans-serif">إلغاء</VBtn>
          <VBtn color="primary" :loading="saving" @click="saveEdit" style="font-family:Cairo,sans-serif">حفظ التعديلات</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- ═══════════════════════════════════════════════════════════════════
         Images Dialog
    ════════════════════════════════════════════════════════════════════ -->
    <VDialog v-model="imagesDialog" max-width="680">
      <VCard>
        <VCardTitle style="font-family:Cairo,sans-serif;font-size:18px;padding:20px 24px 0">
          صور الآلية: {{ selectedItem?.name }}
        </VCardTitle>
        <VCardText>
          <!-- Current images grid -->
          <div v-if="equipImages.length > 0" class="d-flex flex-wrap gap-3 mb-4">
            <div
              v-for="img in equipImages"
              :key="img.id"
              style="position:relative;width:120px;height:100px;border-radius:8px;overflow:hidden;border:2px solid;"
              :style="img.is_primary ? 'border-color:#000269' : 'border-color:#e5e7eb'"
            >
              <img :src="img.url" style="width:100%;height:100%;object-fit:cover;" :alt="`صورة ${img.id}`">
              <!-- Primary badge -->
              <div v-if="img.is_primary" style="position:absolute;top:4px;right:4px;background:#000269;color:white;font-size:10px;padding:2px 6px;border-radius:4px;font-family:Cairo,sans-serif">
                رئيسية
              </div>
              <!-- Actions overlay -->
              <div style="position:absolute;bottom:0;left:0;right:0;display:flex;gap:4px;justify-content:center;padding:4px;background:rgba(0,0,0,0.5);">
                <VBtn
                  v-if="!img.is_primary"
                  icon size="x-small" variant="flat" color="primary"
                  style="width:24px;height:24px;min-width:24px"
                  @click="setPrimary(img)"
                >
                  <VIcon icon="tabler-star" size="12" />
                  <VTooltip activator="parent">تعيين رئيسية</VTooltip>
                </VBtn>
                <VBtn
                  icon size="x-small" variant="flat" color="error"
                  style="width:24px;height:24px;min-width:24px"
                  :loading="deletingImageId === img.id"
                  :disabled="deletingImageId !== null"
                  @click="deleteImage(img)"
                >
                  <VIcon icon="tabler-trash" size="12" />
                  <VTooltip activator="parent">حذف</VTooltip>
                </VBtn>
              </div>
            </div>
          </div>
          <p v-else class="text-medium-emphasis mb-4" style="font-family:Cairo,sans-serif">لا توجد صور مرفوعة بعد</p>

          <!-- Upload new -->
          <VDivider class="mb-4" />
          <p class="text-subtitle-2 mb-2" style="font-family:Cairo,sans-serif">رفع صور جديدة</p>
          <VFileInput
            accept="image/jpeg,image/png,image/webp"
            multiple
            prepend-icon="tabler-upload"
            label="اختر الصور"
            variant="outlined"
            density="compact"
            style="font-family:Cairo,sans-serif"
            @change="onImageFiles"
          />
        </VCardText>
        <VCardActions class="pa-4 pt-0 justify-end gap-2">
          <VBtn variant="tonal" color="secondary" @click="imagesDialog = false" style="font-family:Cairo,sans-serif">إغلاق</VBtn>
          <VBtn
            color="primary"
            :loading="imageUploading"
            :disabled="imageFiles.length === 0"
            prepend-icon="tabler-upload"
            @click="uploadImages"
            style="font-family:Cairo,sans-serif"
          >
            رفع الصور
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- ═══════════════════════════════════════════════════════════════════
         Calendar / Blocked Dates Dialog
    ════════════════════════════════════════════════════════════════════ -->
    <VDialog v-model="calendarDialog" max-width="560" scrollable>
      <VCard :loading="calLoading">
        <VCardTitle style="font-family:Cairo,sans-serif;font-size:18px;padding:20px 24px 0">
          <VIcon icon="tabler-calendar" size="20" class="me-2" />
          تواريخ عدم التوفر: {{ selectedItem?.name }}
        </VCardTitle>
        <VCardText>
          <!-- Reservations (read-only) — طلبات حجز وصلت من تطبيق المقاولين -->
          <p class="text-subtitle-2 mb-1" style="font-family:Cairo,sans-serif">حجوزات المقاولين</p>
          <p class="text-caption text-medium-emphasis mb-2" style="font-family:Cairo,sans-serif">
            حجوزات طلبها مقاولون عبر التطبيق — للاطّلاع فقط، تُلغى من جهة المقاول
          </p>
          <div v-if="reservations.length === 0 && !calLoading" class="text-medium-emphasis mb-4" style="font-family:Cairo,sans-serif">
            لا توجد حجوزات على هذه الآلية
          </div>
          <VList v-else density="compact" class="mb-4 pa-0">
            <VListItem
              v-for="r in reservations"
              :key="r.id"
              class="px-0"
            >
              <template #prepend>
                <VChip
                  :color="reservationStatus[r.status]?.color ?? 'secondary'"
                  variant="tonal"
                  size="x-small"
                  class="me-2"
                  style="font-family:Cairo,sans-serif"
                >
                  {{ reservationStatus[r.status]?.label ?? r.status }}
                </VChip>
              </template>
              <VListItemTitle style="font-family:Cairo,sans-serif;font-size:13px">
                {{ r.contractor?.name ?? '—' }}
              </VListItemTitle>
              <VListItemSubtitle style="font-family:Cairo,sans-serif;font-size:12px">
                {{ r.start_date?.slice(0, 10) }} ← {{ r.end_date?.slice(0, 10) }}
                <span v-if="r.contractor?.phone"> · {{ r.contractor.phone }}</span>
              </VListItemSubtitle>
            </VListItem>
          </VList>

          <VDivider class="mb-4" />

          <!-- Blocked dates list -->
          <p class="text-subtitle-2 mb-1" style="font-family:Cairo,sans-serif">الأيام المحجوزة / الموقوفة</p>
          <p class="text-caption text-medium-emphasis mb-2" style="font-family:Cairo,sans-serif">
            حجب يدوي يضيفه الأدمن لمنع الحجز في أيام محددة (صيانة أو ارتباط خارج المنصة)
          </p>
          <div v-if="blockedDates.length === 0 && !calLoading" class="text-medium-emphasis mb-4" style="font-family:Cairo,sans-serif">
            لا توجد تواريخ محجوزة
          </div>
          <div class="d-flex flex-wrap gap-2 mb-4">
            <VChip
              v-for="bd in blockedDates"
              :key="bd.id"
              closable
              :color="bd.reason === 'booked' ? 'error' : bd.reason === 'maintenance' ? 'warning' : 'secondary'"
              variant="tonal"
              size="small"
              style="font-family:Cairo,sans-serif"
              @click:close="removeDate(bd)"
            >
              {{ bd.blocked_date }} — {{ reasonLabel[bd.reason] ?? bd.reason }}
            </VChip>
          </div>

          <VDivider class="mb-4" />

          <!-- Add new dates -->
          <p class="text-subtitle-2 mb-2" style="font-family:Cairo,sans-serif">إضافة تواريخ محجوزة</p>
          <VRow dense>
            <VCol cols="12">
              <VTextField
                v-model="newDates"
                label="أدخل التواريخ (YYYY-MM-DD) مفصولة بفاصلة"
                variant="outlined"
                density="compact"
                placeholder="2026-07-10,2026-07-11"
                style="font-family:Cairo,sans-serif"
                @change="(v: any) => { newDates = String(v).split(',').map(s => s.trim()).filter(Boolean) }"
              />
            </VCol>
            <VCol cols="12">
              <VSelect
                v-model="dateReason"
                :items="[
                  { title: 'محجوز', value: 'booked' },
                  { title: 'صيانة', value: 'maintenance' },
                  { title: 'أخرى',  value: 'other' },
                ]"
                label="السبب"
                variant="outlined"
                density="compact"
                style="font-family:Cairo,sans-serif"
              />
            </VCol>
          </VRow>
        </VCardText>
        <VCardActions class="pa-4 pt-0 justify-end gap-2">
          <VBtn variant="tonal" color="secondary" @click="calendarDialog = false" style="font-family:Cairo,sans-serif">إغلاق</VBtn>
          <VBtn color="primary" prepend-icon="tabler-plus" @click="addBlockedDates" style="font-family:Cairo,sans-serif">
            إضافة التواريخ
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Delete Dialog -->
    <VDialog v-model="deleteDialog" max-width="400">
      <VCard>
        <VCardText class="pa-6 text-center">
          <VIcon icon="tabler-alert-triangle" size="48" color="error" class="mb-3" />
          <p class="text-body-1" style="font-family:Cairo,sans-serif">
            هل تريد حذف الآلية <strong>{{ selectedItem?.name }}</strong>؟
          </p>
          <p class="text-body-2 text-medium-emphasis" style="font-family:Cairo,sans-serif">
            سيتم حذف جميع الصور والبيانات المرتبطة بها نهائياً.
          </p>
        </VCardText>
        <VCardActions class="justify-center gap-3 pb-4">
          <VBtn variant="tonal" color="secondary" @click="deleteDialog = false" style="font-family:Cairo,sans-serif">إلغاء</VBtn>
          <VBtn color="error" :loading="deleting" @click="confirmDelete" style="font-family:Cairo,sans-serif">حذف نهائياً</VBtn>
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

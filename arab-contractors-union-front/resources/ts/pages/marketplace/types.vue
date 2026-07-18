<script setup lang="ts">
import api from '@/plugins/axios'

definePage({ meta: { requiresAdmin: true,
    adminOnly: true } })

// ─── State ─────────────────────────────────────────────────────────────────
const types       = ref<any[]>([])
const loading     = ref(false)
const dialog      = ref(false)
const saving      = ref(false)
const deleteDialog = ref(false)
const deleting    = ref(false)
const selectedType = ref<any>(null)

const form = ref({
  name_ar:   '',
  name_en:   '',
  icon:      'tabler-crane',
  is_active: true,
})

const iconOptions = [
  'tabler-crane', 'tabler-bulldozer', 'tabler-truck', 'tabler-tools',
  'tabler-engine', 'tabler-forklift', 'tabler-shovel', 'tabler-package',
]

// ─── Load ───────────────────────────────────────────────────────────────────
const fetchTypes = async () => {
  loading.value = true
  try {
    const { data } = await api.get('/api/v1/equipment-types')
    types.value = data
  }
  finally {
    loading.value = false
  }
}

onMounted(fetchTypes)

// ─── Add / Edit ──────────────────────────────────────────────────────────────
const openCreate = () => {
  selectedType.value = null
  form.value = { name_ar: '', name_en: '', icon: 'tabler-crane', is_active: true }
  dialog.value = true
}

const openEdit = (type: any) => {
  selectedType.value = type
  form.value = { name_ar: type.name_ar, name_en: type.name_en ?? '', icon: type.icon ?? 'tabler-crane', is_active: type.is_active }
  dialog.value = true
}

const save = async () => {
  saving.value = true
  try {
    if (selectedType.value) {
      const { data } = await api.patch(`/api/v1/equipment-types/${selectedType.value.id}`, form.value)
      const idx = types.value.findIndex(t => t.id === data.id)
      if (idx !== -1) types.value[idx] = data
    }
    else {
      const { data } = await api.post('/api/v1/equipment-types', form.value)
      types.value.unshift(data)
    }
    dialog.value = false
  }
  finally {
    saving.value = false
  }
}

// ─── Delete ──────────────────────────────────────────────────────────────────
const openDelete = (type: any) => {
  selectedType.value = type
  deleteDialog.value = true
}

const confirmDelete = async () => {
  deleting.value = true
  try {
    await api.delete(`/api/v1/equipment-types/${selectedType.value.id}`)
    types.value = types.value.filter(t => t.id !== selectedType.value.id)
    deleteDialog.value = false
  }
  catch (e: any) {
    alert(e?.response?.data?.message ?? 'حدث خطأ أثناء الحذف')
  }
  finally {
    deleting.value = false
  }
}

// ─── Toggle active ───────────────────────────────────────────────────────────
const toggleActive = async (type: any) => {
  try {
    const { data } = await api.patch(`/api/v1/equipment-types/${type.id}`, { is_active: !type.is_active })
    const idx = types.value.findIndex(t => t.id === data.id)
    if (idx !== -1) types.value[idx] = data
  }
  catch {/* silent */}
}
</script>

<template>
  <div>
    <!-- Header -->
    <div class="d-flex align-center justify-space-between mb-6">
      <div>
        <h1 class="text-h4 font-weight-bold" style="font-family:Cairo,sans-serif">أنواع المعدات</h1>
        <p class="text-body-2 text-medium-emphasis mb-0" style="font-family:Cairo,sans-serif">
          إدارة تصنيفات الآليات المتاحة في سوق الآليات
        </p>
      </div>
      <VBtn color="primary" prepend-icon="tabler-plus" style="font-family:Cairo,sans-serif" @click="openCreate">
        إضافة نوع
      </VBtn>
    </div>

    <!-- Table -->
    <VCard :loading="loading">
      <VTable>
        <thead>
          <tr>
            <th style="font-family:Cairo,sans-serif">الأيقونة</th>
            <th style="font-family:Cairo,sans-serif">الاسم بالعربية</th>
            <th style="font-family:Cairo,sans-serif">الاسم بالإنجليزية</th>
            <th style="font-family:Cairo,sans-serif">الحالة</th>
            <th style="font-family:Cairo,sans-serif">الإجراءات</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="!loading && types.length === 0">
            <td colspan="5" class="text-center pa-8 text-medium-emphasis" style="font-family:Cairo,sans-serif">
              لا توجد أنواع معدات مضافة بعد
            </td>
          </tr>
          <tr v-for="type in types" :key="type.id">
            <td>
              <VAvatar color="primary" variant="tonal" size="36">
                <VIcon :icon="type.icon || 'tabler-tools'" size="20" />
              </VAvatar>
            </td>
            <td style="font-family:Cairo,sans-serif;font-weight:600">{{ type.name_ar }}</td>
            <td style="font-family:Cairo,sans-serif;color:#6B7280">{{ type.name_en || '—' }}</td>
            <td>
              <VSwitch
                :model-value="type.is_active"
                color="success"
                density="compact"
                hide-details
                @change="toggleActive(type)"
              />
            </td>
            <td>
              <div class="d-flex gap-2">
                <VBtn icon size="small" variant="tonal" color="primary" @click="openEdit(type)">
                  <VIcon icon="tabler-edit" size="18" />
                  <VTooltip activator="parent">تعديل</VTooltip>
                </VBtn>
                <VBtn icon size="small" variant="tonal" color="error" @click="openDelete(type)">
                  <VIcon icon="tabler-trash" size="18" />
                  <VTooltip activator="parent">حذف</VTooltip>
                </VBtn>
              </div>
            </td>
          </tr>
        </tbody>
      </VTable>
    </VCard>

    <!-- Add/Edit Dialog -->
    <VDialog v-model="dialog" max-width="500">
      <VCard>
        <VCardTitle style="font-family:Cairo,sans-serif;font-size:18px;padding:20px 24px 0">
          {{ selectedType ? 'تعديل نوع المعدة' : 'إضافة نوع معدة جديد' }}
        </VCardTitle>
        <VCardText class="pt-4">
          <VRow>
            <VCol cols="12">
              <VTextField
                v-model="form.name_ar"
                label="الاسم بالعربية *"
                variant="outlined"
                density="compact"
                style="font-family:Cairo,sans-serif"
              />
            </VCol>
            <VCol cols="12">
              <VTextField
                v-model="form.name_en"
                label="الاسم بالإنجليزية"
                variant="outlined"
                density="compact"
              />
            </VCol>
            <VCol cols="12">
              <VSelect
                v-model="form.icon"
                label="الأيقونة"
                :items="iconOptions"
                variant="outlined"
                density="compact"
                style="font-family:Cairo,sans-serif"
              >
                <template #item="{ item, props }">
                  <VListItem v-bind="props">
                    <template #prepend>
                      <VIcon :icon="item.value" size="20" class="me-2" />
                    </template>
                  </VListItem>
                </template>
                <template #selection="{ item }">
                  <div class="d-flex align-center gap-2">
                    <VIcon :icon="item.value" size="18" />
                    <span style="font-family:Cairo,sans-serif">{{ item.value }}</span>
                  </div>
                </template>
              </VSelect>
            </VCol>
            <VCol cols="12">
              <VSwitch
                v-model="form.is_active"
                label="نشط (يظهر في القوائم)"
                color="success"
                hide-details
                style="font-family:Cairo,sans-serif"
              />
            </VCol>
          </VRow>
        </VCardText>
        <VCardActions class="pa-4 pt-0 justify-end gap-2">
          <VBtn variant="tonal" color="secondary" @click="dialog = false" style="font-family:Cairo,sans-serif">إلغاء</VBtn>
          <VBtn color="primary" :loading="saving" :disabled="!form.name_ar.trim()" @click="save" style="font-family:Cairo,sans-serif">
            {{ selectedType ? 'حفظ التعديلات' : 'إضافة' }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Delete Dialog -->
    <VDialog v-model="deleteDialog" max-width="400">
      <VCard>
        <VCardText class="pa-6 text-center">
          <VIcon icon="tabler-alert-triangle" size="48" color="error" class="mb-3" />
          <p class="text-body-1 mb-0" style="font-family:Cairo,sans-serif">
            هل تريد حذف نوع المعدة <strong>{{ selectedType?.name_ar }}</strong>؟
          </p>
          <p class="text-body-2 text-medium-emphasis" style="font-family:Cairo,sans-serif">
            لا يمكن حذف النوع إذا كانت هناك آليات مرتبطة به.
          </p>
        </VCardText>
        <VCardActions class="justify-center gap-3 pb-4">
          <VBtn variant="tonal" color="secondary" @click="deleteDialog = false" style="font-family:Cairo,sans-serif">إلغاء</VBtn>
          <VBtn color="error" :loading="deleting" @click="confirmDelete" style="font-family:Cairo,sans-serif">حذف</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>

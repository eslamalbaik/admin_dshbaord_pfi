<script setup lang="ts">
import api from '@/plugins/axios'

definePage({ meta: { requiresAdmin: true, adminOnly: true } })

// ─── State ─────────────────────────────────────────────────────────────────
const categories    = ref<any[]>([])
const loading       = ref(false)
const dialog        = ref(false)
const saving        = ref(false)
const deleteDialog  = ref(false)
const deleting      = ref(false)
const deleteError   = ref('')
const selectedCategory = ref<any>(null)

const form = ref({
  name:      '',
  is_active: true,
})

// ─── Load ───────────────────────────────────────────────────────────────────
const fetchCategories = async () => {
  loading.value = true
  try {
    const { data } = await api.get('/api/v1/announcement-categories')
    categories.value = data
  }
  finally {
    loading.value = false
  }
}

onMounted(fetchCategories)

// ─── Add / Edit ──────────────────────────────────────────────────────────────
const openCreate = () => {
  selectedCategory.value = null
  form.value = { name: '', is_active: true }
  dialog.value = true
}

const openEdit = (category: any) => {
  selectedCategory.value = category
  form.value = { name: category.name, is_active: category.is_active }
  dialog.value = true
}

const save = async () => {
  saving.value = true
  try {
    if (selectedCategory.value) {
      const { data } = await api.patch(`/api/v1/announcement-categories/${selectedCategory.value.id}`, form.value)
      const idx = categories.value.findIndex(c => c.id === data.id)
      if (idx !== -1) categories.value[idx] = data
    }
    else {
      const { data } = await api.post('/api/v1/announcement-categories', form.value)
      categories.value.unshift(data)
    }
    dialog.value = false
  }
  finally {
    saving.value = false
  }
}

// ─── Delete ──────────────────────────────────────────────────────────────────
const openDelete = (category: any) => {
  selectedCategory.value = category
  deleteError.value = ''
  deleteDialog.value = true
}

const confirmDelete = async () => {
  deleting.value = true
  deleteError.value = ''
  try {
    await api.delete(`/api/v1/announcement-categories/${selectedCategory.value.id}`)
    categories.value = categories.value.filter(c => c.id !== selectedCategory.value.id)
    deleteDialog.value = false
  }
  catch (e: any) {
    deleteError.value = e?.response?.data?.message ?? 'حدث خطأ أثناء الحذف'
  }
  finally {
    deleting.value = false
  }
}

// ─── Toggle active ───────────────────────────────────────────────────────────
const toggleActive = async (category: any) => {
  try {
    const { data } = await api.patch(`/api/v1/announcement-categories/${category.id}`, { is_active: !category.is_active })
    const idx = categories.value.findIndex(c => c.id === data.id)
    if (idx !== -1) categories.value[idx] = data
  }
  catch {/* silent */}
}
</script>

<template>
  <div>
    <!-- Header -->
    <div class="d-flex align-center justify-space-between mb-6">
      <div>
        <h1 class="text-h4 font-weight-bold" style="font-family:Cairo,sans-serif">تصنيفات التعميمات</h1>
        <p class="text-body-2 text-medium-emphasis mb-0" style="font-family:Cairo,sans-serif">
          التصنيفات المعتمدة من الإدارة، والمتاحة عند إنشاء تعميم جديد
        </p>
      </div>
      <VBtn color="primary" prepend-icon="tabler-plus" style="font-family:Cairo,sans-serif" @click="openCreate">
        إضافة تصنيف
      </VBtn>
    </div>

    <!-- Table -->
    <VCard :loading="loading">
      <VTable>
        <thead>
          <tr>
            <th style="font-family:Cairo,sans-serif">الاسم</th>
            <th style="font-family:Cairo,sans-serif">الحالة</th>
            <th style="font-family:Cairo,sans-serif">الإجراءات</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="!loading && categories.length === 0">
            <td colspan="3" class="text-center pa-8 text-medium-emphasis" style="font-family:Cairo,sans-serif">
              لا توجد تصنيفات مضافة بعد
            </td>
          </tr>
          <tr v-for="category in categories" :key="category.id">
            <td style="font-family:Cairo,sans-serif;font-weight:600">{{ category.name }}</td>
            <td>
              <VSwitch
                :model-value="category.is_active"
                color="success"
                density="compact"
                hide-details
                @change="toggleActive(category)"
              />
            </td>
            <td>
              <div class="d-flex gap-2">
                <VBtn icon size="small" variant="tonal" color="primary" @click="openEdit(category)">
                  <VIcon icon="tabler-edit" size="18" />
                  <VTooltip activator="parent">تعديل</VTooltip>
                </VBtn>
                <VBtn icon size="small" variant="tonal" color="error" @click="openDelete(category)">
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
          {{ selectedCategory ? 'تعديل التصنيف' : 'إضافة تصنيف جديد' }}
        </VCardTitle>
        <VCardText class="pt-4">
          <VRow>
            <VCol cols="12">
              <VTextField
                v-model="form.name"
                label="اسم التصنيف *"
                variant="outlined"
                density="compact"
                style="font-family:Cairo,sans-serif"
              />
            </VCol>
            <VCol cols="12">
              <VSwitch
                v-model="form.is_active"
                label="نشط (يظهر عند إنشاء تعميم جديد)"
                color="success"
                hide-details
                style="font-family:Cairo,sans-serif"
              />
            </VCol>
          </VRow>
        </VCardText>
        <VCardActions class="pa-4 pt-0 justify-end gap-2">
          <VBtn variant="tonal" color="secondary" @click="dialog = false" style="font-family:Cairo,sans-serif">إلغاء</VBtn>
          <VBtn color="primary" :loading="saving" :disabled="!form.name.trim()" @click="save" style="font-family:Cairo,sans-serif">
            {{ selectedCategory ? 'حفظ التعديلات' : 'إضافة' }}
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
            هل تريد حذف التصنيف <strong>{{ selectedCategory?.name }}</strong>؟
          </p>
          <p class="text-body-2 text-medium-emphasis" style="font-family:Cairo,sans-serif">
            لا يمكن حذف التصنيف إذا كانت هناك تعميمات مرتبطة به.
          </p>
          <p v-if="deleteError" class="text-body-2 text-error mt-2" style="font-family:Cairo,sans-serif">
            {{ deleteError }}
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

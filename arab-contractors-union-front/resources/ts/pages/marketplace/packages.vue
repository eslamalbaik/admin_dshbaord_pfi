<script setup lang="ts">
import api from '@/plugins/axios'

definePage({ meta: { requiresAdmin: true, adminOnly: true } })

// ─── State ─────────────────────────────────────────────────────────────────
const packages     = ref<any[]>([])
const loading      = ref(false)
const dialog       = ref(false)
const saving       = ref(false)
const deleteDialog = ref(false)
const deleting     = ref(false)
const selectedPackage = ref<any>(null)

const emptyForm = () => ({
  name: '',
  price: 0,
  currency: 'JOD',
  duration_days: 365,
  ads_limit: null as number | null,
  is_active: true,
})

const form = ref(emptyForm())

const currencyOptions = ['JOD', 'ILS', 'USD']

// ─── Load ───────────────────────────────────────────────────────────────────
const fetchPackages = async () => {
  loading.value = true
  try {
    const { data } = await api.get('/api/v1/equipment-packages')
    packages.value = data.items ?? []
  }
  finally {
    loading.value = false
  }
}

onMounted(fetchPackages)

// ─── Add / Edit ──────────────────────────────────────────────────────────────
const openCreate = () => {
  selectedPackage.value = null
  form.value = emptyForm()
  dialog.value = true
}

const openEdit = (pkg: any) => {
  selectedPackage.value = pkg
  form.value = {
    name: pkg.name,
    price: pkg.price,
    currency: pkg.currency ?? 'JOD',
    duration_days: pkg.duration_days,
    ads_limit: pkg.ads_limit,
    is_active: pkg.is_active,
  }
  dialog.value = true
}

const save = async () => {
  saving.value = true
  try {
    if (selectedPackage.value) {
      const { data } = await api.patch(`/api/v1/equipment-packages/${selectedPackage.value.id}`, form.value)
      const idx = packages.value.findIndex(p => p.id === data.items.id)
      if (idx !== -1) packages.value[idx] = data.items
    }
    else {
      const { data } = await api.post('/api/v1/equipment-packages', form.value)
      packages.value.push(data.items)
    }
    dialog.value = false
  }
  finally {
    saving.value = false
  }
}

// ─── Delete ──────────────────────────────────────────────────────────────────
const openDelete = (pkg: any) => {
  selectedPackage.value = pkg
  deleteDialog.value = true
}

const confirmDelete = async () => {
  deleting.value = true
  try {
    await api.delete(`/api/v1/equipment-packages/${selectedPackage.value.id}`)
    packages.value = packages.value.filter(p => p.id !== selectedPackage.value.id)
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
const toggleActive = async (pkg: any) => {
  try {
    const { data } = await api.patch(`/api/v1/equipment-packages/${pkg.id}`, { is_active: !pkg.is_active })
    const idx = packages.value.findIndex(p => p.id === data.items.id)
    if (idx !== -1) packages.value[idx] = data.items
  }
  catch {/* silent */}
}
</script>

<template>
  <div>
    <!-- Header -->
    <div class="d-flex align-center justify-space-between mb-6">
      <div>
        <h1 class="text-h4 font-weight-bold" style="font-family:Cairo,sans-serif">باقات اشتراك سوق الآليات</h1>
        <p class="text-body-2 text-medium-emphasis mb-0" style="font-family:Cairo,sans-serif">
          إدارة الباقات المدفوعة لنشر الآليات في سوق الآليات
        </p>
      </div>
      <VBtn color="primary" prepend-icon="tabler-plus" style="font-family:Cairo,sans-serif" @click="openCreate">
        إضافة باقة
      </VBtn>
    </div>

    <!-- Table -->
    <VCard :loading="loading">
      <VTable>
        <thead>
          <tr>
            <th style="font-family:Cairo,sans-serif">اسم الباقة</th>
            <th style="font-family:Cairo,sans-serif">السعر</th>
            <th style="font-family:Cairo,sans-serif">المدة (يوم)</th>
            <th style="font-family:Cairo,sans-serif">حد الإعلانات</th>
            <th style="font-family:Cairo,sans-serif">الحالة</th>
            <th style="font-family:Cairo,sans-serif">الإجراءات</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="!loading && packages.length === 0">
            <td colspan="6" class="text-center pa-8 text-medium-emphasis" style="font-family:Cairo,sans-serif">
              لا توجد باقات مضافة بعد
            </td>
          </tr>
          <tr v-for="pkg in packages" :key="pkg.id">
            <td style="font-family:Cairo,sans-serif;font-weight:600">{{ pkg.name }}</td>
            <td style="font-family:Cairo,sans-serif">{{ pkg.price }} {{ pkg.currency }}</td>
            <td style="font-family:Cairo,sans-serif">{{ pkg.duration_days }}</td>
            <td style="font-family:Cairo,sans-serif">{{ pkg.ads_limit ?? 'بلا حد' }}</td>
            <td>
              <VSwitch
                :model-value="pkg.is_active"
                color="success"
                density="compact"
                hide-details
                @change="toggleActive(pkg)"
              />
            </td>
            <td>
              <div class="d-flex gap-2">
                <VBtn icon size="small" variant="tonal" color="primary" @click="openEdit(pkg)">
                  <VIcon icon="tabler-edit" size="18" />
                  <VTooltip activator="parent">تعديل</VTooltip>
                </VBtn>
                <VBtn icon size="small" variant="tonal" color="error" @click="openDelete(pkg)">
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
          {{ selectedPackage ? 'تعديل الباقة' : 'إضافة باقة جديدة' }}
        </VCardTitle>
        <VCardText class="pt-4">
          <VRow>
            <VCol cols="12">
              <VTextField
                v-model="form.name"
                label="اسم الباقة *"
                variant="outlined"
                density="compact"
                style="font-family:Cairo,sans-serif"
              />
            </VCol>
            <VCol cols="6">
              <VTextField
                v-model.number="form.price"
                label="السعر *"
                type="number"
                variant="outlined"
                density="compact"
                dir="ltr"
              />
            </VCol>
            <VCol cols="6">
              <VSelect
                v-model="form.currency"
                label="العملة"
                :items="currencyOptions"
                variant="outlined"
                density="compact"
                dir="ltr"
              />
            </VCol>
            <VCol cols="6">
              <VTextField
                v-model.number="form.duration_days"
                label="مدة الاشتراك (يوم) *"
                type="number"
                variant="outlined"
                density="compact"
                dir="ltr"
              />
            </VCol>
            <VCol cols="6">
              <VTextField
                v-model.number="form.ads_limit"
                label="الحد الأقصى للإعلانات (اختياري)"
                type="number"
                variant="outlined"
                density="compact"
                dir="ltr"
              />
            </VCol>
            <VCol cols="12">
              <VSwitch
                v-model="form.is_active"
                label="نشطة (متاحة للاشتراك)"
                color="success"
                hide-details
                style="font-family:Cairo,sans-serif"
              />
            </VCol>
          </VRow>
        </VCardText>
        <VCardActions class="pa-4 pt-0 justify-end gap-2">
          <VBtn variant="tonal" color="secondary" @click="dialog = false" style="font-family:Cairo,sans-serif">إلغاء</VBtn>
          <VBtn color="primary" :loading="saving" :disabled="!form.name.trim() || !form.price || !form.duration_days" @click="save" style="font-family:Cairo,sans-serif">
            {{ selectedPackage ? 'حفظ التعديلات' : 'إضافة' }}
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
            هل تريد حذف الباقة <strong>{{ selectedPackage?.name }}</strong>؟
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

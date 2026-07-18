<script setup lang="ts">
import { ref, computed } from 'vue'
import { useQuery, useMutation, useQueryClient } from '@tanstack/vue-query'
import api from '@/plugins/axios'

definePage({ meta: { requiresAdmin: true } })

const queryClient = useQueryClient()

const { data, isLoading } = useQuery({
  queryKey: ['bank-accounts'],
  queryFn: async () => (await api.get('/api/v1/dashboard/bank-accounts')).data,
})

const accounts = computed(() => data.value?.items?.bank_accounts ?? [])

const emptyForm = () => ({
  bank_name: '',
  bank_name_en: '',
  iban: '',
  account_number: '',
  account_holder: '',
  swift: '',
  notes: '',
  is_active: true,
  sort: 0,
})

const isFormOpen = ref(false)
const isEditing = ref(false)
const editingId = ref<number | null>(null)
const form = ref<any>(emptyForm())
const logoFile = ref<File[]>([])
const formError = ref('')

const openCreate = () => {
  isEditing.value = false
  editingId.value = null
  form.value = emptyForm()
  form.value.sort = accounts.value.length
  logoFile.value = []
  formError.value = ''
  isFormOpen.value = true
}

const openEdit = (a: any) => {
  isEditing.value = true
  editingId.value = a.id
  form.value = { ...a }
  logoFile.value = []
  formError.value = ''
  isFormOpen.value = true
}

const saveMutation = useMutation({
  mutationFn: async () => {
    const fd = new FormData()
    for (const key of ['bank_name', 'bank_name_en', 'iban', 'account_number', 'account_holder', 'swift', 'notes'])
      fd.append(key, form.value[key] ?? '')
    fd.append('is_active', form.value.is_active ? '1' : '0')
    fd.append('sort', String(form.value.sort ?? 0))
    if (logoFile.value.length)
      fd.append('logo', logoFile.value[0])

    if (isEditing.value) {
      fd.append('_method', 'PUT')

      return (await api.post(`/api/v1/dashboard/bank-accounts/${editingId.value}`, fd)).data
    }

    return (await api.post('/api/v1/dashboard/bank-accounts', fd)).data
  },
  onSuccess: () => {
    queryClient.invalidateQueries({ queryKey: ['bank-accounts'] })
    isFormOpen.value = false
  },
  onError: (e: any) => {
    formError.value = e?.response?.data?.message || 'فشل حفظ الحساب البنكي.'
  },
})

const deleteMutation = useMutation({
  mutationFn: async (id: number) => (await api.delete(`/api/v1/dashboard/bank-accounts/${id}`)).data,
  onSuccess: () => queryClient.invalidateQueries({ queryKey: ['bank-accounts'] }),
})

const confirmDelete = (a: any) => {
  if (confirm('هل تريد حذف هذا الحساب البنكي؟'))
    deleteMutation.mutate(a.id)
}
</script>

<template>
  <div>
    <div class="d-flex justify-space-between align-center mb-6 flex-wrap gap-4">
      <div>
        <h1 class="text-h4 font-weight-bold">الحسابات البنكية</h1>
        <p class="text-body-2 text-medium-emphasis mb-0">
          الحسابات البنكية الظاهرة في شاشة الدفع بالتطبيق (معلومات الدفع)
        </p>
      </div>
      <VBtn color="primary" prepend-icon="tabler-plus" @click="openCreate">
        إضافة حساب بنكي
      </VBtn>
    </div>

    <VProgressLinear v-if="isLoading" indeterminate color="primary" />

    <VCard v-else-if="accounts.length === 0" class="text-center py-12">
      <VIcon icon="tabler-building-bank" size="64" color="disabled" class="mb-3" />
      <p class="text-h6 text-medium-emphasis">لا توجد حسابات بنكية بعد.</p>
      <VBtn class="mt-4" color="primary" prepend-icon="tabler-plus" @click="openCreate">
        أضف أول حساب
      </VBtn>
    </VCard>

    <VCard v-else>
      <VTable>
        <thead>
          <tr>
            <th>البنك</th>
            <th>IBAN</th>
            <th>رقم الحساب</th>
            <th>صاحب الحساب</th>
            <th>الحالة</th>
            <th class="text-center">إجراءات</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="a in accounts" :key="a.id">
            <td>
              <div class="d-flex align-center gap-3">
                <VAvatar v-if="a.logo_url" :image="a.logo_url" size="32" rounded />
                <VAvatar v-else size="32" rounded color="secondary" variant="tonal">
                  <VIcon icon="tabler-building-bank" size="18" />
                </VAvatar>
                <div>
                  <div class="font-weight-medium">{{ a.bank_name }}</div>
                  <div v-if="a.bank_name_en" class="text-caption text-medium-emphasis" dir="ltr">
                    {{ a.bank_name_en }}
                  </div>
                </div>
              </div>
            </td>
            <td dir="ltr">{{ a.iban }}</td>
            <td dir="ltr">{{ a.account_number ?? '—' }}</td>
            <td>{{ a.account_holder ?? '—' }}</td>
            <td>
              <VChip size="x-small" :color="a.is_active ? 'success' : 'secondary'">
                {{ a.is_active ? 'نشط' : 'مخفي' }}
              </VChip>
            </td>
            <td class="text-center">
              <VBtn icon="tabler-edit" size="x-small" variant="text" @click="openEdit(a)" />
              <VBtn icon="tabler-trash" size="x-small" variant="text" color="error" @click="confirmDelete(a)" />
            </td>
          </tr>
        </tbody>
      </VTable>
    </VCard>

    <!-- Form Dialog -->
    <VDialog v-model="isFormOpen" max-width="640" persistent scrollable>
      <VCard>
        <VCardTitle class="pt-4 pb-0">
          <span class="text-h6">{{ isEditing ? 'تعديل الحساب البنكي' : 'إضافة حساب بنكي' }}</span>
        </VCardTitle>

        <VCardText class="pt-4">
          <VAlert v-if="formError" type="error" variant="tonal" class="mb-4">
            {{ formError }}
          </VAlert>

          <VRow dense>
            <VCol cols="12" md="6">
              <VTextField v-model="form.bank_name" label="اسم البنك (عربي) *" dir="rtl" />
            </VCol>
            <VCol cols="12" md="6">
              <VTextField v-model="form.bank_name_en" label="Bank Name (English)" dir="ltr" />
            </VCol>
            <VCol cols="12">
              <VTextField v-model="form.iban" label="IBAN *" dir="ltr" />
            </VCol>
            <VCol cols="12" md="6">
              <VTextField v-model="form.account_number" label="رقم الحساب" dir="ltr" />
            </VCol>
            <VCol cols="12" md="6">
              <VTextField v-model="form.swift" label="SWIFT" dir="ltr" />
            </VCol>
            <VCol cols="12">
              <VTextField v-model="form.account_holder" label="اسم صاحب الحساب" />
            </VCol>
            <VCol cols="12">
              <VTextarea v-model="form.notes" label="ملاحظات" rows="2" />
            </VCol>
            <VCol cols="12" md="6">
              <VFileInput
                v-model="logoFile"
                label="شعار البنك"
                accept="image/*"
                density="compact"
                prepend-icon="tabler-photo"
              />
            </VCol>
            <VCol cols="6" md="3">
              <VTextField v-model.number="form.sort" label="الترتيب" type="number" min="0" density="compact" />
            </VCol>
            <VCol cols="6" md="3">
              <VSwitch v-model="form.is_active" label="نشط" color="success" />
            </VCol>
          </VRow>
        </VCardText>

        <VCardActions>
          <VSpacer />
          <VBtn variant="text" @click="isFormOpen = false">إلغاء</VBtn>
          <VBtn
            color="primary"
            :loading="saveMutation.isPending.value"
            :disabled="!form.bank_name || !form.iban"
            @click="saveMutation.mutate()"
          >
            حفظ
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>

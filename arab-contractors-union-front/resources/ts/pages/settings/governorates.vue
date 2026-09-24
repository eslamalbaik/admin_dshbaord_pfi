<script setup lang="ts">
import { computed, ref } from 'vue'
import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query'
import api from '@/plugins/axios'

definePage({ meta: { requiresAdmin: true, adminOnly: true } })

const queryClient = useQueryClient()
const tab = ref('governorates')

// ─── المحافظات ───────────────────────────────────────────────────────────
const { data: governoratesData, isLoading: governoratesLoading } = useQuery({
  queryKey: ['governorates'],
  queryFn: async () => (await api.get('/api/v1/dashboard/governorates')).data,
})
const governorates = computed(() => governoratesData.value?.items ?? [])

// ─── المدن ───────────────────────────────────────────────────────────────
const { data: citiesData, isLoading: citiesLoading } = useQuery({
  queryKey: ['cities'],
  queryFn: async () => (await api.get('/api/v1/dashboard/cities')).data,
})
const cities = computed(() => citiesData.value?.items ?? [])

// ─── فورم عام (يُعاد استخدامه للنوعين) ─────────────────────────────────────
type LookupKind = 'governorate' | 'city'

const kindConfig: Record<LookupKind, { endpoint: string, queryKey: string, label: string }> = {
  governorate: { endpoint: 'governorates', queryKey: 'governorates', label: 'محافظة' },
  city:        { endpoint: 'cities',       queryKey: 'cities',       label: 'مدينة' },
}

const formOpen = ref(false)
const formKind = ref<LookupKind>('governorate')
const formEditingId = ref<number | null>(null)
const formError = ref('')
const form = ref<Record<string, any>>({})

const openCreate = (kind: LookupKind) => {
  formKind.value = kind
  formEditingId.value = null
  formError.value = ''
  form.value = kind === 'city'
    ? { governorate_id: null, name: '' }
    : { name: '' }
  formOpen.value = true
}

const openEdit = (kind: LookupKind, row: any) => {
  formKind.value = kind
  formEditingId.value = row.id
  formError.value = ''
  form.value = kind === 'city'
    ? { governorate_id: row.governorate_id, name: row.name }
    : { name: row.name }
  formOpen.value = true
}

const saveMutation = useMutation({
  mutationFn: async () => {
    const cfg = kindConfig[formKind.value]
    return formEditingId.value
      ? (await api.patch(`/api/v1/dashboard/${cfg.endpoint}/${formEditingId.value}`, form.value)).data
      : (await api.post(`/api/v1/dashboard/${cfg.endpoint}`, form.value)).data
  },
  onSuccess: () => {
    // إضافة/تعديل مدينة يغيّر عدد مدن المحافظة المعروض بجدول المحافظات أيضاً
    queryClient.invalidateQueries({ queryKey: ['governorates'] })
    queryClient.invalidateQueries({ queryKey: ['cities'] })
    formOpen.value = false
  },
  onError: (e: any) => {
    const errors = e?.response?.data?.errors
    formError.value = errors
      ? Object.values(errors).flat().join(' — ')
      : (e?.response?.data?.message || 'فشل الحفظ. يرجى التحقق من المدخلات.')
  },
})

// ─── تعطيل (حذف ناعم) مع تأكيد ─────────────────────────────────────────────
const deactivateDialog = ref(false)
const deactivateTarget = ref<{ kind: LookupKind, row: any } | null>(null)

const confirmDeactivate = (kind: LookupKind, row: any) => {
  deactivateTarget.value = { kind, row }
  deactivateDialog.value = true
}

const deactivateMutation = useMutation({
  mutationFn: async () => {
    const { kind, row } = deactivateTarget.value!
    return api.delete(`/api/v1/dashboard/${kindConfig[kind].endpoint}/${row.id}`)
  },
  onSuccess: () => {
    queryClient.invalidateQueries({ queryKey: ['governorates'] })
    queryClient.invalidateQueries({ queryKey: ['cities'] })
    deactivateDialog.value = false
  },
})

const reactivateMutation = useMutation({
  mutationFn: async ({ kind, row }: { kind: LookupKind, row: any }) =>
    api.patch(`/api/v1/dashboard/${kindConfig[kind].endpoint}/${row.id}`, { is_active: true }),
  onSuccess: () => {
    queryClient.invalidateQueries({ queryKey: ['governorates'] })
    queryClient.invalidateQueries({ queryKey: ['cities'] })
  },
})
</script>

<template>
  <div>
    <div class="mb-6">
      <h1 class="text-h4 font-weight-bold">إدارة المحافظات والمدن</h1>
      <p class="text-body-2 text-medium-emphasis mb-0">
        هذه القوائم تغذّي حقلي المحافظة والمدينة في فورم بيانات المقاول مباشرة —
        "الحذف" هنا يُخفي العنصر عن الخيارات فقط ولا يمسه فعلياً حتى لا تتأثر بيانات المقاولين
        الحاليين المرتبطين به.
      </p>
    </div>

    <VTabs v-model="tab" class="mb-4">
      <VTab value="governorates">المحافظات</VTab>
      <VTab value="cities">المدن</VTab>
    </VTabs>

    <!-- المحافظات -->
    <VCard v-if="tab === 'governorates'">
      <VCardTitle class="d-flex justify-space-between align-center">
        <span>المحافظات</span>
        <VBtn size="small" color="primary" prepend-icon="tabler-plus" @click="openCreate('governorate')">إضافة محافظة</VBtn>
      </VCardTitle>
      <VProgressLinear v-if="governoratesLoading" indeterminate color="primary" />
      <VTable v-else>
        <thead>
          <tr>
            <th>الاسم</th>
            <th>عدد المدن</th>
            <th>الحالة</th>
            <th class="text-center">إجراءات</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="row in governorates" :key="row.id">
            <td>{{ row.name }}</td>
            <td>{{ row.cities_count }}</td>
            <td>
              <VChip size="small" :color="row.is_active ? 'success' : 'default'" variant="tonal">
                {{ row.is_active ? 'مفعّلة' : 'مخفية' }}
              </VChip>
            </td>
            <td class="text-center">
              <VBtn icon="tabler-edit" size="x-small" variant="text" @click="openEdit('governorate', row)" />
              <VBtn
                v-if="row.is_active"
                icon="tabler-trash" size="x-small" variant="text" color="error"
                @click="confirmDeactivate('governorate', row)"
              />
              <VBtn
                v-else
                icon="tabler-refresh" size="x-small" variant="text" color="success"
                title="إعادة تفعيل"
                @click="reactivateMutation.mutate({ kind: 'governorate', row })"
              />
            </td>
          </tr>
        </tbody>
      </VTable>
    </VCard>

    <!-- المدن -->
    <VCard v-if="tab === 'cities'">
      <VCardTitle class="d-flex justify-space-between align-center">
        <span>المدن</span>
        <VBtn size="small" color="primary" prepend-icon="tabler-plus" @click="openCreate('city')">إضافة مدينة</VBtn>
      </VCardTitle>
      <VProgressLinear v-if="citiesLoading" indeterminate color="primary" />
      <VTable v-else>
        <thead>
          <tr>
            <th>الاسم</th>
            <th>المحافظة</th>
            <th>الحالة</th>
            <th class="text-center">إجراءات</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="row in cities" :key="row.id">
            <td>{{ row.name }}</td>
            <td>{{ row.governorate?.name ?? '—' }}</td>
            <td>
              <VChip size="small" :color="row.is_active ? 'success' : 'default'" variant="tonal">
                {{ row.is_active ? 'مفعّلة' : 'مخفية' }}
              </VChip>
            </td>
            <td class="text-center">
              <VBtn icon="tabler-edit" size="x-small" variant="text" @click="openEdit('city', row)" />
              <VBtn
                v-if="row.is_active"
                icon="tabler-trash" size="x-small" variant="text" color="error"
                @click="confirmDeactivate('city', row)"
              />
              <VBtn
                v-else
                icon="tabler-refresh" size="x-small" variant="text" color="success"
                title="إعادة تفعيل"
                @click="reactivateMutation.mutate({ kind: 'city', row })"
              />
            </td>
          </tr>
        </tbody>
      </VTable>
    </VCard>

    <!-- فورم إضافة/تعديل -->
    <VDialog v-model="formOpen" max-width="480" persistent>
      <VCard>
        <VCardTitle class="pt-4 pb-0">
          <span class="text-h6">{{ formEditingId ? 'تعديل' : 'إضافة' }} {{ kindConfig[formKind].label }}</span>
        </VCardTitle>
        <VCardText class="pt-4">
          <VAlert v-if="formError" type="error" variant="tonal" class="mb-4">{{ formError }}</VAlert>

          <VRow dense>
            <template v-if="formKind === 'city'">
              <VCol cols="12">
                <VSelect
                  v-model="form.governorate_id"
                  :items="governorates"
                  item-title="name"
                  item-value="id"
                  label="المحافظة"
                />
              </VCol>
              <VCol cols="12">
                <VTextField v-model="form.name" label="اسم المدينة" />
              </VCol>
            </template>

            <template v-else>
              <VCol cols="12">
                <VTextField v-model="form.name" label="اسم المحافظة" />
              </VCol>
            </template>
          </VRow>
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn variant="text" @click="formOpen = false">إلغاء</VBtn>
          <VBtn color="primary" :loading="saveMutation.isPending.value" @click="saveMutation.mutate()">حفظ</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- تأكيد التعطيل -->
    <VDialog v-model="deactivateDialog" max-width="420">
      <VCard>
        <VCardTitle class="d-flex align-center gap-2">
          <VIcon icon="tabler-alert-triangle" color="error" />
          تأكيد الإخفاء
        </VCardTitle>
        <VCardText>
          هل أنت متأكد من إخفاء "{{ deactivateTarget?.row?.name }}"؟
          لن تظهر بعدها ضمن الخيارات المتاحة، وستبقى بيانات المقاولين الحاليين المرتبطين بها دون تغيير.
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn variant="tonal" @click="deactivateDialog = false">إلغاء</VBtn>
          <VBtn color="error" :loading="deactivateMutation.isPending.value" @click="deactivateMutation.mutate()">إخفاء</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>

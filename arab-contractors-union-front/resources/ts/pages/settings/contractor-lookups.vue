<script setup lang="ts">
import { computed, ref } from 'vue'
import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query'
import api from '@/plugins/axios'

definePage({ meta: { requiresAdmin: true, adminOnly: true } })

const queryClient = useQueryClient()
const tab = ref('fields')

// ─── المجالات ────────────────────────────────────────────────────────────
const { data: fieldsData, isLoading: fieldsLoading } = useQuery({
  queryKey: ['contractor-fields'],
  queryFn: async () => (await api.get('/api/v1/dashboard/contractor-fields')).data,
})
const fields = computed(() => fieldsData.value?.items ?? [])

// ─── الاختصاصات ──────────────────────────────────────────────────────────
const { data: specsData, isLoading: specsLoading } = useQuery({
  queryKey: ['contractor-specializations'],
  queryFn: async () => (await api.get('/api/v1/dashboard/contractor-specializations')).data,
})
const specializations = computed(() => specsData.value?.items ?? [])

// ─── الدرجات ──────────────────────────────────────────────────────────────
const { data: gradesData, isLoading: gradesLoading } = useQuery({
  queryKey: ['contractor-grades'],
  queryFn: async () => (await api.get('/api/v1/dashboard/contractor-grades')).data,
})
const grades = computed(() => gradesData.value?.items ?? [])

// ─── فورم عام (يُعاد استخدامه للأنواع الثلاثة) ─────────────────────────────
type LookupKind = 'field' | 'specialization' | 'grade'

const kindConfig: Record<LookupKind, { endpoint: string, queryKey: string, label: string }> = {
  field:          { endpoint: 'contractor-fields',          queryKey: 'contractor-fields',          label: 'مجال' },
  specialization: { endpoint: 'contractor-specializations', queryKey: 'contractor-specializations', label: 'اختصاص' },
  grade:          { endpoint: 'contractor-grades',          queryKey: 'contractor-grades',          label: 'درجة' },
}

const formOpen = ref(false)
const formKind = ref<LookupKind>('field')
const formEditingId = ref<number | null>(null)
const formError = ref('')
const form = ref<Record<string, any>>({})

const openCreate = (kind: LookupKind) => {
  formKind.value = kind
  formEditingId.value = null
  formError.value = ''
  form.value = kind === 'grade'
    ? { code: '', label: '', level: 1, eligible_field_codes: null }
    : kind === 'specialization'
      ? { code: null, name: '', contractor_field_id: null }
      : { code: null, name: '' }
  formOpen.value = true
}

const openEdit = (kind: LookupKind, row: any) => {
  formKind.value = kind
  formEditingId.value = row.id
  formError.value = ''
  form.value = kind === 'grade'
    ? { label: row.label, level: row.level, eligible_field_codes: row.eligible_field_codes }
    : kind === 'specialization'
      ? { name: row.name, contractor_field_id: row.contractor_field_id }
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
    queryClient.invalidateQueries({ queryKey: [kindConfig[formKind.value].queryKey] })
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
    queryClient.invalidateQueries({ queryKey: [kindConfig[deactivateTarget.value!.kind].queryKey] })
    deactivateDialog.value = false
  },
})

const reactivateMutation = useMutation({
  mutationFn: async ({ kind, row }: { kind: LookupKind, row: any }) =>
    api.patch(`/api/v1/dashboard/${kindConfig[kind].endpoint}/${row.id}`, { is_active: true }),
  onSuccess: (_data, { kind }) => {
    queryClient.invalidateQueries({ queryKey: [kindConfig[kind].queryKey] })
  },
})
</script>

<template>
  <div>
    <div class="mb-6">
      <h1 class="text-h4 font-weight-bold">إدارة المجالات والاختصاصات والدرجات</h1>
      <p class="text-body-2 text-medium-emphasis mb-0">
        هذه القوائم يستخدمها فورم إضافة/تعديل المقاول ومحرّك احتساب رسوم العضوية وتوليد الشهادات مباشرة —
        "الحذف" هنا يُخفي العنصر عن خيارات المقاولين الجدد فقط ولا يمسه فعلياً حتى لا تتأثر بيانات المقاولين
        الحاليين أو الرسوم المحسوبة سابقاً.
      </p>
    </div>

    <VTabs v-model="tab" class="mb-4">
      <VTab value="fields">المجالات</VTab>
      <VTab value="specializations">الاختصاصات</VTab>
      <VTab value="grades">الدرجات</VTab>
    </VTabs>

    <!-- المجالات -->
    <VCard v-if="tab === 'fields'">
      <VCardTitle class="d-flex justify-space-between align-center">
        <span>المجالات</span>
        <VBtn size="small" color="primary" prepend-icon="tabler-plus" @click="openCreate('field')">إضافة مجال</VBtn>
      </VCardTitle>
      <VProgressLinear v-if="fieldsLoading" indeterminate color="primary" />
      <VTable v-else>
        <thead>
          <tr>
            <th>الكود</th>
            <th>الاسم</th>
            <th>الحالة</th>
            <th class="text-center">إجراءات</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="row in fields" :key="row.id">
            <td>{{ row.code }}</td>
            <td>{{ row.name }}</td>
            <td>
              <VChip size="small" :color="row.is_active ? 'success' : 'default'" variant="tonal">
                {{ row.is_active ? 'مفعّل' : 'مخفي' }}
              </VChip>
            </td>
            <td class="text-center">
              <VBtn icon="tabler-edit" size="x-small" variant="text" @click="openEdit('field', row)" />
              <VBtn
                v-if="row.is_active"
                icon="tabler-trash" size="x-small" variant="text" color="error"
                @click="confirmDeactivate('field', row)"
              />
              <VBtn
                v-else
                icon="tabler-refresh" size="x-small" variant="text" color="success"
                title="إعادة تفعيل"
                @click="reactivateMutation.mutate({ kind: 'field', row })"
              />
            </td>
          </tr>
        </tbody>
      </VTable>
    </VCard>

    <!-- الاختصاصات -->
    <VCard v-if="tab === 'specializations'">
      <VCardTitle class="d-flex justify-space-between align-center">
        <span>الاختصاصات</span>
        <VBtn size="small" color="primary" prepend-icon="tabler-plus" @click="openCreate('specialization')">إضافة اختصاص</VBtn>
      </VCardTitle>
      <VProgressLinear v-if="specsLoading" indeterminate color="primary" />
      <VTable v-else>
        <thead>
          <tr>
            <th>الكود</th>
            <th>الاسم</th>
            <th>المجال</th>
            <th>الحالة</th>
            <th class="text-center">إجراءات</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="row in specializations" :key="row.id">
            <td>{{ row.code }}</td>
            <td>{{ row.name }}</td>
            <td>{{ row.field?.name ?? '—' }}</td>
            <td>
              <VChip size="small" :color="row.is_active ? 'success' : 'default'" variant="tonal">
                {{ row.is_active ? 'مفعّل' : 'مخفي' }}
              </VChip>
            </td>
            <td class="text-center">
              <VBtn icon="tabler-edit" size="x-small" variant="text" @click="openEdit('specialization', row)" />
              <VBtn
                v-if="row.is_active"
                icon="tabler-trash" size="x-small" variant="text" color="error"
                @click="confirmDeactivate('specialization', row)"
              />
              <VBtn
                v-else
                icon="tabler-refresh" size="x-small" variant="text" color="success"
                title="إعادة تفعيل"
                @click="reactivateMutation.mutate({ kind: 'specialization', row })"
              />
            </td>
          </tr>
        </tbody>
      </VTable>
    </VCard>

    <!-- الدرجات -->
    <VCard v-if="tab === 'grades'">
      <VCardTitle class="d-flex justify-space-between align-center">
        <span>الدرجات</span>
        <VBtn size="small" color="primary" prepend-icon="tabler-plus" @click="openCreate('grade')">إضافة درجة</VBtn>
      </VCardTitle>
      <VProgressLinear v-if="gradesLoading" indeterminate color="primary" />
      <VTable v-else>
        <thead>
          <tr>
            <th>الكود</th>
            <th>المسمّى</th>
            <th>المستوى</th>
            <th>المجالات المؤهَّلة</th>
            <th>الحالة</th>
            <th class="text-center">إجراءات</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="row in grades" :key="row.id">
            <td>{{ row.code }}</td>
            <td>{{ row.label }}</td>
            <td>{{ row.level }}</td>
            <td>{{ row.eligible_field_codes ? row.eligible_field_codes.join(', ') : 'كل المجالات' }}</td>
            <td>
              <VChip size="small" :color="row.is_active ? 'success' : 'default'" variant="tonal">
                {{ row.is_active ? 'مفعّل' : 'مخفي' }}
              </VChip>
            </td>
            <td class="text-center">
              <VBtn icon="tabler-edit" size="x-small" variant="text" @click="openEdit('grade', row)" />
              <VBtn
                v-if="row.is_active"
                icon="tabler-trash" size="x-small" variant="text" color="error"
                @click="confirmDeactivate('grade', row)"
              />
              <VBtn
                v-else
                icon="tabler-refresh" size="x-small" variant="text" color="success"
                title="إعادة تفعيل"
                @click="reactivateMutation.mutate({ kind: 'grade', row })"
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
            <template v-if="formKind === 'field'">
              <VCol v-if="!formEditingId" cols="12">
                <VTextField v-model.number="form.code" label="الكود الرقمي (يُستخدم بالكامل بالنظام)" type="number" />
              </VCol>
              <VCol cols="12">
                <VTextField v-model="form.name" label="اسم المجال" />
              </VCol>
            </template>

            <template v-else-if="formKind === 'specialization'">
              <VCol v-if="!formEditingId" cols="12">
                <VTextField v-model.number="form.code" label="الكود الرقمي (يُستخدم بالكامل بالنظام)" type="number" />
              </VCol>
              <VCol cols="12">
                <VTextField v-model="form.name" label="اسم الاختصاص" />
              </VCol>
              <VCol cols="12">
                <VSelect
                  v-model="form.contractor_field_id"
                  :items="fields"
                  item-title="name"
                  item-value="id"
                  label="المجال التابع له"
                  clearable
                />
              </VCol>
            </template>

            <template v-else>
              <VCol v-if="!formEditingId" cols="12">
                <VTextField v-model="form.code" label="الكود (القيمة المخزَّنة فعلياً، مثال: اولى أ)" />
              </VCol>
              <VCol cols="12">
                <VTextField v-model="form.label" label="المسمّى المعروض" />
              </VCol>
              <VCol cols="12" md="6">
                <VTextField v-model.number="form.level" label="المستوى (الأقل = الأعلى درجة)" type="number" min="1" />
              </VCol>
              <VCol cols="12" md="6">
                <VSelect
                  v-model="form.eligible_field_codes"
                  :items="fields.map((f: any) => ({ title: f.name, value: f.code }))"
                  label="مقصورة على مجالات (اتركها فارغة = كل المجالات)"
                  multiple
                  clearable
                />
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
          هل أنت متأكد من إخفاء "{{ deactivateTarget?.row?.name ?? deactivateTarget?.row?.label }}"؟
          لن يظهر بعدها ضمن خيارات المقاولين الجدد، وستبقى بيانات المقاولين الحاليين الذين يستخدمونه دون تغيير.
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

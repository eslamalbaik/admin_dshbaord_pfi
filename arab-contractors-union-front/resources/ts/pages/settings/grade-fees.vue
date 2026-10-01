<script setup lang="ts">
import { ref, computed } from 'vue'
import { useQuery, useMutation, useQueryClient } from '@tanstack/vue-query'
import api from '@/plugins/axios'

definePage({ meta: { requiresAdmin: true } })

const queryClient = useQueryClient()

const { data, isLoading } = useQuery({
  queryKey: ['grade-fees'],
  queryFn: async () => (await api.get('/api/v1/dashboard/grade-fees')).data,
})

const rows = computed(() => data.value?.items ?? [])

const isFormOpen = ref(false)
const editingId = ref<number | null>(null)
const form = ref({ grade_label: '', registration_fee_jod: 0, annual_fee_jod: 0 })
const formError = ref('')

const openEdit = (row: any) => {
  editingId.value = row.id
  form.value = {
    grade_label: row.grade_label,
    registration_fee_jod: Number(row.registration_fee_jod),
    annual_fee_jod: Number(row.annual_fee_jod),
  }
  formError.value = ''
  isFormOpen.value = true
}

const saveMutation = useMutation({
  mutationFn: async () => (await api.put(`/api/v1/dashboard/grade-fees/${editingId.value}`, form.value)).data,
  onSuccess: () => {
    queryClient.invalidateQueries({ queryKey: ['grade-fees'] })
    isFormOpen.value = false
  },
  onError: (e: any) => {
    formError.value = e?.response?.data?.message || 'فشل حفظ رسوم الدرجة.'
  },
})
</script>

<template>
  <div>
    <div class="d-flex justify-space-between align-center mb-6 flex-wrap gap-4">
      <div>
        <h1 class="text-h4 font-weight-bold">جدول رسوم الدرجات</h1>
        <p class="text-body-2 text-medium-emphasis mb-0">
          رسوم التسجيل والاشتراك السنوي لكل درجة تصنيف (المادة 37) — يعتمدها محرّك احتساب رسوم العضوية
        </p>
      </div>
    </div>

    <VAlert type="info" variant="tonal" class="mb-4">
      <div class="d-flex flex-column gap-1">
        <div><strong>رسوم التسجيل:</strong> تُطبَّق مرّة واحدة فقط على المجال الأعلى، في أول سنة انتساب للمقاول (بدل الرسم السنوي لذلك المجال).</div>
        <div><strong>الاشتراك السنوي:</strong> يُطبَّق كل سنة (تجديد) — 100% على المجال الأعلى بين مجالات المقاول، و50% على باقي مجالاته.</div>
      </div>
    </VAlert>

    <VProgressLinear v-if="isLoading" indeterminate color="primary" />

    <VCard v-else>
      <VTable>
        <thead>
          <tr>
            <th>الدرجة</th>
            <th>
              رسوم التسجيل (د.أ)
              <VTooltip activator="parent" location="top">أول سنة انتساب فقط — بدل الرسم السنوي على المجال الأعلى</VTooltip>
            </th>
            <th>
              الاشتراك السنوي (د.أ)
              <VTooltip activator="parent" location="top">كل سنة تجديد — 100% للمجال الأعلى، 50% للباقي</VTooltip>
            </th>
            <th class="text-center">إجراءات</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="row in rows" :key="row.id">
            <td class="font-weight-medium">{{ row.grade_label }}</td>
            <td>{{ row.registration_fee_jod }}</td>
            <td>{{ row.annual_fee_jod }}</td>
            <td class="text-center">
              <VBtn icon="tabler-edit" size="x-small" variant="text" @click="openEdit(row)" />
            </td>
          </tr>
        </tbody>
      </VTable>
    </VCard>

    <!-- Edit Dialog -->
    <VDialog v-model="isFormOpen" max-width="480" persistent>
      <VCard>
        <VCardTitle class="pt-4 pb-0">
          <span class="text-h6">تعديل رسوم الدرجة</span>
        </VCardTitle>

        <VCardText class="pt-4">
          <VAlert v-if="formError" type="error" variant="tonal" class="mb-4">
            {{ formError }}
          </VAlert>

          <VRow dense>
            <VCol cols="12">
              <VTextField v-model="form.grade_label" label="مسمّى الدرجة" />
            </VCol>
            <VCol cols="12" md="6">
              <VTextField
                v-model.number="form.registration_fee_jod"
                label="رسوم التسجيل (د.أ)"
                type="number"
                min="0"
                hint="أول سنة انتساب فقط، على المجال الأعلى"
                persistent-hint
              />
            </VCol>
            <VCol cols="12" md="6">
              <VTextField
                v-model.number="form.annual_fee_jod"
                label="الاشتراك السنوي (د.أ)"
                type="number"
                min="0"
                hint="كل سنة تجديد — 100%/50%"
                persistent-hint
              />
            </VCol>
          </VRow>
        </VCardText>

        <VCardActions>
          <VSpacer />
          <VBtn variant="text" @click="isFormOpen = false">إلغاء</VBtn>
          <VBtn color="primary" :loading="saveMutation.isPending.value" @click="saveMutation.mutate()">
            حفظ
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>

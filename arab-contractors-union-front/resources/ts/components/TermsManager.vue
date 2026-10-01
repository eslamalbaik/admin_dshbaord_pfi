<script setup lang="ts">
import { ref, computed } from 'vue'
import { useQuery, useMutation, useQueryClient } from '@tanstack/vue-query'
import api from '@/plugins/axios'

const props = defineProps<{
  /** نوع النص المُدار: شروط وأحكام أو سياسة خصوصية */
  type: 'terms' | 'privacy'
  title: string
  subtitle: string
}>()

const queryClient = useQueryClient()

const { data, isLoading } = useQuery({
  queryKey: ['terms', props.type],
  queryFn: async () => (await api.get('/api/v1/dashboard/terms', { params: { type: props.type } })).data,
})
const terms = computed(() => data.value ?? [])

const invalidate = () => queryClient.invalidateQueries({ queryKey: ['terms', props.type] })

const emptyForm = () => ({
  type: props.type,
  title: '',
  body: '',
  sort: 0,
  is_active: true,
})

const isFormOpen = ref(false)
const isEditing = ref(false)
const editingId = ref<number | null>(null)
const form = ref<any>(emptyForm())
const formError = ref('')

const openCreate = () => {
  isEditing.value = false
  editingId.value = null
  form.value = emptyForm()
  form.value.sort = terms.value.length
  formError.value = ''
  isFormOpen.value = true
}

const openEdit = (t: any) => {
  isEditing.value = true
  editingId.value = t.id
  form.value = { ...t, type: props.type }
  formError.value = ''
  isFormOpen.value = true
}

const saveMutation = useMutation({
  mutationFn: async () => {
    if (isEditing.value)
      return (await api.put(`/api/v1/dashboard/terms/${editingId.value}`, form.value)).data

    return (await api.post('/api/v1/dashboard/terms', form.value)).data
  },
  onSuccess: () => {
    invalidate()
    isFormOpen.value = false
  },
  onError: (e: any) => {
    formError.value = e?.response?.data?.message || 'فشل حفظ البند.'
  },
})

const deleteMutation = useMutation({
  mutationFn: async (id: number) => (await api.delete(`/api/v1/dashboard/terms/${id}`)).data,
  onSuccess: () => invalidate(),
})

const confirmDelete = (t: any) => {
  if (confirm('هل تريد حذف هذا البند؟'))
    deleteMutation.mutate(t.id)
}

// إعادة الترتيب عبر تبديل مع الجار المباشر بالقائمة (REQ-13) — بدل إدخال رقم ترتيب يدوي
// عرضة لثغرات/تداخل، دايماً موضع مجاور موجود فعلاً فتبقى القائمة متسلسلة بلا فجوات
const reorderMutation = useMutation({
  mutationFn: async ({ item, newSort }: { item: any, newSort: number }) =>
    (await api.put(`/api/v1/dashboard/terms/${item.id}`, { ...item, sort: newSort })).data,
  onSuccess: () => invalidate(),
})

const moveUp = (index: number) => {
  if (index === 0 || reorderMutation.isPending.value) return
  const current = terms.value[index]
  const prev = terms.value[index - 1]
  reorderMutation.mutate({ item: current, newSort: prev.sort })
}

const moveDown = (index: number) => {
  if (index === terms.value.length - 1 || reorderMutation.isPending.value) return
  const current = terms.value[index]
  const next = terms.value[index + 1]
  reorderMutation.mutate({ item: current, newSort: next.sort })
}
</script>

<template>
  <div>
    <!-- Header -->
    <div class="d-flex justify-space-between align-center mb-6 flex-wrap gap-4">
      <div>
        <h1 class="text-h4 font-weight-bold">{{ props.title }}</h1>
        <p class="text-body-2 text-medium-emphasis mb-0">
          {{ props.subtitle }}
        </p>
      </div>
      <VBtn color="primary" prepend-icon="tabler-plus" @click="openCreate">
        إضافة بند جديد
      </VBtn>
    </div>

    <!-- Loading -->
    <VProgressLinear v-if="isLoading" indeterminate color="primary" />

    <!-- Empty -->
    <VCard v-else-if="terms.length === 0" class="text-center py-12">
      <VIcon icon="tabler-file-text" size="64" color="disabled" class="mb-3" />
      <p class="text-h6 text-medium-emphasis">لا توجد بنود بعد.</p>
      <VBtn class="mt-4" color="primary" prepend-icon="tabler-plus" @click="openCreate">
        أضف أول بند
      </VBtn>
    </VCard>

    <!-- Terms List -->
    <VExpansionPanels v-else>
      <VExpansionPanel v-for="(t, index) in terms" :key="t.id">
        <VExpansionPanelTitle>
          <div class="d-flex align-center justify-space-between w-100 pe-3">
            <div class="d-flex align-center gap-3">
              <div class="d-flex flex-column" @click.stop>
                <VBtn
                  icon="tabler-chevron-up"
                  size="x-small"
                  variant="text"
                  density="compact"
                  :disabled="index === 0"
                  :loading="reorderMutation.isPending.value"
                  @click="moveUp(index)"
                />
                <VBtn
                  icon="tabler-chevron-down"
                  size="x-small"
                  variant="text"
                  density="compact"
                  :disabled="index === terms.length - 1"
                  :loading="reorderMutation.isPending.value"
                  @click="moveDown(index)"
                />
              </div>
              <VChip size="x-small" color="secondary" variant="tonal">
                {{ t.sort }}
              </VChip>
              <span class="font-weight-medium">{{ t.title }}</span>
            </div>
            <VChip size="x-small" :color="t.is_active ? 'success' : 'secondary'">
              {{ t.is_active ? 'نشط' : 'مخفي' }}
            </VChip>
          </div>
        </VExpansionPanelTitle>
        <VExpansionPanelText>
          <VCard variant="tonal" color="secondary" class="mb-3 pa-3 rounded-lg">
            <div class="text-body-2 term-body-preview" v-html="t.body" />
          </VCard>
          <div class="d-flex gap-2 mt-2">
            <VBtn size="small" prepend-icon="tabler-edit" @click="openEdit(t)">
              تعديل
            </VBtn>
            <VBtn
              size="small"
              color="error"
              variant="tonal"
              prepend-icon="tabler-trash"
              :loading="deleteMutation.isPending.value"
              @click="confirmDelete(t)"
            >
              حذف
            </VBtn>
          </div>
        </VExpansionPanelText>
      </VExpansionPanel>
    </VExpansionPanels>

    <!-- Form Dialog -->
    <VDialog v-model="isFormOpen" max-width="760" persistent scrollable>
      <VCard>
        <VCardTitle class="pt-4 pb-0">
          <span class="text-h6">
            {{ isEditing ? 'تعديل البند' : 'إضافة بند جديد' }}
          </span>
        </VCardTitle>

        <VCardText class="pt-4">
          <VAlert v-if="formError" type="error" variant="tonal" class="mb-4">
            {{ formError }}
          </VAlert>

          <!-- Sort + Active -->
          <div class="d-flex gap-4 mb-4">
            <VTextField
              v-model.number="form.sort"
              label="الترتيب"
              type="number"
              min="0"
              style="max-width: 120px"
              density="compact"
            />
            <VSwitch v-model="form.is_active" label="نشط" color="success" />
          </div>

          <VTextField
            v-model="form.title"
            label="العنوان *"
            class="mb-4"
            dir="rtl"
          />
          <p class="text-body-2 font-weight-medium mb-1">نص البند *</p>
          <TiptapEditor v-model="form.body" placeholder="اكتب نص البند هنا..." class="border rounded" />
        </VCardText>

        <VCardActions>
          <VSpacer />
          <VBtn variant="text" @click="isFormOpen = false">إلغاء</VBtn>
          <VBtn
            color="primary"
            :loading="saveMutation.isPending.value"
            :disabled="!form.title || !form.body"
            @click="saveMutation.mutate()"
          >
            حفظ
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>

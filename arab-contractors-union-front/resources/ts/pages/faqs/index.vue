<script setup lang="ts">
import { ref, computed } from 'vue'
import { useQuery, useMutation, useQueryClient } from '@tanstack/vue-query'
import api from '@/plugins/axios'

definePage({ meta: { requiresAdmin: true } })

const queryClient = useQueryClient()

const { data, isLoading } = useQuery({
  queryKey: ['faqs'],
  queryFn: async () => (await api.get('/api/dashboard/faqs')).data,
})
const faqs = computed(() => data.value ?? [])

const emptyForm = () => ({ question: '', question_en: '', answer: '', answer_en: '', is_active: true, sort: 0 })
const isFormOpen = ref(false)
const isEditing = ref(false)
const editingId = ref<number | null>(null)
const form = ref<any>(emptyForm())
const formError = ref('')

const openCreate = () => { isEditing.value = false; editingId.value = null; form.value = emptyForm(); form.value.sort = faqs.value.length; formError.value = ''; isFormOpen.value = true }
const openEdit = (f: any) => { isEditing.value = true; editingId.value = f.id; form.value = { ...f }; formError.value = ''; isFormOpen.value = true }

const saveMutation = useMutation({
  mutationFn: async () => {
    if (isEditing.value)
      return (await api.put(`/api/dashboard/faqs/${editingId.value}`, form.value)).data
    return (await api.post('/api/dashboard/faqs', form.value)).data
  },
  onSuccess: () => { queryClient.invalidateQueries({ queryKey: ['faqs'] }); isFormOpen.value = false },
  onError: (e: any) => { formError.value = e?.response?.data?.message || 'Failed to save FAQ.' },
})
const deleteMutation = useMutation({
  mutationFn: async (id: number) => (await api.delete(`/api/dashboard/faqs/${id}`)).data,
  onSuccess: () => queryClient.invalidateQueries({ queryKey: ['faqs'] }),
})
const confirmDelete = (f: any) => { if (confirm('Delete this FAQ?')) deleteMutation.mutate(f.id) }
</script>

<template>
  <div>
    <div class="d-flex justify-space-between align-center mb-6 flex-wrap gap-4">
      <div>
        <h1 class="text-h4 font-weight-bold">{{ $t('FAQ') }}</h1>
        <p class="text-body-2 text-medium-emphasis mb-0">{{ $t('Questions shown on the landing page') }}</p>
      </div>
      <VBtn color="primary" prepend-icon="tabler-plus" @click="openCreate">{{ $t('New Question') }}</VBtn>
    </div>

    <VProgressLinear v-if="isLoading" indeterminate color="primary" />

    <VCard v-else-if="faqs.length === 0" class="text-center py-12">
      <VIcon icon="tabler-help-circle" size="64" color="disabled" class="mb-3" />
      <p class="text-h6 text-medium-emphasis">{{ $t('No FAQs yet.') }}</p>
    </VCard>

    <VExpansionPanels v-else>
      <VExpansionPanel v-for="f in faqs" :key="f.id">
        <VExpansionPanelTitle>
          <div class="d-flex align-center justify-space-between w-100 pe-3">
            <span class="font-weight-medium">{{ f.question }}</span>
            <div class="d-flex align-center gap-2">
              <VChip size="x-small" :color="f.is_active ? 'success' : 'secondary'">{{ f.is_active ? $t('Active') : $t('Hidden') }}</VChip>
            </div>
          </div>
        </VExpansionPanelTitle>
        <VExpansionPanelText>
          <p class="text-body-2 mb-3">{{ f.answer }}</p>
          <div class="d-flex gap-2">
            <VBtn size="small" prepend-icon="tabler-edit" @click="openEdit(f)">{{ $t('Edit') }}</VBtn>
            <VBtn size="small" color="error" variant="tonal" prepend-icon="tabler-trash" @click="confirmDelete(f)">{{ $t('Delete') }}</VBtn>
          </div>
        </VExpansionPanelText>
      </VExpansionPanel>
    </VExpansionPanels>

    <VDialog v-model="isFormOpen" max-width="640" persistent>
      <VCard>
        <VCardTitle class="pt-4">{{ isEditing ? $t('Edit Question') : $t('New Question') }}</VCardTitle>
        <VCardText>
          <VAlert v-if="formError" type="error" variant="tonal" class="mb-4">{{ formError }}</VAlert>
          <VTextField v-model="form.question" :label="$t('Question (Arabic)')" class="mb-3" />
          <VTextField v-model="form.question_en" :label="$t('Question (English)')" class="mb-3" />
          <VTextarea v-model="form.answer" :label="$t('Answer (Arabic)')" rows="3" class="mb-3" />
          <VTextarea v-model="form.answer_en" :label="$t('Answer (English)')" rows="3" class="mb-3" />
          <div class="d-flex gap-4">
            <VTextField v-model.number="form.sort" :label="$t('Sort')" type="number" min="0" style="max-width: 140px" />
            <VSwitch v-model="form.is_active" :label="$t('Active')" color="success" />
          </div>
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn variant="text" @click="isFormOpen = false">{{ $t('Cancel') }}</VBtn>
          <VBtn color="primary" :loading="saveMutation.isPending.value" :disabled="!form.question || !form.answer" @click="saveMutation.mutate()">{{ $t('Save') }}</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>

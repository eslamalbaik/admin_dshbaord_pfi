<script setup lang="ts">
import { ref } from 'vue'
import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query'
import api from '@/plugins/axios'

definePage({ meta: { requiresAdmin: true, adminOnly: true } })

// لازم يطابق Tender::CATEGORIES بالباك اند
const categories = ['مباني', 'طرق', 'بنية تحتية', 'قطاع صحي', 'قطاع تعليمي', 'عام']

const queryClient = useQueryClient()

const { data, isLoading } = useQuery({
  queryKey: ['tender-category-images'],
  queryFn: async () => (await api.get('/api/v1/tenders/category-images')).data,
})

const imageFor = (category: string): string | null => (data.value?.items ?? {})[category] ?? null

const errorMsg = ref('')
const successMsg = ref('')
const uploadingCategory = ref<string | null>(null)
const deletingCategory = ref<string | null>(null)

const uploadMutation = useMutation({
  mutationFn: async ({ category, file }: { category: string, file: File }) => {
    const fd = new FormData()
    fd.append('category', category)
    fd.append('image', file)

    return (await api.post('/api/v1/tenders/category-images', fd)).data
  },
  onSuccess: () => {
    successMsg.value = 'تم حفظ الصورة بنجاح.'
    errorMsg.value = ''
    queryClient.invalidateQueries({ queryKey: ['tender-category-images'] })
  },
  onError: (e: any) => {
    errorMsg.value = e?.response?.data?.message || 'تعذّر رفع الصورة.'
  },
  onSettled: () => { uploadingCategory.value = null },
})

const onFilePicked = (category: string, file: File | File[] | null) => {
  const f = Array.isArray(file) ? file[0] : file
  if (!f)
    return
  uploadingCategory.value = category
  uploadMutation.mutate({ category, file: f })
}

const deleteMutation = useMutation({
  mutationFn: async (category: string) => api.delete(`/api/v1/tenders/category-images/${encodeURIComponent(category)}`),
  onSuccess: () => {
    successMsg.value = 'تمت إزالة الصورة.'
    errorMsg.value = ''
    queryClient.invalidateQueries({ queryKey: ['tender-category-images'] })
  },
  onError: () => {
    errorMsg.value = 'تعذّر إزالة الصورة.'
  },
  onSettled: () => { deletingCategory.value = null },
})

const removeImage = (category: string) => {
  deletingCategory.value = category
  deleteMutation.mutate(category)
}
</script>

<template>
  <div>
    <h4 class="text-h4 mb-2" style="font-family:Cairo,sans-serif">صور تصنيفات العطاءات</h4>
    <p class="text-body-2 text-medium-emphasis mb-6" style="font-family:Cairo,sans-serif">
      صورة افتراضية واحدة لكل تصنيف — تُعرض بدلاً من صورة العطاء (العطاءات لا تحمل صوراً خاصة بها).
    </p>

    <VAlert v-if="successMsg" type="success" variant="tonal" class="mb-4" closable @click:close="successMsg = ''">
      {{ successMsg }}
    </VAlert>
    <VAlert v-if="errorMsg" type="error" variant="tonal" class="mb-4" closable @click:close="errorMsg = ''">
      {{ errorMsg }}
    </VAlert>

    <VRow v-if="!isLoading">
      <VCol v-for="category in categories" :key="category" cols="12" sm="6" md="4">
        <VCard>
          <VImg v-if="imageFor(category)" :src="imageFor(category)!" height="140" cover />
          <div v-else class="d-flex align-center justify-center" style="height:140px;background:rgba(var(--v-theme-on-surface),0.04)">
            <VIcon icon="tabler-photo-off" size="32" color="secondary" />
          </div>
          <VCardText>
            <div class="font-weight-medium mb-3" style="font-family:Cairo,sans-serif">{{ category }}</div>
            <div class="d-flex align-center gap-2">
              <VFileInput
                :model-value="null"
                accept="image/jpeg,image/png,image/webp"
                density="compact"
                hide-details
                prepend-icon=""
                prepend-inner-icon="tabler-upload"
                :loading="uploadingCategory === category"
                style="flex:1"
                @update:model-value="onFilePicked(category, $event as any)"
              />
              <VBtn
                v-if="imageFor(category)"
                icon
                size="small"
                variant="text"
                color="error"
                :loading="deletingCategory === category"
                @click="removeImage(category)"
              >
                <VIcon icon="tabler-trash" size="18" />
                <VTooltip activator="parent">إزالة الصورة</VTooltip>
              </VBtn>
            </div>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <div v-else class="d-flex justify-center pa-10">
      <VProgressCircular indeterminate color="primary" />
    </div>
  </div>
</template>

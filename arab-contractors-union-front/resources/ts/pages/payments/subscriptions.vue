<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { useQuery, useMutation, useQueryClient } from '@tanstack/vue-query'
import api from '@/plugins/axios'

definePage({ meta: { requiresAdmin: true } })

const queryClient = useQueryClient()

// ─── Courses (for the selector) ───────────────────────────────────────────────
const { data: coursesData } = useQuery({
  queryKey: ['admin-courses-for-packages'],
  queryFn: async () => {
    const res = await api.get('/api/dashboard/courses')
    return res.data?.data ?? res.data ?? []
  },
})
const courses = computed(() => coursesData.value ?? [])
const selectedCourseId = ref<number | null>(null)

watch(courses, list => {
  if (!selectedCourseId.value && list.length)
    selectedCourseId.value = list[0].id
})

// ─── Packages for the selected course ─────────────────────────────────────────
const { data: packagesData, isLoading, refetch } = useQuery({
  queryKey: ['course-packages', selectedCourseId],
  queryFn: async () => {
    if (!selectedCourseId.value)
      return []
    return (await api.get(`/api/dashboard/courses/${selectedCourseId.value}/packages`)).data
  },
  enabled: computed(() => selectedCourseId.value !== null),
})
const packages = computed(() => packagesData.value ?? [])

// ─── Create / Edit dialog ─────────────────────────────────────────────────────
const isFormOpen = ref(false)
const isEditing = ref(false)
const editingId = ref<number | null>(null)
const formError = ref('')
const form = ref({
  name: '',
  price: 0,
  sort: 0,
  entitlements: { has_quizzes: false, has_files: false, has_certificate: false },
})

const openCreate = () => {
  isEditing.value = false
  editingId.value = null
  formError.value = ''
  form.value = { name: '', price: 0, sort: packages.value.length, entitlements: { has_quizzes: false, has_files: false, has_certificate: false } }
  isFormOpen.value = true
}

const openEdit = (p: any) => {
  isEditing.value = true
  editingId.value = p.id
  formError.value = ''
  form.value = {
    name: p.name,
    price: Number(p.price),
    sort: p.sort ?? 0,
    entitlements: {
      has_quizzes: !!p.entitlements?.has_quizzes,
      has_files: !!p.entitlements?.has_files,
      has_certificate: !!p.entitlements?.has_certificate,
    },
  }
  isFormOpen.value = true
}

const saveMutation = useMutation({
  mutationFn: async () => {
    const payload = { ...form.value }
    if (isEditing.value)
      return (await api.put(`/api/dashboard/course-packages/${editingId.value}`, payload)).data
    return (await api.post(`/api/dashboard/courses/${selectedCourseId.value}/packages`, payload)).data
  },
  onSuccess: () => {
    queryClient.invalidateQueries({ queryKey: ['course-packages', selectedCourseId] })
    refetch()
    isFormOpen.value = false
  },
  onError: (e: any) => {
    formError.value = e?.response?.data?.message || 'Failed to save package.'
  },
})

const deleteMutation = useMutation({
  mutationFn: async (id: number) => (await api.delete(`/api/dashboard/course-packages/${id}`)).data,
  onSuccess: () => { refetch() },
})
const confirmDelete = (p: any) => {
  if (confirm(`Delete the "${p.name}" package?`))
    deleteMutation.mutate(p.id)
}

const featureList = [
  { key: 'has_quizzes', label: 'Quizzes & Exams' },
  { key: 'has_files', label: 'Downloadable Files' },
  { key: 'has_certificate', label: 'Certificate' },
] as const
</script>

<template>
  <div>
    <div class="d-flex justify-space-between align-center mb-6 flex-wrap gap-4">
      <div>
        <h1 class="text-h4 font-weight-bold">{{ $t('Course Packages') }}</h1>
        <p class="text-body-2 text-medium-emphasis mb-0">{{ $t('Tiered pricing & feature access per course') }}</p>
      </div>
      <VBtn color="primary" prepend-icon="tabler-plus" :disabled="!selectedCourseId" @click="openCreate">
        {{ $t('New Package') }}
      </VBtn>
    </div>

    <!-- Course selector -->
    <VCard class="mb-6">
      <VCardText>
        <VSelect
          v-model="selectedCourseId"
          :items="courses"
          item-title="title"
          item-value="id"
          :label="$t('Select Course')"
          prepend-inner-icon="tabler-book-2"
        />
      </VCardText>
    </VCard>

    <VRow v-if="isLoading">
      <VCol v-for="i in 3" :key="i" cols="12" md="4"><VSkeletonLoader type="article" /></VCol>
    </VRow>

    <VCard v-else-if="packages.length === 0" class="text-center py-12">
      <VIcon icon="tabler-stack-2" size="64" color="disabled" class="mb-3" />
      <p class="text-h6 text-medium-emphasis px-4">{{ $t('No packages for this course yet. Students get full access by default.') }}</p>
    </VCard>

    <VRow v-else>
      <VCol v-for="p in packages" :key="p.id" cols="12" md="4">
        <VCard border>
          <VCardItem>
            <VCardTitle class="d-flex align-center justify-space-between">
              <span>{{ p.name }}</span>
              <span class="text-primary font-weight-bold">${{ Number(p.price).toFixed(2) }}</span>
            </VCardTitle>
          </VCardItem>
          <VDivider />
          <VCardText>
            <div v-for="f in featureList" :key="f.key" class="d-flex align-center gap-2 mb-2">
              <VIcon
                :icon="p.entitlements?.[f.key] ? 'tabler-circle-check-filled' : 'tabler-circle-x'"
                :color="p.entitlements?.[f.key] ? 'success' : 'disabled'"
                size="20"
              />
              <span :class="p.entitlements?.[f.key] ? '' : 'text-disabled text-decoration-line-through'">
                {{ $t(f.label) }}
              </span>
            </div>
          </VCardText>
          <VDivider />
          <VCardActions>
            <VBtn size="small" prepend-icon="tabler-edit" @click="openEdit(p)">{{ $t('Edit') }}</VBtn>
            <VSpacer />
            <IconBtn color="error" @click="confirmDelete(p)"><VIcon icon="tabler-trash" /></IconBtn>
          </VCardActions>
        </VCard>
      </VCol>
    </VRow>

    <!-- Create / Edit dialog -->
    <VDialog v-model="isFormOpen" max-width="500" persistent>
      <VCard>
        <VCardTitle class="pt-4">{{ isEditing ? $t('Edit Package') : $t('New Package') }}</VCardTitle>
        <VCardText>
          <VAlert v-if="formError" type="error" variant="tonal" class="mb-4">{{ formError }}</VAlert>
          <VTextField v-model="form.name" :label="$t('Package Name')" placeholder="Gold" class="mb-3" />
          <VTextField v-model.number="form.price" :label="$t('Price (USD)')" type="number" min="0" step="0.01" class="mb-3" />
          <VTextField v-model.number="form.sort" :label="$t('Sort order')" type="number" min="0" class="mb-4" />

          <p class="text-subtitle-2 mb-2">{{ $t('Included features') }}</p>
          <VSwitch v-model="form.entitlements.has_quizzes" :label="$t('Quizzes & Exams')" color="success" density="compact" />
          <VSwitch v-model="form.entitlements.has_files" :label="$t('Downloadable Files')" color="success" density="compact" />
          <VSwitch v-model="form.entitlements.has_certificate" :label="$t('Certificate')" color="success" density="compact" />
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn variant="text" @click="isFormOpen = false">{{ $t('Cancel') }}</VBtn>
          <VBtn color="primary" :loading="saveMutation.isPending.value" :disabled="!form.name" @click="saveMutation.mutate()">
            {{ $t('Save') }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>

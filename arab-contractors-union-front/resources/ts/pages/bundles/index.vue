<script setup lang="ts">
import { ref, computed } from 'vue'
import { useQuery, useMutation, useQueryClient } from '@tanstack/vue-query'
import api from '@/plugins/axios'

definePage({ meta: { requiresAdmin: true } })

const queryClient = useQueryClient()

// ─── Data ─────────────────────────────────────────────────────────────────────
const { data: bundlesData, isLoading } = useQuery({
  queryKey: ['bundles'],
  queryFn: async () => (await api.get('/api/dashboard/bundles')).data,
})
const bundles = computed(() => bundlesData.value ?? [])

const { data: coursesData } = useQuery({
  queryKey: ['courses-list'],
  queryFn: async () => (await api.get('/api/dashboard/courses')).data,
})
const allCourses = computed(() =>
  (coursesData.value?.data ?? coursesData.value ?? []).map((c: any) => ({
    title: c.title || c.name || `Course ${c.id}`,
    value: c.id,
  }))
)

// ─── Form ─────────────────────────────────────────────────────────────────────
const emptyForm = () => ({
  // Basic info
  name: '', name_en: '',
  description: '', description_en: '',
  price: 0,
  is_active: true,
  sort: 0,
  // Pricing card display (same as PricingPlan)
  badge: '', badge_en: '',
  is_featured: false,
  color: '#4F46E5',
  cta_label: '', cta_label_en: '',
  period: '', period_en: '',
  features: [] as any[],
  // Bundle-specific
  access_days: null as number | null,
  type: 'fixed' as 'fixed' | 'flexible' | 'subscription',
  max_courses: null as number | null,
  auto_renewal: false,
  default_quiz_visibility: 'all' as 'all' | 'none' | 'selected',
  default_certificate_enabled: true,
  default_products_visibility: 'all' as 'all' | 'none' | 'selected',
  course_ids: [] as number[],
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
  form.value.sort = bundles.value.length
  formError.value = ''
  isFormOpen.value = true
}

const openEdit = (b: any) => {
  isEditing.value = true
  editingId.value = b.id
  form.value = {
    ...emptyForm(),
    ...b,
    price: Number(b.price),
    access_days: b.access_days ?? null,
    max_courses: b.max_courses ?? null,
    features: Array.isArray(b.features) ? JSON.parse(JSON.stringify(b.features)) : [],
    course_ids: (b.courses ?? []).map((c: any) => c.id),
  }
  formError.value = ''
  isFormOpen.value = true
}

// ─── Feature-group editor (same logic as pricing-plans) ───────────────────────
const addGroup  = () => form.value.features.push({ title: '', title_en: '', items: [] })
const removeGroup = (gi: number) => form.value.features.splice(gi, 1)
const addItem   = (gi: number) => form.value.features[gi].items.push({ text: '', text_en: '', included: true })
const removeItem  = (gi: number, ii: number) => form.value.features[gi].items.splice(ii, 1)

const featureCount = (b: any) =>
  (b.features || []).reduce((sum: number, g: any) => sum + (g.items?.length || 0), 0)

// ─── Save ─────────────────────────────────────────────────────────────────────
const saveMutation = useMutation({
  mutationFn: async () => {
    const payload = { ...form.value }
    if (isEditing.value)
      return (await api.put(`/api/dashboard/bundles/${editingId.value}`, payload)).data
    return (await api.post('/api/dashboard/bundles', payload)).data
  },
  onSuccess: () => {
    queryClient.invalidateQueries({ queryKey: ['bundles'] })
    isFormOpen.value = false
  },
  onError: (e: any) => {
    formError.value = e?.response?.data?.message || 'Failed to save bundle.'
  },
})

// ─── Delete ───────────────────────────────────────────────────────────────────
const deleteMutation = useMutation({
  mutationFn: async (id: number) => (await api.delete(`/api/dashboard/bundles/${id}`)).data,
  onSuccess: () => queryClient.invalidateQueries({ queryKey: ['bundles'] }),
})
const confirmDelete = (b: any) => {
  if (confirm(`Delete the "${b.name}" bundle?`))
    deleteMutation.mutate(b.id)
}
</script>

<template>
  <div>
    <!-- Header -->
    <div class="d-flex justify-space-between align-center mb-6 flex-wrap gap-4">
      <div>
        <h1 class="text-h4 font-weight-bold">{{ $t('Bundles') }}</h1>
        <p class="text-body-2 text-medium-emphasis mb-0">{{ $t('Multi-course packages shown to visitors') }}</p>
      </div>
      <VBtn color="primary" prepend-icon="tabler-plus" @click="openCreate">{{ $t('New Bundle') }}</VBtn>
    </div>

    <!-- Loading -->
    <VRow v-if="isLoading">
      <VCol v-for="i in 3" :key="i" cols="12" md="4"><VSkeletonLoader type="article" /></VCol>
    </VRow>

    <!-- Empty -->
    <VCard v-else-if="bundles.length === 0" class="text-center py-12">
      <VIcon icon="tabler-packages" size="64" color="disabled" class="mb-3" />
      <p class="text-h6 text-medium-emphasis">{{ $t('No bundles yet.') }}</p>
    </VCard>

    <!-- Cards -->
    <VRow v-else>
      <VCol v-for="b in bundles" :key="b.id" cols="12" md="4">
        <VCard
          :variant="b.is_featured ? 'flat' : 'outlined'"
          :style="b.is_featured
            ? { backgroundColor: b.color || '#4F46E5', color: '#ffffff', borderColor: b.color || '#4F46E5' }
            : {}"
        >
          <VCardItem>
            <VCardTitle class="d-flex align-center justify-space-between flex-wrap gap-2"
              :style="b.is_featured ? { color: '#ffffff' } : {}">
              <span>{{ b.name }}</span>
              <div class="d-flex gap-2">
                <VChip v-if="b.badge" size="small" color="amber" variant="flat" style="color:#0f172a">
                  {{ b.badge }}
                </VChip>
                <VChip
                  size="small"
                  :color="b.type === 'subscription' ? 'info' : 'secondary'"
                  variant="tonal"
                >
                  {{ b.type === 'flexible' ? $t('Flexible') : b.type === 'subscription' ? $t('Subscription') : $t('Fixed') }}
                </VChip>
              </div>
            </VCardTitle>
            <VCardSubtitle :style="b.is_featured ? { color: 'rgba(255,255,255,0.8)' } : {}">
              {{ Number(b.price) === 0 ? $t('Free') : Number(b.price).toLocaleString() + ' · ' + (b.period || '') }}
            </VCardSubtitle>
          </VCardItem>

          <VCardText>
            <div
              class="d-flex flex-wrap align-center gap-3 text-body-2"
              :style="b.is_featured ? { color: 'rgba(255,255,255,0.85)' } : {}"
              :class="b.is_featured ? '' : 'text-medium-emphasis'"
            >
              <span><VIcon icon="tabler-book-2" size="16" /> {{ (b.courses ?? []).length }} {{ $t('courses') }}</span>
              <span><VIcon icon="tabler-list-check" size="16" /> {{ featureCount(b) }} {{ $t('features') }}</span>
              <span>
                <VIcon :icon="b.is_active ? 'tabler-eye' : 'tabler-eye-off'" size="16" />
                {{ b.is_active ? $t('Active') : $t('Hidden') }}
              </span>
            </div>
          </VCardText>

          <VDivider :style="b.is_featured ? { borderColor: 'rgba(255,255,255,0.2)' } : {}" />
          <VCardActions>
            <VBtn
              size="small"
              prepend-icon="tabler-edit"
              :style="b.is_featured ? { color: '#ffffff' } : {}"
              @click="openEdit(b)"
            >{{ $t('Edit') }}</VBtn>
            <VSpacer />
            <IconBtn
              :color="b.is_featured ? undefined : 'error'"
              :style="b.is_featured ? { color: '#ffffff' } : {}"
              @click="confirmDelete(b)"
            >
              <VIcon icon="tabler-trash" />
            </IconBtn>
          </VCardActions>
        </VCard>
      </VCol>
    </VRow>

    <!-- Create / Edit dialog -->
    <VDialog v-model="isFormOpen" max-width="800" scrollable persistent>
      <VCard>
        <VCardTitle class="pt-4">{{ isEditing ? $t('Edit Bundle') : $t('New Bundle') }}</VCardTitle>
        <VCardText style="max-height: 75vh;">
          <VAlert v-if="formError" type="error" variant="tonal" class="mb-4">{{ formError }}</VAlert>

          <!-- ── Section 1: Basic info ────────────────────────────────────── -->
          <VRow>
            <VCol cols="12" md="6"><VTextField v-model="form.name" :label="$t('Name (Arabic)')" /></VCol>
            <VCol cols="12" md="6"><VTextField v-model="form.name_en" :label="$t('Name (English)')" /></VCol>
            <VCol cols="12" md="6"><VTextarea v-model="form.description" :label="$t('Description (Arabic)')" rows="2" /></VCol>
            <VCol cols="12" md="6"><VTextarea v-model="form.description_en" :label="$t('Description (English)')" rows="2" /></VCol>

            <VCol cols="6" md="3"><VTextField v-model.number="form.price" :label="$t('Price (SAR)')" type="number" min="0" /></VCol>
            <VCol cols="6" md="3"><VTextField v-model="form.period" :label="$t('Period (AR)')" placeholder="ريال / سنوياً" /></VCol>
            <VCol cols="6" md="3"><VTextField v-model="form.period_en" :label="$t('Period (EN)')" placeholder="SAR / year" /></VCol>
            <VCol cols="6" md="3"><VTextField v-model.number="form.sort" :label="$t('Sort')" type="number" min="0" /></VCol>

            <VCol cols="6" md="3"><VTextField v-model="form.badge" :label="$t('Badge (AR)')" placeholder="الأكثر شيوعاً" /></VCol>
            <VCol cols="6" md="3"><VTextField v-model="form.badge_en" :label="$t('Badge (EN)')" placeholder="Most Popular" /></VCol>
            <VCol cols="6" md="3"><VTextField v-model="form.cta_label" :label="$t('Button (AR)')" placeholder="اشترِ الآن" /></VCol>
            <VCol cols="6" md="3"><VTextField v-model="form.cta_label_en" :label="$t('Button (EN)')" placeholder="Buy Now" /></VCol>

            <VCol cols="6" md="3"><VTextField v-model="form.color" :label="$t('Color')" type="color" /></VCol>
            <VCol cols="6" md="3" class="d-flex align-center"><VSwitch v-model="form.is_featured" :label="$t('Featured')" color="primary" hide-details /></VCol>
            <VCol cols="6" md="3" class="d-flex align-center"><VSwitch v-model="form.is_active" :label="$t('Active')" color="success" hide-details /></VCol>
          </VRow>

          <!-- ── Section 2: Bundle-specific ──────────────────────────────── -->
          <VDivider class="my-4" />
          <p class="text-caption text-medium-emphasis mb-3">{{ $t('Bundle Settings') }}</p>
          <VRow>
            <VCol cols="12" md="4">
              <VSelect
                v-model="form.type"
                :items="[
                  { title: $t('Fixed'), value: 'fixed' },
                  { title: $t('Flexible'), value: 'flexible' },
                  { title: $t('Subscription'), value: 'subscription' },
                ]"
                :label="$t('Type')"
              />
            </VCol>
            <VCol cols="6" md="4">
              <VTextField
                v-model.number="form.access_days"
                :label="$t('Access Days')"
                type="number" min="1"
                :placeholder="$t('Leave empty for lifetime')"
              />
            </VCol>
            <VCol cols="6" md="4">
              <VTextField
                v-model.number="form.max_courses"
                :label="$t('Max Courses')"
                type="number" min="1"
                :disabled="form.type !== 'flexible'"
              />
            </VCol>

            <VCol cols="12" md="4">
              <VSelect
                v-model="form.default_quiz_visibility"
                :items="[
                  { title: $t('All Quizzes'), value: 'all' },
                  { title: $t('No Quizzes'), value: 'none' },
                  { title: $t('Selected'), value: 'selected' },
                ]"
                :label="$t('Quiz Visibility')"
              />
            </VCol>
            <VCol cols="12" md="4">
              <VSelect
                v-model="form.default_products_visibility"
                :items="[
                  { title: $t('All Products'), value: 'all' },
                  { title: $t('No Products'), value: 'none' },
                  { title: $t('Selected'), value: 'selected' },
                ]"
                :label="$t('Digital Products')"
              />
            </VCol>
            <VCol cols="12" md="4" class="d-flex align-center gap-4">
              <VSwitch v-model="form.default_certificate_enabled" :label="$t('Certificate')" color="success" hide-details />
              <VSwitch v-model="form.auto_renewal" :label="$t('Auto Renewal')" color="primary" hide-details :disabled="form.type !== 'subscription'" />
            </VCol>

            <VCol cols="12">
              <VSelect
                v-model="form.course_ids"
                :items="allCourses"
                item-title="title"
                item-value="value"
                :return-object="false"
                :label="$t('Select Courses')"
                multiple chips closable-chips
              />
            </VCol>
          </VRow>

          <!-- ── Section 3: Feature groups (same as pricing-plans) ────────── -->
          <VDivider class="my-4" />
          <div class="d-flex align-center justify-space-between mb-3">
            <h6 class="text-h6">{{ $t('Feature Groups') }}</h6>
            <VBtn size="small" variant="tonal" prepend-icon="tabler-plus" @click="addGroup">{{ $t('Add Group') }}</VBtn>
          </div>

          <VCard v-for="(group, gi) in form.features" :key="gi" variant="outlined" class="mb-3">
            <VCardText>
              <div class="d-flex gap-2 mb-2">
                <VTextField v-model="group.title" :label="$t('Group title (AR)')" density="compact" hide-details />
                <VTextField v-model="group.title_en" :label="$t('Group title (EN)')" density="compact" hide-details />
                <IconBtn color="error" @click="removeGroup(gi)"><VIcon icon="tabler-trash" /></IconBtn>
              </div>
              <div v-for="(item, ii) in group.items" :key="ii" class="d-flex align-center gap-2 mb-2">
                <VTextField v-model="item.text" :label="$t('Feature (AR)')" density="compact" hide-details />
                <VTextField v-model="item.text_en" :label="$t('Feature (EN)')" density="compact" hide-details />
                <VSwitch v-model="item.included" color="success" density="compact" hide-details :true-value="true" :false-value="false" />
                <IconBtn size="small" color="error" @click="removeItem(gi, ii)"><VIcon icon="tabler-x" /></IconBtn>
              </div>
              <VBtn size="x-small" variant="text" prepend-icon="tabler-plus" @click="addItem(gi)">{{ $t('Add feature') }}</VBtn>
            </VCardText>
          </VCard>

          <VCard v-if="form.features.length === 0" variant="tonal" class="text-center py-6">
            <p class="text-body-2 text-medium-emphasis mb-2">{{ $t('No feature groups yet.') }}</p>
            <VBtn size="small" variant="tonal" prepend-icon="tabler-plus" @click="addGroup">{{ $t('Add First Group') }}</VBtn>
          </VCard>
        </VCardText>

        <VCardActions>
          <VSpacer />
          <VBtn variant="text" @click="isFormOpen = false">{{ $t('Cancel') }}</VBtn>
          <VBtn
            color="primary"
            :loading="saveMutation.isPending.value"
            :disabled="!form.name"
            @click="saveMutation.mutate()"
          >
            {{ $t('Save') }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>

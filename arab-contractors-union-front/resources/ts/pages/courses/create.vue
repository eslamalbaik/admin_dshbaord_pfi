<script setup lang="ts">
definePage({
  meta: {
    requiresAdmin: true,
  },
})

import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useCookie } from '@core/composable/useCookie'
import api from '@/plugins/axios'
import { useMutation, useQueryClient } from '@tanstack/vue-query'

const router = useRouter()
const queryClient = useQueryClient()

const currentStep = ref(0)
const steps = ['Basic Info', 'Content', 'SEO', 'Pricing', 'Publish']

const form = ref({
  title: '',
  short_description: '',
  description: '',
  thumbnail: null as File | null,
  category: null,
  difficulty: null,
  language: null,
  meta_title: '',
  meta_description: '',
  keywords: '',
  is_free: false,
  price: null,
  discount_price: null,
  access_days: null,
  include_in_subscription: false,
  bundle_only: false,
  status: 'Draft'
})

const validationErrors = ref<Record<string, string[]>>({})

const createCourseMutation = useMutation({
  mutationFn: async (formData: FormData) => {
    return await api.post('/api/dashboard/courses', formData, {
      headers: {
        'Content-Type': 'multipart/form-data'
      }
    })
  },
  onSuccess: (response: any) => {
    queryClient.invalidateQueries({ queryKey: ['courses'] })
    const courseId = response.data?.course?.id
    if (courseId) {
      router.push(`/courses/${courseId}/builder`)
    } else {
      router.push({ name: 'courses' })
    }
  },
  onError: (error: any) => {
    console.error('Failed to create course:', error)
    if (error.response?.status === 422) {
      validationErrors.value = error.response.data.errors || {}
    }
  }
})

const submitCourse = () => {
  validationErrors.value = {}
  
  const formData = new FormData()
  
  const courseData = { ...form.value }
  if (courseData.is_free) {
    courseData.price = null
    courseData.discount_price = null
  }

  Object.keys(courseData).forEach(key => {
    const value = (courseData as any)[key]
    if (value !== null && value !== '') {
      // Fix booleans for Laravel validation
      if (typeof value === 'boolean') {
        formData.append(key, value ? '1' : '0')
      } else {
        formData.append(key, value instanceof File ? value : String(value))
      }
    }
  })

  createCourseMutation.mutate(formData)
}
</script>

<template>
  <div>
    <div class="d-flex align-center gap-2 mb-6">
      <VBtn
        icon="tabler-arrow-left"
        variant="text"
        :to="{ name: 'courses' }"
      />
      <div>
        <h4 class="text-h4">{{ $t('Create New Course') }}</h4>
        <p class="text-body-2 text-medium-emphasis mb-0">{{ $t('Build your pharmacy course step by step') }}</p>
      </div>
    </div>

    <VCard>
      <VCardText>
        <VTabs v-model="currentStep" class="mb-6">
          <VTab
            v-for="(step, i) in steps"
            :key="step"
            :value="i"
          >
            <VIcon
              :icon="i <= currentStep ? 'tabler-circle-check' : 'tabler-circle'"
              start
            />
            {{ $t(step) }}
          </VTab>
        </VTabs>

        <VWindow v-model="currentStep">
          <!-- Basic Info -->
          <VWindowItem :value="0">
            <VRow>
              <VCol cols="12" md="8">
                <VAlert v-if="Object.keys(validationErrors).length > 0" type="error" variant="tonal" class="mb-6">
                  Please fix the validation errors below before submitting.
                </VAlert>
                <VTextField
                  v-model="form.title"
                  :label="$t('Course Title')"
                  placeholder="e.g. Advanced Clinical Pharmacology"
                  class="mb-4"
                  :error-messages="validationErrors.title"
                />
                <VTextarea
                  v-model="form.short_description"
                  :label="$t('Short Description')"
                  placeholder="Brief description for course cards"
                  rows="3"
                  class="mb-4"
                  :error-messages="validationErrors.short_description"
                />
                <VTextarea
                  v-model="form.description"
                  :label="$t('Full Description')"
                  placeholder="Detailed course description"
                  rows="6"
                  class="mb-4"
                  :error-messages="validationErrors.description"
                />
              </VCol>
              <VCol cols="12" md="4">
                <VFileInput
                  :label="$t('Thumbnail')"
                  accept="image/*"
                  prepend-icon="tabler-photo"
                  class="mb-4"
                  @change="e => form.thumbnail = e.target.files[0]"
                  :error-messages="validationErrors.thumbnail"
                />
                <VSelect
                  v-model="form.category"
                  :label="$t('Category')"
                  :items="['Pharmacology', 'Clinical Pharmacy', 'Drug Interactions', 'Chemistry'].map(v => ({ title: $t(v), value: v }))"
                  class="mb-4"
                  :error-messages="validationErrors.category"
                />
                <VSelect
                  v-model="form.difficulty"
                  :label="$t('Difficulty Level')"
                  :items="['Beginner', 'Intermediate', 'Advanced'].map(v => ({ title: $t(v), value: v }))"
                  class="mb-4"
                  :error-messages="validationErrors.difficulty"
                />
                <VSelect
                  v-model="form.language"
                  :label="$t('Language')"
                  :items="['Arabic', 'English'].map(v => ({ title: $t(v), value: v }))"
                  class="mb-4"
                  :error-messages="validationErrors.language"
                />
              </VCol>
            </VRow>
          </VWindowItem>

          <!-- Content -->
          <VWindowItem :value="1">
            <VAlert type="info" variant="tonal">
              {{ $t('Module and Lesson builder will be available after the course is created.') }}
            </VAlert>
          </VWindowItem>

          <!-- SEO -->
          <VWindowItem :value="2">
            <VRow>
              <VCol cols="12" md="8">
                <VTextField
                  v-model="form.meta_title"
                  :label="$t('Meta Title')"
                  placeholder="SEO title for search engines"
                  class="mb-4"
                  :error-messages="validationErrors.meta_title"
                />
                <VTextarea
                  v-model="form.meta_description"
                  :label="$t('Meta Description')"
                  placeholder="SEO description (150-160 characters)"
                  rows="3"
                  class="mb-4"
                  :error-messages="validationErrors.meta_description"
                />
                <VTextField
                  v-model="form.keywords"
                  :label="$t('Keywords')"
                  placeholder="pharmacy, pharmacology, clinical"
                  class="mb-4"
                  :error-messages="validationErrors.keywords"
                />
              </VCol>
            </VRow>
          </VWindowItem>

          <!-- Pricing -->
          <VWindowItem :value="3">
            <VRow>
              <VCol cols="12" md="6">
                <VSwitch v-model="form.is_free" :label="$t('Free Course')" class="mb-4" :error-messages="validationErrors.is_free" />
                <VTextField
                  v-model="form.price"
                  :label="$t('Course Price (SAR)')"
                  placeholder="450"
                  type="number"
                  class="mb-4"
                  :disabled="form.is_free"
                  :error-messages="validationErrors.price"
                />
                <VTextField
                  v-model="form.discount_price"
                  :label="$t('Discount Price (SAR)')"
                  placeholder="350"
                  type="number"
                  class="mb-4"
                  :disabled="form.is_free"
                  :error-messages="validationErrors.discount_price"
                />
                <VTextField
                  v-model="form.access_days"
                  :label="$t('Access duration (days)')"
                  :hint="$t('Leave empty for lifetime access')"
                  persistent-hint
                  placeholder="365"
                  type="number"
                  min="1"
                  class="mb-4"
                  :error-messages="validationErrors.access_days"
                />
                <VSwitch v-model="form.include_in_subscription" :label="$t('Include in Subscription')" class="mb-4" :error-messages="validationErrors.include_in_subscription" />
                <VSwitch
                  v-model="form.bundle_only"
                  label="Bundle Only — حصري للباقات"
                  hint="Hide from individual courses listing / إخفاء من قسم الكورسات الفردية"
                  persistent-hint
                  class="mb-4"
                  :error-messages="validationErrors.bundle_only"
                />
              </VCol>
            </VRow>
          </VWindowItem>

          <!-- Publish -->
          <VWindowItem :value="4">
            <VRow>
              <VCol cols="12" md="6">
                <VSelect
                  v-model="form.status"
                  :label="$t('Status')"
                  :items="['Draft', 'Published', 'Scheduled'].map(v => ({ title: $t(v), value: v }))"
                  class="mb-4"
                  :error-messages="validationErrors.status"
                />
                <VBtn color="primary" size="large" block :loading="createCourseMutation.isPending.value" @click="submitCourse">
                  <VIcon icon="tabler-upload" start />
                  {{ $t('Publish Course') }}
                </VBtn>
              </VCol>
            </VRow>
          </VWindowItem>
        </VWindow>

        <div class="d-flex justify-space-between mt-6">
          <VBtn
            :disabled="currentStep === 0"
            variant="outlined"
            @click="currentStep--"
          >
            <VIcon icon="tabler-arrow-left" start />
            {{ $t('Previous') }}
          </VBtn>
          <VBtn
            v-if="currentStep < steps.length - 1"
            color="primary"
            @click="currentStep++"
          >
            {{ $t('Next') }}
            <VIcon icon="tabler-arrow-right" end />
          </VBtn>
        </div>
      </VCardText>
    </VCard>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import api from '@/plugins/axios'
import { useQuery, useQueryClient } from '@tanstack/vue-query'

definePage({ meta: { requiresAdmin: true } })

const queryClient = useQueryClient()

// ─── Courses List ────────────────────────────────────────────────────────────
const { data: courses = [], isLoading } = useQuery({
  queryKey: ['courses'],
  queryFn: async () => {
    const res = await api.get('/api/dashboard/courses')
    return res.data
  },
})

// ─── Toggle Publish (status only — no file upload) ──────────────────────────
const togglePublish = async (course: any) => {
  const newPublished = !course.is_published
  // Optimistic update
  queryClient.setQueryData(['courses'], (old: any) =>
    old?.map((c: any) => c.id === course.id ? { ...c, is_published: newPublished, status: newPublished ? 'Published' : 'Draft' } : c)
  )
  try {
    // Status-only toggle: plain JSON, no file — standard PUT is fine here
    await api.put(`/api/dashboard/courses/${course.id}`, {
      is_published: newPublished,
      status: newPublished ? 'Published' : 'Draft',
    })
  } catch {
    // Rollback on error
    queryClient.invalidateQueries({ queryKey: ['courses'] })
  }
}

// ─── Delete with Confirm Dialog ──────────────────────────────────────────────
const courseToDelete      = ref<any>(null)
const isDeleteDialogVisible = ref(false)
const isDeleting          = ref(false)

const confirmDelete = (course: any) => { courseToDelete.value = course; isDeleteDialogVisible.value = true }

const executeDelete = async () => {
  if (!courseToDelete.value) return
  isDeleting.value = true
  try {
    await api.delete(`/api/dashboard/courses/${courseToDelete.value.id}`)
    queryClient.invalidateQueries({ queryKey: ['courses'] })
    isDeleteDialogVisible.value = false
  } catch (e) {
    console.error('Delete failed', e)
  } finally {
    isDeleting.value = false
    courseToDelete.value = null
  }
}

// ─── Edit Dialog State ───────────────────────────────────────────────────────
const isEditDialogVisible = ref(false)
const isEditing           = ref(false)
const editErrors          = ref<Record<string, string>>({})   // 422 field errors
const thumbnailFile       = ref<File | null>(null)
const thumbnailPreview    = ref<string | null>(null)
const editForm = ref({
  id:                0,
  title:             '',
  short_description: '',
  category:          '',
  difficulty:        '',
  price:             0,
  is_free:           false,
  bundle_only:       false,
  status:            'Draft',
})

const difficulties = ['Beginner', 'Intermediate', 'Advanced']
const statuses     = ['Draft', 'Published']

const openEditDialog = (course: any) => {
  editErrors.value       = {}
  thumbnailFile.value    = null
  thumbnailPreview.value = course.thumbnail
    ? `http://localhost:8000/storage/${course.thumbnail}`
    : null

  editForm.value = {
    id:                course.id,
    title:             course.title        || '',
    short_description: course.short_description || '',
    category:          course.category    || '',
    difficulty:        course.difficulty  || '',
    price:             course.price       || 0,
    is_free:           !!course.is_free,
    bundle_only:       !!course.bundle_only,
    status:            course.status      || 'Draft',
  }
  isEditDialogVisible.value = true
}

const onThumbnailChange = (e: Event) => {
  const file = (e.target as HTMLInputElement).files?.[0]
  if (!file) return
  thumbnailFile.value   = file
  thumbnailPreview.value = URL.createObjectURL(file)
}

const submitEdit = async () => {
  editErrors.value = {}
  isEditing.value  = true
  try {
    // ✅ FIX 1: Always use FormData + POST + _method=PUT
    // Laravel drops multipart/form-data on actual PUT requests
    const formData = new FormData()
    formData.append('_method', 'PUT')
    formData.append('title',             editForm.value.title)
    formData.append('short_description', editForm.value.short_description)
    formData.append('category',          editForm.value.category)
    formData.append('difficulty',        editForm.value.difficulty)
    formData.append('price',             editForm.value.is_free ? '0' : String(editForm.value.price))
    formData.append('is_free',           editForm.value.is_free ? '1' : '0')
    formData.append('bundle_only',       editForm.value.bundle_only ? '1' : '0')
    formData.append('status',            editForm.value.status)
    if (thumbnailFile.value) {
      formData.append('thumbnail', thumbnailFile.value)
    }

    await api.post(`/api/dashboard/courses/${editForm.value.id}`, formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })

    queryClient.invalidateQueries({ queryKey: ['courses'] })
    isEditDialogVisible.value = false
  } catch (e: any) {
    if (e.response?.status === 422) {
      // ✅ FIX 4: Hydrate each form field with its Laravel validation error
      const raw = e.response.data.errors || {}
      Object.keys(raw).forEach(k => { editErrors.value[k] = raw[k][0] })
    } else {
      editErrors.value['title'] = 'Update failed. Please try again.'
    }
  } finally {
    isEditing.value = false
  }
}

const statusColor = (s: string) => s === 'Published' ? 'success' : 'secondary'
</script>

<template>
  <div>
    <VCard>
      <VCardItem>
        <VCardTitle class="d-flex align-center gap-2">
          <VIcon icon="tabler-book-2" />
          {{ $t('All Courses') }}
        </VCardTitle>
        <VCardSubtitle>{{ $t('Manage your pharmacy courses') }}</VCardSubtitle>

        <template #append>
          <VBtn color="primary" prepend-icon="tabler-plus" :to="{ name: 'courses-create' }">
            {{ $t('Create Course') }}
          </VBtn>
        </template>
      </VCardItem>

      <VCardText>
        <VTable>
          <thead>
            <tr>
              <th>{{ $t('Course') }}</th>
              <th>{{ $t('Category') }}</th>
              <th>{{ $t('Price') }}</th>
              <th>{{ $t('Status') }}</th>
              <th>{{ $t('Publish') }}</th>
              <th class="text-center">{{ $t('Students') }}</th>
              <th class="text-center">{{ $t('Actions') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="isLoading">
              <td colspan="7" class="text-center text-medium-emphasis pa-8">
                <VProgressCircular indeterminate color="primary" />
              </td>
            </tr>
            <tr v-else-if="courses.length === 0">
              <td colspan="7" class="text-center text-medium-emphasis pa-8">
                {{ $t('No courses yet. Create your first course to get started.') }}
              </td>
            </tr>
            <tr v-for="course in courses" :key="course.id" v-else>
              <!-- Course -->
              <td>
                <div class="d-flex align-center gap-3">
                  <VAvatar rounded variant="tonal" color="primary" size="38">
                    <VImg v-if="course.thumbnail" :src="`http://localhost:8000/storage/${course.thumbnail}`" cover />
                    <VIcon v-else icon="tabler-book" size="22" />
                  </VAvatar>
                  <div class="d-flex flex-column">
                    <span class="text-subtitle-2 font-weight-medium text-high-emphasis">{{ course.title }}</span>
                    <span class="text-caption text-medium-emphasis">{{ course.slug }}</span>
                  </div>
                </div>
              </td>

              <!-- Category -->
              <td>
                <VChip size="small" variant="tonal" color="info">
                  {{ course.category || 'N/A' }}
                </VChip>
              </td>

              <!-- Price -->
              <td>{{ course.is_free ? 'Free' : `${course.price} SAR` }}</td>

              <!-- Status chip -->
              <td>
                <VChip size="small" variant="elevated" :color="statusColor(course.status)">
                  {{ course.status || (course.is_published ? 'Published' : 'Draft') }}
                </VChip>
              </td>

              <!-- Publish toggle -->
              <td>
                <VSwitch
                  :model-value="!!course.is_published"
                  color="success"
                  hide-details
                  density="compact"
                  @change="togglePublish(course)"
                />
              </td>

              <!-- Students count -->
              <td class="text-center">
                <VChip size="small" color="primary" variant="tonal">
                  <VIcon icon="tabler-users" size="13" class="me-1" />
                  {{ course.students_count || 0 }}
                </VChip>
              </td>

              <!-- Action buttons -->
              <td class="text-center">
                <VTooltip text="Edit Course Info" location="top">
                  <template #activator="{ props }">
                    <VBtn
                      v-bind="props"
                      icon="tabler-edit"
                      variant="text"
                      size="small"
                      color="medium-emphasis"
                      @click="openEditDialog(course)"
                    />
                  </template>
                </VTooltip>

                <VTooltip :text="$t('Curriculum Builder')" location="top">
                  <template #activator="{ props }">
                    <VBtn
                      v-bind="props"
                      icon="tabler-layout-list"
                      variant="text"
                      size="small"
                      color="info"
                      :to="`/courses/${course.id}/builder`"
                    />
                  </template>
                </VTooltip>

                <VTooltip text="Delete Course" location="top">
                  <template #activator="{ props }">
                    <VBtn
                      v-bind="props"
                      icon="tabler-trash"
                      variant="text"
                      size="small"
                      color="error"
                      @click="confirmDelete(course)"
                    />
                  </template>
                </VTooltip>
              </td>
            </tr>
          </tbody>
        </VTable>
      </VCardText>
    </VCard>

    <!-- ─── Edit Course Dialog ──────────────────────────────────────────────── -->
    <VDialog v-model="isEditDialogVisible" max-width="600" persistent>
      <VCard>
        <VCardItem>
          <VCardTitle class="d-flex align-center gap-2">
            <VIcon icon="tabler-edit" color="primary" />
            {{ $t('Edit Course') }}
          </VCardTitle>
        </VCardItem>

        <VCardText class="pt-2">
          <VRow>
            <!-- Thumbnail Preview + Upload -->
            <VCol cols="12">
              <div class="d-flex align-center gap-4 mb-2">
                <VAvatar rounded size="72" color="primary" variant="tonal">
                  <VImg v-if="thumbnailPreview" :src="thumbnailPreview" cover />
                  <VIcon v-else icon="tabler-photo" size="36" />
                </VAvatar>
                <div>
                  <label for="thumbnailInput" class="v-btn v-btn--variant-outlined text-primary" style="cursor:pointer">
                    <VIcon icon="tabler-upload" size="16" class="me-1" />
                    {{ $t('Change Thumbnail') }}
                  </label>
                  <input
                    id="thumbnailInput"
                    type="file"
                    accept="image/*"
                    class="d-none"
                    @change="onThumbnailChange"
                  />
                  <p class="text-caption text-medium-emphasis mt-1">{{ $t('JPG, PNG, WebP. Max 2MB.') }}</p>
                </div>
              </div>
            </VCol>

            <VCol cols="12">
              <VTextField
                v-model="editForm.title"
                :label="$t('Course Title')"
                variant="outlined"
                :error-messages="editErrors['title']"
                prepend-inner-icon="tabler-book"
              />
            </VCol>

            <VCol cols="12">
              <VTextField
                v-model="editForm.short_description"
                :label="$t('Short Description')"
                variant="outlined"
                :error-messages="editErrors['short_description']"
                prepend-inner-icon="tabler-file-description"
              />
            </VCol>

            <VCol cols="12" sm="6">
              <VTextField
                v-model="editForm.category"
                :label="$t('Category')"
                variant="outlined"
                :error-messages="editErrors['category']"
                prepend-inner-icon="tabler-tag"
              />
            </VCol>

            <VCol cols="12" sm="6">
              <VSelect
                v-model="editForm.difficulty"
                :items="difficulties"
                :label="$t('Difficulty')"
                variant="outlined"
                :error-messages="editErrors['difficulty']"
                prepend-inner-icon="tabler-chart-bar"
              />
            </VCol>

            <VCol cols="12" sm="6">
              <VTextField
                v-model.number="editForm.price"
                :label="$t('Price (SAR)')"
                type="number"
                variant="outlined"
                :error-messages="editErrors['price']"
                prepend-inner-icon="tabler-currency-riyal"
                :disabled="editForm.is_free"
              />
            </VCol>

            <VCol cols="12" sm="6" class="d-flex align-center">
              <VSwitch
                v-model="editForm.is_free"
                :label="$t('Free Course')"
                color="success"
                hide-details
              />
            </VCol>

            <VCol cols="12">
              <VSwitch
                v-model="editForm.bundle_only"
                label="Bundle Only — حصري للباقات"
                color="warning"
                hint="Hide from individual courses listing / إخفاء من قسم الكورسات الفردية"
                persistent-hint
              />
            </VCol>

            <VCol cols="12">
              <VSelect
                v-model="editForm.status"
                :items="statuses"
                :label="$t('Status')"
                variant="outlined"
                :error-messages="editErrors['status']"
                prepend-inner-icon="tabler-toggle-left"
              />
            </VCol>
          </VRow>
        </VCardText>

        <VCardActions>
          <VSpacer />
          <VBtn variant="text" :disabled="isEditing" @click="isEditDialogVisible = false">
            {{ $t('Cancel') }}
          </VBtn>
          <VBtn
            color="primary"
            :loading="isEditing"
            :disabled="!editForm.title || isEditing"
            @click="submitEdit"
          >
            {{ $t('Save Changes') }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- ─── Delete Confirm Dialog ───────────────────────────────────────────── -->
    <VDialog v-model="isDeleteDialogVisible" max-width="420">
      <VCard>
        <VCardItem>
          <VCardTitle class="d-flex align-center gap-2 text-error">
            <VIcon icon="tabler-alert-triangle" color="error" />
            {{ $t('Delete Course') }}
          </VCardTitle>
        </VCardItem>
        <VCardText>
          <p>{{ $t('Are you sure you want to delete') }} <strong>{{ courseToDelete?.title }}</strong>?</p>
          <p class="text-medium-emphasis text-caption mt-1">{{ $t('This action cannot be undone.') }}</p>
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn variant="text" :disabled="isDeleting" @click="isDeleteDialogVisible = false">
            {{ $t('Cancel') }}
          </VBtn>
          <VBtn color="error" :loading="isDeleting" @click="executeDelete">
            {{ $t('Yes, Delete') }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>

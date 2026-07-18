<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import api from '@/plugins/axios'

definePage({ meta: { requiresAdmin: true } })

// ── Reset device lock ───────────────────────────────────────────────────────
const resettingId  = ref<number | null>(null)
const resetSnackbar = ref(false)
const resetMessage  = ref('')

const resetDeviceLock = async (enrollmentId: number) => {
  resettingId.value = enrollmentId
  try {
    const res = await api.post(`/api/dashboard/enrollments/${enrollmentId}/reset-device`)
    // Update the local record
    const idx = enrollments.value.findIndex(e => e.id === enrollmentId)
    if (idx !== -1) {
      enrollments.value[idx].device_locked = false
      enrollments.value[idx].device_id     = null
    }
    resetMessage.value = res.data.message || 'Device lock cleared.'
    resetSnackbar.value = true
  } catch (e: any) {
    resetMessage.value = e?.response?.data?.message || 'Failed to reset lock.'
    resetSnackbar.value = true
  } finally {
    resettingId.value = null
  }
}

// ─── Table State ───────────────────────────────────────────────────────────
const enrollments = ref<any[]>([])
const isLoading   = ref(true)
const search      = ref('')
const currentPage = ref(1)
const lastPage    = ref(1)
const total       = ref(0)

// ─── Enroll Dialog State ───────────────────────────────────────────────────
const allStudents  = ref<any[]>([])
const allCourses   = ref<any[]>([])
const isEnrollDialogVisible = ref(false)
const isEnrolling  = ref(false)
const enrollErrors = ref<Record<string, string>>({})  // 422 field errors
const enrollForm   = ref<{ user_id: number | null; course_id: number | null }>({ user_id: null, course_id: null })

// ─── Fetch Paginated Enrollments ───────────────────────────────────────────
const fetchEnrollments = async (page = 1) => {
  isLoading.value = true
  try {
    const res = await api.get('/api/dashboard/enrollments', { params: { page } })
    enrollments.value = res.data.data        // paginator data array
    currentPage.value  = res.data.current_page
    lastPage.value     = res.data.last_page
    total.value        = res.data.total
  } catch (e) {
    console.error('Failed to fetch enrollments', e)
  } finally {
    isLoading.value = false
  }
}

const onPageChange = (page: number) => fetchEnrollments(page)

// ─── Enroll Dialog ─────────────────────────────────────────────────────────
const openEnrollDialog = async () => {
  enrollForm.value   = { user_id: null, course_id: null }
  enrollErrors.value = {}
  isEnrollDialogVisible.value = true

  // Lazy-load dropdowns only when dialog opens
  const [s, c] = await Promise.all([
    api.get('/api/dashboard/students'),
    api.get('/api/dashboard/courses'),
  ])
  allStudents.value = s.data || []
  allCourses.value  = c.data || []
}

const submitEnroll = async () => {
  enrollErrors.value = {}
  isEnrolling.value  = true
  try {
    await api.post('/api/dashboard/enrollments', enrollForm.value)
    isEnrollDialogVisible.value = false
    await fetchEnrollments(currentPage.value)
  } catch (e: any) {
    if (e.response?.status === 422) {
      // Map Laravel validation errors to fields
      const raw = e.response.data.errors || {}
      Object.keys(raw).forEach(k => { enrollErrors.value[k] = raw[k][0] })
    } else if (e.response?.status === 409) {
      enrollErrors.value['user_id'] = e.response.data.message
    } else {
      enrollErrors.value['user_id'] = 'Enrollment failed. Please try again.'
    }
  } finally {
    isEnrolling.value = false
  }
}

// Client-side search on already-loaded page
const filteredEnrollments = computed(() => {
  if (!search.value) return enrollments.value
  const q = search.value.toLowerCase()
  return enrollments.value.filter(e =>
    e.student_name?.toLowerCase().includes(q) ||
    e.student_email?.toLowerCase().includes(q) ||
    e.course_title?.toLowerCase().includes(q)
  )
})

onMounted(() => fetchEnrollments())
</script>

<template>
  <VCard>
    <VCardItem>
      <VCardTitle class="d-flex align-center gap-2">
        <VIcon icon="tabler-clipboard-list" />
        {{ $t('Enrollments') }}
        <VChip size="small" color="primary" class="ms-2">{{ total }}</VChip>
      </VCardTitle>
      <VCardSubtitle>{{ $t('All student course enrollments') }}</VCardSubtitle>

      <template #append>
        <VBtn color="primary" prepend-icon="tabler-user-plus" @click="openEnrollDialog">
          {{ $t('Enroll Student') }}
        </VBtn>
      </template>
    </VCardItem>

    <VCardText>
      <!-- Search -->
      <VTextField
        v-model="search"
        prepend-inner-icon="tabler-search"
        :placeholder="$t('Search by student or course...')"
        density="compact"
        variant="outlined"
        class="mb-4"
        hide-details
        style="max-width: 360px"
        clearable
      />

      <!-- Table -->
      <VTable>
        <thead>
          <tr>
            <th>#</th>
            <th>{{ $t('Student') }}</th>
            <th>{{ $t('Email') }}</th>
            <th>{{ $t('Course') }}</th>
            <th>{{ $t('Progress') }}</th>
            <th>{{ $t('Device') }}</th>
            <th>{{ $t('Enrolled Date') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="isLoading">
            <td colspan="7" class="text-center pa-8">
              <VProgressCircular indeterminate color="primary" />
            </td>
          </tr>
          <tr v-else-if="filteredEnrollments.length === 0">
            <td colspan="7" class="text-center text-medium-emphasis pa-8">
              {{ $t('No enrollments found.') }}
            </td>
          </tr>
          <tr v-for="(enrollment, i) in filteredEnrollments" :key="enrollment.id">
            <td class="text-medium-emphasis text-caption">
              {{ (currentPage - 1) * 15 + i + 1 }}
            </td>
            <td>
              <div class="d-flex align-center gap-2">
                <VAvatar color="primary" variant="tonal" size="32">
                  <VIcon icon="tabler-user" size="18" />
                </VAvatar>
                <span class="font-weight-medium">{{ enrollment.student_name }}</span>
              </div>
            </td>
            <td class="text-medium-emphasis">{{ enrollment.student_email }}</td>
            <td>
              <VChip size="small" color="info" variant="tonal">{{ enrollment.course_title }}</VChip>
            </td>
            <td>
              <div class="d-flex align-center gap-2" style="min-width: 110px">
                <VProgressLinear
                  :model-value="enrollment.progress"
                  color="success"
                  rounded
                  height="6"
                />
                <span class="text-caption text-medium-emphasis">{{ enrollment.progress }}%</span>
              </div>
            </td>
            <!-- Device Lock Column -->
            <td>
              <div v-if="enrollment.device_locked" class="d-flex align-center gap-2">
                <VChip size="small" color="warning" variant="tonal" prepend-icon="tabler-device-mobile">
                  {{ $t('Locked') }}
                </VChip>
                <VBtn
                  size="x-small"
                  color="error"
                  variant="tonal"
                  icon="tabler-lock-open"
                  :loading="resettingId === enrollment.id"
                  :disabled="resettingId !== null"
                  @click="resetDeviceLock(enrollment.id)"
                >
                  <VIcon icon="tabler-lock-open" />
                  <VTooltip activator="parent" location="top">
                    {{ $t('Reset Device Lock') }}
                  </VTooltip>
                </VBtn>
              </div>
              <VChip v-else size="small" color="success" variant="tonal" prepend-icon="tabler-device-mobile">
                {{ $t('Free') }}
              </VChip>
            </td>
            <td class="text-caption text-medium-emphasis">
              {{ new Date(enrollment.enrolled_at).toLocaleDateString() }}
            </td>
          </tr>
        </tbody>
      </VTable>

      <!-- Pagination -->
      <div v-if="lastPage > 1" class="d-flex justify-center mt-4">
        <VPagination
          v-model="currentPage"
          :length="lastPage"
          :total-visible="6"
          @update:model-value="onPageChange"
        />
      </div>
    </VCardText>
  </VCard>

  <!-- ─── Reset Device Snackbar ────────────────────────────────────────────── -->
  <VSnackbar
    v-model="resetSnackbar"
    :timeout="3500"
    color="surface"
    location="bottom end"
    variant="elevated"
  >
    <div class="d-flex align-center gap-2">
      <VIcon icon="tabler-device-mobile" color="primary" />
      <span>{{ resetMessage }}</span>
    </div>
    <template #actions>
      <VBtn variant="text" size="small" @click="resetSnackbar = false">{{ $t('Close') }}</VBtn>
    </template>
  </VSnackbar>

  <!-- ─── Manual Enroll Dialog ─────────────────────────────────────────────── -->
  <VDialog v-model="isEnrollDialogVisible" max-width="480">
    <VCard>
      <VCardItem>
        <VCardTitle class="d-flex align-center gap-2">
          <VIcon icon="tabler-user-plus" />
          {{ $t('Enroll Student Manually') }}
        </VCardTitle>
      </VCardItem>

      <VCardText class="pt-2">
        <!-- 409 / generic error banner -->
        <VAlert
          v-if="enrollErrors['user_id'] && !enrollForm.user_id"
          type="warning"
          variant="tonal"
          class="mb-4"
          closable
          @click:close="enrollErrors = {}"
        >
          {{ enrollErrors['user_id'] }}
        </VAlert>

        <VSelect
          v-model="enrollForm.user_id"
          :items="allStudents"
          item-title="name"
          item-value="id"
          :label="$t('Select Student')"
          prepend-inner-icon="tabler-user"
          variant="outlined"
          class="mb-4"
          :error-messages="enrollErrors['user_id']"
        >
          <template #item="{ props, item }">
            <VListItem v-bind="props">
              <template #subtitle>{{ item.raw.email }}</template>
            </VListItem>
          </template>
        </VSelect>

        <VSelect
          v-model="enrollForm.course_id"
          :items="allCourses"
          item-title="title"
          item-value="id"
          :label="$t('Select Course')"
          prepend-inner-icon="tabler-book"
          variant="outlined"
          :error-messages="enrollErrors['course_id']"
        />
      </VCardText>

      <VCardActions>
        <VSpacer />
        <VBtn variant="text" @click="isEnrollDialogVisible = false">{{ $t('Cancel') }}</VBtn>
        <VBtn
          color="primary"
          :loading="isEnrolling"
          :disabled="!enrollForm.user_id || !enrollForm.course_id || isEnrolling"
          @click="submitEnroll"
        >
          {{ $t('Enroll') }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import api from '@/plugins/axios'

definePage({ meta: { requiresAdmin: true } })

const API_STORAGE = (import.meta.env.VITE_API_URL || 'http://localhost:8000').replace(/\/$/, '')

// ─── Table State ────────────────────────────────────────────────────────────
const students  = ref<any[]>([])
const isLoading = ref(true)
const search    = ref('')

const filteredStudents = computed(() => {
  if (!search.value) return students.value
  const q = search.value.toLowerCase()
  return students.value.filter(s =>
    s.name?.toLowerCase().includes(q) ||
    s.email?.toLowerCase().includes(q)
  )
})

// ─── Student Detail Dialog ──────────────────────────────────────────────────
const selectedStudent        = ref<any>(null)
const studentDetail          = ref<{ enrollments: any[]; bundles: any[] } | null>(null)
const isDetailDialogVisible  = ref(false)
const isLoadingDetail        = ref(false)
const detailTab              = ref<'progress' | 'bundles'>('progress')

const openDetailDialog = async (s: any) => {
  selectedStudent.value       = s
  studentDetail.value         = null
  isLoadingDetail.value       = true
  detailTab.value             = 'progress'
  isDetailDialogVisible.value = true

  try {
    const res = await api.get(`/api/dashboard/students/${s.id}/detail`)
    studentDetail.value = res.data
  } catch (e) {
    console.error('Failed to load student detail', e)
  } finally {
    isLoadingDetail.value = false
  }
}

const totalProgress = computed(() => {
  const arr = studentDetail.value?.enrollments || []
  if (!arr.length) return 0
  return Math.round(arr.reduce((sum: number, e: any) => sum + (e.progress || 0), 0) / arr.length)
})

// ─── Add Student Dialog State ───────────────────────────────────────────────
const isAddDialogVisible = ref(false)
const isAdding           = ref(false)
const addErrors          = ref<Record<string, string>>({})
const isPasswordVisible  = ref(false)
const addForm = ref({ name: '', email: '', password: '' })

const openAddDialog = () => {
  addForm.value   = { name: '', email: '', password: '' }
  addErrors.value = {}
  isPasswordVisible.value = false
  isAddDialogVisible.value = true
}

const submitAddStudent = async () => {
  addErrors.value = {}
  isAdding.value  = true
  try {
    const res = await api.post('/api/dashboard/students', addForm.value)
    students.value.unshift(res.data.student)
    isAddDialogVisible.value = false
  } catch (e: any) {
    if (e.response?.status === 422) {
      const raw = e.response.data.errors || {}
      Object.keys(raw).forEach(k => { addErrors.value[k] = raw[k][0] })
    } else {
      addErrors.value['email'] = 'Something went wrong. Please try again.'
    }
  } finally {
    isAdding.value = false
  }
}

// ─── Fetch Students ─────────────────────────────────────────────────────────
onMounted(async () => {
  try {
    const res = await api.get('/api/dashboard/students')
    students.value = res.data || []
  } catch (e) {
    console.error('Failed to fetch students', e)
  } finally {
    isLoading.value = false
  }
})

// ─── Helpers ─────────────────────────────────────────────────────────────────
const progressColor = (p: number) => p >= 80 ? 'success' : p >= 40 ? 'warning' : 'error'
</script>

<template>
  <VCard>
    <VCardItem>
      <VCardTitle class="d-flex align-center gap-2">
        <VIcon icon="tabler-users" />
        {{ $t('Students') }}
        <VChip size="small" color="primary" class="ms-2">{{ students.length }}</VChip>
      </VCardTitle>
      <VCardSubtitle>{{ $t('Manage all registered students') }}</VCardSubtitle>

      <template #append>
        <div class="d-flex align-center gap-3">
          <VTextField
            v-model="search"
            prepend-inner-icon="tabler-search"
            :placeholder="$t('Search...')"
            density="compact"
            variant="outlined"
            hide-details
            style="max-width: 220px"
            clearable
          />
          <VBtn color="primary" prepend-icon="tabler-user-plus" @click="openAddDialog">
            {{ $t('Add Student') }}
          </VBtn>
        </div>
      </template>
    </VCardItem>

    <VCardText>
      <VTable>
        <thead>
          <tr>
            <th>{{ $t('Name') }}</th>
            <th>{{ $t('Email') }}</th>
            <th>{{ $t('Role') }}</th>
            <th>{{ $t('Registered') }}</th>
            <th>{{ $t('Actions') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="isLoading">
            <td colspan="5" class="text-center pa-8">
              <VProgressCircular indeterminate color="primary" />
            </td>
          </tr>
          <tr v-else-if="filteredStudents.length === 0">
            <td colspan="5" class="text-center text-medium-emphasis pa-8">
              {{ $t('No students registered yet.') }}
            </td>
          </tr>
          <tr v-for="student in filteredStudents" :key="student.id">
            <td>
              <div class="d-flex align-center gap-2">
                <VAvatar color="primary" variant="tonal" size="34">
                  <VIcon icon="tabler-user" size="18" />
                </VAvatar>
                <span class="font-weight-medium">{{ student.name }}</span>
              </div>
            </td>
            <td class="text-medium-emphasis">{{ student.email }}</td>
            <td>
              <VChip color="primary" size="small" label>{{ student.role }}</VChip>
            </td>
            <td class="text-caption text-medium-emphasis">
              {{ new Date(student.created_at).toLocaleDateString() }}
            </td>
            <td>
              <VBtn
                icon="tabler-chart-bar"
                variant="text"
                size="small"
                color="primary"
                :title="$t('View Progress & Bundles')"
                @click="openDetailDialog(student)"
              />
            </td>
          </tr>
        </tbody>
      </VTable>
    </VCardText>
  </VCard>

  <!-- ─── Student Detail Dialog ────────────────────────────────────────────── -->
  <VDialog v-model="isDetailDialogVisible" max-width="680" scrollable>
    <VCard v-if="selectedStudent">
      <!-- Header -->
      <VCardItem class="pb-0">
        <template #prepend>
          <VAvatar color="primary" variant="tonal" size="46">
            <VIcon icon="tabler-user" size="24" />
          </VAvatar>
        </template>
        <VCardTitle>{{ selectedStudent.name }}</VCardTitle>
        <VCardSubtitle>{{ selectedStudent.email }}</VCardSubtitle>
        <template #append>
          <VBtn icon="tabler-x" variant="text" size="small" @click="isDetailDialogVisible = false" />
        </template>
      </VCardItem>

      <!-- Loading -->
      <div v-if="isLoadingDetail" class="d-flex justify-center pa-10">
        <VProgressCircular indeterminate color="primary" />
      </div>

      <template v-else-if="studentDetail">
        <!-- Summary chips -->
        <VCardText class="pb-0 pt-4">
          <div class="d-flex flex-wrap gap-3">
            <VChip color="primary" variant="tonal" prepend-icon="tabler-book-2">
              {{ studentDetail.enrollments.length }} {{ $t('Courses') }}
            </VChip>
            <VChip color="success" variant="tonal" prepend-icon="tabler-trending-up">
              {{ $t('Avg. Progress') }}: {{ totalProgress }}%
            </VChip>
            <VChip color="warning" variant="tonal" prepend-icon="tabler-packages">
              {{ studentDetail.bundles.length }} {{ $t('Bundles') }}
            </VChip>
          </div>
        </VCardText>

        <!-- Tabs -->
        <VTabs v-model="detailTab" class="mt-3 px-4" density="compact">
          <VTab value="progress" prepend-icon="tabler-chart-bar">
            {{ $t('Course Progress') }}
          </VTab>
          <VTab value="bundles" prepend-icon="tabler-packages">
            {{ $t('Subscribed Bundles') }}
            <VChip v-if="studentDetail.bundles.length" size="x-small" color="warning" class="ms-2">
              {{ studentDetail.bundles.length }}
            </VChip>
          </VTab>
        </VTabs>

        <VDivider />

        <VCardText style="max-height: 420px; overflow-y: auto">

          <!-- ── Progress Tab ─────────────────────────────────────── -->
          <VTabsWindow v-model="detailTab">
            <VTabsWindowItem value="progress">
              <div v-if="studentDetail.enrollments.length === 0" class="text-center py-8 text-medium-emphasis">
                <VIcon icon="tabler-book-off" size="48" class="mb-3 opacity-30" />
                <p>{{ $t('No course enrollments yet.') }}</p>
              </div>

              <div v-else class="d-flex flex-column gap-3 pt-2">
                <div
                  v-for="en in studentDetail.enrollments"
                  :key="en.id"
                  class="d-flex align-center gap-3 rounded-lg pa-3 border"
                  style="border-color: rgba(var(--v-border-color), var(--v-border-opacity))"
                >
                  <!-- Thumbnail -->
                  <VAvatar
                    v-if="en.thumbnail"
                    :image="`${API_STORAGE}/storage/${en.thumbnail}`"
                    rounded size="42"
                  />
                  <VAvatar v-else color="primary" variant="tonal" rounded size="42">
                    <VIcon icon="tabler-book-2" size="20" />
                  </VAvatar>

                  <!-- Info -->
                  <div class="flex-grow-1 overflow-hidden">
                    <div class="text-body-2 font-weight-semibold text-truncate">{{ en.course_title }}</div>
                    <div class="d-flex align-center gap-2 mt-1">
                      <VProgressLinear
                        :model-value="en.progress"
                        :color="progressColor(en.progress)"
                        rounded height="6"
                        style="flex:1"
                      />
                      <span class="text-caption font-weight-bold" :class="`text-${progressColor(en.progress)}`">
                        {{ en.progress }}%
                      </span>
                    </div>
                    <div class="text-caption text-medium-emphasis mt-1">
                      {{ $t('Enrolled') }}: {{ new Date(en.enrolled_at).toLocaleDateString() }}
                      <template v-if="en.bundle_id">
                        · <VChip size="x-small" color="warning" variant="tonal">Bundle</VChip>
                      </template>
                      <template v-if="en.expires_at">
                        · {{ $t('Expires') }}: {{ new Date(en.expires_at).toLocaleDateString() }}
                      </template>
                    </div>
                  </div>
                </div>
              </div>
            </VTabsWindowItem>

            <!-- ── Bundles Tab ──────────────────────────────────── -->
            <VTabsWindowItem value="bundles">
              <div v-if="studentDetail.bundles.length === 0" class="text-center py-8 text-medium-emphasis">
                <VIcon icon="tabler-packages" size="48" class="mb-3 opacity-30" />
                <p>{{ $t('No bundle subscriptions.') }}</p>
              </div>

              <div v-else class="d-flex flex-column gap-3 pt-2">
                <div
                  v-for="b in studentDetail.bundles"
                  :key="b.id"
                  class="d-flex align-center gap-3 rounded-lg pa-4 border"
                  style="border-color: rgba(var(--v-theme-warning), 0.3); background: rgba(var(--v-theme-warning), 0.04)"
                >
                  <div class="d-flex align-center justify-center rounded-lg"
                    style="width:44px;height:44px;background:rgba(var(--v-theme-warning),0.12);flex-shrink:0">
                    <VIcon icon="tabler-packages" color="warning" size="22" />
                  </div>
                  <div class="flex-grow-1">
                    <div class="text-body-1 font-weight-bold">{{ b.name }}</div>
                    <div class="text-caption text-medium-emphasis">
                      {{ b.courses_count }} {{ $t('courses') }} ·
                      {{ Number(b.price).toLocaleString() }} {{ $t('SAR') }} ·
                      <template v-if="b.access_days">
                        {{ b.access_days }} {{ $t('days access') }}
                      </template>
                      <template v-else>
                        {{ $t('Lifetime access') }}
                      </template>
                    </div>
                  </div>
                  <VChip size="small" color="success" variant="tonal">{{ $t('Active') }}</VChip>
                </div>
              </div>
            </VTabsWindowItem>
          </VTabsWindow>
        </VCardText>
      </template>

      <VCardActions>
        <VSpacer />
        <VBtn variant="outlined" @click="isDetailDialogVisible = false">{{ $t('Close') }}</VBtn>
      </VCardActions>
    </VCard>
  </VDialog>

  <!-- ─── Add Student Dialog ───────────────────────────────────────────────── -->
  <VDialog v-model="isAddDialogVisible" max-width="480" persistent>
    <VCard>
      <VCardItem>
        <VCardTitle class="d-flex align-center gap-2">
          <VIcon icon="tabler-user-plus" color="primary" />
          {{ $t('Add New Student') }}
        </VCardTitle>
      </VCardItem>
      <VCardText class="pt-2">
        <VTextField
          v-model="addForm.name"
          :label="$t('Full Name')"
          prepend-inner-icon="tabler-user"
          variant="outlined"
          class="mb-3"
          :error-messages="addErrors['name']"
          autofocus
        />
        <VTextField
          v-model="addForm.email"
          :label="$t('Email Address')"
          type="email"
          prepend-inner-icon="tabler-mail"
          variant="outlined"
          class="mb-3"
          :error-messages="addErrors['email']"
        />
        <VTextField
          v-model="addForm.password"
          :label="$t('Password')"
          :type="isPasswordVisible ? 'text' : 'password'"
          prepend-inner-icon="tabler-lock"
          :append-inner-icon="isPasswordVisible ? 'tabler-eye-off' : 'tabler-eye'"
          variant="outlined"
          :error-messages="addErrors['password']"
          @click:append-inner="isPasswordVisible = !isPasswordVisible"
        />
        <VAlert type="info" variant="tonal" class="mt-3" density="compact">
          {{ $t('Student role is assigned automatically.') }}
        </VAlert>
      </VCardText>
      <VCardActions>
        <VSpacer />
        <VBtn variant="text" :disabled="isAdding" @click="isAddDialogVisible = false">{{ $t('Cancel') }}</VBtn>
        <VBtn
          color="primary" :loading="isAdding"
          :disabled="!addForm.name || !addForm.email || !addForm.password || isAdding"
          @click="submitAddStudent"
        >{{ $t('Create Student') }}</VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

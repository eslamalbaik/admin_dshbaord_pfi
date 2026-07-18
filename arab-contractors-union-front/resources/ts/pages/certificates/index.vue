<script setup lang="ts">
import { ref, computed } from 'vue'
import { useQuery, useQueryClient } from '@tanstack/vue-query'
import api from '@/plugins/axios'

definePage({ meta: { requiresAdmin: true } })

const queryClient = useQueryClient()
const activeTab   = ref(0)

const API_BASE = import.meta.env.VITE_API_URL
  ? import.meta.env.VITE_API_URL.replace(/\/$/, '')
  : 'http://localhost:8000'

// ─── Issued Certificates ──────────────────────────────────────────────────────
const { data: issuedData, isLoading: isLoadingIssued } = useQuery({
  queryKey: ['admin-certificates'],
  queryFn: async () => (await api.get('/api/dashboard/certificates')).data,
})

const certificates = computed(() => issuedData.value?.data || [])
const pagination   = computed(() => ({
  current: issuedData.value?.current_page || 1,
  last:    issuedData.value?.last_page    || 1,
  total:   issuedData.value?.total        || 0,
}))

// ─── Stats (per-course) ───────────────────────────────────────────────────────
const { data: statsData, isLoading: isLoadingStats } = useQuery({
  queryKey: ['admin-certificates-stats'],
  queryFn: async () => (await api.get('/api/dashboard/certificates/stats')).data,
})

const courseStats = computed(() => statsData.value || [])

// ─── Students & Courses for Issue Dialog ─────────────────────────────────────
const { data: studentsData } = useQuery({
  queryKey: ['admin-students-list'],
  queryFn: async () => {
    const res = await api.get('/api/dashboard/students?per_page=200')
    return res.data?.data || res.data || []
  },
})
const { data: coursesData } = useQuery({
  queryKey: ['admin-courses-list'],
  queryFn: async () => {
    const res = await api.get('/api/dashboard/courses')
    return res.data?.data || res.data || []
  },
})
const students = computed(() => studentsData.value || [])
const courses  = computed(() => coursesData.value  || [])

// ─── Issue Certificate Dialog (admin manual) ──────────────────────────────────
const isIssueOpen     = ref(false)
const isIssuing       = ref(false)
const issueForm       = ref({ user_id: null as number | null, course_id: null as number | null })
const issueSuccess    = ref('')
const issueError      = ref('')

const openIssueDialog = () => {
  issueForm.value  = { user_id: null, course_id: null }
  issueSuccess.value = ''
  issueError.value   = ''
  isIssueOpen.value  = true
}

const submitIssue = async () => {
  if (!issueForm.value.user_id || !issueForm.value.course_id) return
  isIssuing.value    = true
  issueSuccess.value = ''
  issueError.value   = ''
  try {
    const res = await api.post('/api/dashboard/certificates/issue', issueForm.value)
    issueSuccess.value = res.data.message
    queryClient.invalidateQueries({ queryKey: ['admin-certificates'] })
    queryClient.invalidateQueries({ queryKey: ['admin-certificates-stats'] })
  } catch (e: any) {
    issueError.value = e.response?.data?.message || 'Failed to issue certificate.'
  } finally {
    isIssuing.value = false
  }
}

// ─── Download Certificate ─────────────────────────────────────────────────────
const downloadCertificate = async (ulid: string, courseName: string) => {
  try {
    const res = await api.get(`/api/v1/certificates/${ulid}/download`)
    if (res.data.download_url) {
      window.location.href = res.data.download_url
    }
  } catch (e) {
    console.error('Failed to download certificate:', e)
  }
}

// ─── Regenerate Certificate ───────────────────────────────────────────────────
const regeneratingUlid = ref<string | null>(null)
const regenerateSuccess = ref('')
const regenerateError   = ref('')

const regenerateCertificate = async (ulid: string) => {
  regeneratingUlid.value = ulid
  regenerateSuccess.value = ''
  regenerateError.value   = ''
  try {
    await api.post(`/api/dashboard/certificates/${ulid}/regenerate`)
    regenerateSuccess.value = 'Certificate regeneration started. Download again in a few seconds.'
    setTimeout(() => { regenerateSuccess.value = '' }, 5000)
  } catch (e: any) {
    regenerateError.value = e.response?.data?.message || 'Failed to regenerate certificate.'
    setTimeout(() => { regenerateError.value = '' }, 5000)
  } finally {
    regeneratingUlid.value = null
  }
}

// ─── Verify Dialog ────────────────────────────────────────────────────────────
const isVerifyOpen  = ref(false)
const verifyUlid    = ref('')
const verifyUserId  = ref<number | null>(null)
const verifyCourseId = ref<number | null>(null)
const verifySignature = ref('')
const isVerifying   = ref(false)
const verifyResult  = ref<any>(null)
const verifyError   = ref('')

const openVerify = (cert: any) => {
  verifyUlid.value      = cert.ulid
  verifyUserId.value    = cert.user_id
  verifyCourseId.value  = cert.course_id
  verifySignature.value = cert.signature
  verifyResult.value = null
  verifyError.value  = ''
  isVerifyOpen.value = true
  doVerify()
}

const doVerify = async () => {
  isVerifying.value  = true
  verifyResult.value = null
  verifyError.value  = ''
  try {
    const res = await api.get(`/api/certificates/${verifyUlid.value}/verify`, {
      params: {
        signature: verifySignature.value,
        userId: verifyUserId.value,
        courseId: verifyCourseId.value
      }
    })
    verifyResult.value = res.data
  } catch (e: any) {
    verifyError.value = e.response?.data?.message || 'Certificate not found.'
  } finally {
    isVerifying.value = false
  }
}

const thumbnailUrl = (path: string | null) =>
  path ? `${API_BASE}/storage/${path}` : null
</script>

<template>
  <VCard>
    <VCardItem>
      <VCardTitle class="d-flex align-center gap-2 flex-wrap">
        <VIcon icon="tabler-certificate" color="primary" />
        {{ $t('Certificates') }}
        <VChip size="small" color="primary" class="ms-1">{{ pagination.total }}</VChip>
        <VSpacer />
        <VBtn
          color="primary"
          prepend-icon="tabler-plus"
          size="small"
          @click="openIssueDialog"
        >
          {{ $t('Issue Certificate') }}
        </VBtn>
      </VCardTitle>
      <VCardSubtitle>{{ $t('Manage and issue certificates to students') }}</VCardSubtitle>
    </VCardItem>

    <VCardText class="pb-0">
      <VTabs v-model="activeTab">
        <VTab>{{ $t('Issued Certificates') }}</VTab>
        <VTab>{{ $t('By Course') }}</VTab>
      </VTabs>
    </VCardText>
    <VDivider />

    <!-- ─── Regenerate Alerts ────────────────────────────────────────────── -->
    <VCardText v-if="regenerateSuccess || regenerateError" class="pb-0">
      <VAlert v-if="regenerateSuccess" type="success" variant="tonal" closable class="mb-2">
        {{ regenerateSuccess }}
      </VAlert>
      <VAlert v-if="regenerateError" type="error" variant="tonal" closable class="mb-2">
        {{ regenerateError }}
      </VAlert>
    </VCardText>

    <!-- ─── Tab 0: Issued Certificates ────────────────────────────────────── -->
    <VCardText v-if="activeTab === 0">
      <div v-if="isLoadingIssued" class="d-flex justify-center pa-8">
        <VProgressCircular indeterminate color="primary" />
      </div>

      <VTable v-else class="border rounded">
        <thead>
          <tr>
            <th>{{ $t('Student') }}</th>
            <th>{{ $t('Course') }}</th>
            <th>{{ $t('Category') }}</th>
            <th>{{ $t('Issued At') }}</th>
            <th class="text-center">{{ $t('Actions') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="certificates.length === 0">
            <td colspan="5" class="text-center text-medium-emphasis pa-8">
              {{ $t('No certificates issued yet.') }}
            </td>
          </tr>
          <tr v-for="cert in certificates" :key="cert.id">
            <td>
              <div class="d-flex align-center gap-2">
                <VAvatar color="primary" variant="tonal" size="34">
                  <VIcon icon="tabler-user" size="18" />
                </VAvatar>
                <div class="d-flex flex-column">
                  <span class="font-weight-medium text-body-2">{{ cert.user?.name }}</span>
                  <span class="text-caption text-medium-emphasis">{{ cert.user?.email }}</span>
                </div>
              </div>
            </td>

            <td>
              <div class="d-flex align-center gap-2">
                <VAvatar rounded size="32" color="secondary" variant="tonal">
                  <VImg v-if="thumbnailUrl(cert.course?.thumbnail)" :src="thumbnailUrl(cert.course?.thumbnail)" cover />
                  <VIcon v-else icon="tabler-book" size="16" />
                </VAvatar>
                <span class="text-body-2 font-weight-medium">{{ cert.course?.title }}</span>
              </div>
            </td>

            <td>
              <VChip size="small" color="info" variant="tonal">{{ cert.course?.category || '—' }}</VChip>
            </td>

            <td class="text-caption text-medium-emphasis">
              {{ new Date(cert.issued_at).toLocaleDateString() }}
            </td>

            <td class="text-center" style="white-space:nowrap;">
              <!-- Download PDF -->
              <VTooltip text="Download Certificate PDF" location="top">
                <template #activator="{ props }">
                  <VBtn
                    v-bind="props"
                    icon="tabler-download"
                    variant="text"
                    size="small"
                    color="primary"
                    @click="downloadCertificate(cert.ulid, cert.course?.title || 'certificate')"
                  />
                </template>
              </VTooltip>
              <!-- Verify -->
              <VTooltip text="Verify Certificate" location="top">
                <template #activator="{ props }">
                  <VBtn
                    v-bind="props"
                    icon="tabler-shield-check"
                    variant="text"
                    size="small"
                    color="success"
                    @click="openVerify(cert)"
                  />
                </template>
              </VTooltip>
              <!-- Regenerate -->
              <VTooltip text="Regenerate Certificate PDF" location="top">
                <template #activator="{ props }">
                  <VBtn
                    v-bind="props"
                    icon="tabler-refresh"
                    variant="text"
                    size="small"
                    color="warning"
                    :loading="regeneratingUlid === cert.ulid"
                    @click="regenerateCertificate(cert.ulid)"
                  />
                </template>
              </VTooltip>
            </td>
          </tr>
        </tbody>
      </VTable>
    </VCardText>

    <!-- ─── Tab 1: By Course ───────────────────────────────────────────────── -->
    <VCardText v-else>
      <div v-if="isLoadingStats" class="d-flex justify-center pa-8">
        <VProgressCircular indeterminate color="primary" />
      </div>
      <div v-else-if="courseStats.length === 0" class="text-center text-medium-emphasis pa-8">
        {{ $t('No certificates issued yet.') }}
      </div>
      <VTable v-else class="border rounded">
        <thead>
          <tr>
            <th>{{ $t('Course') }}</th>
            <th>{{ $t('Category') }}</th>
            <th class="text-center">{{ $t('Certificates Issued') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="stat in courseStats" :key="stat.course_id">
            <td>
              <div class="d-flex align-center gap-2">
                <VAvatar rounded size="32" color="secondary" variant="tonal">
                  <VImg v-if="thumbnailUrl(stat.course?.thumbnail)" :src="thumbnailUrl(stat.course?.thumbnail)" cover />
                  <VIcon v-else icon="tabler-book" size="16" />
                </VAvatar>
                <span class="font-weight-medium">{{ stat.course?.title }}</span>
              </div>
            </td>
            <td>
              <VChip size="small" color="info" variant="tonal">{{ stat.course?.category || '—' }}</VChip>
            </td>
            <td class="text-center">
              <VChip size="small" color="success" variant="tonal">
                <VIcon icon="tabler-certificate" size="13" class="me-1" />
                {{ stat.issued_count }}
              </VChip>
            </td>
          </tr>
        </tbody>
      </VTable>
    </VCardText>
  </VCard>

  <!-- ─── Issue Certificate Dialog ─────────────────────────────────────────── -->
  <VDialog v-model="isIssueOpen" max-width="520">
    <VCard>
      <VCardItem>
        <VCardTitle class="d-flex align-center gap-2">
          <VIcon icon="tabler-certificate" color="primary" />
          {{ $t('Issue Certificate Manually') }}
        </VCardTitle>
        <VCardSubtitle>{{ $t('Grant a certificate directly to any student') }}</VCardSubtitle>
      </VCardItem>

      <VCardText>
        <VRow>
          <VCol cols="12">
            <VAutocomplete
              v-model="issueForm.user_id"
              :items="students"
              item-title="name"
              item-value="id"
              :label="$t('Select Student')"
              variant="outlined"
              prepend-inner-icon="tabler-user"
              :no-data-text="$t('No students found')"
            >
              <template #item="{ item, props }">
                <VListItem v-bind="props">
                  <template #subtitle>{{ item.raw.email }}</template>
                </VListItem>
              </template>
            </VAutocomplete>
          </VCol>
          <VCol cols="12">
            <VAutocomplete
              v-model="issueForm.course_id"
              :items="courses"
              item-title="title"
              item-value="id"
              :label="$t('Select Course')"
              variant="outlined"
              prepend-inner-icon="tabler-book"
              :no-data-text="$t('No courses found')"
            />
          </VCol>
        </VRow>

        <VAlert v-if="issueSuccess" type="success" variant="tonal" class="mt-3" closable>
          {{ issueSuccess }}
        </VAlert>
        <VAlert v-if="issueError" type="error" variant="tonal" class="mt-3" closable>
          {{ issueError }}
        </VAlert>
      </VCardText>

      <VCardActions>
        <VSpacer />
        <VBtn variant="text" @click="isIssueOpen = false">{{ $t('Close') }}</VBtn>
        <VBtn
          color="primary"
          :loading="isIssuing"
          :disabled="!issueForm.user_id || !issueForm.course_id"
          prepend-icon="tabler-certificate"
          @click="submitIssue"
        >
          {{ $t('Issue Certificate') }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>

  <!-- ─── Verify Dialog ──────────────────────────────────────────────────────── -->
  <VDialog v-model="isVerifyOpen" max-width="480">
    <VCard>
      <VCardItem>
        <VCardTitle class="d-flex align-center gap-2">
          <VIcon icon="tabler-shield-check" color="success" />
          {{ $t('Certificate Verification') }}
        </VCardTitle>
      </VCardItem>

      <VCardText>
        <div v-if="isVerifying" class="d-flex justify-center pa-4">
          <VProgressCircular indeterminate color="success" />
        </div>

        <VAlert v-else-if="verifyResult" type="success" variant="tonal">
          <div class="font-weight-bold mb-2 text-body-1">✅ {{ $t('Valid Certificate') }}</div>
          <div class="text-body-2 mb-1">
            <strong>{{ $t('Student') }}:</strong> {{ verifyResult.student }}
          </div>
          <div class="text-body-2 mb-1">
            <strong>{{ $t('Course') }}:</strong> {{ verifyResult.course }}
          </div>
          <div class="text-body-2 mb-1">
            <strong>{{ $t('Issued') }}:</strong> {{ verifyResult.issued_at }}
          </div>
          <div class="text-caption text-medium-emphasis mt-2 font-weight-mono">
            ID: {{ verifyResult.ulid }}
          </div>
        </VAlert>

        <VAlert v-else-if="verifyError" type="error" variant="tonal">
          {{ verifyError }}
        </VAlert>
      </VCardText>

      <VCardActions>
        <VSpacer />
        <VBtn variant="text" @click="isVerifyOpen = false">{{ $t('Close') }}</VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

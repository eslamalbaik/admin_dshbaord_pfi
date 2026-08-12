<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { useQuery, useMutation, useQueryClient } from '@tanstack/vue-query'
import api from '@/plugins/axios'

definePage({ meta: { requiresAdmin: true } })

const queryClient = useQueryClient()

// ─── Tabs ───
const activeTab = ref(0)

// ═══════════════════════════════════════════════════════════════════════════
//  Tab 0: المقاولون — إنشاء شهادة عضوية
// ═══════════════════════════════════════════════════════════════════════════
const contractorPage = ref(1)
const contractorSearch = ref('')
const contractorStatus = ref<string | null>('active')

const statusOptions = [
  { value: null, title: 'الكل' },
  { value: 'active', title: 'نشط' },
  { value: 'pending', title: 'معلّق' },
  { value: 'expired', title: 'منتهي' },
  { value: 'suspended', title: 'موقوف' },
]

watch([contractorSearch, contractorStatus], () => contractorPage.value = 1)

const { data: contractorsData, isLoading: loadingContractors } = useQuery({
  queryKey: computed(() => ['membership-contractors', contractorPage.value, contractorSearch.value, contractorStatus.value]),
  queryFn: async () => {
    const params: Record<string, any> = {
      page: contractorPage.value,
      per_page: 15,
    }
    if (contractorSearch.value) params.search = contractorSearch.value
    if (contractorStatus.value) params.status = contractorStatus.value

    return (await api.get('/api/v1/contractors', { params })).data
  },
})

const contractors = computed(() => contractorsData.value?.items ?? contractorsData.value?.data ?? [])
const contractorsLastPage = computed(() => contractorsData.value?.meta?.last_page ?? contractorsData.value?.last_page ?? 1)

// ─── إصدار شهادة ───
const isIssueOpen = ref(false)
const selectedContractor = ref<any>(null)
const issueNotes = ref('')
const issueError = ref('')
const issueAddress = ref('')
const issueDecisionNumber = ref('')
const issueDecisionDate = ref('')
const loadingIssueDefaults = ref(false)

async function openIssueDialog(c: any) {
  selectedContractor.value = c
  issueNotes.value = ''
  issueError.value = ''
  issueAddress.value = ''
  issueDecisionNumber.value = ''
  issueDecisionDate.value = ''
  isIssueOpen.value = true

  loadingIssueDefaults.value = true
  try {
    const full = (await api.get(`/api/v1/contractors/${c.id}`)).data?.items
    issueAddress.value = full?.city || full?.address || 'غزة'
    issueDecisionNumber.value = full?.classification_decision_number || ''
    issueDecisionDate.value = full?.classification_decision_date?.slice(0, 10) || ''
  } finally {
    loadingIssueDefaults.value = false
  }
}

const issueMutation = useMutation({
  mutationFn: async () => {
    return (await api.post('/api/v1/dashboard/certificate-requests/issue-membership', {
      contractor_id: selectedContractor.value.id,
      notes: issueNotes.value || undefined,
      address: issueAddress.value || undefined,
      decision_number: issueDecisionNumber.value || undefined,
      decision_date: issueDecisionDate.value || undefined,
    })).data
  },
  onSuccess: (data: any) => {
    queryClient.invalidateQueries({ queryKey: ['issued-certificates'] })

    const certificateUrl = data?.items?.certificate_url
    if (certificateUrl)
      window.open(certificateUrl, '_blank')
  },
  onError: (e: any) => {
    console.error('Issue error:', e, e.response?.data);
    const serverMessage = e?.response?.data?.message;
    const errors = e?.response?.data?.errors ? JSON.stringify(e.response.data.errors) : '';
    issueError.value = serverMessage ? `${serverMessage} ${errors}` : 'حدث خطأ أثناء إصدار الشهادة. راجع الكونسول.';
  },
})

// ═══════════════════════════════════════════════════════════════════════════
//  Tab 1: الشهادات الصادرة
// ═══════════════════════════════════════════════════════════════════════════
const certPage = ref(1)
const certSearch = ref('')
const certStatusFilter = ref<string | null>('issued')

const certStatusOptions = [
  { value: null, title: 'الكل' },
  { value: 'pending', title: 'قيد المراجعة' },
  { value: 'approved', title: 'موافق عليه' },
  { value: 'issued', title: 'تم الإصدار' },
  { value: 'rejected', title: 'مرفوض' },
]

const certStatusColor: Record<string, string> = {
  pending: 'warning',
  approved: 'info',
  issued: 'success',
  rejected: 'error',
}

watch([certSearch, certStatusFilter], () => certPage.value = 1)

const { data: certsData, isLoading: loadingCerts } = useQuery({
  queryKey: computed(() => ['issued-certificates', certPage.value, certSearch.value, certStatusFilter.value]),
  queryFn: async () => {
    const params: Record<string, any> = {
      page: certPage.value,
      type: 'membership',
    }
    if (certStatusFilter.value) params.status = certStatusFilter.value
    if (certSearch.value) params.search = certSearch.value

    return (await api.get('/api/v1/dashboard/certificate-requests', { params })).data
  },
})

const certificates = computed(() => certsData.value?.items ?? [])
const certsLastPage = computed(() => certsData.value?.meta?.last_page ?? 1)

// ─── حذف شهادة صادرة ───
const isDeleteOpen = ref(false)
const certToDelete = ref<any>(null)

function openDeleteDialog(cert: any) {
  certToDelete.value = cert
  isDeleteOpen.value = true
}

const deleteMutation = useMutation({
  mutationFn: async () => {
    return (await api.delete(`/api/v1/dashboard/certificate-requests/${certToDelete.value.id}`)).data
  },
  onSuccess: () => {
    queryClient.invalidateQueries({ queryKey: ['issued-certificates'] })
    isDeleteOpen.value = false
    certToDelete.value = null
  },
})

// ─── تصدير CSV ───
const isExporting = ref(false)

function exportCsv() {
  isExporting.value = true
  try {
    const rows = certificates.value.map((r: any) => ({
      '#': r.id,
      'المقاول': r.contractor ?? '',
      'رقم العضوية': r.membership_number ?? '',
      'الحالة': r.status_label ?? r.status,
      'تاريخ الطلب': fmtDate(r.request_date),
      'تاريخ الإصدار': fmtDate(r.issue_date),
    }))

    if (!rows.length) { isExporting.value = false; return }

    const headers = Object.keys(rows[0])
    const csvContent = '\uFEFF' + [
      headers.join(','),
      ...rows.map((row: any) =>
        headers.map(h => `"${String(row[h]).replace(/"/g, '""')}"`).join(',')
      ),
    ].join('\n')

    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' })
    const link = document.createElement('a')
    link.href = URL.createObjectURL(blob)
    link.download = `شهادات_العضوية_${new Date().toISOString().slice(0, 10)}.csv`
    link.click()
    URL.revokeObjectURL(link.href)
  } finally {
    isExporting.value = false
  }
}

// ─── Helpers ───
function fmtDate(d: string | null) {
  if (!d) return '—'
  return new Date(d).toLocaleDateString('ar-EG', { year: 'numeric', month: 'short', day: 'numeric' })
}

const contractorStatusColor: Record<string, string> = {
  active: 'success',
  pending: 'warning',
  expired: 'error',
  suspended: 'error',
}

const contractorStatusLabel: Record<string, string> = {
  active: 'نشط',
  pending: 'معلّق',
  expired: 'منتهي',
  suspended: 'موقوف',
}
</script>

<template>
  <div>
    <div class="mb-6">
      <h1 class="text-h4 font-weight-bold">شهادة العضوية</h1>
      <p class="text-body-2 text-medium-emphasis mb-0">
        إصدار شهادات العضوية للمقاولين وعرض الشهادات الصادرة
      </p>
    </div>

    <VCard>
      <VCardText class="pb-0">
        <VTabs v-model="activeTab">
          <VTab>
            <VIcon icon="tabler-users" class="me-2" size="18" />
            المقاولون
          </VTab>
          <VTab>
            <VIcon icon="tabler-certificate" class="me-2" size="18" />
            الشهادات الصادرة
          </VTab>
        </VTabs>
      </VCardText>
      <VDivider />

      <!-- ═══ Tab 0: المقاولون ═══ -->
      <VCardText v-if="activeTab === 0">
        <VRow dense class="mb-4">
          <VCol cols="12" md="6">
            <VTextField
              v-model="contractorSearch"
              label="بحث باسم المقاول أو رقم العضوية"
              prepend-inner-icon="tabler-search"
              density="compact"
              clearable
            />
          </VCol>
          <VCol cols="12" md="6">
            <VSelect
              v-model="contractorStatus"
              :items="statusOptions"
              label="حالة المقاول"
              density="compact"
              clearable
            />
          </VCol>
        </VRow>

        <VProgressLinear v-if="loadingContractors" indeterminate color="primary" />

        <VAlert v-else-if="contractors.length === 0" type="info" variant="tonal" class="mb-4">
          لا يوجد مقاولون مطابقون لمعايير البحث.
        </VAlert>

        <VTable v-else class="border rounded">
          <thead>
            <tr>
              <th>#</th>
              <th>اسم المقاول</th>
              <th>رقم العضوية</th>
              <th>التصنيف</th>
              <th>الحالة</th>
              <th class="text-center">إجراء</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="c in contractors" :key="c.id">
              <td class="text-caption">{{ c.id }}</td>
              <td class="font-weight-medium">{{ c.name }}</td>
              <td>{{ c.membership_number ?? '—' }}</td>
              <td>
                <VChip v-if="c.classification" size="x-small" color="primary" variant="tonal">
                  {{ c.classification }}
                </VChip>
                <span v-else class="text-medium-emphasis">—</span>
              </td>
              <td>
                <VChip
                  size="x-small"
                  :color="contractorStatusColor[c.status] ?? 'secondary'"
                >
                  {{ contractorStatusLabel[c.status] ?? c.status }}
                </VChip>
              </td>
              <td class="text-center">
                <VBtn
                  color="success"
                  size="small"
                  variant="tonal"
                  prepend-icon="tabler-certificate"
                  @click="openIssueDialog(c)"
                >
                  إنشاء شهادة
                </VBtn>
              </td>
            </tr>
          </tbody>
        </VTable>

        <div v-if="contractorsLastPage > 1" class="d-flex justify-center mt-4">
          <VPagination v-model="contractorPage" :length="contractorsLastPage" total-visible="7" />
        </div>
      </VCardText>

      <!-- ═══ Tab 1: الشهادات الصادرة ═══ -->
      <VCardText v-else>
        <VRow dense class="mb-4">
          <VCol cols="12" md="5">
            <VTextField
              v-model="certSearch"
              label="بحث باسم المقاول أو رقم العضوية"
              prepend-inner-icon="tabler-search"
              density="compact"
              clearable
            />
          </VCol>
          <VCol cols="12" md="4">
            <VSelect
              v-model="certStatusFilter"
              :items="certStatusOptions"
              label="الحالة"
              density="compact"
              clearable
            />
          </VCol>
          <VCol cols="12" md="3" class="d-flex align-center">
            <VBtn
              color="success"
              variant="tonal"
              prepend-icon="tabler-file-export"
              :loading="isExporting"
              :disabled="certificates.length === 0"
              block
              @click="exportCsv"
            >
              تصدير CSV
            </VBtn>
          </VCol>
        </VRow>

        <VProgressLinear v-if="loadingCerts" indeterminate color="primary" />

        <VAlert v-else-if="certificates.length === 0" type="info" variant="tonal">
          لا توجد شهادات عضوية مطابقة.
        </VAlert>

        <VTable v-else class="border rounded">
          <thead>
            <tr>
              <th>#</th>
              <th>المقاول</th>
              <th>رقم العضوية</th>
              <th>الحالة</th>
              <th>تاريخ الطلب</th>
              <th>تاريخ الإصدار</th>
              <th class="text-center">الشهادة</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="r in certificates" :key="r.id">
              <td class="text-caption">{{ r.id }}</td>
              <td class="font-weight-medium">{{ r.contractor ?? '—' }}</td>
              <td>{{ r.membership_number ?? '—' }}</td>
              <td>
                <VChip size="x-small" :color="certStatusColor[r.status] ?? 'secondary'">
                  {{ r.status_label ?? r.status }}
                </VChip>
              </td>
              <td class="text-body-2">{{ fmtDate(r.request_date) }}</td>
              <td class="text-body-2">{{ fmtDate(r.issue_date) }}</td>
              <td class="text-center">
                <VTooltip v-if="r.certificate_url" text="تحميل الشهادة PDF" location="top">
                  <template #activator="{ props }">
                    <VBtn
                      v-bind="props"
                      icon="tabler-download"
                      size="x-small"
                      variant="text"
                      color="success"
                      :href="r.certificate_url"
                      target="_blank"
                    />
                  </template>
                </VTooltip>
                <VChip v-else size="x-small" color="warning" variant="tonal">
                  لم تُصدر بعد
                </VChip>
                <VTooltip text="حذف الشهادة" location="top">
                  <template #activator="{ props }">
                    <VBtn
                      v-bind="props"
                      icon="tabler-trash"
                      size="x-small"
                      variant="text"
                      color="error"
                      @click="openDeleteDialog(r)"
                    />
                  </template>
                </VTooltip>
              </td>
            </tr>
          </tbody>
        </VTable>

        <div v-if="certsLastPage > 1" class="d-flex justify-center mt-4">
          <VPagination v-model="certPage" :length="certsLastPage" total-visible="7" />
        </div>
      </VCardText>
    </VCard>

    <!-- ═══ حوار إصدار شهادة عضوية ═══ -->
    <VDialog v-model="isIssueOpen" max-width="520">
      <VCard v-if="selectedContractor">
        <VCardItem>
          <VCardTitle class="d-flex align-center gap-2">
            <VIcon icon="tabler-certificate" color="success" />
            إصدار شهادة عضوية
          </VCardTitle>
          <VCardSubtitle>
            سيتم إنشاء شهادة عضوية PDF تلقائياً وإرسال إشعار للمقاول.
          </VCardSubtitle>
        </VCardItem>

        <VCardText>
          <VAlert type="info" variant="tonal" class="mb-4">
            <div class="d-flex flex-column gap-1">
              <div><strong>المقاول:</strong> {{ selectedContractor.name }}</div>
              <div><strong>رقم العضوية:</strong> {{ selectedContractor.membership_number ?? '—' }}</div>
              <div v-if="selectedContractor.classification">
                <strong>التصنيف:</strong> {{ selectedContractor.classification }}
              </div>
            </div>
          </VAlert>

          <VTextField
            v-model="issueAddress"
            label="عنوان الشركة"
            dir="rtl"
            class="mb-4"
            :loading="loadingIssueDefaults"
            placeholder="غزة"
          />

          <VRow dense class="mb-4">
            <VCol cols="6">
              <VTextField
                v-model="issueDecisionNumber"
                label="رقم قرار التصنيف"
                dir="rtl"
                :loading="loadingIssueDefaults"
                placeholder="مثال: 04/2022"
              />
            </VCol>
            <VCol cols="6">
              <VTextField
                v-model="issueDecisionDate"
                label="تاريخ قرار التصنيف"
                type="date"
                :loading="loadingIssueDefaults"
              />
            </VCol>
          </VRow>

          <VTextarea
            v-model="issueNotes"
            label="ملاحظات (اختياري)"
            rows="2"
            dir="rtl"
            placeholder="مثال: صادرة بطلب خاص من الإدارة"
          />

          <VAlert v-if="issueError" type="error" variant="tonal" class="mt-3">
            {{ issueError }}
          </VAlert>

          <VAlert v-if="issueMutation.isSuccess?.value" type="success" variant="tonal" class="mt-3">
            تم إصدار الشهادة بنجاح! يمكنك تحميلها من تبويب "الشهادات الصادرة".
          </VAlert>
        </VCardText>

        <VCardActions>
          <VSpacer />
          <VBtn variant="text" @click="isIssueOpen = false">إلغاء</VBtn>
          <VBtn
            color="success"
            :loading="issueMutation.isPending?.value"
            :disabled="issueMutation.isSuccess?.value"
            prepend-icon="tabler-certificate"
            @click="issueMutation.mutate()"
          >
            إصدار الشهادة
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- ═══ حوار تأكيد حذف شهادة ═══ -->
    <VDialog v-model="isDeleteOpen" max-width="420">
      <VCard v-if="certToDelete">
        <VCardItem>
          <VCardTitle class="d-flex align-center gap-2">
            <VIcon icon="tabler-alert-triangle" color="error" />
            حذف الشهادة
          </VCardTitle>
        </VCardItem>

        <VCardText>
          هل أنت متأكد من حذف شهادة عضوية <strong>{{ certToDelete.contractor }}</strong>؟
          سيتم حذف ملف الشهادة نهائياً ولا يمكن التراجع عن هذا الإجراء.

          <VAlert v-if="deleteMutation.isError?.value" type="error" variant="tonal" class="mt-3">
            حدث خطأ أثناء حذف الشهادة.
          </VAlert>
        </VCardText>

        <VCardActions>
          <VSpacer />
          <VBtn variant="text" @click="isDeleteOpen = false">إلغاء</VBtn>
          <VBtn
            color="error"
            :loading="deleteMutation.isPending?.value"
            prepend-icon="tabler-trash"
            @click="deleteMutation.mutate()"
          >
            حذف نهائياً
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { useQuery, useMutation, useQueryClient } from '@tanstack/vue-query'
import api from '@/plugins/axios'
import { firstFile, type SingleFileModel } from '@/utils/files'

definePage({ meta: { requiresAdmin: true } })

const queryClient = useQueryClient()

const page = ref(1)
const statusFilter = ref<string | null>(null)
const typeFilter = ref<string | null>(null)
const search = ref('')

const statusOptions = [
  { value: 'pending', title: 'قيد المراجعة' },
  { value: 'approved', title: 'موافق عليه' },
  { value: 'issued', title: 'تم الإصدار' },
  { value: 'rejected', title: 'مرفوض' },
]

const typeOptions = [
  { value: 'membership', title: 'شهادة العضوية' },
  { value: 'good_standing', title: 'شهادة حسن السير والسلوك' },
  { value: 'classification', title: 'شهادة التصنيف' },
  { value: 'experience', title: 'شهادة الخبرة والمشاريع' },
]

const statusColor: Record<string, string> = {
  pending: 'warning',
  approved: 'info',
  issued: 'success',
  rejected: 'error',
}

watch([statusFilter, typeFilter, search], () => page.value = 1)

const { data, isLoading } = useQuery({
  queryKey: computed(() => ['certificate-requests', page.value, statusFilter.value, typeFilter.value, search.value]),
  queryFn: async () => {
    const params: Record<string, any> = { page: page.value }
    if (statusFilter.value) params.status = statusFilter.value
    if (typeFilter.value) params.type = typeFilter.value
    if (search.value) params.search = search.value

    return (await api.get('/api/v1/dashboard/certificate-requests', { params })).data
  },
})

const requests = computed(() => data.value?.items ?? [])
const lastPage = computed(() => data.value?.meta?.last_page ?? 1)

// ─── تفاصيل الطلب ───
const isViewOpen = ref(false)
const selected = ref<any>(null)
const rejectReason = ref('')
const certificateFile = ref<SingleFileModel>(null)
const actionError = ref('')

const detailQueryEnabled = computed(() => isViewOpen.value && !!selected.value?.id)
const { data: detailData } = useQuery({
  queryKey: computed(() => ['certificate-request', selected.value?.id]),
  queryFn: async () =>
    (await api.get(`/api/v1/dashboard/certificate-requests/${selected.value.id}`)).data,
  enabled: detailQueryEnabled,
})

const detail = computed(() => detailData.value?.items ?? selected.value)

const openRequest = (r: any) => {
  selected.value = r
  rejectReason.value = ''
  certificateFile.value = null
  actionError.value = ''
  isViewOpen.value = true
}

const refresh = () => {
  queryClient.invalidateQueries({ queryKey: ['certificate-requests'] })
  queryClient.invalidateQueries({ queryKey: ['certificate-request'] })
}

const approveMutation = useMutation({
  mutationFn: async () =>
    (await api.post(`/api/v1/dashboard/certificate-requests/${selected.value.id}/approve`)).data,
  onSuccess: (d: any) => {
    refresh()
    selected.value = d?.items ?? selected.value
  },
  onError: (e: any) => actionError.value = e?.response?.data?.message || 'فشل تنفيذ الإجراء.',
})

const rejectMutation = useMutation({
  mutationFn: async () =>
    (await api.post(`/api/v1/dashboard/certificate-requests/${selected.value.id}/reject`, { reject_reason: rejectReason.value })).data,
  onSuccess: (d: any) => {
    refresh()
    selected.value = d?.items ?? selected.value
  },
  onError: (e: any) => actionError.value = e?.response?.data?.message || 'فشل تنفيذ الإجراء.',
})

const issueMutation = useMutation({
  mutationFn: async () => {
    const file = firstFile(certificateFile.value)
    if (!file)
      throw new Error('لم يتم اختيار ملف الشهادة.')

    const fd = new FormData()
    fd.append('certificate', file)

    return (await api.post(`/api/v1/dashboard/certificate-requests/${selected.value.id}/issue`, fd)).data
  },
  onSuccess: (d: any) => {
    refresh()
    selected.value = d?.items ?? selected.value
    certificateFile.value = null
  },
  onError: (e: any) => actionError.value = e?.response?.data?.message || 'فشل إصدار الشهادة.',
})

const deleteMutation = useMutation({
  mutationFn: async (id: number) => (await api.delete(`/api/v1/dashboard/certificate-requests/${id}`)).data,
  onSuccess: () => {
    refresh()
    isViewOpen.value = false
  },
})

const confirmDelete = (r: any) => {
  if (confirm('هل تريد حذف هذا الطلب نهائيًا؟'))
    deleteMutation.mutate(r.id)
}

function requirementTypeLabel(type: string): string {
  const labels: Record<string, string> = {
    late_fees: 'غرامات التأخير',
    overdue_subscription: 'رسوم الاشتراك المتأخرة',
    pending_dispute: 'نزاع قيد المعالجة',
    missing_documents: 'وثائق مفقودة',
    other: 'متطلب آخر',
  }

  return labels[type] ?? type
}

function fmtDate(d: string | null) {
  if (!d) return '—'
  return new Date(d).toLocaleDateString('ar-EG', { year: 'numeric', month: 'short', day: 'numeric' })
}
</script>

<template>
  <div>
    <div class="mb-6">
      <h1 class="text-h4 font-weight-bold">طلبات شهادات العضوية</h1>
      <p class="text-body-2 text-medium-emphasis mb-0">
        مراجعة طلبات الشهادات الواردة من المقاولين — موافقة، رفض، أو إصدار الشهادة
      </p>
    </div>

    <!-- Filters -->
    <VCard class="mb-6 pa-4">
      <VRow dense>
        <VCol cols="12" md="4">
          <VTextField
            v-model="search"
            label="بحث باسم المقاول أو رقم العضوية"
            prepend-inner-icon="tabler-search"
            density="compact"
            clearable
          />
        </VCol>
        <VCol cols="6" md="4">
          <VSelect
            v-model="statusFilter"
            :items="statusOptions"
            label="الحالة"
            density="compact"
            clearable
          />
        </VCol>
        <VCol cols="6" md="4">
          <VSelect
            v-model="typeFilter"
            :items="typeOptions"
            label="نوع الشهادة"
            density="compact"
            clearable
          />
        </VCol>
      </VRow>
    </VCard>

    <VProgressLinear v-if="isLoading" indeterminate color="primary" />

    <VCard v-else-if="requests.length === 0" class="text-center py-12">
      <VIcon icon="tabler-certificate-off" size="64" color="disabled" class="mb-3" />
      <p class="text-h6 text-medium-emphasis">لا توجد طلبات مطابقة.</p>
    </VCard>

    <template v-else>
      <VCard>
        <VTable>
          <thead>
            <tr>
              <th>#</th>
              <th>المقاول</th>
              <th>رقم العضوية</th>
              <th>نوع الشهادة</th>
              <th>الحالة</th>
              <th>تاريخ الطلب</th>
              <th class="text-center">إجراءات</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="r in requests" :key="r.id" style="cursor: pointer" @click="openRequest(r)">
              <td>{{ r.id }}</td>
              <td class="font-weight-medium">{{ r.contractor ?? '—' }}</td>
              <td>{{ r.membership_number ?? '—' }}</td>
              <td>{{ r.type_label ?? r.type }}</td>
              <td>
                <VChip size="x-small" :color="statusColor[r.status] ?? 'secondary'">
                  {{ r.status_label ?? r.status }}
                </VChip>
              </td>
              <td class="text-body-2">{{ fmtDate(r.request_date) }}</td>
              <td class="text-center" @click.stop>
                <VBtn icon="tabler-eye" size="x-small" variant="text" @click="openRequest(r)" />
                <VBtn icon="tabler-trash" size="x-small" variant="text" color="error" @click="confirmDelete(r)" />
              </td>
            </tr>
          </tbody>
        </VTable>
      </VCard>

      <div v-if="lastPage > 1" class="d-flex justify-center mt-4">
        <VPagination v-model="page" :length="lastPage" total-visible="7" />
      </div>
    </template>

    <!-- ─── Request Dialog ─── -->
    <VDialog v-model="isViewOpen" max-width="720" scrollable>
      <VCard v-if="detail">
        <VCardTitle class="d-flex align-center justify-space-between pt-4">
          <span class="text-h6">طلب #{{ detail.id }} — {{ detail.type_label ?? detail.type }}</span>
          <VChip size="small" :color="statusColor[detail.status] ?? 'secondary'">
            {{ detail.status_label ?? detail.status }}
          </VChip>
        </VCardTitle>

        <VCardText>
          <VAlert v-if="actionError" type="error" variant="tonal" class="mb-4">
            {{ actionError }}
          </VAlert>

          <div class="d-flex flex-wrap gap-4 mb-4 text-body-2">
            <div><strong>المقاول:</strong> {{ detail.contractor ?? '—' }}</div>
            <div><strong>رقم العضوية:</strong> {{ detail.membership_number ?? '—' }}</div>
            <div><strong>تاريخ الطلب:</strong> {{ fmtDate(detail.request_date) }}</div>
            <div v-if="detail.issue_date"><strong>تاريخ الإصدار:</strong> {{ fmtDate(detail.issue_date) }}</div>
          </div>

          <VCard v-if="detail.notes" variant="tonal" color="secondary" class="pa-4 mb-4 rounded-lg">
            <p class="text-body-2 font-weight-medium mb-1">ملاحظات المقاول:</p>
            <p class="text-body-2" style="white-space: pre-wrap">{{ detail.notes }}</p>
          </VCard>

          <!-- المتطلبات المالية غير المسوّاة -->
          <VAlert
            v-if="detail.requirement_issues?.length"
            type="warning"
            variant="tonal"
            class="mb-4"
          >
            <p class="font-weight-bold mb-2">متطلبات غير مسوّاة على المقاول:</p>
            <ul class="ps-4">
              <li v-for="(issue, i) in detail.requirement_issues" :key="i" class="mb-1">
                <strong>{{ requirementTypeLabel(issue.type) }}:</strong>
                {{ issue.description }}
                <template v-if="issue.amount"> — {{ issue.amount }} ₪</template>
              </li>
            </ul>
          </VAlert>

          <VAlert v-if="detail.reject_reason" type="error" variant="tonal" class="mb-4">
            <strong>سبب الرفض:</strong> {{ detail.reject_reason }}
          </VAlert>

          <VBtn
            v-if="detail.certificate_url"
            color="success"
            variant="tonal"
            prepend-icon="tabler-download"
            :href="detail.certificate_url"
            target="_blank"
            class="mb-4"
          >
            تحميل الشهادة الصادرة
          </VBtn>

          <!-- إجراءات حسب الحالة -->
          <template v-if="detail.status === 'pending'">
            <VDivider class="my-4" />
            <p class="text-body-2 font-weight-medium mb-2">قرار المراجعة:</p>
            <div class="d-flex gap-3 mb-4">
              <VBtn
                color="info"
                prepend-icon="tabler-check"
                :loading="approveMutation.isPending.value"
                @click="approveMutation.mutate()"
              >
                موافقة
              </VBtn>
            </div>
            <VTextarea
              v-model="rejectReason"
              label="سبب الرفض (مطلوب عند الرفض)"
              rows="2"
              dir="rtl"
            />
            <VBtn
              color="error"
              variant="tonal"
              prepend-icon="tabler-x"
              class="mt-2"
              :disabled="!rejectReason"
              :loading="rejectMutation.isPending.value"
              @click="rejectMutation.mutate()"
            >
              رفض الطلب
            </VBtn>
          </template>

          <template v-if="detail.status === 'pending' || detail.status === 'approved'">
            <VDivider class="my-4" />
            <p class="text-body-2 font-weight-medium mb-2">إصدار الشهادة (ملف PDF):</p>
            <VFileInput
              v-model="certificateFile"
              label="ملف الشهادة"
              accept="application/pdf"
              density="compact"
              prepend-icon="tabler-file-type-pdf"
            />
            <VBtn
              color="success"
              prepend-icon="tabler-certificate"
              :disabled="!firstFile(certificateFile)"
              :loading="issueMutation.isPending.value"
              @click="issueMutation.mutate()"
            >
              إصدار الشهادة
            </VBtn>
          </template>
        </VCardText>

        <VCardActions>
          <VBtn color="error" variant="text" @click="confirmDelete(detail)">حذف</VBtn>
          <VSpacer />
          <VBtn variant="text" @click="isViewOpen = false">إغلاق</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>

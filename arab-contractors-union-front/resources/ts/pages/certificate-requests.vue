<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { useQuery, useMutation, useQueryClient } from '@tanstack/vue-query'
import api from '@/plugins/axios'
import { firstFile, type SingleFileModel } from '@/utils/files'
import CertificatePreviewDialog from '@/components/dialogs/CertificatePreviewDialog.vue'

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
const manualUpload = ref(false)
const actionError = ref('')

// نافذة عرض الشهادة + حالة وصولها للمقاول (بدل فتح الرابط بتبويب جديد)
const previewOpen = ref(false)
const previewId = ref<number | null>(null)
function openPreview(id: number) {
  previewId.value = id
  previewOpen.value = true
}

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
  manualUpload.value = false
  actionError.value = ''
  isViewOpen.value = true
}

// فتح طلب من الرابط (‎?id=‎، مثلاً عند الضغط على إشعار)
const route = useRoute()
watch(() => route.query.id, (id) => {
  const rId = Number(id)
  if (Number.isInteger(rId) && rId > 0 && !(isViewOpen.value && selected.value?.id === rId))
    openRequest({ id: rId })
}, { immediate: true })

const refresh = () => {
  queryClient.invalidateQueries({ queryKey: ['certificate-requests'] })
  queryClient.invalidateQueries({ queryKey: ['certificate-request'] })
}

// ─── بيانات شهادة العضوية: رقم وتاريخ قرار التصنيف (+ العنوان عند الإصدار) ───
// بتنطلب بنافذة عند الموافقة على طلب عضوية وعند الإصدار التلقائي، زي صفحة شهادات
// العضوية. بتتعبّى من المحفوظ على الطلب (من الموافقة)، وإلا من ملف المقاول.
const certFieldsOpen = ref(false)
const certFieldsMode = ref<'approve' | 'issue' | 'regenerate'>('approve')
const certFieldsLoading = ref(false)
const certFields = ref({ address: '', decision_number: '', decision_date: '' })

async function openCertFields(mode: 'approve' | 'issue' | 'regenerate') {
  const r = detail.value

  certFieldsMode.value = mode
  certFields.value = {
    address: r?.certificate_address || '',
    decision_number: r?.decision_number || '',
    decision_date: r?.decision_date || '',
  }
  actionError.value = ''
  certFieldsOpen.value = true

  if (certFields.value.decision_number && certFields.value.decision_date && (mode === 'approve' || certFields.value.address))
    return

  certFieldsLoading.value = true
  try {
    const c = (await api.get(`/api/v1/contractors/${r.contractor_id}`)).data?.items

    certFields.value.address ||= c?.city || c?.address || 'غزة'
    certFields.value.decision_number ||= c?.classification_decision_number || ''
    certFields.value.decision_date ||= c?.classification_decision_date?.slice(0, 10) || ''
  }
  catch {}
  finally {
    certFieldsLoading.value = false
  }
}

function certFieldsPayload() {
  const f = certFields.value
  const payload: Record<string, string> = {}

  if (certFieldsMode.value !== 'approve' && f.address)
    payload.address = f.address
  if (f.decision_number)
    payload.decision_number = f.decision_number
  if (f.decision_date)
    payload.decision_date = f.decision_date

  return payload
}

const approveMutation = useMutation({
  mutationFn: async (payload: Record<string, string>) =>
    (await api.post(`/api/v1/dashboard/certificate-requests/${selected.value.id}/approve`, payload)).data,
  onSuccess: (d: any) => {
    refresh()
    selected.value = d?.items ?? selected.value
    certFieldsOpen.value = false
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

// شهادة العضوية بتتولّد تلقائياً من بيانات المقاول (بدون رفع ملف)؛ الأنواع التانية
// ما إلها قالب، فبدها ملف PDF. رفع ملف يدوي للعضوية متاح كخيار بديل.
const canAutoIssue = computed(() => detail.value?.type === 'membership')
const needsFile = computed(() => !canAutoIssue.value || manualUpload.value)

const issueMutation = useMutation({
  mutationFn: async (fields: Record<string, string>) => {
    const fd = new FormData()
    if (needsFile.value) {
      const file = firstFile(certificateFile.value)
      if (!file)
        throw new Error('لم يتم اختيار ملف الشهادة.')
      fd.append('certificate', file)
    }
    else {
      for (const [k, v] of Object.entries(fields))
        fd.append(k, v)
    }

    return (await api.post(`/api/v1/dashboard/certificate-requests/${selected.value.id}/issue`, fd)).data
  },
  onSuccess: (d: any) => {
    refresh()
    selected.value = d?.items ?? selected.value
    certificateFile.value = null
    manualUpload.value = false
    certFieldsOpen.value = false
    if (d?.items?.id)
      openPreview(d.items.id)
  },
  onError: (e: any) => actionError.value = e?.response?.data?.message || 'فشل إصدار الشهادة.',
})

// شهادة صادرة سابقاً: تعديل رقم/تاريخ القرار (أو العنوان) وتوليدها من جديد بنفس الرقم
const regenerateMutation = useMutation({
  mutationFn: async (payload: Record<string, string>) =>
    (await api.post(`/api/v1/dashboard/certificate-requests/${selected.value.id}/regenerate`, payload)).data,
  onSuccess: (d: any) => {
    refresh()
    selected.value = d?.items ?? selected.value
    certFieldsOpen.value = false
    if (d?.items?.id)
      openPreview(d.items.id)
  },
  onError: (e: any) => actionError.value = e?.response?.data?.message || 'تعذّرت إعادة إصدار الشهادة.',
})

function submitCertFields() {
  if (certFieldsMode.value === 'approve')
    approveMutation.mutate(certFieldsPayload())
  else if (certFieldsMode.value === 'regenerate')
    regenerateMutation.mutate(certFieldsPayload())
  else
    issueMutation.mutate(certFieldsPayload())
}

function onApprove() {
  if (detail.value?.type === 'membership')
    openCertFields('approve')
  else
    approveMutation.mutate({})
}

function onIssue() {
  if (needsFile.value)
    issueMutation.mutate({})
  else
    openCertFields('issue')
}

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
                <!-- طلب قُدِّم بانتظار اعتماد دفعة الرسوم (TASK-17 #5): يُرى ويُراجَع،
                     لكن الموافقة والإصدار موقوفان حتى يعتمد المحاسب الدفعة. -->
                <VChip
                  v-if="r.awaiting_payment_confirmation"
                  size="x-small"
                  color="warning"
                  variant="tonal"
                  prepend-icon="tabler-clock-dollar"
                  class="ms-1"
                >
                  بانتظار اعتماد الدفعة
                </VChip>
              </td>
              <td class="text-body-2">{{ fmtDate(r.request_date) }}</td>
              <td class="text-center" @click.stop>
                <VBtn icon="tabler-eye" size="x-small" variant="text" @click="openRequest(r)" />
                <VBtn v-if="$can('services.certificate_requests', 'delete')" icon="tabler-trash" size="x-small" variant="text" color="error" @click="confirmDelete(r)" />
              </td>
            </tr>
          </tbody>
        </VTable>
      </VCard>

      <div v-if="lastPage > 1" class="d-flex justify-center mt-4">
        <VPagination v-model="page" :length="lastPage" :total-visible="$vuetify.display.xs ? 5 : 7" />
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
            <div v-if="detail.decision_number"><strong>رقم قرار التصنيف:</strong> {{ detail.decision_number }}</div>
            <div v-if="detail.decision_date"><strong>تاريخ القرار:</strong> {{ detail.decision_date }}</div>
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
            prepend-icon="tabler-eye"
            class="mb-4"
            @click="openPreview(detail.id)"
          >
            عرض الشهادة وحالة وصولها
          </VBtn>
          <VBtn
            v-if="detail.status === 'issued' && detail.type === 'membership' && $can('services.certificate_requests', 'update')"
            color="primary"
            variant="text"
            prepend-icon="tabler-edit"
            class="mb-4 ms-2"
            @click="openCertFields('regenerate')"
          >
            تعديل رقم وتاريخ القرار وإعادة الإصدار
          </VBtn>

          <VAlert
            v-if="detail.awaiting_payment_confirmation"
            type="warning"
            variant="tonal"
            class="mb-4"
          >
            <p class="mb-1 font-weight-medium">الطلب بانتظار اعتماد دفعة الرسوم</p>
            <p class="text-body-2 mb-0">
              قدّم المقاول الطلب بعد دفع الرسوم من التطبيق، ودفعته لم تُعتمد بعد
              <template v-if="detail.pending_payment">
                (معاملة {{ detail.pending_payment.transaction_number ?? detail.pending_payment.id }} —
                {{ detail.pending_payment.amount }} {{ detail.pending_payment.currency }})
              </template>.
              اعتمد الدفعة من «سجل المدفوعات» أولاً؛ الموافقة والإصدار موقوفان حتى ذلك الحين.
            </p>
          </VAlert>

          <!-- إجراءات حسب الحالة -->
          <template v-if="detail.status === 'pending'">
            <VDivider class="my-4" />
            <p class="text-body-2 font-weight-medium mb-2">قرار المراجعة:</p>
            <div class="d-flex gap-3 mb-4">
              <VBtn v-if="$can('services.certificate_requests', 'update')"
                color="info"
                prepend-icon="tabler-check"
                :disabled="detail.awaiting_payment_confirmation"
                :title="detail.awaiting_payment_confirmation ? 'اعتمد دفعة الرسوم أولاً' : undefined"
                :loading="approveMutation.isPending.value"
                @click="onApprove"
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
            <VBtn v-if="$can('services.certificate_requests', 'update')"
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
            <p class="text-body-2 font-weight-medium mb-2">
              إصدار الشهادة<template v-if="!canAutoIssue"> (ملف PDF)</template>:
            </p>
            <p
              v-if="canAutoIssue && !manualUpload"
              class="text-body-2 text-medium-emphasis mb-3"
            >
              بتتولّد الشهادة تلقائياً من بيانات ملف المقاول وبتوصله إشعار بالتطبيق — بدون رفع ملف.
            </p>
            <VFileInput
              v-if="needsFile"
              v-model="certificateFile"
              label="ملف الشهادة"
              accept="application/pdf"
              density="compact"
              prepend-icon="tabler-file-type-pdf"
            />
            <div class="d-flex flex-wrap align-center gap-3">
              <VBtn v-if="$can('services.certificate_requests', 'update')"
                color="success"
                :prepend-icon="needsFile ? 'tabler-certificate' : 'tabler-wand'"
                :disabled="(needsFile && !firstFile(certificateFile)) || detail.awaiting_payment_confirmation"
                :loading="issueMutation.isPending.value"
                @click="onIssue"
              >
                {{ needsFile ? 'إصدار الشهادة' : 'إصدار الشهادة تلقائياً' }}
              </VBtn>
              <VBtn
                v-if="canAutoIssue && $can('services.certificate_requests', 'update')"
                variant="text"
                size="small"
                @click="manualUpload = !manualUpload; certificateFile = null"
              >
                {{ manualUpload ? 'رجوع للإصدار التلقائي' : 'أو ارفع ملف PDF يدوياً' }}
              </VBtn>
            </div>
          </template>
        </VCardText>

        <VCardActions>
          <VBtn v-if="$can('services.certificate_requests', 'delete')" color="error" variant="text" @click="confirmDelete(detail)">حذف</VBtn>
          <VSpacer />
          <VBtn variant="text" @click="isViewOpen = false">إغلاق</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- ─── نافذة بيانات شهادة العضوية (عند الموافقة / الإصدار التلقائي) ─── -->
    <VDialog v-model="certFieldsOpen" max-width="520">
      <VCard>
        <VCardItem>
          <VCardTitle class="d-flex align-center gap-2">
            <VIcon :icon="certFieldsMode === 'approve' ? 'tabler-check' : 'tabler-certificate'" :color="certFieldsMode === 'approve' ? 'info' : 'success'" />
            {{ certFieldsMode === 'approve' ? 'الموافقة على طلب شهادة العضوية' : certFieldsMode === 'regenerate' ? 'تعديل بيانات الشهادة وإعادة إصدارها' : 'إصدار شهادة العضوية' }}
          </VCardTitle>
          <VCardSubtitle>
            {{ certFieldsMode === 'approve'
              ? 'رقم وتاريخ قرار لجنة التصنيف — بينحفظوا على الطلب وبينطبعوا بالشهادة عند إصدارها.'
              : certFieldsMode === 'regenerate'
                ? 'بتتولّد الشهادة من جديد بنفس الرقم بهذه البيانات، وبيتسجّل التعديل بسجل المحددات الهامة.'
                : 'بتتولّد الشهادة PDF بهذه البيانات وبيوصل للمقاول إشعار.' }}
          </VCardSubtitle>
        </VCardItem>

        <VCardText>
          <VAlert type="info" variant="tonal" density="compact" class="mb-4">
            <strong>المقاول:</strong> {{ detail?.contractor ?? '—' }} — {{ detail?.membership_number ?? '—' }}
          </VAlert>

          <VTextField
            v-if="certFieldsMode !== 'approve'"
            v-model="certFields.address"
            label="عنوان الشركة"
            dir="rtl"
            class="mb-4"
            :loading="certFieldsLoading"
            placeholder="غزة"
          />
          <VRow dense>
            <VCol cols="6">
              <VTextField
                v-model="certFields.decision_number"
                label="رقم قرار التصنيف"
                dir="rtl"
                :loading="certFieldsLoading"
                placeholder="مثال: 04/2022"
              />
            </VCol>
            <VCol cols="6">
              <VTextField
                v-model="certFields.decision_date"
                label="تاريخ قرار التصنيف"
                type="date"
                :loading="certFieldsLoading"
              />
            </VCol>
          </VRow>

          <VAlert v-if="actionError" type="error" variant="tonal" density="compact" class="mt-3">
            {{ actionError }}
          </VAlert>
        </VCardText>

        <VCardActions>
          <VSpacer />
          <VBtn variant="text" @click="certFieldsOpen = false">إلغاء</VBtn>
          <VBtn
            :color="certFieldsMode === 'approve' ? 'info' : 'success'"
            :prepend-icon="certFieldsMode === 'approve' ? 'tabler-check' : 'tabler-certificate'"
            :loading="approveMutation.isPending.value || issueMutation.isPending.value || regenerateMutation.isPending.value"
            :disabled="certFieldsLoading"
            @click="submitCertFields"
          >
            {{ certFieldsMode === 'approve' ? 'موافقة' : certFieldsMode === 'regenerate' ? 'إعادة الإصدار' : 'إصدار الشهادة' }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <CertificatePreviewDialog
      v-model="previewOpen"
      :request-id="previewId"
    />
  </div>
</template>

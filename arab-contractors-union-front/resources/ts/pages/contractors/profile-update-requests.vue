<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { useQuery, useMutation, useQueryClient } from '@tanstack/vue-query'
import api from '@/plugins/axios'

definePage({ meta: { requiresAdmin: true } })

const queryClient = useQueryClient()

const page = ref(1)
const statusFilter = ref<string | null>(null)

const statusOptions = [
  { value: 'pending', title: 'قيد المراجعة' },
  { value: 'approved', title: 'مقبول' },
  { value: 'rejected', title: 'مرفوض' },
  { value: 'superseded', title: 'ألغاه طلب أحدث' },
]

const statusColor: Record<string, string> = {
  pending: 'warning',
  approved: 'success',
  rejected: 'error',
  superseded: 'secondary',
}

// خريطة ثابتة تُقابل ProfileUpdateRequest::REVIEWED_* في الـbackend. توسيع الطابور من خمسة
// حقول إلى الملف الكامل (TASK-17 US11) يجعل هذه الخريطة حمّالة: أي مفتاح غير مُعرَّف كان
// سيُعرض بلا عنوان، فيوافق المراجِع على تغيير لا يقرأه. لذلك يظهر المفتاح الخام بعلامة
// واضحة عند عدم وجود ترجمة، بدل أن يظهر فراغاً.
const fieldLabels: Record<string, string> = {
  // تواصل
  email: 'البريد الإلكتروني',
  address: 'العنوان التفصيلي',
  phone: 'الجوال',
  fax: 'الفاكس',
  district: 'الحي / المنطقة',
  building: 'البناية',
  floor: 'الطابق',
  authorized_person_phone: 'جوال المفوض',
  authorized_person_whatsapp: 'واتساب المفوض',
  // هوية الشركة وبياناتها القانونية
  established_year: 'سنة التأسيس',
  established_date: 'تاريخ التأسيس',
  owner_name: 'اسم المالك',
  partners: 'الشركاء',
  capital: 'رأس المال',
  registration_date: 'تاريخ التسجيل',
  legal_form: 'الشكل القانوني',
  company_purposes: 'أغراض الشركة',
  authorized_person: 'المفوض بالتوقيع',
  authorized_person_title: 'صفة المفوض',
  authorized_person_id_number: 'رقم هوية المفوض',
}

/** مجموعة الحقل — تُستخدم لتقسيم المقارنة؛ قائمة من 25 صفاً غير قابلة للمراجعة. */
const contactFields = [
  'email', 'address', 'fax', 'district', 'building', 'floor',
  'authorized_person_phone', 'authorized_person_whatsapp', 'phone',
]

// الحقول التي تُعرض LTR حتى لا تتكسّر الأرقام/البريد داخل صفحة RTL
const ltrFields = [
  'phone', 'email', 'fax', 'authorized_person_phone', 'authorized_person_whatsapp',
  'established_year', 'capital', 'authorized_person_id_number',
]

watch(statusFilter, () => page.value = 1)

const { data, isLoading } = useQuery({
  queryKey: computed(() => ['profile-update-requests', page.value, statusFilter.value]),
  queryFn: async () => {
    const params: Record<string, any> = { page: page.value }
    if (statusFilter.value) params.status = statusFilter.value

    return (await api.get('/api/v1/dashboard/profile-update-requests', { params })).data
  },
})

const requests = computed(() => data.value?.items ?? [])
const lastPage = computed(() => data.value?.meta?.last_page ?? 1)

// ─── تفاصيل الطلب ───
const isViewOpen = ref(false)
const selected = ref<any>(null)
const rejectReason = ref('')
const rejectReasonError = ref('')
const actionError = ref('')

const openRequest = (r: any) => {
  selected.value = r
  rejectReason.value = ''
  rejectReasonError.value = ''
  actionError.value = ''
  isViewOpen.value = true
}

/** أسماء الحقول المطلوب تعديلها — تُستخدم للملخّص داخل الجدول. */
const changedFieldNames = (r: any) =>
  Object.keys(r?.proposed_data ?? {}).map(k => fieldLabels[k] ?? k)

/** صفوف المقارنة "الحالي ← المقترح" داخل نافذة التفاصيل. */
const diffRows = computed(() => {
  const proposed = selected.value?.proposed_data ?? {}
  const current = selected.value?.current_data ?? {}

  return Object.keys(proposed).map(key => ({
    key,
    label: fieldLabels[key] ?? key,
    // بلا ترجمة: يُعرض المفتاح الخام بعلامة بدل فراغ يوافَق عليه على غير بيان
    unlabelled: !fieldLabels[key],
    group: contactFields.includes(key) ? 'contact' : 'identity',
    dir: ltrFields.includes(key) ? 'ltr' : undefined,
    current: formatValue(current[key]),
    proposed: formatValue(proposed[key]),
  }))
})

/** partners تصل كمصفوفة/كائن — عرضها الخام يُظهر [object Object] بوجه المراجِع. */
function formatValue(value: any): string | null {
  if (value === null || value === undefined || value === '') return null
  if (typeof value === 'object') return JSON.stringify(value, null, 1)

  return String(value)
}

const identityRows = computed(() => diffRows.value.filter(r => r.group === 'identity'))
const contactRows = computed(() => diffRows.value.filter(r => r.group === 'contact'))

/** المستندات المقترحة — رابط المقترح مع رابط الحالي للمقارنة قبل الموافقة. */
const proposedDocuments = computed(() => selected.value?.proposed_documents ?? [])

const refresh = () => {
  queryClient.invalidateQueries({ queryKey: ['profile-update-requests'] })
}

// سبب الرفض اختياري في الـ backend (nullable) لكننا نُلزم به هنا — الرفض بلا سبب
// يصل المقاول كإشعار بلا أي تفسير، وهو ما يولّد تذكرة دعم مباشرة.
const onReject = () => {
  if (rejectReason.value.trim().length < 5) {
    rejectReasonError.value = 'سبب الرفض إلزامي (5 أحرف على الأقل) لإبلاغ المقاول به.'

    return
  }
  rejectMutation.mutate()
}

const approveMutation = useMutation({
  mutationFn: async () =>
    (await api.post(`/api/v1/dashboard/profile-update-requests/${selected.value.id}/approve`)).data,
  onSuccess: () => {
    refresh()
    isViewOpen.value = false
  },
  onError: (e: any) => actionError.value = e?.response?.data?.message || 'فشل تنفيذ الإجراء.',
})

const rejectMutation = useMutation({
  mutationFn: async () =>
    (await api.post(`/api/v1/dashboard/profile-update-requests/${selected.value.id}/reject`, { reject_reason: rejectReason.value })).data,
  onSuccess: () => {
    refresh()
    isViewOpen.value = false
  },
  onError: (e: any) => actionError.value = e?.response?.data?.message || 'فشل تنفيذ الإجراء.',
})

function fmtDate(d: string | null) {
  if (!d) return '—'

  return new Date(d).toLocaleDateString('ar-EG', { year: 'numeric', month: 'short', day: 'numeric' })
}
</script>

<template>
  <div>
    <div class="mb-6">
      <h1 class="text-h4 font-weight-bold">طلبات تعديل البيانات</h1>
      <p class="text-body-2 text-medium-emphasis mb-0">
        مراجعة طلبات المقاولين لتعديل بيانات التواصل والمفوض بالتوقيع — لا تُطبَّق على ملف المقاول إلا بعد الموافقة
      </p>
    </div>

    <VCard class="mb-6 pa-4">
      <VRow dense>
        <VCol cols="12" md="4">
          <VSelect
            v-model="statusFilter"
            :items="statusOptions"
            label="الحالة"
            density="compact"
            clearable
          />
        </VCol>
      </VRow>
    </VCard>

    <VProgressLinear v-if="isLoading" indeterminate color="primary" />

    <VCard v-else-if="requests.length === 0" class="text-center py-12">
      <VIcon icon="tabler-file-off" size="64" color="disabled" class="mb-3" />
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
              <th>الحقول المطلوب تعديلها</th>
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
              <td>
                <VChip
                  v-for="label in changedFieldNames(r)"
                  :key="label"
                  size="x-small"
                  variant="tonal"
                  class="me-1 mb-1"
                >
                  {{ label }}
                </VChip>
              </td>
              <td>
                <VChip size="x-small" :color="statusColor[r.status] ?? 'secondary'">
                  {{ r.status_label ?? r.status }}
                </VChip>
              </td>
              <td class="text-body-2">{{ fmtDate(r.created_at) }}</td>
              <td class="text-center" @click.stop>
                <VBtn icon="tabler-eye" size="x-small" variant="text" @click="openRequest(r)" />
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
      <VCard v-if="selected">
        <VCardTitle class="d-flex align-center justify-space-between pt-4">
          <span class="text-h6">طلب #{{ selected.id }}</span>
          <VChip size="small" :color="statusColor[selected.status] ?? 'secondary'">
            {{ selected.status_label ?? selected.status }}
          </VChip>
        </VCardTitle>

        <VCardText>
          <VAlert v-if="actionError" type="error" variant="tonal" class="mb-4">
            {{ actionError }}
          </VAlert>

          <div class="d-flex flex-wrap gap-4 mb-4 text-body-2">
            <div><strong>المقاول:</strong> {{ selected.contractor ?? '—' }}</div>
            <div><strong>رقم العضوية:</strong> {{ selected.membership_number ?? '—' }}</div>
            <div><strong>تاريخ الطلب:</strong> {{ fmtDate(selected.created_at) }}</div>
          </div>

          <!-- مقسّمة إلى مجموعات: الطابور صار يحمل الملف الكامل، وقائمة مسطّحة من 25 صفاً
               ليست قابلة للمراجعة فعلياً (TASK-17 US11). -->
          <VCard
            v-if="identityRows.length"
            variant="tonal"
            color="secondary"
            class="pa-4 mb-4 rounded-lg"
          >
            <p class="text-body-2 font-weight-medium mb-3">بيانات الشركة وهويتها القانونية:</p>
            <div v-for="row in identityRows" :key="row.key" class="mb-3">
              <p class="text-caption text-medium-emphasis mb-1">
                {{ row.label }}
                <VChip v-if="row.unlabelled" size="x-small" color="warning" variant="tonal" class="ms-1">
                  حقل غير معرَّف بالواجهة
                </VChip>
              </p>
              <div class="d-flex align-center flex-wrap gap-2">
                <span class="text-body-2 text-decoration-line-through text-medium-emphasis" :dir="row.dir">
                  {{ row.current || '—' }}
                </span>
                <VIcon icon="tabler-arrow-left" size="16" class="text-medium-emphasis" />
                <span class="text-body-2 font-weight-medium text-success" :dir="row.dir">
                  {{ row.proposed || '—' }}
                </span>
              </div>
            </div>
          </VCard>

          <VCard
            v-if="contactRows.length"
            variant="tonal"
            color="secondary"
            class="pa-4 mb-4 rounded-lg"
          >
            <p class="text-body-2 font-weight-medium mb-3">بيانات التواصل:</p>
            <div v-for="row in contactRows" :key="row.key" class="mb-3">
              <p class="text-caption text-medium-emphasis mb-1">
                {{ row.label }}
                <VChip v-if="row.unlabelled" size="x-small" color="warning" variant="tonal" class="ms-1">
                  حقل غير معرَّف بالواجهة
                </VChip>
              </p>
              <div class="d-flex align-center flex-wrap gap-2">
                <span class="text-body-2 text-decoration-line-through text-medium-emphasis" :dir="row.dir">
                  {{ row.current || '—' }}
                </span>
                <VIcon icon="tabler-arrow-left" size="16" class="text-medium-emphasis" />
                <span class="text-body-2 font-weight-medium text-success" :dir="row.dir">
                  {{ row.proposed || '—' }}
                </span>
              </div>
            </div>
          </VCard>

          <!-- المستندات المقترحة: المستند الحالي لم يُلمس بعد، والموافقة هي ما ينقل الجديد
               مكانه. فتح الملفين إلى جانب بعضهما هو الشيء الوحيد الذي يجعل الموافقة مراجعةً
               لا تخميناً. -->
          <VCard
            v-if="proposedDocuments.length"
            variant="tonal"
            color="info"
            class="pa-4 mb-4 rounded-lg"
          >
            <p class="text-body-2 font-weight-medium mb-3">
              مستندات مقترحة ({{ proposedDocuments.length }}) — لم تُطبَّق بعد:
            </p>
            <div v-for="doc in proposedDocuments" :key="doc.field" class="mb-3">
              <p class="text-caption text-medium-emphasis mb-1">{{ doc.label }}</p>
              <div class="d-flex align-center flex-wrap gap-2">
                <VBtn
                  v-if="doc.current_url"
                  size="x-small"
                  variant="tonal"
                  color="secondary"
                  prepend-icon="tabler-file"
                  :href="doc.current_url"
                  target="_blank"
                >
                  المستند الحالي
                </VBtn>
                <span v-else class="text-caption text-medium-emphasis">لا مستند حالي</span>
                <VIcon icon="tabler-arrow-left" size="16" class="text-medium-emphasis" />
                <VBtn
                  size="x-small"
                  variant="tonal"
                  color="success"
                  prepend-icon="tabler-file-check"
                  :href="doc.proposed_url"
                  target="_blank"
                >
                  المستند المقترح
                </VBtn>
              </div>
            </div>
          </VCard>

          <VBtn
            v-if="selected.attachment_url"
            color="info"
            variant="tonal"
            prepend-icon="tabler-file-text"
            :href="selected.attachment_url"
            target="_blank"
            class="mb-4"
          >
            عرض المرفق المثبت للطلب
          </VBtn>

          <VAlert v-if="selected.reject_reason" type="error" variant="tonal" class="mb-4">
            <strong>سبب الرفض:</strong> {{ selected.reject_reason }}
          </VAlert>

          <div v-if="selected.reviewed_by" class="text-caption text-medium-emphasis mb-2">
            تمت المراجعة بواسطة {{ selected.reviewed_by }} — {{ fmtDate(selected.reviewed_at) }}
          </div>

          <VAlert
            v-if="selected.status === 'superseded'"
            type="info"
            variant="tonal"
            density="compact"
            class="mb-4"
          >
            ألغى هذا الطلبَ طلبٌ أحدث من المقاول نفسه — لا إجراء مطلوب عليه.
            <template v-if="selected.superseded_at"> ({{ fmtDate(selected.superseded_at) }})</template>
          </VAlert>

          <template v-if="selected.status === 'pending'">
            <VDivider class="my-4" />
            <p class="text-body-2 font-weight-medium mb-2">قرار المراجعة:</p>
            <VBtn
              color="success"
              prepend-icon="tabler-check"
              class="mb-4"
              :loading="approveMutation.isPending.value"
              @click="approveMutation.mutate()"
            >
              الموافقة وتطبيق التعديلات
            </VBtn>
            <VTextarea
              v-model="rejectReason"
              label="سبب الرفض *"
              rows="2"
              dir="rtl"
              :error-messages="rejectReasonError"
              @update:model-value="rejectReasonError = ''"
            />
            <VBtn
              color="error"
              variant="tonal"
              prepend-icon="tabler-x"
              class="mt-2"
              :loading="rejectMutation.isPending.value"
              @click="onReject"
            >
              رفض الطلب
            </VBtn>
          </template>
        </VCardText>

        <VCardActions>
          <VSpacer />
          <VBtn variant="text" @click="isViewOpen = false">إغلاق</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>

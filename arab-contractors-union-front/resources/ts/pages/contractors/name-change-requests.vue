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
]

const statusColor: Record<string, string> = {
  pending: 'warning',
  approved: 'success',
  rejected: 'error',
}

watch(statusFilter, () => page.value = 1)

const { data, isLoading } = useQuery({
  queryKey: computed(() => ['name-change-requests', page.value, statusFilter.value]),
  queryFn: async () => {
    const params: Record<string, any> = { page: page.value }
    if (statusFilter.value) params.status = statusFilter.value

    return (await api.get('/api/v1/dashboard/name-change-requests', { params })).data
  },
})

const requests = computed(() => data.value?.items ?? [])
const lastPage = computed(() => data.value?.meta?.last_page ?? 1)

// ─── تفاصيل الطلب ───
const isViewOpen = ref(false)
const selected = ref<any>(null)
const rejectReason = ref('')
const actionError = ref('')

const openRequest = (r: any) => {
  selected.value = r
  rejectReason.value = ''
  actionError.value = ''
  isViewOpen.value = true
}

const refresh = () => {
  queryClient.invalidateQueries({ queryKey: ['name-change-requests'] })
}

const approveMutation = useMutation({
  mutationFn: async () =>
    (await api.post(`/api/v1/dashboard/name-change-requests/${selected.value.id}/approve`)).data,
  onSuccess: () => {
    refresh()
    isViewOpen.value = false
  },
  onError: (e: any) => actionError.value = e?.response?.data?.message || 'فشل تنفيذ الإجراء.',
})

const rejectMutation = useMutation({
  mutationFn: async () =>
    (await api.post(`/api/v1/dashboard/name-change-requests/${selected.value.id}/reject`, { reject_reason: rejectReason.value })).data,
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
      <h1 class="text-h4 font-weight-bold">طلبات تعديل اسم الشركة</h1>
      <p class="text-body-2 text-medium-emphasis mb-0">
        مراجعة طلبات المقاولين لتعديل اسم شركاتهم — تتطلب وثيقة رسمية مثبتة للتغيير
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
              <th>الاسم الحالي</th>
              <th>الاسم المطلوب</th>
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
              <td>{{ r.current_name }}</td>
              <td class="font-weight-medium">{{ r.requested_name }}</td>
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
    <VDialog v-model="isViewOpen" max-width="640" scrollable>
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

          <VCard variant="tonal" color="secondary" class="pa-4 mb-4 rounded-lg">
            <p class="text-body-2 mb-1"><strong>الاسم الحالي:</strong> {{ selected.current_name }}</p>
            <p class="text-body-2 mb-0"><strong>الاسم المطلوب:</strong> {{ selected.requested_name }}</p>
          </VCard>

          <VBtn
            v-if="selected.supporting_document_url"
            color="info"
            variant="tonal"
            prepend-icon="tabler-file-text"
            :href="selected.supporting_document_url"
            target="_blank"
            class="mb-4"
          >
            عرض الوثيقة الرسمية المرفقة
          </VBtn>

          <VAlert v-if="selected.reject_reason" type="error" variant="tonal" class="mb-4">
            <strong>سبب الرفض:</strong> {{ selected.reject_reason }}
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
              الموافقة وتحديث اسم الشركة
            </VBtn>
            <VTextarea
              v-model="rejectReason"
              label="سبب الرفض (اختياري)"
              rows="2"
              dir="rtl"
            />
            <VBtn
              color="error"
              variant="tonal"
              prepend-icon="tabler-x"
              class="mt-2"
              :loading="rejectMutation.isPending.value"
              @click="rejectMutation.mutate()"
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

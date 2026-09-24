<script setup lang="ts">
import { ref, watch } from 'vue'
import { useQuery, useMutation, useQueryClient } from '@tanstack/vue-query'
import api from '@/plugins/axios'

definePage({ meta: { requiresAdmin: true } })

const queryClient = useQueryClient()

interface Penalty {
  id: number
  contractor_id: number
  contractor_name: string
  reason: string
  amount: number
  paid_amount: number
  status: 'unpaid' | 'paid' | 'partially_paid' | 'rejected'
  status_label: string
  reject_reason?: string
  notes?: string
  paid_at?: string
  created_at: string
}

const search = ref('')
const page = ref(1)
const successMessage = ref('')
const errorMessage = ref('')

function flash(msg: string, isError = false) {
  if (isError) {
    errorMessage.value = msg
  } else {
    successMessage.value = msg
    errorMessage.value = ''
    setTimeout(() => successMessage.value = '', 5000)
  }
}

const { data, isLoading } = useQuery({
  queryKey: ['dashboard-penalties', search, page],
  queryFn: async () => (await api.get('/api/v1/dashboard/penalties', {
    params: { search: search.value || undefined, page: page.value },
  })).data,
})

// إنشاء غرامة جديدة
const createDialog = ref(false)
const createForm = ref({
  contractor_id: null as number | null,
  reason: '',
  amount: '',
  status: 'unpaid' as 'unpaid' | 'paid' | 'partially_paid' | 'rejected',
  paid_amount: '',
  reject_reason: '',
  notes: '',
})

const contractorSearch = ref('')
const contractorOptions = ref<{ id: number; name: string; membership_number: string }[]>([])
let contractorTimer: ReturnType<typeof setTimeout> | null = null

watch(contractorSearch, q => {
  if (contractorTimer) clearTimeout(contractorTimer)
  contractorTimer = setTimeout(async () => {
    if (!q || q.length < 2) return
    try {
      const r = await api.get('/api/v1/contractors', { params: { search: q, per_page: 10 } })
      contractorOptions.value = (r.data.items ?? r.data.data ?? []).map((c: any) => ({
        id: c.id,
        name: c.name,
        membership_number: c.membership_number,
      }))
    }
    catch {}
  }, 350)
})

const createMutation = useMutation({
  mutationFn: async () => (await api.post('/api/v1/dashboard/penalties', {
    contractor_id: createForm.value.contractor_id,
    reason: createForm.value.reason,
    amount: parseFloat(createForm.value.amount as any),
    status: createForm.value.status || undefined,
    paid_amount: createForm.value.paid_amount ? parseFloat(createForm.value.paid_amount as any) : undefined,
    reject_reason: createForm.value.reject_reason || undefined,
    notes: createForm.value.notes || undefined,
  })).data,
  onSuccess: () => {
    createDialog.value = false
    createForm.value = {
      contractor_id: null,
      reason: '',
      amount: '',
      status: 'unpaid',
      paid_amount: '',
      reject_reason: '',
      notes: '',
    }
    flash('تم إضافة الغرامة بنجاح.')
    queryClient.invalidateQueries({ queryKey: ['dashboard-penalties'] })
  },
  onError: (e: any) => flash(e?.response?.data?.message || 'فشل إضافة الغرامة.', true),
})

// تحديث حالة الغرامة
const updateStatusDialog = ref(false)
const updatingPenalty = ref<Penalty | null>(null)
const updateStatusForm = ref({
  status: 'unpaid' as 'unpaid' | 'paid' | 'partially_paid' | 'rejected',
  paid_amount: '',
  reject_reason: '',
})

const updateStatusMutation = useMutation({
  mutationFn: async (penaltyId: number) => (await api.patch(`/api/v1/dashboard/penalties/${penaltyId}/status`, {
    status: updateStatusForm.value.status,
    paid_amount: updateStatusForm.value.paid_amount ? parseFloat(updateStatusForm.value.paid_amount) : undefined,
    reject_reason: updateStatusForm.value.reject_reason || undefined,
  })).data,
  onSuccess: () => {
    updateStatusDialog.value = false
    updatingPenalty.value = null
    flash('تم تحديث حالة الغرامة بنجاح.')
    queryClient.invalidateQueries({ queryKey: ['dashboard-penalties'] })
  },
  onError: (e: any) => flash(e?.response?.data?.message || 'فشل تحديث الغرامة.', true),
})

function openUpdateStatus(penalty: Penalty) {
  updatingPenalty.value = penalty
  updateStatusForm.value = {
    status: penalty.status,
    paid_amount: penalty.paid_amount.toString(),
    reject_reason: penalty.reject_reason || '',
  }
  updateStatusDialog.value = true
}

// حذف غرامة
const deletePenaltyDialog = ref(false)
const deletingPenaltyId = ref<number | null>(null)

const deletePenaltyMutation = useMutation({
  mutationFn: async (id: number) => (await api.delete(`/api/v1/dashboard/penalties/${id}`)).data,
  onSuccess: () => {
    flash('تم حذف الغرامة بنجاح.')
    queryClient.invalidateQueries({ queryKey: ['dashboard-penalties'] })
  },
  onError: (e: any) => flash(e?.response?.data?.message || 'فشل حذف الغرامة.', true),
})

function confirmDeletePenalty(id: number) {
  deletingPenaltyId.value = id
  deletePenaltyDialog.value = true
}

function deleteConfirmed() {
  if (deletingPenaltyId.value) {
    deletePenaltyMutation.mutate(deletingPenaltyId.value)
  }
  deletePenaltyDialog.value = false
}

const statusColor: Record<string, string> = {
  unpaid: 'error',
  partially_paid: 'warning',
  paid: 'success',
  rejected: 'secondary',
}

const statusLabels: Record<string, string> = {
  unpaid: 'غير مسدَّدة',
  paid: 'مسدَّدة',
  partially_paid: 'مسدَّدة جزئياً',
  rejected: 'مرفوضة',
}
</script>

<template>
  <div>
    <div class="d-flex align-center justify-space-between flex-wrap gap-4 mb-6">
      <h1 class="text-h4 font-weight-bold">الغرامات المالية</h1>
      <VBtn color="primary" @click="createDialog = true">إضافة غرامة جديدة</VBtn>
    </div>

    <!-- الرسائل -->
    <VAlert v-if="successMessage" type="success" class="mb-4" closable>{{ successMessage }}</VAlert>
    <VAlert v-if="errorMessage" type="error" class="mb-4" closable>{{ errorMessage }}</VAlert>

    <!-- البحث -->
    <VCard class="mb-6">
      <VCardText>
        <VTextField v-model="search" label="بحث (الاسم أو السبب)" prepend-inner-icon="mdi-magnify" dir="rtl" />
      </VCardText>
    </VCard>

    <!-- جدول الغرامات -->
    <VCard>
      <VDataTable
        :headers="[
          { title: 'المقاول', key: 'contractor_name' },
          { title: 'السبب', key: 'reason' },
          { title: 'المبلغ (د.أ)', key: 'amount', align: 'end' },
          { title: 'المسدد (د.أ)', key: 'paid_amount', align: 'end' },
          { title: 'الحالة', key: 'status_label' },
          { title: 'الإجراءات', key: 'actions', sortable: false },
        ]"
        :items="data?.items ?? []"
        :loading="isLoading"
        :items-per-page="15"
        dir="rtl"
      >
        <template #item.amount="{ item }">
          {{ Number(item.amount).toFixed(2) }}
        </template>

        <template #item.paid_amount="{ item }">
          {{ Number(item.paid_amount).toFixed(2) }}
        </template>

        <template #item.status_label="{ item }">
          <VChip :color="statusColor[item.status]" size="small">{{ item.status_label }}</VChip>
        </template>

        <template #item.actions="{ item }">
          <div class="d-flex gap-2">
            <VBtn
              size="small"
              icon="mdi-pencil"
              variant="text"
              @click="openUpdateStatus(item)"
              title="تحديث الحالة"
            />
            <VBtn
              size="small"
              icon="mdi-delete"
              variant="text"
              color="error"
              @click="confirmDeletePenalty(item.id)"
              title="حذف الغرامة"
            />
          </div>
        </template>

        <template #bottom>
          <VPagination v-model="page" :length="data?.meta?.last_page ?? 1" />
        </template>
      </VDataTable>
    </VCard>

    <!-- مودل إضافة غرامة -->
    <VDialog v-model="createDialog" max-width="600">
      <VCard title="إضافة غرامة جديدة">
        <VCardText>
          <VAutocomplete
            v-model="createForm.contractor_id"
            v-model:search="contractorSearch"
            label="المقاول"
            :items="contractorOptions"
            item-title="name"
            item-value="id"
            dir="rtl"
            class="mb-4"
          />

          <VTextField v-model="createForm.reason" label="السبب" dir="rtl" class="mb-4" />
          <VTextField v-model="createForm.amount" label="المبلغ الكامل (د.أ)" type="number" dir="rtl" class="mb-4" />

          <VSelect
            v-model="createForm.status"
            label="الحالة"
            :items="[
              { value: 'unpaid', title: 'غير مسدَّدة' },
              { value: 'paid', title: 'مسدَّدة' },
              { value: 'partially_paid', title: 'مسدَّدة جزئياً' },
              { value: 'rejected', title: 'مرفوضة' },
            ]"
            dir="rtl"
            class="mb-4"
          />

          <VTextField
            v-if="createForm.status === 'partially_paid'"
            v-model="createForm.paid_amount"
            label="المبلغ المسدد (د.أ)"
            type="number"
            dir="rtl"
            class="mb-4"
          />

          <VTextField
            v-if="createForm.status === 'rejected'"
            v-model="createForm.reject_reason"
            label="سبب الرفض"
            dir="rtl"
            class="mb-4"
          />

          <VTextField v-model="createForm.notes" label="ملاحظات" dir="rtl" />
        </VCardText>

        <VCardActions>
          <VSpacer />
          <VBtn variant="tonal" @click="createDialog = false">إلغاء</VBtn>
          <VBtn color="primary" :loading="createMutation.isPending.value" @click="createMutation.mutate()">إضافة</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- مودل تحديث الحالة -->
    <VDialog v-model="updateStatusDialog" max-width="500">
      <VCard title="تحديث حالة الغرامة">
        <VCardText>
          <VSelect
            v-model="updateStatusForm.status"
            label="الحالة"
            :items="[
              { value: 'unpaid', title: 'غير مسدَّدة' },
              { value: 'paid', title: 'مسدَّدة' },
              { value: 'partially_paid', title: 'مسدَّدة جزئياً' },
              { value: 'rejected', title: 'مرفوضة' },
            ]"
            dir="rtl"
            class="mb-4"
          />

          <VTextField
            v-if="updateStatusForm.status === 'partially_paid'"
            v-model="updateStatusForm.paid_amount"
            label="المبلغ المسدد (د.أ)"
            type="number"
            dir="rtl"
            class="mb-4"
          />

          <VTextField
            v-if="updateStatusForm.status === 'rejected'"
            v-model="updateStatusForm.reject_reason"
            label="سبب الرفض"
            dir="rtl"
          />
        </VCardText>

        <VCardActions>
          <VSpacer />
          <VBtn variant="tonal" @click="updateStatusDialog = false">إلغاء</VBtn>
          <VBtn
            color="primary"
            :loading="updateStatusMutation.isPending.value"
            @click="updateStatusMutation.mutate(updatingPenalty?.id!)"
          >
            تحديث
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- مودل تأكيد الحذف -->
    <VDialog v-model="deletePenaltyDialog" max-width="400">
      <VCard title="تأكيد الحذف">
        <VCardText>هل متأكد من حذف هذه الغرامة؟</VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn variant="tonal" @click="deletePenaltyDialog = false">إلغاء</VBtn>
          <VBtn color="error" :loading="deletePenaltyMutation.isPending.value" @click="deleteConfirmed">حذف</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>

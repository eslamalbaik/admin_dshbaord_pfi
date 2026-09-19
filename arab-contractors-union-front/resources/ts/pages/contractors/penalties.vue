<script setup lang="ts">
import api from '@/plugins/axios'

definePage({ meta: { requiresAdmin: true } })

const loading = ref(false)
const penalties = ref<any[]>([])
const total = ref(0)
const page = ref(1)
const search = ref('')

const addDialog = ref(false)
const addLoading = ref(false)
const newPenalty = ref({ contractor_id: null as number | null, reason: '', amount: '', notes: '' })

// الحالات الأربع المعتمَدة (REQ-06 #6) — قبل هذا كان النظام يدعم unpaid/paid فقط
const statusOptions = [
  { title: 'غير مسدَّدة', value: 'unpaid' },
  { title: 'مسدَّدة', value: 'paid' },
  { title: 'مسدَّدة جزئياً', value: 'partially_paid' },
  { title: 'مرفوضة', value: 'rejected' },
]
const statusColor: Record<string, string> = {
  unpaid: 'error',
  paid: 'success',
  partially_paid: 'warning',
  rejected: 'secondary',
}

const statusDialog = ref(false)
const statusLoading = ref(false)
const statusTarget = ref<any>(null)
const statusForm = ref({ status: 'unpaid', paid_amount: '', reject_reason: '' })

function openStatusDialog(item: any) {
  statusTarget.value = item
  statusForm.value = { status: item.status, paid_amount: '', reject_reason: item.reject_reason ?? '' }
  statusDialog.value = true
}

const updateStatus = async () => {
  statusLoading.value = true
  try {
    await api.patch(`/api/v1/penalties/${statusTarget.value.id}/status`, {
      status: statusForm.value.status,
      paid_amount: statusForm.value.status === 'partially_paid' ? statusForm.value.paid_amount : undefined,
      reject_reason: statusForm.value.status === 'rejected' ? (statusForm.value.reject_reason || undefined) : undefined,
    })
    statusDialog.value = false
    notify('تم تحديث حالة الغرامة بنجاح.')
    fetchPenalties()
  }
  catch (err: any) {
    notify(err?.response?.data?.message || 'فشل تحديث حالة الغرامة.', 'error')
  }
  finally {
    statusLoading.value = false
  }
}

// ── Snackbar ──────────────────────────────────────
const snackbar = ref(false)
const snackbarText = ref('')
const snackbarColor = ref<'success' | 'error'>('success')
const notify = (text: string, color: 'success' | 'error' = 'success') => {
  snackbarText.value = text
  snackbarColor.value = color
  snackbar.value = true
}

// ── Contractor picker (search-as-you-type, like the dues page) ──
const contractorSearch = ref('')
const contractorOptions = ref<{ id: number; name: string; membership_number: string }[]>([])
let contractorTimer: ReturnType<typeof setTimeout> | null = null
const justSelectedContractor = ref(false)

watch(() => newPenalty.value.contractor_id, () => {
  justSelectedContractor.value = true
})

watch(contractorSearch, q => {
  if (justSelectedContractor.value) {
    justSelectedContractor.value = false
    return
  }
  if (contractorTimer) clearTimeout(contractorTimer)
  contractorTimer = setTimeout(async () => {
    if (!q || q.length < 2) return
    try {
      const r = await api.get('/api/v1/contractors', { params: { search: q, per_page: 10 } })
      contractorOptions.value = (r.data.data ?? r.data.items ?? []).map((c: any) => ({
        id: c.id,
        name: c.name,
        membership_number: c.membership_number,
      }))
    }
    catch {}
  }, 350)
})

function openAddDialog() {
  newPenalty.value = { contractor_id: null, reason: '', amount: '', notes: '' }
  contractorSearch.value = ''
  contractorOptions.value = []
  addDialog.value = true
}

const headers = [
  { title: 'المقاول', key: 'contractor_name' },
  { title: 'السبب', key: 'reason' },
  { title: 'المبلغ', key: 'amount' },
  { title: 'الحالة', key: 'status' },
  { title: 'التاريخ', key: 'created_at' },
  { title: 'إجراءات', key: 'actions', sortable: false },
]

const fetchPenalties = async () => {
  loading.value = true
  try {
    const { data } = await api.get('/api/v1/penalties', {
      params: { search: search.value, page: page.value },
    })
    penalties.value = data.data || data || []
    total.value = data.total || penalties.value.length
  }
  catch {
    penalties.value = []
  }
  finally {
    loading.value = false
  }
}

const addPenalty = async () => {
  addLoading.value = true
  try {
    await api.post('/api/v1/penalties', newPenalty.value)
    addDialog.value = false
    notify('تمت إضافة الغرامة بنجاح.')
    fetchPenalties()
  }
  catch (err: any) {
    console.error(err)
    notify(err?.response?.data?.message || 'فشل حفظ الغرامة. تحقق من البيانات المدخلة.', 'error')
  }
  finally {
    addLoading.value = false
  }
}

onMounted(fetchPenalties)
</script>

<template>
  <div>
    <div class="d-flex justify-space-between align-center mb-6">
      <div>
        <h1 class="text-h4 font-weight-bold" style="font-family:Cairo,sans-serif">الغرامات والمخالفات</h1>
        <p class="text-body-2 text-medium-emphasis mb-0" style="font-family:Cairo,sans-serif">إدارة غرامات المقاولين والمخالفات المسجّلة</p>
      </div>
      <VBtn color="primary" prepend-icon="tabler-plus" @click="openAddDialog">
        إضافة غرامة
      </VBtn>
    </div>

    <VCard>
      <VCardText>
        <VTextField
          v-model="search"
          placeholder="بحث..."
          prepend-inner-icon="tabler-search"
          density="compact"
          style="max-width:300px"
          @update:model-value="page = 1; fetchPenalties()"
        />
      </VCardText>

      <VDataTable
        :headers="headers"
        :items="penalties"
        :loading="loading"
        :items-per-page="15"
        mobile-breakpoint="sm"
      >
        <template #item.contractor_name="{ item }">
          <span style="font-family:Cairo,sans-serif">{{ item.contractor_name || item.contractor?.name || '—' }}</span>
        </template>

        <template #item.amount="{ item }">
          <span class="font-weight-semibold">₪ {{ Number(item.amount || 0).toLocaleString() }}</span>
        </template>

        <template #item.status="{ item }">
          <VChip
            :color="statusColor[item.status] ?? 'default'"
            size="small"
            label
            style="font-family:Cairo,sans-serif"
          >
            {{ item.status_label ?? item.status }}
          </VChip>
          <div v-if="item.status === 'partially_paid'" class="text-caption text-medium-emphasis mt-1">
            مسدَّد: ₪ {{ Number(item.paid_amount || 0).toLocaleString() }}
          </div>
          <div v-if="item.status === 'rejected' && item.reject_reason" class="text-caption text-medium-emphasis mt-1">
            {{ item.reject_reason }}
          </div>
        </template>

        <template #item.created_at="{ item }">
          {{ item.created_at ? new Date(item.created_at).toLocaleDateString('ar-PS') : '—' }}
        </template>

        <template #item.actions="{ item }">
          <VBtn size="small" variant="text" icon="tabler-edit" title="تحديث الحالة" @click="openStatusDialog(item)" />
        </template>

        <template #no-data>
          <div class="text-center pa-6 text-medium-emphasis" style="font-family:Cairo,sans-serif">لا توجد غرامات</div>
        </template>
      </VDataTable>
    </VCard>

    <!-- Add Penalty Dialog -->
    <VDialog v-model="addDialog" max-width="480">
      <VCard>
        <VCardTitle style="font-family:Cairo,sans-serif">إضافة غرامة جديدة</VCardTitle>
        <VCardText>
          <VRow>
            <VCol cols="12">
              <VAutocomplete
                v-model="newPenalty.contractor_id"
                v-model:search="contractorSearch"
                :items="contractorOptions"
                :item-title="(c: any) => `${c.name} (${c.membership_number})`"
                item-value="id"
                label="المقاول"
                placeholder="ابحث بالاسم أو رقم العضوية..."
                no-data-text="اكتب حرفين على الأقل للبحث"
              />
            </VCol>
            <VCol cols="12">
              <VTextField v-model="newPenalty.reason" label="سبب الغرامة" />
            </VCol>
            <VCol cols="12">
              <VTextField v-model="newPenalty.amount" label="المبلغ (₪)" type="number" />
            </VCol>
            <VCol cols="12">
              <VTextarea v-model="newPenalty.notes" label="ملاحظات" rows="2" />
            </VCol>
          </VRow>
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn variant="tonal" @click="addDialog = false">إلغاء</VBtn>
          <VBtn
            color="primary"
            :loading="addLoading"
            :disabled="addLoading || !newPenalty.contractor_id || !newPenalty.reason || !newPenalty.amount"
            @click="addPenalty"
          >
            حفظ
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Update Status Dialog -->
    <VDialog v-model="statusDialog" max-width="480">
      <VCard>
        <VCardTitle style="font-family:Cairo,sans-serif">تحديث حالة الغرامة</VCardTitle>
        <VCardText>
          <VRow>
            <VCol cols="12">
              <VSelect
                v-model="statusForm.status"
                :items="statusOptions"
                item-title="title"
                item-value="value"
                label="الحالة"
              />
            </VCol>
            <VCol v-if="statusForm.status === 'partially_paid'" cols="12">
              <VTextField
                v-model="statusForm.paid_amount"
                label="المبلغ المسدَّد (₪)"
                type="number"
                :hint="`يجب أن يكون أقل من مبلغ الغرامة الكامل (₪ ${Number(statusTarget?.amount || 0).toLocaleString()})`"
                persistent-hint
              />
            </VCol>
            <VCol v-if="statusForm.status === 'rejected'" cols="12">
              <VTextField v-model="statusForm.reject_reason" label="سبب الرفض (اختياري)" />
            </VCol>
          </VRow>
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn variant="tonal" @click="statusDialog = false">إلغاء</VBtn>
          <VBtn
            color="primary"
            :loading="statusLoading"
            :disabled="statusLoading || (statusForm.status === 'partially_paid' && !statusForm.paid_amount)"
            @click="updateStatus"
          >
            حفظ
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Feedback Snackbar -->
    <VSnackbar v-model="snackbar" :timeout="3500" :color="snackbarColor" location="bottom end" variant="elevated">
      <span style="font-family:Cairo,sans-serif">{{ snackbarText }}</span>
      <template #actions>
        <VBtn variant="text" size="small" @click="snackbar = false">إغلاق</VBtn>
      </template>
    </VSnackbar>
  </div>
</template>

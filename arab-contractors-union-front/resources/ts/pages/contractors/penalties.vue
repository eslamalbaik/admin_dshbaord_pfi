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
            :color="item.status === 'paid' ? 'success' : 'error'"
            size="small"
            label
            style="font-family:Cairo,sans-serif"
          >
            {{ item.status === 'paid' ? 'مدفوع' : 'غير مدفوع' }}
          </VChip>
        </template>

        <template #item.created_at="{ item }">
          {{ item.created_at ? new Date(item.created_at).toLocaleDateString('ar-PS') : '—' }}
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

    <!-- Feedback Snackbar -->
    <VSnackbar v-model="snackbar" :timeout="3500" :color="snackbarColor" location="bottom end" variant="elevated">
      <span style="font-family:Cairo,sans-serif">{{ snackbarText }}</span>
      <template #actions>
        <VBtn variant="text" size="small" @click="snackbar = false">إغلاق</VBtn>
      </template>
    </VSnackbar>
  </div>
</template>

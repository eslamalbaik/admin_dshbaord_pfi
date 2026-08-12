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
const newPenalty = ref({ contractor_id: '', reason: '', amount: '', notes: '' })

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
    newPenalty.value = { contractor_id: '', reason: '', amount: '', notes: '' }
    fetchPenalties()
  }
  catch (err) {
    console.error(err)
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
      <VBtn color="primary" prepend-icon="tabler-plus" @click="addDialog = true">
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
              <VTextField v-model="newPenalty.contractor_id" label="رقم هوية المقاول (ID)" />
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
          <VBtn color="primary" :loading="addLoading" @click="addPenalty">حفظ</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>

<script setup lang="ts">
import api from '@/plugins/axios'

definePage({ meta: { requiresAdmin: true,
    adminOnly: true } })

const loading = ref(false)
const requests = ref<any[]>([])
const actionLoading = ref<number | null>(null)

const headers = [
  { title: 'المقاول', key: 'contractor_name' },
  { title: 'نوع الطلب', key: 'type' },
  { title: 'الحالة', key: 'status' },
  { title: 'تاريخ الطلب', key: 'created_at' },
  { title: 'المستندات', key: 'documents' },
  { title: 'الإجراء', key: 'actions', sortable: false },
]

const fetchRequests = async () => {
  loading.value = true
  try {
    const { data } = await api.get('/api/v1/memberships')
    requests.value = data.items || []
  }
  catch {
    requests.value = []
  }
  finally {
    loading.value = false
  }
}

// تأكيد قبل قبول/رفض الطلب — القرار بيغيّر حالة عضوية المقاول وما في تراجع عنه من الداشبورد.
const confirmAction = ref<{ item: any; action: 'approve' | 'reject' } | null>(null)

const runConfirmedAction = async () => {
  if (!confirmAction.value) return
  const { item, action } = confirmAction.value
  if (action === 'approve') await approve(item.id)
  else await reject(item.id)
  confirmAction.value = null
}

const approve = async (id: number) => {
  actionLoading.value = id
  try {
    await api.post(`/api/v1/memberships/${id}/approve`)
    fetchRequests()
  }
  catch (err) {
    console.error(err)
  }
  finally {
    actionLoading.value = null
  }
}

const reject = async (id: number) => {
  actionLoading.value = id
  try {
    await api.post(`/api/v1/memberships/${id}/reject`)
    fetchRequests()
  }
  catch (err) {
    console.error(err)
  }
  finally {
    actionLoading.value = null
  }
}

const getStatusColor = (status: string) => {
  switch (status) {
    case 'active': return 'success'
    case 'pending': return 'warning'
    case 'rejected': return 'error'
    default: return 'secondary'
  }
}

const getStatusLabel = (status: string) => {
  switch (status) {
    case 'active': return 'نشط / مقبول'
    case 'pending': return 'معلّق'
    case 'rejected': return 'مرفوض'
    default: return status
  }
}

onMounted(fetchRequests)
</script>

<template>
  <div>
    <div class="d-flex justify-space-between align-center flex-wrap gap-4 mb-6">
      <div>
        <h1 class="text-h4 font-weight-bold" style="font-family:Cairo,sans-serif">طلبات الانتساب</h1>
        <p class="text-body-2 text-medium-emphasis mb-0" style="font-family:Cairo,sans-serif">مراجعة وقبول أو رفض طلبات الانضمام والاشتراكات</p>
      </div>
      <VBtn variant="tonal" prepend-icon="tabler-refresh" @click="fetchRequests" :loading="loading">
        تحديث
      </VBtn>
    </div>

    <VCard>
      <VDataTable
        :headers="headers"
        :items="requests"
        :loading="loading"
        :items-per-page="15"
        mobile-breakpoint="sm"
      >
        <template #item.contractor_name="{ item }">
          <div class="font-weight-medium" style="font-family:Cairo,sans-serif">
            {{ item.contractor_name || item.contractor?.name || '—' }}
          </div>
          <div class="text-caption text-medium-emphasis">{{ item.contractor?.license_number }}</div>
        </template>

        <template #item.type="{ item }">
          <VChip color="info" size="small" label style="font-family:Cairo,sans-serif">
            {{ item.type === 'new' ? 'عضوية جديدة' : item.type === 'renewal' ? 'تجديد' : item.type }}
          </VChip>
        </template>

        <template #item.status="{ item }">
          <VChip :color="getStatusColor(item.status)" size="small" label style="font-family:Cairo,sans-serif">
            {{ getStatusLabel(item.status) }}
          </VChip>
        </template>

        <template #item.created_at="{ item }">
          {{ item.created_at ? new Date(item.created_at).toLocaleDateString('ar-PS') : '—' }}
        </template>

        <template #item.documents="{ item }">
          <VBtn
            v-if="item.document_url"
            size="small"
            variant="tonal"
            :href="item.document_url"
            target="_blank"
            prepend-icon="tabler-file"
          >
            عرض
          </VBtn>
          <span v-else class="text-medium-emphasis">—</span>
        </template>

        <template #item.actions="{ item }">
          <div class="d-flex gap-2" v-if="item.status === 'pending'">
            <VBtn
              color="success"
              size="small"
              :loading="actionLoading === item.id"
              @click="confirmAction = { item, action: 'approve' }"
              style="font-family:Cairo,sans-serif"
            >
              قبول
            </VBtn>
            <VBtn
              color="error"
              size="small"
              variant="tonal"
              :loading="actionLoading === item.id"
              @click="confirmAction = { item, action: 'reject' }"
              style="font-family:Cairo,sans-serif"
            >
              رفض
            </VBtn>
          </div>
          <span v-else class="text-caption text-medium-emphasis">مكتمل</span>
        </template>

        <template #no-data>
          <div class="text-center pa-6" style="font-family:Cairo,sans-serif">
            <VIcon icon="tabler-check-circle" size="48" color="success" class="mb-2" />
            <div>لا توجد طلبات عضوية مسجلة</div>
          </div>
        </template>
      </VDataTable>
    </VCard>

    <!-- Approve / Reject Confirm Dialog -->
    <VDialog :model-value="!!confirmAction" max-width="500" @update:model-value="confirmAction = null">
      <VCard v-if="confirmAction" style="font-family:Cairo,sans-serif">
        <VCardTitle style="font-family:Cairo,sans-serif">
          {{ confirmAction.action === 'approve' ? 'تأكيد قبول طلب العضوية' : 'تأكيد رفض طلب العضوية' }}
        </VCardTitle>
        <VCardText style="font-family:Cairo,sans-serif">
          <div class="mb-3">
            {{ confirmAction.action === 'approve' ? 'قبول' : 'رفض' }} طلب
            <strong>{{ confirmAction.item.contractor_name || confirmAction.item.contractor?.name || '—' }}</strong>
            ({{ confirmAction.item.type === 'new' ? 'عضوية جديدة' : confirmAction.item.type === 'renewal' ? 'تجديد' : confirmAction.item.type }})
          </div>
          <div class="text-body-2 font-weight-medium mb-2">شو رح يصير بعد التغيير:</div>
          <template v-if="confirmAction.action === 'approve'">
            <div class="d-flex align-start gap-2 mb-2">
              <VIcon size="18" class="mt-1 flex-shrink-0" color="success" icon="tabler-circle-check" />
              <span class="text-body-2">العضوية بتصير فعّالة وبتنتهي 31/12 من السنة المغطّاة (السنة الحالية، أو اللي بعد آخر عضوية مدفوعة).</span>
            </div>
            <div class="d-flex align-start gap-2 mb-2">
              <VIcon size="18" class="mt-1 flex-shrink-0" color="success" icon="tabler-circle-check" />
              <span class="text-body-2">إذا حالة المقاول "معلّق" أو "منتهي" بترجع "نشط" تلقائياً (الموقوف بيضل موقوف).</span>
            </div>
            <div class="d-flex align-start gap-2 mb-2">
              <VIcon size="18" class="mt-1 flex-shrink-0" color="warning" icon="tabler-alert-triangle" />
              <span class="text-body-2">ما في زر تراجع عن القبول من الداشبورد.</span>
            </div>
          </template>
          <template v-else>
            <div class="d-flex align-start gap-2 mb-2">
              <VIcon size="18" class="mt-1 flex-shrink-0" color="warning" icon="tabler-alert-triangle" />
              <span class="text-body-2">الطلب بيتسجّل "مرفوض" وما بتنضاف أو بتتجدد العضوية، وما في تراجع عنه من الداشبورد.</span>
            </div>
            <div class="d-flex align-start gap-2 mb-2">
              <VIcon size="18" class="mt-1 flex-shrink-0" color="info" icon="tabler-info-circle" />
              <span class="text-body-2">حالة المقاول نفسها ما بتتغير؛ بيقدر يقدّم طلب جديد.</span>
            </div>
          </template>
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn variant="tonal" @click="confirmAction = null">إلغاء</VBtn>
          <VBtn
            :color="confirmAction.action === 'approve' ? 'success' : 'error'"
            :loading="actionLoading === confirmAction.item.id"
            @click="runConfirmedAction"
          >
            {{ confirmAction.action === 'approve' ? 'قبول' : 'رفض' }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>

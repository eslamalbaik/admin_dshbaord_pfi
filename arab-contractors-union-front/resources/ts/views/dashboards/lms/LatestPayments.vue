<script setup lang="ts">
import { useDashboardStore } from '@/stores/dashboardStore'

const dashboardStore = useDashboardStore()
const payments = computed(() => dashboardStore.latestPayments)

const getStatusColor = (status: string) => {
  switch (status) {
    case 'paid':
    case 'Completed': return 'success'
    case 'pending':
    case 'Pending': return 'warning'
    case 'refunded':
    case 'Refunded': return 'error'
    default: return 'secondary'
  }
}

const getStatusLabel = (status: string) => {
  switch (status) {
    case 'paid':
    case 'Completed': return 'مدفوع'
    case 'pending':
    case 'Pending': return 'معلّق'
    case 'refunded':
    case 'Refunded': return 'مسترد'
    default: return status
  }
}
</script>

<template>
  <VCard class="h-100">
    <VCardItem>
      <VCardTitle style="font-family:Cairo,sans-serif">أحدث المدفوعات</VCardTitle>
      <VCardSubtitle style="font-family:Cairo,sans-serif">المعاملات الأخيرة</VCardSubtitle>

      <template #append>
        <VBtn
          variant="tonal"
          size="small"
          :to="{ name: 'payments-transactions' }"
        >
          عرض الكل
        </VBtn>
      </template>
    </VCardItem>

    <VCardText class="pa-0">
      <VList lines="two">
        <template
          v-for="(payment, index) in payments"
          :key="payment.id"
        >
          <VListItem>
            <template #prepend>
              <VAvatar
                color="primary"
                variant="tonal"
                size="40"
                rounded
              >
                <VIcon icon="tabler-cash" size="22" />
              </VAvatar>
            </template>

            <VListItemTitle class="font-weight-medium" style="font-family:Cairo,sans-serif">
              {{ payment.contractor || payment.student || payment.name }}
            </VListItemTitle>
            <VListItemSubtitle style="font-family:Cairo,sans-serif">
              {{ payment.type || 'رسوم عضوية' }}
            </VListItemSubtitle>

            <template #append>
              <div class="d-flex flex-column align-end">
                <span class="text-body-1 font-weight-semibold">₪ {{ payment.amount || 0 }}</span>
                <VChip
                  :color="getStatusColor(payment.status)"
                  size="x-small"
                  label
                >
                  {{ getStatusLabel(payment.status) }}
                </VChip>
              </div>
            </template>
          </VListItem>
          <VDivider v-if="index < payments.length - 1" />
        </template>

        <VListItem v-if="!payments.length">
          <VListItemTitle class="text-center text-medium-emphasis" style="font-family:Cairo,sans-serif">
            لا توجد مدفوعات بعد
          </VListItemTitle>
        </VListItem>
      </VList>
    </VCardText>
  </VCard>
</template>

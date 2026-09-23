<script setup lang="ts">
import { defineAsyncComponent, onMounted, computed } from 'vue'
import { useDashboardStore } from '@/stores/dashboardStore'

const StatsCards = defineAsyncComponent(() => import('@/views/dashboards/lms/StatsCards.vue'))
const RevenueChart = defineAsyncComponent(() => import('@/views/dashboards/lms/RevenueChart.vue'))
const LatestPayments = defineAsyncComponent(() => import('@/views/dashboards/lms/LatestPayments.vue'))
const LatestContractors = defineAsyncComponent(() => import('@/views/dashboards/lms/LatestStudents.vue'))
const RecentActivities = defineAsyncComponent(() => import('@/views/dashboards/lms/RecentActivities.vue'))

definePage({
  meta: {
    requiresAdmin: true,
  },
})

const dashboardStore = useDashboardStore()
const isLoading = computed(() => dashboardStore.isLoading)

onMounted(() => {
  dashboardStore.fetchDashboardData()
})

const handleRefresh = () => {
  dashboardStore.fetchDashboardData(true)
}
</script>

<template>
  <div>
    <div class="d-flex justify-space-between align-center mb-6">
      <div>
        <h1 class="text-h4 font-weight-bold" style="font-family: Cairo, sans-serif;">
          لوحة تحكم الاتحاد
        </h1>
        <p class="text-body-2 text-medium-emphasis mb-0">
          اتحاد المقاولين الفلسطينيين — Palestinian Contractors Union
        </p>
      </div>
      <VBtn
        color="primary"
        variant="tonal"
        prepend-icon="tabler-refresh"
        :loading="isLoading"
        @click="handleRefresh"
      >
        تحديث
      </VBtn>
    </div>

    <!-- Quick Actions -->
    <VRow class="mb-4">
      <VCol cols="12" sm="6" md="3">
        <VCard class="text-center" :to="{ name: 'contractors-create' }" style="cursor:pointer">
          <VCardText class="py-4">
            <VIcon icon="tabler-user-plus" size="32" color="primary" class="mb-2" />
            <div class="text-body-1 font-weight-medium">تسجيل مقاول</div>
          </VCardText>
        </VCard>
      </VCol>
      <VCol cols="12" sm="6" md="3">
        <VCard class="text-center" :to="{ name: 'contractors-memberships' }" style="cursor:pointer">
          <VCardText class="py-4">
            <VIcon icon="tabler-user-check" size="32" color="warning" class="mb-2" />
            <div class="text-body-1 font-weight-medium">طلبات الانتساب</div>
          </VCardText>
        </VCard>
      </VCol>
      <VCol cols="12" sm="6" md="3">
        <VCard class="text-center" :to="{ name: 'tenders' }" style="cursor:pointer">
          <VCardText class="py-4">
            <VIcon icon="tabler-files" size="32" color="success" class="mb-2" />
            <div class="text-body-1 font-weight-medium">العطاءات</div>
          </VCardText>
        </VCard>
      </VCol>
      <VCol cols="12" sm="6" md="3">
        <VCard class="text-center" :to="{ name: 'payments-transactions' }" style="cursor:pointer">
          <VCardText class="py-4">
            <VIcon icon="tabler-credit-card" size="32" color="info" class="mb-2" />
            <div class="text-body-1 font-weight-medium">المدفوعات</div>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <!-- Stats Cards -->
    <div style="min-height: 120px;" class="mb-6">
      <VRow v-if="isLoading">
        <VCol v-for="i in 6" :key="i" cols="12" md="2">
          <VSkeletonLoader type="list-item-avatar, text" />
        </VCol>
      </VRow>
      <StatsCards v-else />
    </div>

    <VRow>
      <VCol cols="12" lg="8">
        <div style="min-height: 380px;">
          <VSkeletonLoader v-if="isLoading" type="image" height="380" />
          <RevenueChart v-else />
        </div>
      </VCol>
      <VCol cols="12" lg="4">
        <div style="min-height: 380px;">
          <VSkeletonLoader v-if="isLoading" type="list-item-two-line@5" height="380" />
          <RecentActivities v-else />
        </div>
      </VCol>
    </VRow>

    <VRow>
      <VCol cols="12" lg="6">
        <div style="min-height: 420px;">
          <VSkeletonLoader v-if="isLoading" type="list-item-avatar-two-line@5" height="420" />
          <LatestContractors v-else />
        </div>
      </VCol>
      <VCol cols="12" lg="6">
        <div style="min-height: 420px;">
          <VSkeletonLoader v-if="isLoading" type="list-item-avatar-two-line@5" height="420" />
          <LatestPayments v-else />
        </div>
      </VCol>
    </VRow>
  </div>
</template>

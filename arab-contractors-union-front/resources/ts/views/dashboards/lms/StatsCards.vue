<script setup lang="ts">
import { useDashboardStore } from '@/stores/dashboardStore'

const dashboardStore = useDashboardStore()

const stats = computed(() => [
  {
    title: 'إجمالي المقاولين',
    value: dashboardStore.stats.total_contractors,
    change: '+5.2%',
    changeType: 'positive' as const,
    icon: 'tabler-building-factory-2',
    color: 'primary',
  },
  {
    title: 'عضويات نشطة',
    value: dashboardStore.stats.active_memberships,
    change: '+3.1%',
    changeType: 'positive' as const,
    icon: 'tabler-id-badge-2',
    color: 'success',
  },
  {
    title: 'طلبات معلّقة',
    value: dashboardStore.stats.pending_requests,
    change: '',
    changeType: 'neutral' as const,
    icon: 'tabler-clock-hour-4',
    color: 'warning',
  },
  {
    title: 'إجمالي الإيرادات',
    value: '₪ ' + (dashboardStore.stats.total_revenue || 0).toLocaleString(),
    change: '+8.3%',
    changeType: 'positive' as const,
    icon: 'tabler-cash',
    color: 'info',
  },
  {
    title: 'تنتهي قريباً',
    value: dashboardStore.stats.expiring_soon,
    change: '',
    changeType: 'negative' as const,
    icon: 'tabler-calendar-exclamation',
    color: 'error',
  },
])
</script>

<template>
  <VRow>
    <VCol
      v-for="stat in stats"
      :key="stat.title"
      cols="12"
      sm="6"
      lg="4"
      xl="2"
    >
      <VCard class="stats-card">
        <VCardText class="d-flex align-center gap-4">
          <VAvatar
            :color="stat.color"
            variant="tonal"
            size="48"
            rounded
          >
            <VIcon
              :icon="stat.icon"
              size="28"
            />
          </VAvatar>

          <div class="d-flex flex-column">
            <span class="text-body-2 text-medium-emphasis" style="font-family:Cairo,sans-serif">{{ stat.title }}</span>
            <div class="d-flex align-center gap-2">
              <h4 class="text-h4 font-weight-semibold">
                {{ stat.value }}
              </h4>
              <VChip
                v-if="stat.change"
                :color="stat.changeType === 'positive' ? 'success' : 'error'"
                size="x-small"
                label
              >
                <VIcon
                  :icon="stat.changeType === 'positive' ? 'tabler-arrow-up' : 'tabler-arrow-down'"
                  size="12"
                  start
                />
                {{ stat.change }}
              </VChip>
            </div>
          </div>
        </VCardText>
      </VCard>
    </VCol>
  </VRow>
</template>

<style scoped>
.stats-card {
  transition: transform 0.2s ease, box-shadow 0.2s ease;
}
.stats-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 25px rgba(var(--v-theme-on-surface), 0.08) !important;
}
</style>

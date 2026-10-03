<script setup lang="ts">
import { useDashboardStore } from '@/stores/dashboardStore'

const dashboardStore = useDashboardStore()

// نسبة التغيّر الفعلية (آخر 30 يوم مقابل الـ30 يوم اللي قبلها) بدل الأرقام الثابتة
// اللي كانت من قالب Vuexy الأصلي — القيم جايّة من DashboardController::percentChange.
const changeChip = (pct: number) => ({
  change: pct ? `${pct > 0 ? '+' : ''}${pct}%` : '',
  changeType: (pct > 0 ? 'positive' : pct < 0 ? 'negative' : 'neutral') as 'positive' | 'negative' | 'neutral',
})

const stats = computed(() => [
  {
    title: 'إجمالي المقاولين',
    value: dashboardStore.stats.total_contractors,
    ...changeChip(dashboardStore.stats.changes.total_contractors),
    icon: 'tabler-building-factory-2',
    color: 'primary',
  },
  {
    title: 'عضويات نشطة',
    value: dashboardStore.stats.active_memberships,
    ...changeChip(dashboardStore.stats.changes.active_memberships),
    icon: 'tabler-id-badge-2',
    color: 'success',
  },
  {
    title: 'مستخدمو التطبيق',
    value: dashboardStore.stats.app_users,
    ...changeChip(dashboardStore.stats.changes.app_users),
    icon: 'tabler-device-mobile-check',
    color: 'secondary',
  },
  {
    title: 'طلبات معلّقة',
    value: dashboardStore.stats.pending_requests,
    ...changeChip(dashboardStore.stats.changes.pending_requests),
    icon: 'tabler-clock-hour-4',
    color: 'warning',
  },
  {
    title: 'إجمالي الإيرادات',
    value: '₪ ' + (dashboardStore.stats.total_revenue || 0).toLocaleString(),
    ...changeChip(dashboardStore.stats.changes.total_revenue),
    icon: 'tabler-cash',
    color: 'info',
  },
  {
    title: 'تنتهي قريباً',
    value: dashboardStore.stats.expiring_soon,
    ...changeChip(dashboardStore.stats.changes.expiring_soon),
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

          <div class="d-flex flex-column stats-card__body">
            <span class="text-body-2 text-medium-emphasis" style="font-family:Cairo,sans-serif">{{ stat.title }}</span>
            <div class="d-flex align-center flex-wrap gap-x-2">
              <h4 class="text-h4 font-weight-semibold stats-card__value">
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
.stats-card__body {
  /* بدون هذا العمود ما بيصغر عن عرض محتواه، فالقيم المالية الكبيرة والـ chip
     بيطلعوا برّا الكرت لما تكون 6 كروت بسطر (xl) أو عالموبايل */
  min-inline-size: 0;
}
.stats-card__value {
  overflow-wrap: anywhere;
}
.stats-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 25px rgba(var(--v-theme-on-surface), 0.08) !important;
}
</style>

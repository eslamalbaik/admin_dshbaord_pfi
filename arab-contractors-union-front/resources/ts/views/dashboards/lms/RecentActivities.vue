<script setup lang="ts">
import { useDashboardStore } from '@/stores/dashboardStore'

const dashboardStore = useDashboardStore()

function timeAgo(dateStr: string) {
  const diffMs = Date.now() - new Date(dateStr).getTime()
  const minutes = Math.floor(diffMs / 60000)
  if (minutes < 1) return 'الآن'
  if (minutes < 60) return `منذ ${minutes} دقيقة`
  const hours = Math.floor(minutes / 60)
  if (hours < 24) return `منذ ${hours} ساعة`
  const days = Math.floor(hours / 24)
  return `منذ ${days} يوم`
}

const activities = computed(() => {
  const contractorEvents = dashboardStore.latestContractors.map(c => ({
    title: 'تسجيل مقاول جديد',
    description: `${c.name} انضم إلى الاتحاد`,
    time: c.created_at,
    icon: 'tabler-building-factory-2',
    color: 'success',
  }))

  const paymentEvents = dashboardStore.latestPayments.map(p => ({
    title: 'دفعة مستلمة',
    description: `₪ ${p.amount} من ${p.contractor ?? 'مقاول'}`,
    time: p.created_at,
    icon: 'tabler-cash',
    color: 'primary',
  }))

  return [...contractorEvents, ...paymentEvents]
    .sort((a, b) => new Date(b.time).getTime() - new Date(a.time).getTime())
    .slice(0, 6)
    .map(a => ({ ...a, time: timeAgo(a.time) }))
})
</script>

<template>
  <VCard class="h-100">
    <VCardItem>
      <VCardTitle style="font-family:Cairo,sans-serif">الأنشطة الأخيرة</VCardTitle>
      <VCardSubtitle style="font-family:Cairo,sans-serif">أحداث الاتحاد</VCardSubtitle>
    </VCardItem>

    <VCardText class="pa-0">
      <VTimeline
        v-if="activities.length"
        density="compact"
        align="start"
        truncate-line="both"
        class="px-5 pb-4"
      >
        <VTimelineItem
          v-for="(activity, index) in activities"
          :key="index"
          :dot-color="activity.color"
          size="x-small"
        >
          <div class="d-flex justify-space-between align-start flex-wrap gap-2">
            <div>
              <p class="text-body-1 font-weight-medium mb-0" style="font-family:Cairo,sans-serif">
                {{ activity.title }}
              </p>
              <p class="text-body-2 text-medium-emphasis mb-0" style="font-family:Cairo,sans-serif">
                {{ activity.description }}
              </p>
            </div>
            <span class="text-caption text-medium-emphasis text-no-wrap" style="font-family:Cairo,sans-serif">{{ activity.time }}</span>
          </div>
        </VTimelineItem>
      </VTimeline>
      <p v-else class="text-center text-medium-emphasis py-8 px-5" style="font-family:Cairo,sans-serif">
        لا توجد أنشطة بعد
      </p>
    </VCardText>
  </VCard>
</template>

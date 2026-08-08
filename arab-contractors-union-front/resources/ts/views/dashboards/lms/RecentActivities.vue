<script setup lang="ts">
const activities = ref([
  {
    title: 'تسجيل مقاول جديد',
    description: 'شركة المدار للمقاولات انضمت إلى الاتحاد',
    time: 'منذ 10 دقائق',
    icon: 'tabler-building-factory-2',
    color: 'success',
  },
  {
    title: 'دفعة مستلمة',
    description: '₪ 850 من شركة النور للإنشاءات',
    time: 'منذ 25 دقيقة',
    icon: 'tabler-cash',
    color: 'primary',
  },
  {
    title: 'طلب عضوية جديد',
    description: 'مقاول عمر خليل قدّم طلب عضوية',
    time: 'منذ ساعة',
    icon: 'tabler-id-badge-2',
    color: 'info',
  },
  {
    title: 'عطاء جديد',
    description: 'عطاء مشروع توسعة الطرق المحلية',
    time: 'منذ 3 ساعات',
    icon: 'tabler-files',
    color: 'secondary',
  },
  {
    title: 'تجديد عضوية',
    description: 'مؤسسة الفارابي جددت اشتراك سنوي',
    time: 'منذ 5 ساعات',
    icon: 'tabler-refresh',
    color: 'success',
  },
])
</script>

<template>
  <VCard class="h-100">
    <VCardItem>
      <VCardTitle style="font-family:Cairo,sans-serif">الأنشطة الأخيرة</VCardTitle>
      <VCardSubtitle style="font-family:Cairo,sans-serif">أحداث الاتحاد</VCardSubtitle>
    </VCardItem>

    <VCardText class="pa-0">
      <VTimeline
        density="compact"
        align="start"
        truncate-line="both"
        class="px-5 pb-4"
      >
        <VTimelineItem
          v-for="activity in activities"
          :key="activity.title"
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
    </VCardText>
  </VCard>
</template>

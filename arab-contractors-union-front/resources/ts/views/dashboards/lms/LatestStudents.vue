<script setup lang="ts">
import { useDashboardStore } from '@/stores/dashboardStore'

const dashboardStore = useDashboardStore()
const contractors = computed(() => dashboardStore.latestContractors)

const getInitials = (name: string) => {
  return name.split(' ').map(n => n[0]).join('').substring(0, 2).toUpperCase()
}

const avatarColors = ['primary', 'success', 'info', 'warning', 'error']
</script>

<template>
  <VCard class="h-100">
    <VCardItem>
      <VCardTitle style="font-family:Cairo,sans-serif">أحدث المقاولين</VCardTitle>
      <VCardSubtitle style="font-family:Cairo,sans-serif">المقاولون المسجلون حديثاً</VCardSubtitle>

      <template #append>
        <VBtn
          variant="tonal"
          size="small"
          :to="{ name: 'contractors' }"
        >
          عرض الكل
        </VBtn>
      </template>
    </VCardItem>

    <VCardText class="pa-0">
      <VList lines="two">
        <template
          v-for="(contractor, index) in contractors"
          :key="contractor.email"
        >
          <VListItem>
            <template #prepend>
              <VAvatar
                :color="avatarColors[index % avatarColors.length]"
                variant="tonal"
                size="40"
              >
                <span class="text-body-2 font-weight-medium">{{ getInitials(contractor.name) }}</span>
              </VAvatar>
            </template>

            <VListItemTitle class="font-weight-medium" style="font-family:Cairo,sans-serif">
              {{ contractor.name }}
            </VListItemTitle>
            <VListItemSubtitle style="font-family:Cairo,sans-serif">
              {{ contractor.trade || contractor.course || contractor.email }}
            </VListItemSubtitle>

            <template #append>
              <VChip
                v-if="contractor.status"
                :color="contractor.status === 'active' ? 'success' : 'warning'"
                size="x-small"
                label
              >
                {{ contractor.status === 'active' ? 'نشط' : 'معلّق' }}
              </VChip>
              <span v-else class="text-caption text-medium-emphasis">منذ يوم</span>
            </template>
          </VListItem>
          <VDivider v-if="index < contractors.length - 1" />
        </template>

        <VListItem v-if="!contractors.length">
          <VListItemTitle class="text-center text-medium-emphasis" style="font-family:Cairo,sans-serif">
            لا توجد بيانات بعد
          </VListItemTitle>
        </VListItem>
      </VList>
    </VCardText>
  </VCard>
</template>

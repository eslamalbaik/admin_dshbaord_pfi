<script setup lang="ts">
import { useAuthStore } from '@/stores/authStore'

// شريط الترحيب أعلى لوحة التحكم: الاسم والشارة حسب المستخدم الحالي ودوره
const authStore = useAuthStore()

const ROLE_BADGES: Record<string, string> = {
  admin: 'مالك المنصة',
  accountant: 'محاسب',
  supervisor: 'مشرف',
  instructor: 'مشرف',
}

const greetingName = computed(() => {
  const u = authStore.user as any
  if (u?.role === 'admin')
    return 'مدير المنصة'

  return u?.fullName || u?.name || ''
})

const roleBadge = computed(() => ROLE_BADGES[authStore.user?.role ?? ''] ?? '')
</script>

<template>
  <VCard
    v-if="authStore.user"
    class="welcome-banner mb-6"
  >
    <VCardText class="d-flex align-center flex-wrap gap-3 py-5 px-6">
      <span class="welcome-banner__text">
        مرحباً، {{ greetingName }}
      </span>
      <span
        v-if="roleBadge"
        class="welcome-banner__badge"
      >
        {{ roleBadge }}
      </span>
    </VCardText>
  </VCard>
</template>

<style scoped>
/* هوية الاتحاد: كحلي الشعار (primary) مع الأصفر الذهبي */
.welcome-banner {
  background: linear-gradient(72.47deg, rgb(var(--v-theme-primary)) 22.16%, rgba(var(--v-theme-primary), 0.7) 76.47%) !important;
  box-shadow: 0 4px 16px rgba(0, 2, 105, 0.2) !important;
}

.welcome-banner__text {
  color: #fff;
  font-size: 1.375rem;
  font-weight: 600;
  line-height: 1.4;
}

.welcome-banner__badge {
  display: inline-flex;
  align-items: center;
  border-radius: 999px;
  background: #ffbe1a;
  color: #000269;
  font-size: 0.8125rem;
  font-weight: 700;
  line-height: 1;
  padding-block: 7px;
  padding-inline: 14px;
  white-space: nowrap;
}

@media (max-width: 600px) {
  .welcome-banner__text {
    font-size: 1.125rem;
  }
}
</style>

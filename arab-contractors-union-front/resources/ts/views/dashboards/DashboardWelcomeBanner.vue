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
.welcome-banner__text {
  color: rgba(var(--v-theme-on-surface), 0.75);
  font-size: 1.375rem;
  font-weight: 500;
  line-height: 1.4;
}

.welcome-banner__badge {
  display: inline-flex;
  align-items: center;
  border-radius: 999px;
  background: #6b0a1e;
  color: #fff;
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

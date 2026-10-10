<script setup lang="ts">
import { useQueryClient } from '@tanstack/vue-query'
import { useRouter } from 'vue-router'
import api from '@/plugins/axios'
import { NOTIFICATIONS_KEY } from '@/composables/useNotifications'
import type { AdminToast } from '@/composables/useRealtimeAdminNotifications'
import { adminToasts, dismissToast } from '@/composables/useRealtimeAdminNotifications'
import { notificationLink } from '@/utils/notificationLink'

// تنبيهات الإشعارات الفورية: بطاقة لكل إشعار أعلى الشاشة بعنوان وأيقونة ولون
// حسب النوع، وشريط عدّ تنازلي يتوقف عند مرور الماوس، والضغط عليها يفتح الصفحة
// المرتبطة ويعلّم الإشعار كمقروء.

const router = useRouter()
const queryClient = useQueryClient()

async function open(toast: AdminToast) {
  dismissToast(toast.id)

  if (toast.notificationId) {
    api.patch(`/api/v1/notifications/${toast.notificationId}/mark-as-read`)
      .then(() => queryClient.invalidateQueries({ queryKey: NOTIFICATIONS_KEY }))
      .catch(() => {})
  }

  const target = notificationLink(toast.data)
  if (target)
    router.push(target)
}

function accentStyle(toast: AdminToast) {
  // ألوان الثيم تُمرَّر بالاسم (primary...) واللون المالي بقيمة hex
  const c = toast.color.startsWith('#') ? toast.color : `rgb(var(--v-theme-${toast.color}))`

  return { '--toast-accent': c, '--toast-duration': `${toast.duration}ms` }
}
</script>

<template>
  <div
    class="admin-toasts"
    role="region"
    aria-label="إشعارات جديدة"
    aria-live="polite"
  >
    <TransitionGroup name="admin-toast">
      <div
        v-for="toast in adminToasts"
        :key="toast.id"
        class="admin-toast"
        :class="{ 'admin-toast--financial': toast.financial }"
        :style="accentStyle(toast)"
        role="button"
        tabindex="0"
        @click="open(toast)"
        @keydown.enter="open(toast)"
      >
        <div class="admin-toast__icon">
          <VIcon
            :icon="toast.icon"
            size="24"
          />
        </div>

        <div class="admin-toast__body">
          <div class="admin-toast__head">
            <span
              v-if="toast.financial"
              class="admin-toast__tag"
            >مالي</span>
            <span class="admin-toast__title">{{ toast.title }}</span>
          </div>
          <p class="admin-toast__text">
            {{ toast.text }}
          </p>
          <span class="admin-toast__hint">الآن · اضغط للفتح</span>
        </div>

        <button
          type="button"
          class="admin-toast__close"
          aria-label="إغلاق"
          @click.stop="dismissToast(toast.id)"
        >
          <VIcon
            icon="tabler-x"
            size="18"
          />
        </button>

        <span
          class="admin-toast__progress"
          @animationend="dismissToast(toast.id)"
        />
      </div>
    </TransitionGroup>
  </div>
</template>

<style lang="scss">
.admin-toasts {
  position: fixed;
  z-index: 2600;
  display: flex;
  flex-direction: column;
  gap: 12px;
  inline-size: 380px;
  inset-block-start: 88px;
  inset-inline-end: 24px;
  pointer-events: none;
}

.admin-toast {
  position: relative;
  display: flex;
  overflow: hidden;
  align-items: flex-start;
  padding: 14px;
  border: 1px solid rgba(var(--v-border-color), 0.12);
  border-radius: 14px;
  background: rgb(var(--v-theme-surface));
  box-shadow: 0 12px 32px rgba(15, 23, 42, 0.18), 0 2px 6px rgba(15, 23, 42, 0.08);
  color: rgb(var(--v-theme-on-surface));
  cursor: pointer;
  gap: 12px;
  pointer-events: auto;
  text-align: start;

  // شريط لوني على طرف البطاقة يدل على نوع الإشعار
  &::before {
    position: absolute;
    background: var(--toast-accent);
    content: "";
    inline-size: 5px;
    inset-block: 0;
    inset-inline-start: 0;
  }

  &:hover,
  &:focus-visible {
    box-shadow: 0 16px 40px rgba(15, 23, 42, 0.24), 0 2px 6px rgba(15, 23, 42, 0.1);
    outline: none;
    transform: translateY(-1px);
  }

  &:hover .admin-toast__progress {
    animation-play-state: paused;
  }
}

.admin-toast--financial {
  border: 2px solid rgba(183, 121, 31, 0.55);
  background:
    linear-gradient(135deg, rgba(246, 196, 83, 0.18), rgba(246, 196, 83, 0.04) 60%),
    rgb(var(--v-theme-surface));

  &::before {
    inline-size: 6px;
  }

  .admin-toast__icon {
    animation: admin-toast-ring 0.9s ease-in-out 2;
    background: var(--toast-accent);
    color: #fff;
  }
}

.admin-toast__icon {
  display: flex;
  flex-shrink: 0;
  align-items: center;
  justify-content: center;
  border-radius: 12px;
  margin-inline-start: 4px;
  background: color-mix(in srgb, var(--toast-accent) 16%, transparent);
  block-size: 44px;
  color: var(--toast-accent);
  inline-size: 44px;
}

.admin-toast__body {
  flex: 1;
  min-inline-size: 0;
}

.admin-toast__head {
  display: flex;
  align-items: center;
  margin-block-end: 4px;
  gap: 6px;
}

.admin-toast__title {
  font-size: 0.95rem;
  font-weight: 700;
  line-height: 1.4;
}

.admin-toast__tag {
  flex-shrink: 0;
  padding: 1px 8px;
  border-radius: 999px;
  background: var(--toast-accent);
  color: #fff;
  font-size: 0.7rem;
  font-weight: 700;
  line-height: 1.6;
}

.admin-toast__text {
  margin: 0;
  color: rgba(var(--v-theme-on-surface), 0.78);
  font-size: 0.875rem;
  line-height: 1.55;
}

.admin-toast__hint {
  display: block;
  margin-block-start: 6px;
  color: rgba(var(--v-theme-on-surface), 0.5);
  font-size: 0.75rem;
}

.admin-toast__close {
  display: flex;
  flex-shrink: 0;
  align-items: center;
  justify-content: center;
  padding: 0;
  border: 0;
  border-radius: 8px;
  background: transparent;
  block-size: 28px;
  color: rgba(var(--v-theme-on-surface), 0.55);
  cursor: pointer;
  inline-size: 28px;

  &:hover {
    background: rgba(var(--v-theme-on-surface), 0.08);
    color: rgb(var(--v-theme-on-surface));
  }
}

.admin-toast__progress {
  position: absolute;
  animation: admin-toast-countdown var(--toast-duration) linear forwards;
  background: var(--toast-accent);
  block-size: 3px;
  inline-size: 100%;
  inset-block-end: 0;
  inset-inline-start: 0;
  opacity: 0.7;
}

// العرض (لا scaleX) حتى يتقلّص الشريط نحو بداية السطر في RTL وLTR معاً
@keyframes admin-toast-countdown {
  from { inline-size: 100%; }
  to { inline-size: 0; }
}

@keyframes admin-toast-ring {
  0%, 100% { transform: rotate(0); }
  20% { transform: rotate(-14deg); }
  40% { transform: rotate(12deg); }
  60% { transform: rotate(-8deg); }
  80% { transform: rotate(4deg); }
}

.admin-toast-enter-active,
.admin-toast-leave-active {
  transition: all 0.3s cubic-bezier(0.2, 0.8, 0.2, 1);
}

.admin-toast-enter-from {
  opacity: 0;
  transform: translateY(-16px) scale(0.96);
}

.admin-toast-leave-to {
  opacity: 0;
  transform: scale(0.94);
}

.admin-toast-move {
  transition: transform 0.3s ease;
}

@media (max-width: 600px) {
  .admin-toasts {
    inline-size: auto;
    inset-block-start: 76px;
    inset-inline: 12px;
  }
}

@media (prefers-reduced-motion: reduce) {
  .admin-toast,
  .admin-toast__icon,
  .admin-toast-enter-active,
  .admin-toast-leave-active {
    animation: none !important;
    transition: none !important;
  }
}
</style>

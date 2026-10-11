<script setup lang="ts">
import { useRouteLoading } from '@/composables/useRouteLoading'

/**
 * شعار الاتحاد المتحرّك (تجميع القطع) أثناء التنقل بين الصفحات.
 * بيظهر بحالتين: تنقل الـrouter (useRouteLoading من guards.ts)، وانتظار Suspense لصفحة
 * إلها setup غير متزامن (fallbackHandle/resolveHandle من الـlayouts).
 * الملف ثابت بـpublic/ فبينحمّل مرة وحدة وبيضل بالكاش، والحركة SMIL جوّاه فبتشتغل بـ<img>.
 */
const { isRouteLoading } = useRouteLoading()
const isFallbackState = ref(false)

const visible = computed(() => isRouteLoading.value || isFallbackState.value)

const fallbackHandle = () => {
  isFallbackState.value = true
}

const resolveHandle = () => {
  isFallbackState.value = false
}

defineExpose({
  fallbackHandle,
  resolveHandle,
})
</script>

<template>
  <Transition name="app-loading-fade">
    <div
      v-if="visible"
      class="app-loading-overlay"
      role="status"
      aria-live="polite"
    >
      <img
        src="/images/loader/logo-assemble.svg"
        alt=""
        class="app-loading-logo"
      >
      <span class="app-loading-sr">جاري التحميل…</span>
    </div>
  </Transition>
</template>

<style lang="scss" scoped>
.app-loading-overlay {
  position: fixed;
  z-index: 9999;
  display: flex;
  align-items: center;
  justify-content: center;
  backdrop-filter: blur(2px);
  background: rgba(var(--v-theme-background), 0.72);
  inset: 0;
}

.app-loading-logo {
  inline-size: clamp(120px, 18vw, 180px);
  max-inline-size: 60vw;
  pointer-events: none;
  user-select: none;
}

.app-loading-sr {
  position: absolute;
  overflow: hidden;
  block-size: 1px;
  clip-path: inset(50%);
  inline-size: 1px;
  white-space: nowrap;
}

.app-loading-fade-enter-active,
.app-loading-fade-leave-active {
  transition: opacity 0.2s ease;
}

.app-loading-fade-enter-from,
.app-loading-fade-leave-to {
  opacity: 0;
}

@media (prefers-reduced-motion: reduce) {
  .app-loading-fade-enter-active,
  .app-loading-fade-leave-active {
    transition: none;
  }
}
</style>

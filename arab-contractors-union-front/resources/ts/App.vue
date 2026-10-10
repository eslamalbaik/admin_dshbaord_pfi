<script setup lang="ts">
import { useRoute } from 'vue-router'
import { VApp } from 'vuetify/components/VApp'
import { useTheme } from 'vuetify'
import ScrollToTop from '@core/components/ScrollToTop.vue'
import initCore from '@core/initCore'
import { initConfigStore, useConfigStore } from '@core/stores/config'
import { hexToRgb } from '@core/utils/colorConverter'
import { useAuthStore } from '@/stores/authStore'
import { useRealtimeAdminNotifications } from '@/composables/useRealtimeAdminNotifications'
import AdminNotificationToasts from '@/components/AdminNotificationToasts.vue'
import { useAppUpdateCheck } from '@/composables/useAppUpdateCheck'

const route = useRoute()
const { global } = useTheme()

// ℹ️ Sync current theme with initial loader theme
initCore()
initConfigStore()

const configStore = useConfigStore()
const authStore = useAuthStore()

// تهيئة المصادقة عند الإقلاع — بدونها يبقى splash الشعار يدور للأبد على المسارات
// العامة (login/landing) إذا كان في accessToken مخزّن، لأن حارس الراوتر يتخطّى
// fetchUser على العامة فلا شيء يصفّر isInitializing.
authStore.fetchUser()

// إشعارات الأدمن الفورية (Reverb) — تشترك/تُلغي الاشتراك تلقائياً مع تسجيل الدخول/الخروج
useRealtimeAdminNotifications()

// حالة الاتصال — شريط عام يطمئن المستخدم أن ما أدخله محفوظ محلياً أثناء الانقطاع.
// مكتوب بعناصر HTML عادية لا مكوّنات Vuetify: صفحات layout='pure' تُصيَّر خارج
// VApp فلا تعمل داخلها مكوّنات مثل VAlert/VSnackbar بشكل موثوق.
// شريط «يتوفر تحديث جديد» — يظهر لمستخدمي اللوحة لما يُنشر إصدار أحدث من الواجهة
const { showUpdateBar, reloadNow, snooze } = useAppUpdateCheck()

const isOnline = useOnline()
const justReconnected = ref(false)

watch(isOnline, (online, wasOnline) => {
  if (online && wasOnline === false) {
    justReconnected.value = true
    setTimeout(() => { justReconnected.value = false }, 4000)
  }
})
</script>

<template>
  <!-- Splash Screen / Loader during initial auth check -->
  <div v-if="authStore.isInitializing" id="loading-bg">
    <div class="loading-logo">
      <img src="/logo.png" alt="اتحاد المقاولين الفلسطينيين" style="width:160px;height:160px;object-fit:contain;" />
    </div>
    <div class="loading">
      <div class="effect-1 effects"></div>
      <div class="effect-2 effects"></div>
      <div class="effect-3 effects"></div>
    </div>
  </div>

  <VLocaleProvider v-else :rtl="configStore.isAppRTL">
    <!-- ℹ️ Conditionally bypass VApp for pure layouts to prevent Vuetify styling collisions -->
    <component 
      :is="route.meta.layout === 'pure' ? 'div' : VApp"
      :style="route.meta.layout === 'pure' ? '' : `--v-global-theme-primary: ${hexToRgb(global.current.value.colors.primary)}`"
      :class="route.meta.layout === 'pure' ? '' : undefined"
    >
      <div v-if="!isOnline" class="conn-bar conn-bar--offline">
        <span class="conn-dot" />
        انقطع الاتصال بالإنترنت — بياناتك محفوظة ولن تفقدها، وسيُستأنف الإرسال تلقائياً عند عودة الاتصال.
      </div>
      <div v-else-if="justReconnected" class="conn-bar conn-bar--online">
        <span class="conn-dot" />
        عاد الاتصال بالإنترنت — جارٍ استئناف العملية.
      </div>

      <div
        v-if="showUpdateBar && authStore.isLoggedIn"
        class="update-bar"
        role="status"
      >
        <span class="update-bar__text">يتوفر تحديث جديد للوحة التحكم. حدّث الصفحة لتحصل على آخر التعديلات.</span>
        <button type="button" class="update-bar__btn update-bar__btn--primary" @click="reloadNow">
          حدّث الآن
        </button>
        <button type="button" class="update-bar__btn" @click="snooze">
          لاحقاً
        </button>
      </div>

      <RouterView />
      <ScrollToTop v-if="route.meta.layout !== 'pure'" />

      <AdminNotificationToasts v-if="authStore.isLoggedIn" />
    </component>
  </VLocaleProvider>
</template>

<style>
.conn-bar {
  position: fixed;
  inset-block-start: 0;
  inset-inline: 0;
  z-index: 3000;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
  padding: 0.6rem 1rem;
  font-size: 0.85rem;
  font-weight: 600;
  color: #fff;
  text-align: center;
}

.conn-bar--offline { background: #b71c1c; }
.conn-bar--online { background: #1b5e20; }

.conn-dot {
  flex-shrink: 0;
  inline-size: 8px;
  block-size: 8px;
  border-radius: 50%;
  background: currentcolor;
}

.conn-bar--offline .conn-dot { animation: conn-pulse 1.2s ease-in-out infinite; }

.update-bar {
  position: fixed;
  inset-block-end: 1rem;
  left: 50%;
  transform: translateX(-50%);
  z-index: 3000;
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: center;
  gap: 0.5rem 0.75rem;
  max-inline-size: calc(100vw - 2rem);
  inline-size: max-content;
  padding: 0.65rem 1rem;
  border-radius: 10px;
  background: #1e3a5f;
  box-shadow: 0 6px 20px rgba(0, 0, 0, 0.25);
  color: #fff;
  font-size: 0.9rem;
  font-weight: 600;
}

.update-bar__text { text-align: center; }

.update-bar__btn {
  padding: 0.35rem 0.9rem;
  border: 1px solid rgba(255, 255, 255, 0.6);
  border-radius: 6px;
  background: transparent;
  color: #fff;
  cursor: pointer;
  font: inherit;
}

.update-bar__btn--primary {
  border-color: #fff;
  background: #fff;
  color: #1e3a5f;
}

@keyframes conn-pulse {
  50% { opacity: 0.25; }
}
</style>

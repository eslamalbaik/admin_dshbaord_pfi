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

      <Transition name="update-pop">
        <div
          v-if="showUpdateBar && authStore.isLoggedIn"
          class="update-overlay"
          @click.self="snooze"
        >
          <div
            class="update-dialog"
            role="dialog"
            aria-modal="true"
            aria-labelledby="update-dialog-title"
          >
            <div class="update-dialog__icon" aria-hidden="true">
              <svg viewBox="0 0 24 24" width="30" height="30" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M21 12a9 9 0 1 1-2.64-6.36" />
                <polyline points="21 3 21 9 15 9" />
              </svg>
            </div>
            <h2 id="update-dialog-title" class="update-dialog__title">
              يتوفر تحديث جديد
            </h2>
            <p class="update-dialog__text">
              نزل إصدار أحدث من لوحة التحكم. حدّث الصفحة لتحصل على آخر التعديلات والإصلاحات.
            </p>
            <div class="update-dialog__actions">
              <button type="button" class="update-dialog__btn update-dialog__btn--primary" @click="reloadNow">
                حدّث الآن
              </button>
              <button type="button" class="update-dialog__btn update-dialog__btn--ghost" @click="snooze">
                لاحقاً
              </button>
            </div>
          </div>
        </div>
      </Transition>

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

.update-overlay {
  position: fixed;
  inset: 0;
  z-index: 3000;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 1rem;
  background: rgba(17, 24, 39, 0.55);
  backdrop-filter: blur(3px);
}

.update-dialog {
  inline-size: 100%;
  max-inline-size: 420px;
  padding: 2rem 1.75rem 1.5rem;
  border-radius: 16px;
  background: #fff;
  box-shadow: inset 0 5px 0 #c62828, 0 20px 50px rgba(0, 0, 0, 0.3);
  color: #1f2937;
  text-align: center;
}

.update-dialog__icon {
  display: flex;
  align-items: center;
  justify-content: center;
  margin: 0 auto 1rem;
  inline-size: 64px;
  block-size: 64px;
  border-radius: 50%;
  background: #fdecea;
  color: #c62828;
}

.update-dialog__title {
  margin: 0 0 0.5rem;
  font-size: 1.3rem;
  font-weight: 700;
  color: #b71c1c;
}

.update-dialog__text {
  margin: 0 0 1.5rem;
  font-size: 0.95rem;
  line-height: 1.7;
  color: #4b5563;
}

.update-dialog__actions {
  display: flex;
  gap: 0.75rem;
}

.update-dialog__btn {
  flex: 1;
  min-block-size: 44px;
  padding: 0.6rem 1rem;
  border: 2px solid #c62828;
  border-radius: 10px;
  cursor: pointer;
  font: inherit;
  font-size: 0.95rem;
  font-weight: 700;
  transition: background-color 0.15s, color 0.15s, box-shadow 0.15s;
}

/* .update-dialog قبل الكلاس: قاعدة الثيم button, [type="button"] { color: inherit } إلها نفس
   القوة وبتنحمّل بعدنا بالـ build، فكانت تخلّي نص الأزرار غامق بدل أبيض/أحمر */
.update-dialog .update-dialog__btn--primary {
  background: #c62828;
  color: #fff;
}

.update-dialog__btn--primary:hover { background: #a91f1f; border-color: #a91f1f; }

.update-dialog .update-dialog__btn--ghost {
  background: #fff;
  color: #c62828;
}

.update-dialog__btn--ghost:hover { background: #fdecea; }

.update-dialog__btn:focus-visible {
  outline: none;
  box-shadow: 0 0 0 3px rgba(198, 40, 40, 0.35);
}

.update-pop-enter-active,
.update-pop-leave-active { transition: opacity 0.2s ease; }

.update-pop-enter-active .update-dialog,
.update-pop-leave-active .update-dialog { transition: transform 0.2s ease; }

.update-pop-enter-from,
.update-pop-leave-to { opacity: 0; }

.update-pop-enter-from .update-dialog,
.update-pop-leave-to .update-dialog { transform: scale(0.94); }

@media (max-width: 420px) {
  .update-dialog__actions { flex-direction: column; }
}

@keyframes conn-pulse {
  50% { opacity: 0.25; }
}
</style>

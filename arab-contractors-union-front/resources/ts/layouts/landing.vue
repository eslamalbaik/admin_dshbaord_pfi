<script setup lang="ts">
import PublicNavbar from '@/components/PublicNavbar.vue'
import PublicFooter from '@/components/PublicFooter.vue'
</script>

<template>
  <div dir="rtl" class="lp-layout">
    <PublicNavbar />
    <!-- each public page fades/slides in when navigating from the header (off for reduced motion) -->
    <RouterView v-slot="{ Component, route }">
      <Transition name="lp-page" mode="out-in">
        <div :key="route.path" class="lp-page">
          <component :is="Component" />
        </div>
      </Transition>
    </RouterView>
    <PublicFooter />
  </div>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800;900&display=swap');
/* Base/Global Styles for the public pages nested layout */
.lp-layout {
  min-height: 100vh;
  display: flex;
  flex-direction: column;
  background: #ffffff;
  font-family: 'Tajawal', 'Cairo', sans-serif;
  /* blue contact strip (40px) + PublicNavbar (85px); page heroes slide up under them */
  --pub-nav-h: 125px;
}
@media (max-width: 1100px) {
  .lp-layout { --pub-nav-h: 103px; }
}

/* page wrapper keeps one element root for the transition, whatever the page renders */
.lp-page { display: flex; flex-direction: column; flex: 1 0 auto; }
.lp-page-enter-active { transition: opacity .38s ease-out, transform .45s cubic-bezier(.2, .8, .3, 1); }
.lp-page-leave-active { transition: opacity .14s ease-in, transform .14s ease-in; }
.lp-page-enter-from { opacity: 0; transform: translateY(18px); }
.lp-page-leave-to { opacity: 0; transform: translateY(-6px); }
@media (prefers-reduced-motion: reduce) {
  .lp-page-enter-active, .lp-page-leave-active { transition: none; }
}
</style>

<style>
/* Vuetify typography/buttons force their own font with !important; keep the public pages on Tajawal */
.lp-layout [class*="text-h"],
.lp-layout [class*="text-body"],
.lp-layout [class*="text-subtitle"],
.lp-layout .v-btn {
  font-family: 'Tajawal', 'Cairo', sans-serif !important;
}
</style>

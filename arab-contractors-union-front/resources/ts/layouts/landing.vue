<script setup lang="ts">
import PublicNavbar from '@/components/PublicNavbar.vue'
import PublicFooter from '@/components/PublicFooter.vue'
</script>

<template>
  <div dir="rtl" class="lp-layout">
    <PublicNavbar />
    <RouterView />
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
  /* height of the floating PublicNavbar (padding + card); the home hero slides up under it */
  --pub-nav-h: 114px;
}
@media (max-width: 1100px) {
  .lp-layout { --pub-nav-h: 88px; }
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

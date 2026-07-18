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
@import url('https://fonts.cdnfonts.com/css/neo-sans-arabic');
/* Base/Global Styles for the public pages nested layout */
.lp-layout {
  min-height: 100vh;
  display: flex;
  flex-direction: column;
  background: #ffffff;
}
</style>

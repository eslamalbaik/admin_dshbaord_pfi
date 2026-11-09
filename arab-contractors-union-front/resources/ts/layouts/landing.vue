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

/* ── Shared inner-page hero: construction photo + navy/orange overlay (every public inner page) ──
   Pages keep their own markup/typography; this only swaps the background, so it must out-rank their scoped rules. */
.lp-layout .lp-page .page-hero,
.lp-layout .lp-page .page-header,
.lp-layout .lp-page .vp-hero {
  position: relative; overflow: hidden; isolation: isolate;
  background-color: #000068;
  background-image:
    linear-gradient(260deg, rgba(0, 0, 90, .8) 0%, rgba(0, 0, 120, .6) 42%, rgba(0, 0, 120, .28) 75%, rgba(0, 0, 90, .18) 100%),
    url('/images/landing/inner-hero.webp');
  background-size: cover; background-position: center 38%; background-repeat: no-repeat;
  border-bottom: 0;
  text-align: start;
}
/* orange accent line along the bottom edge, like the brand's yellow/orange rule */
.lp-layout .lp-page .page-hero::after,
.lp-layout .lp-page .page-header::after,
.lp-layout .lp-page .vp-hero::after {
  content: ''; position: absolute; inset-inline: 0; bottom: 0; height: 4px; z-index: 3; pointer-events: none;
  background: linear-gradient(90deg, #f5a623 0%, #d67a00 40%, rgba(214, 122, 0, 0) 100%);
}
.lp-layout .lp-page .page-hero::before,
.lp-layout .lp-page .vp-hero::before { display: none; }
.lp-layout .lp-page .page-hero-shapes,
.lp-layout .lp-page .vp-hero-glow { display: none; }
.lp-layout .lp-page .page-hero { padding-bottom: 3rem; min-height: calc(var(--pub-nav-h, 125px) + 250px); }
.lp-layout .lp-page .page-header { padding-bottom: 3rem; min-height: calc(var(--pub-nav-h, 125px) + 250px); }
.lp-layout .lp-page .vp-hero { padding-bottom: 5.5rem; }
/* titles on the news/events header were navy-on-light */
.lp-layout .lp-page .page-header .page-title { color: #fff; text-shadow: 0 3px 18px rgba(0, 0, 40, .45); }
.lp-layout .lp-page .page-header .page-desc { color: rgba(255, 255, 255, .86); margin-inline: 0; max-width: 600px; }
.lp-layout .lp-page .vp-hero-inner { text-align: start; align-items: flex-start; }
.lp-layout .lp-page .vp-crumb { justify-content: flex-start; }
.lp-layout .lp-page .vp-sub { margin-inline: 0; }
@media (max-width: 760px) {
  .lp-layout .lp-page .page-hero,
  .lp-layout .lp-page .page-header,
  .lp-layout .lp-page .vp-hero {
    background-image:
      linear-gradient(180deg, rgba(0, 0, 90, .75) 0%, rgba(0, 0, 120, .55) 60%, rgba(0, 0, 90, .5) 100%),
      url('/images/landing/inner-hero-sm.webp');
    background-position: 62% center;
  }
}

/* follow-us block: opposite the page title (left in RTL) on wide screens, below the title otherwise */
.lp-layout .lp-page .pub-follow-slot { position: relative; z-index: 2; max-width: 1240px; margin: 1.75rem auto 0; padding: 0 1.5rem; }
@media (min-width: 1001px) {
  .lp-layout .lp-page .page-hero > .pub-follow-slot,
  .lp-layout .lp-page .page-header > .pub-follow-slot,
  .lp-layout .lp-page .vp-hero > .pub-follow-slot {
    position: absolute; inset-inline: 0; top: var(--pub-nav-h, 125px); bottom: 0; margin: 0 auto; width: 100%;
    display: flex; align-items: center; justify-content: flex-end; pointer-events: none;
  }
}
</style>

<script setup lang="ts">
import PublicSocialFollow from '@/components/PublicSocialFollow.vue'
import type { Component } from 'vue'

defineProps<{
  title: string
  subtitle: string
  icon: Component
  // لون التمييز للأيقونة الكبيرة
  accent?: string
}>()
</script>

<template>
  <div class="social-hero">
    <div class="sh-shapes"><div class="sh-s sh-s1" /><div class="sh-s sh-s2" /></div>
    <div class="sh-cont sh-inner">
      <div class="sh-text">
        <div class="sh-crumb">
          <RouterLink to="/landing">الرئيسية</RouterLink><span>/</span><span>اجتماعيات</span><span>/</span><span>{{ title }}</span>
        </div>
        <h1 class="sh-title">{{ title }}</h1>
        <div class="sh-rule" />
        <p class="sh-sub">{{ subtitle }}</p>
      </div>
      <div class="sh-art" :style="{ '--accent': accent ?? '#f9a825' }">
        <div class="sh-ring"><component :is="icon" :size="84" :stroke-width="1.2" /></div>
      </div>
    </div>
    <svg class="sh-wave" viewBox="0 0 1440 60" preserveAspectRatio="none" aria-hidden="true">
      <path d="M0,40 C240,0 480,60 720,35 C960,10 1200,55 1440,25 L1440,60 L0,60 Z" fill="#f4f7fc" />
    </svg>
    <div class="pub-follow-slot"><PublicSocialFollow /></div>
  </div>
</template>

<style scoped>
.social-hero {
  background: linear-gradient(145deg, #0a1440 0%, #1a237e 62%, #283593 100%);
  margin-top: calc(-1 * var(--pub-nav-h, 114px));
  padding: calc(var(--pub-nav-h, 114px) + 2.5rem) 0 5.5rem;
  position: relative;
  overflow: hidden;
  font-family: 'Tajawal', 'Cairo', sans-serif;
}
.sh-shapes { position: absolute; inset: 0; pointer-events: none; }
.sh-s { position: absolute; border-radius: 50%; }
.sh-s1 { width: 460px; height: 460px; background: radial-gradient(circle, rgba(249,168,37,.14), transparent 65%); top: -120px; left: 4%; }
.sh-s2 { width: 320px; height: 320px; background: radial-gradient(circle, rgba(255,255,255,.07), transparent 65%); bottom: -90px; right: 12%; }
.sh-cont { max-width: 1240px; margin: 0 auto; padding: 0 1.5rem; }
.sh-inner { position: relative; z-index: 2; display: flex; align-items: center; justify-content: space-between; gap: 2rem; }
.sh-crumb { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem; font-size: .82rem; color: rgba(255,255,255,.6); margin-bottom: 1rem; }
.sh-crumb a { color: rgba(255,255,255,.6); text-decoration: none; }
.sh-crumb a:hover { color: #f9a825; }
.sh-title { font-size: clamp(2.2rem, 5vw, 3.4rem); font-weight: 900; color: #fff; line-height: 1.2; margin: 0 0 .75rem; }
.sh-rule { width: 64px; height: 4px; border-radius: 4px; background: #f9a825; margin-bottom: 1rem; }
.sh-sub { font-size: 1.1rem; color: rgba(255,255,255,.82); line-height: 1.8; max-width: 560px; margin: 0; }
.sh-art { flex-shrink: 0; }
.sh-ring {
  width: 190px; height: 190px; border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  color: var(--accent);
  background: radial-gradient(circle at 35% 30%, rgba(255,255,255,.16), rgba(255,255,255,.04));
  border: 1.5px solid rgba(255,255,255,.2);
  box-shadow: 0 0 0 14px rgba(255,255,255,.04), 0 20px 50px rgba(0,0,0,.25);
}
.sh-wave { position: absolute; bottom: -1px; left: 0; width: 100%; height: 44px; display: block; }

@media (max-width: 720px) {
  .sh-art { display: none; }
  .social-hero { padding-bottom: 4rem; }
}
</style>

<script setup lang="ts">
import PublicSocialIcon from '@/components/PublicSocialIcon.vue'
import { PUBLIC_SOCIAL_FOLLOW_TEXT, PUBLIC_SOCIAL_LINKS } from '@/utils/publicSocialLinks'
</script>

<template>
  <!-- "follow us" card shown in every inner-page hero (placed beside the title by .page-hero-inner in layouts/landing.vue) -->
  <aside class="pub-follow" aria-label="تابعونا">
    <p class="pub-follow-text">
      <span class="pub-follow-ico" aria-hidden="true">📢</span>
      {{ PUBLIC_SOCIAL_FOLLOW_TEXT }}
    </p>
    <div class="pub-follow-links">
      <a
        v-for="s in PUBLIC_SOCIAL_LINKS"
        :key="s.key"
        :href="s.href"
        target="_blank"
        rel="noopener noreferrer"
        class="pub-follow-btn"
        :class="`is-${s.key}`"
      >
        <span class="pub-follow-btn-ico"><PublicSocialIcon :name="s.key" :size="18" /></span>
        <span>{{ s.label }}</span>
      </a>
    </div>
  </aside>
</template>

<style scoped>
/* one strip: message on the start (right, RTL) and the three buttons on the end (left) */
.pub-follow {
  position: relative; z-index: 2;
  display: flex; align-items: center; justify-content: space-between; gap: 1.25rem 2rem; flex-wrap: wrap;
  margin-top: 2.25rem; padding: 1rem 1.5rem;
  border-radius: 16px;
  background: linear-gradient(90deg, rgba(0, 0, 90, .78), rgba(0, 0, 60, .6));
  border: 1px solid rgba(255, 255, 255, .16);
  border-inline-start: 4px solid #f5a623;
  box-shadow: 0 14px 34px rgba(0, 0, 40, .32);
  backdrop-filter: blur(6px); -webkit-backdrop-filter: blur(6px);
  color: #fff; text-align: start; font-family: 'Tajawal', 'Cairo', sans-serif;
}
.pub-follow-text { margin: 0; flex: 1 1 340px; font-size: 1rem; font-weight: 700; line-height: 1.85; color: rgba(255, 255, 255, .95); }
.pub-follow-ico { margin-inline-end: .25rem; }
.pub-follow-links { display: flex; flex-wrap: wrap; gap: .6rem; }
.pub-follow-btn {
  display: inline-flex; align-items: center; justify-content: center; gap: .5rem;
  padding: .55rem 1.1rem; border-radius: 12px;
  background: rgba(255, 255, 255, .1); border: 1px solid rgba(255, 255, 255, .24);
  color: #fff; font-size: .9rem; font-weight: 700; text-decoration: none; white-space: nowrap;
  transition: background .2s, border-color .2s, transform .2s, color .2s;
}
.pub-follow-btn-ico { display: inline-grid; place-items: center; color: #f5a623; transition: color .2s; }
.pub-follow-btn:hover { background: #f5a623; border-color: #f5a623; color: #00006e; transform: translateY(-2px); }
.pub-follow-btn:hover .pub-follow-btn-ico { color: #00006e; }
.pub-follow-btn:focus-visible { outline: 2px solid #f5a623; outline-offset: 2px; }

@media (max-width: 760px) {
  .pub-follow { margin-top: 1.5rem; padding: .9rem 1rem; flex-direction: column; align-items: stretch; }
  .pub-follow-text { flex-basis: auto; font-size: .92rem; }
  .pub-follow-links { display: grid; grid-template-columns: repeat(3, 1fr); gap: .45rem; }
  .pub-follow-btn { padding: .5rem .3rem; font-size: .8rem; gap: .3rem; }
}
@media (max-width: 380px) {
  .pub-follow-btn { font-size: .74rem; }
  .pub-follow-btn-ico :deep(svg) { width: 15px; height: 15px; }
}
</style>

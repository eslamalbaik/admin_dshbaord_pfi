<script setup lang="ts">
import PublicSocialIcon from '@/components/PublicSocialIcon.vue'
import { PUBLIC_SOCIAL_FOLLOW_TEXT, PUBLIC_SOCIAL_LINKS } from '@/utils/publicSocialLinks'
</script>

<template>
  <!-- "follow us" block of every inner-page hero: heading + icon-only round buttons, sits opposite the page title (see .pub-follow-slot in layouts/landing.vue) -->
  <aside class="pub-follow" aria-label="تابعونا">
    <h2 class="pub-follow-title">تابعونا</h2>
    <div class="pub-follow-links">
      <a
        v-for="s in PUBLIC_SOCIAL_LINKS"
        :key="s.key"
        :href="s.href"
        target="_blank"
        rel="noopener noreferrer"
        class="pub-follow-btn"
        :class="`is-${s.key}`"
        :aria-label="s.label"
        :title="s.label"
      >
        <PublicSocialIcon :name="s.key" :size="24" />
      </a>
    </div>
    <p class="pub-follow-text"><span aria-hidden="true">📢</span> {{ PUBLIC_SOCIAL_FOLLOW_TEXT }}</p>
  </aside>
</template>

<style scoped>
.pub-follow {
  display: flex; flex-direction: column; align-items: center; gap: .85rem;
  width: 100%; max-width: 290px; text-align: center; color: #fff;
  font-family: 'Tajawal', 'Cairo', sans-serif; pointer-events: auto;
}
.pub-follow-title { margin: 0; font-size: 1.35rem; font-weight: 800; color: #fff; position: relative; padding-bottom: .55rem; }
.pub-follow-title::after { content: ''; position: absolute; bottom: 0; inset-inline: 30%; height: 3px; border-radius: 3px; background: #f5a623; }
.pub-follow-links { display: flex; justify-content: center; gap: .9rem; }
.pub-follow-btn {
  width: 48px; height: 48px; border-radius: 50%; display: grid; place-items: center;
  color: #fff; text-decoration: none;
  background: rgba(255, 255, 255, .1); border: 1.5px solid rgba(255, 255, 255, .45);
  transition: background .2s, border-color .2s, color .2s, transform .2s;
}
.pub-follow-btn:hover { background: #f5a623; border-color: #f5a623; color: #00006e; transform: translateY(-3px); }
.pub-follow-btn:focus-visible { outline: 2px solid #f5a623; outline-offset: 3px; }
.pub-follow-text { margin: 0; font-size: .82rem; font-weight: 500; line-height: 1.8; color: rgba(255, 255, 255, .82); }

@media (max-width: 1000px) {
  .pub-follow { max-width: none; align-items: flex-start; text-align: start; }
  .pub-follow-title::after { inset-inline: auto; inset-inline-start: 0; width: 32px; }
  .pub-follow-links { justify-content: flex-start; }
}
</style>

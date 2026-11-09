<script setup lang="ts">
import PublicSocialFollow from '@/components/PublicSocialFollow.vue'
import { committees } from '@/data/unionCommittees'
import { Globe } from 'lucide-vue-next'

definePage({ meta: { layout: 'landing', public: true, unauthenticatedOnly: false } })
</script>

<template>
  <div dir="rtl" class="pub-page">

    <div class="page-hero">
      <div class="page-hero-shapes"><div class="ph-s ph-s1" /><div class="ph-s ph-s2" /></div>
      <div class="pub-cont page-hero-inner">
        <div class="page-breadcrumb">
          <RouterLink to="/landing">الرئيسية</RouterLink><span>/</span><span>اللجان</span>
        </div>
        <h1 class="page-hero-title">اللجان</h1>
        <p class="page-hero-desc">لجان الاتحاد الداخلية التي تعمل على تطوير قطاع المقاولات وخدمة أعضاء الهيئة العامة</p>
      </div>
      <div class="pub-follow-slot"><PublicSocialFollow /></div>
    </div>

    <section class="pub-section">
      <div class="pub-cont">
        <div class="pub-sec-header-c">
          <div class="pub-sec-label">اللجان</div>
          <h2 class="pub-sec-h center">اللجان الداخلية للاتحاد</h2>
        </div>
        <div class="committees-grid">
          <div v-for="c in committees" :key="c.name" class="committee-card" style="--cc:#1a237e;--cbg:#f5f7ff">
            <div class="committee-header">
              <h3 class="committee-name">{{ c.name }}</h3>
            </div>
            <p class="committee-desc">{{ c.desc }}</p>
            <p class="chair"><Globe :size="13" /> رئيس اللجنة: {{ c.chair }}</p>
          </div>
        </div>
      </div>
    </section>

  </div>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800;900&family=Tajawal:wght@400;500;700;800&display=swap');
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
.pub-page { font-family: 'Tajawal', 'Cairo', sans-serif; direction: rtl; color: #374151; background: #fff; }
.pub-cont { max-width: 1240px; margin: 0 auto; padding: 0 1.5rem; }
.pub-section { padding: 5rem 0; }
.bg-soft { background: #f5f7ff; }
.pub-sec-label { display: inline-block; background: #e8eaf6; color: #1a237e; border: 1px solid #c5cae9; border-radius: 50px; padding: .28rem 1.1rem; font-size: .77rem; font-weight: 700; letter-spacing: .05em; }
.pub-sec-h { font-family: 'Tajawal', 'Cairo', sans-serif; font-size: clamp(1.6rem,3.5vw,2.3rem); font-weight: 900; color: #0d1b3e; padding-bottom: .7rem; position: relative; }
.pub-sec-h::after { content: ''; position: absolute; bottom: 0; right: 0; width: 48px; height: 4px; background: linear-gradient(90deg,#f9a825,#e65100); border-radius: 2px; }
.pub-sec-h.center { text-align: center; }
.pub-sec-h.center::after { right: 50%; transform: translateX(50%); }
.pub-sec-header-c { text-align: center; margin-bottom: 3rem; }
.page-hero { background: linear-gradient(145deg, #0d1b4b 0%, #1a237e 60%, #283593 100%); margin-top: calc(-1 * var(--pub-nav-h, 114px)); padding: calc(var(--pub-nav-h, 114px) + 2.5rem) 0 3rem; position: relative; overflow: hidden; }
.page-hero-shapes { position: absolute; top: 0; right: 0; bottom: 0; left: 0; pointer-events: none; }
.ph-s { position: absolute; border-radius: 50%; }
.ph-s1 { width: 400px; height: 400px; background: radial-gradient(circle, rgba(249,168,37,.12), transparent 65%); top: -100px; left: 5%; }
.ph-s2 { width: 300px; height: 300px; background: radial-gradient(circle, rgba(255,255,255,.05), transparent 65%); bottom: -80px; right: 10%; }
.page-hero-inner { position: relative; z-index: 2; }
.page-breadcrumb { display: flex; align-items: center; gap: .5rem; font-size: .82rem; color: rgba(255,255,255,.6); margin-bottom: 1rem; }
.page-breadcrumb a { color: rgba(255,255,255,.6); text-decoration: none; }
.page-breadcrumb a:hover { color: #f9a825; }
.page-hero-title { font-family: 'Tajawal', 'Cairo', sans-serif; font-size: clamp(2rem,4vw,3rem); font-weight: 900; color: #fff; margin-bottom: .75rem; }
.page-hero-desc { font-size: 1rem; color: rgba(255,255,255,.75); line-height: 1.75; max-width: 600px; }

.committees-grid { display: grid; grid-template-columns: repeat(3,1fr); gap: 1.5rem; }
.committee-card { background: var(--cbg); border: 1.5px solid var(--cc); border-top: 5px solid var(--cc); border-radius: 16px; padding: 1.75rem; transition: transform .25s, box-shadow .25s; }
.committee-card:hover { transform: translateY(-5px); box-shadow: 0 10px 28px rgba(0,0,0,.1); }
.committee-header { display: flex; align-items: flex-start; justify-content: space-between; gap: .75rem; margin-bottom: .875rem; }
.committee-name { font-family: 'Tajawal', 'Cairo', sans-serif; font-size: .95rem; font-weight: 800; color: #0d1b3e; line-height: 1.4; }
.committee-members { display: flex; align-items: center; gap: .3rem; font-size: .76rem; font-weight: 700; color: var(--cc); background: rgba(255,255,255,.7); border: 1px solid var(--cc); border-radius: 50px; padding: .2rem .65rem; white-space: nowrap; flex-shrink: 0; }
.committee-desc { font-size: .85rem; color: #4b5563; line-height: 1.8; }

.chair { display: flex; align-items: center; gap: .4rem; margin-top: .75rem; font-size: .8rem; font-weight: 700; color: #1a237e; }
@media (max-width: 900px) { .committees-grid { grid-template-columns: repeat(2,1fr); } }
@media (max-width: 640px) { .committees-grid { grid-template-columns: 1fr; } }
</style>

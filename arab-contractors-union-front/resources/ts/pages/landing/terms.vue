<script setup lang="ts">
import { computed } from 'vue'
import { useQuery } from '@tanstack/vue-query'
import axios from 'axios'

definePage({ meta: { layout: 'landing', public: true, unauthenticatedOnly: false } })

const { data, isLoading } = useQuery({
  queryKey: ['public-terms'],
  queryFn: async () => {
    const res = await axios.get('/api/v1/terms')
    return res.data
  },
  staleTime: 5 * 60 * 1000,
})

const sections = computed(() => data.value ?? [])
</script>

<template>
  <div dir="rtl" class="pub-page">

    <div class="page-hero">
      <div class="page-hero-shapes"><div class="ph-s ph-s1" /></div>
      <div class="pub-cont page-hero-inner">
        <div class="page-breadcrumb">
          <RouterLink to="/landing">الرئيسية</RouterLink>
          <span>/</span>
          <span>الشروط والأحكام</span>
        </div>
        <h1 class="page-hero-title">الشروط والأحكام</h1>
        <p class="page-hero-desc">آخر تحديث: ١ يناير ٢٠٢٤</p>
      </div>
    </div>

    <section class="legal-section">
      <div class="pub-cont legal-layout">

        <!-- TOC — Dynamic from API -->
        <nav class="legal-toc">
          <p class="toc-title">المحتويات</p>
          <template v-if="isLoading">
            <div v-for="i in 5" :key="i" class="toc-skeleton" />
          </template>
          <template v-else>
            <a
              v-for="(sec, idx) in sections"
              :key="sec.id"
              :href="`#t${sec.id}`"
              class="toc-link"
            >
              {{ idx + 1 }}. {{ sec.title }}
            </a>
          </template>
        </nav>

        <!-- Content — Dynamic from API -->
        <div class="legal-content">

          <!-- Loading skeleton -->
          <template v-if="isLoading">
            <div v-for="i in 4" :key="i" class="legal-skeleton-sec">
              <div class="skel skel-title" />
              <div class="skel skel-line" />
              <div class="skel skel-line skel-line-short" />
            </div>
          </template>

          <!-- Sections from API -->
          <template v-else-if="sections.length">
            <section
              v-for="(sec, idx) in sections"
              :id="`t${sec.id}`"
              :key="sec.id"
              class="legal-sec"
            >
              <h2>{{ idx + 1 }}. {{ sec.title }}</h2>
              <div v-html="sec.body" />
            </section>
          </template>

          <!-- Fallback empty -->
          <div v-else class="legal-empty">
            <p>لا توجد بنود متاحة حالياً.</p>
          </div>

        </div>
      </div>
    </section>

  </div>
</template>

<style scoped>
@import url('https://fonts.cdnfonts.com/css/neo-sans-arabic');
@import url('https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800;900&family=Tajawal:wght@400;500;700;800&display=swap');
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
.pub-page { font-family: 'Neo Sans Arabic', 'Tajawal', 'Cairo', sans-serif; direction: rtl; color: #374151; background: #fff; }
.pub-cont { max-width: 1240px; margin: 0 auto; padding: 0 1.5rem; }
.page-hero { background: linear-gradient(145deg, #0d1b4b 0%, #1a237e 60%, #283593 100%); padding: 4rem 0 3rem; position: relative; overflow: hidden; }
.page-hero-shapes { position: absolute; inset: 0; pointer-events: none; }
.ph-s { position: absolute; border-radius: 50%; }
.ph-s1 { width: 400px; height: 400px; background: radial-gradient(circle, rgba(249,168,37,.12), transparent 65%); top: -100px; left: 5%; }
.page-hero-inner { position: relative; z-index: 2; }
.page-breadcrumb { display: flex; align-items: center; gap: .5rem; font-size: .82rem; color: rgba(255,255,255,.6); margin-bottom: 1rem; }
.page-breadcrumb a { color: rgba(255,255,255,.6); text-decoration: none; }
.page-hero-title { font-family: 'Neo Sans Arabic', 'Cairo', sans-serif; font-size: clamp(2rem,4vw,3rem); font-weight: 900; color: #fff; margin-bottom: .5rem; }
.page-hero-desc { font-size: .9rem; color: rgba(255,255,255,.6); }

.legal-section { padding: 5rem 0; }
.legal-layout { display: grid; grid-template-columns: 220px 1fr; gap: 4rem; align-items: start; }
.legal-toc { position: sticky; top: 120px; background: #f9fafb; border: 1.5px solid #e5e7eb; border-radius: 14px; padding: 1.5rem; }
.toc-title { font-family: 'Neo Sans Arabic', 'Cairo', sans-serif; font-size: .85rem; font-weight: 800; color: #0d1b3e; margin-bottom: 1rem; padding-bottom: .65rem; border-bottom: 2px solid #e5e7eb; }
.toc-link { display: block; color: #6b7280; font-size: .83rem; font-weight: 600; text-decoration: none; padding: .4rem 0; border-bottom: 1px solid #f3f4f6; transition: color .2s; }
.toc-link:hover { color: #1a237e; }
.toc-skeleton { height: 1rem; background: #e5e7eb; border-radius: 6px; margin-bottom: .6rem; animation: pulse 1.4s ease-in-out infinite; }

.legal-intro { background: #fff8e1; border: 1.5px solid #fdd835; border-radius: 12px; padding: 1.25rem 1.5rem; font-size: .92rem; color: #374151; line-height: 1.85; margin-bottom: 2.5rem; }
.legal-sec { margin-bottom: 2.5rem; }
.legal-sec h2 { font-family: 'Neo Sans Arabic', 'Cairo', sans-serif; font-size: 1.1rem; font-weight: 800; color: #0d1b3e; margin-bottom: 1rem; padding-bottom: .5rem; border-bottom: 2px solid #e5e7eb; }
.legal-sec p { font-size: .92rem; color: #4b5563; line-height: 1.9; margin-bottom: .875rem; }
.legal-sec ul { list-style: none; padding: 0; display: flex; flex-direction: column; gap: .5rem; margin-bottom: .875rem; }
.legal-sec ul li { font-size: .9rem; color: #4b5563; line-height: 1.75; padding-inline-start: 1.25rem; position: relative; }
.legal-sec ul li::before { content: '—'; position: absolute; right: 0; color: #1a237e; font-weight: 700; }

/* Skeleton for sections */
.legal-skeleton-sec { margin-bottom: 2.5rem; }
.skel { background: #e5e7eb; border-radius: 6px; animation: pulse 1.4s ease-in-out infinite; margin-bottom: .75rem; }
.skel-title { height: 1.3rem; width: 40%; }
.skel-line { height: .9rem; width: 100%; }
.skel-line-short { width: 70%; }

.legal-empty { text-align: center; padding: 3rem; color: #9ca3af; font-size: .95rem; }

@keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: .5; } }
@media (max-width: 900px) { .legal-layout { grid-template-columns: 1fr; } .legal-toc { position: static; } }
</style>

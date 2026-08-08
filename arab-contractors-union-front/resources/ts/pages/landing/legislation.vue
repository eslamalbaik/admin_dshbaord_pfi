<script setup lang="ts">
import { FileText, Download, ExternalLink, Calendar } from 'lucide-vue-next'

definePage({ meta: { layout: 'landing', public: true, unauthenticatedOnly: false } })

const categories = [
  { key:'laws',      label:'القوانين والتشريعات' },
  { key:'contracts', label:'عقود الفيديك FIDIC' },
  { key:'procurement', label:'تعليمات الشراء العام' },
  { key:'guides',    label:'الأدلة الإرشادية' },
  { key:'decisions', label:'القرارات واللوائح' },
]

const docs = [
  { id:1, cat:'laws',      title:'قانون المقاولات الفلسطيني',                    year:'٢٠١٨', type:'PDF', pages:45, color:'#1a237e', bg:'#e8eaf6' },
  { id:2, cat:'laws',      title:'قانون الشراء العام',                          year:'٢٠١٩', type:'PDF', pages:32, color:'#c62828', bg:'#fce4ec' },
  { id:3, cat:'laws',      title:'قانون التسوية والتحكيم',                       year:'٢٠١٧', type:'PDF', pages:28, color:'#2e7d32', bg:'#e8f5e9' },
  { id:4, cat:'contracts', title:'عقد الفيديك الأحمر - إنشاء المشاريع',         year:'٢٠١٧', type:'PDF', pages:160, color:'#e65100', bg:'#fff8e1' },
  { id:5, cat:'contracts', title:'عقد الفيديك الأصفر - التصميم والبناء',        year:'٢٠١٧', type:'PDF', pages:144, color:'#f9a825', bg:'#fff8e1' },
  { id:6, cat:'contracts', title:'عقد الفيديك الفضي - المحطات الإنشائية',       year:'٢٠١٧', type:'PDF', pages:128, color:'#607d8b', bg:'#eceff1' },
  { id:7, cat:'procurement', title:'تعليمات الشراء العام للأشغال الحكومية',     year:'٢٠٢٠', type:'PDF', pages:56, color:'#6a1b9a', bg:'#f3e5f5' },
  { id:8, cat:'procurement', title:'دليل إعداد وثائق العطاءات',                 year:'٢٠٢١', type:'PDF', pages:38, color:'#00695c', bg:'#e0f2f1' },
  { id:9, cat:'guides',   title:'الدليل الإرشادي لتصنيف المقاولين',             year:'٢٠٢٣', type:'PDF', pages:42, color:'#0277bd', bg:'#e3f2fd' },
  { id:10, cat:'guides',  title:'دليل السلامة المهنية في مواقع البناء',          year:'٢٠٢٢', type:'PDF', pages:64, color:'#558b2f', bg:'#f1f8e9' },
  { id:11, cat:'decisions', title:'لائحة تسجيل شركات المقاولات',               year:'٢٠٢١', type:'PDF', pages:24, color:'#ad1457', bg:'#fce4ec' },
  { id:12, cat:'decisions', title:'قرار مجلس الوزراء رقم ٢٠٢٢/٤٥ — رسوم التصنيف', year:'٢٠٢٢', type:'PDF', pages:8, color:'#e65100', bg:'#fff8e1' },
]

import { ref, computed } from 'vue'
const activecat = ref('laws')
const filteredDocs = computed(() => docs.filter(d => d.cat === activecat.value))
</script>

<template>
  <div dir="rtl" class="pub-page">
        <div class="page-hero">
      <div class="page-hero-shapes"><div class="ph-s ph-s1" /><div class="ph-s ph-s2" /></div>
      <div class="pub-cont page-hero-inner">
        <div class="page-breadcrumb">
          <RouterLink to="/landing">الرئيسية</RouterLink><span>/</span><span>التشريعات والأنظمة</span>
        </div>
        <h1 class="page-hero-title">التشريعات والأنظمة</h1>
        <p class="page-hero-desc">القوانين والأنظمة وعقود الفيديك والأدلة الإرشادية المتعلقة بقطاع المقاولات</p>
      </div>
    </div>

    <section class="pub-section">
      <div class="pub-cont">
        <div class="leg-layout">
          <!-- Sidebar -->
          <div class="leg-sidebar">
            <h3 class="sidebar-title">التصنيفات</h3>
            <button v-for="c in categories" :key="c.key" class="sidebar-btn" :class="{ active: activecat === c.key }" @click="activecat = c.key">
              <FileText :size="15" /> {{ c.label }}
            </button>
          </div>

          <!-- Docs List -->
          <div class="leg-content">
            <div class="docs-grid">
              <div v-for="d in filteredDocs" :key="d.id" class="doc-card">
                <div class="doc-icon" :style="`background:${d.bg};color:${d.color};border-color:${d.color}`">
                  <FileText :size="26" />
                </div>
                <div class="doc-body">
                  <h3 class="doc-title">{{ d.title }}</h3>
                  <div class="doc-meta">
                    <span><Calendar :size="12" /> {{ d.year }}</span>
                    <span>{{ d.pages }} صفحة</span>
                    <span class="doc-type">{{ d.type }}</span>
                  </div>
                </div>
                <div class="doc-actions">
                  <button class="doc-btn download" :style="`color:${d.color};border-color:${d.color}`">
                    <Download :size="14" /> تحميل
                  </button>
                  <button class="doc-btn view">
                    <ExternalLink :size="14" /> عرض
                  </button>
                </div>
              </div>
            </div>
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
.pub-page { font-family: 'Neo Sans Arabic', 'Tajawal', 'Neo Sans Arabic', 'Cairo', sans-serif; direction: rtl; color: #374151; background: #fff; }
.pub-cont { max-width: 1240px; margin: 0 auto; padding: 0 1.5rem; }
.pub-section { padding: 5rem 0; }
.page-hero { background: linear-gradient(145deg, #0d1b4b 0%, #1a237e 60%, #283593 100%); padding: 4rem 0 3rem; position: relative; overflow: hidden; }
.page-hero-shapes { position: absolute; inset: 0; pointer-events: none; }
.ph-s { position: absolute; border-radius: 50%; }
.ph-s1 { width: 400px; height: 400px; background: radial-gradient(circle, rgba(249,168,37,.12), transparent 65%); top: -100px; left: 5%; }
.ph-s2 { width: 300px; height: 300px; background: radial-gradient(circle, rgba(255,255,255,.05), transparent 65%); bottom: -80px; right: 10%; }
.page-hero-inner { position: relative; z-index: 2; }
.page-breadcrumb { display: flex; align-items: center; gap: .5rem; font-size: .82rem; color: rgba(255,255,255,.6); margin-bottom: 1rem; }
.page-breadcrumb a { color: rgba(255,255,255,.6); text-decoration: none; } .page-breadcrumb a:hover { color: #f9a825; }
.page-hero-title { font-family: 'Neo Sans Arabic', 'Cairo', sans-serif; font-size: clamp(2rem,4vw,3rem); font-weight: 900; color: #fff; margin-bottom: .75rem; }
.page-hero-desc { font-size: 1rem; color: rgba(255,255,255,.75); line-height: 1.75; max-width: 600px; }

.leg-layout { display: grid; grid-template-columns: 240px 1fr; gap: 2.5rem; align-items: start; }
.leg-sidebar { background: #f9fafb; border: 1.5px solid #e5e7eb; border-radius: 16px; padding: 1.5rem; position: sticky; top: 120px; }
.sidebar-title { font-family: 'Neo Sans Arabic', 'Cairo', sans-serif; font-size: .9rem; font-weight: 800; color: #0d1b3e; margin-bottom: 1rem; padding-bottom: .65rem; border-bottom: 2px solid #e5e7eb; }
.sidebar-btn { width: 100%; display: flex; align-items: center; gap: .65rem; padding: .65rem .875rem; border: none; border-radius: 10px; font-size: .85rem; font-weight: 600; cursor: pointer; font-family: 'Neo Sans Arabic', 'Tajawal', sans-serif; color: #6b7280; background: transparent; transition: all .2s; text-align: right; margin-bottom: .25rem; }
.sidebar-btn.active { background: #1a237e; color: #fff; }
.sidebar-btn:hover:not(.active) { background: #e8eaf6; color: #1a237e; }

.docs-grid { display: flex; flex-direction: column; gap: 1rem; }
.doc-card { display: flex; gap: 1.25rem; align-items: center; background: #fff; border: 1.5px solid #e5e7eb; border-radius: 14px; padding: 1.25rem; transition: all .25s; }
.doc-card:hover { box-shadow: 0 6px 20px rgba(0,0,0,.08); border-color: #c5cae9; transform: translateX(-4px); }
.doc-icon { width: 58px; height: 58px; border-radius: 14px; border: 1.5px solid; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.doc-body { flex: 1; min-width: 0; }
.doc-title { font-family: 'Neo Sans Arabic', 'Cairo', sans-serif; font-size: .93rem; font-weight: 800; color: #0d1b3e; margin-bottom: .45rem; }
.doc-meta { display: flex; gap: .875rem; flex-wrap: wrap; }
.doc-meta span { display: flex; align-items: center; gap: .3rem; font-size: .77rem; color: #9ca3af; font-weight: 500; }
.doc-type { background: #e8eaf6; color: #1a237e; border-radius: 50px; padding: .1rem .55rem; font-weight: 700 !important; }
.doc-actions { display: flex; gap: .5rem; flex-shrink: 0; }
.doc-btn { display: flex; align-items: center; gap: .3rem; border-radius: 8px; padding: .45rem .875rem; font-size: .8rem; font-weight: 700; cursor: pointer; font-family: 'Neo Sans Arabic', 'Tajawal', sans-serif; transition: all .2s; white-space: nowrap; }
.doc-btn.download { background: transparent; border: 1.5px solid; }
.doc-btn.download:hover { color: #fff !important; }
.doc-btn.view { background: #f3f4f6; color: #6b7280; border: 1.5px solid #e5e7eb; }
.doc-btn.view:hover { background: #e5e7eb; }

@media (max-width: 900px) { .leg-layout { grid-template-columns: 1fr; } .leg-sidebar { position: static; } }
@media (max-width: 600px) { .doc-card { flex-wrap: wrap; } .doc-actions { width: 100%; } }
</style>

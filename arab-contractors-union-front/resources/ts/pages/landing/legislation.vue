<script setup lang="ts">
import PublicSocialFollow from '@/components/PublicSocialFollow.vue'
import { ref, computed, watch } from 'vue'
import { FileText, Download, ExternalLink, Calendar } from 'lucide-vue-next'
import { usePublicLegalFiles, fileExt } from '@/composables/usePublicLegalFiles'

definePage({ meta: { layout: 'landing', public: true, unauthenticatedOnly: false } })

// الملفات الحقيقية من "المكتبة القانونية" بلوحة التحكم، بدل القائمة الثابتة القديمة
const { files, categories, isLoading, isError } = usePublicLegalFiles()

const palette = [
  { color: '#1a237e', bg: '#e8eaf6' }, { color: '#c62828', bg: '#fce4ec' }, { color: '#2e7d32', bg: '#e8f5e9' },
  { color: '#e65100', bg: '#fff8e1' }, { color: '#6a1b9a', bg: '#f3e5f5' }, { color: '#00695c', bg: '#e0f2f1' },
  { color: '#0277bd', bg: '#e3f2fd' }, { color: '#ad1457', bg: '#fce4ec' },
]

const activecat = ref('')
watch(categories, cats => {
  if (!cats.some(c => c.value === activecat.value))
    activecat.value = cats[0]?.value ?? ''
}, { immediate: true })

const filteredDocs = computed(() => files.value
  .filter(f => f.category === activecat.value)
  .map((f, i) => ({ ...f, ext: fileExt(f), year: new Date(f.created_at).getFullYear(), ...palette[i % palette.length] })))
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
      <div class="pub-follow-slot"><PublicSocialFollow /></div>
    </div>

    <section class="pub-section">
      <div class="pub-cont">
        <div class="leg-layout">
          <!-- Sidebar -->
          <div class="leg-sidebar">
            <h3 class="sidebar-title">التصنيفات</h3>
            <button v-for="c in categories" :key="c.value" class="sidebar-btn" :class="{ active: activecat === c.value }" @click="activecat = c.value">
              <FileText :size="15" /> {{ c.label }} <span class="sidebar-count">{{ c.count }}</span>
            </button>
          </div>

          <!-- Docs List -->
          <div class="leg-content">
            <p v-if="isLoading" class="leg-state">جاري تحميل الملفات...</p>
            <p v-else-if="isError" class="leg-state">تعذّر تحميل الملفات، حاول مرة أخرى لاحقاً.</p>
            <p v-else-if="!filteredDocs.length" class="leg-state">لا توجد ملفات منشورة حالياً.</p>
            <div class="docs-grid">
              <div v-for="d in filteredDocs" :key="d.id" class="doc-card">
                <div class="doc-icon" :style="`background:${d.bg};color:${d.color};border-color:${d.color}`">
                  <FileText :size="26" />
                </div>
                <div class="doc-body">
                  <h3 class="doc-title">{{ d.title }}</h3>
                  <p v-if="d.description" class="doc-desc">{{ d.description }}</p>
                  <div class="doc-meta">
                    <span><Calendar :size="12" /> {{ d.year }}</span>
                    <span v-if="d.formatted_size">{{ d.formatted_size }}</span>
                    <span class="doc-type">{{ d.ext }}</span>
                  </div>
                </div>
                <div class="doc-actions">
                  <a class="doc-btn download" :href="d.url" download target="_blank" rel="noopener noreferrer" :style="`color:${d.color};border-color:${d.color}`">
                    <Download :size="14" /> تحميل
                  </a>
                  <a class="doc-btn view" :href="d.url" target="_blank" rel="noopener noreferrer">
                    <ExternalLink :size="14" /> عرض
                  </a>
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
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
.pub-page { font-family: 'Tajawal', 'Cairo', sans-serif; direction: rtl; color: #374151; background: #fff; }
.pub-cont { max-width: 1240px; margin: 0 auto; padding: 0 1.5rem; }
.pub-section { padding: 5rem 0; }
.page-hero { background: linear-gradient(145deg, #0d1b4b 0%, #1a237e 60%, #283593 100%); margin-top: calc(-1 * var(--pub-nav-h, 114px)); padding: calc(var(--pub-nav-h, 114px) + 2.5rem) 0 3rem; position: relative; overflow: hidden; }
.page-hero-shapes { position: absolute; top: 0; right: 0; bottom: 0; left: 0; pointer-events: none; }
.ph-s { position: absolute; border-radius: 50%; }
.ph-s1 { width: 400px; height: 400px; background: radial-gradient(circle, rgba(249,168,37,.12), transparent 65%); top: -100px; left: 5%; }
.ph-s2 { width: 300px; height: 300px; background: radial-gradient(circle, rgba(255,255,255,.05), transparent 65%); bottom: -80px; right: 10%; }
.page-hero-inner { position: relative; z-index: 2; }
.page-breadcrumb { display: flex; align-items: center; gap: .5rem; font-size: .82rem; color: rgba(255,255,255,.6); margin-bottom: 1rem; }
.page-breadcrumb a { color: rgba(255,255,255,.6); text-decoration: none; } .page-breadcrumb a:hover { color: #f9a825; }
.page-hero-title { font-family: 'Tajawal', 'Cairo', sans-serif; font-size: clamp(2rem,4vw,3rem); font-weight: 900; color: #fff; margin-bottom: .75rem; }
.page-hero-desc { font-size: 1rem; color: rgba(255,255,255,.75); line-height: 1.75; max-width: 600px; }

.leg-layout { display: grid; grid-template-columns: 240px 1fr; gap: 2.5rem; align-items: start; }
.leg-sidebar { background: #f9fafb; border: 1.5px solid #e5e7eb; border-radius: 16px; padding: 1.5rem; position: sticky; top: 120px; }
.sidebar-title { font-family: 'Tajawal', 'Cairo', sans-serif; font-size: .9rem; font-weight: 800; color: #0d1b3e; margin-bottom: 1rem; padding-bottom: .65rem; border-bottom: 2px solid #e5e7eb; }
.sidebar-btn { width: 100%; display: flex; align-items: center; gap: .65rem; padding: .65rem .875rem; border: none; border-radius: 10px; font-size: .85rem; font-weight: 600; cursor: pointer; font-family: 'Tajawal', sans-serif; color: #6b7280; background: transparent; transition: all .2s; text-align: right; margin-bottom: .25rem; }
.sidebar-btn.active { background: #1a237e; color: #fff; }
.sidebar-btn:hover:not(.active) { background: #e8eaf6; color: #1a237e; }

.docs-grid { display: flex; flex-direction: column; gap: 1rem; }
.doc-card { display: flex; gap: 1.25rem; align-items: center; background: #fff; border: 1.5px solid #e5e7eb; border-radius: 14px; padding: 1.25rem; transition: all .25s; }
.doc-card:hover { box-shadow: 0 6px 20px rgba(0,0,0,.08); border-color: #c5cae9; transform: translateX(-4px); }
.doc-icon { width: 58px; height: 58px; border-radius: 14px; border: 1.5px solid; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.doc-body { flex: 1; min-width: 0; }
.doc-title { font-family: 'Tajawal', 'Cairo', sans-serif; font-size: .93rem; font-weight: 800; color: #0d1b3e; margin-bottom: .45rem; }
.doc-meta { display: flex; gap: .875rem; flex-wrap: wrap; }
.doc-meta span { display: flex; align-items: center; gap: .3rem; font-size: .77rem; color: #9ca3af; font-weight: 500; }
.doc-type { background: #e8eaf6; color: #1a237e; border-radius: 50px; padding: .1rem .55rem; font-weight: 700 !important; }
.doc-actions { display: flex; gap: .5rem; flex-shrink: 0; }
.sidebar-count { margin-inline-start: auto; font-size: .72rem; opacity: .75; }
.leg-state { color: #9ca3af; font-size: .95rem; padding: 2rem 0; text-align: center; }
.doc-desc { font-size: .8rem; color: #6b7280; line-height: 1.6; margin-bottom: .45rem; }
.doc-btn { display: flex; align-items: center; gap: .3rem; border-radius: 8px; text-decoration: none; padding: .45rem .875rem; font-size: .8rem; font-weight: 700; cursor: pointer; font-family: 'Tajawal', sans-serif; transition: all .2s; white-space: nowrap; }
.doc-btn.download { background: transparent; border: 1.5px solid; }
.doc-btn.download:hover { color: #fff !important; }
.doc-btn.view { background: #f3f4f6; color: #6b7280; border: 1.5px solid #e5e7eb; }
.doc-btn.view:hover { background: #e5e7eb; }

@media (max-width: 900px) { .leg-layout { grid-template-columns: 1fr; } .leg-sidebar { position: static; } }
@media (max-width: 600px) { .doc-card { flex-wrap: wrap; } .doc-actions { width: 100%; } }
</style>

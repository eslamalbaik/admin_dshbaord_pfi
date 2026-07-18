<script setup lang="ts">
import { ref, computed } from 'vue'
import { Search, Download, FileText, Clock, Eye } from 'lucide-vue-next'

definePage({ meta: { layout: 'landing', public: true, unauthenticatedOnly: false } })

const searchQ = ref('')
const activeType = ref('all')

const types = [
  { key:'all', label:'الكل' },
  { key:'form', label:'النماذج والاستمارات' },
  { key:'guide', label:'الأدلة والكتيبات' },
  { key:'report', label:'التقارير والدراسات' },
  { key:'circular', label:'التعاميم والقرارات' },
  { key:'template', label:'عقود ونماذج قانونية' },
]

const files = [
  { id:1,  type:'form',     title:'استمارة طلب تسجيل العضوية',          size:'٢٤٠ ك.ب', date:'يناير ٢٠٢٤',  downloads:1245, ext:'PDF', color:'#1a237e' },
  { id:2,  type:'form',     title:'نموذج طلب تجديد العضوية',            size:'١٨٠ ك.ب', date:'يناير ٢٠٢٤',  downloads:987,  ext:'PDF', color:'#c62828' },
  { id:3,  type:'form',     title:'استمارة طلب التصنيف ورفع الدرجة',    size:'٣٢٠ ك.ب', date:'مارس ٢٠٢٤',   downloads:654,  ext:'PDF', color:'#2e7d32' },
  { id:4,  type:'form',     title:'نموذج شكوى وتقديم نزاع',             size:'١٥٠ ك.ب', date:'فبراير ٢٠٢٤', downloads:348,  ext:'PDF', color:'#e65100' },
  { id:5,  type:'guide',    title:'الدليل الإرشادي للمقاولين الجدد',    size:'١.٢ م.ب', date:'يناير ٢٠٢٤',  downloads:2100, ext:'PDF', color:'#6a1b9a' },
  { id:6,  type:'guide',    title:'دليل إدارة مشاريع البنية التحتية',   size:'٢.٤ م.ب', date:'أبريل ٢٠٢٣',  downloads:1567, ext:'PDF', color:'#00695c' },
  { id:7,  type:'guide',    title:'دليل السلامة المهنية في البناء',      size:'١.٨ م.ب', date:'يونيو ٢٠٢٣',  downloads:3210, ext:'PDF', color:'#0277bd' },
  { id:8,  type:'report',   title:'تقرير قطاع المقاولات السنوي ٢٠٢٣',  size:'٤.٥ م.ب', date:'ديسمبر ٢٠٢٣', downloads:876,  ext:'PDF', color:'#558b2f' },
  { id:9,  type:'report',   title:'دراسة سوق العمل في الإنشاءات',       size:'٣.١ م.ب', date:'سبتمبر ٢٠٢٣', downloads:543,  ext:'PDF', color:'#f57f17' },
  { id:10, type:'circular', title:'تعميم رسوم التصنيف المحدّثة ٢٠٢٤',  size:'٩٠ ك.ب',  date:'يناير ٢٠٢٤',  downloads:2145, ext:'PDF', color:'#ad1457' },
  { id:11, type:'circular', title:'قرار مجلس الإدارة رقم ٢٠٢٤/١ — الانتساب', size:'١٢٠ ك.ب', date:'فبراير ٢٠٢٤', downloads:1345, ext:'PDF', color:'#1a237e' },
  { id:12, type:'template', title:'نموذج عقد مقاولة باطن معياري',       size:'٤٥٠ ك.ب', date:'مارس ٢٠٢٤',   downloads:2345, ext:'DOCX', color:'#c62828' },
  { id:13, type:'template', title:'نموذج خطاب ضمان أداء',               size:'١٨٠ ك.ب', date:'يناير ٢٠٢٤',  downloads:1123, ext:'DOCX', color:'#2e7d32' },
]

const filteredFiles = computed(() => {
  let list = activeType.value === 'all' ? files : files.filter(f => f.type === activeType.value)
  if (searchQ.value) list = list.filter(f => f.title.includes(searchQ.value))
  return list
})
</script>

<template>
  <div dir="rtl" class="pub-page">
        <div class="page-hero">
      <div class="page-hero-shapes"><div class="ph-s ph-s1" /><div class="ph-s ph-s2" /></div>
      <div class="pub-cont page-hero-inner">
        <div class="page-breadcrumb"><RouterLink to="/landing">الرئيسية</RouterLink><span>/</span><span>مكتبة الملفات</span></div>
        <h1 class="page-hero-title">مكتبة الملفات والنماذج</h1>
        <p class="page-hero-desc">مكتبة رقمية شاملة تضم النماذج والاستمارات والأدلة والتقارير والتعاميم</p>
      </div>
    </div>

    <section class="pub-section">
      <div class="pub-cont">
        <div class="lib-toolbar">
          <div class="search-wrap">
            <Search :size="16" class="search-ico" />
            <input v-model="searchQ" type="text" placeholder="ابحث في المكتبة..." class="search-input" />
          </div>
          <div class="type-tabs">
            <button v-for="t in types" :key="t.key" class="type-tab" :class="{ active: activeType === t.key }" @click="activeType = t.key">{{ t.label }}</button>
          </div>
        </div>
        <p class="results-count">{{ filteredFiles.length }} ملف</p>

        <div class="files-grid">
          <div v-for="f in filteredFiles" :key="f.id" class="file-card">
            <div class="file-icon" :style="`background:${f.color}15;color:${f.color};border-color:${f.color}30`">
              <FileText :size="28" />
              <span class="file-ext">{{ f.ext }}</span>
            </div>
            <div class="file-body">
              <h3 class="file-title">{{ f.title }}</h3>
              <div class="file-meta">
                <span><Clock :size="11" /> {{ f.date }}</span>
                <span>{{ f.size }}</span>
                <span><Download :size="11" /> {{ f.downloads.toLocaleString() }}</span>
              </div>
            </div>
            <div class="file-actions">
              <button class="file-btn-view"><Eye :size="14" /></button>
              <button class="file-btn-dl" :style="`background:${f.color}`"><Download :size="14" /> تحميل</button>
            </div>
          </div>
        </div>

        <div v-if="!filteredFiles.length" class="empty-state">
          <FileText :size="48" style="color:#c5cae9" />
          <p>لا توجد ملفات مطابقة</p>
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

.lib-toolbar { display: flex; flex-direction: column; gap: 1rem; margin-bottom: 2rem; }
.search-wrap { position: relative; }
.search-ico { position: absolute; top: 50%; right: 1rem; transform: translateY(-50%); color: #9ca3af; }
.search-input { width: 100%; border: 1.5px solid #e5e7eb; border-radius: 10px; padding: .75rem 2.75rem .75rem 1rem; font-size: .9rem; font-family: 'Neo Sans Arabic', 'Tajawal', sans-serif; color: #374151; background: #fff; outline: none; transition: border-color .2s; }
.search-input:focus { border-color: #1a237e; }
.type-tabs { display: flex; gap: .5rem; flex-wrap: wrap; }
.type-tab { padding: .5rem 1.1rem; border: 1.5px solid #e5e7eb; border-radius: 50px; font-size: .82rem; font-weight: 600; cursor: pointer; font-family: 'Neo Sans Arabic', 'Tajawal', sans-serif; color: #6b7280; background: #fff; transition: all .2s; }
.type-tab.active { background: #1a237e; color: #fff; border-color: #1a237e; }
.type-tab:hover:not(.active) { border-color: #1a237e; color: #1a237e; }
.results-count { font-size: .85rem; color: #9ca3af; margin-bottom: 1.25rem; }

.files-grid { display: flex; flex-direction: column; gap: 1rem; }
.file-card { display: flex; gap: 1.25rem; align-items: center; background: #fff; border: 1.5px solid #e5e7eb; border-radius: 14px; padding: 1.25rem; transition: all .25s; }
.file-card:hover { box-shadow: 0 6px 20px rgba(0,0,0,.08); border-color: #c5cae9; transform: translateX(-4px); }
.file-icon { width: 64px; height: 64px; border-radius: 14px; border: 1.5px solid; display: flex; flex-direction: column; align-items: center; justify-content: center; flex-shrink: 0; gap: .15rem; }
.file-ext { font-size: .62rem; font-weight: 800; letter-spacing: .03em; }
.file-body { flex: 1; min-width: 0; }
.file-title { font-family: 'Neo Sans Arabic', 'Cairo', sans-serif; font-size: .93rem; font-weight: 800; color: #0d1b3e; margin-bottom: .45rem; }
.file-meta { display: flex; gap: .875rem; flex-wrap: wrap; }
.file-meta span { display: flex; align-items: center; gap: .3rem; font-size: .77rem; color: #9ca3af; }
.file-actions { display: flex; gap: .5rem; flex-shrink: 0; }
.file-btn-view { width: 36px; height: 36px; border-radius: 9px; background: #f3f4f6; color: #6b7280; border: 1.5px solid #e5e7eb; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all .2s; }
.file-btn-view:hover { background: #e8eaf6; color: #1a237e; }
.file-btn-dl { display: flex; align-items: center; gap: .4rem; border: none; border-radius: 9px; padding: .5rem 1rem; font-size: .82rem; font-weight: 700; color: #fff; cursor: pointer; font-family: 'Neo Sans Arabic', 'Tajawal', sans-serif; transition: all .2s; white-space: nowrap; }
.file-btn-dl:hover { filter: brightness(1.1); transform: translateY(-1px); }
.empty-state { text-align: center; padding: 4rem 2rem; color: #9ca3af; }
.empty-state p { font-size: 1rem; margin-top: 1rem; }

@media (max-width: 640px) { .file-card { flex-wrap: wrap; } .file-actions { width: 100%; } .file-btn-dl { flex: 1; justify-content: center; } }
</style>

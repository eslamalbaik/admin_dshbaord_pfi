<script setup lang="ts">
import { ref, computed } from 'vue'
import { Building2, Calendar, MapPin, Eye, Filter } from 'lucide-vue-next'

definePage({ meta: { layout: 'landing', public: true, unauthenticatedOnly: false } })

const activeTab = ref('all')
const tabs = [
  { key: 'all',        label: 'الكل' },
  { key: 'project',    label: 'المشاريع الوطنية' },
  { key: 'exhibition', label: 'المعارض' },
  { key: 'conference', label: 'المؤتمرات' },
  { key: 'initiative', label: 'المبادرات' },
]

const items = [
  { id:1,  type:'project',    title:'مشروع تطوير شبكة الطرق الرئيسية في رام الله',          year:'٢٠٢٤', location:'رام الله',  desc:'مشروع تطوير وتأهيل ٤٠ كم من الطرق الرئيسية وإنشاء ٦ تقاطعات جديدة بالتعاون مع البلدية.', color:'#1a237e',bg:'#e8eaf6' },
  { id:2,  type:'project',    title:'مشروع إعادة تأهيل شبكة المياه في نابلس',                year:'٢٠٢٤', location:'نابلس',    desc:'تأهيل ٨٠ كم من شبكات المياه القديمة وإنشاء محطتي ضخ حديثتين لتحسين الخدمة.', color:'#0277bd',bg:'#e3f2fd' },
  { id:3,  type:'exhibition', title:'معرض فلسطين للبناء والإنشاء ٢٠٢٤',                     year:'٢٠٢٤', location:'رام الله',  desc:'أكبر معرض للبناء والإنشاء في فلسطين، شارك فيه أكثر من ١٥٠ شركة من ١٠ دول.', color:'#c62828',bg:'#fce4ec' },
  { id:4,  type:'conference', title:'مؤتمر قطاع المقاولات الفلسطيني السنوي',                 year:'٢٠٢٤', location:'بيت لحم', desc:'المؤتمر السنوي لمناقشة تحديات القطاع ووضع استراتيجية التطوير للسنوات القادمة.', color:'#2e7d32',bg:'#e8f5e9' },
  { id:5,  type:'project',    title:'إنشاء مجمع صناعي متكامل في أريحا',                     year:'٢٠٢٣', location:'أريحا',   desc:'إنشاء مجمع صناعي على مساحة ٥٠ دونم لاستيعاب المشاريع الصناعية الصغيرة والمتوسطة.', color:'#e65100',bg:'#fff8e1' },
  { id:6,  type:'initiative', title:'مبادرة بناء ١٠٠٠ وحدة سكنية ميسّرة',                  year:'٢٠٢٣', location:'متعدد',   desc:'مبادرة وطنية بالشراكة مع وزارة الإسكان لتوفير وحدات سكنية ميسّرة للشباب الفلسطيني.', color:'#6a1b9a',bg:'#f3e5f5' },
  { id:7,  type:'exhibition', title:'معرض مواد البناء والتقنيات الحديثة',                    year:'٢٠٢٣', location:'نابلس',   desc:'معرض متخصص في أحدث مواد وتقنيات البناء المستدام والصديق للبيئة.', color:'#00695c',bg:'#e0f2f1' },
  { id:8,  type:'project',    title:'مشروع بناء وتأهيل المدارس الحكومية',                   year:'٢٠٢٣', location:'متعدد',   desc:'مشروع لتأهيل وبناء ٣٠ مدرسة حكومية بتمويل من الاتحاد الأوروبي ووكالة الأونروا.', color:'#558b2f',bg:'#f1f8e9' },
  { id:9,  type:'conference', title:'ملتقى المقاولين الفلسطينيين والعرب',                    year:'٢٠٢٢', location:'رام الله', desc:'ملتقى لتبادل الخبرات وبناء الشراكات بين المقاولين الفلسطينيين ونظرائهم العرب.', color:'#f57f17',bg:'#fff8e1' },
]

const filtered = computed(() => activeTab.value === 'all' ? items : items.filter(i => i.type === activeTab.value))
</script>

<template>
  <div dir="rtl" class="pub-page">
    
    <div class="page-hero">
      <div class="page-hero-shapes"><div class="ph-s ph-s1" /><div class="ph-s ph-s2" /></div>
      <div class="pub-cont page-hero-inner">
        <div class="page-breadcrumb">
          <RouterLink to="/landing">الرئيسية</RouterLink><span>/</span><span>المشاريع والمعارض</span>
        </div>
        <h1 class="page-hero-title">المشاريع والمعارض</h1>
        <p class="page-hero-desc">أبرز المشاريع الوطنية والمعارض والمؤتمرات والمبادرات التي يشارك فيها الاتحاد</p>
      </div>
    </div>

    <section class="pub-section">
      <div class="pub-cont">
        <!-- Tabs -->
        <div class="tabs-bar">
          <button v-for="t in tabs" :key="t.key" class="tab-btn" :class="{ active: activeTab === t.key }" @click="activeTab = t.key">
            {{ t.label }}
          </button>
        </div>

        <div class="projects-grid">
          <div v-for="item in filtered" :key="item.id" class="project-card">
            <div class="project-thumb" :style="`background:${item.bg};border-color:${item.color}`">
              <Building2 :size="48" :style="`color:${item.color}`" />
            </div>
            <div class="project-body">
              <div class="project-meta">
                <span class="project-year"><Calendar :size="12" /> {{ item.year }}</span>
                <span class="project-loc"><MapPin :size="12" /> {{ item.location }}</span>
              </div>
              <h3 class="project-title">{{ item.title }}</h3>
              <p class="project-desc">{{ item.desc }}</p>
              <button class="project-more" :style="`color:${item.color}`">
                <Eye :size="14" /> عرض التفاصيل
              </button>
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

.tabs-bar { display: flex; gap: .5rem; flex-wrap: wrap; margin-bottom: 2.5rem; background: #f5f7ff; border-radius: 14px; padding: .5rem; }
.tab-btn { padding: .55rem 1.25rem; border: none; border-radius: 10px; font-size: .85rem; font-weight: 700; cursor: pointer; font-family: 'Neo Sans Arabic', 'Tajawal', sans-serif; color: #6b7280; background: transparent; transition: all .2s; }
.tab-btn.active { background: #1a237e; color: #fff; box-shadow: 0 4px 12px rgba(26,35,126,.2); }
.tab-btn:hover:not(.active) { background: #e8eaf6; color: #1a237e; }

.projects-grid { display: grid; grid-template-columns: repeat(3,1fr); gap: 1.75rem; }
.project-card { border: 1.5px solid #e5e7eb; border-radius: 18px; overflow: hidden; transition: all .3s; background: #fff; display: flex; flex-direction: column; }
.project-card:hover { box-shadow: 0 12px 36px rgba(0,0,0,.1); transform: translateY(-6px); }
.project-thumb { height: 180px; border-bottom: 1.5px solid; display: flex; align-items: center; justify-content: center; }
.project-body { padding: 1.5rem; flex: 1; display: flex; flex-direction: column; gap: .65rem; }
.project-meta { display: flex; gap: .875rem; }
.project-year, .project-loc { display: flex; align-items: center; gap: .3rem; font-size: .78rem; color: #9ca3af; font-weight: 600; }
.project-title { font-family: 'Neo Sans Arabic', 'Cairo', sans-serif; font-size: .95rem; font-weight: 800; color: #0d1b3e; line-height: 1.5; }
.project-desc { font-size: .85rem; color: #4b5563; line-height: 1.8; flex: 1; }
.project-more { display: flex; align-items: center; gap: .4rem; background: none; border: none; font-size: .85rem; font-weight: 700; cursor: pointer; font-family: 'Neo Sans Arabic', 'Tajawal', sans-serif; margin-top: auto; padding: 0; transition: opacity .2s; }
.project-more:hover { opacity: .75; }

@media (max-width: 900px) { .projects-grid { grid-template-columns: repeat(2,1fr); } }
@media (max-width: 560px) { .projects-grid { grid-template-columns: 1fr; } }
</style>

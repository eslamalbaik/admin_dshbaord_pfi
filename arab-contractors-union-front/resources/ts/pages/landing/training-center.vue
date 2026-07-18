<script setup lang="ts">
import { GraduationCap, Clock, Users, Calendar, ChevronLeft, ShieldCheck, ChartBar, FileText, BookOpen } from 'lucide-vue-next'

definePage({ meta: { layout: 'landing', public: true, unauthenticatedOnly: false } })

const courses = [
  { icon: ShieldCheck, color:'#c62828', bg:'#fce4ec', title:'السلامة والصحة المهنية في مواقع البناء', duration:'٣ أيام', level:'مبتدئ', seats:25, date:'١٥ يوليو ٢٠٢٤', certified:true, desc:'دورة شاملة في معايير السلامة المهنية وفق المعايير الدولية OSHA لجميع العاملين في مواقع البناء.' },
  { icon: ChartBar,    color:'#1a237e', bg:'#e8eaf6', title:'إدارة المشاريع الإنشائية PMP',          duration:'٥ أيام', level:'متوسط', seats:20, date:'٢٢ يوليو ٢٠٢٤', certified:true, desc:'منهج متكامل في إدارة مشاريع الإنشاء وفق معايير PMI مع التطبيق العملي على دراسات الحالة.' },
  { icon: FileText,    color:'#e65100', bg:'#fff8e1', title:'عقود الفيديك FIDIC المحدثة',             duration:'٤ أيام', level:'متقدم', seats:15, date:'٥ أغسطس ٢٠٢٤', certified:true, desc:'دراسة وتحليل عقود الفيديك المحدثة ٢٠١٧ وكيفية التعامل معها في المشاريع الإنشائية الدولية.' },
  { icon: ChartBar,    color:'#2e7d32', bg:'#e8f5e9', title:'تسعير المشاريع الإنشائية',               duration:'٣ أيام', level:'متوسط', seats:20, date:'١٢ أغسطس ٢٠٢٤', certified:false, desc:'أسس وأساليب تسعير الأعمال الإنشائية وإعداد العروض التنافسية وتحليل الكميات والتكاليف.' },
  { icon: ShieldCheck, color:'#6a1b9a', bg:'#f3e5f5', title:'إدارة المخاطر الإنشائية',               duration:'٢ أيام', level:'متقدم', seats:18, date:'١٩ أغسطس ٢٠٢٤', certified:false, desc:'أدوات ومنهجيات تحديد وتقييم وإدارة المخاطر في مشاريع البناء والبنية التحتية.' },
  { icon: BookOpen,    color:'#00695c', bg:'#e0f2f1', title:'قراءة المخططات الهندسية والكميات',       duration:'٣ أيام', level:'مبتدئ', seats:30, date:'٢ سبتمبر ٢٠٢٤', certified:false, desc:'أسس قراءة المخططات الهندسية وحساب الكميات وإعداد جداول الكميات للمشاريع الإنشائية.' },
  { icon: ChartBar,    color:'#0277bd', bg:'#e3f2fd', title:'إدارة سلسلة التوريد في الإنشاءات',      duration:'٢ أيام', level:'متوسط', seats:22, date:'٩ سبتمبر ٢٠٢٤', certified:false, desc:'تخطيط وإدارة سلسلة توريد مواد البناء وتحسين الكفاءة وخفض التكاليف اللوجستية.' },
  { icon: GraduationCap, color:'#558b2f', bg:'#f1f8e9', title:'القيادة وإدارة فرق العمل الإنشائية', duration:'٢ أيام', level:'متقدم', seats:16, date:'١٦ سبتمبر ٢٠٢٤', certified:false, desc:'مهارات القيادة وإدارة الفرق متعددة التخصصات في بيئة العمل الإنشائية.' },
]

const levelColor: Record<string,string> = { مبتدئ:'#2e7d32', متوسط:'#e65100', متقدم:'#c62828' }
const levelBg: Record<string,string> = { مبتدئ:'#e8f5e9', متوسط:'#fff8e1', متقدم:'#fce4ec' }
</script>

<template>
  <div dir="rtl" class="pub-page">
    
    <div class="page-hero">
      <div class="page-hero-shapes"><div class="ph-s ph-s1" /><div class="ph-s ph-s2" /></div>
      <div class="pub-cont page-hero-inner">
        <div class="page-breadcrumb">
          <RouterLink to="/landing">الرئيسية</RouterLink><span>/</span><span>التدريب وبناء القدرات</span>
        </div>
        <h1 class="page-hero-title">التدريب وبناء القدرات</h1>
        <p class="page-hero-desc">برامج تدريبية متخصصة لرفع كفاءة العاملين في قطاع المقاولات وفق المعايير الدولية</p>
        <div class="hero-stats">
          <div class="hs-item"><span class="hs-num">+١٥٠</span><span class="hs-lbl">دورة سنوياً</span></div>
          <div class="hs-item"><span class="hs-num">+٣٠٠٠</span><span class="hs-lbl">متدرب سنوياً</span></div>
          <div class="hs-item"><span class="hs-num">٤٠+</span><span class="hs-lbl">مدرب معتمد</span></div>
        </div>
      </div>
    </div>

    <!-- Courses -->
    <section class="pub-section">
      <div class="pub-cont">
        <div class="sec-header-split">
          <div>
            <div class="pub-sec-label">الدورات المتاحة</div>
            <h2 class="pub-sec-h">الدورات التدريبية القادمة</h2>
          </div>
        </div>
        <div class="courses-grid">
          <div v-for="c in courses" :key="c.title" class="course-card">
            <div class="course-header" :style="`background:${c.bg};border-color:${c.color}`">
              <div class="course-icon" :style="`background:${c.color}20;color:${c.color};border-color:${c.color}`">
                <component :is="c.icon" :size="24" />
              </div>
              <div v-if="c.certified" class="course-certified">معتمدة</div>
            </div>
            <div class="course-body">
              <div class="course-level" :style="`background:${levelBg[c.level]};color:${levelColor[c.level]}`">{{ c.level }}</div>
              <h3 class="course-title">{{ c.title }}</h3>
              <p class="course-desc">{{ c.desc }}</p>
              <div class="course-meta">
                <span><Clock :size="13" /> {{ c.duration }}</span>
                <span><Users :size="13" /> {{ c.seats }} مقعداً</span>
                <span><Calendar :size="13" /> {{ c.date }}</span>
              </div>
              <button class="course-register" :style="`background:${c.color}`">
                سجّل الآن <ChevronLeft :size="14" />
              </button>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Registration CTA -->
    <section class="cta-section">
      <div class="pub-cont cta-inner">
        <GraduationCap :size="52" style="color:#f9a825;margin-bottom:1rem" />
        <h2 class="cta-title">هل تريد دورة تدريبية مخصصة لشركتك؟</h2>
        <p class="cta-sub">يمكننا تصميم برامج تدريبية مخصصة لفريق عملك وفق احتياجاتك المحددة</p>
        <RouterLink to="/contact-us" class="cta-btn">تواصل معنا</RouterLink>
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
.pub-sec-label { display: inline-block; background: #e8eaf6; color: #1a237e; border: 1px solid #c5cae9; border-radius: 50px; padding: .28rem 1.1rem; font-size: .77rem; font-weight: 700; letter-spacing: .05em; margin-bottom: .65rem; }
.pub-sec-h { font-family: 'Neo Sans Arabic', 'Cairo', sans-serif; font-size: clamp(1.6rem,3.5vw,2.3rem); font-weight: 900; color: #0d1b3e; padding-bottom: .7rem; position: relative; }
.pub-sec-h::after { content: ''; position: absolute; bottom: 0; right: 0; width: 48px; height: 4px; background: linear-gradient(90deg,#f9a825,#e65100); border-radius: 2px; }
.sec-header-split { display: flex; align-items: flex-end; justify-content: space-between; margin-bottom: 2.5rem; flex-wrap: wrap; gap: 1rem; }

.page-hero { background: linear-gradient(145deg, #0d1b4b 0%, #1a237e 60%, #283593 100%); padding: 4rem 0 3rem; position: relative; overflow: hidden; }
.page-hero-shapes { position: absolute; inset: 0; pointer-events: none; }
.ph-s { position: absolute; border-radius: 50%; }
.ph-s1 { width: 400px; height: 400px; background: radial-gradient(circle, rgba(249,168,37,.12), transparent 65%); top: -100px; left: 5%; }
.ph-s2 { width: 300px; height: 300px; background: radial-gradient(circle, rgba(255,255,255,.05), transparent 65%); bottom: -80px; right: 10%; }
.page-hero-inner { position: relative; z-index: 2; }
.page-breadcrumb { display: flex; align-items: center; gap: .5rem; font-size: .82rem; color: rgba(255,255,255,.6); margin-bottom: 1rem; }
.page-breadcrumb a { color: rgba(255,255,255,.6); text-decoration: none; } .page-breadcrumb a:hover { color: #f9a825; }
.page-hero-title { font-family: 'Neo Sans Arabic', 'Cairo', sans-serif; font-size: clamp(2rem,4vw,3rem); font-weight: 900; color: #fff; margin-bottom: .75rem; }
.page-hero-desc { font-size: 1rem; color: rgba(255,255,255,.75); line-height: 1.75; max-width: 600px; margin-bottom: 2rem; }
.hero-stats { display: flex; gap: 2.5rem; flex-wrap: wrap; }
.hs-item { display: flex; flex-direction: column; }
.hs-num { font-family: 'Neo Sans Arabic', 'Cairo', sans-serif; font-size: 2rem; font-weight: 900; color: #f9a825; line-height: 1; }
.hs-lbl { font-size: .8rem; color: rgba(255,255,255,.65); margin-top: .25rem; }

.courses-grid { display: grid; grid-template-columns: repeat(4,1fr); gap: 1.5rem; }
.course-card { border: 1.5px solid #e5e7eb; border-radius: 18px; overflow: hidden; display: flex; flex-direction: column; transition: all .3s; }
.course-card:hover { box-shadow: 0 12px 36px rgba(0,0,0,.1); transform: translateY(-6px); }
.course-header { padding: 1.25rem 1.25rem .875rem; border-bottom: 1.5px solid; display: flex; align-items: center; justify-content: space-between; }
.course-icon { width: 48px; height: 48px; border-radius: 12px; border: 1.5px solid; display: flex; align-items: center; justify-content: center; }
.course-certified { background: #2e7d32; color: #fff; font-size: .7rem; font-weight: 700; border-radius: 50px; padding: .2rem .7rem; }
.course-body { padding: 1.25rem; flex: 1; display: flex; flex-direction: column; gap: .65rem; }
.course-level { display: inline-block; font-size: .72rem; font-weight: 700; border-radius: 50px; padding: .2rem .7rem; width: fit-content; }
.course-title { font-family: 'Neo Sans Arabic', 'Cairo', sans-serif; font-size: .9rem; font-weight: 800; color: #0d1b3e; line-height: 1.5; }
.course-desc { font-size: .82rem; color: #4b5563; line-height: 1.75; flex: 1; }
.course-meta { display: flex; flex-direction: column; gap: .35rem; }
.course-meta span { display: flex; align-items: center; gap: .4rem; font-size: .78rem; color: #6b7280; }
.course-register { display: flex; align-items: center; justify-content: center; gap: .4rem; width: 100%; color: #fff; border: none; border-radius: 10px; padding: .7rem; font-size: .88rem; font-weight: 700; cursor: pointer; font-family: 'Neo Sans Arabic', 'Tajawal', sans-serif; transition: all .2s; margin-top: auto; }
.course-register:hover { filter: brightness(1.08); transform: translateY(-1px); }

.cta-section { background: linear-gradient(145deg, #0d1b4b, #1a237e); padding: 5rem 0; }
.cta-inner { text-align: center; }
.cta-title { font-family: 'Neo Sans Arabic', 'Cairo', sans-serif; font-size: 1.75rem; font-weight: 900; color: #fff; margin-bottom: .65rem; }
.cta-sub { font-size: 1rem; color: rgba(255,255,255,.7); margin-bottom: 2rem; }
.cta-btn { display: inline-flex; align-items: center; background: linear-gradient(135deg,#f9a825,#e65100); color: #fff; border-radius: 10px; padding: .875rem 2.25rem; font-size: .95rem; font-weight: 800; text-decoration: none; font-family: 'Neo Sans Arabic', 'Tajawal', sans-serif; transition: all .25s; }
.cta-btn:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(249,168,37,.4); }

@media (max-width: 1100px) { .courses-grid { grid-template-columns: repeat(3,1fr); } }
@media (max-width: 800px) { .courses-grid { grid-template-columns: repeat(2,1fr); } }
@media (max-width: 500px) { .courses-grid { grid-template-columns: 1fr; } }
</style>

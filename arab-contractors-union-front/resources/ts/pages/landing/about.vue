<script setup lang="ts">
import PublicSocialFollow from '@/components/PublicSocialFollow.vue'
import { Check, Star, Lightbulb, Shield, Users, Target, Award, Globe, TrendingUp } from 'lucide-vue-next'

definePage({ meta: { layout: 'landing', public: true, unauthenticatedOnly: false } })

// المصدر: التقرير الإداري والمالي للدورة الانتخابية الثانية عشرة (2023–2026) – اتحاد المقاولين الفلسطينيين، المحافظات الجنوبية.
const values = [
  { icon: Shield,     title: 'النزاهة والشفافية',      desc: 'يلتزم الاتحاد بتطبيق أعلى معايير النزاهة والشفافية في جميع معاملاته وقراراته، مما يعزز الثقة والمصداقية لدى أعضائه وشركائه.', color: '#1a237e', bg: '#e8eaf6' },
  { icon: Award,      title: 'الجودة والتميز',         desc: 'يسعى الاتحاد إلى تحقيق أعلى مستويات الجودة في قطاع المقاولات، من خلال تشجيع التميز والابتكار في تنفيذ المشاريع.', color: '#c62828', bg: '#fce4ec' },
  { icon: Users,      title: 'المسؤولية المجتمعية',    desc: 'يؤمن الاتحاد بدوره في دعم المجتمع الفلسطيني والمساهمة في التنمية المستدامة من خلال تنفيذ مشاريع تخدم الصالح العام.', color: '#e65100', bg: '#fff8e1' },
  { icon: Globe,      title: 'التعاون والشراكة',       desc: 'يعزز الاتحاد ثقافة التعاون والشراكة مع الجهات الحكومية والخاصة، بما يسهم في بناء بيئة عمل متكاملة ومثمرة.', color: '#2e7d32', bg: '#e8f5e9' },
  { icon: TrendingUp, title: 'الاحترافية والمهنية',    desc: 'يركز الاتحاد على رفع مستوى الاحترافية والمهنية بين أعضائه، من خلال توفير برامج تدريبية وتطويرية مستمرة.', color: '#6a1b9a', bg: '#f3e5f5' },
  { icon: Lightbulb,  title: 'العدالة والمساواة',      desc: 'يضمن الاتحاد تطبيق مبدأ العدالة والمساواة بين جميع أعضائه، مع توفير فرص متكافئة للجميع دون أي تمييز.', color: '#00695c', bg: '#e0f2f1' },
]

const goals = [
  'تنظيم مهنة المقاولات في فلسطين من خلال وضع المعايير والضوابط اللازمة لضمان جودة العمل والمنافسة العادلة في السوق.',
  'حماية حقوق ومصالح أعضائه أمام الجهات الرسمية وغير الرسمية، مما يضمن بيئة عمل مستقرة ومزدهرة.',
  'رفع كفاءة المقاولين والعاملين في قطاع الإنشاءات عبر تقديم برامج تدريبية وتطويرية متقدمة.',
  'تحسين بيئة العمل في قطاع المقاولات من خلال تعزيز معايير السلامة المهنية وضمان جودة التنفيذ.',
  'تعزيز قنوات التواصل بين المقاولين والجهات ذات العلاقة، بما في ذلك الحكومة والمانحين والمجتمع المحلي، لتحقيق تعاون مثمر ومستدام.',
  'تقديم خدمات الوساطة والمساعدة في حل النزاعات والمشكلات التي قد تنشأ بين المقاولين وأصحاب العمل لضمان استمرارية المشاريع بسلاسة.',
  'المساهمة في دعم قطاع الإنشاءات كأحد المحركات الأساسية للاقتصاد الوطني الفلسطيني، من خلال تعزيز الاستثمارات والمشاريع الإنشائية.',
]

const facts = [
  { num: '١٩٩٤', label: 'سنة التأسيس (١/٦/١٩٩٤)' },
  { num: 'القدس', label: 'المركز الرئيسي للاتحاد' },
  { num: 'فرعان', label: 'الضفة الغربية وقطاع غزة' },
  { num: '٧', label: 'أعضاء في مجلس إدارة غزة' },
]
</script>

<template>
  <div dir="rtl" class="pub-page">
    
    <!-- Page Hero -->
    <div class="page-hero">
      <div class="page-hero-shapes">
        <div class="ph-s ph-s1" /><div class="ph-s ph-s2" />
      </div>
      <div class="pub-cont page-hero-inner">
        <div class="page-breadcrumb">
          <RouterLink to="/landing">الرئيسية</RouterLink>
          <span>/</span>
          <span>عن الاتحاد</span>
        </div>
        <h1 class="page-hero-title">عن الاتحاد</h1>
        <p class="page-hero-desc">تعرّف على اتحاد المقاولين الفلسطينيين — تاريخه، رسالته، ورؤيته</p>
      </div>
      <div class="pub-follow-slot"><PublicSocialFollow /></div>
    </div>

    <!-- Overview -->
    <section class="pub-section">
      <div class="pub-cont about-two-col">
        <div class="about-text">
          <div class="pub-sec-label">نبذة عامة</div>
          <h2 class="pub-sec-h">مؤسسة مهنية اقتصادية اجتماعية</h2>
          <p class="pub-para">
            اتحاد المقاولين الفلسطينيين (Palestinian Contractors Union) هو مؤسسة مهنية فلسطينية اقتصادية
            اجتماعية، ممثلة بمجموع المقاولين المحليين والمصنفين في الاتحاد، ويهدف إلى تنظيم ممارستهم
            لمهنة المقاولات في فلسطين، ويقوم بدور إيجابي في التنمية الاقتصادية الوطنية.
          </p>
          <p class="pub-para">
            ومنذ التوقيع على اتفاقية أوسلو وارتفاع سقف تطلعات الشعب الفلسطيني والنشاط الملحوظ الذي شهده
            قطاع الإنشاءات، كانت الضرورة ملحّة لتشكيل هيئة نقابية تمثل المقاولين الفلسطينيين وتعمل على
            النهوض بمهنة المقاولات وتنظيمها. فتم تأسيس الاتحاد بتاريخ ١٩٩٤/٦/١ تحت رعاية سيادة رئيس دولة
            فلسطين الراحل ياسر عرفات، على أن تكون مدينة القدس مركزاً رئيسياً للاتحاد، ويكون للاتحاد فرعان
            رئيسيان في الضفة الغربية وقطاع غزة، كما يحق للاتحاد فتح مراكز وفروع في المدن الفلسطينية الأخرى
            بقرار من مجلس إدارة الاتحاد.
          </p>
        </div>
        <div class="about-stats-panel">
          <div v-for="a in facts" :key="a.label" class="ach-card">
            <span class="ach-num">{{ a.num }}</span>
            <span class="ach-lbl">{{ a.label }}</span>
          </div>
        </div>
      </div>
    </section>

    <!-- Vision & Mission -->
    <section class="pub-section bg-soft">
      <div class="pub-cont">
        <div class="pub-sec-header-c">
          <div class="pub-sec-label">الرؤية والرسالة</div>
          <h2 class="pub-sec-h center">توجهاتنا الاستراتيجية</h2>
        </div>
        <div class="vm-grid">
          <div class="vm-card vm-vision">
            <div class="vm-icon"><Star :size="32" /></div>
            <h3 class="vm-title">رؤيتنا</h3>
            <p class="vm-desc">اتحاد المقاولين الفلسطينيين المحرك الأساسي للنمو الاقتصادي والتنموي في فلسطين، ونموذجاً يُحتذى به في الصمود والتميز والابتكار.</p>
          </div>
          <div class="vm-card vm-mission">
            <div class="vm-icon"><Target :size="32" /></div>
            <h3 class="vm-title">رسالتنا</h3>
            <p class="vm-desc">يسعى اتحاد المقاولين الفلسطينيين بالمحافظات الجنوبية إلى تنظيم مهنة المقاولات والارتقاء بها وتعزيز القدرة التنافسية للمقاول الفلسطيني، من خلال وضع الأطر القانونية والفنية المرتكزة على التميز والابتكار ونسج الشراكات المحلية والدولية من منصة واحدة، ملتزمين بالشفافية والاحترافية والتميز بكل عدالة ومساواة.</p>
          </div>
          <div class="vm-card vm-goals">
            <div class="vm-icon"><Award :size="32" /></div>
            <h3 class="vm-title">أبرز أهدافنا</h3>
            <ul class="vm-list">
              <li>تنظيم مهنة المقاولات ووضع المعايير والضوابط</li>
              <li>حماية حقوق ومصالح الأعضاء</li>
              <li>رفع كفاءة المقاولين عبر برامج تدريبية</li>
              <li>خدمات الوساطة وحل النزاعات</li>
            </ul>
          </div>
        </div>
      </div>
    </section>

    <!-- Values -->
    <section class="pub-section">
      <div class="pub-cont">
        <div class="pub-sec-header-c">
          <div class="pub-sec-label">القيم</div>
          <h2 class="pub-sec-h center">قيمنا المؤسسية</h2>
        </div>
        <div class="values-grid">
          <div v-for="v in values" :key="v.title" class="value-card" :style="`--vc:${v.color};--vbg:${v.bg}`">
            <div class="value-icon"><component :is="v.icon" :size="26" /></div>
            <h3 class="value-title">{{ v.title }}</h3>
            <p class="value-desc">{{ v.desc }}</p>
          </div>
        </div>
      </div>
    </section>

    <!-- Goals -->
    <section class="pub-section bg-soft">
      <div class="pub-cont">
        <div class="pub-sec-header-c">
          <div class="pub-sec-label">الأهداف</div>
          <h2 class="pub-sec-h center">أهداف الاتحاد</h2>
        </div>
        <ol class="goals-list">
          <li v-for="g in goals" :key="g"><Check :size="16" class="chk-ico" /> <span>{{ g }}</span></li>
        </ol>
      </div>
    </section>

  </div>
</template>

<style scoped>
.goals-list { list-style: none; max-width: 900px; margin: 0 auto; display: flex; flex-direction: column; gap: .9rem; }
.goals-list li { display: flex; gap: .75rem; align-items: flex-start; background: #fff; border: 1.5px solid #e5e7eb; border-radius: 12px; padding: 1rem 1.25rem; line-height: 1.8; font-size: .95rem; }
.goals-list .chk-ico { color: #2e7d32; flex-shrink: 0; margin-top: .4rem; }
@import url('https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800;900&family=Tajawal:wght@400;500;700;800&display=swap');
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
.pub-page { font-family: 'Tajawal', 'Cairo', sans-serif; direction: rtl; color: #374151; background: #fff; }
.pub-cont { max-width: 1240px; margin: 0 auto; padding: 0 1.5rem; }
.pub-section { padding: 5rem 0; }
.bg-soft { background: #f5f7ff; }
.pub-sec-label { display: inline-block; background: #e8eaf6; color: #1a237e; border: 1px solid #c5cae9; border-radius: 50px; padding: .28rem 1.1rem; font-size: .77rem; font-weight: 700; letter-spacing: .05em; margin-bottom: .65rem; }
.pub-sec-h { font-family: 'Tajawal', 'Cairo', sans-serif; font-size: clamp(1.6rem,3.5vw,2.3rem); font-weight: 900; color: #0d1b3e; margin-bottom: .5rem; position: relative; padding-bottom: .7rem; }
.pub-sec-h::after { content: ''; position: absolute; bottom: 0; right: 0; width: 48px; height: 4px; background: linear-gradient(90deg,#f9a825,#e65100); border-radius: 2px; }
.pub-sec-h.center { text-align: center; }
.pub-sec-h.center::after { right: 50%; transform: translateX(50%); }
.pub-sec-header-c { text-align: center; margin-bottom: 3rem; }
.pub-para { color: #4b5563; font-size: .97rem; line-height: 1.9; margin-bottom: .9rem; }

/* Page Hero */
.page-hero { background: linear-gradient(145deg, #0d1b4b 0%, #1a237e 60%, #283593 100%); margin-top: calc(-1 * var(--pub-nav-h, 114px)); padding: calc(var(--pub-nav-h, 114px) + 2.5rem) 0 3rem; position: relative; overflow: hidden; }
.page-hero-shapes { position: absolute; top: 0; right: 0; bottom: 0; left: 0; pointer-events: none; }
.ph-s { position: absolute; border-radius: 50%; }
.ph-s1 { width: 400px; height: 400px; background: radial-gradient(circle, rgba(249,168,37,.12), transparent 65%); top: -100px; left: 5%; }
.ph-s2 { width: 300px; height: 300px; background: radial-gradient(circle, rgba(255,255,255,.05), transparent 65%); bottom: -80px; right: 10%; }
.page-hero-inner { position: relative; z-index: 2; }
.page-breadcrumb { display: flex; align-items: center; gap: .5rem; font-size: .82rem; color: rgba(255,255,255,.6); margin-bottom: 1rem; }
.page-breadcrumb a { color: rgba(255,255,255,.6); text-decoration: none; transition: color .2s; }
.page-breadcrumb a:hover { color: #f9a825; }
.page-hero-title { font-family: 'Tajawal', 'Cairo', sans-serif; font-size: clamp(2rem,4vw,3rem); font-weight: 900; color: #fff; margin-bottom: .75rem; }
.page-hero-desc { font-size: 1rem; color: rgba(255,255,255,.75); line-height: 1.75; max-width: 600px; }

/* About */
.about-two-col { display: grid; grid-template-columns: 1.2fr 1fr; gap: 5rem; align-items: center; }
.about-checklist { list-style: none; margin: 1.5rem 0 0; display: flex; flex-direction: column; gap: .6rem; }
.about-checklist li { display: flex; align-items: center; gap: .6rem; font-size: .9rem; color: #374151; padding: .5rem; background: #f9fafb; border-radius: 8px; border: 1px solid #e5e7eb; }
.chk-ico { color: #2e7d32; flex-shrink: 0; }
.about-stats-panel { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
.ach-card { background: linear-gradient(135deg, #e8eaf6, #f5f7ff); border: 1.5px solid #c5cae9; border-radius: 16px; padding: 1.75rem 1.25rem; text-align: center; transition: transform .2s; }
.ach-card:hover { transform: translateY(-4px); box-shadow: 0 8px 24px rgba(13,27,75,.1); }
.ach-num { display: block; font-family: 'Tajawal', 'Cairo', sans-serif; font-size: 2rem; font-weight: 900; color: #1a237e; line-height: 1; }
.ach-lbl { display: block; font-size: .8rem; color: #6b7280; font-weight: 600; margin-top: .35rem; }

/* Vision & Mission */
.vm-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.5rem; }
.vm-card { border-radius: 20px; padding: 2.5rem 2rem; text-align: center; }
.vm-vision { background: linear-gradient(145deg, #e8eaf6, #c5cae9); border: 1.5px solid #9fa8da; }
.vm-mission { background: linear-gradient(145deg, #fff8e1, #ffe082); border: 1.5px solid #fdd835; }
.vm-goals { background: linear-gradient(145deg, #e8f5e9, #c8e6c9); border: 1.5px solid #81c784; }
.vm-icon { width: 64px; height: 64px; border-radius: 18px; background: rgba(255,255,255,.6); display: flex; align-items: center; justify-content: center; margin: 0 auto 1.25rem; color: #1a237e; }
.vm-title { font-family: 'Tajawal', 'Cairo', sans-serif; font-size: 1.15rem; font-weight: 900; color: #0d1b3e; margin-bottom: .875rem; }
.vm-desc { font-size: .9rem; color: #4b5563; line-height: 1.85; }
.vm-list { list-style: none; text-align: right; }
.vm-list li { font-size: .88rem; color: #4b5563; padding: .35rem 0; border-bottom: 1px solid rgba(0,0,0,.06); display: flex; align-items: center; }
.vm-list li::before { content: '•'; color: #2e7d32; margin-inline-end: .5rem; font-weight: 900; }

/* Values */
.values-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.5rem; }
.value-card { background: var(--vbg); border: 1.5px solid var(--vc); border-top: 4px solid var(--vc); border-radius: 16px; padding: 1.75rem; transition: transform .25s, box-shadow .25s; }
.value-card:hover { transform: translateY(-5px); box-shadow: 0 10px 28px rgba(0,0,0,.1); }
.value-icon { width: 54px; height: 54px; border-radius: 14px; background: rgba(255,255,255,.6); border: 1.5px solid var(--vc); color: var(--vc); display: flex; align-items: center; justify-content: center; margin-bottom: 1rem; }
.value-title { font-family: 'Tajawal', 'Cairo', sans-serif; font-size: .95rem; font-weight: 800; color: #0d1b3e; margin-bottom: .4rem; }
.value-desc { font-size: .83rem; color: #6b7280; line-height: 1.75; }

/* Timeline */
.timeline { position: relative; max-width: 860px; margin: 0 auto; }
.tl-line { position: absolute; top: 0; bottom: 0; right: 50%; width: 2px; background: linear-gradient(to bottom, #1a237e, #c5cae9); transform: translateX(50%); }
.tl-item { display: flex; justify-content: flex-end; margin-bottom: 2rem; padding-inline-end: calc(50% + 1.5rem); position: relative; }
.tl-item.right { justify-content: flex-start; padding-inline-end: 0; padding-inline-start: calc(50% + 1.5rem); }
.tl-dot { position: absolute; top: 1.25rem; right: calc(50% - 8px); width: 16px; height: 16px; border-radius: 50%; background: #1a237e; border: 3px solid #e8eaf6; z-index: 1; }
.tl-item.right .tl-dot { right: auto; left: calc(50% - 8px); }
.tl-card { background: #fff; border: 1.5px solid #e5e7eb; border-radius: 14px; padding: 1.25rem 1.5rem; max-width: 320px; box-shadow: 0 2px 12px rgba(0,0,0,.05); transition: box-shadow .2s; }
.tl-card:hover { box-shadow: 0 6px 20px rgba(13,27,75,.1); }
.tl-year { display: block; font-family: 'Tajawal', 'Cairo', sans-serif; font-size: .9rem; font-weight: 900; color: #1a237e; margin-bottom: .4rem; }
.tl-event { font-size: .88rem; color: #4b5563; line-height: 1.7; }

@media (max-width: 1024px) {
  .about-two-col { grid-template-columns: 1fr; gap: 2.5rem; }
  .vm-grid { grid-template-columns: 1fr; }
}
@media (max-width: 768px) {
  .values-grid { grid-template-columns: 1fr 1fr; }
  .about-stats-panel { grid-template-columns: 1fr 1fr; }
  .tl-item, .tl-item.right { padding-inline-start: 2rem; padding-inline-end: 0; justify-content: flex-start; }
  .tl-dot, .tl-item.right .tl-dot { right: auto; left: 0; }
  .tl-line { right: auto; left: 7px; }
  .tl-card { max-width: 100%; }
}
@media (max-width: 480px) {
  .values-grid { grid-template-columns: 1fr; }
}
</style>

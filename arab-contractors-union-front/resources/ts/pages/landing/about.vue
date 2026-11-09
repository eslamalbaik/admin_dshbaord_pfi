<script setup lang="ts">
import PublicSocialFollow from '@/components/PublicSocialFollow.vue'
import { Check, Star, Target, Award, Shield, Users, Globe, Handshake, GraduationCap, Scale } from 'lucide-vue-next'

definePage({ meta: { layout: 'landing', public: true, unauthenticatedOnly: false } })

// المصدر: التقرير الإداري لاتحاد المقاولين الفلسطينيين — المحافظات الجنوبية (الدورة الانتخابية الثانية عشرة)
const goals = [
  'تنظيم مهنة المقاولات في فلسطين من خلال وضع المعايير والضوابط اللازمة لضمان جودة العمل والمنافسة العادلة في السوق.',
  'حماية حقوق ومصالح أعضائه أمام الجهات الرسمية وغير الرسمية، مما يضمن بيئة عمل مستقرة ومزدهرة.',
  'رفع كفاءة المقاولين والعاملين في قطاع الإنشاءات عبر تقديم برامج تدريبية وتطويرية متقدمة.',
  'تحسين بيئة العمل في قطاع المقاولات من خلال تعزيز معايير السلامة المهنية وضمان جودة التنفيذ.',
  'تعزيز قنوات التواصل بين المقاولين والجهات ذات العلاقة، بما في ذلك الحكومة والمانحين والمجتمع المحلي.',
  'تقديم خدمات الوساطة والمساعدة في حل النزاعات والمشكلات التي قد تنشأ بين المقاولين وأصحاب العمل.',
  'المساهمة في دعم قطاع الإنشاءات كأحد المحركات الأساسية للاقتصاد الوطني الفلسطيني، من خلال تعزيز الاستثمارات والمشاريع الإنشائية.',
]

const values = [
  { icon: Shield,        title: 'النزاهة والشفافية',   desc: 'يلتزم الاتحاد بتطبيق أعلى معايير النزاهة والشفافية في جميع معاملاته وقراراته، مما يعزز الثقة والمصداقية لدى أعضائه وشركائه.', color: '#1a237e', bg: '#e8eaf6' },
  { icon: Award,         title: 'الجودة والتميز',      desc: 'يسعى الاتحاد إلى تحقيق أعلى مستويات الجودة في قطاع المقاولات، من خلال تشجيع التميز والابتكار في تنفيذ المشاريع.', color: '#e65100', bg: '#fff8e1' },
  { icon: Users,         title: 'المسؤولية المجتمعية', desc: 'يؤمن الاتحاد بدوره في دعم المجتمع الفلسطيني والمساهمة في التنمية المستدامة من خلال تنفيذ مشاريع تخدم الصالح العام.', color: '#c62828', bg: '#fce4ec' },
  { icon: Handshake,     title: 'التعاون والشراكة',    desc: 'يعزز الاتحاد ثقافة التعاون والشراكة مع الجهات الحكومية والخاصة، بما يسهم في بناء بيئة عمل متكاملة ومثمرة.', color: '#2e7d32', bg: '#e8f5e9' },
  { icon: GraduationCap, title: 'الاحترافية والمهنية', desc: 'يركز الاتحاد على رفع مستوى الاحترافية والمهنية بين أعضائه، من خلال توفير برامج تدريبية وتطويرية مستمرة.', color: '#6a1b9a', bg: '#f3e5f5' },
  { icon: Scale,         title: 'العدالة والمساواة',   desc: 'يضمن الاتحاد تطبيق مبدأ العدالة والمساواة بين جميع أعضائه، مع توفير فرص متكافئة للجميع دون أي تمييز.', color: '#00695c', bg: '#e0f2f1' },
]

const facts = [
  { num: '١٩٩٤', label: 'سنة تأسيس الاتحاد' },
  { num: '٧', label: 'أعضاء مجلس الإدارة' },
  { num: '٩', label: 'لجان داخلية' },
  { num: '٣', label: 'لجان خارجية' },
]

const committees = [
  { name: 'لجنة التنسيب والتصنيف', chair: 'محروس أبو رجيلة', desc: 'بحث آليات تصنيف المقاولين وتنسيبهم وفق معايير واضحة تضمن العدالة والشفافية في منح التصنيفات.' },
  { name: 'لجنة تنظيم المهنة والكوتة', chair: 'حسين شنن', desc: 'بحث آليات تنظيم قطاع المقاولات وتوزيع المشاريع وفق نظام الكوتة العادل بين المقاولين.' },
  { name: 'لجنة التدريب والتطوير', chair: 'م. هاشم سكيك', desc: 'مناقشة البرامج التدريبية الخاصة بالمقاولين وموظفيهم وإعداد خطط لتطوير قدراتهم بما يتماشى مع متطلبات السوق.' },
  { name: 'لجنة العلاقات العامة والدولية', chair: 'م. عارف أبو عودة', desc: 'تعزيز العلاقات مع الجهات المحلية والدولية وفتح آفاق التعاون مع المؤسسات المانحة والشركاء الدوليين لدعم قطاع المقاولات.' },
  { name: 'اللجنة القانونية والعقود', chair: 'عماد أبو مطر', desc: 'دراسة عقود العمل التي تقوم المؤسسات بطرحها وتوفير آليات التحكيم لحل النزاعات بين المقاولين.' },
  { name: 'لجنة فض النزاعات والشكاوى', chair: 'عماد أبو مطر', desc: 'دراسة الشكاوى المقدمة من المقاولين وأصحاب المشاريع وتقديم الحلول القانونية المناسبة لتسوية النزاعات.' },
  { name: 'اللجنة الاجتماعية', chair: 'م. عارف أبو عودة', desc: 'تنفيذ زيارات اجتماعية دورية للمقاولين وأسرهم، وتقديم الدعم الطارئ خلال فترات الحرب والمحن.' },
  { name: 'لجنة المعابر والحدود', chair: 'عبد الحكيم إسماعيل', desc: 'متابعة القضايا المتعلقة بدخول وخروج مواد البناء والتنسيق مع الجهات المختصة لتوفير التسهيلات اللازمة لقطاع المقاولات.' },
  { name: 'لجنة الإرجاع الضريبي', chair: 'م. عارف أبو عودة', desc: 'تسهيل عملية استرداد الضرائب للمقاولين وفق القوانين المعمول بها، والتواصل مع الجهات الحكومية لحل المعوقات.' },
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
        <p class="page-hero-desc">تعرّف على اتحاد المقاولين الفلسطينيين — تعريفه، رؤيته، رسالته وأهدافه</p>
      </div>
      <div class="pub-follow-slot"><PublicSocialFollow /></div>
    </div>

    <!-- Overview -->
    <section class="pub-section">
      <div class="pub-cont about-two-col">
        <div class="about-text">
          <div class="pub-sec-label">تعريف</div>
          <h2 class="pub-sec-h">اتحاد المقاولين الفلسطينيين</h2>
          <p class="pub-para">
            اتحاد المقاولين الفلسطينيين (Palestinian Contractors Union) هو مؤسسة مهنية فلسطينية اقتصادية اجتماعية،
            ممثلة بمجموع المقاولين المحليين والمصنفين في الاتحاد، ويهدف إلى تنظيم ممارستهم لمهنة المقاولات في فلسطين،
            ويقوم بدور إيجابي في التنمية الاقتصادية الوطنية.
          </p>
          <p class="pub-para">
            ومنذ التوقيع على اتفاقية أوسلو وارتفاع سقف تطلعات الشعب الفلسطيني والنشاط الملحوظ الذي شهده قطاع الإنشاءات،
            كانت الضرورة ملحة لتشكيل هيئة نقابية تمثل المقاولين الفلسطينيين وتعمل على النهوض بمهنة المقاولات وتنظيمها،
            ونتيجة لتضافر الجهود المشتركة لكل من الأمانة العامة للاتحاد العام للمهندسين الفلسطينيين وعدد من المقاولين
            الفلسطينيين من داخل الوطن وخارجه، فقد تم تأسيس اتحاد المقاولين الفلسطينيين بتاريخ 1/6/1994، كتتويج للاجتماع
            الذي عقد في تونس في الفترة ما بين 30/5 – 1/6/1994، تحت رعاية سيادة رئيس دولة فلسطين الراحل ياسر عرفات،
            على أن تكون مدينة القدس مركزاً رئيسياً للاتحاد، ويكون للاتحاد فرعان رئيسيان في الضفة الغربية وقطاع غزة،
            كما يحق للاتحاد فتح مراكز وفروع في المدن الفلسطينية الأخرى بقرار من مجلس إدارة الاتحاد.
          </p>
          <p class="pub-para">
            يُعد اتحاد المقاولين الفلسطينيين جهةً رائدةً في تنظيم قطاع الإنشاءات ودعم مصالح المقاولين في فلسطين.
          </p>
        </div>
        <div class="about-stats-panel">
          <img src="/images/landing/about-hq.jpg" alt="مقر اتحاد المقاولين الفلسطينيين في غزة" class="about-hq">
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
          <h2 class="pub-sec-h center">رؤيتنا ورسالتنا</h2>
        </div>
        <div class="vm-grid vm-two">
          <div class="vm-card vm-vision">
            <div class="vm-icon"><Star :size="32" /></div>
            <h3 class="vm-title">الرؤية</h3>
            <p class="vm-desc">اتحاد المقاولين الفلسطينيين المحرك الأساسي للنمو الاقتصادي والتنموي في فلسطين، ونموذجا يُحتذى به في الصمود والتميز والابتكار.</p>
          </div>
          <div class="vm-card vm-mission">
            <div class="vm-icon"><Target :size="32" /></div>
            <h3 class="vm-title">الرسالة</h3>
            <p class="vm-desc">يسعى اتحاد المقاولين الفلسطينيين بالمحافظات الجنوبية إلى تنظيم مهنة المقاولات والارتقاء بها وتعزيز القدرة التنافسية للمقاول الفلسطيني، وذلك من خلال وضع الأطر القانونية والفنية المرتكزة على التميز والابتكار ونسج الشراكات المحلية والدولية من منصة واحدة، ملتزمين بالشفافية والاحترافية والتميز بكل عدالة ومساواة.</p>
          </div>
        </div>
      </div>
    </section>

    <!-- Goals -->
    <section class="pub-section">
      <div class="pub-cont goals-wrap">
        <div class="pub-sec-header-c">
          <div class="pub-sec-label">الأهداف</div>
          <h2 class="pub-sec-h center">أهداف الاتحاد</h2>
        </div>
        <ul class="about-checklist">
          <li v-for="g in goals" :key="g"><Check :size="15" class="chk-ico" /> {{ g }}</li>
        </ul>
      </div>
    </section>

    <!-- Values -->
    <section class="pub-section bg-soft">
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

    <!-- Committees -->
    <section class="pub-section">
      <div class="pub-cont">
        <div class="pub-sec-header-c">
          <div class="pub-sec-label">اللجان</div>
          <h2 class="pub-sec-h center">اللجان الداخلية للاتحاد</h2>
        </div>
        <div class="values-grid">
          <div v-for="c in committees" :key="c.name" class="value-card" style="--vc:#1a237e;--vbg:#f5f7ff">
            <h3 class="value-title">{{ c.name }}</h3>
            <p class="value-desc">{{ c.desc }}</p>
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

/* extra */
.about-hq { grid-column: 1 / -1; width: 100%; border-radius: 16px; object-fit: cover; max-height: 280px; }
.vm-two { grid-template-columns: repeat(2, 1fr); }
.goals-wrap { max-width: 900px; }
.chair { display: flex; align-items: center; gap: .4rem; margin-top: .75rem; font-size: .8rem; font-weight: 700; color: #1a237e; }
/* Timeline (unused) */
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
  .vm-grid, .vm-two { grid-template-columns: 1fr; }
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

<script setup lang="ts">
import PublicSocialFollow from '@/components/PublicSocialFollow.vue'
import PublicCommitteeGrid from '@/components/PublicCommitteeGrid.vue'
import { Award, ChevronLeft, Eye, GraduationCap, Handshake, Scale, Shield, Target, Users } from 'lucide-vue-next'

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
  { num: '٠١', icon: Shield, title: 'النزاهة والشفافية', desc: 'يلتزم الاتحاد بتطبيق أعلى معايير النزاهة والشفافية في جميع معاملاته وقراراته، مما يعزز الثقة والمصداقية لدى أعضائه وشركائه.' },
  { num: '٠٢', icon: Award, title: 'الجودة والتميز', desc: 'يسعى الاتحاد إلى تحقيق أعلى مستويات الجودة في قطاع المقاولات، من خلال تشجيع التميز والابتكار في تنفيذ المشاريع.' },
  { num: '٠٣', icon: Users, title: 'المسؤولية المجتمعية', desc: 'يؤمن الاتحاد بدوره في دعم المجتمع الفلسطيني والمساهمة في التنمية المستدامة من خلال تنفيذ مشاريع تخدم الصالح العام.' },
  { num: '٠٤', icon: Handshake, title: 'التعاون والشراكة', desc: 'يعزز الاتحاد ثقافة التعاون والشراكة مع الجهات الحكومية والخاصة، بما يسهم في بناء بيئة عمل متكاملة ومثمرة.' },
  { num: '٠٥', icon: GraduationCap, title: 'الاحترافية والمهنية', desc: 'يركز الاتحاد على رفع مستوى الاحترافية والمهنية بين أعضائه، من خلال توفير برامج تدريبية وتطويرية مستمرة.' },
  { num: '٠٦', icon: Scale, title: 'العدالة والمساواة', desc: 'يضمن الاتحاد تطبيق مبدأ العدالة والمساواة بين جميع أعضائه، مع توفير فرص متكافئة للجميع دون أي تمييز.' },
]

const facts = [
  { num: '١٩٩٤', label: 'سنة تأسيس الاتحاد' },
  { num: '٧', label: 'أعضاء مجلس الإدارة' },
  { num: '٩', label: 'لجان داخلية' },
  { num: '٣', label: 'لجان خارجية' },
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
            على أن تكون مدينة القدس مركزاً رئيسياً للاتحاد.
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
        <div class="vm-grid">
          <div class="vm-card vm-vision">
            <div class="vm-head">
              <div class="vm-icon"><Eye :size="30" /></div>
              <h3 class="vm-title">الرؤية</h3>
            </div>
            <p class="vm-desc">اتحاد المقاولين الفلسطينيين المحرك الأساسي للنمو الاقتصادي والتنموي في فلسطين، ونموذجا يُحتذى به في الصمود والتميز والابتكار.</p>
          </div>
          <div class="vm-card vm-mission">
            <div class="vm-head">
              <div class="vm-icon"><Target :size="30" /></div>
              <h3 class="vm-title">الرسالة</h3>
            </div>
            <p class="vm-desc">يسعى اتحاد المقاولين الفلسطينيين بالمحافظات الجنوبية إلى تنظيم مهنة المقاولات والارتقاء بها وتعزيز القدرة التنافسية للمقاول الفلسطيني، وذلك من خلال وضع الأطر القانونية والفنية المرتكزة على التميز والابتكار ونسج الشراكات المحلية والدولية من منصة واحدة، ملتزمين بالشفافية والاحترافية والتميز بكل عدالة ومساواة.</p>
          </div>
        </div>
      </div>
    </section>

    <!-- Goals -->
    <section class="pub-section">
      <div class="pub-cont">
        <div class="pub-sec-header-c">
          <div class="pub-sec-label">الأهداف</div>
          <h2 class="pub-sec-h center">أهداف الاتحاد</h2>
        </div>
        <div class="goals-grid">
          <ol class="goals-list">
            <li v-for="(g, i) in goals" :key="g" class="goal-item">
              <span class="goal-num">{{ String(i + 1).padStart(2, '0') }}</span>
              <span class="goal-arrow" aria-hidden="true"><ChevronLeft :size="18" /></span>
              <p>{{ g }}</p>
            </li>
          </ol>
          <div class="goals-photo">
            <img src="/images/landing/goals-workers.jpg" alt="مهندسون في موقع إنشاءات" loading="lazy">
            <div class="goals-photo-cap">
              <span class="goals-photo-bar" />
              <p>معاً .. نحو قطاع إنشائي قوي ومستدام في فلسطين</p>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Values -->
    <section class="pub-section values-sec">
      <div class="values-shapes"><div class="ph-s vs-s1" /><div class="ph-s vs-s2" /></div>
      <div class="pub-cont values-inner">
        <div class="pub-sec-header-c">
          <div class="pub-sec-label values-label">القيم</div>
          <h2 class="pub-sec-h center values-h">قيمنا المؤسسية</h2>
        </div>
        <div class="values-grid">
          <article v-for="v in values" :key="v.title" class="value-card">
            <div class="value-head">
              <div class="value-icon"><component :is="v.icon" :size="24" /></div>
              <span class="value-num" aria-hidden="true">{{ v.num }}</span>
            </div>
            <h3 class="value-title">{{ v.title }}</h3>
            <p class="value-desc">{{ v.desc }}</p>
          </article>
        </div>
      </div>
    </section>

    <!-- Committees -->
    <section class="pub-section bg-soft">
      <div class="pub-cont">
        <div class="pub-sec-header-c">
          <div class="pub-sec-label">اللجان</div>
          <h2 class="pub-sec-h center">اللجان الداخلية للاتحاد</h2>
        </div>
        <PublicCommitteeGrid />
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
.about-stats-panel { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
.ach-card { background: linear-gradient(135deg, #e8eaf6, #f5f7ff); border: 1.5px solid #c5cae9; border-radius: 16px; padding: 1.75rem 1.25rem; text-align: center; transition: transform .2s; }
.ach-card:hover { transform: translateY(-4px); box-shadow: 0 8px 24px rgba(13,27,75,.1); }
.ach-num { display: block; font-family: 'Tajawal', 'Cairo', sans-serif; font-size: 2rem; font-weight: 900; color: #1a237e; line-height: 1; }
.ach-lbl { display: block; font-size: .8rem; color: #6b7280; font-weight: 600; margin-top: .35rem; }

/* Vision & Mission */
.vm-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 1.75rem; }
.vm-card { position: relative; overflow: hidden; isolation: isolate; display: flex; flex-direction: column; min-height: 340px; border-radius: 24px; padding: 3rem 2.75rem; box-shadow: 0 10px 30px rgba(13,27,75,.08); transition: transform .3s, box-shadow .3s; }
.vm-card::after { content: ''; position: absolute; bottom: -90px; inset-inline-end: -90px; width: 260px; height: 260px; border-radius: 50%; background: radial-gradient(circle, rgba(255,255,255,.75), transparent 70%); z-index: -1; }
.vm-card:hover { transform: translateY(-6px); box-shadow: 0 18px 40px rgba(13,27,75,.14); }
.vm-vision { background: linear-gradient(145deg, #f2f5fd 0%, #dde5f8 100%); border: 1px solid #c5cae9; }
.vm-mission { background: linear-gradient(145deg, #fffaf0 0%, #fbe7c0 100%); border: 1px solid #f3d9a4; }
.vm-head { display: flex; align-items: center; gap: 1.1rem; margin-bottom: 1.5rem; }
.vm-icon { width: 68px; height: 68px; border-radius: 18px; background: #fff; display: flex; align-items: center; justify-content: center; flex-shrink: 0; box-shadow: 0 4px 14px rgba(13,27,75,.1); }
.vm-vision .vm-icon { color: #1a237e; }
.vm-mission .vm-icon { color: #e8790a; }
.vm-title { font-family: 'Tajawal', 'Cairo', sans-serif; font-size: 1.6rem; font-weight: 900; color: #0d1b3e; }
.vm-desc { font-size: 1.02rem; color: #374151; line-height: 2.05; }

/* Goals */
.goals-grid { display: grid; grid-template-columns: 1.55fr 1fr; gap: 2.5rem; align-items: stretch; }
.goals-list { list-style: none; display: flex; flex-direction: column; gap: .7rem; }
.goal-item { display: flex; align-items: center; gap: .85rem; background: #fff; border: 1px solid #e5e9f5; border-radius: 14px; padding: .75rem 1rem; box-shadow: 0 4px 14px rgba(13,27,75,.06); transition: transform .25s, box-shadow .25s, border-color .25s; }
.goal-item:hover { transform: translateX(-4px); box-shadow: 0 10px 24px rgba(13,27,75,.12); border-color: #f9a825; }
.goal-num { width: 50px; height: 42px; border-radius: 10px; background: #1a237e; color: #fff; font-weight: 900; font-size: 1rem; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.goal-arrow { color: #e8790a; display: flex; flex-shrink: 0; }
.goal-item p { flex: 1; font-size: .93rem; line-height: 1.85; color: #1f2937; }
.goals-photo { position: relative; border-radius: 24px; overflow: hidden; min-height: 420px; box-shadow: 0 16px 40px rgba(13,27,75,.18); }
.goals-photo img { position: absolute; top: 0; left: 0; width: 100%; height: 100%; object-fit: cover; }
/* النص على يمين الصورة: تعتيم من جهة اليمين */
.goals-photo-cap { position: absolute; top: 0; right: 0; bottom: 0; left: 0; display: flex; flex-direction: column; justify-content: flex-end; align-items: flex-start; padding: 2rem 1.75rem; color: #fff; background: linear-gradient(to left, rgba(13,27,75,.92) 0%, rgba(13,27,75,.62) 38%, rgba(13,27,75,0) 72%), linear-gradient(to top, rgba(13,27,75,.55) 0%, rgba(13,27,75,0) 45%); }
.goals-photo-bar { display: block; width: 44px; height: 4px; border-radius: 2px; background: #f9a825; margin-bottom: .9rem; }
.goals-photo-cap p { font-family: 'Tajawal', 'Cairo', sans-serif; font-size: 1.2rem; font-weight: 800; line-height: 1.75; max-width: 11rem; text-align: start; }

/* Values */
.values-sec { background: linear-gradient(145deg, #0d1b4b 0%, #1a237e 60%, #283593 100%); position: relative; overflow: hidden; }
.values-shapes { position: absolute; top: 0; right: 0; bottom: 0; left: 0; pointer-events: none; }
.vs-s1 { width: 460px; height: 460px; background: radial-gradient(circle, rgba(249,168,37,.14), transparent 65%); top: -160px; right: -80px; }
.vs-s2 { width: 380px; height: 380px; background: radial-gradient(circle, rgba(255,255,255,.06), transparent 65%); bottom: -140px; left: -60px; }
.values-inner { position: relative; z-index: 1; }
.values-label { background: rgba(249,168,37,.12); color: #f9a825; border-color: rgba(249,168,37,.35); }
.values-h { color: #fff; }
.values-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.25rem; }
.value-card { position: relative; overflow: hidden; background: rgba(255,255,255,.06); border: 1px solid rgba(255,255,255,.12); border-radius: 18px; padding: 1.75rem; transition: transform .25s, background .25s, border-color .25s, box-shadow .25s; }
.value-card::before { content: ''; position: absolute; top: 0; inset-inline-start: 0; width: 48px; height: 3px; background: linear-gradient(90deg,#f9a825,#e65100); transition: width .35s; }
.value-card:hover { transform: translateY(-5px); background: rgba(255,255,255,.1); border-color: rgba(249,168,37,.45); box-shadow: 0 16px 36px rgba(0,0,0,.25); }
.value-card:hover::before { width: 100%; }
.value-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.25rem; }
.value-icon { width: 52px; height: 52px; border-radius: 14px; background: rgba(249,168,37,.14); border: 1px solid rgba(249,168,37,.3); color: #f9a825; display: flex; align-items: center; justify-content: center; transition: background .25s, color .25s; }
.value-card:hover .value-icon { background: #f9a825; color: #0d1b3e; }
.value-num { font-family: 'Tajawal', 'Cairo', sans-serif; font-size: 2.2rem; font-weight: 900; line-height: 1; color: rgba(255,255,255,.16); }
.value-title { font-family: 'Tajawal', 'Cairo', sans-serif; font-size: 1.08rem; font-weight: 800; color: #fff; margin-bottom: .5rem; }
.value-desc { font-size: .88rem; color: rgba(255,255,255,.72); line-height: 1.85; }

/* extra */
.about-hq { grid-column: 1 / -1; width: 100%; border-radius: 16px; object-fit: cover; max-height: 280px; }
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
  .vm-grid, .goals-grid { grid-template-columns: 1fr; }
  .vm-card { min-height: 0; padding: 2.25rem 1.75rem; }
  .goals-photo { min-height: 320px; }
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

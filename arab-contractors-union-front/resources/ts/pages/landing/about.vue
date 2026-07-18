<script setup lang="ts">
import { Check, Star, Lightbulb, Shield, Users, Target, Award, Globe, TrendingUp } from 'lucide-vue-next'

definePage({ meta: { layout: 'landing', public: true, unauthenticatedOnly: false } })

const timeline = [
  { year: '١٩٩٤', event: 'تأسيس اتحاد المقاولين الفلسطينيين بعد اتفاقية أوسلو' },
  { year: '١٩٩٨', event: 'الانضمام رسمياً لاتحاد المقاولين العرب' },
  { year: '٢٠٠٣', event: 'افتتاح أول فروع محافظات خارج رام الله' },
  { year: '٢٠٠٨', event: 'اعتماد نظام تصنيف المقاولين وفق معايير دولية' },
  { year: '٢٠١٤', event: 'توسيع العضوية لتشمل ١١ محافظة فلسطينية' },
  { year: '٢٠١٨', event: 'إطلاق البوابة الرقمية وخدمات الاتحاد الإلكترونية' },
  { year: '٢٠٢٢', event: 'تجاوز عدد الأعضاء ١٢٠٠ شركة مقاولات' },
  { year: '٢٠٢٤', event: 'إطلاق منظومة التدريب الإلكتروني وشهادات FIDIC' },
]

const values = [
  { icon: Shield,     title: 'النزاهة والشفافية',   desc: 'نتعامل بمنتهى الشفافية مع أعضائنا والجهات الرسمية', color: '#1a237e', bg: '#e8eaf6' },
  { icon: Users,      title: 'الانتماء والوحدة',     desc: 'نحن صوت واحد موحّد يمثل جميع المقاولين الفلسطينيين', color: '#c62828', bg: '#fce4ec' },
  { icon: Target,     title: 'الاحترافية والجودة',   desc: 'نسعى دائماً لرفع معايير الجودة في قطاع المقاولات', color: '#e65100', bg: '#fff8e1' },
  { icon: Globe,      title: 'الانفتاح والشراكة',    desc: 'نبني شراكات استراتيجية مع المؤسسات المحلية والدولية', color: '#2e7d32', bg: '#e8f5e9' },
  { icon: Lightbulb,  title: 'الابتكار والتطوير',    desc: 'نتبنى الحلول المبتكرة لتطوير بيئة المقاولات الفلسطينية', color: '#6a1b9a', bg: '#f3e5f5' },
  { icon: TrendingUp, title: 'التنمية المستدامة',    desc: 'نسعى لبناء قطاع مقاولات متوازن ومستدام للأجيال القادمة', color: '#00695c', bg: '#e0f2f1' },
]

const achievements = [
  { num: '+١٢٠٠', label: 'شركة مقاولات عضو' },
  { num: '+٥٠٠٠', label: 'مشروع منجز' },
  { num: '٣٠+',   label: 'سنة خبرة' },
  { num: '١١',     label: 'فرع ومحافظة' },
  { num: '+١٥٠',  label: 'دورة تدريبية' },
  { num: '+٢٥',   label: 'شراكة دولية' },
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
    </div>

    <!-- Overview -->
    <section class="pub-section">
      <div class="pub-cont about-two-col">
        <div class="about-text">
          <div class="pub-sec-label">نبذة عامة</div>
          <h2 class="pub-sec-h">الممثل الرسمي لقطاع المقاولات الفلسطيني</h2>
          <p class="pub-para">
            تأسّس اتحاد المقاولين الفلسطينيين عام ١٩٩٤ ليكون الجهة الرسمية الممثّلة لشركات المقاولات
            في فلسطين، ويضم اليوم أكثر من ١٢٠٠ شركة موزعة في ١١ محافظة، تمثل الغالبية العظمى
            من شركات المقاولات العاملة في الأراضي الفلسطينية.
          </p>
          <p class="pub-para">
            يتولى الاتحاد تصنيف الشركات وإصدار شهادات التصنيف المعتمدة، ويمثّل القطاع أمام
            الجهات الحكومية والمؤسسات الدولية، ويعمل على تطوير البيئة التشريعية وبناء قدرات
            المنتسبين عبر برامج تدريبية متخصصة.
          </p>
          <ul class="about-checklist">
            <li v-for="item in ['جهة رسمية معتمدة من السلطة الوطنية الفلسطينية','عضو في اتحاد المقاولين العرب والدولي','شهادات تصنيف معترف بها دولياً من FIDIC','حضور في جميع المحافظات الفلسطينية']" :key="item">
              <Check :size="15" class="chk-ico" /> {{ item }}
            </li>
          </ul>
        </div>
        <div class="about-stats-panel">
          <div v-for="a in achievements" :key="a.num" class="ach-card">
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
            <p class="vm-desc">قطاع مقاولات فلسطيني متميز يرقى للمعايير الدولية في الجودة والاحترافية والاستدامة، ويُسهم في بناء الدولة الفلسطينية المستقلة وتطوير بنيتها التحتية.</p>
          </div>
          <div class="vm-card vm-mission">
            <div class="vm-icon"><Target :size="32" /></div>
            <h3 class="vm-title">رسالتنا</h3>
            <p class="vm-desc">تمثيل قطاع المقاولات الفلسطيني وحماية مصالح أعضائه، وتطوير البيئة التشريعية والمهنية، وبناء القدرات وتعزيز الشراكات الاستراتيجية لخدمة القطاع والوطن.</p>
          </div>
          <div class="vm-card vm-goals">
            <div class="vm-icon"><Award :size="32" /></div>
            <h3 class="vm-title">أهدافنا</h3>
            <ul class="vm-list">
              <li>تنظيم قطاع المقاولات وتصنيف الشركات</li>
              <li>حماية حقوق المقاولين ومتابعة مستحقاتهم</li>
              <li>تطوير التشريعات والأنظمة الحاكمة للقطاع</li>
              <li>بناء شراكات مع الجهات المانحة والدولية</li>
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

    <!-- Timeline -->
    <section class="pub-section bg-soft">
      <div class="pub-cont">
        <div class="pub-sec-header-c">
          <div class="pub-sec-label">التاريخ</div>
          <h2 class="pub-sec-h center">مسيرة الاتحاد عبر الزمن</h2>
        </div>
        <div class="timeline">
          <div v-for="(t, i) in timeline" :key="t.year" class="tl-item" :class="{ right: i % 2 === 0 }">
            <div class="tl-dot" />
            <div class="tl-card">
              <span class="tl-year">{{ t.year }}</span>
              <p class="tl-event">{{ t.event }}</p>
            </div>
          </div>
          <div class="tl-line" />
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
.bg-soft { background: #f5f7ff; }
.pub-sec-label { display: inline-block; background: #e8eaf6; color: #1a237e; border: 1px solid #c5cae9; border-radius: 50px; padding: .28rem 1.1rem; font-size: .77rem; font-weight: 700; letter-spacing: .05em; margin-bottom: .65rem; }
.pub-sec-h { font-family: 'Neo Sans Arabic', 'Cairo', sans-serif; font-size: clamp(1.6rem,3.5vw,2.3rem); font-weight: 900; color: #0d1b3e; margin-bottom: .5rem; position: relative; padding-bottom: .7rem; }
.pub-sec-h::after { content: ''; position: absolute; bottom: 0; right: 0; width: 48px; height: 4px; background: linear-gradient(90deg,#f9a825,#e65100); border-radius: 2px; }
.pub-sec-h.center { text-align: center; }
.pub-sec-h.center::after { right: 50%; transform: translateX(50%); }
.pub-sec-header-c { text-align: center; margin-bottom: 3rem; }
.pub-para { color: #4b5563; font-size: .97rem; line-height: 1.9; margin-bottom: .9rem; }

/* Page Hero */
.page-hero { background: linear-gradient(145deg, #0d1b4b 0%, #1a237e 60%, #283593 100%); padding: 4rem 0 3rem; position: relative; overflow: hidden; }
.page-hero-shapes { position: absolute; inset: 0; pointer-events: none; }
.ph-s { position: absolute; border-radius: 50%; }
.ph-s1 { width: 400px; height: 400px; background: radial-gradient(circle, rgba(249,168,37,.12), transparent 65%); top: -100px; left: 5%; }
.ph-s2 { width: 300px; height: 300px; background: radial-gradient(circle, rgba(255,255,255,.05), transparent 65%); bottom: -80px; right: 10%; }
.page-hero-inner { position: relative; z-index: 2; }
.page-breadcrumb { display: flex; align-items: center; gap: .5rem; font-size: .82rem; color: rgba(255,255,255,.6); margin-bottom: 1rem; }
.page-breadcrumb a { color: rgba(255,255,255,.6); text-decoration: none; transition: color .2s; }
.page-breadcrumb a:hover { color: #f9a825; }
.page-hero-title { font-family: 'Neo Sans Arabic', 'Cairo', sans-serif; font-size: clamp(2rem,4vw,3rem); font-weight: 900; color: #fff; margin-bottom: .75rem; }
.page-hero-desc { font-size: 1rem; color: rgba(255,255,255,.75); line-height: 1.75; max-width: 600px; }

/* About */
.about-two-col { display: grid; grid-template-columns: 1.2fr 1fr; gap: 5rem; align-items: center; }
.about-checklist { list-style: none; margin: 1.5rem 0 0; display: flex; flex-direction: column; gap: .6rem; }
.about-checklist li { display: flex; align-items: center; gap: .6rem; font-size: .9rem; color: #374151; padding: .5rem; background: #f9fafb; border-radius: 8px; border: 1px solid #e5e7eb; }
.chk-ico { color: #2e7d32; flex-shrink: 0; }
.about-stats-panel { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
.ach-card { background: linear-gradient(135deg, #e8eaf6, #f5f7ff); border: 1.5px solid #c5cae9; border-radius: 16px; padding: 1.75rem 1.25rem; text-align: center; transition: transform .2s; }
.ach-card:hover { transform: translateY(-4px); box-shadow: 0 8px 24px rgba(13,27,75,.1); }
.ach-num { display: block; font-family: 'Neo Sans Arabic', 'Cairo', sans-serif; font-size: 2rem; font-weight: 900; color: #1a237e; line-height: 1; }
.ach-lbl { display: block; font-size: .8rem; color: #6b7280; font-weight: 600; margin-top: .35rem; }

/* Vision & Mission */
.vm-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.5rem; }
.vm-card { border-radius: 20px; padding: 2.5rem 2rem; text-align: center; }
.vm-vision { background: linear-gradient(145deg, #e8eaf6, #c5cae9); border: 1.5px solid #9fa8da; }
.vm-mission { background: linear-gradient(145deg, #fff8e1, #ffe082); border: 1.5px solid #fdd835; }
.vm-goals { background: linear-gradient(145deg, #e8f5e9, #c8e6c9); border: 1.5px solid #81c784; }
.vm-icon { width: 64px; height: 64px; border-radius: 18px; background: rgba(255,255,255,.6); display: flex; align-items: center; justify-content: center; margin: 0 auto 1.25rem; color: #1a237e; }
.vm-title { font-family: 'Neo Sans Arabic', 'Cairo', sans-serif; font-size: 1.15rem; font-weight: 900; color: #0d1b3e; margin-bottom: .875rem; }
.vm-desc { font-size: .9rem; color: #4b5563; line-height: 1.85; }
.vm-list { list-style: none; text-align: right; }
.vm-list li { font-size: .88rem; color: #4b5563; padding: .35rem 0; border-bottom: 1px solid rgba(0,0,0,.06); display: flex; align-items: center; }
.vm-list li::before { content: '•'; color: #2e7d32; margin-inline-end: .5rem; font-weight: 900; }

/* Values */
.values-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.5rem; }
.value-card { background: var(--vbg); border: 1.5px solid var(--vc); border-top: 4px solid var(--vc); border-radius: 16px; padding: 1.75rem; transition: transform .25s, box-shadow .25s; }
.value-card:hover { transform: translateY(-5px); box-shadow: 0 10px 28px rgba(0,0,0,.1); }
.value-icon { width: 54px; height: 54px; border-radius: 14px; background: rgba(255,255,255,.6); border: 1.5px solid var(--vc); color: var(--vc); display: flex; align-items: center; justify-content: center; margin-bottom: 1rem; }
.value-title { font-family: 'Neo Sans Arabic', 'Cairo', sans-serif; font-size: .95rem; font-weight: 800; color: #0d1b3e; margin-bottom: .4rem; }
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
.tl-year { display: block; font-family: 'Neo Sans Arabic', 'Cairo', sans-serif; font-size: .9rem; font-weight: 900; color: #1a237e; margin-bottom: .4rem; }
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

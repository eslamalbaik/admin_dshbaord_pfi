<script setup lang="ts">
import PublicSocialFollow from '@/components/PublicSocialFollow.vue'
import { ClipboardCheck, Award, FileCheck, Scale, GraduationCap, ShieldCheck, Megaphone, ChartColumn, Check } from 'lucide-vue-next'

definePage({ meta: { layout: 'landing', public: true, unauthenticatedOnly: false } })

const services = [
  {
    icon: ClipboardCheck, color: '#1a237e', bg: '#e8eaf6',
    title: 'تسجيل العضوية',
    desc: 'انضم إلى اتحاد المقاولين الفلسطينيين، واستفد من خدماته ومزاياه المهنية والمؤسسية المخصصة للأعضاء.',
    features: ['تقديم طلب العضوية إلكترونياً', 'متابعة حالة الطلب', 'استكمال إجراءات التسجيل', 'الحصول على شهادة العضوية'],
  },
  {
    icon: Award, color: '#c62828', bg: '#fde8e8',
    title: 'تجديد العضوية والاشتراكات',
    desc: 'جدّد عضويتك بسهولة، وحافظ على استمرار استفادتك من خدمات الاتحاد وحقوقك ومزايا العضوية.',
    features: ['تقديم طلب التجديد إلكترونياً', 'متابعة حالة التجديد', 'تسوية الاشتراكات المستحقة', 'إصدار شهادة العضوية المجددة'],
  },
  {
    icon: FileCheck, color: '#2e7d32', bg: '#e6f4e8',
    title: 'التصنيف وإصدار الشهادات',
    desc: 'خدمات متكاملة لتصنيف شركات المقاولات وفق معاييرها الفنية والمالية والإدارية، وتحديد درجاتها واختصاصاتها المعتمدة.',
    features: ['تقديم طلب التصنيف', 'تصنيف الشركات حسب الدرجة', 'تحديد الاختصاصات ومجالات العمل', 'تحديث بيانات وملفات التصنيف', 'إصدار شهادات التصنيف'],
  },
  {
    icon: Megaphone, color: '#0277bd', bg: '#e3f1fc',
    title: 'المناقصات والعطاءات',
    desc: 'منصة متخصصة تتيح لشركات المقاولات الاطلاع على المناقصات والفرص المتاحة لدى الجهات الحكومية والمؤسسات المحلية والدولية، ومتابعة متطلبات المشاركة فيها.',
    features: ['المناقصات الحكومية', 'مناقصات المؤسسات الدولية', 'فرص التعاقد والمشاريع', 'متابعة الإعلانات والنتائج'],
  },
  {
    icon: GraduationCap, color: '#6a1b9a', bg: '#f3e8f8',
    title: 'التدريب والتأهيل',
    desc: 'برامج تدريبية متخصصة لتطوير الكفاءات الفنية والإدارية، وتعزيز قدرات شركات المقاولات ورفع جاهزيتها للمنافسة وتنفيذ المشاريع.',
    features: ['برامج تدريبية متخصصة', 'ورش عمل مهنية وتطبيقية', 'برامج التأهيل والشهادات المهنية', 'تطوير القدرات المؤسسية والفنية'],
  },
  {
    icon: ShieldCheck, color: '#00695c', bg: '#e0f3f0',
    title: 'التحكيم وتسوية النزاعات',
    desc: 'خدمات متخصصة في تسوية المنازعات الهندسية والتعاقدية، من خلال الوسائل البديلة لفض النزاعات، بما يعزز العدالة ويحافظ على حقوق أطراف التعاقد.',
    features: ['التحكيم الهندسي', 'تسوية منازعات التشييد', 'الوساطة والتوفيق', 'معالجة الخلافات التعاقدية'],
  },
  {
    icon: Scale, color: '#ef6c00', bg: '#fff1e0',
    title: 'الاستشارات القانونية',
    desc: 'خدمات استشارية قانونية متخصصة لدعم شركات المقاولات في المسائل التعاقدية والقانونية وحماية حقوقها ومصالحها.',
    features: ['مراجعة العقود والاتفاقيات', 'تقديم الاستشارات القانونية', 'المساعدة في معالجة النزاعات التعاقدية', 'الإرشاد بشأن الحقوق والالتزامات القانونية'],
  },
  {
    icon: ChartColumn, color: '#2e7d32', bg: '#e6f4e8',
    title: 'الدراسات والأبحاث والتقارير',
    desc: 'إعداد دراسات وأبحاث وتقارير متخصصة ترصد واقع قطاع المقاولات وتطوراته، وتدعم اتخاذ القرار والتخطيط والتطوير المؤسسي.',
    features: ['التقارير الدورية عن القطاع', 'الإحصاءات والمؤشرات الاقتصادية', 'دراسات سوق المقاولات', 'الأبحاث والتوصيات التطويرية'],
  },
]
</script>

<template>
  <div dir="rtl" class="pub-page">
    
    <div class="page-hero">
      <div class="page-hero-shapes"><div class="ph-s ph-s1" /><div class="ph-s ph-s2" /></div>
      <div class="pub-cont page-hero-inner">
        <div class="page-breadcrumb">
          <RouterLink to="/landing">الرئيسية</RouterLink><span>/</span><span>الخدمات</span>
        </div>
        <h1 class="page-hero-title">خدمات الاتحاد</h1>
        <p class="page-hero-desc">منظومة متكاملة من الخدمات المهنية لدعم شركات المقاولات في جميع مراحل عملها</p>
      </div>
      <div class="pub-follow-slot"><PublicSocialFollow /></div>
    </div>

    <section class="pub-section">
      <div class="pub-cont">
        <div class="services-grid">
          <article v-for="s in services" :key="s.title" class="svc-card" :style="{ '--c': s.color, '--bg': s.bg }">
            <span class="svc-corner" />
            <header class="svc-head">
              <h3 class="svc-title">{{ s.title }}</h3>
              <div class="svc-icon"><component :is="s.icon" :size="26" :stroke-width="1.8" /></div>
            </header>
            <p class="svc-desc">{{ s.desc }}</p>
            <ul class="svc-features">
              <li v-for="f in s.features" :key="f"><Check :size="13" :stroke-width="3" class="svc-check" /> {{ f }}</li>
            </ul>
          </article>
        </div>
      </div>
    </section>

    <!-- CTA -->
    <section class="cta-section">
      <div class="pub-cont cta-inner">
        <h2 class="cta-title">هل أنت عضو في الاتحاد؟</h2>
        <p class="cta-sub">سجّل الدخول للاستفادة من جميع الخدمات والتواصل مع فريق الدعم</p>
        <div class="cta-actions">
          <RouterLink to="/landing" class="cta-btn-p">تسجيل الدخول</RouterLink>
          <RouterLink to="/contact-us" class="cta-btn-s">تواصل معنا</RouterLink>
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

.services-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1.4rem; }
.svc-card {
  position: relative; overflow: hidden; display: flex; flex-direction: column;
  background: linear-gradient(165deg, var(--bg) 0%, #fff 62%);
  border: 1.5px solid color-mix(in srgb, var(--c) 18%, #fff);
  border-radius: 18px; padding: 1.4rem 1.3rem 1.3rem;
  box-shadow: 0 6px 20px rgba(13, 27, 75, .06);
  transition: transform .25s, box-shadow .25s;
}
.svc-card:hover { transform: translateY(-5px); box-shadow: 0 16px 34px rgba(13, 27, 75, .13); }
.svc-corner {
  position: absolute; top: 0; left: 0; width: 90px; height: 90px; pointer-events: none;
  background: color-mix(in srgb, var(--c) 14%, #fff);
  clip-path: polygon(0 0, 100% 0, 0 100%);
  border-top-left-radius: 18px; opacity: .75;
}
.svc-head { position: relative; display: flex; align-items: center; justify-content: space-between; gap: .75rem; margin-bottom: 1rem; }
.svc-title { font-family: 'Tajawal', 'Cairo', sans-serif; font-size: 1.1rem; font-weight: 800; color: var(--c); line-height: 1.4; }
.svc-icon {
  flex-shrink: 0; width: 52px; height: 52px; border-radius: 50%;
  background: color-mix(in srgb, var(--c) 14%, #fff); color: var(--c);
  display: flex; align-items: center; justify-content: center;
}
.svc-desc { position: relative; font-size: .8rem; color: #4b5563; line-height: 1.85; margin-bottom: 1rem; }
.svc-features { position: relative; list-style: none; display: flex; flex-direction: column; gap: .45rem; margin-bottom: 1.25rem; flex: 1; }
.svc-features li { display: flex; align-items: center; gap: .5rem; font-size: .8rem; color: #374151; }
.svc-check { flex-shrink: 0; color: var(--c); }

.cta-section { background: linear-gradient(145deg, #0d1b4b, #1a237e); padding: 4rem 0; }
.cta-inner { text-align: center; }
.cta-title { font-family: 'Tajawal', 'Cairo', sans-serif; font-size: 1.75rem; font-weight: 900; color: #fff; margin-bottom: .65rem; }
.cta-sub { font-size: 1rem; color: rgba(255,255,255,.7); margin-bottom: 2rem; }
.cta-actions { display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap; }
.cta-btn-p { display: inline-flex; align-items: center; background: linear-gradient(135deg,#f9a825,#e65100); color: #fff; border-radius: 10px; padding: .875rem 2rem; font-size: .95rem; font-weight: 800; text-decoration: none; font-family: 'Tajawal', sans-serif; transition: all .25s; }
.cta-btn-p:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(249,168,37,.4); }
.cta-btn-s { display: inline-flex; align-items: center; background: rgba(255,255,255,.15); color: #fff; border: 1.5px solid rgba(255,255,255,.4); border-radius: 10px; padding: .875rem 2rem; font-size: .95rem; font-weight: 700; text-decoration: none; font-family: 'Tajawal', sans-serif; transition: all .25s; }
.cta-btn-s:hover { background: rgba(255,255,255,.25); transform: translateY(-2px); }

@media (max-width: 1100px) { .services-grid { grid-template-columns: repeat(3,1fr); } }
@media (max-width: 800px) { .services-grid { grid-template-columns: repeat(2,1fr); } }
@media (max-width: 500px) { .services-grid { grid-template-columns: 1fr; } }
</style>

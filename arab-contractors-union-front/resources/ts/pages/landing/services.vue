<script setup lang="ts">
import { ClipboardList, Award, FileText, Scale, GraduationCap, Handshake, ChartBar, Megaphone, ChevronLeft, Check } from 'lucide-vue-next'

definePage({ meta: { layout: 'landing', public: true, unauthenticatedOnly: false } })

const services = [
  {
    icon: ClipboardList, color: '#1a237e', bg: '#e8eaf6',
    title: 'تسجيل العضوية',
    desc: 'انضم إلى اتحاد المقاولين الفلسطينيين وابدأ الاستفادة من جميع المزايا والخدمات المتاحة لأعضاء الاتحاد.',
    features: ['تسجيل إلكتروني أو يدوي','مراجعة المستندات','إصدار بطاقة عضوية','الوصول لجميع الخدمات'],
    duration: '٥-٧ أيام عمل', fee: 'حسب الدرجة',
  },
  {
    icon: Award, color: '#c62828', bg: '#fce4ec',
    title: 'تجديد العضوية',
    desc: 'جدّد عضويتك في الاتحاد بسهولة للاستمرار في الاستفادة من الخدمات والتمثيل الرسمي.',
    features: ['تجديد إلكتروني أو حضوري','مراجعة بيانات الشركة','إصدار شهادة تجديد','إشعار قبل الانتهاء'],
    duration: '٢-٣ أيام عمل', fee: 'حسب الدرجة',
  },
  {
    icon: FileText, color: '#2e7d32', bg: '#e8f5e9',
    title: 'إصدار شهادات التصنيف',
    desc: 'احصل على شهادة تصنيف معتمدة معترف بها محلياً ودولياً في جميع درجات التصنيف من A1 إلى E.',
    features: ['تقييم الكفاءة الفنية والمالية','فحص المشاريع المنجزة','شهادة معترف بها دولياً','صلاحية سنة قابلة للتجديد'],
    duration: '١٠-١٤ يوم عمل', fee: 'حسب الدرجة',
  },
  {
    icon: Scale, color: '#e65100', bg: '#fff8e1',
    title: 'الاستشارات القانونية',
    desc: 'فريق متخصص من المحامين والخبراء القانونيين لحماية حقوقك التعاقدية وتقديم الاستشارات اللازمة.',
    features: ['مراجعة العقود والاتفاقيات','متابعة المستحقات','الاستشارة في النزاعات','الدعم أمام المحاكم'],
    duration: 'حسب الطلب', fee: 'مجاني للأعضاء',
  },
  {
    icon: GraduationCap, color: '#6a1b9a', bg: '#f3e5f5',
    title: 'التدريب والتأهيل',
    desc: 'برامج تدريبية متخصصة في إدارة المشاريع والسلامة والفيديك وتسعير المشاريع لرفع كفاءة القطاع.',
    features: ['برامج معتمدة دولياً','مدربون معتمدون','شهادات معترف بها','دورات حضورية وإلكترونية'],
    duration: 'يومان — أسبوع', fee: 'مخفّض للأعضاء',
  },
  {
    icon: Handshake, color: '#00695c', bg: '#e0f2f1',
    title: 'التحكيم وتسوية النزاعات',
    desc: 'لجنة تحكيم متخصصة من خبراء قانونيين وهندسيين لحل النزاعات بين المقاولين والجهات المختلفة.',
    features: ['لجنة تحكيم معتمدة','إجراءات سريعة وفعّالة','قرارات ملزمة','حلول ودية في أغلب الحالات'],
    duration: 'حسب القضية', fee: 'رمزي للأعضاء',
  },
  {
    icon: Megaphone, color: '#0277bd', bg: '#e3f2fd',
    title: 'العطاءات',
    desc: 'اطلع على آخر العطاءات الحكومية المتاحة للشركات الأعضاء في الاتحاد.',
    features: ['تحديث يومي للعطاءات','تصفية حسب التخصص','تنبيهات المواعيد','دعم في تحضير العروض'],
    duration: 'مستمر', fee: 'مجاني للأعضاء',
  },
  {
    icon: ChartBar, color: '#558b2f', bg: '#f1f8e9',
    title: 'الدراسات والتقارير',
    desc: 'تقارير ودراسات تحليلية دورية عن قطاع المقاولات الفلسطيني لدعم القرار وتوجيه الاستثمار.',
    features: ['تقارير ربع سنوية','تحليل السوق والمنافسة','إحصائيات القطاع','دراسات مخصصة بالطلب'],
    duration: 'دورية أو بطلب', fee: 'مجاني للأعضاء',
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
    </div>

    <section class="pub-section">
      <div class="pub-cont">
        <div class="services-grid">
          <div v-for="s in services" :key="s.title" class="service-detail-card">
            <div class="sdc-header" :style="`background:${s.bg};border-color:${s.color}`">
              <div class="sdc-icon" :style="`background:${s.color}20;color:${s.color};border-color:${s.color}`">
                <component :is="s.icon" :size="28" />
              </div>
              <h3 class="sdc-title" :style="`color:${s.color}`">{{ s.title }}</h3>
            </div>
            <div class="sdc-body">
              <p class="sdc-desc">{{ s.desc }}</p>
              <ul class="sdc-features">
                <li v-for="f in s.features" :key="f"><Check :size="13" class="sdc-check" :style="`color:${s.color}`" /> {{ f }}</li>
              </ul>
              <div class="sdc-meta">
                <div class="sdc-meta-item">
                  <span class="sdc-meta-label">المدة المتوقعة</span>
                  <span class="sdc-meta-val">{{ s.duration }}</span>
                </div>
                <div class="sdc-meta-item">
                  <span class="sdc-meta-label">الرسوم</span>
                  <span class="sdc-meta-val">{{ s.fee }}</span>
                </div>
              </div>
              <button class="sdc-btn" :style="`background:${s.color}`">
                طلب الخدمة <ChevronLeft :size="14" />
              </button>
            </div>
          </div>
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

.services-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1.5rem; }
.service-detail-card { border: 1.5px solid #e5e7eb; border-radius: 18px; overflow: hidden; transition: all .3s; display: flex; flex-direction: column; }
.service-detail-card:hover { box-shadow: 0 12px 36px rgba(0,0,0,.1); transform: translateY(-6px); }
.sdc-header { padding: 1.75rem 1.5rem; border-bottom: 1.5px solid; display: flex; flex-direction: column; gap: .875rem; }
.sdc-icon { width: 54px; height: 54px; border-radius: 14px; border: 1.5px solid; display: flex; align-items: center; justify-content: center; }
.sdc-title { font-family: 'Neo Sans Arabic', 'Cairo', sans-serif; font-size: 1rem; font-weight: 800; }
.sdc-body { padding: 1.5rem; flex: 1; display: flex; flex-direction: column; gap: .875rem; }
.sdc-desc { font-size: .85rem; color: #4b5563; line-height: 1.8; }
.sdc-features { list-style: none; display: flex; flex-direction: column; gap: .4rem; }
.sdc-features li { display: flex; align-items: center; gap: .5rem; font-size: .82rem; color: #374151; }
.sdc-check { flex-shrink: 0; }
.sdc-meta { display: flex; gap: 1rem; padding: .875rem; background: #f9fafb; border-radius: 10px; border: 1px solid #e5e7eb; }
.sdc-meta-item { flex: 1; }
.sdc-meta-label { display: block; font-size: .72rem; color: #9ca3af; margin-bottom: .2rem; }
.sdc-meta-val { font-size: .82rem; font-weight: 700; color: #374151; }
.sdc-btn { display: flex; align-items: center; justify-content: center; gap: .4rem; width: 100%; color: #fff; border: none; border-radius: 10px; padding: .75rem; font-size: .88rem; font-weight: 700; cursor: pointer; font-family: 'Neo Sans Arabic', 'Tajawal', sans-serif; transition: all .2s; margin-top: auto; }
.sdc-btn:hover { filter: brightness(1.08); transform: translateY(-1px); }

.cta-section { background: linear-gradient(145deg, #0d1b4b, #1a237e); padding: 4rem 0; }
.cta-inner { text-align: center; }
.cta-title { font-family: 'Neo Sans Arabic', 'Cairo', sans-serif; font-size: 1.75rem; font-weight: 900; color: #fff; margin-bottom: .65rem; }
.cta-sub { font-size: 1rem; color: rgba(255,255,255,.7); margin-bottom: 2rem; }
.cta-actions { display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap; }
.cta-btn-p { display: inline-flex; align-items: center; background: linear-gradient(135deg,#f9a825,#e65100); color: #fff; border-radius: 10px; padding: .875rem 2rem; font-size: .95rem; font-weight: 800; text-decoration: none; font-family: 'Neo Sans Arabic', 'Tajawal', sans-serif; transition: all .25s; }
.cta-btn-p:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(249,168,37,.4); }
.cta-btn-s { display: inline-flex; align-items: center; background: rgba(255,255,255,.15); color: #fff; border: 1.5px solid rgba(255,255,255,.4); border-radius: 10px; padding: .875rem 2rem; font-size: .95rem; font-weight: 700; text-decoration: none; font-family: 'Neo Sans Arabic', 'Tajawal', sans-serif; transition: all .25s; }
.cta-btn-s:hover { background: rgba(255,255,255,.25); transform: translateY(-2px); }

@media (max-width: 1100px) { .services-grid { grid-template-columns: repeat(3,1fr); } }
@media (max-width: 800px) { .services-grid { grid-template-columns: repeat(2,1fr); } }
@media (max-width: 500px) { .services-grid { grid-template-columns: 1fr; } }
</style>

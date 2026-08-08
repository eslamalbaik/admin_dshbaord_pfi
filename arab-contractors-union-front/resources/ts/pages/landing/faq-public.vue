<script setup lang="ts">
import { ref } from 'vue'
import { ChevronDown, HelpCircle, Search } from 'lucide-vue-next'

definePage({ meta: { layout: 'landing', public: true, unauthenticatedOnly: false } })

const searchQ = ref('')
const openId = ref<number | null>(null)

const faqs = [
  {
    id: 1, cat: 'العضوية',
    q: 'كيف يمكنني التسجيل في اتحاد المقاولين الفلسطينيين؟',
    a: 'يمكنك التسجيل عبر زيارة أي فرع من فروع الاتحاد في محافظتك، أو من خلال البوابة الإلكترونية. تحتاج إلى تقديم سجل تجاري ساري المفعول، شهادة ضريبية، شهادة تأمين، ومستندات الملكية أو الإدارة للشركة.',
  },
  {
    id: 2, cat: 'العضوية',
    q: 'ما هي رسوم العضوية في الاتحاد؟',
    a: 'تختلف رسوم العضوية حسب درجة تصنيف الشركة. تتراوح بين ٥٠٠ دولار للشركات الصغيرة (درجة E) وحتى ٣٠٠٠ دولار للشركات الكبرى (درجة A1). لمزيد من التفاصيل تواصل مع أقرب فرع.',
  },
  {
    id: 3, cat: 'العضوية',
    q: 'ما هي مزايا الانتساب للاتحاد؟',
    a: 'يستفيد أعضاء الاتحاد من: شهادة تصنيف معترف بها، الاستشارات القانونية المجانية، الأولوية في العطاءات الحكومية، الخصومات على برامج التدريب، والتمثيل الرسمي أمام الجهات الرسمية.',
  },
  {
    id: 4, cat: 'التصنيف',
    q: 'ما هي درجات التصنيف المعتمدة في الاتحاد؟',
    a: 'يعتمد الاتحاد ٦ درجات للتصنيف: A1 (أولى أ)، A2 (أولى ب)، B (ثانية)، C (ثالثة)، D (رابعة)، E (خامسة). تُحدَّد الدرجة بناءً على الملاءة المالية، الكوادر الفنية، والمشاريع المنجزة.',
  },
  {
    id: 5, cat: 'التصنيف',
    q: 'كيف يمكنني رفع درجة التصنيف؟',
    a: 'لرفع درجة التصنيف يجب: إتمام المدة المحددة في الدرجة الحالية، تقديم ملف يثبت المشاريع المنجزة وفق الحد الأدنى المطلوب، وإثبات الكوادر الفنية والملاءة المالية وفق شروط الدرجة الجديدة.',
  },
  {
    id: 6, cat: 'التصنيف',
    q: 'هل شهادة التصنيف معترف بها دولياً؟',
    a: 'نعم، شهادات التصنيف الصادرة عن اتحاد المقاولين الفلسطينيين معترف بها في إطار اتفاقيات التبادل مع اتحاد المقاولين العرب والدولي، وتُقبل في أغلب العطاءات التي تموّلها الجهات الدولية.',
  },
  {
    id: 7, cat: 'العطاءات',
    q: 'كيف يمكنني الاطلاع على العطاءات المتاحة؟',
    a: 'يمكنك الاطلاع على أحدث العطاءات عبر صفحة "العطاءات" في موقعنا. يتم تحديثها بشكل يومي ويمكنك التصفية حسب القطاع والموقع وحالة العطاء.',
  },
  {
    id: 8, cat: 'العطاءات',
    q: 'هل يساعد الاتحاد في تحضير عروض العطاءات؟',
    a: 'يقدم الاتحاد استشارات وإرشادات حول إعداد العروض الفنية والمالية، كما ينظم دورات تدريبية متخصصة في إعداد العطاءات وفق أحدث المعايير.',
  },
  {
    id: 9, cat: 'التدريب',
    q: 'هل الدورات التدريبية متاحة للغير أعضاء؟',
    a: 'نعم، الدورات التدريبية متاحة للجميع لكن بأسعار مختلفة؛ يستفيد الأعضاء من خصم يصل إلى ٣٠٪ على جميع الدورات، بالإضافة إلى أولوية الحجز.',
  },
  {
    id: 10, cat: 'التدريب',
    q: 'هل شهادات التدريب معتمدة دولياً؟',
    a: 'بعض الدورات تمنح شهادات معتمدة دولياً من جهات مثل PMI وFIDIC، بينما الدورات الأخرى تمنح شهادات الاتحاد المعترف بها محلياً. يُشار بوضوح لنوع الاعتماد عند وصف كل دورة.',
  },
  {
    id: 11, cat: 'الدعم والشكاوى',
    q: 'كيف يمكنني تقديم شكوى أو نزاع؟',
    a: 'يمكنك تقديم شكوى من خلال تعبئة نموذج "تقديم شكوى" المتوفر في مكتبة الملفات، وتسليمه لأي فرع. تتولى لجنة التحكيم وتسوية النزاعات دراسة الشكوى خلال ٣٠ يوم عمل.',
  },
  {
    id: 12, cat: 'الدعم والشكاوى',
    q: 'كيف يمكنني التواصل مع الاتحاد في حالة الطوارئ؟',
    a: 'للحالات الطارئة يمكنك التواصل عبر خط الدعم الفوري على الرقم +970 59 000 0000 أو عبر البريد الإلكتروني urgent@pcu.ps. نستهدف الرد خلال ٢٤ ساعة في أيام العمل.',
  },
]

const categories = [...new Set(faqs.map(f => f.cat))]
const activecat = ref('الكل')

const filteredFaqs = ref(faqs).value.filter ? faqs : faqs

function getFiltered() {
  return faqs.filter(f => {
    const catOk = activecat.value === 'الكل' || f.cat === activecat.value
    const searchOk = !searchQ.value || f.q.includes(searchQ.value) || f.a.includes(searchQ.value)
    return catOk && searchOk
  })
}

import { computed } from 'vue'
const faqList = computed(getFiltered)

function toggle(id: number) { openId.value = openId.value === id ? null : id }
</script>

<template>
  <div dir="rtl" class="pub-page">
    
    <div class="page-hero">
      <div class="page-hero-shapes"><div class="ph-s ph-s1" /><div class="ph-s ph-s2" /></div>
      <div class="pub-cont page-hero-inner">
        <div class="page-breadcrumb"><RouterLink to="/landing">الرئيسية</RouterLink><span>/</span><span>الأسئلة الشائعة</span></div>
        <h1 class="page-hero-title">الأسئلة الشائعة</h1>
        <p class="page-hero-desc">إجابات على أكثر الأسئلة شيوعاً حول الاتحاد وخدماته</p>
      </div>
    </div>

    <section class="pub-section">
      <div class="pub-cont faq-layout">
        <!-- Sidebar -->
        <div class="faq-sidebar">
          <div class="search-wrap">
            <Search :size="15" class="search-ico" />
            <input v-model="searchQ" type="text" placeholder="ابحث في الأسئلة..." class="search-input" />
          </div>
          <div class="faq-cats">
            <button
              v-for="cat in ['الكل', ...categories]" :key="cat"
              class="faq-cat-btn" :class="{ active: activecat === cat }"
              @click="activecat = cat"
            >
              {{ cat }}
            </button>
          </div>
          <div class="faq-cta-box">
            <HelpCircle :size="32" style="color:#f9a825;margin-bottom:.75rem" />
            <h4>لم تجد إجابتك؟</h4>
            <p>تواصل معنا مباشرة وسنساعدك</p>
            <RouterLink to="/contact-us" class="faq-contact-btn">تواصل معنا</RouterLink>
          </div>
        </div>

        <!-- FAQ List -->
        <div class="faq-list">
          <p class="faq-count">{{ faqList.length }} سؤال</p>
          <div v-for="f in faqList" :key="f.id" class="faq-item">
            <button class="faq-question" @click="toggle(f.id)" :class="{ open: openId === f.id }">
              <span class="faq-q-text">{{ f.q }}</span>
              <div class="faq-chevron"><ChevronDown :size="18" /></div>
            </button>
            <div class="faq-answer" :class="{ open: openId === f.id }">
              <div class="faq-answer-inner">
                <span class="faq-cat-tag">{{ f.cat }}</span>
                <p>{{ f.a }}</p>
              </div>
            </div>
          </div>
          <div v-if="!faqList.length" class="faq-empty">
            <HelpCircle :size="48" style="color:#c5cae9" />
            <p>لا توجد نتائج مطابقة</p>
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

.faq-layout { display: grid; grid-template-columns: 260px 1fr; gap: 2.5rem; align-items: start; }
.faq-sidebar { position: sticky; top: 120px; display: flex; flex-direction: column; gap: 1.25rem; }

.search-wrap { position: relative; }
.search-ico { position: absolute; top: 50%; right: .875rem; transform: translateY(-50%); color: #9ca3af; }
.search-input { width: 100%; border: 1.5px solid #e5e7eb; border-radius: 10px; padding: .65rem 2.5rem .65rem 1rem; font-size: .87rem; font-family: 'Neo Sans Arabic', 'Tajawal', sans-serif; color: #374151; background: #fff; outline: none; transition: border-color .2s; }
.search-input:focus { border-color: #1a237e; }

.faq-cats { display: flex; flex-direction: column; gap: .3rem; }
.faq-cat-btn { padding: .6rem .875rem; border: none; border-radius: 9px; font-size: .85rem; font-weight: 600; cursor: pointer; font-family: 'Neo Sans Arabic', 'Tajawal', sans-serif; color: #6b7280; background: transparent; transition: all .2s; text-align: right; }
.faq-cat-btn.active { background: #1a237e; color: #fff; }
.faq-cat-btn:hover:not(.active) { background: #e8eaf6; color: #1a237e; }

.faq-cta-box { background: linear-gradient(145deg,#0d1b4b,#1a237e); border-radius: 16px; padding: 1.5rem; text-align: center; }
.faq-cta-box h4 { font-family: 'Neo Sans Arabic', 'Cairo', sans-serif; font-size: .9rem; font-weight: 800; color: #fff; margin-bottom: .35rem; }
.faq-cta-box p { font-size: .8rem; color: rgba(255,255,255,.6); margin-bottom: 1rem; }
.faq-contact-btn { display: inline-block; background: linear-gradient(135deg,#f9a825,#e65100); color: #fff; border-radius: 9px; padding: .6rem 1.25rem; font-size: .85rem; font-weight: 700; text-decoration: none; font-family: 'Neo Sans Arabic', 'Tajawal', sans-serif; }

.faq-count { font-size: .85rem; color: #9ca3af; margin-bottom: 1.25rem; }
.faq-list { display: flex; flex-direction: column; gap: .75rem; }
.faq-item { border: 1.5px solid #e5e7eb; border-radius: 14px; overflow: hidden; transition: box-shadow .2s; }
.faq-item:has(.faq-question.open) { border-color: #1a237e; box-shadow: 0 4px 16px rgba(26,35,126,.1); }

.faq-question { width: 100%; display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding: 1.25rem 1.5rem; background: #fff; border: none; cursor: pointer; font-family: 'Neo Sans Arabic', 'Cairo', sans-serif; font-size: .95rem; font-weight: 700; color: #0d1b3e; text-align: right; transition: background .2s; }
.faq-question.open { background: #e8eaf6; color: #1a237e; }
.faq-question:hover:not(.open) { background: #f9fafb; }
.faq-q-text { flex: 1; }
.faq-chevron { width: 30px; height: 30px; background: #f3f4f6; border-radius: 8px; display: flex; align-items: center; justify-content: center; transition: all .3s; flex-shrink: 0; color: #6b7280; }
.faq-question.open .faq-chevron { background: #1a237e; color: #fff; transform: rotate(-180deg); }

.faq-answer { max-height: 0; overflow: hidden; transition: max-height .4s ease; }
.faq-answer.open { max-height: 400px; }
.faq-answer-inner { padding: 1.25rem 1.5rem; border-top: 1px solid #e5e7eb; background: #f9fafb; }
.faq-cat-tag { display: inline-block; background: #e8eaf6; color: #1a237e; border-radius: 50px; padding: .15rem .75rem; font-size: .75rem; font-weight: 700; margin-bottom: .875rem; }
.faq-answer-inner p { font-size: .9rem; color: #4b5563; line-height: 1.9; }

.faq-empty { text-align: center; padding: 3rem 2rem; color: #9ca3af; }
.faq-empty p { font-size: 1rem; margin-top: 1rem; }

@media (max-width: 900px) { .faq-layout { grid-template-columns: 1fr; } .faq-sidebar { position: static; } .faq-cats { flex-direction: row; flex-wrap: wrap; } }
</style>

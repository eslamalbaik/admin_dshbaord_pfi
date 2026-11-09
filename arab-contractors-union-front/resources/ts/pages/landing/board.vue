<script setup lang="ts">
import PublicSocialFollow from '@/components/PublicSocialFollow.vue'
import { Building2 } from 'lucide-vue-next'

definePage({ meta: { layout: 'landing', public: true, unauthenticatedOnly: false } })

// المصدر: التقرير الإداري لاتحاد المقاولين (المحافظات الجنوبية) وكشف أعضاء مجلس الإدارة
const boardMembers = [
  {
    name: 'م. سهيل هاشم السقا',
    position: 'نقيب المقاولين الفلسطينيين — المحافظات الجنوبية',
    subPosition: 'النائب الأول لرئيس الاتحاد في فلسطين',
    company: 'شركة سكلز اند كواليتي للانشاءات',
    bio: 'رئيس مجلس إدارة شركة سكلز اند كواليتي للانشاءات ونقيب المقاولين الفلسطينيين.',
    photo: '/images/board/saqqa.jpg',
  },
  {
    name: 'م. هاشم عرفة سكيك',
    position: 'أمين سر الاتحاد العام',
    company: 'شركة بالكون للتجارة والمقاولات',
    bio: 'رئيس مجلس إدارة شركة بالكون ورئيس مجلس إدارة شركة إمكانات، وعضو مجلس بلدي لبلدية غزة.',
    photo: '/images/board/sakik.jpg',
  },
  {
    name: 'حسين محمد شنن',
    position: 'أمين الصندوق',
    company: 'شركة أبناء شنن للمقاولات',
    bio: 'رئيس مجلس إدارة أبناء شنن ورئيس مجلس إدارة مجموعة شنن.',
    photo: '/images/board/shanan.jpg',
  },
  {
    name: 'عبد الحكيم إسماعيل',
    position: 'عضو مجلس إدارة',
    company: 'شركة عبد الحكيم إسماعيل',
    bio: 'عضو مجلس إدارة جمعية رجال الأعمال ورئيس مجلس إدارة شركة عبد الحكيم.',
    photo: '/images/board/ismail.jpg',
  },
  {
    name: 'م. عارف محمود أبو عودة',
    position: 'عضو مجلس إدارة',
    company: 'شركة التأسيس للتجارة والمقاولات العامة',
    bio: 'رئيس مجلس إدارة شركة التأسيس ورئيس مجلس إدارة شركة أرض الوفاق.',
    photo: '/images/board/abu-oda.jpg',
  },
  {
    name: 'محروس سلمان أبو رجيلة',
    position: 'عضو مجلس إدارة',
    company: 'شركة الرحاب للمقاولات العامة',
    bio: 'رئيس مجلس إدارة شركة الرحاب.',
    photo: '/images/board/abu-rajila.jpg',
  },
  {
    name: 'عماد عبد الله أبو مطر',
    position: 'عضو مجلس إدارة',
    company: 'شركة زهرة الوادي للتجارة والمقاولات',
    bio: 'رئيس مجلس إدارة شركة زهرة الوادي.',
    photo: '/images/board/abu-matar.jpg',
  },
]
</script>

<template>
  <div dir="rtl" class="pub-page">

    <!-- Hero -->
    <div class="page-hero">
      <div class="page-hero-shapes"><div class="ph-s ph-s1" /><div class="ph-s ph-s2" /></div>
      <div class="pub-cont page-hero-inner">
        <div class="page-breadcrumb">
          <RouterLink to="/landing">الرئيسية</RouterLink><span>/</span><span>مجلس الإدارة</span>
        </div>
        <h1 class="page-hero-title">مجلس الإدارة</h1>
        <p class="page-hero-desc">مجلس إدارة اتحاد المقاولين الفلسطينيين — المحافظات الجنوبية (الدورة الانتخابية الثانية عشرة 2023 – 2025)</p>
      </div>
      <div class="pub-follow-slot"><PublicSocialFollow /></div>
    </div>

    <section class="pub-section">
      <div class="pub-cont">
        <!-- النقيب -->
        <div class="president-card">
          <img :src="boardMembers[0].photo" :alt="boardMembers[0].name" class="pres-avatar">
          <div class="pres-body">
            <div class="pub-sec-label" style="margin-bottom:.5rem">{{ boardMembers[0].position }}</div>
            <h2 class="pres-name">{{ boardMembers[0].name }}</h2>
            <p class="pres-sub">{{ boardMembers[0].subPosition }}</p>
            <p class="pres-bio">{{ boardMembers[0].bio }}</p>
            <div class="pres-contacts">
              <span class="pres-contact"><Building2 :size="14" /> {{ boardMembers[0].company }}</span>
            </div>
          </div>
        </div>

        <h3 class="board-section-title">أعضاء مجلس الإدارة</h3>
        <div class="board-grid">
          <div v-for="m in boardMembers.slice(1)" :key="m.name" class="board-card">
            <img :src="m.photo" :alt="m.name" class="board-avatar">
            <div class="board-position">{{ m.position }}</div>
            <h3 class="board-name">{{ m.name }}</h3>
            <p class="board-bio">{{ m.bio }}</p>
            <div class="board-city"><Building2 :size="12" /> {{ m.company }}</div>
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
.pub-sec-label { display: inline-block; background: #e8eaf6; color: #1a237e; border: 1px solid #c5cae9; border-radius: 50px; padding: .28rem 1.1rem; font-size: .77rem; font-weight: 700; letter-spacing: .05em; margin-bottom: .65rem; }
.page-hero { background: linear-gradient(145deg, #0d1b4b 0%, #1a237e 60%, #283593 100%); margin-top: calc(-1 * var(--pub-nav-h, 114px)); padding: calc(var(--pub-nav-h, 114px) + 2.5rem) 0 3rem; position: relative; overflow: hidden; }
.page-hero-shapes { position: absolute; top: 0; right: 0; bottom: 0; left: 0; pointer-events: none; }
.ph-s { position: absolute; border-radius: 50%; }
.ph-s1 { width: 400px; height: 400px; background: radial-gradient(circle, rgba(249,168,37,.12), transparent 65%); top: -100px; left: 5%; }
.ph-s2 { width: 300px; height: 300px; background: radial-gradient(circle, rgba(255,255,255,.05), transparent 65%); bottom: -80px; right: 10%; }
.page-hero-inner { position: relative; z-index: 2; }
.page-breadcrumb { display: flex; align-items: center; gap: .5rem; font-size: .82rem; color: rgba(255,255,255,.6); margin-bottom: 1rem; }
.page-breadcrumb a { color: rgba(255,255,255,.6); text-decoration: none; }
.page-breadcrumb a:hover { color: #f9a825; }
.page-hero-title { font-family: 'Tajawal', 'Cairo', sans-serif; font-size: clamp(2rem,4vw,3rem); font-weight: 900; color: #fff; margin-bottom: .75rem; }
.page-hero-desc { font-size: 1rem; color: rgba(255,255,255,.75); line-height: 1.75; max-width: 600px; }

/* President Card */
.president-card { display: flex; gap: 2.5rem; align-items: flex-start; background: linear-gradient(145deg, #e8eaf6, #f5f7ff); border: 2px solid #c5cae9; border-right: 6px solid #1a237e; border-radius: 20px; padding: 2.5rem; margin-bottom: 3.5rem; }
.pres-avatar { width: 150px; height: 150px; border-radius: 50%; object-fit: cover; object-position: top; flex-shrink: 0; box-shadow: 0 8px 24px rgba(0,0,0,.2); border: 4px solid #fff; }
.pres-sub { font-size: .95rem; color: #1a237e; font-weight: 700; margin-bottom: .6rem; }
.pres-name { font-family: 'Tajawal', 'Cairo', sans-serif; font-size: 1.5rem; font-weight: 900; color: #0d1b3e; margin-bottom: .75rem; }
.pres-bio { font-size: .95rem; color: #4b5563; line-height: 1.85; margin-bottom: 1.25rem; }
.pres-contacts { display: flex; gap: 1rem; flex-wrap: wrap; }
.pres-contact { display: flex; align-items: center; gap: .4rem; color: #1a237e; font-size: .85rem; font-weight: 600; text-decoration: none; transition: color .2s; }
.pres-contact:hover { color: #f9a825; }

/* Board Grid */
.board-section-title { font-family: 'Tajawal', 'Cairo', sans-serif; font-size: 1.25rem; font-weight: 800; color: #0d1b3e; margin-bottom: 2rem; padding-bottom: .75rem; border-bottom: 2px solid #e5e7eb; }
.board-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.5rem; }
.board-card { background: #fff; border: 1.5px solid #e5e7eb; border-radius: 18px; padding: 2rem 1.5rem; text-align: center; transition: all .3s; }
.board-card:hover { box-shadow: 0 12px 32px rgba(13,27,75,.1); transform: translateY(-5px); border-color: #c5cae9; }
.board-avatar { width: 104px; height: 104px; border-radius: 50%; object-fit: cover; object-position: top; display: block; margin: 0 auto 1rem; box-shadow: 0 4px 16px rgba(0,0,0,.15); border: 3px solid #fff; }
.board-position { display: inline-block; background: #e8eaf6; color: #1a237e; border-radius: 50px; padding: .25rem .9rem; font-size: .76rem; font-weight: 700; margin-bottom: .65rem; }
.board-name { font-family: 'Tajawal', 'Cairo', sans-serif; font-size: .95rem; font-weight: 800; color: #0d1b3e; margin-bottom: .6rem; }
.board-bio { font-size: .82rem; color: #6b7280; line-height: 1.75; margin-bottom: .875rem; }
.board-city { font-size: .78rem; color: #9ca3af; display: flex; align-items: center; justify-content: center; gap: .3rem; margin-bottom: 1rem; }
.board-contacts { display: flex; justify-content: center; gap: .65rem; }
.board-contact-btn { width: 36px; height: 36px; border-radius: 9px; display: flex; align-items: center; justify-content: center; text-decoration: none; transition: all .2s; }
.board-contact-btn.phone { background: #e8f5e9; color: #2e7d32; border: 1.5px solid #a5d6a7; }
.board-contact-btn.mail { background: #e8eaf6; color: #1a237e; border: 1.5px solid #9fa8da; }
.board-contact-btn:hover { transform: scale(1.1); }

@media (max-width: 1024px) { .board-grid { grid-template-columns: repeat(2,1fr); } }
@media (max-width: 768px) { .president-card { flex-direction: column; align-items: center; text-align: center; } .pres-contacts { justify-content: center; } .board-grid { grid-template-columns: repeat(2,1fr); } }
@media (max-width: 480px) { .board-grid { grid-template-columns: 1fr; } }
</style>

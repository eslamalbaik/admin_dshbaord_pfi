<script setup lang="ts">
import PublicSocialFollow from '@/components/PublicSocialFollow.vue'
import { Building2, Mail, Phone } from 'lucide-vue-next'

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
    phone: '0599413071',
    email: 'suhailalsaqqa@gmail.com',
  },
  {
    name: 'م. هاشم عرفة سكيك',
    position: 'أمين سر الاتحاد العام',
    company: 'شركة بالكون للتجارة والمقاولات',
    bio: 'رئيس مجلس إدارة شركة بالكون ورئيس مجلس إدارة شركة إمكانات، وعضو مجلس بلدي لبلدية غزة.',
    photo: '/images/board/sakik.jpg',
    phone: '0599745910',
    email: 'palcon_2005@yahoo.com',
  },
  {
    name: 'حسين محمد شنن',
    position: 'أمين الصندوق',
    company: 'شركة أبناء شنن للمقاولات',
    bio: 'رئيس مجلس إدارة أبناء شنن ورئيس مجلس إدارة مجموعة شنن.',
    photo: '/images/board/shanan.jpg',
    phone: '0594446665',
    email: 'sons.4@hotmail.com',
  },
  {
    name: 'عبد الحكيم إسماعيل',
    position: 'عضو مجلس إدارة',
    company: 'شركة عبد الحكيم إسماعيل',
    bio: 'عضو مجلس إدارة جمعية رجال الأعمال ورئيس مجلس إدارة شركة عبد الحكيم.',
    photo: '/images/board/ismail.jpg',
    phone: '0599400590',
    email: 'aico.pcu@gmail.com',
  },
  {
    name: 'م. عارف محمود أبو عودة',
    position: 'عضو مجلس إدارة',
    company: 'شركة التأسيس للتجارة والمقاولات العامة',
    bio: 'رئيس مجلس إدارة شركة التأسيس ورئيس مجلس إدارة شركة أرض الوفاق.',
    photo: '/images/board/abu-oda.jpg',
    phone: '0599407275',
    email: 'altaasis.co@gmail.com',
  },
  {
    name: 'محروس سلمان أبو رجيلة',
    position: 'عضو مجلس إدارة',
    company: 'شركة الرحاب للمقاولات العامة',
    bio: 'رئيس مجلس إدارة شركة الرحاب.',
    photo: '/images/board/abu-rajila.jpg',
    phone: '0599440220',
    email: 'mcomp@hotmail.com',
  },
  {
    name: 'عماد عبد الله أبو مطر',
    position: 'عضو مجلس إدارة',
    company: 'شركة زهرة الوادي للتجارة والمقاولات',
    bio: 'رئيس مجلس إدارة شركة زهرة الوادي.',
    photo: '/images/board/abu-matar.jpg',
    phone: '0599147766',
    email: 'emadabumatar04@gmail.com',
  },
]
</script>

<template>
  <div dir="rtl" class="board-page">

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

    <div class="board-wrap">
      <!-- بطاقة الرئيس -->
      <section class="pres-card">
        <svg class="pres-skyline" viewBox="0 0 260 150" fill="none" aria-hidden="true">
          <g fill="#dbe4f5">
            <rect x="10" y="70" width="26" height="80" rx="2" />
            <rect x="44" y="30" width="30" height="120" rx="2" />
            <rect x="82" y="55" width="24" height="95" rx="2" />
            <rect x="114" y="10" width="34" height="140" rx="2" />
            <rect x="156" y="45" width="26" height="105" rx="2" />
            <rect x="190" y="75" width="28" height="75" rx="2" />
            <rect x="226" y="95" width="24" height="55" rx="2" />
          </g>
          <g fill="#f3f6fc">
            <rect x="50" y="42" width="5" height="5" /><rect x="60" y="42" width="5" height="5" />
            <rect x="50" y="58" width="5" height="5" /><rect x="60" y="58" width="5" height="5" />
            <rect x="122" y="24" width="5" height="5" /><rect x="134" y="24" width="5" height="5" />
            <rect x="122" y="42" width="5" height="5" /><rect x="134" y="42" width="5" height="5" />
            <rect x="122" y="60" width="5" height="5" /><rect x="134" y="60" width="5" height="5" />
          </g>
        </svg>

        <div class="pres-photo">
          <img :src="boardMembers[0].photo" :alt="boardMembers[0].name">
        </div>

        <div class="pres-body">
          <span class="pres-pill">رئيس مجلس الإدارة</span>
          <h1 class="pres-name">{{ boardMembers[0].name }}</h1>
          <h2 class="pres-title">{{ boardMembers[0].subPosition }}</h2>
          <p class="pres-bio">{{ boardMembers[0].bio }}</p>

          <div class="pres-contacts">
            <span class="pres-contact"><Building2 :size="15" /> {{ boardMembers[0].company }}</span>
            <a :href="`tel:${boardMembers[0].phone}`" class="pres-contact"><Phone :size="15" /> <span dir="ltr">{{ boardMembers[0].phone }}</span></a>
            <a :href="`mailto:${boardMembers[0].email}`" class="pres-contact"><Mail :size="15" /> {{ boardMembers[0].email }}</a>
          </div>
        </div>
      </section>

      <!-- عنوان الأعضاء -->
      <div class="members-heading">
        <span class="mh-line" />
        <h3>أعضاء مجلس الإدارة</h3>
        <span class="mh-line" />
      </div>

      <!-- بطاقات الأعضاء -->
      <div class="members-grid">
        <article v-for="m in boardMembers.slice(1)" :key="m.email" class="m-card">
          <img :src="m.photo" :alt="m.name" class="m-photo">
          <span class="m-pill">{{ m.position }}</span>
          <h4 class="m-name">{{ m.name }}</h4>
          <p class="m-bio">{{ m.bio }}</p>
          <a :href="`tel:${m.phone}`" class="m-contact"><Phone :size="13" /> {{ m.phone }}</a>
          <a :href="`mailto:${m.email}`" class="m-contact"><Mail :size="13" /> {{ m.email }}</a>
          <span class="m-corner" />
        </article>
      </div>
    </div>
  </div>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800;900&family=Tajawal:wght@400;500;700;800&display=swap');

.board-page {
  font-family: 'Tajawal', 'Cairo', sans-serif;
  direction: rtl;
  color: #4b5563;
  background: linear-gradient(180deg, #eef3fb 0%, #f7f9fd 100%);
  min-height: 100vh;
  padding: 0 0 4rem;
}
.board-wrap { max-width: 1100px; margin: 0 auto; padding: 3rem 1.25rem 0; }
.pub-cont { max-width: 1240px; margin: 0 auto; padding: 0 1.5rem; }

/* Hero */
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

/* بطاقة الرئيس */
.pres-card {
  position: relative;
  display: flex;
  align-items: center;
  gap: 2.25rem;
  background: #fff;
  border-radius: 24px;
  padding: 1.5rem 1.75rem;
  box-shadow: 0 10px 40px rgba(26, 35, 126, .08);
  overflow: hidden;
}
.pres-skyline { position: absolute; left: 1.5rem; bottom: 0; width: 260px; opacity: .9; pointer-events: none; }
.pres-photo {
  position: relative; z-index: 1;
  width: 215px; height: 235px; flex-shrink: 0;
  border-radius: 20px; overflow: hidden;
  background: linear-gradient(160deg, #dfe6f4, #c4d0e8);
  box-shadow: 0 8px 24px rgba(13, 27, 75, .15);
}
.pres-photo img { width: 100%; height: 100%; object-fit: cover; object-position: top; display: block; }
.pres-body { position: relative; z-index: 1; flex: 1; min-width: 0; }
.pres-pill {
  display: inline-block; background: #e8eefb; color: #2b4a8b;
  border-radius: 50px; padding: .3rem 1.1rem; font-size: .8rem; font-weight: 700; margin-bottom: .9rem;
}
.pres-name { font-family: 'Cairo', sans-serif; font-size: 2rem; font-weight: 900; color: #14275f; line-height: 1.3; }
.pres-title { font-size: 1.05rem; font-weight: 800; color: #14275f; margin: .35rem 0 .6rem; }
.pres-bio { font-size: .92rem; color: #5b6475; line-height: 1.8; margin-bottom: 1.1rem; }
.pres-contacts { display: flex; flex-wrap: wrap; gap: .5rem 1.4rem; }
.pres-contact {
  display: inline-flex; align-items: center; gap: .4rem;
  font-size: .82rem; font-weight: 600; color: #2b4a8b; text-decoration: none;
}
.pres-contact:hover { color: #f9a825; }

/* عنوان الأعضاء */
.members-heading { display: flex; align-items: center; gap: 1.25rem; margin: 2.75rem 0 2rem; }
.members-heading h3 { font-family: 'Cairo', sans-serif; font-size: 1.6rem; font-weight: 900; color: #14275f; white-space: nowrap; }
.mh-line { flex: 1; height: 2px; background: linear-gradient(to left, transparent, #c9d5ee); border-radius: 2px; }
.mh-line:last-child { background: linear-gradient(to right, transparent, #c9d5ee); }

/* الأعضاء */
.members-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.5rem; }
.m-card {
  position: relative; overflow: hidden;
  background: #fff; border-radius: 18px;
  padding: 1.5rem 1.25rem 1.6rem;
  text-align: center;
  display: flex; flex-direction: column; align-items: center;
  box-shadow: 0 6px 24px rgba(26, 35, 126, .07);
  transition: transform .25s, box-shadow .25s;
}
.m-card:hover { transform: translateY(-4px); box-shadow: 0 14px 34px rgba(26, 35, 126, .13); }
.m-photo {
  width: 74px; height: 74px; border-radius: 50%; object-fit: cover; object-position: top;
  border: 3px solid #e6ecf8; background: #eef2fa; margin-bottom: .6rem;
}
.m-pill {
  display: inline-block; background: #f1f3f7; color: #4b5563;
  border-radius: 50px; padding: .18rem .85rem; font-size: .7rem; font-weight: 600; margin-bottom: .55rem;
}
.m-name { font-family: 'Cairo', sans-serif; font-size: 1rem; font-weight: 800; color: #14275f; margin-bottom: .45rem; }
.m-bio { font-size: .76rem; color: #6b7280; line-height: 1.75; margin-bottom: .9rem; flex: 1; }
.m-contact {
  display: inline-flex; align-items: center; justify-content: center; gap: .4rem;
  width: 100%; max-width: 210px;
  background: #f4f6fa; border-radius: 8px; padding: .3rem .6rem; margin-top: .4rem;
  font-size: .74rem; font-weight: 600; color: #14275f; text-decoration: none; direction: ltr;
  overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}
.m-contact:hover { background: #e8eefb; }
.m-corner {
  position: absolute; left: 0; bottom: 0; width: 46px; height: 46px;
  background: linear-gradient(135deg, transparent 50%, #f9c846 50%);
  border-bottom-left-radius: 18px;
}

@media (max-width: 1000px) { .pres-skyline { display: none; } }
@media (max-width: 900px) {
  .members-grid { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 640px) {
  .pres-card { flex-direction: column; text-align: center; }
  .pres-contacts { justify-content: center; }
  .pres-photo { width: 180px; height: 200px; }
  .members-grid { grid-template-columns: 1fr; }
  .members-heading h3 { font-size: 1.25rem; }
}
</style>

<script setup lang="ts">
import PublicSocialFollow from '@/components/PublicSocialFollow.vue'
import { onBeforeUnmount, onMounted, ref } from 'vue'
import { Building2, Info, Mail, Phone, X } from 'lucide-vue-next'

definePage({ meta: { layout: 'landing', public: true, unauthenticatedOnly: false } })

// المصدر: التقرير الإداري لاتحاد المقاولين (المحافظات الجنوبية) وكشف أعضاء مجلس الإدارة
interface BoardMember {
  name: string; position: string; subPosition?: string; company: string; bio: string
  details?: string // تعريف موسّع 5–7 أسطر (يُضاف لاحقاً من الاتحاد)
  photo: string; phone: string; email: string
}
const boardMembers: BoardMember[] = [
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

// نافذة التعريف بالعضو: بتعرض details (5–7 أسطر) لو موجودة، وإلا النبذة المختصرة bio
const selected = ref<BoardMember | null>(null)
function openMember(m: BoardMember) {
  selected.value = m
  document.body.style.overflow = 'hidden'
}
function closeMember() {
  selected.value = null
  document.body.style.overflow = ''
}
function onKey(e: KeyboardEvent) { if (e.key === 'Escape' && selected.value) closeMember() }
onMounted(() => window.addEventListener('keydown', onKey))
onBeforeUnmount(() => { window.removeEventListener('keydown', onKey); document.body.style.overflow = '' })
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

          <button type="button" class="info-btn" aria-label="التعريف بالعضو" @click="openMember(boardMembers[0])"><Info :size="16" /> التعريف بالعضو</button>

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
          <button type="button" class="info-btn info-btn-sm" aria-label="التعريف بالعضو" @click="openMember(m)"><Info :size="15" /> التعريف بالعضو</button>
          <span class="m-corner" />
        </article>
      </div>
    </div>

    <!-- نافذة التعريف بالعضو -->
    <Teleport to="body">
      <div v-if="selected" class="bm-overlay" dir="rtl" role="dialog" aria-modal="true" :aria-label="selected.name" @click.self="closeMember">
        <div class="bm-dialog">
          <div class="bm-head">
            <button type="button" class="bm-close" aria-label="إغلاق" @click="closeMember"><X :size="18" /></button>
          </div>
          <div class="bm-body">
            <img :src="selected.photo" :alt="selected.name" class="bm-photo">
            <h3 class="bm-name">{{ selected.name }}</h3>
            <span class="bm-pill">{{ selected.position || 'رئيس مجلس الإدارة' }}</span>
            <p v-if="selected.subPosition" class="bm-sub">{{ selected.subPosition }}</p>
            <p v-if="selected.company" class="bm-company"><Building2 :size="15" /> {{ selected.company }}</p>

            <div class="bm-about">
              <h4 class="bm-about-title">نبذة تعريفية</h4>
              <p class="bm-text">{{ selected.details || selected.bio }}</p>
            </div>

            <div v-if="selected.phone || selected.email" class="bm-contacts">
              <a v-if="selected.phone" :href="`tel:${selected.phone}`" class="bm-contact" dir="ltr"><Phone :size="15" /> {{ selected.phone }}</a>
              <a v-if="selected.email" :href="`mailto:${selected.email}`" class="bm-contact" dir="ltr"><Mail :size="15" /> {{ selected.email }}</a>
            </div>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<style scoped>

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
.page-hero { background: linear-gradient(145deg, var(--pcu-brand-deep) 0%, var(--pcu-brand) 60%, var(--pcu-brand-soft) 100%); margin-top: calc(-1 * var(--pub-nav-h, 114px)); padding: calc(var(--pub-nav-h, 114px) + 2.5rem) 0 3rem; position: relative; overflow: hidden; }
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
  gap: 2rem;
  background: linear-gradient(100deg, #e4ecf9 0%, #f1f6fd 50%, #fbfdff 100%);
  border: 1px solid #dde6f5;
  border-radius: 22px;
  padding: 1.1rem 1.4rem 1.1rem 1.75rem;
  box-shadow: 0 10px 36px rgba(var(--pcu-brand-rgb), .09);
  overflow: hidden;
}
.pres-skyline { position: absolute; left: 1rem; bottom: 0; width: 330px; opacity: .55; pointer-events: none; }
.pres-photo {
  position: relative; z-index: 1;
  width: 200px; height: 215px; flex-shrink: 0;
  border-radius: 18px; overflow: hidden;
  background: linear-gradient(160deg, #dfe6f4, #c4d0e8);
  box-shadow: 0 8px 22px rgba(var(--pcu-brand-deep-rgb), .18);
}
.pres-photo img { width: 100%; height: 100%; object-fit: cover; object-position: top; display: block; }
.pres-body { position: relative; z-index: 1; flex: 1; min-width: 0; }
.pres-pill {
  display: inline-block; background: #d9e5f8; color: #2b4a8b;
  border-radius: 50px; padding: .28rem 1.1rem; font-size: .78rem; font-weight: 700; margin-bottom: .7rem;
}
.pres-name { font-family: 'Cairo', sans-serif; font-size: 2rem; font-weight: 900; color: #14275f; line-height: 1.25; }
.pres-title { font-size: 1.05rem; font-weight: 800; color: #14275f; margin: .3rem 0 .45rem; }
.pres-bio { font-size: .88rem; color: #5b6475; line-height: 1.75; margin-bottom: .85rem; }
.pres-contacts { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: .5rem 1.4rem; padding-top: .8rem; border-top: 1px solid rgba(43, 74, 139, .14); }
.pres-contact {
  display: inline-flex; align-items: center; gap: .45rem;
  font-size: .8rem; font-weight: 600; color: #2b4a8b; text-decoration: none;
}
.pres-contact svg { flex-shrink: 0; color: #4a6cb3; }
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
  box-shadow: 0 6px 24px rgba(var(--pcu-brand-rgb), .07);
  transition: transform .25s, box-shadow .25s;
}
.m-card:hover { transform: translateY(-4px); box-shadow: 0 14px 34px rgba(var(--pcu-brand-rgb), .13); }
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
.m-corner { position: absolute; left: 0; bottom: 0; width: 64px; height: 64px; pointer-events: none; }
/* ركن ذهبي منحنٍ بطبقتين: قوس شفاف كبير + ربع دائرة متدرّج أصغر */
.m-corner::before,
.m-corner::after { content: ''; position: absolute; left: 0; bottom: 0; border-top-right-radius: 100%; }
.m-corner::before { width: 64px; height: 64px; background: linear-gradient(45deg, rgba(249, 200, 70, .28), rgba(249, 200, 70, 0)); }
.m-corner::after { width: 38px; height: 38px; background: linear-gradient(45deg, #f5a623, #f9d56a); box-shadow: 0 -2px 10px rgba(245, 166, 35, .35); }

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

.info-btn { display: inline-flex; align-items: center; gap: .4rem; border: 1.5px solid #c5cae9; background: #f5f7ff; color: var(--pcu-brand-ink); border-radius: 50px; padding: .4rem 1rem; font-size: .82rem; font-weight: 700; font-family: inherit; cursor: pointer; margin: .5rem 0; transition: all .2s; }
.info-btn:hover { background: var(--pcu-brand); border-color: var(--pcu-brand); color: #fff; }
.info-btn-sm { padding: .3rem .8rem; font-size: .78rem; }
.bm-overlay { position: fixed; inset: 0; z-index: 2000; background: rgba(var(--pcu-brand-rgb), .6); display: grid; place-items: center; padding: 1rem; font-family: 'Tajawal', 'Cairo', sans-serif; }
.bm-dialog { position: relative; background: #fff; border-radius: 22px; width: min(540px, 100%); max-height: 90vh; overflow-y: auto; text-align: center; box-shadow: 0 24px 60px rgba(0, 0, 0, .3); }
.bm-head { height: 110px; background: linear-gradient(145deg, var(--pcu-brand-deep) 0%, var(--pcu-brand) 60%, var(--pcu-brand-soft) 100%); position: relative; }
.bm-head::after { content: ''; position: absolute; inset: 0; background: radial-gradient(circle at 80% 20%, rgba(249, 168, 37, .22), transparent 55%); }
.bm-close { position: absolute; z-index: 2; top: .9rem; inset-inline-start: .9rem; width: 34px; height: 34px; border-radius: 50%; border: 0; background: rgba(255, 255, 255, .18); color: #fff; cursor: pointer; display: grid; place-items: center; transition: background .2s; }
.bm-close:hover { background: rgba(255, 255, 255, .32); }
.bm-body { padding: 0 1.75rem 1.75rem; display: flex; flex-direction: column; align-items: center; }
.bm-photo { width: 130px; height: 130px; border-radius: 50%; object-fit: cover; object-position: top; border: 5px solid #fff; box-shadow: 0 8px 22px rgba(var(--pcu-brand-deep-rgb), .25); margin-top: -65px; background: #eef2fa; position: relative; z-index: 1; }
.bm-name { font-family: 'Cairo', sans-serif; font-size: 1.45rem; font-weight: 900; color: #0d1b3e; margin: .8rem 0 .5rem; }
.bm-pill { display: inline-block; background: #fff4dc; color: #b26a00; border: 1px solid #ffe0a3; border-radius: 50px; padding: .25rem 1rem; font-size: .8rem; font-weight: 700; }
.bm-sub { font-size: .82rem; color: #6b7280; margin-top: .45rem; }
.bm-company { display: inline-flex; align-items: center; gap: .4rem; background: #f4f6fa; color: #4b5563; border-radius: 50px; padding: .3rem .9rem; font-size: .86rem; margin-top: .7rem; }
.bm-about { width: 100%; margin-top: 1.25rem; padding-top: 1.1rem; border-top: 1px solid #e8ecf5; text-align: start; }
.bm-about-title { font-size: .95rem; font-weight: 800; color: var(--pcu-brand-ink); margin-bottom: .5rem; display: flex; align-items: center; gap: .5rem; }
.bm-about-title::before { content: ''; width: 4px; height: 16px; border-radius: 2px; background: #f9a825; }
.bm-text { color: #4b5563; font-size: .96rem; line-height: 2; white-space: pre-line; }
.bm-contacts { width: 100%; display: flex; flex-wrap: wrap; gap: .6rem; margin-top: 1.1rem; }
.bm-contact { flex: 1 1 200px; display: inline-flex; align-items: center; justify-content: center; gap: .45rem; background: #f5f7ff; border: 1px solid #dde3f5; border-radius: 10px; padding: .5rem .8rem; font-size: .82rem; font-weight: 600; color: var(--pcu-brand-ink); text-decoration: none; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; transition: all .2s; }
.bm-contact:hover { background: var(--pcu-brand); border-color: var(--pcu-brand); color: #fff; }
</style>

<script setup lang="ts">
import { Mail, Phone, MapPin } from 'lucide-vue-next'

definePage({ meta: { layout: 'landing', public: true, unauthenticatedOnly: false } })

const boardMembers = [
  {
    name: 'م. محمد أحمد الشيخ',
    position: 'رئيس الاتحاد',
    bio: 'مهندس مدني بخبرة تزيد على ٣٠ عاماً في قطاع المقاولات، ترأّس عدة مشاريع بنية تحتية وطنية كبرى.',
    city: 'رام الله', phone: '+970 59 000 0001', email: 'president@pcu.ps',
    initial: 'م',
  },
  {
    name: 'م. سميرة خالد النمر',
    position: 'نائب الرئيس',
    bio: 'خبرة واسعة في إدارة مشاريع الإسكان وإعادة الإعمار، عضو في عدة هيئات هندسية دولية.',
    city: 'نابلس', phone: '+970 59 000 0002', email: 'vp@pcu.ps',
    initial: 'س',
  },
  {
    name: 'م. يوسف عمر الريماوي',
    position: 'الأمين العام',
    bio: 'متخصص في إدارة المنظمات المهنية وله تجربة طويلة في تطوير أنظمة تصنيف المقاولين.',
    city: 'رام الله', phone: '+970 59 000 0003', email: 'secretary@pcu.ps',
    initial: 'ي',
  },
  {
    name: 'م. إبراهيم سالم الجعبري',
    position: 'أمين الصندوق',
    bio: 'خبير في الإدارة المالية وتمويل مشاريع البنية التحتية، شارك في إعداد الخطط المالية للاتحاد.',
    city: 'الخليل', phone: '+970 59 000 0004', email: 'treasurer@pcu.ps',
    initial: 'إ',
  },
  {
    name: 'م. رنا فارس حمدان',
    position: 'عضو مجلس',
    bio: 'رائدة في قطاع مقاولات الطاقة المتجددة والبنية التحتية الخضراء في فلسطين.',
    city: 'جنين', phone: '+970 59 000 0005', email: 'board1@pcu.ps',
    initial: 'ر',
  },
  {
    name: 'م. طارق نضال القاسم',
    position: 'عضو مجلس',
    bio: 'خبرة في مجال الطرق والجسور، أشرف على تنفيذ عشرات المشاريع الحيوية في الضفة الغربية.',
    city: 'طولكرم', phone: '+970 59 000 0006', email: 'board2@pcu.ps',
    initial: 'ط',
  },
  {
    name: 'م. ليلى حسن أبو عمر',
    position: 'عضو مجلس',
    bio: 'متخصصة في قطاع المياه والصرف الصحي، وشاركت في تصميم وتنفيذ مشاريع مياه حيوية.',
    city: 'بيت لحم', phone: '+970 59 000 0007', email: 'board3@pcu.ps',
    initial: 'ل',
  },
  {
    name: 'م. وليد كمال ذياب',
    position: 'عضو مجلس',
    bio: 'خبير في إدارة مشاريع الكهرباء والاتصالات، ويمثّل قطاع الخليل في مجلس الإدارة.',
    city: 'الخليل', phone: '+970 59 000 0008', email: 'board4@pcu.ps',
    initial: 'و',
  },
]

const colors = ['#1a237e','#c62828','#2e7d32','#e65100','#6a1b9a','#00695c','#0277bd','#558b2f']
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
        <p class="page-hero-desc">تعرّف على أعضاء مجلس إدارة اتحاد المقاولين الفلسطينيين المنتخبين لقيادة القطاع</p>
      </div>
    </div>

    <!-- Board Members -->
    <section class="pub-section">
      <div class="pub-cont">
        <!-- President highlight -->
        <div class="president-card">
          <div class="pres-avatar" :style="`background:${colors[0]}`">{{ boardMembers[0].initial }}</div>
          <div class="pres-body">
            <div class="pub-sec-label" style="margin-bottom:.5rem">رئيس الاتحاد</div>
            <h2 class="pres-name">{{ boardMembers[0].name }}</h2>
            <p class="pres-bio">{{ boardMembers[0].bio }}</p>
            <div class="pres-contacts">
              <a :href="`tel:${boardMembers[0].phone}`" class="pres-contact"><Phone :size="14" /> {{ boardMembers[0].phone }}</a>
              <a :href="`mailto:${boardMembers[0].email}`" class="pres-contact"><Mail :size="14" /> {{ boardMembers[0].email }}</a>
              <span class="pres-contact"><MapPin :size="14" /> {{ boardMembers[0].city }}</span>
            </div>
          </div>
        </div>

        <!-- Other members -->
        <h3 class="board-section-title">أعضاء مجلس الإدارة</h3>
        <div class="board-grid">
          <div v-for="(m, i) in boardMembers.slice(1)" :key="m.name" class="board-card">
            <div class="board-avatar" :style="`background:${colors[(i+1)%colors.length]}`">{{ m.initial }}</div>
            <div class="board-position">{{ m.position }}</div>
            <h3 class="board-name">{{ m.name }}</h3>
            <p class="board-bio">{{ m.bio }}</p>
            <div class="board-city"><MapPin :size="12" /> {{ m.city }}</div>
            <div class="board-contacts">
              <a :href="`tel:${m.phone}`" class="board-contact-btn phone"><Phone :size="13" /></a>
              <a :href="`mailto:${m.email}`" class="board-contact-btn mail"><Mail :size="13" /></a>
            </div>
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
.pub-sec-label { display: inline-block; background: #e8eaf6; color: #1a237e; border: 1px solid #c5cae9; border-radius: 50px; padding: .28rem 1.1rem; font-size: .77rem; font-weight: 700; letter-spacing: .05em; margin-bottom: .65rem; }
.page-hero { background: linear-gradient(145deg, #0d1b4b 0%, #1a237e 60%, #283593 100%); padding: 4rem 0 3rem; position: relative; overflow: hidden; }
.page-hero-shapes { position: absolute; inset: 0; pointer-events: none; }
.ph-s { position: absolute; border-radius: 50%; }
.ph-s1 { width: 400px; height: 400px; background: radial-gradient(circle, rgba(249,168,37,.12), transparent 65%); top: -100px; left: 5%; }
.ph-s2 { width: 300px; height: 300px; background: radial-gradient(circle, rgba(255,255,255,.05), transparent 65%); bottom: -80px; right: 10%; }
.page-hero-inner { position: relative; z-index: 2; }
.page-breadcrumb { display: flex; align-items: center; gap: .5rem; font-size: .82rem; color: rgba(255,255,255,.6); margin-bottom: 1rem; }
.page-breadcrumb a { color: rgba(255,255,255,.6); text-decoration: none; }
.page-breadcrumb a:hover { color: #f9a825; }
.page-hero-title { font-family: 'Neo Sans Arabic', 'Cairo', sans-serif; font-size: clamp(2rem,4vw,3rem); font-weight: 900; color: #fff; margin-bottom: .75rem; }
.page-hero-desc { font-size: 1rem; color: rgba(255,255,255,.75); line-height: 1.75; max-width: 600px; }

/* President Card */
.president-card { display: flex; gap: 2.5rem; align-items: flex-start; background: linear-gradient(145deg, #e8eaf6, #f5f7ff); border: 2px solid #c5cae9; border-right: 6px solid #1a237e; border-radius: 20px; padding: 2.5rem; margin-bottom: 3.5rem; }
.pres-avatar { width: 110px; height: 110px; border-radius: 50%; color: #fff; display: flex; align-items: center; justify-content: center; font-family: 'Neo Sans Arabic', 'Cairo', sans-serif; font-size: 2.5rem; font-weight: 900; flex-shrink: 0; box-shadow: 0 8px 24px rgba(0,0,0,.2); }
.pres-name { font-family: 'Neo Sans Arabic', 'Cairo', sans-serif; font-size: 1.5rem; font-weight: 900; color: #0d1b3e; margin-bottom: .75rem; }
.pres-bio { font-size: .95rem; color: #4b5563; line-height: 1.85; margin-bottom: 1.25rem; }
.pres-contacts { display: flex; gap: 1rem; flex-wrap: wrap; }
.pres-contact { display: flex; align-items: center; gap: .4rem; color: #1a237e; font-size: .85rem; font-weight: 600; text-decoration: none; transition: color .2s; }
.pres-contact:hover { color: #f9a825; }

/* Board Grid */
.board-section-title { font-family: 'Neo Sans Arabic', 'Cairo', sans-serif; font-size: 1.25rem; font-weight: 800; color: #0d1b3e; margin-bottom: 2rem; padding-bottom: .75rem; border-bottom: 2px solid #e5e7eb; }
.board-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1.5rem; }
.board-card { background: #fff; border: 1.5px solid #e5e7eb; border-radius: 18px; padding: 2rem 1.5rem; text-align: center; transition: all .3s; }
.board-card:hover { box-shadow: 0 12px 32px rgba(13,27,75,.1); transform: translateY(-5px); border-color: #c5cae9; }
.board-avatar { width: 76px; height: 76px; border-radius: 50%; color: #fff; display: flex; align-items: center; justify-content: center; font-family: 'Neo Sans Arabic', 'Cairo', sans-serif; font-size: 1.75rem; font-weight: 900; margin: 0 auto 1rem; box-shadow: 0 4px 16px rgba(0,0,0,.15); }
.board-position { display: inline-block; background: #e8eaf6; color: #1a237e; border-radius: 50px; padding: .25rem .9rem; font-size: .76rem; font-weight: 700; margin-bottom: .65rem; }
.board-name { font-family: 'Neo Sans Arabic', 'Cairo', sans-serif; font-size: .95rem; font-weight: 800; color: #0d1b3e; margin-bottom: .6rem; }
.board-bio { font-size: .82rem; color: #6b7280; line-height: 1.75; margin-bottom: .875rem; }
.board-city { font-size: .78rem; color: #9ca3af; display: flex; align-items: center; justify-content: center; gap: .3rem; margin-bottom: 1rem; }
.board-contacts { display: flex; justify-content: center; gap: .65rem; }
.board-contact-btn { width: 36px; height: 36px; border-radius: 9px; display: flex; align-items: center; justify-content: center; text-decoration: none; transition: all .2s; }
.board-contact-btn.phone { background: #e8f5e9; color: #2e7d32; border: 1.5px solid #a5d6a7; }
.board-contact-btn.mail { background: #e8eaf6; color: #1a237e; border: 1.5px solid #9fa8da; }
.board-contact-btn:hover { transform: scale(1.1); }

@media (max-width: 1024px) { .board-grid { grid-template-columns: repeat(3,1fr); } }
@media (max-width: 768px) { .president-card { flex-direction: column; align-items: center; text-align: center; } .pres-contacts { justify-content: center; } .board-grid { grid-template-columns: repeat(2,1fr); } }
@media (max-width: 480px) { .board-grid { grid-template-columns: 1fr; } }
</style>

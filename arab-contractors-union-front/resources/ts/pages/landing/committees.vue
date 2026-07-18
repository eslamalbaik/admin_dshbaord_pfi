<script setup lang="ts">
import { MapPin, Phone, Mail, Users, Building2 } from 'lucide-vue-next'

definePage({ meta: { layout: 'landing', public: true, unauthenticatedOnly: false } })

const branches = [
  { name: 'رام الله والبيرة', members: 320, address: 'رام الله — شارع الإرسال', phone: '+970 2 000 0010', email: 'ramallah@pcu.ps', isHQ: true, color: '#1a237e' },
  { name: 'نابلس',           members: 185, address: 'نابلس — شارع فيصل',      phone: '+970 9 000 0011', email: 'nablus@pcu.ps',    isHQ: false, color: '#c62828' },
  { name: 'الخليل',          members: 210, address: 'الخليل — وسط المدينة',   phone: '+970 2 000 0012', email: 'hebron@pcu.ps',   isHQ: false, color: '#2e7d32' },
  { name: 'بيت لحم',         members: 140, address: 'بيت لحم — بجانب البلدية', phone: '+970 2 000 0013', email: 'bethlehem@pcu.ps', isHQ: false, color: '#e65100' },
  { name: 'جنين',            members: 120, address: 'جنين — شارع الاستقلال', phone: '+970 4 000 0014', email: 'jenin@pcu.ps',    isHQ: false, color: '#6a1b9a' },
  { name: 'طولكرم',          members: 90,  address: 'طولكرم — وسط البلد',    phone: '+970 9 000 0015', email: 'tulkarm@pcu.ps', isHQ: false, color: '#00695c' },
  { name: 'قلقيلية',         members: 65,  address: 'قلقيلية — القدس Street',  phone: '+970 9 000 0016', email: 'qalqilya@pcu.ps', isHQ: false, color: '#0277bd' },
  { name: 'سلفيت',           members: 45,  address: 'سلفيت — مبنى البلدية',  phone: '+970 9 000 0017', email: 'salfit@pcu.ps',  isHQ: false, color: '#558b2f' },
  { name: 'أريحا والأغوار',  members: 55,  address: 'أريحا — شارع الملك',    phone: '+970 2 000 0018', email: 'jericho@pcu.ps', isHQ: false, color: '#f57f17' },
  { name: 'طوباس',           members: 38,  address: 'طوباس — مركز المدينة',   phone: '+970 9 000 0019', email: 'tubas@pcu.ps',   isHQ: false, color: '#ad1457' },
  { name: 'القدس',           members: 110, address: 'القدس — باب العمود',      phone: '+970 2 000 0020', email: 'jerusalem@pcu.ps', isHQ: false, color: '#4e342e' },
]

const committees = [
  { name: 'لجنة التصنيف والمعايير',      desc: 'تتولى وضع معايير التصنيف ومراجعة ملفات الشركات وإصدار شهادات التصنيف.',   members: 7, color: '#1a237e', bg: '#e8eaf6' },
  { name: 'لجنة التشريعات والأنظمة',     desc: 'تعمل على مراجعة وتطوير الأنظمة والقوانين المتعلقة بقطاع المقاولات.',      members: 5, color: '#c62828', bg: '#fce4ec' },
  { name: 'لجنة التدريب وبناء القدرات',  desc: 'تشرف على البرامج التدريبية وتطوير المناهج وإصدار الشهادات.',               members: 6, color: '#2e7d32', bg: '#e8f5e9' },
  { name: 'لجنة التحكيم وتسوية النزاعات', desc: 'تفصل في النزاعات بين الأعضاء والجهات الأخرى وفق أنظمة التحكيم.',         members: 9, color: '#e65100', bg: '#fff8e1' },
  { name: 'لجنة الشراكات والعلاقات',     desc: 'تبني وتطور علاقات الاتحاد مع الجهات المحلية والدولية والمانحين.',          members: 5, color: '#6a1b9a', bg: '#f3e5f5' },
  { name: 'لجنة السلامة والجودة',         desc: 'تضع معايير السلامة المهنية وتراقب تطبيقها في مواقع البناء.',               members: 6, color: '#00695c', bg: '#e0f2f1' },
]
</script>

<template>
  <div dir="rtl" class="pub-page">
    
    <div class="page-hero">
      <div class="page-hero-shapes"><div class="ph-s ph-s1" /><div class="ph-s ph-s2" /></div>
      <div class="pub-cont page-hero-inner">
        <div class="page-breadcrumb">
          <RouterLink to="/landing">الرئيسية</RouterLink><span>/</span><span>اللجان والفروع</span>
        </div>
        <h1 class="page-hero-title">اللجان والفروع</h1>
        <p class="page-hero-desc">شبكة فروع تغطي جميع المحافظات الفلسطينية ولجان متخصصة لخدمة القطاع</p>
      </div>
    </div>

    <!-- Committees -->
    <section class="pub-section">
      <div class="pub-cont">
        <div class="pub-sec-header-c">
          <div class="pub-sec-label">اللجان</div>
          <h2 class="pub-sec-h center">اللجان الدائمة للاتحاد</h2>
          <p style="text-align:center;color:#6b7280;font-size:.95rem;margin-top:.5rem">لجان متخصصة تعمل على تطوير مختلف جوانب قطاع المقاولات</p>
        </div>
        <div class="committees-grid">
          <div v-for="c in committees" :key="c.name" class="committee-card" :style="`--cc:${c.color};--cbg:${c.bg}`">
            <div class="committee-header">
              <h3 class="committee-name">{{ c.name }}</h3>
              <span class="committee-members"><Users :size="13" /> {{ c.members }} أعضاء</span>
            </div>
            <p class="committee-desc">{{ c.desc }}</p>
          </div>
        </div>
      </div>
    </section>

    <!-- Branches -->
    <section class="pub-section bg-soft">
      <div class="pub-cont">
        <div class="pub-sec-header-c">
          <div class="pub-sec-label">الفروع</div>
          <h2 class="pub-sec-h center">فروعنا في المحافظات</h2>
          <p style="text-align:center;color:#6b7280;font-size:.95rem;margin-top:.5rem">{{ branches.length }} فرع يخدم المقاولين في جميع أنحاء فلسطين</p>
        </div>

        <!-- HQ -->
        <div class="hq-card">
          <div class="hq-badge">المقر الرئيسي</div>
          <div class="hq-inner">
            <div class="hq-icon"><Building2 :size="36" /></div>
            <div class="hq-body">
              <h3 class="hq-name">{{ branches[0].name }}</h3>
              <div class="hq-info">
                <span><MapPin :size="14" /> {{ branches[0].address }}</span>
                <a :href="`tel:${branches[0].phone}`"><Phone :size="14" /> {{ branches[0].phone }}</a>
                <a :href="`mailto:${branches[0].email}`"><Mail :size="14" /> {{ branches[0].email }}</a>
              </div>
              <div class="hq-members"><Users :size="14" /> {{ branches[0].members }} عضو مقاول</div>
            </div>
          </div>
        </div>

        <!-- Other branches -->
        <div class="branches-grid">
          <div v-for="b in branches.slice(1)" :key="b.name" class="branch-card" :style="`--bc:${b.color}`">
            <div class="branch-color-bar" />
            <div class="branch-body">
              <h3 class="branch-name">{{ b.name }}</h3>
              <p class="branch-addr"><MapPin :size="12" /> {{ b.address }}</p>
              <a :href="`tel:${b.phone}`" class="branch-phone"><Phone :size="12" /> {{ b.phone }}</a>
              <a :href="`mailto:${b.email}`" class="branch-email"><Mail :size="12" /> {{ b.email }}</a>
              <div class="branch-members"><Users :size="12" /> {{ b.members }} عضو</div>
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
.bg-soft { background: #f5f7ff; }
.pub-sec-label { display: inline-block; background: #e8eaf6; color: #1a237e; border: 1px solid #c5cae9; border-radius: 50px; padding: .28rem 1.1rem; font-size: .77rem; font-weight: 700; letter-spacing: .05em; }
.pub-sec-h { font-family: 'Neo Sans Arabic', 'Cairo', sans-serif; font-size: clamp(1.6rem,3.5vw,2.3rem); font-weight: 900; color: #0d1b3e; padding-bottom: .7rem; position: relative; }
.pub-sec-h::after { content: ''; position: absolute; bottom: 0; right: 0; width: 48px; height: 4px; background: linear-gradient(90deg,#f9a825,#e65100); border-radius: 2px; }
.pub-sec-h.center { text-align: center; }
.pub-sec-h.center::after { right: 50%; transform: translateX(50%); }
.pub-sec-header-c { text-align: center; margin-bottom: 3rem; }
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

.committees-grid { display: grid; grid-template-columns: repeat(3,1fr); gap: 1.5rem; }
.committee-card { background: var(--cbg); border: 1.5px solid var(--cc); border-top: 5px solid var(--cc); border-radius: 16px; padding: 1.75rem; transition: transform .25s, box-shadow .25s; }
.committee-card:hover { transform: translateY(-5px); box-shadow: 0 10px 28px rgba(0,0,0,.1); }
.committee-header { display: flex; align-items: flex-start; justify-content: space-between; gap: .75rem; margin-bottom: .875rem; }
.committee-name { font-family: 'Neo Sans Arabic', 'Cairo', sans-serif; font-size: .95rem; font-weight: 800; color: #0d1b3e; line-height: 1.4; }
.committee-members { display: flex; align-items: center; gap: .3rem; font-size: .76rem; font-weight: 700; color: var(--cc); background: rgba(255,255,255,.7); border: 1px solid var(--cc); border-radius: 50px; padding: .2rem .65rem; white-space: nowrap; flex-shrink: 0; }
.committee-desc { font-size: .85rem; color: #4b5563; line-height: 1.8; }

.hq-card { background: linear-gradient(145deg, #e8eaf6, #f5f7ff); border: 2px solid #c5cae9; border-right: 6px solid #1a237e; border-radius: 20px; padding: 2rem 2.5rem; margin-bottom: 2.5rem; position: relative; }
.hq-badge { position: absolute; top: -12px; right: 1.5rem; background: #1a237e; color: #fff; font-size: .75rem; font-weight: 700; border-radius: 50px; padding: .25rem .875rem; }
.hq-inner { display: flex; gap: 1.75rem; align-items: center; }
.hq-icon { width: 72px; height: 72px; border-radius: 20px; background: #1a237e; color: #fff; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.hq-name { font-family: 'Neo Sans Arabic', 'Cairo', sans-serif; font-size: 1.2rem; font-weight: 900; color: #0d1b3e; margin-bottom: .75rem; }
.hq-info { display: flex; flex-wrap: wrap; gap: 1rem; margin-bottom: .65rem; }
.hq-info span, .hq-info a { display: flex; align-items: center; gap: .4rem; font-size: .85rem; color: #374151; text-decoration: none; font-weight: 500; }
.hq-info a:hover { color: #1a237e; }
.hq-members { display: flex; align-items: center; gap: .4rem; font-size: .85rem; font-weight: 700; color: #1a237e; }

.branches-grid { display: grid; grid-template-columns: repeat(5,1fr); gap: 1rem; }
.branch-card { background: #fff; border: 1.5px solid #e5e7eb; border-radius: 14px; overflow: hidden; transition: all .25s; }
.branch-card:hover { box-shadow: 0 8px 24px rgba(0,0,0,.1); transform: translateY(-4px); }
.branch-color-bar { height: 5px; background: var(--bc); }
.branch-body { padding: 1.25rem 1rem; }
.branch-name { font-family: 'Neo Sans Arabic', 'Cairo', sans-serif; font-size: .9rem; font-weight: 800; color: #0d1b3e; margin-bottom: .65rem; }
.branch-addr, .branch-phone, .branch-email, .branch-members { display: flex; align-items: center; gap: .35rem; font-size: .78rem; color: #6b7280; margin-bottom: .4rem; text-decoration: none; }
.branch-phone:hover, .branch-email:hover { color: var(--bc); }
.branch-members { font-weight: 700; color: var(--bc); margin-top: .65rem; }

@media (max-width: 1100px) { .branches-grid { grid-template-columns: repeat(4,1fr); } }
@media (max-width: 900px) { .committees-grid { grid-template-columns: repeat(2,1fr); } .branches-grid { grid-template-columns: repeat(3,1fr); } }
@media (max-width: 640px) { .committees-grid { grid-template-columns: 1fr; } .branches-grid { grid-template-columns: repeat(2,1fr); } .hq-inner { flex-direction: column; text-align: center; } .hq-info { justify-content: center; } }
</style>

<script setup lang="ts">
import { MapPin, Phone, Mail, Clock, HardHat } from 'lucide-vue-next'

const quickLinks = [
  { label: 'الرئيسية',         href: '/landing' },
  { label: 'عن الاتحاد',       href: '/landing/about' },
  { label: 'مجلس الإدارة',    href: '/landing/board' },
  { label: 'اللجان والفروع',  href: '/landing/committees' },
  { label: 'أعضاء الاتحاد',  href: '/landing/members' },
]

const serviceLinks = [
  { label: 'خدمات الاتحاد',   href: '/landing/services' },
  { label: 'المشاريع والمعارض', href: '/landing/projects' },
  { label: 'التدريب وبناء القدرات', href: '/landing/training-center' },
  { label: 'التشريعات والأنظمة', href: '/landing/legislation' },
  { label: 'مكتبة الملفات',    href: '/landing/library' },
  { label: 'المناقصات والعطاءات', href: '/landing/public-tenders' },
]

const legalLinks = [
  { label: 'الأسئلة الشائعة',  href: '/landing/faq-public' },
  { label: 'تواصل معنا',        href: '/landing/contact-us' },
  { label: 'سياسة الخصوصية',   href: '/landing/privacy-policy' },
  { label: 'الشروط والأحكام',   href: '/landing/terms' },
]
</script>

<template>
  <!-- CTA Banner -->
  <div class="pub-footer-cta-bar">
    <div class="pub-container pub-cta-inner">
      <div>
        <h3 class="pub-cta-title">انضم إلى الاتحاد اليوم</h3>
        <p class="pub-cta-sub">كن جزءاً من أكبر مجتمع مقاولين في فلسطين</p>
      </div>
      <RouterLink to="/landing" class="pub-cta-btn">
        <HardHat :size="17" /> سجّل عضويتك الآن
      </RouterLink>
    </div>
  </div>

  <!-- Main Footer -->
  <footer class="pub-footer">
    <div class="pub-container pub-footer-grid">
      <!-- Brand -->
      <div class="pub-footer-brand">
        <img src="/logo.png" alt="اتحاد المقاولين الفلسطينيين" class="pub-footer-logo" />
        <p class="pub-footer-name">اتحاد المقاولين الفلسطينيين</p>
        <p class="pub-footer-en">Palestinian Contractors Union</p>
        <p class="pub-footer-bio">
          الممثل الرسمي لقطاع المقاولات في فلسطين منذ أكثر من ثلاثة عقود،
          نعمل على تطوير القطاع وحماية حقوق الأعضاء وتعزيز الشراكة مع الجهات الرسمية والدولية.
        </p>
        <div class="pub-footer-social">
          <a href="#" class="pub-fsoc" aria-label="Facebook">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/></svg>
          </a>
          <a href="#" class="pub-fsoc" aria-label="Twitter">𝕏</a>
          <a href="#" class="pub-fsoc" aria-label="LinkedIn">in</a>
          <a href="#" class="pub-fsoc" aria-label="Instagram">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="2" width="20" height="20" rx="5"/><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"/><line x1="17.5" y1="6.5" x2="17.51" y2="6.5"/></svg>
          </a>
        </div>
      </div>

      <!-- Quick Links -->
      <div class="pub-footer-col">
        <h4 class="pub-footer-col-h">روابط الاتحاد</h4>
        <RouterLink v-for="l in quickLinks" :key="l.href" :to="l.href" class="pub-footer-link">{{ l.label }}</RouterLink>
      </div>

      <!-- Services -->
      <div class="pub-footer-col">
        <h4 class="pub-footer-col-h">الخدمات والمحتوى</h4>
        <RouterLink v-for="l in serviceLinks" :key="l.href" :to="l.href" class="pub-footer-link">{{ l.label }}</RouterLink>
      </div>

      <!-- Contact + Legal -->
      <div class="pub-footer-col">
        <h4 class="pub-footer-col-h">تواصل معنا</h4>
        <p class="pub-footer-contact"><MapPin :size="13" class="pub-fco-ico" /> رام الله، فلسطين — شارع الإرسال</p>
        <p class="pub-footer-contact" dir="ltr"><Phone :size="13" class="pub-fco-ico" /> +970 2 000 0000</p>
        <p class="pub-footer-contact"><Mail :size="13" class="pub-fco-ico" /> info@pcu.ps</p>
        <p class="pub-footer-contact"><Clock :size="13" class="pub-fco-ico" /> الأحد — الخميس: ٨ص — ٤م</p>

        <h4 class="pub-footer-col-h" style="margin-top:1.5rem">روابط قانونية</h4>
        <RouterLink v-for="l in legalLinks" :key="l.href" :to="l.href" class="pub-footer-link">{{ l.label }}</RouterLink>
      </div>
    </div>

    <!-- Bottom Bar -->
    <div class="pub-footer-bottom">
      <div class="pub-container pub-footer-bottom-inner">
        <p>جميع الحقوق محفوظة © {{ new Date().getFullYear() }} — اتحاد المقاولين الفلسطينيين</p>
        <p class="pub-footer-dev">منصة رقمية متكاملة</p>
      </div>
    </div>
  </footer>

  <!-- Floating WhatsApp -->
  <a href="https://wa.me/970200000000" class="pub-float-wa" target="_blank" rel="noopener" aria-label="واتساب">
    <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/><path d="M12 0C5.373 0 0 5.373 0 12c0 2.123.558 4.116 1.533 5.845L.073 23.25a.5.5 0 0 0 .613.613l5.405-1.46A11.94 11.94 0 0 0 12 24c6.627 0 12-5.373 12-12S18.627 0 12 0zm0 22a9.94 9.94 0 0 1-5.163-1.444l-.371-.221-3.809 1.03 1.03-3.809-.221-.371A9.94 9.94 0 0 1 2 12C2 6.477 6.477 2 12 2s10 4.477 10 10-4.477 10-10 10z"/></svg>
    <span>واتساب</span>
  </a>
</template>

<style scoped>
@import url('https://fonts.cdnfonts.com/css/neo-sans-arabic');
@import url('https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800;900&family=Tajawal:wght@400;500;700;800&display=swap');
@import url('https://fonts.cdnfonts.com/css/dubai');

footer, .pub-footer-cta-bar, .pub-float-wa { font-family: 'Dubai', 'Neo Sans Arabic', 'Tajawal', 'Neo Sans Arabic', 'Cairo', sans-serif; }
.pub-container { max-width: 1240px; margin: 0 auto; padding: 0 1.5rem; }

.pub-footer-cta-bar {
  background: linear-gradient(135deg, #1a237e, #283593);
  padding: 1.75rem 0; border-bottom: 1px solid rgba(255,255,255,.1);
}
.pub-cta-inner { display: flex; align-items: center; justify-content: space-between; gap: 1.5rem; flex-wrap: wrap; }
.pub-cta-title { font-family: 'Neo Sans Arabic', 'Cairo', sans-serif; font-size: 1.25rem; font-weight: 900; color: #fff; margin-bottom: .2rem; }
.pub-cta-sub { font-size: .85rem; color: rgba(255,255,255,.7); }
.pub-cta-btn {
  display: inline-flex; align-items: center; gap: .5rem;
  background: linear-gradient(135deg, #f9a825, #e65100);
  color: #fff; border: none; border-radius: 10px;
  padding: .8rem 1.75rem; font-size: .9rem; font-weight: 800;
  cursor: pointer; font-family: 'Neo Sans Arabic', 'Tajawal', sans-serif; white-space: nowrap;
  text-decoration: none; transition: all .25s;
  box-shadow: 0 4px 14px rgba(249,168,37,.3);
}
.pub-cta-btn:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(249,168,37,.4); }

.pub-footer { background: #0d1b4b; color: rgba(255,255,255,.75); font-family: 'Neo Sans Arabic', 'Tajawal', sans-serif; }
.pub-footer-grid {
  display: grid; grid-template-columns: 1.7fr 1fr 1fr 1fr;
  gap: 3rem; padding: 3.5rem 0 3rem;
  border-bottom: 1px solid rgba(255,255,255,.08);
}
.pub-footer-logo { height: 64px; width: auto; object-fit: contain; margin-bottom: .75rem; filter: brightness(0) invert(1) opacity(.9); }
.pub-footer-name { font-family: 'Neo Sans Arabic', 'Cairo', sans-serif; font-size: .95rem; font-weight: 900; color: #fff; margin-bottom: .15rem; }
.pub-footer-en { font-size: .65rem; color: rgba(255,255,255,.45); margin-bottom: .875rem; letter-spacing: .04em; }
.pub-footer-bio { font-size: .82rem; color: rgba(255,255,255,.5); line-height: 1.85; margin-bottom: 1.25rem; }
.pub-footer-social { display: flex; gap: .5rem; }
.pub-fsoc {
  width: 34px; height: 34px; border-radius: 8px;
  background: rgba(255,255,255,.1); color: rgba(255,255,255,.65);
  display: flex; align-items: center; justify-content: center;
  font-size: .82rem; text-decoration: none;
  border: 1px solid rgba(255,255,255,.1); transition: all .2s;
}
.pub-fsoc:hover { background: #f9a825; color: #0d1b4b; border-color: #f9a825; }
.pub-footer-col-h {
  font-family: 'Neo Sans Arabic', 'Cairo', sans-serif; font-size: .88rem; font-weight: 800; color: #fff;
  margin-bottom: 1.25rem; padding-bottom: .55rem;
  border-bottom: 2px solid #f9a825; display: inline-block;
}
.pub-footer-link { display: block; color: rgba(255,255,255,.5); text-decoration: none; font-size: .83rem; margin-bottom: .55rem; transition: color .2s; }
.pub-footer-link:hover { color: #f9a825; }
.pub-footer-contact { font-size: .82rem; color: rgba(255,255,255,.5); margin-bottom: .55rem; display: flex; align-items: center; gap: .4rem; line-height: 1.55; }
.pub-fco-ico { flex-shrink: 0; color: #f9a825; }
.pub-footer-bottom { padding: 1.5rem 0; }
.pub-footer-bottom-inner { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: .5rem; }
.pub-footer-bottom-inner p { font-size: .8rem; color: rgba(255,255,255,.35); }
.pub-footer-dev { font-size: .75rem; }

.pub-float-wa {
  position: fixed; bottom: 2rem; left: 2rem; z-index: 500;
  background: #25d366; color: #fff; border-radius: 50px;
  padding: .7rem 1.25rem .7rem .9rem;
  display: flex; align-items: center; gap: .6rem;
  text-decoration: none; font-size: .85rem; font-weight: 700;
  box-shadow: 0 6px 20px rgba(37,211,102,.4); font-family: 'Neo Sans Arabic', 'Tajawal', sans-serif;
  animation: wa-float 3s ease-in-out infinite; border: 2px solid rgba(255,255,255,.3);
}
.pub-float-wa:hover { background: #128c7e; }
@keyframes wa-float { 0%,100%{transform:translateY(0)} 50%{transform:translateY(-6px)} }

@media (max-width: 1024px) {
  .pub-footer-grid { grid-template-columns: 1fr 1fr; gap: 2.5rem; }
}
@media (max-width: 600px) {
  .pub-footer-grid { grid-template-columns: 1fr; gap: 2rem; }
  .pub-cta-inner { flex-direction: column; text-align: center; }
  .pub-float-wa span { display: none; }
  .pub-float-wa { padding: .7rem; border-radius: 50%; }
  .pub-footer-bottom-inner { flex-direction: column; text-align: center; }
}
</style>

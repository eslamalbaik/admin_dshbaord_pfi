<script setup lang="ts">
import { ref } from 'vue'
import { Clock, Mail, MapPin, Phone } from 'lucide-vue-next'

const unionLinks = [
  { label: 'الرئيسية', href: '/landing' },
  { label: 'الخدمات الالكترونية', href: '/landing/services' },
  { label: 'عن الاتحاد', href: '/landing/about' },
  { label: 'المركز الاعلامي', href: '/landing/news' },
  { label: 'تواصل معنا', href: '/landing/contact-us' },
]

const serviceLinks = [
  { label: 'طلب شهادة عضوية', href: '/contractor/login' },
  { label: 'طلب شهادة تصنيف', href: '/contractor/login' },
  { label: 'سوق الاليات والمعدات', href: '/contractor/login' },
  { label: 'بوابة العطاءات', href: '/landing/public-tenders' },
  { label: 'الذمم المالية', href: '/contractor/login' },
]

const legalLinks = [
  { label: 'الاسئلة الشائعة', href: '/landing/faq-public' },
  { label: 'الشروط والاحكام', href: '/landing/terms' },
  { label: 'سياسة الخصوصية', href: '/landing/privacy-policy' },
]

const branches = {
  gaza: { address: 'غزة - الرمال', phone: '+970-592373805' },
  westbank: { address: 'رام الله - شارع الإرسال', phone: '+970-2-2987654' },
}
const branch = ref<keyof typeof branches>('gaza')
</script>

<template>
  <footer class="pub-footer">
    <div class="pub-container pub-footer-grid">
      <div class="pub-footer-brand">
        <div class="pub-footer-id">
          <img src="/images/landing/logo-white.png" alt="اتحاد المقاولين الفلسطينيين" class="pub-footer-logo">
          <div>
            <p class="pub-footer-name">اتحاد المقاولين الفلسطينيين</p>
            <p class="pub-footer-en">Palestinian Contractor<br>Union - PCU</p>
          </div>
        </div>
        <p class="pub-footer-bio">
          الممثل الرسمي لقطاع المقاولات في فلسطين منذ أكثر من ثلاثة عقود، نعمل على تطوير القطاع وحماية حقوق الأعضاء.
        </p>
        <div class="pub-footer-social">
          <a href="#" class="pub-fsoc" aria-label="Facebook">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z" /></svg>
          </a>
          <a href="#" class="pub-fsoc" aria-label="Instagram">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="3" width="18" height="18" rx="5" /><circle cx="12" cy="12" r="4" /><circle cx="17.5" cy="6.5" r=".8" fill="currentColor" /></svg>
          </a>
          <a href="#" class="pub-fsoc" aria-label="LinkedIn">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 9h4v11H4zM6 4.5a1.5 1.5 0 1 1 0 3 1.5 1.5 0 0 1 0-3zM11 9h3.8v1.6c.6-1 1.8-1.9 3.6-1.9 3.4 0 3.6 2.2 3.6 5V20h-4v-5.3c0-1.3 0-2.9-1.8-2.9S14 13.2 14 14.6V20h-3z" /></svg>
          </a>
          <a href="#" class="pub-fsoc" aria-label="X">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M17.8 3h3.1l-6.8 7.7L22 21h-6.2l-4.9-6.3L5.3 21H2.2l7.2-8.3L2 3h6.3l4.4 5.8zm-1.1 16.2h1.7L7.4 4.7H5.6z" /></svg>
          </a>
        </div>
      </div>

      <nav class="pub-footer-col" aria-label="روابط الاتحاد">
        <h4 class="pub-footer-col-h">روابط الاتحاد</h4>
        <RouterLink v-for="l in unionLinks" :key="l.label" :to="l.href" class="pub-footer-link">{{ l.label }}</RouterLink>
      </nav>

      <nav class="pub-footer-col" aria-label="الخدمات الالكترونية">
        <h4 class="pub-footer-col-h">الخدمات الالكترونية</h4>
        <RouterLink v-for="l in serviceLinks" :key="l.label" :to="l.href" class="pub-footer-link">{{ l.label }}</RouterLink>
      </nav>

      <div class="pub-footer-col pub-footer-contact-col">
        <h4 class="pub-footer-col-h">تواصل معنا</h4>
        <div class="pub-branch-toggle" role="tablist">
          <button :class="{ active: branch === 'gaza' }" @click="branch = 'gaza'">غزة</button>
          <button :class="{ active: branch === 'westbank' }" @click="branch = 'westbank'">الضفة</button>
        </div>
        <p class="pub-footer-contact"><span class="pub-fco-ico"><MapPin :size="20" /></span>{{ branches[branch].address }}</p>
        <p class="pub-footer-contact"><span class="pub-fco-ico"><Phone :size="20" /></span><span dir="ltr">{{ branches[branch].phone }}</span></p>
        <p class="pub-footer-contact"><span class="pub-fco-ico"><Mail :size="20" /></span>info.pcu@gmail.com</p>
        <p class="pub-footer-contact"><span class="pub-fco-ico"><Clock :size="20" /></span>الاحد - الخميس : 8ص - 4م</p>
      </div>
    </div>

    <div class="pub-container">
      <div class="pub-footer-bottom">
        <p>جميع الحقوق محفوظة © {{ new Date().getFullYear() }} – اتحاد المقاولين الفلسطينيين</p>
        <p class="pub-footer-legal">
          <template v-for="(l, i) in legalLinks" :key="l.href">
            <span v-if="i" class="sep">|</span>
            <RouterLink :to="l.href">{{ l.label }}</RouterLink>
          </template>
        </p>
      </div>
    </div>
  </footer>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800;900&display=swap');

.pub-footer { background: #000068; color: #fff; font-family: 'Tajawal', 'Cairo', sans-serif; }
.pub-footer p { margin: 0; }
.pub-container { max-width: 1200px; margin: 0 auto; padding: 0 24px; }
.pub-footer-grid { display: grid; grid-template-columns: minmax(0, 1.45fr) minmax(0, .8fr) minmax(0, .9fr) minmax(0, 1fr); gap: 40px; padding: 40px 24px 30px; }

.pub-footer-id { display: flex; align-items: center; gap: 18px; margin-bottom: 20px; }
.pub-footer-logo { height: 84px; width: auto; }
.pub-footer-name { font-size: 1.15rem; font-weight: 500; margin-bottom: 8px !important; }
.pub-footer-en { font-size: 1.05rem; color: rgba(255, 255, 255, .85); direction: ltr; text-align: left; line-height: 1.5; }
.pub-footer-bio { font-size: 1.15rem; line-height: 1.75; margin-bottom: 26px !important; }
.pub-footer-social { display: flex; gap: 14px; justify-content: flex-end; }
.pub-fsoc {
  width: 36px; height: 36px; border-radius: 8px; border: 1px solid rgba(255, 255, 255, .25);
  display: grid; place-items: center; color: #fff; text-decoration: none; transition: background .2s;
}
.pub-fsoc:hover { background: rgba(255, 255, 255, .12); }

.pub-footer-col { display: flex; flex-direction: column; gap: 18px; padding-top: 22px; }
.pub-footer-col-h {
  margin: 0 0 4px; font-size: 1.05rem; font-weight: 600; color: #fff; position: relative; padding-bottom: 10px; align-self: flex-start;
}
.pub-footer-col-h::after { content: ''; position: absolute; bottom: 0; right: 0; width: 26px; height: 1.5px; background: #d67a00; }
.pub-footer-link { color: #fff; text-decoration: none; font-size: .95rem; transition: color .2s; }
.pub-footer-link:hover { color: #ffbe1a; }

.pub-footer-contact-col { padding-top: 0; gap: 14px; align-items: flex-start; }
.pub-branch-toggle { display: flex; border: 1px solid rgba(255, 255, 255, .5); border-radius: 18px; padding: 4px; }
.pub-branch-toggle button {
  border: none; background: transparent; color: #fff; font-family: inherit; font-size: 1rem; font-weight: 600;
  padding: 8px 28px; border-radius: 14px; cursor: pointer;
}
.pub-branch-toggle button.active { background: #d67a00; }
.pub-footer-contact { display: flex; align-items: center; gap: 12px; font-size: .92rem; font-weight: 500; }
.pub-fco-ico {
  width: 34px; height: 34px; border-radius: 8px; background: rgba(255, 255, 255, .08); border: 1px solid rgba(255, 255, 255, .15);
  display: grid; place-items: center; color: #d67a00; flex-shrink: 0;
}

.pub-footer-bottom {
  border-top: 1px solid rgba(255, 255, 255, .3); padding: 20px 0 26px;
  display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; font-size: .92rem;
}
.pub-footer-legal { display: flex; align-items: center; gap: 8px; }
.pub-footer-legal a { color: #fff; text-decoration: underline; text-underline-offset: 4px; }
.pub-footer-legal .sep { color: rgba(255, 255, 255, .6); }

@media (max-width: 1000px) {
  .pub-footer-grid { grid-template-columns: 1fr 1fr; }
}
@media (max-width: 600px) {
  .pub-footer-grid { grid-template-columns: 1fr; gap: 28px; }
  .pub-footer-social { justify-content: flex-start; }
  .pub-footer-bottom { flex-direction: column; text-align: center; }
}
</style>

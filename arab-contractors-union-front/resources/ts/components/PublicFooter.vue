<script setup lang="ts">
import PublicSocialIcon from '@/components/PublicSocialIcon.vue'
import { PUBLIC_SOCIAL_LINKS } from '@/utils/publicSocialLinks'
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
  { label: 'التحقق من شهادة', href: '/landing/verify-certificate' },
  { label: 'الذمم المالية', href: '/contractor/login' },
]

const legalLinks = [
  { label: 'الاسئلة الشائعة', href: '/landing/faq-public' },
  { label: 'الشروط والاحكام', href: '/landing/terms' },
  { label: 'سياسة الخصوصية', href: '/landing/privacy-policy' },
]

const branches = {
  gaza: { address: 'غزة - الرمال', phone: '0597233422' },
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
          <a v-for="s in PUBLIC_SOCIAL_LINKS" :key="s.key" :href="s.href" target="_blank" rel="noopener noreferrer" class="pub-fsoc" :aria-label="s.label">
            <PublicSocialIcon :name="s.key" :size="20" />
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
        <p class="pub-footer-contact"><span class="pub-fco-ico"><Mail :size="20" /></span>info@pcu.org.ps</p>
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
.pub-footer-social { display: flex; gap: 14px; justify-content: flex-start; }
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

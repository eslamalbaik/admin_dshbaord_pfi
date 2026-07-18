<script setup lang="ts">
import { ref } from 'vue'
import { MapPin, Phone, Mail, Clock, Send, CheckCircle } from 'lucide-vue-next'

definePage({ meta: { layout: 'landing', public: true, unauthenticatedOnly: false } })

const form = ref({ name: '', email: '', phone: '', subject: '', message: '', type: '' })
const sent = ref(false)
const loading = ref(false)

async function submit() {
  if (!form.value.name || !form.value.email || !form.value.message) return
  loading.value = true
  await new Promise(r => setTimeout(r, 1500))
  loading.value = false
  sent.value = true
}

const contacts = [
  { icon: MapPin,  label: 'العنوان',         value: 'رام الله، فلسطين — شارع الإرسال، مبنى الاتحاد' },
  { icon: Phone,   label: 'هاتف المقر',      value: '+970 2 000 0000', href: 'tel:+97020000000' },
  { icon: Phone,   label: 'هاتف الطوارئ',    value: '+970 59 000 0000', href: 'tel:+97059000000' },
  { icon: Mail,    label: 'البريد الإلكتروني', value: 'info@pcu.ps', href: 'mailto:info@pcu.ps' },
  { icon: Clock,   label: 'ساعات العمل',      value: 'الأحد — الخميس: ٨ص — ٤م' },
]

const inquiryTypes = ['استفسار عام','الانضمام والعضوية','التصنيف','المناقصات','التدريب','الشكاوى والنزاعات','التعاون والشراكات','أخرى']
</script>

<template>
  <div dir="rtl" class="pub-page">
    
    <div class="page-hero">
      <div class="page-hero-shapes"><div class="ph-s ph-s1" /><div class="ph-s ph-s2" /></div>
      <div class="pub-cont page-hero-inner">
        <div class="page-breadcrumb"><RouterLink to="/landing">الرئيسية</RouterLink><span>/</span><span>تواصل معنا</span></div>
        <h1 class="page-hero-title">تواصل معنا</h1>
        <p class="page-hero-desc">نحن هنا للإجابة على استفساراتك وتقديم الدعم اللازم — تواصل معنا بأي طريقة تناسبك</p>
      </div>
    </div>

    <section class="pub-section">
      <div class="pub-cont contact-layout">
        <!-- Info Side -->
        <div class="contact-info">
          <div class="pub-sec-label">بياناتنا</div>
          <h2 class="info-title">كيف تصل إلينا؟</h2>
          <p class="info-desc">مقرنا الرئيسي في رام الله مع فروع في ١١ محافظة فلسطينية. يمكنك زيارتنا أو التواصل معنا عبر أي من القنوات أدناه.</p>

          <div class="contact-details">
            <div v-for="c in contacts" :key="c.label" class="contact-item">
              <div class="contact-icon"><component :is="c.icon" :size="18" /></div>
              <div>
                <span class="contact-label">{{ c.label }}</span>
                <a v-if="c.href" :href="c.href" class="contact-value link">{{ c.value }}</a>
                <span v-else class="contact-value">{{ c.value }}</span>
              </div>
            </div>
          </div>

          <!-- Social -->
          <div class="social-section">
            <p class="social-title">تابعنا على</p>
            <div class="social-btns">
              <a href="#" class="social-btn fb" aria-label="Facebook">Facebook</a>
              <a href="#" class="social-btn tw" aria-label="Twitter">Twitter / X</a>
              <a href="#" class="social-btn ln" aria-label="LinkedIn">LinkedIn</a>
              <a href="https://wa.me/970200000000" class="social-btn wa" aria-label="WhatsApp">WhatsApp</a>
            </div>
          </div>
        </div>

        <!-- Form Side -->
        <div class="contact-form-wrap">
          <div v-if="!sent" class="contact-form">
            <h3 class="form-title">أرسل لنا رسالة</h3>
            <div class="form-grid">
              <div class="form-group">
                <label>الاسم الكامل *</label>
                <input v-model="form.name" type="text" placeholder="محمد عبدالله" class="form-input" />
              </div>
              <div class="form-group">
                <label>البريد الإلكتروني *</label>
                <input v-model="form.email" type="email" placeholder="info@company.ps" class="form-input" />
              </div>
              <div class="form-group">
                <label>رقم الهاتف</label>
                <input v-model="form.phone" type="tel" placeholder="+970 5X XXX XXXX" class="form-input" />
              </div>
              <div class="form-group">
                <label>نوع الاستفسار</label>
                <select v-model="form.type" class="form-input">
                  <option value="">اختر نوع الاستفسار</option>
                  <option v-for="t in inquiryTypes" :key="t" :value="t">{{ t }}</option>
                </select>
              </div>
              <div class="form-group full">
                <label>الموضوع *</label>
                <input v-model="form.subject" type="text" placeholder="موضوع رسالتك" class="form-input" />
              </div>
              <div class="form-group full">
                <label>الرسالة *</label>
                <textarea v-model="form.message" rows="6" placeholder="اكتب رسالتك هنا..." class="form-input form-textarea" />
              </div>
            </div>
            <button class="form-submit" @click="submit" :disabled="loading">
              <Send :size="16" /> {{ loading ? 'جاري الإرسال...' : 'إرسال الرسالة' }}
            </button>
          </div>

          <!-- Success -->
          <div v-else class="success-state">
            <div class="success-icon"><CheckCircle :size="64" /></div>
            <h3>تم الإرسال بنجاح!</h3>
            <p>شكراً لتواصلك معنا. سنرد على رسالتك خلال ١-٢ يوم عمل.</p>
            <button @click="sent=false; form={name:'',email:'',phone:'',subject:'',message:'',type:''}">
              إرسال رسالة أخرى
            </button>
          </div>
        </div>
      </div>
    </section>

    <!-- Map placeholder -->
    <div class="map-section">
      <div class="map-placeholder">
        <MapPin :size="48" style="color:#1a237e;margin-bottom:.75rem" />
        <p>اتحاد المقاولين الفلسطينيين — رام الله، شارع الإرسال</p>
        <a href="https://maps.google.com" target="_blank" class="map-link">افتح في خرائط جوجل</a>
      </div>
    </div>

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
.page-breadcrumb a { color: rgba(255,255,255,.6); text-decoration: none; } .page-breadcrumb a:hover { color: #f9a825; }
.page-hero-title { font-family: 'Neo Sans Arabic', 'Cairo', sans-serif; font-size: clamp(2rem,4vw,3rem); font-weight: 900; color: #fff; margin-bottom: .75rem; }
.page-hero-desc { font-size: 1rem; color: rgba(255,255,255,.75); line-height: 1.75; max-width: 600px; }

.contact-layout { display: grid; grid-template-columns: 1fr 1.5fr; gap: 4rem; align-items: start; }
.info-title { font-family: 'Neo Sans Arabic', 'Cairo', sans-serif; font-size: 1.5rem; font-weight: 900; color: #0d1b3e; margin-bottom: .75rem; }
.info-desc { font-size: .92rem; color: #4b5563; line-height: 1.85; margin-bottom: 2rem; }

.contact-details { display: flex; flex-direction: column; gap: 1rem; margin-bottom: 2rem; }
.contact-item { display: flex; gap: 1rem; align-items: flex-start; }
.contact-icon { width: 42px; height: 42px; border-radius: 12px; background: #e8eaf6; color: #1a237e; display: flex; align-items: center; justify-content: center; flex-shrink: 0; border: 1.5px solid #c5cae9; }
.contact-label { display: block; font-size: .75rem; color: #9ca3af; font-weight: 600; margin-bottom: .2rem; }
.contact-value { font-size: .9rem; color: #374151; font-weight: 600; }
.contact-value.link { color: #1a237e; text-decoration: none; }
.contact-value.link:hover { color: #f9a825; }

.social-title { font-family: 'Neo Sans Arabic', 'Cairo', sans-serif; font-size: .85rem; font-weight: 700; color: #6b7280; margin-bottom: .875rem; }
.social-btns { display: flex; gap: .5rem; flex-wrap: wrap; }
.social-btn { display: flex; align-items: center; border-radius: 9px; padding: .5rem 1rem; font-size: .78rem; font-weight: 700; text-decoration: none; border: 1.5px solid; transition: all .2s; }
.social-btn.fb { background: #e7f0fd; color: #1877f2; border-color: #1877f2; }
.social-btn.tw { background: #f5f8fa; color: #1da1f2; border-color: #1da1f2; }
.social-btn.ln { background: #e8f4fb; color: #0077b5; border-color: #0077b5; }
.social-btn.wa { background: #e8f8f0; color: #25d366; border-color: #25d366; }
.social-btn:hover { opacity: .8; transform: translateY(-1px); }

.contact-form-wrap { background: #f9fafb; border: 1.5px solid #e5e7eb; border-radius: 20px; padding: 2.5rem; }
.form-title { font-family: 'Neo Sans Arabic', 'Cairo', sans-serif; font-size: 1.2rem; font-weight: 900; color: #0d1b3e; margin-bottom: 1.75rem; }
.form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem; margin-bottom: 1.5rem; }
.form-group { display: flex; flex-direction: column; gap: .5rem; }
.form-group.full { grid-column: 1 / -1; }
.form-group label { font-size: .85rem; font-weight: 700; color: #374151; }
.form-input { border: 1.5px solid #e5e7eb; border-radius: 10px; padding: .7rem 1rem; font-size: .88rem; font-family: 'Neo Sans Arabic', 'Tajawal', sans-serif; color: #374151; background: #fff; outline: none; transition: border-color .2s, box-shadow .2s; width: 100%; }
.form-input:focus { border-color: #1a237e; box-shadow: 0 0 0 3px rgba(26,35,126,.08); }
.form-textarea { resize: vertical; min-height: 120px; }
.form-submit { display: flex; align-items: center; justify-content: center; gap: .5rem; width: 100%; background: linear-gradient(135deg, #1a237e, #0d1b4b); color: #fff; border: none; border-radius: 12px; padding: 1rem; font-size: .95rem; font-weight: 800; cursor: pointer; font-family: 'Neo Sans Arabic', 'Tajawal', sans-serif; transition: all .25s; }
.form-submit:hover:not(:disabled) { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(13,27,75,.3); }
.form-submit:disabled { opacity: .7; cursor: not-allowed; }

.success-state { text-align: center; padding: 3rem 1rem; }
.success-icon { color: #2e7d32; margin-bottom: 1.25rem; display: flex; justify-content: center; }
.success-state h3 { font-family: 'Neo Sans Arabic', 'Cairo', sans-serif; font-size: 1.4rem; font-weight: 900; color: #0d1b3e; margin-bottom: .65rem; }
.success-state p { font-size: .95rem; color: #6b7280; margin-bottom: 2rem; line-height: 1.8; }
.success-state button { background: #e8eaf6; color: #1a237e; border: 1.5px solid #c5cae9; border-radius: 10px; padding: .7rem 1.5rem; font-size: .88rem; font-weight: 700; cursor: pointer; font-family: 'Neo Sans Arabic', 'Tajawal', sans-serif; }

.map-section { height: 300px; background: #f3f4f6; }
.map-placeholder { height: 100%; display: flex; flex-direction: column; align-items: center; justify-content: center; color: #6b7280; text-align: center; }
.map-placeholder p { font-size: .9rem; margin-bottom: .75rem; }
.map-link { color: #1a237e; font-weight: 700; font-size: .88rem; text-decoration: none; border-bottom: 2px solid #1a237e; }

@media (max-width: 900px) { .contact-layout { grid-template-columns: 1fr; gap: 2rem; } }
@media (max-width: 560px) { .form-grid { grid-template-columns: 1fr; } .form-group.full { grid-column: auto; } }
</style>

<script setup lang="ts">

definePage({ meta: { layout: 'landing', public: true, unauthenticatedOnly: false } })

const lastUpdated = '١ يناير ٢٠٢٤'
</script>

<template>
  <div dir="rtl" class="pub-page">
    
    <div class="page-hero">
      <div class="page-hero-shapes"><div class="ph-s ph-s1" /></div>
      <div class="pub-cont page-hero-inner">
        <div class="page-breadcrumb"><RouterLink to="/landing">الرئيسية</RouterLink><span>/</span><span>سياسة الخصوصية</span></div>
        <h1 class="page-hero-title">سياسة الخصوصية</h1>
        <p class="page-hero-desc">آخر تحديث: {{ lastUpdated }}</p>
      </div>
    </div>

    <section class="legal-section">
      <div class="pub-cont legal-layout">
        <!-- TOC -->
        <nav class="legal-toc">
          <p class="toc-title">المحتويات</p>
          <a href="#s1" class="toc-link">١. جمع المعلومات</a>
          <a href="#s2" class="toc-link">٢. استخدام المعلومات</a>
          <a href="#s3" class="toc-link">٣. حماية البيانات</a>
          <a href="#s4" class="toc-link">٤. ملفات الارتباط</a>
          <a href="#s5" class="toc-link">٥. مشاركة البيانات</a>
          <a href="#s6" class="toc-link">٦. حقوق المستخدم</a>
          <a href="#s7" class="toc-link">٧. الاحتفاظ بالبيانات</a>
          <a href="#s8" class="toc-link">٨. التواصل معنا</a>
        </nav>

        <!-- Content -->
        <div class="legal-content">
          <div class="legal-intro">
            يلتزم اتحاد المقاولين الفلسطينيين بحماية خصوصية مستخدمي موقعه الإلكتروني وأعضائه.
            توضح هذه السياسة كيفية جمع المعلومات واستخدامها وحمايتها.
          </div>

          <section id="s1" class="legal-sec">
            <h2>١. جمع المعلومات</h2>
            <p>نقوم بجمع المعلومات التي تقدمها طوعاً عند:</p>
            <ul>
              <li>التسجيل في الاتحاد أو تجديد العضوية</li>
              <li>التقدم لطلب تصنيف أو خدمة</li>
              <li>التواصل معنا عبر النماذج الإلكترونية أو البريد الإلكتروني</li>
              <li>الاشتراك في النشرات الإخبارية والفعاليات</li>
            </ul>
            <p>تشمل المعلومات المجمَّعة: الاسم، البريد الإلكتروني، رقم الهاتف، بيانات الشركة، والمعلومات المالية اللازمة للتحقق من الأهلية.</p>
          </section>

          <section id="s2" class="legal-sec">
            <h2>٢. استخدام المعلومات</h2>
            <p>نستخدم المعلومات المجمَّعة للأغراض التالية:</p>
            <ul>
              <li>تقديم الخدمات المطلوبة وإدارة العضوية</li>
              <li>إصدار شهادات التصنيف والوثائق الرسمية</li>
              <li>إرسال الإشعارات والتحديثات الهامة</li>
              <li>تحسين خدماتنا وتطوير تجربة المستخدم</li>
              <li>الامتثال للمتطلبات القانونية والتنظيمية</li>
            </ul>
          </section>

          <section id="s3" class="legal-sec">
            <h2>٣. حماية البيانات</h2>
            <p>نطبّق إجراءات أمنية صارمة لحماية بياناتك، تشمل:</p>
            <ul>
              <li>تشفير البيانات أثناء النقل والتخزين باستخدام بروتوكول SSL/TLS</li>
              <li>تقييد الوصول للبيانات على موظفين مخوّلين فقط</li>
              <li>إجراء مراجعات أمنية دورية للأنظمة والبنية التحتية</li>
              <li>تدريب الموظفين على ممارسات حماية البيانات</li>
            </ul>
          </section>

          <section id="s4" class="legal-sec">
            <h2>٤. ملفات الارتباط (Cookies)</h2>
            <p>يستخدم موقعنا ملفات الارتباط لتحسين تجربتك، تشمل:</p>
            <ul>
              <li><strong>ملفات ضرورية:</strong> ضرورية لعمل الموقع بشكل صحيح</li>
              <li><strong>ملفات الأداء:</strong> تساعدنا في تحليل استخدام الموقع</li>
              <li><strong>ملفات التفضيلات:</strong> تتذكر إعداداتك وتفضيلاتك</li>
            </ul>
            <p>يمكنك التحكم في ملفات الارتباط من خلال إعدادات متصفحك.</p>
          </section>

          <section id="s5" class="legal-sec">
            <h2>٥. مشاركة البيانات مع الأطراف الثالثة</h2>
            <p>لا نبيع أو نؤجر بياناتك لأطراف ثالثة. قد نشارك البيانات في الحالات التالية:</p>
            <ul>
              <li>بموافقتك الصريحة</li>
              <li>مع مزودي الخدمات الذين يعملون بموجب اتفاقيات سرية</li>
              <li>للامتثال للمتطلبات القانونية أو أوامر المحاكم</li>
              <li>مع الجهات الحكومية المعنية لإتمام خدمات التصنيف والترخيص</li>
            </ul>
          </section>

          <section id="s6" class="legal-sec">
            <h2>٦. حقوق المستخدم</h2>
            <p>وفقاً للأنظمة المعمول بها، لديك الحق في:</p>
            <ul>
              <li>الاطلاع على البيانات التي نحتفظ بها عنك</li>
              <li>طلب تصحيح البيانات غير الدقيقة</li>
              <li>طلب حذف بياناتك في الحالات المنصوص عليها</li>
              <li>الاعتراض على معالجة بياناتك لأغراض التسويق</li>
              <li>نقل بياناتك إلى جهة أخرى (حق النقل)</li>
            </ul>
          </section>

          <section id="s7" class="legal-sec">
            <h2>٧. الاحتفاظ بالبيانات</h2>
            <p>نحتفظ ببياناتك للمدة اللازمة لتقديم الخدمات وللامتثال للمتطلبات القانونية. عند انتهاء العضوية، نحتفظ ببيانات أساسية لمدة ٥ سنوات وفق متطلبات القانون الفلسطيني.</p>
          </section>

          <section id="s8" class="legal-sec">
            <h2>٨. التواصل معنا</h2>
            <p>لأي استفسارات حول سياسة الخصوصية أو ممارساتنا في معالجة البيانات:</p>
            <ul>
              <li>البريد الإلكتروني: <a href="mailto:privacy@pcu.ps">privacy@pcu.ps</a></li>
              <li>الهاتف: <a href="tel:+97020000000">+970 2 000 0000</a></li>
              <li>العنوان: رام الله، فلسطين — شارع الإرسال، مبنى الاتحاد</li>
            </ul>
          </section>
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
.page-hero { background: linear-gradient(145deg, #0d1b4b 0%, #1a237e 60%, #283593 100%); padding: 4rem 0 3rem; position: relative; overflow: hidden; }
.page-hero-shapes { position: absolute; inset: 0; pointer-events: none; }
.ph-s { position: absolute; border-radius: 50%; }
.ph-s1 { width: 400px; height: 400px; background: radial-gradient(circle, rgba(249,168,37,.12), transparent 65%); top: -100px; left: 5%; }
.page-hero-inner { position: relative; z-index: 2; }
.page-breadcrumb { display: flex; align-items: center; gap: .5rem; font-size: .82rem; color: rgba(255,255,255,.6); margin-bottom: 1rem; }
.page-breadcrumb a { color: rgba(255,255,255,.6); text-decoration: none; }
.page-hero-title { font-family: 'Neo Sans Arabic', 'Cairo', sans-serif; font-size: clamp(2rem,4vw,3rem); font-weight: 900; color: #fff; margin-bottom: .5rem; }
.page-hero-desc { font-size: .9rem; color: rgba(255,255,255,.6); }

.legal-section { padding: 5rem 0; }
.legal-layout { display: grid; grid-template-columns: 220px 1fr; gap: 4rem; align-items: start; }
.legal-toc { position: sticky; top: 120px; background: #f9fafb; border: 1.5px solid #e5e7eb; border-radius: 14px; padding: 1.5rem; }
.toc-title { font-family: 'Neo Sans Arabic', 'Cairo', sans-serif; font-size: .85rem; font-weight: 800; color: #0d1b3e; margin-bottom: 1rem; padding-bottom: .65rem; border-bottom: 2px solid #e5e7eb; }
.toc-link { display: block; color: #6b7280; font-size: .83rem; font-weight: 600; text-decoration: none; padding: .4rem 0; border-bottom: 1px solid #f3f4f6; transition: color .2s; }
.toc-link:hover { color: #1a237e; }

.legal-intro { background: #e8eaf6; border: 1.5px solid #c5cae9; border-radius: 12px; padding: 1.25rem 1.5rem; font-size: .92rem; color: #1a237e; line-height: 1.85; margin-bottom: 2.5rem; }
.legal-sec { margin-bottom: 2.5rem; }
.legal-sec h2 { font-family: 'Neo Sans Arabic', 'Cairo', sans-serif; font-size: 1.1rem; font-weight: 800; color: #0d1b3e; margin-bottom: 1rem; padding-bottom: .5rem; border-bottom: 2px solid #e5e7eb; }
.legal-sec p { font-size: .92rem; color: #4b5563; line-height: 1.9; margin-bottom: .875rem; }
.legal-sec ul { list-style: none; padding: 0; display: flex; flex-direction: column; gap: .5rem; margin-bottom: .875rem; }
.legal-sec ul li { font-size: .9rem; color: #4b5563; line-height: 1.75; padding-inline-start: 1.25rem; position: relative; }
.legal-sec ul li::before { content: '—'; position: absolute; right: 0; color: #1a237e; font-weight: 700; }
.legal-sec ul li strong { color: #374151; }
.legal-sec a { color: #1a237e; text-decoration: none; border-bottom: 1px solid #c5cae9; }
.legal-sec a:hover { color: #f9a825; border-color: #f9a825; }

@media (max-width: 900px) { .legal-layout { grid-template-columns: 1fr; } .legal-toc { position: static; } }
</style>

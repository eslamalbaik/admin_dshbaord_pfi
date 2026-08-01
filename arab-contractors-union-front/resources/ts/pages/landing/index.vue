<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useRouter } from 'vue-router'
import api from '@/plugins/axios'
import {
  Phone, Mail, MapPin, Clock,
  ClipboardList, Megaphone, Wrench,
  HardHat, Building2, CalendarDays, Award,
  Scale, BookOpen, Landmark, Handshake, ChartBar,
  Newspaper, Check,
  ChevronLeft, ChevronDown, Menu,
  ShieldCheck, Users, Globe, Lightbulb, TrendingUp, Star,
  Zap, FileText, MessageSquare, PlayCircle, GraduationCap,
  Truck, Droplets, Flame, Factory, Home, TreePine,
  ArrowLeft, ArrowRight,
} from 'lucide-vue-next'

const router = useRouter()

definePage({
  meta: { layout: 'landing', public: true, unauthenticatedOnly: false },
})

function goToRegister() { router.push('/contractor/login') }

// ─── News ─────────────────────────────────────────────────────────────────────
interface NewsItem { id:number; title:string; slug:string; excerpt:string|null; image:string|null; category:string; published_at:string }
const latestNews = ref<NewsItem[]>([])
const catLabel: Record<string, string> = { news: 'خبر', announcement: 'إعلان', event: 'فعالية', tender: 'مناقصة' }
function fmtDate(d: string) {
  return new Date(d).toLocaleDateString('ar-PS', { year: 'numeric', month: 'long', day: 'numeric' })
}

onMounted(async () => {
  // نداء موحّد واحد لكل بيانات الصفحة الرئيسية (أخبار + إحصائيات + بيانات الاتحاد)
  try {
    const r = await api.get('/api/v1/landing/home')
    const home = r.data.items ?? {}

    latestNews.value = (home.latest_news ?? []).map((n: any) => ({ ...n, image: n.image_url ?? n.image ?? null }))

    // تحديث العدّادات بالأرقام الحقيقية/المُعدّة من لوحة التحكم
    const s = home.stats ?? {}
    if (s.members_count) stats.value[0].target = s.members_count
    if (s.tenders_open)  stats.value[1].target = s.tenders_open
    if (s.news_count)    stats.value[2].target = s.news_count
    if (s.years)         stats.value[3].target = s.years
    if (s.projects)      stats.value[4].target = s.projects
    if (s.branches)      stats.value[5].target = s.branches
  }
  catch {}

  // Animated counters
  animateCounters()
})

// ─── Animated Counters ────────────────────────────────────────────────────────
const counters = ref([0, 0, 0, 0, 0, 0])
function animateCounters() {
  const targets = stats.value.map(s => s.target)
  targets.forEach((target, i) => {
    let current = 0
    const step = target / 60
    const timer = setInterval(() => {
      current = Math.min(current + step, target)
      counters.value[i] = Math.floor(current)
      if (current >= target) clearInterval(timer)
    }, 25)
  })
}

// ─── Scroll ───────────────────────────────────────────────────────────────────
const scrolled = ref(false)
if (typeof window !== 'undefined')
  window.addEventListener('scroll', () => { scrolled.value = window.scrollY > 50 }, { passive: true })

const mobileOpen = ref(false)

// ─── Testimonials ─────────────────────────────────────────────────────────────
const currentTestimonial = ref(0)
const testimonials = [
  { name: 'م. أحمد محمود الخطيب', title: 'مدير شركة الخطيب للمقاولات', city: 'رام الله', text: 'منذ انضمامنا لاتحاد المقاولين الفلسطينيين، تضاعفت فرصنا في الحصول على المناقصات الحكومية. الاتحاد يمثلنا بشكل حقيقي أمام الجهات الرسمية ويحمي حقوقنا.', rating: 5 },
  { name: 'م. سمر يوسف الزبن', title: 'مديرة شركة الزبن للإنشاءات', city: 'نابلس', text: 'الخدمات التي يقدمها الاتحاد من تصنيف وإصدار شهادات وتدريب متميزة جداً. وفرت علينا الكثير من الوقت والجهد في التعامل مع الجهات الرسمية.', rating: 5 },
  { name: 'م. خالد عمر النجار', title: 'مدير شركة النجار للبنية التحتية', city: 'الخليل', text: 'البرامج التدريبية التي ينظمها الاتحاد ترقى لمستوى المعايير الدولية. حصلنا على شهادات في إدارة المشاريع والفيديك ساعدتنا كثيراً في الفوز بمشاريع دولية.', rating: 5 },
  { name: 'م. رائد سعيد البرغوثي', title: 'مدير شركة البرغوثي للطاقة', city: 'بيت لحم', text: 'التحكيم وتسوية النزاعات الذي يوفره الاتحاد أنقذ شركتنا من نزاع طويل مع مالك المشروع. الاتحاد يستحق كل الثقة والاحترام.', rating: 5 },
]
function nextTestimonial() { currentTestimonial.value = (currentTestimonial.value + 1) % testimonials.length }
function prevTestimonial() { currentTestimonial.value = (currentTestimonial.value - 1 + testimonials.length) % testimonials.length }

// ─── FAQ ──────────────────────────────────────────────────────────────────────
const openFaq = ref<number | null>(null)
const faqs = [
  { q: 'من هم المقاولون المؤهلون للانضمام للاتحاد؟', a: 'جميع شركات المقاولات المسجلة رسمياً في السلطة الوطنية الفلسطينية والحاصلة على السجل التجاري وتمارس نشاطاً في قطاع الإنشاءات والبناء والبنية التحتية.' },
  { q: 'ما هي مزايا العضوية في اتحاد المقاولين الفلسطينيين؟', a: 'تشمل المزايا: شهادات التصنيف المعتمدة، التمثيل أمام الجهات الحكومية، الوصول للمناقصات الحكومية، البرامج التدريبية المتخصصة، الاستشارات القانونية والفنية، وخدمة تسوية النزاعات والتحكيم.' },
  { q: 'كيف أجدد عضويتي في الاتحاد؟', a: 'يمكن تجديد العضوية إلكترونياً من خلال بوابة الأعضاء، أو مراجعة المقر الرئيسي للاتحاد أو أي فرع من فروع المحافظات. يُنصح بتجديد العضوية قبل انتهائها بشهر على الأقل.' },
  { q: 'ما هي درجات تصنيف المقاولين المعتمدة؟', a: 'يعتمد الاتحاد ست درجات تصنيف: الأولى أ (A1) للمشاريع الكبرى، الأولى ب (A2) للمشاريع الكبيرة، الثانية (B) للمتوسطة والكبيرة، الثالثة (C) للمتوسطة، الرابعة (D) للصغيرة، والخامسة (E) للمقاولين الناشئين.' },
  { q: 'هل تُقبل شهادات التصنيف الصادرة عن الاتحاد دولياً؟', a: 'نعم، شهادات الاتحاد معترف بها من قِبل اتحاد المقاولين العرب والاتحاد الدولي للمقاولين (FIDIC)، كما تُقبل من كثير من الجهات المانحة الدولية العاملة في فلسطين.' },
  { q: 'كيف يمكنني الاستفادة من خدمة التحكيم وتسوية النزاعات؟', a: 'يمكن تقديم طلب التحكيم عبر البريد الإلكتروني أو مراجعة قسم الخدمات القانونية في المقر الرئيسي. يضم الاتحاد لجنة تحكيم متخصصة من خبراء قانونيين وهندسيين معتمدين.' },
]
function toggleFaq(idx: number) { openFaq.value = openFaq.value === idx ? null : idx }

// ─── Data ─────────────────────────────────────────────────────────────────────
const navLinks = [
  { label: 'الرئيسية',   href: '#hero' },
  { label: 'من نحن',     href: '#about' },
  { label: 'خدماتنا',   href: '#services' },
  { label: 'القطاعات',  href: '#sectors' },
  { label: 'الأخبار',   href: '#news' },
  { label: 'التدريب',   href: '#training' },
  { label: 'تواصل معنا', href: '#contact' },
]

const stats = ref([
  { target: 0, suffix: '+', label: 'شركة مقاولات عضو',   icon: HardHat,     color: '#1a237e' },
  { target: 0, suffix: '+', label: 'مناقصة مفتوحة',    icon: ClipboardList, color: '#c62828' },
  { target: 0, suffix: '+', label: 'خبر',               icon: Newspaper,   color: '#2e7d32' },
  { target: 0, suffix: '',  label: 'سنة خبرة',         icon: CalendarDays, color: '#e65100' },
  { target: 0, suffix: '+', label: 'مشروع تم تنفيذه',   icon: Building2,   color: '#6a1b9a' },
  { target: 0, suffix: '',  label: 'محافظة وفرع',       icon: MapPin,      color: '#00695c' },
])

const whyUs = [
  { icon: ShieldCheck, title: 'تمثيل رسمي للقطاع', desc: 'نمثل قطاع المقاولات أمام الحكومة والمؤسسات الدولية', color: '#e8eaf6', border: '#3949ab' },
  { icon: Scale, title: 'حماية حقوق المقاولين', desc: 'نتابع استرداد المستحقات ونحل النزاعات بفاعلية', color: '#e8f5e9', border: '#2e7d32' },
  { icon: FileText, title: 'البيئة التشريعية', desc: 'نشارك في صياغة القوانين والأنظمة التي تخدم القطاع', color: '#fff8e1', border: '#f57c00' },
  { icon: GraduationCap, title: 'بناء القدرات', desc: 'برامج تدريب وتأهيل على أعلى المعايير الدولية', color: '#fce4ec', border: '#c62828' },
  { icon: Handshake, title: 'شراكات استراتيجية', desc: 'علاقات مع مؤسسات دولية ومنظمات مهنية عالمية', color: '#e8eaf6', border: '#7986cb' },
  { icon: TrendingUp, title: 'تعزيز الجودة', desc: 'معايير جودة وسلامة مهنية لرفع مستوى القطاع', color: '#e0f7fa', border: '#00838f' },
  { icon: Lightbulb, title: 'دعم الاستثمار', desc: 'تهيئة البيئة لجذب الاستثمار في قطاع الإنشاءات', color: '#f3e5f5', border: '#7b1fa2' },
  { icon: MessageSquare, title: 'التواصل الرسمي', desc: 'قناة رسمية للتواصل مع الجهات الحكومية والدولية', color: '#e8f5e9', border: '#388e3c' },
]

const services = [
  { icon: ClipboardList, title: 'تسجيل العضوية',       desc: 'انضم إلى الاتحاد وابدأ رحلتك نحو قطاع مقاولات أقوى وأكثر احترافية.',   href: '#' },
  { icon: Award,         title: 'تجديد العضوية',       desc: 'جدد عضويتك بسهولة إلكترونياً أو من خلال فروعنا في المحافظات.',           href: '/services' },
  { icon: FileText,      title: 'إصدار الشهادات',      desc: 'شهادات تصنيف معتمدة ومعترف بها محلياً ودولياً لجميع درجات التصنيف.',    href: '/services' },
  { icon: Scale,         title: 'الاستشارات القانونية', desc: 'فريق متخصص من المحامين والخبراء لحماية حقوقك التعاقدية والقانونية.',     href: '/services' },
  { icon: GraduationCap, title: 'التدريب والتأهيل',    desc: 'برامج تدريبية متخصصة في إدارة المشاريع والسلامة والفيديك والتسعير.',     href: '/training-center' },
  { icon: Handshake,     title: 'التحكيم والنزاعات',   desc: 'لجنة تحكيم متخصصة لحل النزاعات بين المقاولين والجهات المختلفة.',        href: '/services' },
  { icon: Megaphone,     title: 'المناقصات والعطاءات', desc: 'اطلع على أحدث المناقصات والعطاءات الحكومية والمشاريع المتاحة للقطاع.',    href: '/public-tenders' },
  { icon: ChartBar,      title: 'الدراسات والتقارير',  desc: 'تقارير ودراسات تحليلية دورية عن قطاع المقاولات الفلسطيني.',              href: '/library' },
]

const sectors = [
  { icon: Building2,  title: 'الإنشاءات العامة',       desc: 'مباني ومنشآت عامة وخاصة',       color: '#1a237e' },
  { icon: Truck,      title: 'البنية التحتية',           desc: 'طرق وجسور وأنفاق',               color: '#c62828' },
  { icon: Droplets,   title: 'المياه والصرف الصحي',     desc: 'شبكات المياه والصرف',            color: '#0277bd' },
  { icon: Flame,      title: 'الكهرباء والطاقة',         desc: 'محطات ومنظومات الطاقة',           color: '#e65100' },
  { icon: Landmark,   title: 'المباني الحكومية',         desc: 'وزارات ودوائر ومؤسسات',          color: '#2e7d32' },
  { icon: Home,       title: 'الإسكان السكني',           desc: 'مجمعات ووحدات سكنية',            color: '#6a1b9a' },
  { icon: Factory,    title: 'المشاريع الصناعية',        desc: 'مناطق صناعية ومصانع',            color: '#00695c' },
  { icon: TreePine,   title: 'المشاريع البيئية',         desc: 'إعادة تأهيل ومشاريع بيئية',      color: '#558b2f' },
]

const courses = [
  { title: 'السلامة والصحة المهنية',    duration: '3 أيام', level: 'مبتدئ',  icon: ShieldCheck, color: '#c62828', bg: '#fce4ec', participants: '٢٥ مشارك' },
  { title: 'إدارة المشاريع الإنشائية', duration: '5 أيام', level: 'متوسط',  icon: ChartBar,    color: '#1a237e', bg: '#e8eaf6', participants: '٢٠ مشارك' },
  { title: 'عقود الفيديك FIDIC',        duration: '4 أيام', level: 'متقدم',  icon: FileText,    color: '#e65100', bg: '#fff8e1', participants: '١٥ مشارك' },
  { title: 'تسعير المشاريع الإنشائية', duration: '3 أيام', level: 'متوسط',  icon: ChartBar,    color: '#2e7d32', bg: '#e8f5e9', participants: '٢٠ مشارك' },
  { title: 'إدارة المخاطر',             duration: '2 أيام', level: 'متقدم',  icon: ShieldCheck, color: '#6a1b9a', bg: '#f3e5f5', participants: '١٨ مشارك' },
  { title: 'قراءة المخططات الهندسية',  duration: '3 أيام', level: 'مبتدئ',  icon: BookOpen,    color: '#00695c', bg: '#e0f2f1', participants: '٣٠ مشارك' },
]

const partners = [
  'وزارة الأشغال العامة', 'وزارة الحكم المحلي',
  'نقابة المهندسين', 'سلطة المياه',
  'اتحاد المقاولين العرب', 'الاتحاد الدولي FIDIC',
  'البنك الدولي', 'الأونروا',
]

const degrees = [
  { code: 'A1', label: 'الأولى أ',  desc: 'أعلى درجات التصنيف',        bg: '#e8eaf6', clr: '#1a237e', border: '#3949ab' },
  { code: 'A2', label: 'الأولى ب',  desc: 'مشاريع متوسطة وكبيرة',      bg: '#e3f2fd', clr: '#0d47a1', border: '#1565c0' },
  { code: 'B',  label: 'الثانية',   desc: 'مشاريع متوسطة الحجم',       bg: '#e8f5e9', clr: '#1b5e20', border: '#2e7d32' },
  { code: 'C',  label: 'الثالثة',   desc: 'مشاريع صغيرة ومتوسطة',     bg: '#fff8e1', clr: '#e65100', border: '#f57c00' },
  { code: 'D',  label: 'الرابعة',   desc: 'مشاريع صغيرة الحجم',       bg: '#fce4ec', clr: '#b71c1c', border: '#c62828' },
  { code: 'E',  label: 'الخامسة',   desc: 'مقاولون ناشئون',            bg: '#f3e5f5', clr: '#4a148c', border: '#7b1fa2' },
]
</script>

<template>
  <div dir="rtl" class="lp">

    <!-- ══ HERO ══ -->
    <section id="hero" class="hero">
      <div class="hero-overlay" />
      <div class="hero-shapes">
        <div class="hs hs-1" />
        <div class="hs hs-2" />
        <div class="hs hs-3" />
        <div class="hs hs-4" />
      </div>

      <div class="container hero-inner">
        <div class="hero-text">
          <div class="hero-eyebrow">
            <span class="eyebrow-dot" />
            اتحاد المقاولين الفلسطينيين — الممثل الرسمي للقطاع
          </div>
          <h1 class="hero-title">
            معاً نحو قطاع مقاولات<br />
            <span class="hero-accent">فلسطيني أكثر قوة</span>
            <span class="hero-accent-gold"> واستدامة</span>
          </h1>
          <p class="hero-desc">
            اتحاد المقاولين الفلسطينيين هو الممثل الرسمي لقطاع المقاولات، يعمل على حماية مصالح المقاولين،
            وتطوير المهنة، وتعزيز الشراكة مع المؤسسات الحكومية والقطاع الخاص للنهوض بقطاع الإنشاءات في فلسطين.
          </p>
          <div class="hero-actions">
            <button class="btn-hero-primary" @click="goToRegister">
              <HardHat :size="18" /> انضم إلى الاتحاد
            </button>
            <a href="#services" class="btn-hero-secondary">
              <Zap :size="18" /> خدمات الأعضاء
            </a>
            <a href="#contact" class="btn-hero-outline">
              <Phone :size="18" /> تواصل معنا
            </a>
          </div>
          <div class="hero-trust">
            <span class="trust-item"><Check :size="14" class="trust-ico" /> جهة رسمية معتمدة</span>
            <span class="trust-sep">•</span>
            <span class="trust-item"><Check :size="14" class="trust-ico" /> أكثر من ١٢٠٠ عضو</span>
            <span class="trust-sep">•</span>
            <span class="trust-item"><Check :size="14" class="trust-ico" /> ٣٠+ سنة خبرة</span>
            <span class="trust-sep">•</span>
            <span class="trust-item"><Check :size="14" class="trust-ico" /> ١١ فرع ومحافظة</span>
          </div>
        </div>

        <div class="hero-visual">
          <div class="hero-card-wrap">
            <div class="hero-main-card">
              <img src="https://images.unsplash.com/photo-1541888946425-d81bb19240f5?q=80&w=800" alt="إنشاءات" class="hero-bg-img" />
              <div class="hero-img-overlay" />
              <img src="/logo.png" alt="شعار اتحاد المقاولين الفلسطينيين" class="hero-logo" />
              <div class="hero-ring hero-ring-1" />
              <div class="hero-ring hero-ring-2" />
            </div>
            <div class="hero-float-card fc-top">
              <div class="fc-icon"><Building2 :size="20" /></div>
              <div>
                <div class="fc-num">+١٢٠٠</div>
                <div class="fc-lbl">شركة عضو</div>
              </div>
            </div>
            <div class="hero-float-card fc-bottom">
              <div class="fc-icon" style="background:#e8f5e9;color:#2e7d32"><Award :size="20" /></div>
              <div>
                <div class="fc-num" style="color:#2e7d32">+٥٠٠٠</div>
                <div class="fc-lbl">مشروع منجز</div>
              </div>
            </div>
            <div class="hero-float-card fc-side">
              <div class="fc-icon" style="background:#fff8e1;color:#e65100"><CalendarDays :size="20" /></div>
              <div>
                <div class="fc-num" style="color:#e65100">٣٠+</div>
                <div class="fc-lbl">سنة خبرة</div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="hero-wave">
        <svg viewBox="0 0 1440 80" preserveAspectRatio="none" xmlns="http://www.w3.org/2000/svg">
          <path d="M0,40 C360,80 1080,0 1440,40 L1440,80 L0,80 Z" fill="#f5f7ff" />
        </svg>
      </div>
    </section>

    <!-- ══ STATS ══ -->
    <section class="stats-section">
      <div class="container stats-grid">
        <div v-for="(s, i) in stats" :key="s.label" class="stat-card">
          <div class="stat-icon-box" :style="`color:${s.color};border-color:${s.color}22;background:${s.color}11`">
            <component :is="s.icon" :size="28" />
          </div>
          <span class="stat-val" :style="`color:${s.color}`">
            {{ counters[i].toLocaleString('ar-PS') }}{{ s.suffix }}
          </span>
          <span class="stat-lbl">{{ s.label }}</span>
        </div>
      </div>
    </section>

    <!-- ══ WHY US ══ -->
    <section id="why" class="section bg-indigo-soft">
      <div class="container">
        <div class="sec-header-c">
          <div class="sec-label">لماذا الاتحاد؟</div>
          <h2 class="sec-heading center">ما الذي يميّز اتحاد المقاولين الفلسطينيين؟</h2>
          <p class="sec-sub" style="margin:0 auto">نقدم منظومة متكاملة من الخدمات والحلول لتمكين قطاع المقاولات وتعزيز مكانته</p>
        </div>
        <div class="why-grid">
          <div v-for="w in whyUs" :key="w.title" class="why-card" :style="`--wbg:${w.color};--wborder:${w.border}`">
            <div class="why-icon-box" :style="`background:${w.color};color:${w.border};border-color:${w.border}`">
              <component :is="w.icon" :size="24" />
            </div>
            <h3 class="why-title">{{ w.title }}</h3>
            <p class="why-desc">{{ w.desc }}</p>
          </div>
        </div>
      </div>
    </section>

    <!-- ══ SERVICES ══ -->
    <section id="services" class="section">
      <div class="container">
        <div class="sec-header-c">
          <div class="sec-label">خدماتنا</div>
          <h2 class="sec-heading center">خدمات الاتحاد للأعضاء</h2>
          <p class="sec-sub" style="margin:0 auto">منظومة متكاملة من الخدمات المهنية لدعم شركات المقاولات في جميع مراحل عملها</p>
        </div>
        <div class="srv-grid">
          <a v-for="s in services" :key="s.title" :href="s.href" class="srv-card">
            <div class="srv-icon-box">
              <component :is="s.icon" :size="26" />
            </div>
            <h3 class="srv-title">{{ s.title }}</h3>
            <p class="srv-desc">{{ s.desc }}</p>
            <span class="srv-more">اعرف أكثر <ChevronLeft :size="14" /></span>
          </a>
        </div>
      </div>
    </section>

    <!-- ══ SECTORS ══ -->
    <section id="sectors" class="section bg-dots">
      <div class="container">
        <div class="sec-header-c">
          <div class="sec-label">قطاعات العمل</div>
          <h2 class="sec-heading center">قطاعات الإنشاءات التي نمثّلها</h2>
          <p class="sec-sub" style="margin:0 auto">نغطي جميع قطاعات الإنشاءات والبنية التحتية في فلسطين</p>
        </div>
        <div class="sectors-grid">
          <div v-for="s in sectors" :key="s.title" class="sector-card">
            <div class="sector-icon" :style="`background:${s.color}15;color:${s.color};border-color:${s.color}30`">
              <component :is="s.icon" :size="30" />
            </div>
            <h3 class="sector-title">{{ s.title }}</h3>
            <p class="sector-desc">{{ s.desc }}</p>
          </div>
        </div>
      </div>
    </section>

    <!-- ══ ABOUT ══ -->
    <section id="about" class="section">
      <div class="container about-grid">
        <div class="about-visual-side">
          <div class="about-card-big">
            <img src="/logo.png" alt="شعار الاتحاد" class="about-logo-big" />
            <div class="about-badge-float">
              <span class="float-num">+٣٠</span>
              <span class="float-lbl">عاماً من الخبرة</span>
            </div>
            <div class="about-badge-top">
              <Check :size="16" />
              <span>جهة رسمية معتمدة</span>
            </div>
          </div>
          <div class="about-stat-cards">
            <div class="mini-stat" style="--ac:#1a237e;--abg:#e8eaf6">
              <span class="mini-val">١٢٠٠+</span>
              <span class="mini-lbl">مقاول عضو</span>
            </div>
            <div class="mini-stat" style="--ac:#c62828;--abg:#fce4ec">
              <span class="mini-val">٨+</span>
              <span class="mini-lbl">قطاعات</span>
            </div>
            <div class="mini-stat" style="--ac:#2e7d32;--abg:#e8f5e9">
              <span class="mini-val">١١</span>
              <span class="mini-lbl">محافظة</span>
            </div>
          </div>
        </div>

        <div class="about-text-side">
          <div class="sec-label">من نحن</div>
          <h2 class="sec-heading">ممثّل قطاع المقاولات<br />الفلسطيني الرسمي</h2>
          <p class="about-p">
            تأسس اتحاد المقاولين الفلسطينيين ليكون المرجع المهني الأول لقطاع المقاولات
            في فلسطين، يضم أكثر من ١٢٠٠ شركة موزعة في جميع محافظات الوطن، ويمثل الصوت الرسمي
            للقطاع أمام الحكومة والمؤسسات الدولية.
          </p>
          <p class="about-p">
            يعمل الاتحاد على تصنيف الشركات وإصدار الشهادات المعتمدة، ويتولى تمثيل
            القطاع أمام الجهات الحكومية والدولية، وينظم برامج تدريبية متخصصة لرفع كفاءة
            العاملين في قطاع الإنشاءات.
          </p>

          <div class="about-pillars">
            <div class="pillar">
              <div class="pillar-icon" style="background:#e8eaf6;color:#1a237e"><Star :size="16" /></div>
              <div>
                <div class="pillar-title">رؤيتنا</div>
                <div class="pillar-desc">قطاع مقاولات فلسطيني متميز يرقى للمعايير الدولية</div>
              </div>
            </div>
            <div class="pillar">
              <div class="pillar-icon" style="background:#fff8e1;color:#e65100"><Lightbulb :size="16" /></div>
              <div>
                <div class="pillar-title">رسالتنا</div>
                <div class="pillar-desc">تمثيل وتطوير وحماية قطاع المقاولات الفلسطيني</div>
              </div>
            </div>
          </div>

          <ul class="about-list">
            <li><Check :size="15" class="list-ico" /> جهة رسمية معتمدة من السلطة الوطنية الفلسطينية</li>
            <li><Check :size="15" class="list-ico" /> شهادات تصنيف معترف بها دولياً من FIDIC</li>
            <li><Check :size="15" class="list-ico" /> عضو في اتحاد المقاولين العرب والدولي</li>
            <li><Check :size="15" class="list-ico" /> حضور في ١١ محافظة فلسطينية</li>
          </ul>
          <a href="#services" class="btn-navy-outline">تعرّف على خدماتنا <ChevronLeft :size="14" /></a>
        </div>
      </div>
    </section>

    <!-- ══ CLASSIFICATION ══ -->
    <section class="section bg-indigo-soft">
      <div class="container">
        <div class="sec-header-c">
          <div class="sec-label">التصنيف</div>
          <h2 class="sec-heading center">درجات تصنيف المقاولين</h2>
          <p class="sec-sub" style="margin:0 auto">ست درجات تصنيف وفق الكفاءة المالية والفنية والخبرة العملية</p>
        </div>
        <div class="deg-grid">
          <div
            v-for="d in degrees" :key="d.code"
            class="deg-card"
            :style="`--dbg:${d.bg};--dclr:${d.clr};--dborder:${d.border}`"
          >
            <span class="deg-code">{{ d.code }}</span>
            <h4 class="deg-label">{{ d.label }}</h4>
            <p class="deg-desc">{{ d.desc }}</p>
          </div>
        </div>
        <div class="deg-cta">
          <p class="deg-cta-text">هل أنت مقاول مسجّل؟ فعّل حسابك الآن وابدأ رحلتك مع الاتحاد.</p>
          <button class="btn-primary" @click="goToRegister">تفعيل الحساب الآن</button>
        </div>
      </div>
    </section>

    <!-- ══ NEWS ══ -->
    <section id="news" class="section">
      <div class="container">
        <div class="sec-header-split">
          <div>
            <div class="sec-label">الأخبار</div>
            <h2 class="sec-heading">آخر الأخبار والإعلانات</h2>
          </div>
          <RouterLink to="/landing/news" class="btn-navy-outline small">عرض جميع الأخبار <ChevronLeft :size="14" /></RouterLink>
        </div>

        <div v-if="latestNews.length" class="news-grid">
          <RouterLink v-for="item in latestNews" :key="item.id" :to="`/landing/news/${item.slug}`" class="news-card">
            <div class="news-thumb">
              <img v-if="item.image" :src="item.image" :alt="item.title" class="news-img" />
              <div v-else class="news-ph">
                <Newspaper :size="44" />
              </div>
              <span class="news-badge">{{ catLabel[item.category] ?? item.category }}</span>
            </div>
            <div class="news-body">
              <p class="news-date"><CalendarDays :size="12" /> {{ fmtDate(item.published_at) }}</p>
              <h3 class="news-title">{{ item.title }}</h3>
              <p v-if="item.excerpt" class="news-exc">{{ item.excerpt }}</p>
              <span class="news-more">اقرأ المزيد <ChevronLeft :size="12" /></span>
            </div>
          </RouterLink>
        </div>
        <div v-else class="news-grid">
          <div v-for="n in 3" :key="n" class="news-card news-skel">
            <div class="skel-thumb" />
            <div class="news-body">
              <div class="skel-line w35" /><div class="skel-line" /><div class="skel-line w60" />
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- ══ TRAINING ══ -->
    <section id="training" class="section bg-dots">
      <div class="container">
        <div class="sec-header-split">
          <div>
            <div class="sec-label">التدريب وبناء القدرات</div>
            <h2 class="sec-heading">البرامج والدورات التدريبية</h2>
          </div>
          <a href="#" class="btn-navy-outline small">جميع الدورات <ChevronLeft :size="14" /></a>
        </div>
        <div class="courses-grid">
          <div v-for="c in courses" :key="c.title" class="course-card">
            <div class="course-icon" :style="`background:${c.bg};color:${c.color}`">
              <component :is="c.icon" :size="24" />
            </div>
            <div class="course-body">
              <div class="course-level" :style="`color:${c.color};background:${c.bg}`">{{ c.level }}</div>
              <h3 class="course-title">{{ c.title }}</h3>
              <div class="course-meta">
                <span><Clock :size="12" /> {{ c.duration }}</span>
                <span><Users :size="12" /> {{ c.participants }}</span>
              </div>
            </div>
            <button class="course-btn" :style="`background:${c.color}`">سجّل الآن</button>
          </div>
        </div>
      </div>
    </section>

    <!-- ══ TESTIMONIALS ══ -->
    <section class="section testimonials-section">
      <div class="container">
        <div class="sec-header-c">
          <div class="sec-label">آراء الأعضاء</div>
          <h2 class="sec-heading center">ماذا يقول أعضاؤنا؟</h2>
        </div>
        <div class="testi-wrap">
          <button class="testi-nav testi-prev" @click="prevTestimonial" aria-label="السابق">
            <ArrowRight :size="20" />
          </button>
          <div class="testi-card">
            <div class="testi-stars">
              <Star v-for="i in testimonials[currentTestimonial].rating" :key="i" :size="18" fill="currentColor" />
            </div>
            <p class="testi-text">"{{ testimonials[currentTestimonial].text }}"</p>
            <div class="testi-author">
              <div class="testi-avatar">{{ testimonials[currentTestimonial].name.charAt(0) }}</div>
              <div>
                <div class="testi-name">{{ testimonials[currentTestimonial].name }}</div>
                <div class="testi-role">{{ testimonials[currentTestimonial].title }}</div>
                <div class="testi-city"><MapPin :size="11" /> {{ testimonials[currentTestimonial].city }}</div>
              </div>
            </div>
          </div>
          <button class="testi-nav testi-next" @click="nextTestimonial" aria-label="التالي">
            <ArrowLeft :size="20" />
          </button>
        </div>
        <div class="testi-dots">
          <button
            v-for="(_, i) in testimonials" :key="i"
            class="testi-dot"
            :class="{ active: i === currentTestimonial }"
            @click="currentTestimonial = i"
          />
        </div>
      </div>
    </section>

    <!-- ══ PARTNERS ══ -->
    <section class="partners-section">
      <div class="container">
        <p class="partners-label">شركاء وجهات مرتبطة</p>
        <div class="partners-row">
          <div v-for="p in partners" :key="p" class="partner-chip">
            <Building2 :size="14" class="partner-ico" />
            {{ p }}
          </div>
        </div>
      </div>
    </section>

    <!-- ══ FAQ ══ -->
    <section class="section bg-indigo-soft">
      <div class="container faq-container">
        <div class="sec-header-c">
          <div class="sec-label">الأسئلة الشائعة</div>
          <h2 class="sec-heading center">أسئلة يطرحها أعضاؤنا</h2>
          <p class="sec-sub" style="margin:0 auto">هل لديك سؤال؟ إليك أكثر الأسئلة شيوعاً حول الاتحاد وخدماته</p>
        </div>
        <div class="faq-list">
          <div v-for="(f, i) in faqs" :key="i" class="faq-item" :class="{ open: openFaq === i }">
            <button class="faq-q" @click="toggleFaq(i)">
              <span>{{ f.q }}</span>
              <span class="faq-arrow"><ChevronDown :size="18" /></span>
            </button>
            <div class="faq-a-wrap">
              <p class="faq-a">{{ f.a }}</p>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- ══ CONTACT ══ -->
    <section id="contact" class="section">
      <div class="container contact-grid">
        <div>
          <div class="sec-label">تواصل معنا</div>
          <h2 class="sec-heading">نحن هنا لمساعدتك</h2>
          <p class="about-p" style="margin-top:.75rem">
            لا تتردد في التواصل معنا لأي استفسار أو طلب خدمة. فريقنا جاهز لخدمتك في مقرنا الرئيسي وجميع فروعنا.
          </p>
          <div class="contact-items">
            <div class="c-item">
              <div class="c-icon" style="--cic:#e8eaf6;--cicc:#1a237e"><MapPin :size="18" /></div>
              <div><strong>العنوان</strong><p>رام الله، فلسطين — شارع الإرسال</p></div>
            </div>
            <div class="c-item">
              <div class="c-icon" style="--cic:#fff8e1;--cicc:#e65100"><Phone :size="18" /></div>
              <div><strong>الهاتف</strong><p dir="ltr">+970 2 000 0000</p></div>
            </div>
            <div class="c-item">
              <div class="c-icon" style="--cic:#fce4ec;--cicc:#c62828"><Mail :size="18" /></div>
              <div><strong>البريد الإلكتروني</strong><p>info@pcu.ps</p></div>
            </div>
            <div class="c-item">
              <div class="c-icon" style="--cic:#e8f5e9;--cicc:#2e7d32"><Clock :size="18" /></div>
              <div><strong>ساعات العمل</strong><p>الأحد — الخميس: ٨ص — ٤م</p></div>
            </div>
          </div>

          <!-- WhatsApp quick -->
          <a href="https://wa.me/970200000000" class="whatsapp-btn" target="_blank" rel="noopener">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/><path d="M12 0C5.373 0 0 5.373 0 12c0 2.123.558 4.116 1.533 5.845L.073 23.25a.5.5 0 0 0 .613.613l5.405-1.46A11.94 11.94 0 0 0 12 24c6.627 0 12-5.373 12-12S18.627 0 12 0zm0 22a9.94 9.94 0 0 1-5.163-1.444l-.371-.221-3.809 1.03 1.03-3.809-.221-.371A9.94 9.94 0 0 1 2 12C2 6.477 6.477 2 12 2s10 4.477 10 10-4.477 10-10 10z"/></svg>
            تواصل عبر واتساب
          </a>
        </div>

        <div class="contact-form-wrap">
          <h3 class="form-h">
            <MessageSquare :size="20" class="form-h-ico" /> أرسل لنا رسالة
          </h3>
          <div class="form-row">
            <div class="form-group">
              <label>الاسم الكامل *</label>
              <input type="text" placeholder="الاسم الكامل" class="fi" />
            </div>
            <div class="form-group">
              <label>رقم الهاتف</label>
              <input type="text" placeholder="رقم الهاتف" class="fi" />
            </div>
          </div>
          <div class="form-group">
            <label>البريد الإلكتروني *</label>
            <input type="email" placeholder="البريد الإلكتروني" class="fi" />
          </div>
          <div class="form-group">
            <label>الموضوع</label>
            <input type="text" placeholder="موضوع الرسالة" class="fi" />
          </div>
          <div class="form-group">
            <label>الرسالة *</label>
            <textarea placeholder="اكتب رسالتك هنا..." class="ft" rows="4" />
          </div>
          <button class="btn-primary" style="width:100%">
            <Mail :size="16" /> إرسال الرسالة
          </button>
        </div>
      </div>
    </section>

  </div>
</template>

<style scoped>
@import url('https://fonts.cdnfonts.com/css/neo-sans-arabic');
@import url('https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;800;900&family=Tajawal:wght@300;400;500;700;800;900&display=swap');
@import url('https://fonts.cdnfonts.com/css/dubai');

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

:global(:root) {
  --navy:        #0d1b4b;
  --navy-mid:    #1a237e;
  --navy-bright: #3949ab;
  --navy-light:  #e8eaf6;
  --navy-soft:   #f5f7ff;
  --gold:        #f9a825;
  --gold-dark:   #e65100;
  --gold-light:  #fff8e1;
  --red:         #c62828;
  --red-light:   #fce4ec;
  --green:       #2e7d32;
  --green-light: #e8f5e9;
  --text-h:      #0d1b3e;
  --text-b:      #374151;
  --text-m:      #6b7280;
  --border:      #e5e7eb;
  --shadow-sm:   0 1px 3px rgba(0,0,0,.08);
  --shadow-md:   0 4px 16px rgba(0,0,0,.1);
  --shadow-lg:   0 12px 40px rgba(0,0,0,.12);
}

.lp {
  font-family: 'Dubai', 'Neo Sans Arabic', 'Tajawal', 'Neo Sans Arabic', 'Cairo', sans-serif;
  direction: rtl;
  color: var(--text-b);
  background: #ffffff;
  overflow-x: hidden;
}

.container { max-width: 1240px; margin: 0 auto; padding: 0 1.5rem; }

/* ─── Top Bar ──────────────────────────────────────────────────────────────── */
.top-bar {
  background: var(--navy);
  padding: .45rem 0;
  border-bottom: 1px solid rgba(255,255,255,.1);
}
.tb-inner { display: flex; align-items: center; justify-content: space-between; }
.tb-contacts { display: flex; gap: 1.5rem; }
.tb-link {
  color: rgba(255,255,255,.8); font-size: .78rem; text-decoration: none;
  font-weight: 500; display: flex; align-items: center; gap: .35rem;
  transition: color .2s;
}
.tb-link:hover { color: var(--gold); }
.tb-ico { flex-shrink: 0; }
.tb-social { display: flex; gap: .4rem; }
.tb-soc {
  width: 24px; height: 24px; border-radius: 5px;
  background: rgba(255,255,255,.12); color: rgba(255,255,255,.8);
  display: flex; align-items: center; justify-content: center;
  font-size: .72rem; text-decoration: none;
  transition: background .2s, color .2s; border: 1px solid rgba(255,255,255,.15);
}
.tb-soc:hover { background: var(--gold); color: var(--navy); border-color: var(--gold); }

/* ─── Navbar ──────────────────────────────────────────────────────────────── */
.navbar {
  position: sticky; top: 0; z-index: 200;
  background: rgba(255,255,255,.95);
  backdrop-filter: blur(12px);
  border-bottom: 1px solid var(--border);
  padding: .7rem 0;
  transition: box-shadow .3s, background .3s;
}
.navbar.scrolled {
  box-shadow: 0 4px 24px rgba(13,27,75,.12);
  background: rgba(255,255,255,.98);
}
.nb-inner { display: flex; align-items: center; gap: 1.25rem; }

.brand { display: flex; align-items: center; gap: .7rem; text-decoration: none; flex-shrink: 0; }
.brand-logo { height: 54px; width: auto; object-fit: contain; }
.brand-text { display: flex; flex-direction: column; line-height: 1.25; }
.brand-ar { font-size: .9rem; font-weight: 900; color: var(--navy); font-family: 'Neo Sans Arabic', 'Cairo', sans-serif; }
.brand-en { font-size: .65rem; color: var(--text-m); letter-spacing: .03em; }

.nav-links { display: flex; gap: .1rem; margin-inline-start: auto; margin-inline-end: .5rem; }
.nav-link {
  padding: .45rem .85rem; color: var(--text-b); font-size: .875rem;
  font-weight: 600; text-decoration: none; border-radius: 7px;
  transition: all .2s; white-space: nowrap; position: relative;
}
.nav-link:hover { color: var(--navy-mid); background: var(--navy-light); }

.nb-actions { flex-shrink: 0; }
.btn-register {
  display: flex; align-items: center; gap: .45rem;
  background: linear-gradient(135deg, var(--navy-mid), var(--navy));
  color: #fff; border: none; border-radius: 9px;
  padding: .55rem 1.3rem; font-size: .85rem; font-weight: 700;
  cursor: pointer; font-family: inherit; white-space: nowrap;
  transition: all .25s; box-shadow: 0 4px 14px rgba(13,27,75,.25);
}
.btn-register:hover {
  background: linear-gradient(135deg, var(--navy-bright), var(--navy-mid));
  transform: translateY(-2px); box-shadow: 0 6px 20px rgba(13,27,75,.3);
}

.hamburger { display: none; background: none; border: none; cursor: pointer; padding: 4px; flex-shrink: 0; color: var(--text-b); }
.mobile-menu { padding: .75rem 1.5rem 1rem; border-top: 1px solid var(--border); display: flex; flex-direction: column; gap: .25rem; background: #fff; }
.mob-link { padding: .6rem .75rem; color: var(--text-b); font-size: .9rem; font-weight: 600; text-decoration: none; border-radius: 7px; transition: all .2s; }
.mob-link:hover { background: var(--navy-light); color: var(--navy); }
.mob-btn { width: 100%; justify-content: center; margin-top: .5rem; border-radius: 9px; padding: .65rem 1.5rem; }

/* ─── Hero ─────────────────────────────────────────────────────────────────── */
.hero {
  position: relative;
  background: linear-gradient(145deg, #0d1b4b 0%, #1a237e 35%, #283593 65%, #1e3a8a 100%);
  min-height: 92vh;
  display: flex; align-items: center;
  overflow: hidden;
}
.hero-overlay {
  position: absolute; inset: 0;
  background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.03'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
}
.hero-shapes { position: absolute; inset: 0; pointer-events: none; }
.hs { position: absolute; border-radius: 50%; }
.hs-1 { width: 600px; height: 600px; background: radial-gradient(circle, rgba(249,168,37,.12), transparent 65%); top: -150px; right: -100px; }
.hs-2 { width: 400px; height: 400px; background: radial-gradient(circle, rgba(255,255,255,.06), transparent 65%); bottom: -50px; left: 10%; }
.hs-3 { width: 300px; height: 300px; background: radial-gradient(circle, rgba(198,40,40,.12), transparent 65%); top: 30%; left: 5%; }
.hs-4 { width: 200px; height: 200px; background: radial-gradient(circle, rgba(57,73,171,.3), transparent 65%); top: 60%; right: 20%; }

.hero-wave { position: absolute; bottom: 0; left: 0; right: 0; line-height: 0; }
.hero-wave svg { width: 100%; height: 80px; display: block; }

.hero-inner {
  position: relative; z-index: 2;
  display: grid; grid-template-columns: 1.1fr .9fr; gap: 4rem; align-items: center;
  padding: 5rem 1.5rem 7rem;
}

.hero-eyebrow {
  display: flex; align-items: center; gap: .5rem;
  font-size: .8rem; font-weight: 700; color: var(--gold);
  letter-spacing: .06em; margin-bottom: 1.5rem;
  text-transform: uppercase;
}
.eyebrow-dot { width: 8px; height: 8px; border-radius: 50%; background: var(--gold); flex-shrink: 0; animation: pulse-dot 2s infinite; }
@keyframes pulse-dot { 0%,100%{transform:scale(1);opacity:1} 50%{transform:scale(1.3);opacity:.7} }

.hero-title {
  font-family: 'Neo Sans Arabic', 'Cairo', sans-serif;
  font-size: clamp(2.2rem, 4.5vw, 3.5rem);
  font-weight: 900; color: #fff;
  line-height: 1.2; margin-bottom: 1.5rem;
}
.hero-accent { color: var(--gold); }
.hero-accent-gold { color: #ffd54f; }

.hero-desc {
  color: rgba(255,255,255,.82); font-size: 1.05rem;
  line-height: 1.9; max-width: 560px; margin-bottom: 2.25rem;
}

.hero-actions { display: flex; gap: .875rem; flex-wrap: wrap; margin-bottom: 2rem; }

.btn-hero-primary {
  display: flex; align-items: center; gap: .5rem;
  background: linear-gradient(135deg, var(--gold), #e65100);
  color: #fff; border: none; border-radius: 10px;
  padding: .875rem 1.75rem; font-size: .95rem; font-weight: 800;
  cursor: pointer; font-family: inherit;
  transition: all .25s; box-shadow: 0 6px 20px rgba(249,168,37,.35);
}
.btn-hero-primary:hover { transform: translateY(-3px); box-shadow: 0 10px 30px rgba(249,168,37,.45); filter: brightness(1.05); }

.btn-hero-secondary {
  display: flex; align-items: center; gap: .5rem;
  background: rgba(255,255,255,.15);
  color: #fff; border: 1.5px solid rgba(255,255,255,.4);
  border-radius: 10px; padding: .875rem 1.75rem;
  font-size: .95rem; font-weight: 700;
  text-decoration: none; font-family: inherit;
  backdrop-filter: blur(8px);
  transition: all .25s;
}
.btn-hero-secondary:hover { background: rgba(255,255,255,.25); border-color: rgba(255,255,255,.7); transform: translateY(-2px); }

.btn-hero-outline {
  display: flex; align-items: center; gap: .5rem;
  background: transparent; color: rgba(255,255,255,.75);
  border: 1.5px solid rgba(255,255,255,.3);
  border-radius: 10px; padding: .875rem 1.75rem;
  font-size: .95rem; font-weight: 600;
  text-decoration: none; font-family: inherit;
  transition: all .25s;
}
.btn-hero-outline:hover { color: #fff; border-color: rgba(255,255,255,.6); transform: translateY(-2px); }

.hero-trust { display: flex; gap: .75rem; flex-wrap: wrap; align-items: center; }
.trust-item { display: flex; align-items: center; gap: .3rem; font-size: .8rem; color: rgba(255,255,255,.7); font-weight: 500; }
.trust-ico { color: #69f0ae; flex-shrink: 0; }
.trust-sep { color: rgba(255,255,255,.3); }

/* Hero Visual */
.hero-visual { display: flex; justify-content: center; align-items: center; }
.hero-card-wrap { position: relative; width: 340px; height: 340px; }
.hero-main-card {
  width: 100%; height: 100%;
  background: rgba(255,255,255,.1);
  backdrop-filter: blur(16px);
  border: 1px solid rgba(255,255,255,.2);
  border-radius: 30px;
  display: flex; align-items: center; justify-content: center;
  position: relative; overflow: hidden;
  box-shadow: 0 15px 35px rgba(0,0,0,.3);
}
.hero-bg-img {
  position: absolute;
  inset: 0;
  width: 100%; height: 100%;
  object-fit: cover;
  transition: transform 6s cubic-bezier(0.25, 0.46, 0.45, 0.94);
  z-index: 0;
}
.hero-main-card:hover .hero-bg-img {
  transform: scale(1.1);
}
.hero-img-overlay {
  position: absolute;
  inset: 0;
  background: linear-gradient(135deg, rgba(13,27,75,0.7) 0%, rgba(26,35,126,0.85) 100%);
  z-index: 1;
}
.hero-logo { width: 220px; max-width: 80%; filter: drop-shadow(0 10px 30px rgba(0,0,0,.3)) brightness(1.1); position: relative; z-index: 2; }
.hero-ring {
  position: absolute; border-radius: 50%;
  border: 1.5px dashed rgba(255,255,255,.15);
  animation: spin-slow 20s linear infinite;
}
.hero-ring-1 { width: 320px; height: 320px; inset: -10px; }
.hero-ring-2 { width: 380px; height: 380px; inset: -30px; animation-duration: 30s; animation-direction: reverse; }
@keyframes spin-slow { to { transform: rotate(360deg); } }

.hero-float-card {
  position: absolute;
  background: rgba(255,255,255,.95);
  backdrop-filter: blur(12px);
  border-radius: 14px;
  padding: .75rem 1rem;
  display: flex; align-items: center; gap: .65rem;
  box-shadow: 0 8px 24px rgba(0,0,0,.2);
  border: 1px solid rgba(255,255,255,.8);
  animation: float 3s ease-in-out infinite;
}
.fc-top { top: -20px; right: -30px; animation-delay: 0s; }
.fc-bottom { bottom: -20px; left: -20px; animation-delay: 1.5s; }
.fc-side { top: 50%; right: -60px; transform: translateY(-50%); animation-delay: .75s; }
@keyframes float { 0%,100%{transform:translateY(0)} 50%{transform:translateY(-8px)} }
.fc-side { animation: float-side 3s ease-in-out infinite; }
@keyframes float-side { 0%,100%{transform:translateY(-50%)} 50%{transform:translateY(calc(-50% - 8px))} }
.fc-icon { width: 40px; height: 40px; border-radius: 10px; background: var(--navy-light); color: var(--navy); display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.fc-num { font-size: 1.1rem; font-weight: 900; color: var(--navy); font-family: 'Neo Sans Arabic', 'Cairo', sans-serif; line-height: 1.2; }
.fc-lbl { font-size: .7rem; color: var(--text-m); font-weight: 600; }

/* ─── Stats ────────────────────────────────────────────────────────────────── */
.stats-section {
  background: #fff;
  padding: 2.5rem 0;
  border-bottom: 1px solid var(--border);
  box-shadow: 0 4px 20px rgba(0,0,0,.05);
}
.stats-grid { display: grid; grid-template-columns: repeat(6,1fr); gap: 1rem; }
.stat-card {
  display: flex; flex-direction: column; align-items: center; gap: .35rem;
  padding: 1.5rem 1rem; text-align: center;
  border-radius: 14px; border: 1px solid var(--border);
  transition: transform .25s, box-shadow .25s;
}
.stat-card:hover { transform: translateY(-4px); box-shadow: var(--shadow-md); }
.stat-icon-box {
  width: 56px; height: 56px; border-radius: 14px;
  border: 1.5px solid;
  display: flex; align-items: center; justify-content: center;
  margin-bottom: .35rem;
}
.stat-val { font-size: 2rem; font-weight: 900; font-family: 'Neo Sans Arabic', 'Cairo', sans-serif; line-height: 1; }
.stat-lbl { font-size: .79rem; font-weight: 600; color: var(--text-m); }

/* ─── Why Us ──────────────────────────────────────────────────────────────── */
.why-grid { display: grid; grid-template-columns: repeat(4,1fr); gap: 1.25rem; }
.why-card {
  background: var(--wbg); border: 1.5px solid var(--wborder);
  border-radius: 16px; padding: 1.75rem 1.5rem;
  transition: transform .25s, box-shadow .25s;
  position: relative; overflow: hidden;
}
.why-card::before {
  content: ''; position: absolute; top: 0; right: 0;
  width: 60px; height: 60px; border-radius: 0 0 0 60px;
  background: var(--wborder); opacity: .08;
}
.why-card:hover { transform: translateY(-5px); box-shadow: var(--shadow-md); }
.why-icon-box {
  width: 52px; height: 52px; border-radius: 12px;
  border: 1.5px solid; display: flex; align-items: center;
  justify-content: center; margin-bottom: 1.1rem;
  transition: transform .2s;
}
.why-card:hover .why-icon-box { transform: scale(1.08) rotate(-3deg); }
.why-title { font-size: .95rem; font-weight: 800; color: var(--text-h); margin-bottom: .4rem; font-family: 'Neo Sans Arabic', 'Cairo', sans-serif; }
.why-desc { font-size: .82rem; color: var(--text-m); line-height: 1.7; }

/* ─── Sections ─────────────────────────────────────────────────────────────── */
.section { padding: 5rem 0; }
.bg-indigo-soft { background: var(--navy-soft); }
.bg-dots {
  background-image: radial-gradient(var(--navy-light) 1.5px, transparent 1.5px);
  background-size: 28px 28px; background-color: #fff;
}

.sec-label {
  display: inline-block; background: var(--navy-light); color: var(--navy);
  border: 1px solid #c5cae9; border-radius: 50px;
  padding: .28rem 1.1rem; font-size: .77rem; font-weight: 700;
  letter-spacing: .05em; margin-bottom: .7rem;
}
.sec-heading {
  font-family: 'Neo Sans Arabic', 'Cairo', sans-serif;
  font-size: clamp(1.6rem, 3.5vw, 2.3rem);
  font-weight: 900; color: var(--text-h); line-height: 1.3;
  margin-bottom: .6rem; position: relative; padding-bottom: .75rem;
}
.sec-heading::after {
  content: ''; position: absolute; bottom: 0; right: 0;
  width: 48px; height: 4px; background: linear-gradient(90deg, var(--gold), var(--gold-dark));
  border-radius: 2px;
}
.sec-heading.center { text-align: center; }
.sec-heading.center::after { right: 50%; transform: translateX(50%); }
.sec-sub { font-size: .95rem; color: var(--text-m); line-height: 1.8; max-width: 600px; }
.sec-header-c { text-align: center; margin-bottom: 3rem; }
.sec-header-c .sec-sub { margin: .75rem auto 0; }
.sec-header-split { display: flex; align-items: flex-end; justify-content: space-between; margin-bottom: 2.75rem; flex-wrap: wrap; gap: 1rem; }

.btn-navy-outline {
  display: inline-flex; align-items: center; gap: .35rem;
  background: transparent; color: var(--navy-mid);
  border: 2px solid var(--navy-mid); border-radius: 9px;
  padding: .7rem 1.5rem; font-size: .875rem; font-weight: 700;
  text-decoration: none; font-family: inherit;
  transition: all .2s;
}
.btn-navy-outline:hover { background: var(--navy-mid); color: #fff; transform: translateY(-1px); }
.btn-navy-outline.small { padding: .45rem 1.1rem; font-size: .82rem; }

.btn-primary {
  display: inline-flex; align-items: center; gap: .5rem;
  background: linear-gradient(135deg, var(--navy-mid), var(--navy));
  color: #fff; border: none; border-radius: 10px;
  padding: .9rem 2.25rem; font-size: .95rem; font-weight: 800;
  cursor: pointer; font-family: inherit;
  transition: all .25s; box-shadow: 0 4px 16px rgba(13,27,75,.25);
}
.btn-primary:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(13,27,75,.35); filter: brightness(1.05); }

/* ─── Services ─────────────────────────────────────────────────────────────── */
.srv-grid { display: grid; grid-template-columns: repeat(4,1fr); gap: 1.5rem; }
.srv-card {
  background: #fff; border: 1.5px solid var(--border); border-radius: 16px;
  padding: 2rem 1.5rem; text-decoration: none; color: inherit;
  transition: all .3s; display: flex; flex-direction: column; gap: .5rem;
  position: relative; overflow: hidden;
}
.srv-card::after {
  content: ''; position: absolute; bottom: 0; right: 0; left: 0;
  height: 3px; background: linear-gradient(90deg, var(--navy-mid), var(--gold));
  transform: scaleX(0); transition: transform .3s; transform-origin: right;
}
.srv-card:hover { box-shadow: 0 12px 36px rgba(13,27,75,.12); transform: translateY(-5px); border-color: #c5cae9; }
.srv-card:hover::after { transform: scaleX(1); }
.srv-icon-box {
  width: 54px; height: 54px; border-radius: 14px;
  background: var(--navy-light); color: var(--navy);
  display: flex; align-items: center; justify-content: center;
  margin-bottom: .75rem; transition: all .3s;
}
.srv-card:hover .srv-icon-box { background: linear-gradient(135deg, var(--navy), var(--navy-mid)); color: #fff; transform: scale(1.08); }
.srv-title { font-size: .95rem; font-weight: 800; color: var(--text-h); font-family: 'Neo Sans Arabic', 'Cairo', sans-serif; }
.srv-desc { font-size: .84rem; color: var(--text-m); line-height: 1.75; flex: 1; }
.srv-more { font-size: .82rem; font-weight: 700; color: var(--navy-mid); display: flex; align-items: center; gap: .2rem; margin-top: auto; opacity: 0; transition: opacity .2s; }
.srv-card:hover .srv-more { opacity: 1; }

/* ─── Sectors ──────────────────────────────────────────────────────────────── */
.sectors-grid { display: grid; grid-template-columns: repeat(4,1fr); gap: 1.25rem; }
.sector-card {
  background: #fff; border: 1.5px solid var(--border); border-radius: 16px;
  padding: 2rem 1.25rem; text-align: center;
  transition: all .3s; cursor: default;
}
.sector-card:hover { transform: translateY(-5px); box-shadow: var(--shadow-md); border-color: #c5cae9; }
.sector-icon {
  width: 64px; height: 64px; border-radius: 18px;
  border: 1.5px solid; display: flex; align-items: center; justify-content: center;
  margin: 0 auto 1.25rem; transition: transform .3s;
}
.sector-card:hover .sector-icon { transform: scale(1.1) rotate(-5deg); }
.sector-title { font-size: .95rem; font-weight: 800; color: var(--text-h); margin-bottom: .4rem; font-family: 'Neo Sans Arabic', 'Cairo', sans-serif; }
.sector-desc { font-size: .8rem; color: var(--text-m); line-height: 1.6; }

/* ─── About ────────────────────────────────────────────────────────────────── */
.about-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 5rem; align-items: center; }
.about-card-big {
  background: linear-gradient(145deg, var(--navy-light) 0%, #fff 100%);
  border: 1.5px solid #c5cae9; border-radius: 24px;
  padding: 3rem 2rem; text-align: center; position: relative; overflow: hidden;
  box-shadow: var(--shadow-md);
}
.about-logo-big { width: 220px; max-width: 100%; filter: drop-shadow(0 8px 20px rgba(13,27,75,.15)); }
.about-badge-float {
  position: absolute; bottom: 1.25rem; left: 1.25rem;
  background: linear-gradient(135deg, var(--gold), #e65100);
  border-radius: 14px; padding: .8rem 1.25rem; text-align: center;
  box-shadow: 0 4px 14px rgba(249,168,37,.4);
}
.float-num { display: block; font-size: 1.8rem; font-weight: 900; color: #fff; line-height: 1; font-family: 'Neo Sans Arabic', 'Cairo', sans-serif; }
.float-lbl { display: block; font-size: .73rem; font-weight: 600; color: rgba(255,255,255,.85); }
.about-badge-top {
  position: absolute; top: 1rem; right: 1rem;
  background: var(--green); color: #fff; border-radius: 50px;
  padding: .3rem .85rem; font-size: .75rem; font-weight: 700;
  display: flex; align-items: center; gap: .35rem;
}
.about-stat-cards { display: grid; grid-template-columns: repeat(3,1fr); gap: .75rem; margin-top: 1rem; }
.mini-stat {
  background: var(--abg); border: 1.5px solid var(--ac); border-radius: 14px;
  padding: 1.1rem .75rem; text-align: center;
}
.mini-val { display: block; font-size: 1.45rem; font-weight: 900; color: var(--ac); font-family: 'Neo Sans Arabic', 'Cairo', sans-serif; }
.mini-lbl { display: block; font-size: .72rem; color: var(--text-m); font-weight: 600; }

.about-pillars { display: flex; flex-direction: column; gap: .875rem; margin: 1.25rem 0; }
.pillar { display: flex; align-items: flex-start; gap: .875rem; padding: 1rem; background: var(--navy-soft); border-radius: 12px; border: 1px solid var(--border); }
.pillar-icon { width: 36px; height: 36px; border-radius: 9px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.pillar-title { font-size: .88rem; font-weight: 800; color: var(--text-h); margin-bottom: .2rem; font-family: 'Neo Sans Arabic', 'Cairo', sans-serif; }
.pillar-desc { font-size: .82rem; color: var(--text-m); line-height: 1.6; }

.about-p { color: var(--text-b); font-size: .95rem; line-height: 1.9; margin-bottom: .9rem; }
.about-list { list-style: none; margin: 1rem 0 1.75rem; }
.about-list li { padding: .4rem 0; font-size: .9rem; color: var(--text-b); display: flex; align-items: center; gap: .5rem; border-bottom: 1px solid var(--border); }
.about-list li:last-child { border-bottom: none; }
.list-ico { color: var(--green); flex-shrink: 0; }

/* ─── Classification ───────────────────────────────────────────────────────── */
.deg-grid { display: grid; grid-template-columns: repeat(6,1fr); gap: 1rem; margin-bottom: 2.5rem; }
.deg-card {
  background: var(--dbg); border: 1.5px solid var(--dborder);
  border-top: 5px solid var(--dborder); border-radius: 14px;
  padding: 1.75rem 1rem; text-align: center;
  transition: transform .25s, box-shadow .25s;
}
.deg-card:hover { transform: translateY(-4px); box-shadow: var(--shadow-md); }
.deg-code { display: block; font-size: 1.6rem; font-weight: 900; color: var(--dclr); font-family: 'Neo Sans Arabic', 'Cairo', sans-serif; }
.deg-label { font-size: .82rem; font-weight: 800; color: var(--dclr); margin: .5rem 0 .25rem; }
.deg-desc { font-size: .73rem; color: var(--text-m); line-height: 1.55; }
.deg-cta { text-align: center; }
.deg-cta-text { font-size: .95rem; color: var(--text-m); margin-bottom: 1.25rem; }

/* ─── News ─────────────────────────────────────────────────────────────────── */
.news-grid { display: grid; grid-template-columns: repeat(3,1fr); gap: 1.75rem; }
.news-card {
  background: #fff; border: 1px solid var(--border); border-radius: 16px;
  overflow: hidden; text-decoration: none; color: inherit;
  display: flex; flex-direction: column;
  transition: all .3s; box-shadow: var(--shadow-sm);
}
.news-card:hover { box-shadow: 0 12px 36px rgba(13,27,75,.12); transform: translateY(-5px); }
.news-thumb { position: relative; height: 210px; background: var(--navy-light); overflow: hidden; flex-shrink: 0; }
.news-img { width: 100%; height: 100%; object-fit: cover; transition: transform .5s; }
.news-card:hover .news-img { transform: scale(1.06); }
.news-ph { width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; color: var(--navy-mid); background: linear-gradient(135deg, var(--navy-light), #c5cae9); }
.news-badge { position: absolute; top: .875rem; right: .875rem; background: var(--navy); color: #fff; font-size: .72rem; font-weight: 700; padding: .25rem .75rem; border-radius: 50px; }
.news-body { padding: 1.5rem; flex: 1; display: flex; flex-direction: column; gap: .45rem; }
.news-date { font-size: .78rem; color: var(--gold-dark); font-weight: 700; display: flex; align-items: center; gap: .3rem; }
.news-title { font-size: .95rem; font-weight: 800; line-height: 1.55; color: var(--text-h); display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; font-family: 'Neo Sans Arabic', 'Cairo', sans-serif; }
.news-exc { font-size: .83rem; color: var(--text-m); line-height: 1.7; flex: 1; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; }
.news-more { font-size: .83rem; font-weight: 700; color: var(--navy-mid); margin-top: auto; display: flex; align-items: center; gap: .2rem; }
.news-skel { pointer-events: none; }
.skel-thumb { height: 210px; background: linear-gradient(90deg, #e8eaf6 25%, #f5f7ff 50%, #e8eaf6 75%); background-size: 400% 100%; animation: shimmer 1.5s infinite; }
.skel-line { height: 11px; background: linear-gradient(90deg, #e8eaf6 25%, #f5f7ff 50%, #e8eaf6 75%); background-size: 400% 100%; border-radius: 5px; margin-bottom: 9px; animation: shimmer 1.5s infinite; }
.skel-line.w35 { width: 35%; } .skel-line.w60 { width: 60%; }
@keyframes shimmer { to { background-position: -400% 0; } }

/* ─── Training ─────────────────────────────────────────────────────────────── */
.courses-grid { display: grid; grid-template-columns: repeat(3,1fr); gap: 1.5rem; }
.course-card {
  background: #fff; border: 1.5px solid var(--border); border-radius: 16px;
  padding: 1.5rem; display: flex; gap: 1rem; align-items: flex-start;
  transition: all .3s; position: relative; overflow: hidden;
}
.course-card:hover { box-shadow: var(--shadow-md); transform: translateY(-4px); border-color: #c5cae9; }
.course-icon { width: 54px; height: 54px; border-radius: 14px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; transition: transform .2s; }
.course-card:hover .course-icon { transform: scale(1.08) rotate(-5deg); }
.course-body { flex: 1; }
.course-level { display: inline-block; font-size: .7rem; font-weight: 700; padding: .2rem .65rem; border-radius: 50px; margin-bottom: .5rem; }
.course-title { font-size: .92rem; font-weight: 800; color: var(--text-h); margin-bottom: .6rem; font-family: 'Neo Sans Arabic', 'Cairo', sans-serif; line-height: 1.45; }
.course-meta { display: flex; gap: .875rem; }
.course-meta span { display: flex; align-items: center; gap: .3rem; font-size: .76rem; color: var(--text-m); font-weight: 500; }
.course-btn {
  position: absolute; bottom: 1.25rem; left: 1.25rem;
  color: #fff; border: none; border-radius: 8px;
  padding: .4rem 1rem; font-size: .78rem; font-weight: 700;
  cursor: pointer; font-family: inherit;
  transition: all .2s; opacity: 0; transform: translateY(4px);
}
.course-card:hover .course-btn { opacity: 1; transform: translateY(0); }

/* ─── Testimonials ─────────────────────────────────────────────────────────── */
.testimonials-section { background: linear-gradient(145deg, var(--navy) 0%, #1e3a8a 100%); }
.testimonials-section .sec-label { background: rgba(255,255,255,.15); color: #fff; border-color: rgba(255,255,255,.2); }
.testimonials-section .sec-heading { color: #fff; }
.testimonials-section .sec-heading::after { background: var(--gold); }

.testi-wrap { display: flex; align-items: center; gap: 2rem; max-width: 820px; margin: 0 auto; }
.testi-card {
  flex: 1; background: rgba(255,255,255,.08); backdrop-filter: blur(12px);
  border: 1px solid rgba(255,255,255,.15); border-radius: 24px;
  padding: 2.5rem;
}
.testi-stars { color: var(--gold); display: flex; gap: .25rem; margin-bottom: 1.25rem; }
.testi-text { font-size: 1.05rem; color: rgba(255,255,255,.9); line-height: 1.9; margin-bottom: 2rem; font-style: italic; }
.testi-author { display: flex; align-items: center; gap: 1rem; }
.testi-avatar {
  width: 54px; height: 54px; border-radius: 50%;
  background: linear-gradient(135deg, var(--gold), #e65100);
  color: #fff; display: flex; align-items: center; justify-content: center;
  font-size: 1.3rem; font-weight: 800; flex-shrink: 0; font-family: 'Neo Sans Arabic', 'Cairo', sans-serif;
}
.testi-name { font-size: .95rem; font-weight: 800; color: #fff; font-family: 'Neo Sans Arabic', 'Cairo', sans-serif; }
.testi-role { font-size: .8rem; color: rgba(255,255,255,.65); margin-top: .15rem; }
.testi-city { font-size: .76rem; color: var(--gold); margin-top: .2rem; display: flex; align-items: center; gap: .25rem; }

.testi-nav {
  width: 48px; height: 48px; border-radius: 50%;
  background: rgba(255,255,255,.1); border: 1px solid rgba(255,255,255,.2);
  color: #fff; cursor: pointer;
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0; transition: all .2s;
}
.testi-nav:hover { background: var(--gold); border-color: var(--gold); color: var(--navy); }

.testi-dots { display: flex; justify-content: center; gap: .5rem; margin-top: 2rem; }
.testi-dot { width: 8px; height: 8px; border-radius: 50%; background: rgba(255,255,255,.3); border: none; cursor: pointer; transition: all .2s; }
.testi-dot.active { background: var(--gold); width: 24px; border-radius: 4px; }

/* ─── Partners ─────────────────────────────────────────────────────────────── */
.partners-section { padding: 2.25rem 0; background: #fff; border-top: 1px solid var(--border); border-bottom: 1px solid var(--border); }
.partners-label { font-size: .8rem; color: var(--text-m); font-weight: 700; text-align: center; margin-bottom: 1.25rem; letter-spacing: .05em; text-transform: uppercase; }
.partners-row { display: flex; align-items: center; justify-content: center; gap: .875rem; flex-wrap: wrap; }
.partner-chip {
  display: flex; align-items: center; gap: .5rem;
  padding: .5rem 1.35rem; border: 1.5px solid var(--border);
  border-radius: 50px; font-size: .83rem; color: var(--text-b);
  background: #fff; white-space: nowrap; font-weight: 600;
  transition: all .2s; cursor: default;
}
.partner-chip:hover { border-color: var(--navy-mid); color: var(--navy); background: var(--navy-light); }
.partner-ico { color: var(--navy-mid); flex-shrink: 0; }

/* ─── FAQ ──────────────────────────────────────────────────────────────────── */
.faq-container .faq-list { max-width: 800px; margin: 0 auto; }
.faq-item { border: 1.5px solid var(--border); border-radius: 14px; margin-bottom: .875rem; overflow: hidden; transition: border-color .2s, box-shadow .2s; background: #fff; }
.faq-item.open { border-color: var(--navy-mid); box-shadow: 0 4px 16px rgba(13,27,75,.1); }
.faq-q {
  width: 100%; background: none; border: none; padding: 1.25rem 1.5rem;
  display: flex; align-items: center; justify-content: space-between; gap: 1rem;
  text-align: right; cursor: pointer; font-family: inherit; font-size: .95rem;
  font-weight: 700; color: var(--text-h); transition: color .2s;
}
.faq-item.open .faq-q { color: var(--navy); }
.faq-arrow { flex-shrink: 0; transition: transform .3s; color: var(--text-m); }
.faq-item.open .faq-arrow { transform: rotate(-180deg); color: var(--navy); }
.faq-a-wrap { max-height: 0; overflow: hidden; transition: max-height .4s ease; }
.faq-item.open .faq-a-wrap { max-height: 200px; }
.faq-a { padding: 0 1.5rem 1.25rem; font-size: .9rem; color: var(--text-m); line-height: 1.85; }

/* ─── Contact ──────────────────────────────────────────────────────────────── */
.contact-grid { display: grid; grid-template-columns: 1fr 1.2fr; gap: 4rem; align-items: start; }
.contact-items { display: flex; flex-direction: column; gap: 1.25rem; margin-top: 1.5rem; }
.c-item { display: flex; align-items: flex-start; gap: .875rem; }
.c-icon { width: 44px; height: 44px; border-radius: 12px; background: var(--cic); color: var(--cicc); border: 1.5px solid var(--cicc); display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.c-item strong { display: block; font-size: .875rem; font-weight: 700; color: var(--text-h); margin-bottom: .2rem; }
.c-item p { font-size: .85rem; color: var(--text-m); margin: 0; line-height: 1.55; }

.whatsapp-btn {
  display: inline-flex; align-items: center; gap: .65rem;
  background: #25d366; color: #fff; border-radius: 10px;
  padding: .75rem 1.5rem; font-size: .9rem; font-weight: 700;
  text-decoration: none; margin-top: 1.5rem;
  transition: all .25s; box-shadow: 0 4px 14px rgba(37,211,102,.3);
}
.whatsapp-btn:hover { background: #128c7e; transform: translateY(-2px); box-shadow: 0 8px 24px rgba(37,211,102,.35); }

.contact-form-wrap {
  background: #fff; border: 1.5px solid #c5cae9;
  border-top: 5px solid var(--navy); border-radius: 20px;
  padding: 2.25rem; box-shadow: 0 8px 32px rgba(13,27,75,.08);
}
.form-h { font-size: 1.1rem; font-weight: 800; color: var(--text-h); margin-bottom: 1.5rem; display: flex; align-items: center; gap: .6rem; font-family: 'Neo Sans Arabic', 'Cairo', sans-serif; }
.form-h-ico { color: var(--navy); }
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: .875rem; margin-bottom: .875rem; }
.form-group { display: flex; flex-direction: column; gap: .35rem; margin-bottom: .875rem; }
.form-group label { font-size: .82rem; font-weight: 700; color: var(--text-h); }
.fi {
  width: 100%; border: 1.5px solid var(--border); border-radius: 9px;
  padding: .7rem 1rem; font-size: .875rem; font-family: inherit;
  color: var(--text-h); outline: none; transition: all .2s; background: #fafafa;
}
.fi:focus { border-color: var(--navy-mid); background: #fff; box-shadow: 0 0 0 3px rgba(57,73,171,.1); }
.ft {
  width: 100%; border: 1.5px solid var(--border); border-radius: 9px;
  padding: .7rem 1rem; font-size: .875rem; font-family: inherit;
  color: var(--text-h); outline: none; resize: vertical;
  background: #fafafa; transition: all .2s;
}
.ft:focus { border-color: var(--navy-mid); background: #fff; box-shadow: 0 0 0 3px rgba(57,73,171,.1); }

/* ─── Footer ───────────────────────────────────────────────────────────────── */
.footer { background: #0d1b4b; color: rgba(255,255,255,.8); }

.footer-top { background: linear-gradient(135deg, #1a237e, #283593); padding: 1.75rem 0; border-bottom: 1px solid rgba(255,255,255,.1); }
.footer-top-inner { }
.ft-cta { display: flex; align-items: center; justify-content: space-between; gap: 1.5rem; flex-wrap: wrap; }
.ft-cta-title { font-family: 'Neo Sans Arabic', 'Cairo', sans-serif; font-size: 1.3rem; font-weight: 900; color: #fff; margin-bottom: .25rem; }
.ft-cta-desc { font-size: .85rem; color: rgba(255,255,255,.7); }
.btn-gold {
  display: inline-flex; align-items: center; gap: .5rem;
  background: linear-gradient(135deg, var(--gold), #e65100);
  color: #fff; border: none; border-radius: 10px;
  padding: .8rem 1.75rem; font-size: .9rem; font-weight: 800;
  cursor: pointer; font-family: inherit; white-space: nowrap;
  transition: all .25s; box-shadow: 0 4px 14px rgba(249,168,37,.3);
}
.btn-gold:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(249,168,37,.4); }

.footer-grid {
  display: grid; grid-template-columns: 1.6fr 1fr 1fr 1fr;
  gap: 3rem; padding: 3.5rem 0 3rem;
  border-bottom: 1px solid rgba(255,255,255,.1);
}
.footer-logo { height: 64px; width: auto; object-fit: contain; margin-bottom: .75rem; filter: brightness(0) invert(1) opacity(.9); }
.footer-name { font-family: 'Neo Sans Arabic', 'Cairo', sans-serif; font-size: .95rem; font-weight: 900; color: #fff; margin-bottom: .2rem; }
.footer-en { font-size: .68rem; color: rgba(255,255,255,.5); margin-bottom: .875rem; letter-spacing: .04em; }
.footer-bio { font-size: .82rem; color: rgba(255,255,255,.55); line-height: 1.8; margin-bottom: 1.25rem; }
.footer-social { display: flex; gap: .5rem; }
.fsoc {
  width: 34px; height: 34px; border-radius: 8px;
  background: rgba(255,255,255,.1); color: rgba(255,255,255,.7);
  display: flex; align-items: center; justify-content: center;
  font-size: .82rem; text-decoration: none;
  border: 1px solid rgba(255,255,255,.1);
  transition: all .2s;
}
.fsoc:hover { background: var(--gold); color: var(--navy); border-color: var(--gold); }
.footer-col-h {
  font-family: 'Neo Sans Arabic', 'Cairo', sans-serif;
  font-size: .9rem; font-weight: 800; color: #fff;
  margin-bottom: 1.25rem; padding-bottom: .6rem;
  border-bottom: 2px solid var(--gold);
  display: inline-block;
}
.footer-link {
  display: block; color: rgba(255,255,255,.55); text-decoration: none;
  font-size: .84rem; margin-bottom: .6rem; transition: color .2s;
}
.footer-link:hover { color: var(--gold); }
.footer-contact {
  font-size: .83rem; color: rgba(255,255,255,.55);
  margin-bottom: .6rem; display: flex; align-items: center; gap: .4rem; line-height: 1.5;
}
.fco-ico { flex-shrink: 0; color: var(--gold); }
.footer-bottom { padding: 1.5rem 0; }
.footer-bottom-inner { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: .5rem; }
.footer-bottom-inner p { font-size: .8rem; color: rgba(255,255,255,.4); }
.footer-dev { font-size: .75rem; color: rgba(255,255,255,.3); }

/* ─── Floating WhatsApp ─────────────────────────────────────────────────────── */
.float-whatsapp {
  position: fixed; bottom: 2rem; left: 2rem; z-index: 500;
  background: #25d366; color: #fff; border-radius: 50px;
  padding: .7rem 1.25rem .7rem .9rem;
  display: flex; align-items: center; gap: .6rem;
  text-decoration: none; font-size: .85rem; font-weight: 700;
  box-shadow: 0 6px 20px rgba(37,211,102,.4);
  transition: all .3s; border: 2px solid rgba(255,255,255,.3);
  animation: float-wa 3s ease-in-out infinite;
}
.float-whatsapp:hover { background: #128c7e; transform: translateY(-3px) scale(1.04); box-shadow: 0 10px 28px rgba(37,211,102,.45); }
.float-whatsapp-label { white-space: nowrap; }
@keyframes float-wa { 0%,100%{transform:translateY(0)} 50%{transform:translateY(-5px)} }

.nb-user { display: flex; align-items: center; gap: .6rem; flex-shrink: 0; }
.nb-avatar { width: 36px; height: 36px; border-radius: 50%; background: linear-gradient(135deg, var(--navy-mid), var(--navy)); color: #fff; display: flex; align-items: center; justify-content: center; font-size: .95rem; font-weight: 800; flex-shrink: 0; }
.nb-name { font-size: .82rem; font-weight: 700; color: var(--navy); max-width: 140px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.nb-logout { background: transparent; border: 1.5px solid var(--border); border-radius: 7px; padding: .3rem .75rem; font-size: .78rem; color: var(--text-m); cursor: pointer; font-family: inherit; transition: all .2s; }
.nb-logout:hover { border-color: var(--red); color: var(--red); }

/* ─── Responsive ──────────────────────────────────────────────────────────────*/
@media (max-width: 1200px) {
  .stats-grid { grid-template-columns: repeat(3,1fr); }
  .srv-grid { grid-template-columns: repeat(2,1fr); }
  .why-grid { grid-template-columns: repeat(2,1fr); }
  .sectors-grid { grid-template-columns: repeat(4,1fr); }
}
@media (max-width: 1024px) {
  .footer-grid { grid-template-columns: 1fr 1fr; gap: 2rem; }
  .deg-grid { grid-template-columns: repeat(3,1fr); }
  .hero-inner { grid-template-columns: 1fr; }
  .hero-visual { display: none; }
  .hero-inner { padding: 4rem 1.5rem 6rem; }
  .about-grid { grid-template-columns: 1fr; gap: 3rem; }
  .contact-grid { grid-template-columns: 1fr; gap: 2.5rem; }
  .courses-grid { grid-template-columns: repeat(2,1fr); }
}
@media (max-width: 900px) {
  .nav-links { display: none; }
  .hamburger { display: flex; }
  .btn-register { display: none; }
  .news-grid { grid-template-columns: repeat(2,1fr); }
  .why-grid { grid-template-columns: repeat(2,1fr); }
  .sectors-grid { grid-template-columns: repeat(2,1fr); }
  .form-row { grid-template-columns: 1fr 1fr; }
}
@media (max-width: 600px) {
  .top-bar { display: none; }
  .srv-grid { grid-template-columns: 1fr; }
  .news-grid { grid-template-columns: 1fr; }
  .deg-grid { grid-template-columns: repeat(2,1fr); }
  .footer-grid { grid-template-columns: 1fr; }
  .hero-title { font-size: 2rem; }
  .form-row { grid-template-columns: 1fr; }
  .about-stat-cards { grid-template-columns: repeat(3,1fr); }
  .stats-grid { grid-template-columns: repeat(2,1fr); }
  .why-grid { grid-template-columns: 1fr; }
  .sectors-grid { grid-template-columns: repeat(2,1fr); }
  .courses-grid { grid-template-columns: 1fr; }
  .testi-wrap { flex-direction: column; gap: 1rem; }
  .testi-prev, .testi-next { display: none; }
  .float-whatsapp-label { display: none; }
  .float-whatsapp { padding: .7rem; border-radius: 50%; }
  .footer-bottom-inner { flex-direction: column; text-align: center; }
  .ft-cta { flex-direction: column; text-align: center; }
}
</style>

<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import api from '@/plugins/axios'
import {
  ArrowLeft, Award, Bell, Bookmark, CalendarDays, Check, ChevronDown, ChevronLeft, ChevronRight,
  ChevronUp, CreditCard, FileBadge, FileText, ImageIcon, Landmark, MapPin, Search, Stamp, Timer, Truck,
} from 'lucide-vue-next'

definePage({
  meta: { layout: 'landing', public: true, unauthenticatedOnly: false },
})

const router = useRouter()

// ─── Hero search ─────────────────────────────────────────────────────────────
const directoryTab = ref<'contractors' | 'suppliers'>('contractors')
const searchQuery = ref('')
function searchDirectory() {
  const q = searchQuery.value.trim()
  router.push({ path: '/landing/members', query: q ? { search: q } : {} })
}

// ─── Union overview tabs ─────────────────────────────────────────────────────
const aboutTab = ref<'about' | 'sectors' | 'degrees'>('about')
const unionStats = ref({ years: 30, branches: 11 })

const sectors = [
  'الإنشاءات العامة', 'البنية التحتية', 'المياه والصرف الصحي', 'الكهرباء والطاقة',
  'المباني الحكومية', 'الإسكان السكني', 'المشاريع الصناعية', 'المشاريع البيئية',
]
const degrees = [
  { code: 'A1', label: 'الأولى أ', desc: 'أعلى درجات التصنيف' },
  { code: 'A2', label: 'الأولى ب', desc: 'مشاريع متوسطة وكبيرة' },
  { code: 'B', label: 'الثانية', desc: 'مشاريع متوسطة الحجم' },
  { code: 'C', label: 'الثالثة', desc: 'مشاريع صغيرة ومتوسطة' },
  { code: 'D', label: 'الرابعة', desc: 'مشاريع صغيرة الحجم' },
  { code: 'E', label: 'الخامسة', desc: 'مقاولون ناشئون' },
]

// ─── Tenders ─────────────────────────────────────────────────────────────────
interface TenderCard { id: number | null; title: string; entity: string; category: string; isNew: boolean; deadline: number }
const DAY = 86_400_000
const sampleDeadline = () => Date.now() + 2 * DAY + 4 * 3_600_000 + 59 * 60_000 + 22_000
const tenders = ref<TenderCard[]>([
  { id: null, title: 'انشاء وتأهيل شبكة صرف صحي -حي الشيخ', entity: 'بلدية الخليل', category: 'المياه والصرف الصحي', isNew: true, deadline: sampleDeadline() },
  { id: null, title: 'انشاء وتأهيل شبكة صرف صحي -حي الشيخ', entity: 'بلدية الخليل', category: 'الاسكان والتطوير', isNew: true, deadline: sampleDeadline() },
  { id: null, title: 'انشاء وتأهيل شبكة صرف صحي -حي الشيخ', entity: 'بلدية الخليل', category: 'المياه والصرف الصحي', isNew: true, deadline: sampleDeadline() },
  { id: null, title: 'انشاء وتأهيل شبكة صرف صحي -حي الشيخ', entity: 'بلدية الخليل', category: 'الاسكان والتطوير', isNew: true, deadline: sampleDeadline() },
])
const tenderPage = ref(0)
const tendersPerView = 2
const tenderPages = computed(() => Math.max(1, Math.ceil(tenders.value.length / tendersPerView)))
const visibleTenders = computed(() => tenders.value.slice(tenderPage.value * tendersPerView, tenderPage.value * tendersPerView + tendersPerView))

const categoryTones = ['tone-yellow', 'tone-purple', 'tone-blue', 'tone-teal']
const fixedTones: Record<string, string> = { 'المياه والصرف الصحي': 'tone-yellow', 'الاسكان والتطوير': 'tone-purple' }
function categoryTone(cat: string) {
  if (fixedTones[cat]) return fixedTones[cat]
  let h = 0
  for (const ch of cat) h = (h + ch.charCodeAt(0)) % categoryTones.length
  return categoryTones[h]
}

const now = ref(Date.now())
let clock: ReturnType<typeof setInterval> | undefined
function countdown(deadline: number) {
  const diff = Math.max(0, deadline - now.value)
  const pad = (n: number) => String(n).padStart(2, '0')
  return {
    days: pad(Math.floor(diff / DAY)),
    hours: pad(Math.floor(diff / 3_600_000) % 24),
    minutes: pad(Math.floor(diff / 60_000) % 60),
    seconds: pad(Math.floor(diff / 1000) % 60),
  }
}
function openTender(t: TenderCard) {
  router.push(t.id ? { path: '/landing/public-tenders', query: { id: String(t.id) } } : '/landing/public-tenders')
}

// ─── Equipment marketplace ───────────────────────────────────────────────────
const governorates = ['كل المحافظات', 'الخليل', 'رام الله', 'نابلس', 'غزة']
const activeGovernorate = ref('كل المحافظات')
const equipment = Array.from({ length: 6 }, (_, i) => ({
  id: i,
  title: 'حفارة كاتربيلر 320',
  spec: 'سعة 20 كوب . مع سائق',
  city: 'رام الله',
  rent: 'تأجير شهري',
  condition: 'حالة ممتازة',
  image: '/images/landing/excavator.webp',
}))
const visibleEquipment = computed(() =>
  activeGovernorate.value === 'كل المحافظات' ? equipment : equipment.filter(e => e.city === activeGovernorate.value),
)

// ─── Circulars ───────────────────────────────────────────────────────────────
interface Circular { id: number | null; number: string; title: string; body: string; urgent: boolean; date: Date }
const circulars = ref<Circular[]>([{
  id: null,
  number: '24 / 2026',
  title: 'إقرار الضوابط التنفيذية الجديدة لمشاركة الشركات في عطاءات البنية التحتية',
  body: 'بناءً على جلسة مجلس الإدارة رقم (42)، تقرر اعتماد المعايير الفنية والمالية المعدلة للتصنيف النقابي الخاص بشركات البنية التحتية، ويستوجب على كافة المقاولين تقديم إقرارات الامتثال قبل نهاية الشهر الجاري.',
  urgent: true,
  date: new Date(2026, 8, 12),
}])
const circularIndex = ref(0)
const circular = computed(() => circulars.value[circularIndex.value])
const arMonth = (d: Date) => d.toLocaleDateString('ar-EG-u-nu-latn', { month: 'long' })

// ─── News & events ───────────────────────────────────────────────────────────
interface MediaCard { key: string; title: string; image: string; tags: { label: string; tone: string }[]; date: string; to: string }
const mediaTab = ref<'news' | 'events'>('events')
const PLACEHOLDER_MEDIA = '/images/landing/news-workers.webp'
const sampleEvent = (i: number): MediaCard => ({
  key: `sample-${i}`,
  title: 'المؤتمر السنوي العام لاتحاد المقاولين الفلسطينيين للعام 2025',
  image: PLACEHOLDER_MEDIA,
  tags: [{ label: 'فعالية محلية للاتحاد', tone: 'tag-blue' }, { label: 'اون لاين zoom', tone: 'tag-green' }],
  date: '15 نوفمبر 2025 . 10:00 ص',
  to: '/landing/events',
})
const newsCards = ref<MediaCard[]>([])
const eventCards = ref<MediaCard[]>([0, 1, 2].map(sampleEvent))
const mediaCards = computed(() => (mediaTab.value === 'news' && newsCards.value.length ? newsCards.value : eventCards.value))
const mediaPage = ref(0)
const mediaPages = computed(() => Math.max(1, Math.ceil(mediaCards.value.length / 3)))
const visibleMedia = computed(() => mediaCards.value.slice(mediaPage.value * 3, mediaPage.value * 3 + 3))
function setMediaTab(tab: 'news' | 'events') { mediaTab.value = tab; mediaPage.value = 0 }

function fmtDateTime(d: string | null | undefined) {
  if (!d) return ''
  const date = new Date(d)
  const day = date.toLocaleDateString('ar-EG-u-nu-latn', { day: 'numeric', month: 'long', year: 'numeric' })
  const time = date.toLocaleTimeString('ar-EG-u-nu-latn', { hour: '2-digit', minute: '2-digit' })
  return `${day} . ${time}`
}

// ─── App features ────────────────────────────────────────────────────────────
const appFeatures = [
  { icon: Bell, title: 'تنبيهات العطاءات الفورية', desc: 'إشعار لحظي فور نشر عطاء مطابق لتصنيف شركتك أو قرب موعد إغلاقه.' },
  { icon: FileBadge, title: 'شهادتك الرقمية في جيبك', desc: 'جدّد عضويتك السنوية واستخرج شهادة عضويتك الرقمية فور.' },
  { icon: Truck, title: 'سوق المعدات المتنقل', desc: 'تصفح او اعرض اليتك الثقيلة للايجار من هاتفك مباشرة' },
  { icon: CreditCard, title: 'دفع الرسوم الكترونيا', desc: 'سدد رسوم العضوية والتصنيف بأمان عبر حساباتك البنكية دون زيارة المقر' },
]

// ─── Testimonials ────────────────────────────────────────────────────────────
const testimonialText = 'انضمامنا لاتحاد المقاولين الفلسطينيين كان نقطة تحوّل حقيقية في مسيرة شركتنا. التصنيف الرسمي المعتمد فتح لنا الباب للتقدّم لعطاءات حكومية كبرى لم نكن نصل إليها من قبل، والدعم الفني للجنة التصنيف كان دقيقاً وسريعاً'
const testimonials = Array.from({ length: 6 }, (_, i) => ({
  id: i, text: testimonialText, name: 'م . سامي الخطيب', role: 'شركة مبارك للمقاولات . مدير العام', initials: 'س.خ',
}))
const testimonialPage = ref(0)
const testimonialPages = Math.ceil(testimonials.length / 3)
const visibleTestimonials = computed(() => testimonials.slice(testimonialPage.value * 3, testimonialPage.value * 3 + 3))

// ─── FAQ ─────────────────────────────────────────────────────────────────────
const faqs = [
  { q: 'كيف أجدد عضويتي السنوية في الاتحاد؟', a: 'من بطاقة "تجديد العضوية" في حسابك كمقاول، اضغط على "جدد الآن"، ثم أكمل سداد الرسوم إلكترونياً لتستلم شهادتك الرقمية المحدَّثة فوراً بضغطة زر.' },
  { q: 'ما المستندات المطلوبة لطلب التصنيف الجديد؟', a: 'السجل التجاري ساري المفعول، شهادة تسجيل الشركة، البيانات المالية لآخر سنتين، سجل الخبرات والمشاريع المنفّذة، وقائمة الكادر الفني والمعدات.' },
  { q: 'كيف أتحقق من صحة شهادة مقاول آخر؟', a: 'استخدم "دليل المقاولين" في أعلى الصفحة وابحث باسم الشركة أو رقم العضوية، أو امسح رمز QR المطبوع على الشهادة للتحقق الفوري من حالتها.' },
  { q: 'هل يمكنني التقدّم لعطاء دون امتلاك تصنيف معتمد؟', a: 'أغلب العطاءات الحكومية والممولة تشترط تصنيفاً سارياً مطابقاً لمجال العطاء وقيمته، لذلك ننصح بإتمام التصنيف قبل موعد الإغلاق بوقت كافٍ.' },
  { q: 'كم تستغرق مراجعة طلب ترفيع الدرجة؟', a: 'تتم مراجعة طلبات الترفيع خلال 1-3 أيام عمل من اكتمال المستندات، ويصلك إشعار فوري بنتيجة لجنة التصنيف.' },
]
const openFaq = ref<number | null>(0)
function toggleFaq(i: number) { openFaq.value = openFaq.value === i ? null : i }

// ─── Data loading ────────────────────────────────────────────────────────────
async function loadHome() {
  try {
    const r = await api.get('/api/v1/landing/home')
    const home = r.data.items ?? {}
    const s = home.stats ?? {}
    if (s.years) unionStats.value.years = s.years
    if (s.branches) unionStats.value.branches = s.branches
    newsCards.value = (home.latest_news ?? []).map((n: any) => ({
      key: `news-${n.id}`,
      title: n.title,
      image: n.image_url || PLACEHOLDER_MEDIA,
      tags: [{ label: 'أخبار الاتحاد', tone: 'tag-blue' }],
      date: fmtDateTime(n.published_at),
      to: `/landing/news/${n.id}`,
    }))
  }
  catch {}
}

async function loadTenders() {
  try {
    const r = await api.get('/api/v1/tenders-public', { params: { status: 'open', per_page: 6 } })
    const items = (r.data.items ?? []).filter((t: any) => t.deadline)
    if (items.length) {
      tenders.value = items.map((t: any) => ({
        id: t.id,
        title: t.title,
        entity: t.issuing_entity || 'اتحاد المقاولين الفلسطينيين',
        category: t.category || 'عطاء عام',
        isNew: !!t.is_new,
        deadline: new Date(t.deadline).getTime(),
      }))
    }
  }
  catch {}
}

async function loadCirculars() {
  try {
    const r = await api.get('/api/v1/announcements', { params: { per_page: 5 } })
    const items = r.data.items ?? []
    if (items.length) {
      circulars.value = items.map((a: any) => ({
        id: a.id,
        number: a.number || '',
        title: a.title,
        body: String(a.body ?? '').replace(/<[^>]+>/g, '').slice(0, 260),
        urgent: !!a.is_pinned,
        date: new Date(a.published_at),
      }))
    }
  }
  catch {}
}

async function loadEvents() {
  try {
    const r = await api.get('/api/v1/events/latest')
    const items = r.data.items ?? []
    if (items.length) {
      eventCards.value = items.map((e: any) => ({
        key: `event-${e.id}`,
        title: e.title,
        image: e.image || PLACEHOLDER_MEDIA,
        tags: [
          { label: 'فعالية محلية للاتحاد', tone: 'tag-blue' },
          ...(e.external_url || e.video_url ? [{ label: 'اون لاين zoom', tone: 'tag-green' }] : []),
        ],
        date: fmtDateTime(e.event_date || e.published_at),
        to: `/landing/events/${e.slug}`,
      }))
    }
  }
  catch {}
}

onMounted(() => {
  clock = setInterval(() => { now.value = Date.now() }, 1000)
  loadHome()
  loadTenders()
  loadCirculars()
  loadEvents()
})
onUnmounted(() => clearInterval(clock))
</script>

<template>
  <div dir="rtl" class="lp">
    <!-- ══ HERO ══ -->
    <section class="hero">
      <img src="/images/landing/hero.webp" alt="" class="hero-img">
      <div class="hero-shade" />
      <div class="container hero-inner">
        <span class="hero-badge">المنصة الرسمية لقطاع المقاولات في فلسطين</span>
        <h1 class="hero-title">معاً لبناء قطاع مقاولات<br>قوي في <span class="accent">غزة</span></h1>
        <p class="hero-desc">
          نعمل على دعم وتطوير قطاع المقاولات الفلسطيني من خلال تقديم الخدمات , والتمثيل , والتمكين وتعزيز
          بيئة العمل المهني في قطاع الانشاءات
        </p>
        <RouterLink to="/landing/services" class="hero-btn">
          تعرف على خدماتنا <ArrowLeft :size="18" />
        </RouterLink>
      </div>
    </section>

    <!-- ══ DIRECTORY SEARCH ══ -->
    <div class="container search-wrap">
      <form class="search-card" @submit.prevent="searchDirectory">
        <div class="search-tabs">
          <button type="button" :class="{ active: directoryTab === 'contractors' }" @click="directoryTab = 'contractors'">دليل المقاولين</button>
          <button type="button" :class="{ active: directoryTab === 'suppliers' }" @click="directoryTab = 'suppliers'">دليل الموردين</button>
        </div>
        <div class="search-row">
          <label class="search-input">
            <Search :size="22" />
            <input v-model="searchQuery" type="text" placeholder="ابحث باسم الشركة أو رقم العضوية...">
          </label>
          <button type="submit" class="btn-blue search-btn">
            {{ directoryTab === 'contractors' ? 'ابحث عن مقاول' : 'ابحث عن مورد' }}
          </button>
        </div>
      </form>
    </div>

    <!-- ══ SERVICES PORTAL (verified membership) ══ -->
    <section id="verified-membership" class="section">
      <div class="container">
        <div class="eyebrow">بوابة الخدمات</div>
        <h2 class="heading">خدمات أساسية، بخطوات رقمية قصيرة</h2>

        <div class="svc-grid">
          <article class="svc-card svc-blue">
            <div class="svc-head">
              <span class="svc-icon"><FileBadge :size="24" /></span>
              <h3>طلب شهادة عضوية</h3>
              <span class="pill pill-green"><i />فوري</span>
            </div>
            <p class="svc-desc">إصدار وتجديد شهادة العضوية الرسمية المعتمدة مع إمكانية التحقق الفوري.</p>
            <div class="svc-chips">
              <span class="chip"><FileText :size="18" class="ico-red" /> تحميل صيغة PDF</span>
              <span class="chip"><Stamp :size="18" class="ico-blue" /> ختم معتمد ورقمي</span>
            </div>
            <RouterLink to="/contractor/login" class="btn-blue svc-btn">طلب استخراج شهادة عضوية</RouterLink>
          </article>

          <article class="svc-card svc-orange">
            <div class="svc-head">
              <span class="svc-icon"><Award :size="24" /></span>
              <h3>طلب شهادة تصنيف</h3>
              <span class="pill pill-orange"><i />1-3 أيام</span>
            </div>
            <p class="svc-desc">تقييم واعتماد القدرات الفنية والمالية للشركات، لتحديد الدرجة والتخصص المستحق للمناقصات والمشاريع.</p>
            <div class="svc-chips">
              <span class="chip"><span class="chk"><Check :size="12" /></span> تحديد الدرجات والمجالات</span>
              <span class="chip"><span class="chk"><Check :size="12" /></span> تقييم شامل معتمد</span>
            </div>
            <RouterLink to="/contractor/login" class="btn-orange svc-btn">طلب استخراج شهادة تصنيف</RouterLink>
          </article>
        </div>
      </div>
    </section>

    <!-- ══ UNION OVERVIEW ══ -->
    <section class="section pt-0">
      <div class="container">
        <div class="eyebrow">منظومة الاتحاد</div>
        <h2 class="heading">كل ما تحتاج معرفته عن الاتحاد في مكان واحد</h2>
        <p class="subheading">نبذة عن الاتحاد، القطاعات التي يمثلها، ودرجات التصنيف الرسمية – بتنقّل سريع دون تمرير طويل.</p>

        <div class="about-card">
          <div class="about-tabs">
            <button :class="{ active: aboutTab === 'about' }" @click="aboutTab = 'about'">عن الاتحاد</button>
            <button :class="{ active: aboutTab === 'sectors' }" @click="aboutTab = 'sectors'">القطاعات التي نمثلها</button>
            <button :class="{ active: aboutTab === 'degrees' }" @click="aboutTab = 'degrees'">درجات التصنيف</button>
          </div>

          <div v-if="aboutTab === 'about'" class="about-body">
            <div class="about-text">
              <h3>صوت قطاع المقاولات الفلسطيني منذ أكثر من {{ unionStats.years }} عاماً</h3>
              <p>يمثّل اتحاد المقاولين الفلسطينيين الشركات المرخّصة في قطاع الإنشاءات والهندسة، ويتولى تسجيل المقاولين وتصنيفهم الرسمي، والدفاع عن مصالحهم أمام الجهات الحكومية والمانحة.</p>
              <p>يوفّر الاتحاد قنوات مباشرة للوصول إلى العطاءات، وخدمات تصنيف وتوثيق معتمدة لدى الوزارات والمؤسسات المحلية والدولية.</p>
            </div>
            <div class="about-stats">
              <div class="stat-box"><strong>1,200+</strong><span>شركة عضو مسجّلة</span></div>
              <div class="stat-box"><strong>{{ unionStats.years }}+</strong><span>سنة من التمثيل النقابي</span></div>
              <div class="stat-box"><strong>{{ unionStats.branches }}</strong><span>فروع إقليمية فاعلة</span></div>
              <div class="stat-box"><strong>{{ sectors.length }}</strong><span>قطاعات إنشائية ممثّلة</span></div>
            </div>
          </div>

          <div v-else-if="aboutTab === 'sectors'" class="tab-grid">
            <div v-for="s in sectors" :key="s" class="tab-item">
              <span class="chk"><Check :size="12" /></span>{{ s }}
            </div>
          </div>

          <div v-else class="tab-grid">
            <div v-for="d in degrees" :key="d.code" class="tab-item degree">
              <span class="degree-code">{{ d.code }}</span>
              <div><strong>{{ d.label }}</strong><small>{{ d.desc }}</small></div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- ══ LIVE TENDERS ══ -->
    <section class="section pt-0">
      <div class="container">
        <div class="eyebrow">مركز العطاءات الحية</div>
        <div class="head-row">
          <h2 class="heading mb-0">عطاءات مطروحة الان أمام الاعضاء</h2>
          <div class="head-actions">
            <RouterLink to="/landing/public-tenders" class="btn-outline-orange">عرض كل العطاءات <ArrowLeft :size="20" /></RouterLink>
            <div class="arrows">
              <button class="arrow" :disabled="tenderPage === 0" aria-label="السابق" @click="tenderPage--"><ChevronRight :size="18" /></button>
              <button class="arrow" :disabled="tenderPage >= tenderPages - 1" aria-label="التالي" @click="tenderPage++"><ChevronLeft :size="18" /></button>
            </div>
          </div>
        </div>
        <p class="subheading">عطاءات حكومية وخاصة محدثة لحظيا مع موعد اغلاق دقيق ومتطلبات تصنيف</p>

        <div class="tender-grid">
          <article v-for="(t, i) in visibleTenders" :key="`${t.id}-${i}`" class="tender-card">
            <div class="tender-top">
              <div class="tender-tags">
                <span class="tag" :class="categoryTone(t.category)">{{ t.category }}</span>
                <span v-if="t.isNew" class="tag tag-new">جديد</span>
              </div>
              <Bookmark :size="24" class="bookmark" fill="currentColor" />
            </div>
            <h3 class="tender-title">{{ t.title }}</h3>
            <p class="tender-entity"><Landmark :size="20" /> {{ t.entity }}</p>
            <hr>
            <p class="tender-closes"><Timer :size="18" /> يغلق خلال</p>
            <div class="tender-bottom">
              <div class="countdown">
                <div class="cd"><b>{{ countdown(t.deadline).days }}</b><span>يوم</span></div>
                <div class="cd"><b>{{ countdown(t.deadline).hours }}</b><span>ساعة</span></div>
                <div class="cd"><b>{{ countdown(t.deadline).minutes }}</b><span>دقيقة</span></div>
                <div class="cd cd-sec"><b>{{ countdown(t.deadline).seconds }}</b><span>ثانية</span></div>
              </div>
              <button class="btn-blue tender-btn" @click="openTender(t)">تفاصيل العطاء</button>
            </div>
          </article>
        </div>
      </div>
    </section>

    <!-- ══ EQUIPMENT MARKETPLACE ══ -->
    <section class="section bg-soft">
      <div class="container">
        <div class="eyebrow">سوق المعدات الثقيلة</div>
        <div class="head-row">
          <h2 class="heading mb-0">ومعدات ثقيلة جاهزة للايجار</h2>
          <RouterLink to="/contractor/login" class="btn-outline-orange">اضف اليتك <ArrowLeft :size="20" /></RouterLink>
        </div>

        <div class="gov-filter">
          <button v-for="g in governorates" :key="g" :class="{ active: activeGovernorate === g }" @click="activeGovernorate = g">{{ g }}</button>
        </div>

        <div class="equip-grid">
          <article v-for="e in visibleEquipment" :key="e.id" class="equip-card">
            <div class="equip-img">
              <img :src="e.image" :alt="e.title" loading="lazy">
              <span class="equip-count"><ImageIcon :size="16" /> 1/5</span>
            </div>
            <div class="equip-title-row">
              <h3>{{ e.title }}</h3>
              <span class="pill pill-green">{{ e.condition }}</span>
            </div>
            <p class="equip-spec">{{ e.spec }}</p>
            <div class="equip-meta">
              <span><MapPin :size="18" class="ico-orange" /> {{ e.city }}</span>
              <span><CalendarDays :size="18" class="ico-orange" /> {{ e.rent }}</span>
            </div>
            <RouterLink to="/contractor/login" class="btn-outline-blue equip-btn">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M3 21l1.65-3.8a9 9 0 1 1 3.4 2.9L3 21" /><path d="M9 10a.5.5 0 0 0 1 0V9a.5.5 0 0 0-1 0v1a5 5 0 0 0 5 5h1a.5.5 0 0 0 0-1h-1a.5.5 0 0 0 0 1" /></svg>
              تواصل مع المالك
            </RouterLink>
          </article>
        </div>
        <p v-if="!visibleEquipment.length" class="empty">لا توجد معدات معروضة في هذه المحافظة حالياً.</p>

        <div class="center">
          <RouterLink to="/contractor/login" class="btn-outline-orange">عرض المزيد <ArrowLeft :size="20" /></RouterLink>
        </div>
      </div>
    </section>

    <!-- ══ CIRCULARS ══ -->
    <section class="section">
      <div class="container">
        <div class="eyebrow">تحديثات الاتحاد</div>
        <div class="head-row">
          <h2 class="heading mb-0">القرارات والتعاميم النقابية</h2>
          <div class="head-actions">
            <RouterLink to="/landing/news" class="btn-outline-orange">عرض كل التعاميم <ArrowLeft :size="20" /></RouterLink>
            <div class="arrows">
              <button class="arrow" :disabled="circularIndex === 0" aria-label="السابق" @click="circularIndex--"><ChevronRight :size="18" /></button>
              <button class="arrow" :disabled="circularIndex >= circulars.length - 1" aria-label="التالي" @click="circularIndex++"><ChevronLeft :size="18" /></button>
            </div>
          </div>
        </div>

        <article v-if="circular" class="circular">
          <div class="circular-date">
            <b>{{ circular.date.getDate() }}</b>
            <span>{{ arMonth(circular.date) }}</span>
            <small>{{ circular.date.getFullYear() }}</small>
          </div>
          <div class="circular-body">
            <div class="circular-tags">
              <span v-if="circular.number" class="ctag">تعميم رقم {{ circular.number }}</span>
              <span v-if="circular.urgent" class="ctag ctag-urgent"><i />عاجل وهام</span>
            </div>
            <h3>{{ circular.title }}</h3>
            <p>{{ circular.body }}</p>
          </div>
        </article>
      </div>
    </section>

    <!-- ══ NEWS & EVENTS ══ -->
    <section class="section pt-0">
      <div class="container">
        <div class="eyebrow">المركز الاعلامي</div>
        <div class="head-row">
          <h2 class="heading mb-0">اخر الاخبار والفعاليات</h2>
          <div class="head-actions">
            <div class="media-toggle">
              <button :class="{ active: mediaTab === 'news' }" @click="setMediaTab('news')">اخر الاخبار</button>
              <button :class="{ active: mediaTab === 'events' }" @click="setMediaTab('events')">الفعاليات</button>
            </div>
            <div class="arrows">
              <button class="arrow" :disabled="mediaPage === 0" aria-label="السابق" @click="mediaPage--"><ChevronRight :size="18" /></button>
              <button class="arrow" :disabled="mediaPage >= mediaPages - 1" aria-label="التالي" @click="mediaPage++"><ChevronLeft :size="18" /></button>
            </div>
          </div>
        </div>
        <p class="subheading">تغطية شاملة لأحدث قرارات الاتحاد، والفعاليات النقابية.</p>

        <div class="media-grid">
          <article v-for="m in visibleMedia" :key="m.key" class="media-card">
            <img :src="m.image" :alt="m.title" class="media-img" loading="lazy">
            <div class="media-body">
              <div class="media-tags">
                <span v-for="tg in m.tags" :key="tg.label" class="mtag" :class="tg.tone">{{ tg.label }}</span>
              </div>
              <h3>{{ m.title }}</h3>
              <p v-if="m.date" class="media-date"><CalendarDays :size="18" /> {{ m.date }}</p>
              <RouterLink :to="m.to" class="read-more">اقرأ المزيد <ArrowLeft :size="18" /></RouterLink>
            </div>
          </article>
        </div>
      </div>
    </section>

    <!-- ══ MOBILE APP ══ -->
    <section class="app-section">
      <div class="container app-inner">
        <div class="app-text">
          <span class="app-badge">PCU Mobile App</span>
          <h2>كل خدمات الاتحاد بين يديك<br>أينما كنت</h2>
          <p>حمّل تطبيق اتحاد المقاولين الفلسطينيين وتابع العطاءات والتعاميم وشهاداتك الرقمية لحظة بلحظة.</p>
          <div class="app-features">
            <div v-for="f in appFeatures" :key="f.title" class="app-feature">
              <span class="app-feature-icon"><component :is="f.icon" :size="26" /></span>
              <h3>{{ f.title }}</h3>
              <p>{{ f.desc }}</p>
            </div>
          </div>
          <div class="stores">
            <a href="#" class="store" aria-label="App Store">
              <svg width="38" height="38" viewBox="0 0 40 40" aria-hidden="true"><defs><linearGradient id="as-g" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#1ec8fc" /><stop offset="1" stop-color="#1e6ef4" /></linearGradient></defs><rect width="40" height="40" rx="9" fill="url(#as-g)" /><path d="M13 27.5h14M16.5 22.5l6-10.5M23.5 22.5l-6-10.5M20.5 17.5l5 8.5M11 27.5l2.2-3.6" stroke="#fff" stroke-width="2.4" stroke-linecap="round" fill="none" /></svg>
              <span><small>حمل من</small>App Store</span>
            </a>
            <a href="#" class="store" aria-label="Google Play">
              <svg width="36" height="38" viewBox="0 0 36 40" aria-hidden="true"><path d="M2 2l19 18L2 38c-.8-.4-1.2-1.2-1.2-2.2V4.2C.8 3.2 1.2 2.4 2 2z" fill="#2196f3" /><path d="M27.5 13.7L21 20 2 2c.3-.2.7-.2 1.1-.2.5 0 1 .1 1.4.4z" fill="#4caf50" /><path d="M27.5 26.3L4.5 37.8c-.4.3-.9.4-1.4.4-.4 0-.8 0-1.1-.2l19-18z" fill="#f44336" /><path d="M34 20c0 1-.5 1.9-1.4 2.4l-5.1 3.9L21 20l6.5-6.3 5.1 3.9c.9.5 1.4 1.4 1.4 2.4z" fill="#ffc107" /></svg>
              <span><small>حمل من</small>Google Play</span>
            </a>
          </div>
        </div>
        <div class="app-visual">
          <img src="/images/landing/app-phones.webp" alt="تطبيق اتحاد المقاولين الفلسطينيين" loading="lazy">
        </div>
      </div>
    </section>

    <!-- ══ TESTIMONIALS ══ -->
    <section class="section">
      <div class="container">
        <div class="eyebrow">شركاء النجاح</div>
        <div class="head-row">
          <h2 class="heading mb-0">ماذا يقول شركاؤنا عن الاتحاد</h2>
          <div class="arrows">
            <button class="arrow" :disabled="testimonialPage === 0" aria-label="السابق" @click="testimonialPage--"><ChevronRight :size="18" /></button>
            <button class="arrow" :disabled="testimonialPage >= testimonialPages - 1" aria-label="التالي" @click="testimonialPage++"><ChevronLeft :size="18" /></button>
          </div>
        </div>

        <div class="testi-grid">
          <article v-for="t in visibleTestimonials" :key="t.id" class="testi-card">
            <span class="quote">&ldquo;</span>
            <p>{{ t.text }}</p>
            <hr>
            <div class="testi-author">
              <span class="avatar">{{ t.initials }}</span>
              <div>
                <strong>{{ t.name }}</strong>
                <small>{{ t.role }}</small>
              </div>
            </div>
          </article>
        </div>
      </div>
    </section>

    <!-- ══ FAQ ══ -->
    <section class="section bg-soft">
      <div class="container faq-wrap">
        <div class="eyebrow center-eyebrow">الاجابات المباشرة</div>
        <h2 class="heading text-center">الاسئلة الشائعة</h2>
        <div class="faq-list">
          <div v-for="(f, i) in faqs" :key="f.q" class="faq-item" :class="{ open: openFaq === i }">
            <button class="faq-q" :aria-expanded="openFaq === i" @click="toggleFaq(i)">
              <span>{{ f.q }}</span>
              <span class="faq-toggle"><ChevronUp v-if="openFaq === i" :size="16" /><ChevronDown v-else :size="16" /></span>
            </button>
            <div v-if="openFaq === i" class="faq-a">{{ f.a }}</div>
          </div>
        </div>
      </div>
    </section>

    <!-- ══ NEWSLETTER ══ -->
    <section class="newsletter">
      <div class="container newsletter-inner">
        <div>
          <h2>احصل على ملخص المناقصات اليومي في بريدك</h2>
          <p>اشترك في التنبيهات الفورية ليصلك ملخص أسبوعي بكافة العطاءات والمشاريع المطروحة في تخصصك.</p>
        </div>
        <RouterLink to="/contractor/login" class="btn-orange newsletter-btn">تفعيل التنبيهات البريدية</RouterLink>
      </div>
    </section>
  </div>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800;900&display=swap');

.lp {
  --blue: #0000a8;
  --blue-deep: #00006e;
  --blue-bright: #0000e0;
  --blue-grad: linear-gradient(90deg, #00007a 0%, #0000e6 100%);
  --orange: #d67a00;
  --ink: #0b0b0f;
  --muted: #5b6474;
  --line: #e6e8ef;
  --soft: #f3f4f8;
  --green: #11903a;
  --green-bg: #e3f6e8;

  font-family: 'Tajawal', 'Cairo', sans-serif;
  color: var(--ink);
  background: #fff;
  overflow-x: hidden;
}
.lp *, .lp *::before, .lp *::after { box-sizing: border-box; }
.lp h1, .lp h2, .lp h3, .lp p { margin: 0; color: inherit; }
.lp button { font-family: inherit; }
.lp a { text-decoration: none; }

.container { max-width: 1200px; margin: 0 auto; padding: 0 24px; }
.section { padding: 64px 0; }
.pt-0 { padding-top: 0; }
.bg-soft { background: var(--soft); }
.center { display: flex; justify-content: center; margin-top: 32px; }
.text-center { text-align: center; }
.mb-0 { margin-bottom: 0 !important; }
.empty { text-align: center; color: var(--muted); padding: 32px 0; }

/* ── Section headings ── */
.eyebrow {
  display: flex; align-items: center; gap: 10px;
  color: var(--orange); font-weight: 600; font-size: 15px; margin-bottom: 14px;
}
.eyebrow::after { content: ''; width: 18px; height: 2px; background: var(--orange); border-radius: 2px; }
.center-eyebrow { justify-content: center; }
.heading { font-size: clamp(28px, 3.4vw, 48px); font-weight: 800; line-height: 1.35; margin-bottom: 24px; }
.subheading { color: var(--muted); font-size: 20px; font-weight: 700; line-height: 1.8; margin-bottom: 44px; }
.head-row { display: flex; align-items: center; justify-content: space-between; gap: 20px; flex-wrap: wrap; margin-bottom: 18px; }
.head-actions { display: flex; align-items: center; gap: 12px; }

/* ── Buttons ── */
.btn-blue, .btn-orange {
  display: inline-flex; align-items: center; justify-content: center; gap: 8px;
  color: #fff; border: none; border-radius: 8px; font-weight: 600; cursor: pointer;
  transition: filter .2s, transform .2s;
}
.btn-blue { background: var(--blue-grad); }
.btn-orange { background: var(--orange); }
.btn-blue:hover, .btn-orange:hover { filter: brightness(1.1); transform: translateY(-1px); }
.btn-outline-orange {
  display: inline-flex; align-items: center; gap: 10px;
  border: 1.5px solid var(--orange); color: var(--orange); background: #fff;
  border-radius: 10px; padding: 12px 22px; font-weight: 500; font-size: 17px; transition: background .2s;
}
.btn-outline-orange:hover { background: #fff6ea; }
.btn-outline-blue {
  display: inline-flex; align-items: center; justify-content: center; gap: 8px;
  border: 1.5px solid var(--blue); color: var(--blue-deep); border-radius: 8px; font-weight: 700;
  transition: background .2s;
}
.btn-outline-blue:hover { background: #f0f0ff; }
.arrows { display: flex; gap: 10px; }
.arrow {
  width: 44px; height: 44px; border-radius: 50%; border: 1px solid var(--line); background: #fff;
  display: grid; place-items: center; color: var(--ink); cursor: pointer;
}
.arrow:disabled { color: #c9ccd4; border-color: #f0f1f4; cursor: default; }

/* ── Pills / chips ── */
.pill { display: inline-flex; align-items: center; gap: 6px; border-radius: 999px; padding: 5px 14px; font-size: 14px; font-weight: 600; white-space: nowrap; }
.pill i { width: 6px; height: 6px; border-radius: 50%; background: currentColor; }
.pill-green { background: var(--green-bg); color: var(--green); }
.pill-orange { background: #fff1dc; color: var(--orange); border: 1px solid #f8dcb2; }
.chk { width: 18px; height: 18px; border-radius: 50%; background: var(--orange); color: #fff; display: inline-grid; place-items: center; flex-shrink: 0; }
.ico-red { color: #e11d2a; }
.ico-blue { color: var(--blue); }
.ico-orange { color: var(--orange); }

/* ══ HERO ══ */
.hero { position: relative; min-height: 673px; display: flex; align-items: center; overflow: hidden; }
.hero-img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; object-position: left center; }
.hero-shade {
  position: absolute; inset: 0;
  background: linear-gradient(to left, rgba(20, 20, 170, .88) 0%, rgba(24, 24, 170, .7) 38%, rgba(30, 30, 150, .25) 65%, rgba(0, 0, 0, 0) 85%);
}
.hero-inner { position: relative; z-index: 1; width: 100%; padding-top: 58px; padding-bottom: 150px; color: #fff; }
.hero-badge {
  display: inline-block; border: 1px solid rgba(255, 255, 255, .6); border-radius: 999px;
  padding: 8px 22px; font-size: 16px; font-weight: 500; margin-bottom: 34px; backdrop-filter: blur(4px);
}
.hero-title { font-size: clamp(32px, 3.4vw, 48px); font-weight: 800; line-height: 1.5; margin-bottom: 26px; color: #fff; }
.hero-title .accent { color: #ffbe1a; }
.hero-desc { max-width: 540px; font-size: 22px; font-weight: 700; line-height: 2; margin-bottom: 36px; }
.hero-btn {
  display: inline-flex; align-items: center; gap: 14px; color: #fff;
  border: 1.5px solid #fff; border-radius: 12px; padding: 16px 38px; font-size: 17px; font-weight: 600;
  transition: background .2s;
}
.hero-btn:hover { background: rgba(255, 255, 255, .12); }

/* ══ SEARCH ══ */
.search-wrap { position: relative; z-index: 2; margin-top: -90px; }
.search-card { background: #fff; border-radius: 22px; box-shadow: 0 18px 50px rgba(15, 23, 60, .12); padding: 22px 28px 30px; }
.search-tabs { display: flex; gap: 14px; margin-bottom: 20px; }
.search-tabs button {
  border: none; background: transparent; color: var(--muted); font-size: 16px; font-weight: 500;
  border-radius: 12px; padding: 10px 22px; cursor: pointer;
}
.search-tabs button.active { background: #f0f1f4; color: var(--ink); }
.search-row { display: flex; gap: 12px; }
.search-input {
  flex: 1; display: flex; align-items: center; gap: 12px; background: #efeff2; border-radius: 10px;
  padding: 0 20px; color: #3b4150;
}
.search-input input { flex: 1; border: none; outline: none; background: transparent; font: inherit; font-size: 16px; padding: 16px 0; color: var(--ink); }
.search-btn { padding: 0 44px; font-size: 16px; min-height: 54px; }

/* ══ SERVICES ══ */
.svc-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 28px; margin-top: 30px; }
.svc-card {
  position: relative; overflow: hidden; background: #fff; border-radius: 24px; padding: 32px;
  border: 1.5px solid; display: flex; flex-direction: column;
}
.svc-card::before {
  content: ''; position: absolute; width: 260px; height: 260px; left: -40px; top: -60px; border-radius: 50%;
  filter: blur(50px); opacity: .7; pointer-events: none;
}
.svc-blue { border-color: #6d6de8; border-top-width: 4px; }
.svc-blue::before { background: #e4e5f4; }
.svc-orange { border-color: #eaa84f; border-top-width: 4px; }
.svc-orange::before { background: #fdeede; }
.svc-head { position: relative; display: flex; align-items: center; gap: 16px; margin-bottom: 26px; }
.svc-head h3 { font-size: 28px; font-weight: 700; flex: 1; }
.svc-icon { width: 54px; height: 54px; border-radius: 14px; display: grid; place-items: center; flex-shrink: 0; }
.svc-blue .svc-icon { background: #ebebf8; border: 1px solid #c9c9ef; color: var(--blue); }
.svc-orange .svc-icon { background: #fdf1e2; border: 1px solid #f5d6ab; color: var(--orange); }
.svc-desc { position: relative; color: #5b6474; font-size: 18px; line-height: 1.8; margin-bottom: 24px; }
.svc-chips { position: relative; display: flex; flex-wrap: wrap; gap: 16px; margin-bottom: 28px; }
.chip {
  display: inline-flex; align-items: center; gap: 10px; background: #f3f4f6; border: 1px solid #e7e8ec;
  border-radius: 10px; padding: 12px 18px; font-size: 16px; font-weight: 500;
}
.svc-btn { position: relative; padding: 16px; font-size: 16px; margin-top: auto; }

/* ══ ABOUT CARD ══ */
.about-card { border: 1.5px solid var(--line); border-radius: 30px; padding: 38px 40px 30px; }
.about-tabs { display: flex; gap: 40px; align-items: center; margin-bottom: 30px; flex-wrap: wrap; }
.about-tabs button { border: none; background: transparent; color: var(--muted); font-size: 18px; cursor: pointer; padding: 12px 0; }
.about-tabs button.active { background: var(--blue-grad); color: #fff; border-radius: 10px; padding: 12px 22px; }
.about-body { display: grid; grid-template-columns: 1.3fr 1fr; gap: 32px; align-items: start; }
.about-text h3 { color: var(--blue-deep); font-size: 28px; font-weight: 800; line-height: 1.75; margin-bottom: 20px; }
.about-text p { color: var(--muted); font-size: 15px; line-height: 2; margin-bottom: 18px; }
.about-stats { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
.stat-box { background: #f3f4f6; border: 1px solid var(--line); border-radius: 14px; padding: 16px 22px 18px; display: flex; flex-direction: column; gap: 8px; }
.stat-box strong { font-size: 26px; font-weight: 800; direction: ltr; text-align: left; }
.stat-box span { color: var(--muted); font-size: 14px; text-align: left; }
.tab-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 14px; }
.tab-item { display: flex; align-items: center; gap: 12px; background: #f3f4f6; border: 1px solid var(--line); border-radius: 14px; padding: 18px 20px; font-weight: 500; }
.degree div { display: flex; flex-direction: column; gap: 4px; }
.degree small { color: var(--muted); }
.degree-code { width: 44px; height: 44px; border-radius: 10px; background: var(--blue-grad); color: #fff; display: grid; place-items: center; font-weight: 700; flex-shrink: 0; }

/* ══ TENDERS ══ */
.tender-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 26px; }
.tender-card { border: 1.5px solid var(--line); border-radius: 24px; padding: 24px; box-shadow: 0 2px 6px rgba(15, 23, 60, .03); }
.tender-top { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 18px; }
.tender-tags { display: flex; gap: 8px; flex-wrap: wrap; }
.tag { border-radius: 999px; padding: 5px 16px; font-size: 15px; font-weight: 600; }
.tone-yellow { background: #fbf5dc; color: #b58900; }
.tone-purple { background: #f1e6fd; color: #7a1fd4; }
.tone-blue { background: #e5ecff; color: #2446c8; }
.tone-teal { background: #dff5f2; color: #0f7d70; }
.tag-new { background: var(--green-bg); color: var(--green); }
.bookmark { color: var(--orange); flex-shrink: 0; }
.tender-title { font-size: 21px; font-weight: 700; line-height: 1.6; margin-bottom: 20px; }
.tender-entity { display: flex; align-items: center; gap: 8px; color: var(--muted); font-size: 15px; }
.tender-card hr { border: none; border-top: 1px solid var(--line); margin: 20px 0 16px; }
.tender-closes { display: flex; align-items: center; gap: 6px; color: var(--muted); font-size: 15px; margin-bottom: 14px; }
.tender-bottom { display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap; }
.countdown { display: flex; gap: 10px; }
.cd { width: 50px; border-radius: 10px; background: #f0f1f4; display: flex; flex-direction: column; align-items: center; padding: 6px 0; }
.cd b { font-size: 15px; font-weight: 700; }
.cd span { font-size: 13px; color: var(--muted); }
.cd-sec { background: #eaf8ee; border: 1px solid #aee3bd; }
.cd-sec b, .cd-sec span { color: var(--green); }
.tender-btn { padding: 15px 28px; font-size: 15px; }

/* ══ EQUIPMENT ══ */
.gov-filter { display: flex; gap: 16px; flex-wrap: wrap; margin: 26px 0 40px; }
.gov-filter button {
  border: none; background: #ecedf2; color: #505665; font-size: 16px; border-radius: 999px;
  padding: 10px 22px; cursor: pointer;
}
.gov-filter button.active { background: #050505; color: #fff; font-weight: 600; }
.equip-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 26px; }
.equip-card { background: #fff; border: 1px solid var(--line); border-radius: 18px; padding: 16px 18px 18px; }
.equip-img { position: relative; border-radius: 14px; overflow: hidden; aspect-ratio: 2.1 / 1; margin-bottom: 16px; }
.equip-img img { width: 100%; height: 100%; object-fit: cover; }
.equip-img::after { content: ''; position: absolute; inset: 0; background: rgba(0, 0, 0, .35); }
.equip-count {
  position: absolute; left: 12px; bottom: 12px; z-index: 1; display: flex; align-items: center; gap: 6px;
  background: rgba(0, 0, 0, .55); color: #fff; font-size: 13px; border-radius: 10px; padding: 4px 10px; direction: ltr;
}
.equip-title-row { display: flex; align-items: center; justify-content: space-between; gap: 10px; margin-bottom: 10px; }
.equip-title-row h3 { font-size: 20px; font-weight: 800; }
.equip-spec { color: var(--muted); font-size: 15px; font-weight: 500; margin-bottom: 10px; }
.equip-meta { display: flex; gap: 22px; color: var(--muted); font-size: 13px; margin-bottom: 18px; }
.equip-meta span { display: flex; align-items: center; gap: 6px; }
.equip-btn { width: 100%; padding: 13px; font-size: 15px; }

/* ══ CIRCULARS ══ */
.circular {
  position: relative; overflow: hidden; margin-top: 28px; background: #000073; border-radius: 22px;
  padding: 40px; display: flex; gap: 28px; align-items: flex-start; color: #fff;
}
.circular::before {
  content: ''; position: absolute; width: 220px; height: 220px; left: 30px; top: -40px; border-radius: 50%;
  background: radial-gradient(circle, rgba(170, 40, 80, .45), transparent 70%); pointer-events: none;
}
.circular-date {
  flex-shrink: 0; width: 104px; border: 1px solid rgba(255, 255, 255, .25); border-radius: 18px;
  display: flex; flex-direction: column; align-items: center; gap: 4px; padding: 16px 0;
}
.circular-date b { color: #ffbe1a; font-size: 26px; }
.circular-date span { font-size: 18px; font-weight: 600; }
.circular-date small { font-size: 16px; font-weight: 600; }
.circular-body { position: relative; flex: 1; }
.circular-tags { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 14px; }
.ctag { border: 1px solid rgba(160, 170, 255, .55); background: rgba(90, 100, 230, .3); border-radius: 999px; padding: 5px 14px; font-size: 14px; font-weight: 600; }
.ctag-urgent { display: inline-flex; align-items: center; gap: 6px; background: #7a0b3c; border-color: #a01a50; }
.ctag-urgent i { width: 8px; height: 8px; border-radius: 50%; background: #fff; }
.circular-body h3 { font-size: 20px; font-weight: 800; line-height: 1.7; margin-bottom: 10px; }
.circular-body p { color: rgba(255, 255, 255, .88); font-size: 14px; line-height: 1.9; }

/* ══ NEWS & EVENTS ══ */
.media-toggle { display: flex; background: #f3f4f8; border: 1px solid var(--line); border-radius: 999px; padding: 4px; }
.media-toggle button { border: none; background: transparent; color: var(--muted); border-radius: 999px; padding: 10px 24px; font-size: 15px; cursor: pointer; }
.media-toggle button.active { background: #000080; color: #fff; font-weight: 600; }
.media-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 24px; }
.media-card { border: 1px solid var(--line); border-radius: 18px; overflow: hidden; display: flex; flex-direction: column; }
.media-img { width: 100%; aspect-ratio: 2.3 / 1; object-fit: cover; }
.media-body { padding: 20px; display: flex; flex-direction: column; gap: 16px; flex: 1; }
.media-tags { display: flex; gap: 8px; flex-wrap: wrap; }
.mtag { border-radius: 999px; padding: 4px 12px; font-size: 13px; font-weight: 600; }
.tag-blue { background: #e8f0ff; color: #2b6cf0; }
.tag-green { background: var(--green-bg); color: var(--green); }
.media-body h3 { font-size: 20px; font-weight: 800; line-height: 1.6; }
.media-date { display: flex; align-items: center; justify-content: center; gap: 8px; background: #f0f1f4; border-radius: 999px; padding: 8px; font-size: 13px; color: #1d2230; }
.media-date svg { color: #34347a; }
.read-more { display: inline-flex; align-items: center; gap: 10px; color: #000080; font-weight: 700; font-size: 16px; margin-top: auto; padding-top: 10px; }

/* ══ APP ══ */
.app-section { background: linear-gradient(180deg, #00008c 0%, #0000c8 60%, #0000d8 100%); color: #fff; padding: 110px 0 100px; overflow: hidden; }
.app-inner { display: grid; grid-template-columns: 1.1fr 1fr; gap: 32px; align-items: center; }
.app-badge { display: inline-block; background: var(--orange); color: #fff; font-weight: 700; font-size: 15px; border-radius: 999px; padding: 8px 16px; margin-bottom: 26px; direction: ltr; }
.app-text h2 { font-size: clamp(30px, 3.3vw, 46px); font-weight: 800; line-height: 1.6; margin-bottom: 20px; color: #fff; }
.app-text > p { font-size: 19px; font-weight: 700; line-height: 1.8; color: rgba(255, 255, 255, .92); margin-bottom: 30px; max-width: 520px; }
.app-features { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 40px; }
.app-feature { background: rgba(255, 255, 255, .08); border: 1px solid rgba(255, 255, 255, .16); border-radius: 18px; padding: 18px 20px; }
.app-feature-icon { width: 42px; height: 42px; border-radius: 10px; background: var(--orange); display: grid; place-items: center; margin-bottom: 12px; }
.app-feature h3 { font-size: 18px; font-weight: 700; margin-bottom: 6px; color: #fff; }
.app-feature p { font-size: 13px; line-height: 1.55; color: rgba(255, 255, 255, .75); }
.stores { display: flex; gap: 16px; flex-wrap: wrap; }
.store { display: flex; align-items: center; gap: 12px; background: #fff; color: var(--ink); border-radius: 14px; padding: 12px 22px; min-width: 176px; direction: ltr; justify-content: center; }
.store span { display: flex; flex-direction: column; font-size: 17px; font-weight: 500; line-height: 1.2; text-align: left; }
.store small { font-size: 13px; color: #333; direction: rtl; text-align: right; }
.app-visual img { width: 100%; max-width: 560px; display: block; margin-inline-end: auto; }

/* ══ TESTIMONIALS ══ */
.testi-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 36px; margin-top: 36px; }
.testi-card { border: 1px solid var(--line); border-radius: 22px; padding: 18px 22px 24px; }
.quote { display: block; color: var(--orange); font-size: 56px; font-weight: 700; line-height: .9; height: 34px; font-family: Georgia, serif; }
.testi-card p { color: #2a2f3a; font-size: 14px; line-height: 1.85; }
.testi-card hr { border: none; border-top: 1.5px solid var(--line); margin: 16px 0 14px; }
.testi-author { display: flex; align-items: center; gap: 10px; }
.avatar { width: 54px; height: 54px; border-radius: 50%; background: var(--blue-grad); color: #fff; display: grid; place-items: center; font-weight: 700; font-size: 15px; flex-shrink: 0; }
.testi-author div { display: flex; flex-direction: column; gap: 6px; }
.testi-author strong { font-size: 16px; }
.testi-author small { color: var(--muted); font-size: 13px; }

/* ══ FAQ ══ */
.faq-wrap { max-width: 1160px; }
.faq-list { max-width: 800px; margin: 36px auto 0; display: flex; flex-direction: column; gap: 22px; }
.faq-item { background: #ededf0; border: 1px solid #e3e4e9; border-radius: 16px; }
.faq-item.open { background: #fff; border-color: var(--orange); box-shadow: 0 10px 28px rgba(214, 122, 0, .08); }
.faq-q { width: 100%; display: flex; align-items: center; justify-content: space-between; gap: 16px; background: none; border: none; text-align: right; padding: 22px 24px; font-size: 18px; font-weight: 700; color: var(--ink); cursor: pointer; }
.faq-toggle { width: 30px; height: 30px; border-radius: 50%; background: #fff; display: grid; place-items: center; flex-shrink: 0; box-shadow: 0 1px 3px rgba(0, 0, 0, .08); }
.faq-item.open .faq-toggle { background: var(--orange); color: #fff; }
.faq-a { margin: 0 24px; padding: 20px 0 22px; border-top: 1px solid var(--line); color: var(--muted); font-size: 17px; line-height: 2; }

/* ══ NEWSLETTER ══ */
.newsletter { background: linear-gradient(90deg, #000060 0%, #000098 45%, #0000d8 100%); color: #fff; padding: 74px 0; margin: 40px 0; }
.newsletter-inner { display: flex; align-items: center; justify-content: space-between; gap: 30px; flex-wrap: wrap; }
.newsletter h2 { font-size: clamp(22px, 2.4vw, 32px); font-weight: 800; margin-bottom: 18px; color: #fff; }
.newsletter p { color: #b9b8f2; font-size: 19px; font-weight: 500; line-height: 2; max-width: 620px; }
.newsletter-btn { padding: 18px 24px; border-radius: 14px; font-size: 16px; }

/* ══ Responsive ══ */
@media (max-width: 1000px) {
  .about-body, .app-inner { grid-template-columns: 1fr; }
  .equip-grid, .media-grid, .testi-grid { grid-template-columns: repeat(2, 1fr); }
  .app-visual img { width: 100%; margin: 0; }
}
@media (max-width: 720px) {
  .section { padding: 48px 0; }
  .svc-grid, .tender-grid, .equip-grid, .media-grid, .testi-grid, .app-features, .about-stats { grid-template-columns: 1fr; }
  .search-row { flex-direction: column; }
  .search-btn { min-height: 52px; }
  .about-card { padding: 22px; }
  .about-tabs { gap: 14px; }
  .circular { flex-direction: column; padding: 26px; }
  .hero-inner { padding-bottom: 130px; }
  .svc-head h3 { font-size: 21px; }
}
</style>

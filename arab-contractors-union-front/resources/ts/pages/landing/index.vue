<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '@/plugins/axios'
import PublicNewsTicker from '@/components/PublicNewsTicker.vue'
import PublicSocialIcon from '@/components/PublicSocialIcon.vue'
import LoginGuideVideoDialog from '@/components/LoginGuideVideoDialog.vue'
import { MotionDirective } from '@vueuse/motion'
import { vHoverCard } from '@/utils/hoverCard'
import {
  ArrowLeft, Award, Bookmark, Building2, CalendarDays, ChevronLeft, ChevronRight, Clock,
  FileText, Hourglass, Layers, Loader2, MapPin, Play, Search, SearchX, ShieldCheck, UserCheck,
} from 'lucide-vue-next'

definePage({
  meta: { layout: 'landing', public: true, unauthenticatedOnly: false },
})

const router = useRouter()
const route = useRoute()

// ─── فيديو "كيف تسجّل دخولك" (نافذة منبثقة) ──────────────────────────────────
// الرابط اللي بينسخه الزائر /landing?video=login بيفتح الصفحة والنافذة مفتوحة
const loginVideoOpen = ref(route.query.video === 'login')
watch(() => route.query.video, v => { if (v === 'login') loginVideoOpen.value = true })
watch(loginVideoOpen, open => {
  if (!open && route.query.video) {
    const { video: _video, ...rest } = route.query
    router.replace({ query: rest })
  }
})

// ─── Scroll animations (@vueuse/motion) ──────────────────────────────────────
// v-reveal: يطلع العنصر من تحت مع fade أول ما يبين بالشاشة (مرة وحدة). :delay للتتابع بين الكروت.
const vReveal = MotionDirective({
  initial: { opacity: 0, y: 40 },
  visibleOnce: { opacity: 1, y: 0, transition: { duration: 700, ease: [0.22, 1, 0.36, 1] } },
})
const vRevealSide = MotionDirective({
  initial: { opacity: 0, x: -60 },
  visibleOnce: { opacity: 1, x: 0, transition: { duration: 800, ease: [0.22, 1, 0.36, 1] } },
})
// الهيرو بيتحرك عند فتح الصفحة مش عند السكرول
const vEnter = MotionDirective({
  initial: { opacity: 0, y: 30 },
  enter: { opacity: 1, y: 0, transition: { duration: 800, ease: [0.22, 1, 0.36, 1] } },
})

// ─── Partners strip ──────────────────────────────────────────────────────────
const partners: { logo: string; label: string; url?: string }[] = [
  { logo: '/images/partners/ministry-public-works.png', label: 'وزارة الأشغال العامة والإسكان' },
  { logo: '/images/partners/engineers-association.png', label: 'نقابة المهندسين - فرع غزة' },
  { logo: '/images/partners/palestinian-housing-council.png', label: 'المجلس الفلسطيني للإسكان' },
  { logo: '/images/partners/gaza-chamber-commerce.png', label: 'غرفة تجارة وصناعة محافظة غزة' },
  { logo: '/images/partners/undp.png', label: 'برنامج الأمم المتحدة الإنمائي UNDP', url: 'https://www.undp.org' },
]

// كاروسيل الشعارات: السهمين بيحرّكوا شعار واحد، وبيلفّ من الآخر للأول. بيتحرك لحاله كل 3 ثواني إلا لما الماوس فوقه.
const partnersTrack = ref<HTMLElement | null>(null)
let partnersTimer: ReturnType<typeof setInterval> | undefined

function slidePartners(dir: 1 | -1) {
  const el = partnersTrack.value
  if (!el) return
  const item = el.querySelector<HTMLElement>('.partner')
  const step = (item?.offsetWidth ?? 200) + 24
  const max = el.scrollWidth - el.clientWidth
  const pos = Math.abs(el.scrollLeft) // RTL: scrollLeft بيكون سالب
  if (dir === 1 && pos >= max - 2) el.scrollTo({ left: 0, behavior: 'smooth' })
  else if (dir === -1 && pos <= 2) el.scrollTo({ left: -max, behavior: 'smooth' })
  else el.scrollBy({ left: -dir * step, behavior: 'smooth' })
}

function startPartners() {
  stopPartners()
  partnersTimer = setInterval(() => slidePartners(1), 3000)
}
function stopPartners() {
  if (partnersTimer) clearInterval(partnersTimer)
}
onUnmounted(stopPartners)

// ─── ومضات التطبيق: كل عبارة 3.5 ثانية بتلاشي ناعم، والأخيرة هي الرسالة الختامية ──
// كل عبارة = [قبل, كلمة مفتاحية, بعد]
const appFlashes: [string, string, string][] = [
  ['', 'عضويتك الرقمية', '... بين يديك.'],
  ['تابع ', 'تصنيف شركتك', ' بسهولة.'],
  ['إشعارات ', 'الاتحاد وتعاميمه', ' تصلك أولاً بأول.'],
  ['', 'العطاءات والمناقصات', '... فرصك أقرب إليك.'],
  ['', 'خدماتك الإلكترونية', '... أسرع وأسهل.'],
  ['', 'اتحادك معك', ' .. أينما كنت وفي أي وقت.'],
]
const flashIdx = ref(0)
let flashTimer: ReturnType<typeof setInterval> | undefined
onMounted(() => { flashTimer = setInterval(() => { flashIdx.value = (flashIdx.value + 1) % appFlashes.length }, 3500) })
onUnmounted(() => { if (flashTimer) clearInterval(flashTimer) })

// ─── About ───────────────────────────────────────────────────────────────────
const aboutFeatures = [
  { icon: UserCheck, title: 'التصنيف والتسجيل المهني للمقاولين', desc: 'إدارة إجراءات تصنيف وتسجيل شركات المقاولات، وتقييم مؤهلاتها وخبراتها وإمكاناتها الفنية والإدارية والمالية، لتحديد فئة التصنيف المناسبة وفق المعايير والأنظمة المعتمدة.' },
  { icon: FileText, title: 'تصفّح العطاءات والمناقصات', desc: 'منصة إلكترونية تتيح للمقاولين الاطلاع على فرص العطاءات والمناقصات المطروحة من الجهات الحكومية والمؤسسات الدولية، ومتابعتها بسهولة وشفافية، بما يعزز فرص الوصول إلى مشاريع الأعمال.' },
  { icon: ShieldCheck, title: 'التمثيل والدفاع عن مصالح المقاولين', desc: 'تمثيل شركات المقاولات والدفاع عن حقوقها ومصالحها أمام الجهات الرسمية والمؤسسات المحلية والدولية، وتعزيز الشراكات والتنسيق مع مختلف الأطراف لدعم تطوير القطاع والمشاركة الفاعلة في جهود التعافي وإعادة الإعمار.' },
]

const unionStats = ref({ years: 30, specializations: 0 })
// الأرقام بتعدّ من صفر لقيمتها أول ما القسم يبين بالشاشة (ومرة ثانية لو تغيّرت القيمة بعد وصول بيانات الـ API)
const stats = computed(() => [
  { icon: Building2, num: 350, prefix: '+', label: 'شركة مقاولات مصنفة', tone: 'navy' },
  { icon: Award, num: 5, prefix: '', label: 'مجالات تصنيف معتمدة', tone: 'orange' },
  { icon: Layers, num: unionStats.value.specializations || 40, prefix: unionStats.value.specializations ? '' : '+', label: 'اختصاصات معتمدة', tone: 'navy' },
  { icon: CalendarDays, num: unionStats.value.years, prefix: '+', label: 'عاما من القيادة الوطنية', tone: 'orange' },
])
const statsEl = ref<HTMLElement | null>(null)
const statShown = ref<number[]>([0, 0, 0, 0])
let statsStarted = false
let statsRaf = 0
function countStats() {
  cancelAnimationFrame(statsRaf)
  const from = [...statShown.value]
  const to = stats.value.map(x => x.num)
  if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) { statShown.value = to; return }
  const t0 = performance.now()
  const dur = 1800
  const tick = (now: number) => {
    const t = Math.min(1, (now - t0) / dur)
    const e = 1 - Math.pow(1 - t, 3) // easeOutCubic
    statShown.value = to.map((v, i) => Math.round(from[i] + (v - from[i]) * e))
    if (t < 1) statsRaf = requestAnimationFrame(tick)
  }
  statsRaf = requestAnimationFrame(tick)
}
watch(() => stats.value.map(x => x.num).join(), () => { if (statsStarted) countStats() })
let statsObserver: IntersectionObserver | undefined
onMounted(() => {
  if (!statsEl.value) return
  statsObserver = new IntersectionObserver((entries) => {
    if (entries.some(e => e.isIntersecting) && !statsStarted) {
      statsStarted = true
      countStats()
      statsObserver?.disconnect()
    }
  }, { threshold: 0.35 })
  statsObserver.observe(statsEl.value)
})
onUnmounted(() => { cancelAnimationFrame(statsRaf); statsObserver?.disconnect() })

// ─── Tenders ─────────────────────────────────────────────────────────────────
interface TenderCard { id: number | null; title: string; desc: string; category: string; image: string; closingSoon: boolean; deadline: string }
// صورة العطاء = صورة تصنيفه الحالية من لوحة التحكم (category_image)، و/tenders.webp فقط لو التصنيف بلا صورة
const TENDER_PLACEHOLDER = '/tenders.webp'
const sampleTender = (closingSoon: boolean): TenderCard => ({
  id: null,
  title: 'انشاء وتأهيل شبكة صرف صحي -حي الشيخ',
  desc: 'دعوة استدراج عروض أسعار للمقاولين للبدء بتأهيل واعادة تشغيل محطات الضخ وتوريد ......',
  category: 'الاسكان والتطوير',
  image: TENDER_PLACEHOLDER,
  closingSoon,
  deadline: '15 نوفمبر 2026',
})
const tenders = ref<TenderCard[]>([sampleTender(false), sampleTender(true), sampleTender(false)])
const tenderPage = ref(0)
const tenderPages = computed(() => Math.max(1, Math.ceil(tenders.value.length / 3)))
const visibleTenders = computed(() => tenders.value.slice(tenderPage.value * 3, tenderPage.value * 3 + 3))

function fmtDate(d: string | null | undefined) {
  if (!d) return ''
  return new Date(d).toLocaleDateString('ar-EG-u-nu-latn', { day: 'numeric', month: 'long', year: 'numeric' })
}
function plainText(html: unknown, max = 160) {
  return String(html ?? '').replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim().slice(0, max)
}
function openTender(t: TenderCard) {
  router.push(t.id ? { path: '/landing/public-tenders', query: { id: String(t.id) } } : '/landing/public-tenders')
}

// ─── Directory search ────────────────────────────────────────────────────────
interface DirectoryItem { name: string; membership_number: string; city: string | null; phone?: string | null; membership_status: string; status_label: string; fields: { name: string; grades: string[] }[] }
const searchQuery = ref('')
const directory = ref<DirectoryItem[]>([])
const directoryTotal = ref(0)
const directoryPage = ref(1)
const directoryLastPage = ref(1)
const directoryLoading = ref(false)
const directorySearched = ref('')
let directoryTimer: ReturnType<typeof setTimeout> | undefined
let directoryRequest = 0

async function fetchDirectory(page = 1) {
  const q = searchQuery.value.trim()
  const id = ++directoryRequest
  directoryLoading.value = true
  try {
    const r = await api.get('/api/v1/contractors-directory', { params: { search: q, page, per_page: 6 } })
    if (id !== directoryRequest) return
    const items = r.data.items ?? []
    directory.value = page === 1 ? items : [...directory.value, ...items]
    directoryTotal.value = r.data.meta?.total ?? items.length
    directoryPage.value = r.data.meta?.current_page ?? page
    directoryLastPage.value = r.data.meta?.last_page ?? page
    directorySearched.value = q
  }
  catch {
    if (id === directoryRequest) { directory.value = []; directoryTotal.value = 0; directorySearched.value = q }
  }
  finally {
    if (id === directoryRequest) directoryLoading.value = false
  }
}
function searchDirectory() {
  clearTimeout(directoryTimer)
  if (searchQuery.value.trim()) fetchDirectory(1)
}
// بحث تلقائي أثناء الكتابة (بعد توقف قصير)، ومسح النتائج لما يفضى الحقل
watch(searchQuery, (q) => {
  clearTimeout(directoryTimer)
  if (!q.trim()) {
    directoryRequest++
    directory.value = []
    directorySearched.value = ''
    directoryLoading.value = false
    return
  }
  if (q.trim().length >= 2) directoryTimer = setTimeout(() => fetchDirectory(1), 400)
})
onUnmounted(() => clearTimeout(directoryTimer))

// ─── News ────────────────────────────────────────────────────────────────────
interface NewsCard { key: string; title: string; excerpt: string; image: string; date: string; to: string }
const NEWS_PLACEHOLDER = '/images/landing/home-news.webp'
const sampleNews = (i: number): NewsCard => ({
  key: `sample-${i}`,
  title: 'اجتماع هيئة المكتب لمناقشة التسهيلات والترخيص لشركات',
  excerpt: 'دعوة استدراج عروض أسعار للمقاولين للبدء بتأهيل واعادة تشغيل محطات الضخ وتوريد ......',
  image: NEWS_PLACEHOLDER,
  date: '2026-08-12',
  to: '/landing/news',
})
const news = ref<NewsCard[]>([0, 1, 2].map(sampleNews))
const newsPage = ref(0)
const newsPages = computed(() => Math.max(1, Math.ceil(news.value.length / 3)))
const visibleNews = computed(() => news.value.slice(newsPage.value * 3, newsPage.value * 3 + 3))

// واتساب بدها الرقم دولي بدون + أو أصفار: 0597… ← 970597…
const waNumber = (p: string) => {
  const d = p.replace(/\D/g, '')

  return d.startsWith('00') ? d.slice(2) : d.startsWith('0') ? `970${d.slice(1)}` : d.length === 9 ? `970${d}` : d
}

// ─── Data loading ────────────────────────────────────────────────────────────
async function loadHome() {
  try {
    const r = await api.get('/api/v1/landing/home')
    const home = r.data.items ?? {}
    if (home.stats?.years) unionStats.value.years = home.stats.years
    if (home.stats?.specializations_count) unionStats.value.specializations = home.stats.specializations_count
    const items = home.latest_news ?? []
    if (items.length) {
      news.value = items.map((n: any) => ({
        key: `news-${n.id}`,
        title: n.title,
        excerpt: plainText(n.excerpt, 140),
        image: n.image_url || NEWS_PLACEHOLDER,
        date: n.published_at ? String(n.published_at).slice(0, 10) : '',
        to: `/landing/news/${n.id}`,
      }))
    }
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
        desc: plainText(t.description, 140),
        category: t.category || 'عطاء عام',
        image: t.category_image || TENDER_PLACEHOLDER,
        closingSoon: !!t.closing_soon,
        deadline: fmtDate(t.deadline),
      }))
    }
  }
  catch {}
}

onMounted(() => {
  loadHome()
  loadTenders()
  startPartners()
})
</script>

<template>
  <div dir="rtl" class="lp">
    <!-- ══ HERO ══ -->
    <section class="hero">
      <img src="/images/landing/home-hero.webp" alt="" class="hero-img">
      <div class="hero-shade" />
      <div class="container hero-inner">
        <span v-enter class="hero-badge"><i />المظلة المهنية والوطنية لقطاع المقاولات</span>
        <h1 v-enter :delay="120" class="hero-title">
          نحو قطاع مقاولات فلسطيني<br>
          أكثر <span class="accent">كفاءةً</span> وقدرةً على <span class="accent">المنافسة</span>
        </h1>
        <p v-enter :delay="240" class="hero-desc">
          نعمل على تمثيل شركات المقاولات وتطوير قدراتها الفنية والإدارية، وتعزيز جاهزيتها للمشاركة الفاعلة في مشاريع التعافي وإعادة الإعمار، وبناء الشراكات المحلية والدولية وفق أفضل المعايير والممارسات المهنية.
        </p>
        <div v-enter :delay="360" class="hero-actions">
          <RouterLink to="/landing/members" class="hero-btn hero-btn-solid">
            تصفّح دليل شركات المقاولات <ChevronLeft :size="18" />
          </RouterLink>
          <button type="button" class="hero-btn hero-btn-ghost" @click="loginVideoOpen = true">
            <span class="play"><Play :size="14" fill="currentColor" /></span> كيف تسجّل دخولك
          </button>
        </div>
      </div>
    </section>

    <!-- ══ NEWS TICKER (overlaps the hero's bottom edge) ══ -->
    <div class="container ticker-wrap">
      <PublicNewsTicker class="home-ticker" />
    </div>

    <!-- ══ ABOUT ══ -->
    <section class="about dotted">
      <div class="container about-grid">
        <div v-reveal class="about-text">
          <span class="eyebrow"><i />عن الاتحاد</span>
          <h2 class="about-heading">
            تأسس الاتحاد ليكون المظلة المهنية والوطنية الجامعة لشركات المقاولات الفلسطينية، والمدافع عن مصالحها، والمساهم في تنظيم وتطوير قطاع المقاولات، وتعزيز قدراته التنافسية، وتيسير وصول الشركات إلى فرص الأعمال والعطاءات والمناقصات، وفق معايير مهنية ومؤسسية عالية.
          </h2>
          <div class="features">
            <article
              v-for="f in aboutFeatures" :key="f.title"
              class="feature"
            >
              <span class="feature-icon"><component :is="f.icon" :size="22" /></span>
              <div>
                <h3>{{ f.title }}</h3>
                <p>{{ f.desc }}</p>
              </div>
            </article>
          </div>
          <RouterLink to="/landing/services" class="btn-navy about-btn">
            تعرّف على كل خدماتنا الإلكترونية <ChevronLeft :size="18" />
          </RouterLink>
        </div>
        <div v-reveal-side :delay="150" class="about-visual">
          <span class="about-frame" />
          <img src="/images/landing/home-about-hq-v2.webp" alt="مقر اتحاد المقاولين الفلسطينيين" width="397" height="595" loading="lazy">
        </div>
      </div>

      <div class="container">
        <div ref="statsEl" v-reveal class="stats">
          <div v-for="(s, i) in stats" :key="s.label" class="stat" :class="`stat-${s.tone}`" :style="{ '--d': `${i * 90}ms` }">
            <span class="stat-icon"><component :is="s.icon" :size="26" :stroke-width="1.8" /></span>
            <strong><span class="stat-num" dir="ltr">{{ s.prefix }}{{ statShown[i] }}</span></strong>
            <span class="stat-label">{{ s.label }}</span>
          </div>
        </div>
      </div>
    </section>

    <!-- ══ TENDERS ══ -->
    <section class="section">
      <div class="container">
        <div v-reveal class="head-row">
          <div>
            <span class="eyebrow"><i />اهم العطاءات</span>
            <h2 class="heading">احدث <span class="underlined">العطاءات</span></h2>
          </div>
          <div class="head-actions">
            <RouterLink to="/landing/public-tenders" class="btn-outline-orange">عرض كل العطاءات <ChevronLeft :size="18" /></RouterLink>
            <div class="arrows">
              <button class="arrow" :disabled="tenderPage === 0" aria-label="السابق" @click="tenderPage--"><ChevronRight :size="18" /></button>
              <button class="arrow" :disabled="tenderPage >= tenderPages - 1" aria-label="التالي" @click="tenderPage++"><ChevronLeft :size="18" /></button>
            </div>
          </div>
        </div>

        <div class="cards-grid">
          <article v-for="(t, i) in visibleTenders" :key="`${t.id}-${i}`" v-reveal v-hover-card :delay="i * 120" class="tender-card">
            <div class="tender-img hc-media">
              <img :src="t.image" :alt="t.title" loading="lazy">
              <span v-if="t.closingSoon" class="status status-soon"><Hourglass :size="13" /> قريب الاغلاق</span>
              <span v-else class="status status-open"><i />مفتوح لتقديم</span>
              <button class="bookmark" aria-label="حفظ العطاء" @click="router.push('/contractor/login')"><Bookmark :size="20" /></button>
              <span class="tender-cat">{{ t.category }}</span>
            </div>
            <div class="tender-body">
              <h3 class="hc-title">{{ t.title }}</h3>
              <p class="clamp-2">{{ t.desc }}</p>
              <div class="deadline" :class="{ soon: t.closingSoon }">
                <span class="deadline-label"><Clock :size="18" /> {{ t.closingSoon ? 'ينتهي قريبا :' : 'الموعد النهائي :' }}</span>
                <span class="deadline-date">{{ t.deadline }}</span>
              </div>
              <button class="btn-navy tender-btn" @click="openTender(t)">عرض التفاصيل <ChevronLeft :size="18" class="hc-arrow" /></button>
            </div>
          </article>
        </div>
      </div>
    </section>

    <!-- ══ DIRECTORY ══ -->
    <div class="container">
      <section v-reveal class="directory">
        <img src="/images/landing/home-directory.webp" alt="" class="directory-img" loading="lazy">
        <div class="directory-shade" />
        <div class="directory-inner">
          <span class="eyebrow eyebrow-dark"><i />خدمة الاستعلام عن شركات المقاولات</span>
          <h2>دليل شركات المقاولات <span class="accent">المسجلة والمصنفة</span></h2>
          <p>ابحث عن شركات المقاولات المسجلة لدى الاتحاد، واطّلع على بياناتها وتصنيفها المهني ودرجة تصنيفها وفق السجلات المعتمدة</p>
          <form class="directory-search" @submit.prevent="searchDirectory">
            <Search :size="18" class="directory-search-icon" />
            <input v-model="searchQuery" type="text" placeholder="ابحث باسم الشركة أو رقم العضوية...">
            <button type="submit" class="btn-navy">بحث</button>
          </form>

          <div v-if="directoryLoading && !directory.length" class="directory-state">
            <Loader2 :size="22" class="spin" /> جاري البحث...
          </div>
          <div v-else-if="directorySearched && !directory.length" class="directory-state">
            <SearchX :size="22" /> لا توجد شركة مسجلة بهذا الاسم أو رقم العضوية
          </div>
          <div v-else-if="directory.length" class="directory-results">
            <p class="directory-count">{{ directoryTotal }} نتيجة لـ "{{ directorySearched }}"</p>
            <div class="directory-grid">
              <article v-for="c in directory" :key="c.membership_number" class="dcard">
                <div class="dcard-head">
                  <span class="dcard-logo"><Building2 :size="20" /></span>
                  <div class="dcard-title">
                    <h3>{{ c.name }}</h3>
                    <span class="dcard-num">رقم العضوية: <b dir="ltr">{{ c.membership_number }}</b></span>
                  </div>
                  <span class="dstatus" :class="`dstatus-${c.membership_status}`"><i />{{ c.status_label }}</span>
                </div>
                <div v-if="c.city || c.phone" class="dcard-meta">
                  <span v-if="c.city" class="dcard-city"><MapPin :size="15" /> {{ c.city }}</span>
                  <a v-if="c.phone" :href="`https://wa.me/${waNumber(c.phone)}`" target="_blank" rel="noopener noreferrer" class="dcard-wa" aria-label="تواصل عبر واتساب" title="تواصل عبر واتساب"><PublicSocialIcon name="whatsapp" :size="18" /></a>
                </div>
                <div v-if="c.fields.length" class="dcard-fields">
                  <span v-for="f in c.fields" :key="f.name" class="dfield">
                    {{ f.name }}<b v-if="f.grades.length"> · {{ f.grades.join('، ') }}</b>
                  </span>
                </div>
              </article>
            </div>
            <button v-if="directoryPage < directoryLastPage" class="directory-more" :disabled="directoryLoading" @click="fetchDirectory(directoryPage + 1)">
              <Loader2 v-if="directoryLoading" :size="16" class="spin" /> عرض نتائج أكثر
            </button>
          </div>
        </div>
      </section>
    </div>

    <!-- ══ NEWS ══ -->
    <section class="section">
      <div class="container">
        <div v-reveal class="head-row">
          <div>
            <span class="eyebrow"><i />المركز الاعلامي</span>
            <h2 class="heading">أخبار ومستجدات الاتحاد</h2>
          </div>
          <div class="head-actions">
            <RouterLink to="/landing/news" class="btn-outline-orange">عرض كل الاخبار <ChevronLeft :size="18" /></RouterLink>
            <div class="arrows">
              <button class="arrow" :disabled="newsPage === 0" aria-label="السابق" @click="newsPage--"><ChevronRight :size="18" /></button>
              <button class="arrow" :disabled="newsPage >= newsPages - 1" aria-label="التالي" @click="newsPage++"><ChevronLeft :size="18" /></button>
            </div>
          </div>
        </div>

        <div class="cards-grid">
          <article v-for="(n, i) in visibleNews" :key="n.key" v-reveal v-hover-card :delay="i * 120" class="news-card">
            <div class="hc-media"><img :src="n.image" :alt="n.title" class="news-img" loading="lazy"></div>
            <div class="news-body">
              <span v-if="n.date" class="news-date">{{ n.date }}</span>
              <h3 class="hc-title">{{ n.title }}</h3>
              <p class="clamp-2">{{ n.excerpt }}</p>
              <RouterLink :to="n.to" class="read-more">أقرأ المزيد <ArrowLeft :size="16" class="hc-arrow" /></RouterLink>
            </div>
          </article>
        </div>
      </div>
    </section>

    <!-- ══ MOBILE APP ══ -->
    <div class="container app-wrap">
      <section v-reveal class="app">
        <span class="ball ball-white" />
        <span class="ball ball-orange" />
        <div class="app-text">
          <span class="eyebrow"><i />التطبيق الذكي الرسمي لاتحاد المقاولين الفلسطينيين</span>
          <h2>خدماتك في <span class="underlined navy">تطبيق واحد</span></h2>
          <Transition name="flash" mode="out-in">
            <p :key="flashIdx" class="app-flash">
              <template v-for="(part, pi) in appFlashes[flashIdx]" :key="pi"><b v-if="pi === 1">{{ part }}</b><template v-else>{{ part }}</template></template>
            </p>
          </Transition>
          <a href="#" class="store" aria-label="Google Play">
            <svg width="28" height="30" viewBox="0 0 36 40" aria-hidden="true"><path d="M2 2l19 18L2 38c-.8-.4-1.2-1.2-1.2-2.2V4.2C.8 3.2 1.2 2.4 2 2z" fill="#2196f3" /><path d="M27.5 13.7L21 20 2 2c.3-.2.7-.2 1.1-.2.5 0 1 .1 1.4.4z" fill="#4caf50" /><path d="M27.5 26.3L4.5 37.8c-.4.3-.9.4-1.4.4-.4 0-.8 0-1.1-.2l19-18z" fill="#f44336" /><path d="M34 20c0 1-.5 1.9-1.4 2.4l-5.1 3.9L21 20l6.5-6.3 5.1 3.9c.9.5 1.4 1.4 1.4 2.4z" fill="#ffc107" /></svg>
            <span><small>حمل من</small>Google Play</span>
          </a>
        </div>
        <svg class="app-arrow" viewBox="0 0 420 110" fill="none" aria-hidden="true">
          <path d="M405 66C330 82 230 84 175 74c-40-7-50-34-15-40 40-7 75 12 55 30-25 22-120 10-200-34" stroke="#1f3f8f" stroke-width="2.5" stroke-linecap="round" />
          <path d="M18 18l-4 15 15 3" stroke="#1f3f8f" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
        <div class="app-visual">
          <img src="/images/landing/home-app-phones.webp" alt="تطبيق اتحاد المقاولين الفلسطينيين" loading="lazy">
        </div>
      </section>
    </div>

    <!-- ══ PARTNERS ══ -->
    <div class="container partners-wrap">
      <div v-reveal class="partners" @mouseenter="stopPartners" @mouseleave="startPartners">
        <span class="partners-label">الشركاء والجهات المعتمدة:</span>
        <div class="partners-carousel">
          <button class="arrow" aria-label="السابق" @click="slidePartners(-1)"><ChevronRight :size="18" /></button>
          <div ref="partnersTrack" class="partners-track">
            <component :is="p.url ? 'a' : 'span'" v-for="p in partners" :key="p.label" class="partner" :title="p.label" v-bind="p.url ? { href: p.url, target: '_blank', rel: 'noopener noreferrer' } : {}">
              <img :src="p.logo" :alt="p.label" loading="lazy">
            </component>
          </div>
          <button class="arrow" aria-label="التالي" @click="slidePartners(1)"><ChevronLeft :size="18" /></button>
        </div>
      </div>
    </div>

    <LoginGuideVideoDialog v-model="loginVideoOpen" />
  </div>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800;900&display=swap');

.lp {
  --navy: #00006e;
  --navy-soft: #e8e8f6;
  --orange: #d67a00;
  --yellow: #ffb61a;
  --orange-soft: #fdf1e1;
  --ink: #0b0b0f;
  --muted: #5b6474;
  --line: #e6e8ef;
  --green: #11903a;

  font-family: 'Tajawal', 'Cairo', sans-serif;
  color: var(--ink);
  background: #fff;
  overflow-x: hidden;
  /* the navbar floats over the hero, so the page slides up under it */
  margin-top: calc(-1 * var(--pub-nav-h, 114px));
}
.lp *, .lp *::before, .lp *::after { box-sizing: border-box; }
.lp h1, .lp h2, .lp h3, .lp p { margin: 0; color: inherit; font-family: inherit; }
.lp button, .lp input { font-family: inherit; }
.lp a { text-decoration: none; }

.container { max-width: 1200px; margin: 0 auto; padding: 0 24px; }
.section { padding: 140px 0 0; }
.clamp-2 { display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.accent { color: var(--yellow); }

/* ── Headings ── */
.eyebrow {
  display: inline-flex; align-items: center; gap: 8px; background: #fff4e3; border: 1px solid #f6d7a8;
  color: var(--orange); font-weight: 700; font-size: 13px; border-radius: 999px; padding: 5px 14px; margin-bottom: 14px;
}
.eyebrow i { width: 7px; height: 7px; border-radius: 50%; background: var(--orange); }
.eyebrow-dark { background: rgba(255, 255, 255, .1); border-color: rgba(255, 255, 255, .35); color: #fff; }
.eyebrow-dark i { background: var(--yellow); }
.heading { font-size: clamp(26px, 2.6vw, 38px); font-weight: 800; line-height: 1.4; }
.underlined { position: relative; display: inline-block; }
.underlined::after { content: ''; position: absolute; right: 0; left: 0; bottom: -6px; height: 4px; border-radius: 2px; background: var(--orange); }
.head-row { display: flex; align-items: flex-end; justify-content: space-between; gap: 20px; flex-wrap: wrap; margin-bottom: 32px; }
.head-actions { display: flex; align-items: center; gap: 12px; }

/* ── Buttons ── */
.btn-navy {
  display: inline-flex; align-items: center; justify-content: center; gap: 10px;
  background: var(--navy); color: #fff; border: none; border-radius: 10px; font-weight: 700; cursor: pointer;
  transition: filter .2s, transform .2s;
}
.btn-navy:hover { filter: brightness(1.25); transform: translateY(-1px); }
.btn-outline-orange {
  display: inline-flex; align-items: center; gap: 10px; border: 1.5px solid var(--orange); color: var(--orange);
  background: #fff; border-radius: 10px; padding: 10px 22px; font-weight: 600; font-size: 16px; transition: background .2s;
}
.btn-outline-orange:hover { background: #fff6ea; }
.arrows { display: flex; gap: 10px; }
.arrow {
  width: 44px; height: 44px; border-radius: 50%; border: 1px solid var(--line); background: #fff;
  display: grid; place-items: center; color: var(--ink); cursor: pointer;
}
.arrow:disabled { color: #c9ccd4; cursor: default; }

/* ══ HERO ══ */
.hero {
  position: relative; overflow: hidden;
  min-height: 642px; border-radius: 0 0 44px 44px; display: flex; align-items: center;
}
.hero-img { position: absolute; top: 0; right: 0; bottom: 0; left: 0; width: 100%; height: 100%; object-fit: cover; object-position: left center; }
.hero-shade {
  position: absolute; top: 0; right: 0; bottom: 0; left: 0;
  background: linear-gradient(to left, rgba(16, 16, 120, .92) 0%, rgba(30, 30, 150, .78) 45%, rgba(40, 40, 160, .35) 75%, rgba(40, 40, 160, .15) 100%);
}
.hero-inner { position: relative; z-index: 1; width: 100%; padding-top: calc(var(--pub-nav-h, 114px) + 40px); padding-bottom: 64px; color: #fff; }
.hero-badge {
  display: inline-flex; align-items: center; gap: 10px; background: rgba(255, 255, 255, .12);
  border: 1px solid rgba(255, 255, 255, .45); border-radius: 999px; padding: 6px 18px; font-size: 14px; font-weight: 600;
  margin-bottom: 26px; backdrop-filter: blur(4px);
}
.hero-badge i { width: 9px; height: 9px; border-radius: 50%; background: var(--yellow); }
.hero .hero-title { font-size: clamp(28px, 3.2vw, 44px); font-weight: 800; line-height: 1.5; margin-bottom: 22px; color: #fff; }
.hero .hero-desc { max-width: 540px; font-size: clamp(17px, 1.45vw, 21px); font-weight: 700; line-height: 2; margin-bottom: 40px; }
.hero-actions { display: flex; gap: 16px; flex-wrap: wrap; }
.hero-btn {
  display: inline-flex; align-items: center; gap: 14px; border-radius: 10px; padding: 15px 28px;
  font-size: 16px; font-weight: 700; min-height: 54px; transition: background .2s, transform .2s;
}
.hero-btn-solid { background: #fff; color: var(--navy); padding-inline: 26px 30px; min-width: 250px; justify-content: space-between; }
.hero-btn-solid:hover { transform: translateY(-1px); }
.hero-btn-ghost { cursor: pointer; font-family: inherit; color: #fff; border: 1px solid rgba(255, 255, 255, .7); background: rgba(255, 255, 255, .08); }
.hero-btn-ghost:hover { background: rgba(255, 255, 255, .16); }
.play { width: 28px; height: 28px; border-radius: 50%; background: var(--yellow); color: var(--navy); display: grid; place-items: center; }

/* ══ PARTNERS ══ */
.partners-wrap { padding-bottom: 72px; }
.partners {
  display: flex; align-items: center; gap: 18px 28px; flex-wrap: wrap;
  border-top: 1px solid var(--line); padding: 28px 0 8px;
}

/* ══ NEWS TICKER ══ */
/* wider than the 1200px content container so the bar reads as a long strip */
.ticker-wrap { position: relative; z-index: 2; margin-top: -23px; max-width: 1440px; }
.home-ticker { border-radius: 12px; box-shadow: 0 14px 34px rgba(0, 0, 80, .22); }
.partners-label { color: #8a90a0; font-weight: 700; font-size: 15px; }
.partners-label { flex-shrink: 0; }
.partners-carousel { flex: 1; min-width: 0; display: flex; align-items: center; gap: 12px; }
.partners-carousel .arrow { flex-shrink: 0; }
.partners-track {
  flex: 1; min-width: 0; display: flex; align-items: center; gap: 24px; overflow-x: auto;
  scroll-snap-type: x mandatory; scrollbar-width: none; padding: 6px 0;
}
.partners-track::-webkit-scrollbar { display: none; }
.partner { flex: 0 0 auto; display: inline-flex; align-items: center; justify-content: center; min-width: 220px; scroll-snap-align: start; }
.partner img { display: block; height: 84px; width: auto; max-width: 210px; object-fit: contain; transition: transform .25s; }
.partner:hover img { transform: scale(1.06); }

/* ══ ABOUT ══ */
.dotted {
  /* small, faint dots: the old 6px ones were too heavy behind the about text */
  background-image: radial-gradient(circle, #eef0f6 2px, transparent 2.5px);
  background-size: 24px 24px; background-repeat: repeat;
}
.about { padding: 80px 0 40px; margin-top: 24px; }
.about-grid { display: grid; grid-template-columns: minmax(0, 1.55fr) minmax(0, 1fr); gap: 56px; align-items: start; }
.about .eyebrow { margin-bottom: 22px; }
/* .lp h2 resets margins, so this needs the extra .lp to win */
.lp .about-heading { max-width: 640px; font-size: clamp(16px, 1.25vw, 18px); font-weight: 700; line-height: 1.9; margin-bottom: 40px; }
.features { display: flex; flex-direction: column; gap: 18px; margin-bottom: 36px; }
.feature {
  display: flex; align-items: center; gap: 22px; padding: 26px 26px; border-radius: 14px; background: #f4f5f9;
  box-shadow: 0 10px 28px rgba(15, 23, 60, .06); transition: background .25s, color .25s;
}
.feature h3 { font-size: 17px; font-weight: 800; margin-bottom: 10px; }
.feature p { color: var(--muted); font-size: 14px; line-height: 1.8; }
.feature-icon { width: 52px; height: 52px; border-radius: 10px; display: grid; place-items: center; flex-shrink: 0; background: #e3e3f1; color: var(--navy); transition: background .25s, color .25s; }
.about-btn { float: left; padding: 14px 26px; font-size: 15px; }
.about-visual { position: relative; padding: 14px 20px 0 0; }
.about-frame { position: absolute; top: 0; right: 0; left: 30px; bottom: 36px; border: 1.5px solid var(--orange); border-radius: 18px; }
.about-visual img {
  position: relative; display: block; width: 100%; aspect-ratio: 400 / 600; object-fit: cover; border-radius: 16px;
  border: 8px solid #fff; box-shadow: 0 14px 34px rgba(15, 23, 60, .14);
}

.stats {
  position: relative; overflow: hidden; display: grid; grid-template-columns: repeat(4, 1fr); gap: 18px; margin-top: 72px; padding: 22px;
  border-radius: 24px; background: linear-gradient(135deg, #00004f 0%, #00006e 55%, #1a1a8f 100%);
  box-shadow: 0 22px 50px rgba(0, 0, 110, .25);
}
.stats::before, .stats::after { content: ''; position: absolute; border-radius: 50%; pointer-events: none; }
.stats::before { width: 260px; height: 260px; top: -120px; inset-inline-start: -60px; background: radial-gradient(circle, rgba(255, 182, 26, .25), transparent 70%); }
.stats::after { width: 220px; height: 220px; bottom: -110px; inset-inline-end: -40px; background: radial-gradient(circle, rgba(255, 255, 255, .12), transparent 70%); }
.stat {
  position: relative; z-index: 1; display: flex; flex-direction: column; align-items: center; text-align: center; gap: 6px;
  padding: 26px 14px 22px; border-radius: 18px; background: rgba(255, 255, 255, .07); border: 1px solid rgba(255, 255, 255, .14);
  backdrop-filter: blur(4px); transition: transform .3s ease, background .3s ease;
}
.stat:hover { transform: translateY(-6px); background: rgba(255, 255, 255, .12); }
.stat::after { content: ''; position: absolute; bottom: 0; left: 24%; right: 24%; height: 3px; border-radius: 3px 3px 0 0; background: linear-gradient(90deg, var(--orange), var(--yellow)); opacity: .9; }
.stat-icon { width: 58px; height: 58px; border-radius: 16px; display: grid; place-items: center; margin-bottom: 6px; background: #fff; box-shadow: 0 8px 20px rgba(0, 0, 0, .2); }
.stat strong { font-size: clamp(34px, 3.2vw, 46px); font-weight: 900; line-height: 1.1; color: #fff; direction: ltr; font-variant-numeric: tabular-nums; }
.stat-num { display: inline-block; }
.stat-label { color: rgba(255, 255, 255, .8); font-size: 14.5px; font-weight: 700; }
.stat-navy .stat-icon { color: var(--navy); }
.stat-orange .stat-icon { color: var(--orange); }
.stat-orange strong { color: var(--yellow); }

/* ══ CARDS ══ */
.cards-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; }

.tender-card { border: 1px solid #dfe2ea; border-radius: 14px; overflow: hidden; background: #fff; display: flex; flex-direction: column; }
.tender-img { position: relative; height: 198px; }
.tender-img img { width: 100%; height: 100%; object-fit: cover; display: block; }
.tender-img::after { content: ''; position: absolute; top: 0; right: 0; bottom: 0; left: 0; background: linear-gradient(to bottom, rgba(0, 0, 0, 0) 30%, rgba(0, 0, 0, .78) 100%); }
.status {
  position: absolute; top: 24px; right: 16px; z-index: 1; display: inline-flex; align-items: center; gap: 6px;
  color: #fff; font-size: 13px; font-weight: 700; border-radius: 6px; padding: 4px 10px;
}
.status i { width: 7px; height: 7px; border-radius: 50%; background: #fff; }
.status-open { background: #1b9e5a; }
.status-soon { background: var(--orange); }
.bookmark {
  position: absolute; top: 24px; left: 16px; z-index: 1; width: 44px; height: 44px; border-radius: 8px; border: none;
  background: #fff; color: var(--navy); display: grid; place-items: center; cursor: pointer;
}
.tender-cat {
  position: absolute; bottom: 24px; right: 16px; z-index: 1; color: var(--orange); font-weight: 800; font-size: 15px;
  background: rgba(0, 0, 0, .3); border-radius: 6px; padding: 4px 12px;
}
.tender-body { padding: 20px 16px 22px; display: flex; flex-direction: column; gap: 14px; flex: 1; }
.tender-body h3, .news-body h3 { font-size: 17px; font-weight: 800; line-height: 1.45; }
.tender-body p, .news-body p { color: var(--muted); font-size: 14px; line-height: 1.75; }
.deadline {
  display: flex; align-items: center; justify-content: space-between; gap: 10px; flex-wrap: wrap;
  background: #f4f5f8; border: 1px solid #e3e5eb; border-radius: 12px; padding: 10px 16px; margin-top: auto;
}
.deadline-label { display: inline-flex; align-items: center; gap: 6px; color: #6b7280; font-size: 13px; font-weight: 700; }
.deadline-label svg { color: var(--orange); }
.deadline-date { background: #fff; border: 1px solid #e3e5eb; border-radius: 8px; padding: 6px 14px; font-size: 13px; font-weight: 800; }
.deadline.soon { background: #fde8ea; border-color: #f7c3c8; }
.deadline.soon .deadline-label, .deadline.soon .deadline-label svg, .deadline.soon .deadline-date { color: #d6142b; }
.tender-btn { width: 100%; padding: 12px; font-size: 15px; }

/* ══ DIRECTORY ══ */
.directory { position: relative; overflow: hidden; border-radius: 30px; margin: 140px 0 0; text-align: center; color: #fff; }
.directory-img { position: absolute; top: 0; right: 0; bottom: 0; left: 0; width: 100%; height: 100%; object-fit: cover; }
.directory-shade { position: absolute; top: 0; right: 0; bottom: 0; left: 0; background: linear-gradient(100deg, rgba(40, 40, 160, .86) 0%, rgba(20, 20, 130, .93) 35%, rgba(30, 30, 140, .9) 70%, rgba(130, 80, 60, .8) 100%); }
.directory-inner { position: relative; padding: 40px 24px 40px; display: flex; flex-direction: column; align-items: center; }
.directory h2 { font-size: clamp(24px, 2.4vw, 32px); font-weight: 800; margin-bottom: 18px; color: #fff; }
.directory p { max-width: 760px; font-size: clamp(15px, 1.3vw, 18px); font-weight: 700; line-height: 1.9; color: rgba(255, 255, 255, .88); margin-bottom: 26px; }
.directory-search {
  width: 100%; max-width: 720px; display: flex; align-items: center; gap: 12px; background: #fff;
  border-radius: 14px; padding: 8px 18px 8px 8px;
}
.directory-search-icon { color: #6b7280; flex-shrink: 0; }
.directory-search input { flex: 1; min-width: 0; border: none; outline: none; background: transparent; font-size: 14px; padding: 10px 0; color: var(--ink); }
.directory-search .btn-navy { padding: 12px 26px; font-size: 15px; }

.directory-state { display: flex; align-items: center; gap: 10px; margin-top: 26px; font-weight: 700; color: rgba(255, 255, 255, .9); }
.directory-results { width: 100%; max-width: 1000px; margin-top: 28px; }
.directory-count { font-size: 14px !important; margin-bottom: 14px !important; color: rgba(255, 255, 255, .8) !important; }
.directory-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 14px; text-align: right; }
.dcard { background: #fff; color: var(--ink); border-radius: 14px; padding: 16px; display: flex; flex-direction: column; gap: 10px; animation: dcard-in .45s ease both; }
@keyframes dcard-in { from { opacity: 0; transform: translateY(12px); } to { opacity: 1; transform: none; } }
.dcard-head { display: flex; align-items: flex-start; gap: 10px; }
.dcard-logo { width: 40px; height: 40px; border-radius: 10px; background: var(--navy-soft); color: var(--navy); display: grid; place-items: center; flex-shrink: 0; }
.dcard-title { flex: 1; min-width: 0; }
.dcard h3 { font-size: 15px; font-weight: 800; line-height: 1.45; }
.dcard-num { font-size: 12px; color: var(--muted); }
.dcard-num b { color: var(--navy); }
.dstatus { display: inline-flex; align-items: center; gap: 5px; font-size: 11px; font-weight: 700; border-radius: 999px; padding: 3px 9px; white-space: nowrap; }
.dstatus i { width: 6px; height: 6px; border-radius: 50%; background: currentColor; }
.dstatus-active { background: #e3f6e8; color: var(--green); }
.dstatus-expired { background: #fff1dc; color: var(--orange); }
.dstatus-suspended { background: #fde8ea; color: #d6142b; }
.dcard { box-shadow: 0 8px 24px rgba(10, 10, 60, .12); transition: transform .2s, box-shadow .2s; }
.dcard:hover { transform: translateY(-3px); box-shadow: 0 14px 32px rgba(10, 10, 60, .2); }
.dcard-head { padding-bottom: 12px; border-bottom: 1px solid #eceef3; }
.dcard-meta { display: flex; flex-wrap: wrap; align-items: center; gap: 8px 16px; }
.dcard-wa { margin-inline-start: auto; display: inline-grid; place-items: center; width: 34px; height: 34px; border-radius: 50%; background: #25d366; color: #fff; transition: transform .2s, box-shadow .2s; }
.dcard-wa:hover { transform: scale(1.1); box-shadow: 0 4px 12px rgba(37, 211, 102, .45); }
.dcard-city { display: flex; align-items: center; gap: 6px; color: var(--muted) !important; font-size: 13px !important; margin: 0 !important; line-height: 1.4 !important; }
.dcard-city svg { color: var(--orange); }
.dcard-fields { display: flex; flex-wrap: wrap; gap: 6px; }
.dfield { background: #f4f5f8; border: 1px solid #e3e5eb; border-radius: 8px; padding: 3px 9px; font-size: 12px; font-weight: 600; }
.dfield b { color: var(--orange); font-weight: 700; }
.directory-more { margin-top: 18px; display: inline-flex; align-items: center; gap: 8px; background: rgba(255, 255, 255, .12); color: #fff; border: 1px solid rgba(255, 255, 255, .5); border-radius: 10px; padding: 10px 22px; font-weight: 700; cursor: pointer; }
.directory-more:hover { background: rgba(255, 255, 255, .2); }
.spin { animation: spin 1s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }

/* ══ NEWS ══ */
.news-card { border: 1px solid #dfe2ea; border-radius: 14px; overflow: hidden; background: #fff; display: flex; flex-direction: column; }
.news-img { width: 100%; height: 198px; object-fit: cover; display: block; }
.news-body { padding: 22px 16px 22px; display: flex; flex-direction: column; gap: 12px; flex: 1; }
.news-date { color: #5b6474; font-size: 12px; font-weight: 700; }
.read-more { display: inline-flex; align-items: center; gap: 10px; color: var(--navy); font-weight: 800; font-size: 15px; margin-top: auto; padding-top: 6px; }

/* ══ APP ══ */
.app-wrap { padding-top: 140px; padding-bottom: 88px; }
.app {
  position: relative; overflow: hidden; border-radius: 32px; display: grid; grid-template-columns: 1fr 1fr; align-items: center;
  background: radial-gradient(circle at 42% 50%, #fff 0%, #f1f3f7 30%, #e9ecf2 100%); min-height: 430px; padding: 40px 40px 40px 0;
}
.ball { position: absolute; border-radius: 50%; pointer-events: none; }
.ball-white { width: 58px; height: 58px; left: 70px; top: 18px; background: radial-gradient(circle at 35% 30%, #fff, #e3e6ee); box-shadow: 0 8px 18px rgba(15, 23, 60, .08); }
.ball-orange { width: 150px; height: 150px; left: -40px; bottom: -70px; background: radial-gradient(circle at 35% 30%, #fde3b8, #e6a24a 70%); }
.app-text { position: relative; z-index: 1; padding-inline-start: 0; }
.app-text h2 { font-size: clamp(28px, 2.8vw, 40px); font-weight: 800; line-height: 1.5; margin-bottom: 26px; }
.app-text .navy { color: var(--navy); }
.app-text p { color: var(--muted); font-size: 17px; font-weight: 700; line-height: 1.9; max-width: 380px; margin-bottom: 26px; }
.app-text p.app-flash { font-family: 'Cairo', 'Tajawal', sans-serif; font-size: clamp(21px, 1.9vw, 23px); font-weight: 600; color: #606a7c; min-height: 3.8em; max-width: 420px; }
.app-flash b { color: var(--navy); font-weight: 700; }
.flash-enter-active, .flash-leave-active { transition: opacity .6s ease; }
.flash-enter-from, .flash-leave-to { opacity: 0; }
@media (prefers-reduced-motion: reduce) { .flash-enter-active, .flash-leave-active { transition: none; } }
.store {
  display: inline-flex; align-items: center; gap: 12px; background: #000; color: #fff; border-radius: 10px;
  padding: 10px 20px; direction: ltr;
}
.store span { display: flex; flex-direction: column; font-size: 16px; font-weight: 600; line-height: 1.2; text-align: right; }
.store small { font-size: 12px; direction: rtl; }
.app-arrow { position: absolute; left: 31%; top: 60%; width: 34%; max-width: 420px; pointer-events: none; }
.app-visual { position: relative; z-index: 1; }
.app-visual img { width: 100%; max-width: 400px; display: block; margin-inline: auto; }

/* ══ Responsive ══ */
@media (max-width: 1000px) {
  .about-grid { grid-template-columns: 1fr; gap: 40px; }
  .about-visual { max-width: 420px; margin: 0 auto; }
  .about-btn { float: none; }
  .stats { grid-template-columns: repeat(2, 1fr); }
  .cards-grid, .directory-grid { grid-template-columns: repeat(2, 1fr); }
  .app { grid-template-columns: 1fr; padding: 40px 28px 0; text-align: center; }
  .app-text p { margin-inline: auto; }
  .app-arrow { display: none; }
}
@media (max-width: 720px) {
  .hero { min-height: 0; border-radius: 0 0 28px 28px; }
  .hero-inner { padding-bottom: 48px; }
  .hero-btn { width: 100%; justify-content: center; }
  .hero-btn-solid { justify-content: space-between; }
  .partners { justify-content: flex-start; }
  .partners-carousel { flex-basis: 100%; }
  .partner { min-width: 130px; }
  .partner img { height: 60px; max-width: 150px; }
  .feature { padding: 18px; gap: 14px; }
  .stats { grid-template-columns: 1fr 1fr; gap: 12px; padding: 14px; margin-top: 48px; }
  .stat { padding: 20px 8px 18px; }
  .cards-grid, .directory-grid { grid-template-columns: 1fr; }
  .section { padding-top: 100px; }
  .directory { margin-top: 100px; }
  .dcard-head { flex-wrap: wrap; }
  .dstatus { order: 3; margin-inline-start: 50px; }
  .app-wrap { padding-top: 100px; padding-bottom: 56px; }
  .partners-wrap { padding-bottom: 48px; }
  .ticker-wrap { margin-top: -20px; }
  .lp .about-heading { margin-bottom: 28px; }
  .head-row { align-items: flex-start; }
  .directory { border-radius: 22px; }
  .directory-search .btn-navy { padding: 12px 18px; }
}
</style>

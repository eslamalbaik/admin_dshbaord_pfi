<script setup lang="ts">
import PublicSocialFollow from '@/components/PublicSocialFollow.vue'
import { ref, computed, onMounted, watch, nextTick } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { Search, Calendar, CalendarDays, ChevronLeft, ChevronRight, ChevronDown, SlidersHorizontal, ArrowUpDown, Clock, Tag, ExternalLink, FileText } from 'lucide-vue-next'
import { vHoverCard } from '@/utils/hoverCard'

definePage({ meta: { layout: 'landing', public: true, unauthenticatedOnly: false } })

const BASE = import.meta.env.VITE_API_BASE_URL ?? ''
// صورة العطاءات الأساسية — نفس صورة كروت "أحدث العطاءات" بالرئيسية
// صورة العطاء = صورة تصنيفه الحالية من لوحة التحكم، و/tenders.webp لو التصنيف بلا صورة
const TENDER_IMAGE = '/tenders.webp'
const tenderImage = (t: { category_image?: string | null }) => t.category_image || TENDER_IMAGE

const route = useRoute()
const router = useRouter()

interface TenderAttachment {
  id: number
  label: string | null
  url: string
  is_image: boolean
}

interface Tender {
  id: number
  title: string
  description: string | null
  category: string | null
  category_image?: string | null
  deadline: string | null
  published_at: string | null
  status: 'open' | 'closed' | 'cancelled'
  closing_soon: boolean
  submission_types: string[] | null
  submission_email: string | null
  submission_phone: string | null
  submission_file_url: string | null
  attachments: TenderAttachment[]
  external_url: string | null
  updated_at: string
  issuing_entity?: string | null
  reference_number?: string | null
}

const searchQ = ref('')
const filterStatus = ref('')
const filterCategory = ref('')
const filterUpdatedFrom = ref('')
const sort = ref('latest')

const tenders = ref<Tender[]>([])
const total = ref(0)
const page = ref(1)
const lastPage = ref(1)
const loading = ref(false)

// تفاصيل عطاء واحد عند فتح الصفحة بـ ?id= (من كروت الرئيسية أو زر عرض التفاصيل)
const selected = ref<Tender | null>(null)
const selectedLoading = ref(false)
const selectedError = ref(false)
const detailEl = ref<HTMLElement | null>(null)

const statuses = [
  { value: 'open', label: 'مفتوحة' },
  { value: 'closed', label: 'مغلقة' },
  { value: 'cancelled', label: 'ملغاة' },
]

const sorts = [
  { value: 'latest', label: 'الأحدث إضافة' },
  { value: 'updated_desc', label: 'آخر تحديث' },
  { value: 'deadline_asc', label: 'الأقرب موعداً' },
]

// الفئات المتاحة تُجمع من النتائج (لا endpoint مخصص لها)
const categories = ref<string[]>([])

const statusLabel: Record<string, string> = { open: 'مفتوحة', closed: 'مغلقة', cancelled: 'ملغاة' }
const statusClass: Record<string, string> = { open: 'status-open', closed: 'status-closed', cancelled: 'status-closed' }

// تسميات وألوان شارة الحالة في بطاقات القائمة
const cardStatusLabel: Record<string, string> = { open: 'مفتوح', closed: 'مغلق', cancelled: 'ملغى' }
const cardStatusClass: Record<string, string> = { open: 'cs-open', closed: 'cs-closed', cancelled: 'cs-closed' }

let debounceTimer: ReturnType<typeof setTimeout> | null = null

async function fetchTenders() {
  loading.value = true
  try {
    const params = new URLSearchParams()
    if (searchQ.value) params.set('search', searchQ.value)
    if (filterStatus.value) params.set('status', filterStatus.value)
    if (filterCategory.value) params.set('category', filterCategory.value)
    if (filterUpdatedFrom.value) params.set('updated_from', filterUpdatedFrom.value)
    params.set('sort', sort.value)
    params.set('page', String(page.value))

    const r = await fetch(`${BASE}/api/v1/tenders-public?${params}`)
    const data = await r.json()

    tenders.value = data.items ?? []
    total.value = data.meta?.total ?? tenders.value.length
    lastPage.value = data.meta?.last_page ?? 1

    for (const t of tenders.value) {
      if (t.category && !categories.value.includes(t.category))
        categories.value.push(t.category)
    }
  }
  catch {
    tenders.value = []
  }
  finally {
    loading.value = false
  }
}

watch([filterStatus, filterCategory, filterUpdatedFrom, sort], () => {
  page.value = 1
  fetchTenders()
})

watch(searchQ, () => {
  if (debounceTimer) clearTimeout(debounceTimer)
  debounceTimer = setTimeout(() => {
    page.value = 1
    fetchTenders()
  }, 400)
})

watch(page, fetchTenders)

async function fetchSelected() {
  const id = Number(route.query.id)
  if (!id) {
    selected.value = null
    selectedError.value = false
    return
  }
  selectedLoading.value = true
  selectedError.value = false
  try {
    const r = await fetch(`${BASE}/api/v1/tenders-public/${id}`, { headers: { Accept: 'application/json' } })
    if (!r.ok) throw new Error(String(r.status))
    const data = await r.json()
    selected.value = data.items ?? data.data ?? null
    if (!selected.value) throw new Error('empty')
  }
  catch {
    selected.value = null
    selectedError.value = true
  }
  finally {
    selectedLoading.value = false
  }
  await nextTick()
  detailEl.value?.scrollIntoView({ behavior: 'smooth', block: 'start' })
}

watch(() => route.query.id, fetchSelected)

onMounted(() => {
  fetchTenders()
  fetchSelected()
})

function closeDetail() {
  router.push({ path: '/landing/public-tenders' })
}

function resetFilters() {
  searchQ.value = ''
  filterStatus.value = ''
  filterCategory.value = ''
  filterUpdatedFrom.value = ''
  sort.value = 'latest'
}

function fmtDate(d: string | null) {
  if (!d) return '—'
  return new Date(d).toLocaleDateString('ar-EG')
}

// تاريخ بأرقام لاتينية وأسماء أشهر عربية، مثل: 25 نوفمبر 2025
function fmtLongDate(d: string | null) {
  if (!d) return '—'
  return new Date(d).toLocaleDateString('ar-u-nu-latn', { day: 'numeric', month: 'long', year: 'numeric' })
}

// الوقت يظهر فقط لو الـ deadline يحمل وقتاً فعلياً (غير منتصف الليل)
function fmtTime(d: string | null) {
  if (!d || !/[T ]\d{2}:\d{2}/.test(d)) return ''
  const dt = new Date(d)
  if (Number.isNaN(dt.getTime()) || (dt.getHours() === 0 && dt.getMinutes() === 0)) return ''
  return dt.toLocaleTimeString('ar-u-nu-latn', { hour: '2-digit', minute: '2-digit', hour12: true })
}

// أرقام الصفحات: كلها لو قليلة، وإلا نافذة حول الصفحة الحالية مع نقاط
const pageNumbers = computed<(number | '…')[]>(() => {
  const last = lastPage.value
  const cur = page.value
  if (last <= 7) return Array.from({ length: last }, (_, i) => i + 1)
  const out: (number | '…')[] = [1]
  const from = Math.max(2, cur - 1)
  const to = Math.min(last - 1, cur + 1)
  if (from > 2) out.push('…')
  for (let p = from; p <= to; p++) out.push(p)
  if (to < last - 1) out.push('…')
  out.push(last)
  return out
})

function openTender(t: Tender) {
  router.push({ path: '/landing/public-tenders', query: { id: String(t.id) } })
}
</script>

<template>
  <div dir="rtl" class="pub-page">
        <div class="page-hero">
      <div class="page-hero-shapes"><div class="ph-s ph-s1" /><div class="ph-s ph-s2" /></div>
      <div class="pub-cont page-hero-inner">
        <div class="page-breadcrumb"><RouterLink to="/landing">الرئيسية</RouterLink><span>/</span><span>العطاءات</span></div>
        <h1 class="page-hero-title">العطاءات</h1>
        <p class="page-hero-desc">أحدث العطاءات الحكومية المتاحة للشركات الأعضاء في الاتحاد</p>
      </div>
      <div class="pub-follow-slot"><PublicSocialFollow /></div>
    </div>

    <section class="pub-section tenders-section">
      <svg class="tenders-skyline" viewBox="0 0 320 180" fill="none" aria-hidden="true">
        <g fill="#dbe4f5">
          <rect x="10" y="90" width="30" height="90" rx="2" /><rect x="48" y="50" width="34" height="130" rx="2" />
          <rect x="90" y="75" width="28" height="105" rx="2" /><rect x="126" y="15" width="40" height="165" rx="2" />
          <rect x="174" y="60" width="30" height="120" rx="2" /><rect x="212" y="95" width="32" height="85" rx="2" />
          <rect x="252" y="115" width="30" height="65" rx="2" />
        </g>
      </svg>
      <div class="pub-cont">
        <div v-if="route.query.id" ref="detailEl" class="tender-detail">
          <div v-if="selectedLoading" class="detail-msg">جاري تحميل تفاصيل العطاء...</div>
          <div v-else-if="selectedError" class="detail-msg">
            لم يتم العثور على هذا العطاء.
            <button class="filter-reset" style="margin: 1rem auto 0;" @click="closeDetail">عرض كل العطاءات</button>
          </div>
          <template v-else-if="selected">
            <div class="detail-img">
              <img :src="tenderImage(selected)" :alt="selected.title">
            </div>
            <div class="detail-body">
              <div class="tender-meta-top">
                <span class="tender-ref">PCU-{{ selected.id }}</span>
                <span v-if="selected.category" class="tender-sector detail-sector">{{ selected.category }}</span>
                <span class="tender-status" :class="statusClass[selected.status]">{{ statusLabel[selected.status] ?? selected.status }}</span>
                <span v-if="selected.closing_soon" class="tender-status status-closing">ينتهي قريباً</span>
              </div>
              <h2 class="detail-title">{{ selected.title }}</h2>
              <p v-if="selected.issuing_entity" class="detail-entity">الجهة المعلنة: {{ selected.issuing_entity }}</p>
              <div class="tender-info">
                <span><Calendar :size="13" /> تاريخ النشر: {{ fmtDate(selected.published_at) }}</span>
                <span><Calendar :size="13" /> آخر موعد: {{ fmtDate(selected.deadline) }}</span>
                <span><Calendar :size="13" /> آخر تحديث: {{ fmtDate(selected.updated_at) }}</span>
              </div>
              <p v-if="selected.description" class="detail-desc">{{ selected.description }}</p>
              <div class="tender-info detail-contact">
                <span v-if="selected.submission_email">البريد للتقديم: {{ selected.submission_email }}</span>
                <span v-if="selected.submission_phone">هاتف: {{ selected.submission_phone }}</span>
                <a v-if="selected.submission_file_url" :href="selected.submission_file_url" target="_blank" rel="noopener"><FileText :size="13" /> ملف العطاء</a>
              </div>
              <div v-if="selected.attachments?.length" class="tender-attachments">
                <a v-for="att in selected.attachments" :key="att.id" :href="att.url" target="_blank" rel="noopener" class="tender-attachment">
                  <img v-if="att.is_image" :src="att.url" :alt="att.label ?? 'مرفق'" class="tender-attachment-img" />
                  <FileText v-else :size="13" />
                  <span>{{ att.label || 'مرفق' }}</span>
                </a>
              </div>
              <div class="detail-actions">
                <a v-if="selected.external_url" :href="selected.external_url" target="_blank" rel="noopener" class="detail-btn">رابط العطاء <ExternalLink :size="14" /></a>
                <button class="filter-reset" @click="closeDetail">عرض كل العطاءات <ChevronLeft :size="14" /></button>
              </div>
            </div>
          </template>
        </div>

        <div class="tender-filters">
          <div class="search-wrap">
            <Search :size="17" class="search-ico" />
            <input v-model="searchQ" type="text" placeholder="ابحث في العطاءات..." class="search-input" />
          </div>
          <div class="sel-wrap">
            <select v-model="filterCategory" class="filter-sel">
              <option value="">كل الفئات</option>
              <option v-for="c in categories" :key="c" :value="c">{{ c }}</option>
            </select>
            <Tag :size="15" class="sel-ico" />
            <ChevronDown :size="15" class="sel-chev" />
          </div>
          <div class="sel-wrap">
            <select v-model="filterStatus" class="filter-sel">
              <option value="">حالة العطاء</option>
              <option v-for="s in statuses" :key="s.value" :value="s.value">{{ s.label }}</option>
            </select>
            <ChevronDown :size="15" class="sel-chev" />
          </div>
          <div class="sel-wrap" title="آخر تحديث منذ تاريخ">
            <input v-model="filterUpdatedFrom" type="date" class="filter-sel filter-date" />
            <CalendarDays :size="15" class="sel-ico" />
          </div>
          <button class="adv-btn" @click="resetFilters">
            <SlidersHorizontal :size="16" /> إعادة تعيين
          </button>
        </div>

        <div class="results-bar">
          <p class="results-count">عدد النتائج ( {{ total }} )</p>
          <label class="sort-wrap">
            <ArrowUpDown :size="14" />
            <span>ترتيب حسب :</span>
            <select v-model="sort" class="sort-sel">
              <option v-for="s in sorts" :key="s.value" :value="s.value">{{ s.label }}</option>
            </select>
          </label>
        </div>

        <div class="tenders-list">
          <article
            v-for="t in tenders"
            :key="t.id"
            v-hover-card
            class="tender-card"
          >
            <div class="tender-thumb hc-media">
              <img :src="tenderImage(t)" :alt="t.title" loading="lazy">
            </div>
            <div class="tender-body">
              <div class="tender-headline">
                <h3 class="tender-title hc-title">{{ t.title }}</h3>
                <span class="card-status" :class="cardStatusClass[t.status]"><i />{{ cardStatusLabel[t.status] ?? t.status }}</span>
                <span v-if="t.closing_soon" class="card-status cs-soon"><i />ينتهي قريباً</span>
              </div>
              <div class="tender-facts">
                <span class="fact"><Calendar :size="15" /> {{ t.status === 'open' ? 'ينتهي في' : 'انتهى في' }} {{ fmtLongDate(t.deadline) }}</span>
                <template v-if="fmtTime(t.deadline)">
                  <span class="fact-sep" />
                  <span class="fact"><Clock :size="15" /> {{ fmtTime(t.deadline) }}</span>
                </template>
                <template v-if="t.category">
                  <span class="fact-sep" />
                  <span class="fact"><Tag :size="15" /> {{ t.category }}</span>
                </template>
              </div>
            </div>
            <button class="tender-btn" @click="openTender(t)">
              عرض التفاصيل <ChevronLeft :size="14" class="hc-arrow" />
            </button>
          </article>
        </div>

        <div v-if="!loading && !tenders.length" class="empty-state">
          <Search :size="48" style="color:#c5cae9" />
          <p>لا توجد عطاءات مطابقة</p>
        </div>

        <nav v-if="lastPage > 1" class="pager" aria-label="التنقل بين الصفحات">
          <button class="pg-btn" :disabled="page <= 1" aria-label="السابق" @click="page--"><ChevronRight :size="16" /></button>
          <template v-for="(n, i) in pageNumbers" :key="i">
            <span v-if="n === '…'" class="pg-dots">…</span>
            <button v-else class="pg-btn pg-num" :class="{ active: n === page }" @click="page = n">{{ n }}</button>
          </template>
          <button class="pg-btn" :disabled="page >= lastPage" aria-label="التالي" @click="page++"><ChevronLeft :size="16" /></button>
        </nav>
      </div>
    </section>

      </div>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800;900&family=Tajawal:wght@400;500;700;800&display=swap');
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
.pub-page { font-family: 'Tajawal', 'Cairo', sans-serif; direction: rtl; color: #374151; background: #fff; }
.pub-cont { max-width: 1240px; margin: 0 auto; padding: 0 1.5rem; }
.pub-section { padding: 5rem 0; }
.page-hero { background: linear-gradient(145deg, #0d1b4b 0%, #1a237e 60%, #283593 100%); margin-top: calc(-1 * var(--pub-nav-h, 114px)); padding: calc(var(--pub-nav-h, 114px) + 2.5rem) 0 3rem; position: relative; overflow: hidden; }
.page-hero-shapes { position: absolute; top: 0; right: 0; bottom: 0; left: 0; pointer-events: none; }
.ph-s { position: absolute; border-radius: 50%; }
.ph-s1 { width: 400px; height: 400px; background: radial-gradient(circle, rgba(249,168,37,.12), transparent 65%); top: -100px; left: 5%; }
.ph-s2 { width: 300px; height: 300px; background: radial-gradient(circle, rgba(255,255,255,.05), transparent 65%); bottom: -80px; right: 10%; }
.page-hero-inner { position: relative; z-index: 2; }
.page-breadcrumb { display: flex; align-items: center; gap: .5rem; font-size: .82rem; color: rgba(255,255,255,.6); margin-bottom: 1rem; }
.page-breadcrumb a { color: rgba(255,255,255,.6); text-decoration: none; } .page-breadcrumb a:hover { color: #f9a825; }
.page-hero-title { font-family: 'Tajawal', 'Cairo', sans-serif; font-size: clamp(2rem,4vw,3rem); font-weight: 900; color: #fff; margin-bottom: .75rem; }
.page-hero-desc { font-size: 1rem; color: rgba(255,255,255,.75); line-height: 1.75; max-width: 600px; }

.tenders-section { position: relative; overflow: hidden; background: linear-gradient(180deg, #f3f6fc 0%, #fbfcfe 100%); }
.tenders-section > .pub-cont { position: relative; z-index: 1; }
.tenders-skyline { position: absolute; left: 0; bottom: 0; width: 340px; opacity: .8; pointer-events: none; }

/* الفلاتر: شريط أبيض واحد بعناصر دائرية */
.tender-filters { display: flex; align-items: center; gap: .75rem; flex-wrap: wrap; background: #fff; border-radius: 20px; padding: .8rem 1rem; margin-bottom: 1.5rem; box-shadow: 0 8px 28px rgba(26, 35, 126, .08); }
.search-wrap { position: relative; flex: 1.6 1 240px; }
.search-ico { position: absolute; top: 50%; left: 1rem; transform: translateY(-50%); color: #1a237e; pointer-events: none; }
.search-input { width: 100%; border: 1.5px solid #e5e9f2; border-radius: 50px; padding: .65rem 1.1rem .65rem 2.8rem; font-size: .88rem; font-family: 'Tajawal', sans-serif; color: #374151; background: #fff; outline: none; transition: border-color .2s; }
.search-input:focus { border-color: #1a237e; }
.sel-wrap { position: relative; flex: 1 1 150px; }
.filter-sel { width: 100%; appearance: none; -webkit-appearance: none; border: 1.5px solid #e5e9f2; border-radius: 50px; padding: .65rem 2.4rem .65rem 2.2rem; font-size: .85rem; font-family: 'Tajawal', sans-serif; color: #374151; background: #fff; outline: none; cursor: pointer; transition: border-color .2s; }
.filter-sel:focus { border-color: #1a237e; }
.filter-date { padding-left: 2.2rem; }
.sel-ico { position: absolute; top: 50%; right: .9rem; transform: translateY(-50%); color: #6b7280; pointer-events: none; }
.sel-chev { position: absolute; top: 50%; left: .9rem; transform: translateY(-50%); color: #6b7280; pointer-events: none; }
.sel-wrap:has(.filter-date) .sel-ico { right: auto; left: .9rem; }
.adv-btn { display: inline-flex; align-items: center; justify-content: center; gap: .45rem; background: linear-gradient(145deg, #1a237e, #0d1b4b); color: #fff; border: none; border-radius: 50px; padding: .7rem 1.5rem; font-size: .85rem; font-weight: 700; font-family: 'Tajawal', sans-serif; cursor: pointer; white-space: nowrap; box-shadow: 0 6px 16px rgba(13, 27, 75, .3); transition: transform .2s, filter .2s; }
.adv-btn:hover { transform: translateY(-1px); filter: brightness(1.12); }
.filter-reset { display: flex; align-items: center; gap: .4rem; background: #e8eaf6; color: #1a237e; border: 1.5px solid #c5cae9; border-radius: 9px; padding: .55rem 1rem; font-size: .83rem; font-weight: 600; cursor: pointer; font-family: 'Tajawal', sans-serif; }
.filter-reset:hover { background: #1a237e; color: #fff; }

.results-bar { display: flex; align-items: center; justify-content: space-between; gap: 1rem; margin: 0 .5rem 1rem; }
.results-count { font-size: .85rem; font-weight: 700; color: #374151; }
.sort-wrap { display: inline-flex; align-items: center; gap: .4rem; font-size: .82rem; color: #374151; font-weight: 600; }
.sort-sel { border: none; background: transparent; font-size: .82rem; font-weight: 700; font-family: 'Tajawal', sans-serif; color: #1a237e; cursor: pointer; outline: none; }

/* بطاقات العطاءات */
.tenders-list { display: flex; flex-direction: column; gap: .9rem; }
.tender-card { display: flex; gap: 1.1rem; align-items: center; background: #fff; border-radius: 16px; padding: .7rem .9rem; box-shadow: 0 5px 20px rgba(26, 35, 126, .07); transition: transform .25s, box-shadow .25s; }
.tender-card:hover { transform: translateY(-2px); box-shadow: 0 12px 28px rgba(26, 35, 126, .13); }
.tender-thumb { width: 118px; height: 82px; flex-shrink: 0; border-radius: 12px; overflow: hidden; background: #eef2fa; }
.tender-thumb img { width: 100%; height: 100%; object-fit: cover; display: block; }
.tender-body { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: .7rem; }
.tender-headline { display: flex; align-items: center; gap: .75rem; flex-wrap: wrap; }
.tender-title { font-family: 'Tajawal', 'Cairo', sans-serif; font-size: 1.05rem; font-weight: 800; color: #14275f; line-height: 1.5; }
.card-status { display: inline-flex; align-items: center; gap: .4rem; font-size: .75rem; font-weight: 700; border-radius: 50px; padding: .15rem .75rem; }
.card-status i { width: 7px; height: 7px; border-radius: 50%; background: currentColor; }
.cs-open { background: #e3f5e6; color: #2e7d32; }
.cs-closed { background: #fde6e6; color: #d32f2f; }
.cs-soon { background: #fff1de; color: #ef6c00; }
.tender-facts { display: flex; align-items: center; flex-wrap: wrap; gap: .5rem 0; }
.fact { display: inline-flex; align-items: center; gap: .4rem; font-size: .8rem; color: #4b5563; }
.fact svg { color: #6b7280; }
.fact-sep { width: 1px; height: 14px; background: #d5dae6; margin: 0 .9rem; }
.tender-btn { display: inline-flex; align-items: center; gap: .4rem; border: none; border-radius: 50px; padding: .65rem 1.4rem; font-size: .83rem; font-weight: 700; color: #fff; background: linear-gradient(145deg, #1a237e, #0d1b4b); cursor: pointer; font-family: 'Tajawal', sans-serif; transition: transform .2s, filter .2s; white-space: nowrap; flex-shrink: 0; box-shadow: 0 5px 14px rgba(13, 27, 75, .28); }
.tender-btn:hover { filter: brightness(1.15); transform: translateY(-1px); }

/* التقسيم */
.pager { display: flex; align-items: center; justify-content: center; gap: .55rem; margin-top: 2rem; }
.pg-btn { width: 38px; height: 38px; border-radius: 50%; border: 1.5px solid #e1e6f1; background: #fff; color: #14275f; font-size: .85rem; font-weight: 700; font-family: 'Tajawal', sans-serif; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; transition: all .2s; }
.pg-btn:hover:not(:disabled) { border-color: #1a237e; }
.pg-btn:disabled { opacity: .45; cursor: not-allowed; }
.pg-num.active { background: #1a237e; border-color: #1a237e; color: #fff; box-shadow: 0 5px 14px rgba(26, 35, 126, .3); }
.pg-dots { color: #9ca3af; padding: 0 .2rem; }

/* عناصر مشتركة مع لوحة تفاصيل العطاء */
.tender-meta-top { display: flex; gap: .5rem; flex-wrap: wrap; margin-bottom: .5rem; align-items: center; }
.tender-ref { font-family: 'Tajawal', 'Cairo', sans-serif; font-size: .75rem; font-weight: 800; color: #9ca3af; letter-spacing: .02em; }
.tender-sector { font-size: .75rem; font-weight: 700; border: 1px solid; border-radius: 50px; padding: .15rem .65rem; }
.tender-status { font-size: .75rem; font-weight: 700; border-radius: 50px; padding: .15rem .65rem; }
.status-open    { background: #e8f5e9; color: #2e7d32; }
.status-closing { background: #fff8e1; color: #e65100; }
.status-closed  { background: #f3f4f6; color: #9ca3af; }
.tender-info { display: flex; gap: 1.25rem; flex-wrap: wrap; }
.tender-info span { display: flex; align-items: center; gap: .35rem; font-size: .8rem; color: #6b7280; }
.tender-details { margin-top: .75rem; padding-top: .75rem; border-top: 1px dashed #e5e7eb; font-size: .85rem; color: #6b7280; line-height: 1.8; }
.tender-details a { color: #1a237e; display: inline-flex; align-items: center; gap: .3rem; }
.tender-attachments { display: flex; flex-wrap: wrap; gap: .5rem; margin-top: .75rem; }
.tender-attachment { display: flex; align-items: center; gap: .4rem; background: #f3f4f6; border: 1px solid #e5e7eb; border-radius: 8px; padding: .3rem .6rem; font-size: .78rem; color: #1a237e; text-decoration: none; }
.tender-attachment:hover { background: #e8eaf6; }
.tender-attachment-img { width: 18px; height: 18px; object-fit: cover; border-radius: 4px; }
.tender-detail { display: grid; grid-template-columns: minmax(0, 5fr) minmax(0, 7fr); background: #fff; border: 1.5px solid #e5e7eb; border-radius: 18px; overflow: hidden; margin-bottom: 2.5rem; box-shadow: 0 10px 30px rgba(13, 27, 75, .08); scroll-margin-top: calc(var(--pub-nav-h, 114px) + 1rem); }
.detail-msg { grid-column: 1 / -1; text-align: center; padding: 3rem 1.5rem; color: #6b7280; display: flex; flex-direction: column; align-items: center; }
.detail-img { min-height: 260px; }
.detail-img img { width: 100%; height: 100%; object-fit: cover; display: block; }
.detail-body { padding: 1.75rem; display: flex; flex-direction: column; gap: .9rem; }
.detail-sector { background: #e8eaf6; color: #1a237e; border-color: #c5cae9; }
.detail-title { font-family: 'Tajawal', 'Cairo', sans-serif; font-size: clamp(1.25rem, 2.4vw, 1.6rem); font-weight: 800; color: #0d1b3e; line-height: 1.5; }
.detail-entity { font-size: .9rem; color: #374151; font-weight: 600; }
.detail-desc { font-size: .95rem; color: #4b5563; line-height: 1.9; white-space: pre-line; }
.detail-contact { font-size: .85rem; }
.detail-contact a { color: #1a237e; display: inline-flex; align-items: center; gap: .3rem; font-size: .8rem; }
.detail-actions { display: flex; gap: .75rem; flex-wrap: wrap; margin-top: .5rem; }
.detail-btn { display: inline-flex; align-items: center; gap: .4rem; background: #1a237e; color: #fff; border-radius: 9px; padding: .55rem 1.1rem; font-size: .85rem; font-weight: 700; text-decoration: none; }
@media (max-width: 860px) { .tender-detail { grid-template-columns: 1fr; } .detail-img { min-height: 0; height: 220px; } .detail-body { padding: 1.25rem; } }
.empty-state { text-align: center; padding: 4rem 2rem; color: #9ca3af; }
.empty-state p { font-size: 1rem; margin-top: 1rem; }

@media (max-width: 760px) {
  .tender-card { flex-wrap: wrap; }
  .tender-thumb { width: 100%; height: 160px; }
  .tender-body { flex-basis: 100%; }
  .tender-btn { width: 100%; justify-content: center; }
  .adv-btn { width: 100%; }
  .tenders-skyline { display: none; }
}
</style>

<script setup lang="ts">
import { ref, onMounted, watch } from 'vue'
import { Search, Calendar, ChevronLeft, Filter, DollarSign, ExternalLink, FileText } from 'lucide-vue-next'

definePage({ meta: { layout: 'landing', public: true, unauthenticatedOnly: false } })

const BASE = import.meta.env.VITE_API_BASE_URL ?? ''

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
  budget: string | null
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
const expanded = ref<number | null>(null)

const statuses = [
  { value: 'open', label: 'مفتوحة' },
  { value: 'closed', label: 'مغلقة' },
  { value: 'cancelled', label: 'ملغاة' },
]

const sorts = [
  { value: 'latest', label: 'الأحدث إضافة' },
  { value: 'updated_desc', label: 'آخر تحديث' },
  { value: 'deadline_asc', label: 'الأقرب موعداً' },
  { value: 'budget_desc', label: 'الأعلى ميزانية' },
]

// الفئات المتاحة تُجمع من النتائج (لا endpoint مخصص لها)
const categories = ref<string[]>([])

const statusLabel: Record<string, string> = { open: 'مفتوحة', closed: 'مغلقة', cancelled: 'ملغاة' }
const statusClass: Record<string, string> = { open: 'status-open', closed: 'status-closed', cancelled: 'status-closed' }

const cardColors = ['#2e7d32', '#1a237e', '#e65100', '#6a1b9a', '#c62828', '#00695c']
const colorFor = (id: number) => cardColors[id % cardColors.length]

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

onMounted(fetchTenders)

function resetFilters() {
  searchQ.value = ''
  filterStatus.value = ''
  filterCategory.value = ''
  filterUpdatedFrom.value = ''
  sort.value = 'latest'
}

function fmtBudget(b: string | null) {
  if (!b) return '—'
  return `${Number(b).toLocaleString('ar-EG')} $`
}

function fmtDate(d: string | null) {
  if (!d) return '—'
  return new Date(d).toLocaleDateString('ar-EG')
}

function openTender(t: Tender) {
  // رابط خارجي إن وُجد (تفاصيل العطاء أو رابط التقديم)، وإلا توسيع البطاقة
  if (t.external_url)
    window.open(t.external_url, '_blank', 'noopener')
  else
    expanded.value = expanded.value === t.id ? null : t.id
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
    </div>

    <section class="pub-section">
      <div class="pub-cont">
        <div class="tender-filters">
          <div class="search-wrap">
            <Search :size="16" class="search-ico" />
            <input v-model="searchQ" type="text" placeholder="ابحث في العطاءات..." class="search-input" />
          </div>
          <div class="filter-row">
            <select v-model="filterStatus" class="filter-sel">
              <option value="">كل الحالات</option>
              <option v-for="s in statuses" :key="s.value" :value="s.value">{{ s.label }}</option>
            </select>
            <select v-model="filterCategory" class="filter-sel">
              <option value="">كل الفئات</option>
              <option v-for="c in categories" :key="c" :value="c">{{ c }}</option>
            </select>
            <input
              v-model="filterUpdatedFrom"
              type="date"
              class="filter-sel"
              title="آخر تحديث منذ تاريخ"
            />
            <select v-model="sort" class="filter-sel">
              <option v-for="s in sorts" :key="s.value" :value="s.value">{{ s.label }}</option>
            </select>
            <button class="filter-reset" @click="resetFilters">
              <Filter :size="14" /> إعادة تعيين
            </button>
          </div>
        </div>
        <p class="results-count">{{ total }} عطاء</p>

        <div class="tenders-list">
          <div
            v-for="t in tenders"
            :key="t.id"
            class="tender-card"
            :style="`--tc:${colorFor(t.id)}`"
          >
            <div class="tender-color-bar" />
            <div class="tender-body">
              <div class="tender-top">
                <div>
                  <div class="tender-meta-top">
                    <span class="tender-ref">PCU-{{ t.id }}</span>
                    <span
                      v-if="t.category"
                      class="tender-sector"
                      :style="`background:${colorFor(t.id)}15;color:${colorFor(t.id)};border-color:${colorFor(t.id)}40`"
                    >{{ t.category }}</span>
                    <span class="tender-status" :class="statusClass[t.status]">{{ statusLabel[t.status] ?? t.status }}</span>
                    <span v-if="t.closing_soon" class="tender-status status-closing">ينتهي قريباً</span>
                  </div>
                  <h3 class="tender-title">{{ t.title }}</h3>
                </div>
                <div class="tender-budget">
                  <DollarSign :size="16" style="color:#f9a825" />
                  <span>{{ fmtBudget(t.budget) }}</span>
                </div>
              </div>
              <div class="tender-info">
                <span><Calendar :size="13" /> تاريخ النشر: {{ fmtDate(t.published_at) }}</span>
                <span><Calendar :size="13" /> آخر موعد: {{ fmtDate(t.deadline) }}</span>
                <span><Calendar :size="13" /> آخر تحديث: {{ fmtDate(t.updated_at) }}</span>
              </div>
              <div v-if="expanded === t.id" class="tender-details">
                <p v-if="t.description">{{ t.description }}</p>
                <div class="tender-info" style="margin-top: .5rem;">
                  <span v-if="t.submission_email">البريد للتقديم: {{ t.submission_email }}</span>
                  <span v-if="t.submission_phone">هاتف: {{ t.submission_phone }}</span>
                  <a
                    v-if="t.submission_file_url"
                    :href="t.submission_file_url"
                    target="_blank"
                    rel="noopener"
                  ><FileText :size="13" /> ملف العطاء</a>
                </div>
                <div v-if="t.attachments?.length" class="tender-attachments">
                  <a
                    v-for="att in t.attachments"
                    :key="att.id"
                    :href="att.url"
                    target="_blank"
                    rel="noopener"
                    class="tender-attachment"
                  >
                    <img v-if="att.is_image" :src="att.url" :alt="att.label ?? 'مرفق'" class="tender-attachment-img" />
                    <FileText v-else :size="13" />
                    <span>{{ att.label || 'مرفق' }}</span>
                  </a>
                </div>
              </div>
            </div>
            <button
              class="tender-btn"
              :style="`background:${colorFor(t.id)}`"
              @click="openTender(t)"
            >
              <template v-if="t.external_url">
                رابط العطاء <ExternalLink :size="14" />
              </template>
              <template v-else>
                عرض التفاصيل <ChevronLeft :size="14" />
              </template>
            </button>
          </div>
        </div>

        <div v-if="!loading && !tenders.length" class="empty-state">
          <Search :size="48" style="color:#c5cae9" />
          <p>لا توجد عطاءات مطابقة</p>
        </div>

        <div v-if="lastPage > 1" class="filter-row" style="justify-content: center; margin-top: 2rem;">
          <button class="filter-reset" :disabled="page <= 1" @click="page--">
            السابق
          </button>
          <span class="results-count" style="margin: 0; align-self: center;">صفحة {{ page }} من {{ lastPage }}</span>
          <button class="filter-reset" :disabled="page >= lastPage" @click="page++">
            التالي
          </button>
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

.tender-filters { background: #f9fafb; border: 1.5px solid #e5e7eb; border-radius: 16px; padding: 1.5rem; margin-bottom: 2rem; display: flex; flex-direction: column; gap: 1rem; }
.search-wrap { position: relative; }
.search-ico { position: absolute; top: 50%; right: 1rem; transform: translateY(-50%); color: #9ca3af; }
.search-input { width: 100%; border: 1.5px solid #e5e7eb; border-radius: 10px; padding: .75rem 2.75rem .75rem 1rem; font-size: .9rem; font-family: 'Neo Sans Arabic', 'Tajawal', sans-serif; color: #374151; background: #fff; outline: none; transition: border-color .2s; }
.search-input:focus { border-color: #1a237e; }
.filter-row { display: flex; gap: .875rem; flex-wrap: wrap; }
.filter-sel { border: 1.5px solid #e5e7eb; border-radius: 9px; padding: .55rem 1rem; font-size: .85rem; font-family: 'Neo Sans Arabic', 'Tajawal', sans-serif; color: #374151; background: #fff; outline: none; cursor: pointer; }
.filter-sel:focus { border-color: #1a237e; }
.filter-reset { display: flex; align-items: center; gap: .4rem; background: #e8eaf6; color: #1a237e; border: 1.5px solid #c5cae9; border-radius: 9px; padding: .55rem 1rem; font-size: .83rem; font-weight: 600; cursor: pointer; font-family: 'Neo Sans Arabic', 'Tajawal', sans-serif; }
.filter-reset:hover { background: #1a237e; color: #fff; }
.results-count { font-size: .85rem; color: #9ca3af; margin-bottom: 1.25rem; }

.tenders-list { display: flex; flex-direction: column; gap: 1.25rem; }
.tender-card { display: flex; gap: 1.5rem; align-items: center; background: #fff; border: 1.5px solid #e5e7eb; border-radius: 16px; overflow: hidden; transition: all .25s; }
.tender-card:hover { box-shadow: 0 8px 24px rgba(0,0,0,.1); transform: translateY(-3px); border-color: #c5cae9; }
.tender-color-bar { width: 6px; background: var(--tc); flex-shrink: 0; align-self: stretch; }
.tender-body { flex: 1; min-width: 0; padding: 1.25rem 0; }
.tender-top { display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem; margin-bottom: .875rem; }
.tender-meta-top { display: flex; gap: .5rem; flex-wrap: wrap; margin-bottom: .5rem; align-items: center; }
.tender-ref { font-family: 'Neo Sans Arabic', 'Cairo', sans-serif; font-size: .75rem; font-weight: 800; color: #9ca3af; letter-spacing: .02em; }
.tender-sector { font-size: .75rem; font-weight: 700; border: 1px solid; border-radius: 50px; padding: .15rem .65rem; }
.tender-status { font-size: .75rem; font-weight: 700; border-radius: 50px; padding: .15rem .65rem; }
.status-open    { background: #e8f5e9; color: #2e7d32; }
.status-closing { background: #fff8e1; color: #e65100; }
.status-closed  { background: #f3f4f6; color: #9ca3af; }
.tender-title { font-family: 'Neo Sans Arabic', 'Cairo', sans-serif; font-size: 1rem; font-weight: 800; color: #0d1b3e; line-height: 1.5; }
.tender-budget { display: flex; align-items: center; gap: .35rem; font-family: 'Neo Sans Arabic', 'Cairo', sans-serif; font-size: .95rem; font-weight: 900; color: #0d1b3e; white-space: nowrap; }
.tender-info { display: flex; gap: 1.25rem; flex-wrap: wrap; }
.tender-info span { display: flex; align-items: center; gap: .35rem; font-size: .8rem; color: #6b7280; }
.tender-pub { background: #f3f4f6; border-radius: 50px; padding: .15rem .65rem; }
.tender-btn { display: flex; align-items: center; gap: .4rem; border: none; border-radius: 10px; margin: 1rem 1.25rem 1rem 0; padding: .65rem 1.25rem; font-size: .83rem; font-weight: 700; color: #fff; cursor: pointer; font-family: 'Neo Sans Arabic', 'Tajawal', sans-serif; transition: all .2s; white-space: nowrap; flex-shrink: 0; }
.tender-btn:hover { filter: brightness(1.08); transform: translateY(-1px); }
.tender-details { margin-top: .75rem; padding-top: .75rem; border-top: 1px dashed #e5e7eb; font-size: .85rem; color: #6b7280; line-height: 1.8; }
.tender-details a { color: #1a237e; display: inline-flex; align-items: center; gap: .3rem; }
.tender-attachments { display: flex; flex-wrap: wrap; gap: .5rem; margin-top: .75rem; }
.tender-attachment { display: flex; align-items: center; gap: .4rem; background: #f3f4f6; border: 1px solid #e5e7eb; border-radius: 8px; padding: .3rem .6rem; font-size: .78rem; color: #1a237e; text-decoration: none; }
.tender-attachment:hover { background: #e8eaf6; }
.tender-attachment-img { width: 18px; height: 18px; object-fit: cover; border-radius: 4px; }
.empty-state { text-align: center; padding: 4rem 2rem; color: #9ca3af; }
.empty-state p { font-size: 1rem; margin-top: 1rem; }

@media (max-width: 700px) { .tender-card { flex-wrap: wrap; } .tender-color-bar { width: 100%; height: 5px; align-self: auto; } .tender-btn { margin: 0 1rem 1rem; width: calc(100% - 2rem); justify-content: center; } .tender-top { flex-direction: column; } }
</style>

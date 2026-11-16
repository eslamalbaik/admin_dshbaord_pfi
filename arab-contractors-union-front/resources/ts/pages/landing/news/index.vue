<script setup lang="ts">
import PublicSocialFollow from '@/components/PublicSocialFollow.vue'
import { ref, watch, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '@/plugins/axios'
import { Search, Inbox, Play, Images, CalendarDays, FolderOpen, Newspaper, Megaphone, Users, Building2, Landmark, Info, ListChecks, Workflow, Sparkles } from 'lucide-vue-next'
import { vHoverCard } from '@/utils/hoverCard'

definePage({
  meta: {
    layout: 'landing',
    public: true,
    unauthenticatedOnly: false,
  },
})

interface NewsItem {
  id: number
  title: string
  slug: string
  excerpt: string | null
  category?: 'union' | 'local' | 'international' | null
  image: string | null
  video_url: string | null
  gallery: string[] | null
  published_at: string
}

interface Pagination {
  data: NewsItem[]
  current_page: number
  last_page: number
  total: number
  per_page: number
}

const router = useRouter()
const route = useRoute()

// قسم التعاميم والإعلانات الرسمية: من يخاطب، وما الذي يصدر فيه
const circularAudience = [
  { icon: Users, label: 'أعضاء الهيئة العامة' },
  { icon: Building2, label: 'شركات المقاولات' },
  { icon: Landmark, label: 'الجهات المعنية والمهتمون بخدمات الاتحاد' },
]
const circularContents = [
  { icon: Info, label: 'المعلومات' },
  { icon: ListChecks, label: 'التعليمات' },
  { icon: Workflow, label: 'الإجراءات' },
  { icon: Sparkles, label: 'الفرص والأنشطة' },
]

const news = ref<NewsItem[]>([])
const pagination = ref<Pagination | null>(null)
const isLoading = ref(false)
const search = ref('')
const currentPage = ref(1)
const categoryTabs = [
  { value: '', label: 'الكل' },
  { value: 'union', label: 'أخبار الاتحاد' },
  { value: 'local', label: 'أخبار محلية' },
  { value: 'international', label: 'أخبار دولية' },
]
const category = ref('')
const CATEGORY_LABEL: Record<string, string> = { union: 'أخبار الاتحاد', local: 'أخبار محلية', international: 'أخبار دولية' }

// آخر الأخبار بالشريط الجانبي
const latest = ref<NewsItem[]>([])
async function fetchLatest() {
  try {
    const res = await api.get('/api/v1/news/latest')
    latest.value = (res.data.items ?? []).slice(0, 5)
  } catch { latest.value = [] }
}
function selectCategory(v: string) {
  category.value = v
  currentPage.value = 1
  fetchNews()
}

function formatDate(dateStr: string): string {
  return new Date(dateStr).toLocaleDateString('ar-PS', {
    year: 'numeric', month: 'long', day: 'numeric',
  })
}

async function fetchNews() {
  isLoading.value = true
  try {
    const params: Record<string, any> = { page: currentPage.value, per_page: 9 }
    if (search.value.trim()) params.search = search.value.trim()
    if (category.value) params.category = category.value

    const res = await api.get('/api/v1/news', { params })
    news.value = res.data.items ?? []
    pagination.value = {
      data: res.data.items ?? [],
      current_page: res.data.meta?.current_page ?? 1,
      last_page: res.data.meta?.last_page ?? 1,
      total: res.data.meta?.total ?? 0,
      per_page: res.data.meta?.per_page ?? 9,
    }
  } catch {
    news.value = []
  } finally {
    isLoading.value = false
  }
}

function goToPage(page: number) {
  currentPage.value = page
  fetchNews()
  window.scrollTo({ top: 0, behavior: 'smooth' })
}

let searchTimer: ReturnType<typeof setTimeout>
watch(search, () => {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => {
    currentPage.value = 1
    fetchNews()
  }, 400)
})

onMounted(() => {
  // روابط الشريط الجانبي بصفحة الخبر: ?category=local أو ?search=كلمة
  const qc = String(route.query.category ?? '')
  if (categoryTabs.some(t => t.value && t.value === qc)) category.value = qc
  if (route.query.search) search.value = String(route.query.search)
  fetchNews()
  fetchLatest()
})
</script>

<template>
  <div dir="rtl" class="news-page">

    <!-- Navbar -->
    
    <!-- Page Header -->
    <div class="page-header">
      <div class="container">
        <h1 class="page-title">الأخبار والإعلانات</h1>
        <p class="page-desc">تابع آخر أخبار الاتحاد والمستجدات في قطاع المقاولات الفلسطيني</p>
      </div>
      <div class="pub-follow-slot"><PublicSocialFollow /></div>
    </div>

    <!-- Official circulars & announcements -->
    <section class="circ-sec" aria-labelledby="circ-title">
      <div class="container">
        <div class="circ-card">
          <div class="circ-icon"><Megaphone :size="34" :stroke-width="1.8" /></div>
          <div class="circ-main">
            <span class="circ-label">الاتحاد يخاطبكم</span>
            <h2 id="circ-title" class="circ-title">التعاميم والإعلانات الرسمية</h2>
            <p class="circ-text">
              هذا القسم مخصص لإبلاغ أعضاء الهيئة العامة وشركات المقاولات والجهات المعنية بالمعلومات والتعليمات والإجراءات والفرص والأنشطة التي يصدرها الاتحاد.
            </p>
            <p class="circ-text circ-sub">
              يخاطب بالدرجة الأولى الأعضاء والشركات والمنتسبين والمهتمين بخدمات الاتحاد.
            </p>
          </div>
          <div class="circ-side">
            <div class="circ-group">
              <h3>يخاطب</h3>
              <ul>
                <li v-for="a in circularAudience" :key="a.label"><component :is="a.icon" :size="16" /> {{ a.label }}</li>
              </ul>
            </div>
            <div class="circ-group">
              <h3>يتضمن</h3>
              <div class="circ-chips">
                <span v-for="c in circularContents" :key="c.label" class="circ-chip"><component :is="c.icon" :size="14" /> {{ c.label }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Content -->
    <div class="container content-area">
      <div class="news-layout">
        <div class="news-main">

        <!-- Loading skeleton -->
        <div v-if="isLoading" class="news-grid">
          <div v-for="n in 6" :key="n" class="news-card skeleton">
            <div class="skeleton-img" />
            <div class="skeleton-body">
              <div class="skeleton-line short" />
              <div class="skeleton-line" />
              <div class="skeleton-line" />
              <div class="skeleton-line medium" />
            </div>
          </div>
        </div>

        <!-- News list -->
        <div v-else-if="news.length > 0" class="news-grid">
          <RouterLink
            v-for="item in news"
            :key="item.id"
            :to="`/landing/news/${item.slug}`"
            class="news-card"
          >
            <div class="news-img-wrap">
              <img v-if="item.image" :src="item.image" :alt="item.title" class="news-img" loading="lazy" />
              <div v-else class="news-img-placeholder">📰</div>
              <span v-if="item.video_url" class="news-video-badge"><Play :size="22" fill="white" /></span>
              <span v-if="item.gallery && item.gallery.length > 0" class="news-gallery-badge">
                <Images :size="13" /> {{ item.gallery.length + (item.image ? 1 : 0) }}
              </span>
            </div>
            <div class="news-body">
              <div class="news-meta">
                <span v-if="item.category && CATEGORY_LABEL[item.category]" class="news-cat">{{ CATEGORY_LABEL[item.category] }}</span>
                <span class="news-date"><CalendarDays :size="14" /> {{ formatDate(item.published_at) }}</span>
              </div>
              <h2 class="news-title">{{ item.title }}</h2>
              <p v-if="item.excerpt" class="news-excerpt">{{ item.excerpt }}</p>
              <div class="news-foot"><span class="news-read-more">اقرأ المزيد <span class="hc-arrow">←</span></span></div>
            </div>
          </RouterLink>
        </div>

        <!-- Empty state -->
        <div v-else class="empty-state">
          <div class="empty-icon"><Inbox :size="56" /></div>
          <h3>لا توجد نتائج</h3>
          <p>لم يتم العثور على أخبار تطابق بحثك. حاول تغيير الفلتر أو كلمة البحث.</p>
        </div>

        <!-- Pagination -->
        <div v-if="pagination && pagination.last_page > 1" class="pagination">
          <button
            class="page-btn"
            :disabled="currentPage === 1"
            @click="goToPage(currentPage - 1)"
          >
            ←
          </button>
          <button
            v-for="page in pagination.last_page"
            :key="page"
            class="page-btn"
            :class="{ active: page === currentPage }"
            @click="goToPage(page)"
          >
            {{ page }}
          </button>
          <button
            class="page-btn"
            :disabled="currentPage === pagination.last_page"
            @click="goToPage(currentPage + 1)"
          >
            →
          </button>
        </div>
    
        </div>

      <aside class="news-aside">
        <section class="aside-card">
          <h3 class="aside-h">ابحث في الأخبار</h3>
          <div class="search-wrap">
            <Search :size="15" class="search-icon" />
            <input v-model="search" type="text" placeholder="ابحث..." class="search-input" />
          </div>
        </section>

        <section class="aside-card">
          <h3 class="aside-h">التصنيفات</h3>
          <ul class="aside-cats">
            <li v-for="t in categoryTabs" :key="t.value">
              <button type="button" class="aside-cat" :class="{ active: category === t.value }" @click="selectCategory(t.value)">
                <FolderOpen :size="15" /> {{ t.label }}
              </button>
            </li>
          </ul>
        </section>

        <section v-if="latest.length" class="aside-card">
          <h3 class="aside-h">آخر الأخبار</h3>
          <RouterLink v-for="n in latest" :key="n.id" :to="`/landing/news/${n.slug}`" class="aside-post">
            <img v-if="n.image" :src="n.image" :alt="n.title" class="aside-thumb" loading="lazy" />
            <span v-else class="aside-thumb aside-thumb-ph"><Newspaper :size="20" /></span>
            <span class="aside-post-body">
              <span class="aside-post-date">{{ formatDate(n.published_at) }}</span>
              <span class="aside-post-title">{{ n.title }}</span>
            </span>
          </RouterLink>
        </section>
      </aside>
      </div>
    </div>

    <!-- Footer -->
    
  </div>
</template>

<style scoped>

* { box-sizing: border-box; margin: 0; padding: 0; }

.news-page {
  font-family: 'Tajawal', sans-serif;
  background: #f8fafc;
  min-height: 100vh;
  direction: rtl;
  color: #1a1a2e;
}

.container {
  max-width: 1200px;
  margin: 0 auto;
  padding: 0 1.5rem;
}

/* Navbar */
.navbar {
  position: sticky;
  top: 0;
  z-index: 100;
  background: rgba(255,255,255,0.97);
  backdrop-filter: blur(8px);
  border-bottom: 1px solid #e0e0e0;
  padding: 0.75rem 0;
}

.navbar-inner {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.navbar-brand {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  text-decoration: none;
}

.navbar-logo {
  height: 44px;
  width: auto;
  object-fit: contain;
}

.navbar-title { display: flex; flex-direction: column; line-height: 1.3; }
.title-ar { font-size: 0.9rem; font-weight: 700; color: #1a237e; }
.title-en { font-size: 0.7rem; color: #757575; }

.btn-back {
  background: white;
  color: #1a237e;
  border: 1.5px solid #1a237e;
  border-radius: 8px;
  padding: 0.4rem 1rem;
  font-size: 0.85rem;
  font-weight: 600;
  text-decoration: none;
  transition: background 0.2s, color 0.2s;
}

.btn-back:hover { background: #1a237e; color: white; }

/* Page header */
.page-header {
  background: linear-gradient(160deg, #e8eaf6 0%, #f5f7ff 100%);
  border-bottom: 2px solid #fdd835;
  /* slides up under the floating PublicNavbar, like the home hero */
  margin-top: calc(-1 * var(--pub-nav-h, 114px));
  padding: calc(var(--pub-nav-h, 114px) + 2.5rem) 0 3.5rem;
  text-align: center;
}

.page-title {
  color: #1a237e;
  font-size: clamp(1.75rem, 4vw, 2.5rem);
  font-weight: 800;
  margin-bottom: 0.75rem;
}

.page-desc {
  color: #424242;
  font-size: 1rem;
  max-width: 500px;
  margin: 0 auto;
  line-height: 1.7;
}

/* Filters */
.filters-bar {
  background: white;
  border-bottom: 1px solid #e5e7eb;
  padding: 1rem 0;
  position: sticky;
  top: 70px;
  z-index: 90;
}

.filters-inner {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  flex-wrap: wrap;
}

.search-wrap {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  border: 1.5px solid #e0e0e0;
  border-radius: 8px;
  padding: 0.4rem 0.875rem;
  background: white;
  transition: border-color 0.2s;
}

.search-wrap:focus-within { border-color: #1a237e; }

.search-icon { color: #757575; flex-shrink: 0; }

.search-input {
  border: none;
  outline: none;
  font-size: 0.875rem;
  font-family: inherit;
  color: #1a1a2e;
  min-width: 200px;
  background: transparent;
}

/* Content */
.content-area { padding: 2.5rem 1.5rem 4rem; }
.news-layout { display: grid; grid-template-columns: 1fr 300px; gap: 1.75rem; align-items: start; }
.news-main { min-width: 0; }

/* News grid */
.news-grid {
  display: flex;
  flex-direction: column;
  gap: 1.75rem;
}

.news-card {
  background: white;
  border: 1px solid #e5e7eb;
  border-radius: 16px;
  overflow: hidden;
  text-decoration: none;
  color: inherit;
  display: flex;
  flex-direction: column;
}

.news-img-wrap {
  position: relative;
  height: 210px;
  overflow: hidden;
  background: #f1f5f9;
  flex-shrink: 0;
}

.news-img { width: 100%; height: 100%; object-fit: cover; }

.news-img-placeholder {
  width: 100%;
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 3rem;
  background: linear-gradient(135deg, #eff6ff, #dbeafe);
}

.news-video-badge {
  position: absolute;
  top: 0; right: 0; bottom: 0; left: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  background: rgba(0,0,0,0.15);
}

.news-gallery-badge {
  position: absolute;
  bottom: 0.75rem;
  left: 0.75rem;
  display: flex;
  align-items: center;
  gap: 0.3rem;
  background: rgba(0,0,0,0.6);
  color: white;
  font-size: 0.75rem;
  font-weight: 700;
  padding: 0.2rem 0.6rem;
  border-radius: 50px;
}

.news-body {
  padding: 1.25rem;
  flex: 1;
  display: flex;
  flex-direction: column;
}

.news-date { font-size: 0.8rem; color: #f9a825; font-weight: 700; margin-bottom: 0.5rem; }

.news-title {
  font-size: 1rem;
  font-weight: 700;
  line-height: 1.5;
  margin-bottom: 0.5rem;
  color: #1a1a2e;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.news-excerpt {
  font-size: 0.85rem;
  color: #6b7280;
  line-height: 1.6;
  flex: 1;
  display: -webkit-box;
  -webkit-line-clamp: 3;
  -webkit-box-orient: vertical;
  overflow: hidden;
  margin-bottom: 1rem;
}

.news-read-more {
  font-size: 0.85rem;
  font-weight: 700;
  color: #1a237e;
  margin-top: auto;
}

/* Skeleton */
.news-card.skeleton { pointer-events: none; }
.skeleton-img { height: 210px; background: #e5e7eb; animation: pulse 1.5s infinite; }
.skeleton-body { padding: 1.25rem; display: flex; flex-direction: column; gap: 0.75rem; }
.skeleton-line { height: 12px; background: #e5e7eb; border-radius: 6px; animation: pulse 1.5s infinite; }
.skeleton-line.short { width: 40%; }
.skeleton-line.medium { width: 65%; }

@keyframes pulse {
  0%, 100% { opacity: 1; }
  50% { opacity: 0.5; }
}

/* Empty */
.empty-state {
  text-align: center;
  padding: 5rem 2rem;
  color: #9ca3af;
}

.empty-icon { color: #9ca3af; margin-bottom: 1rem; display: flex; justify-content: center; }
.empty-state h3 { font-size: 1.25rem; font-weight: 700; color: #4b5563; margin-bottom: 0.5rem; }
.empty-state p { font-size: 0.9rem; line-height: 1.7; }

/* Pagination */
.pagination {
  display: flex;
  justify-content: center;
  gap: 0.5rem;
  margin-top: 3rem;
  flex-wrap: wrap;
}

.page-btn {
  width: 40px;
  height: 40px;
  border: 1.5px solid #e5e7eb;
  border-radius: 8px;
  background: white;
  font-size: 0.9rem;
  font-weight: 600;
  color: #374151;
  cursor: pointer;
  font-family: inherit;
  transition: all 0.2s;
  display: flex;
  align-items: center;
  justify-content: center;
}

.page-btn:hover:not(:disabled):not(.active) {
  border-color: #1a237e;
  color: #1a237e;
}

.page-btn.active {
  background: #1a237e;
  border-color: #1a237e;
  color: white;
}

.page-btn:disabled { opacity: 0.4; cursor: not-allowed; }

/* Footer */
.footer { background: #f5f7ff; border-top: 2px solid #fdd835; padding: 1.5rem 0; text-align: center; }
.footer-copy { font-size: 0.8rem; color: #757575; }

/* Responsive */
@media (max-width: 900px) {
  .news-layout { grid-template-columns: 1fr; }
  .news-aside { order: -1; position: static; }
  .filters-inner { flex-direction: column; align-items: stretch; }
  .search-input { min-width: auto; width: 100%; }
}



/* Official circulars & announcements */
.circ-sec { padding: 2.25rem 0 0; }
.circ-card {
  position: relative; overflow: hidden; display: grid; grid-template-columns: auto 1.5fr 1fr; gap: 2rem; align-items: center;
  background: linear-gradient(135deg, #f5f7ff 0%, #fff 60%); border: 1.5px solid #c5cae9; border-radius: 20px; padding: 2rem 2.25rem;
  box-shadow: 0 10px 30px rgba(13, 27, 75, .07);
}
.circ-card::before { content: ''; position: absolute; top: 0; bottom: 0; right: 0; width: 6px; background: linear-gradient(180deg, #f9a825, #e65100); }
.circ-icon { width: 76px; height: 76px; border-radius: 20px; display: grid; place-items: center; color: #fff; background: linear-gradient(145deg, #1a237e, #283593); box-shadow: 0 10px 22px rgba(26, 35, 126, .3); }
.circ-label { display: inline-block; background: #fff4dc; color: #b26a00; border: 1px solid #ffe0a3; border-radius: 50px; padding: .2rem .9rem; font-size: .75rem; font-weight: 700; margin-bottom: .55rem; }
.circ-title { font-family: 'Tajawal', 'Cairo', sans-serif; font-size: clamp(1.4rem, 2.6vw, 1.9rem); font-weight: 900; color: #0d1b3e; margin: 0 0 .6rem; }
.circ-text { color: #4b5563; font-size: .98rem; line-height: 1.95; margin: 0; }
.circ-sub { color: #6b7280; font-size: .9rem; margin-top: .35rem; }
.circ-side { display: flex; flex-direction: column; gap: 1.1rem; border-inline-start: 1.5px dashed #c5cae9; padding-inline-start: 1.75rem; }
.circ-group h3 { font-size: .8rem; font-weight: 800; color: #1a237e; margin: 0 0 .5rem; }
.circ-group ul { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: .4rem; }
.circ-group li { display: flex; align-items: center; gap: .5rem; font-size: .88rem; color: #374151; font-weight: 600; }
.circ-group li svg { color: #e65100; flex-shrink: 0; }
.circ-chips { display: flex; flex-wrap: wrap; gap: .45rem; }
.circ-chip { display: inline-flex; align-items: center; gap: .35rem; background: #e8eaf6; color: #1a237e; border-radius: 50px; padding: .3rem .8rem; font-size: .8rem; font-weight: 700; }
@media (max-width: 960px) {
  .circ-card { grid-template-columns: 1fr; gap: 1.25rem; padding: 1.5rem 1.4rem; }
  .circ-side { border-inline-start: 0; padding-inline-start: 0; border-top: 1.5px dashed #c5cae9; padding-top: 1.1rem; }
}

/* category tabs */
.cat-tabs { display: flex; gap: .5rem; flex-wrap: wrap; }
.cat-tab { border: 1.5px solid #c5cae9; background: #fff; color: #1a237e; border-radius: 50px; padding: .4rem 1.1rem; font-size: .88rem; font-weight: 700; font-family: inherit; cursor: pointer; transition: all .2s; }
.cat-tab:hover { background: #e8eaf6; }
.cat-tab.active { background: #1a237e; border-color: #1a237e; color: #fff; }
/* post card (blog style): wide image, meta row, title, excerpt, read more */
.news-card { padding: 1rem; border-radius: 20px; box-shadow: 0 6px 22px rgba(13, 27, 75, .06); transition: box-shadow .25s, transform .25s; }
.news-card:hover { box-shadow: 0 14px 36px rgba(13, 27, 75, .13); transform: translateY(-3px); }
.news-card .news-img-wrap { aspect-ratio: 16 / 7; height: auto; border-radius: 14px; overflow: hidden; }
.news-card .news-body { padding: 1.1rem .5rem .4rem; }
.news-meta { display: flex; align-items: center; flex-wrap: wrap; gap: .75rem; margin-bottom: .6rem; }
.news-cat { background: #1a237e; color: #fff; border-radius: 999px; padding: .2rem .9rem; font-size: .78rem; font-weight: 800; }
.news-card .news-date { display: inline-flex; align-items: center; gap: .35rem; margin: 0; color: #6b7280; font-size: .85rem; }
.news-card .news-date svg { color: #e65100; }
.news-card .news-title { font-size: 1.4rem; line-height: 1.6; margin-bottom: .5rem; }
.news-foot { margin-top: 1rem; padding-top: 1rem; border-top: 1px solid #eceef3; }

/* sidebar */
.news-aside { display: flex; flex-direction: column; gap: 1.25rem; position: sticky; top: 130px; }
.aside-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 18px; padding: 1.1rem 1.1rem 1.2rem; box-shadow: 0 6px 22px rgba(13, 27, 75, .05); }
.aside-h { font-size: 1rem; font-weight: 800; color: #0d1b3e; padding-bottom: .7rem; margin: 0 0 .9rem; border-bottom: 1px solid #eceef3; position: relative; }
.aside-h::after { content: ''; position: absolute; bottom: -1px; right: 0; width: 38px; height: 3px; background: linear-gradient(90deg, #f9a825, #e65100); border-radius: 3px; }
.aside-card .search-wrap { width: 100%; background: #f8f9fc; }
.aside-card .search-input { width: 100%; min-width: 0; }
.aside-cats { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: .35rem; }
.aside-cat { width: 100%; display: flex; align-items: center; gap: .55rem; border: 0; background: transparent; padding: .55rem .75rem; border-radius: 10px; font-family: inherit; font-size: .92rem; font-weight: 700; color: #374151; cursor: pointer; text-align: start; transition: background .2s, color .2s; }
.aside-cat svg { color: #e65100; }
.aside-cat:hover { background: #f1f3fb; }
.aside-cat.active { background: #1a237e; color: #fff; }
.aside-cat.active svg { color: #f9a825; }
.aside-post { display: flex; gap: .75rem; align-items: center; text-decoration: none; color: inherit; padding: .6rem 0; border-bottom: 1px solid #f0f1f6; }
.aside-post:last-child { border-bottom: 0; padding-bottom: 0; }
.aside-thumb { width: 64px; height: 64px; border-radius: 12px; object-fit: cover; flex-shrink: 0; }
.aside-thumb-ph { display: grid; place-items: center; background: #e8eaf6; color: #1a237e; }
.aside-post-body { display: flex; flex-direction: column; gap: .2rem; min-width: 0; }
.aside-post-date { font-size: .74rem; color: #9aa3b5; font-weight: 700; }
.aside-post-title { font-size: .88rem; font-weight: 700; line-height: 1.55; color: #0d1b3e; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.aside-post:hover .aside-post-title { color: #1a237e; }
</style>

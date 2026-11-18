<script setup lang="ts">
import PublicSocialFollow from '@/components/PublicSocialFollow.vue'
import { ref, watch, onMounted } from 'vue'
import api from '@/plugins/axios'
import { Search, Inbox, Play, Images } from 'lucide-vue-next'

definePage({
  meta: {
    layout: 'landing',
    public: true,
    unauthenticatedOnly: false,
  },
})

interface EventItem {
  id: number
  title: string
  slug: string
  excerpt: string | null
  image: string | null
  video_url: string | null
  gallery: string[] | null
  published_at: string
  event_date: string | null
  event_location: string | null
}

interface Pagination {
  data: EventItem[]
  current_page: number
  last_page: number
  total: number
  per_page: number
}

const events = ref<EventItem[]>([])
const pagination = ref<Pagination | null>(null)
const isLoading = ref(false)
const search = ref('')
const currentPage = ref(1)
// فعاليات الاتحاد = event_type local ("محلية للاتحاد")، ومحلية = institutional ("مؤسسات")، ودولية = international
const categoryTabs = [
  { value: '', label: 'الكل' },
  { value: 'local', label: 'فعاليات الاتحاد' },
  { value: 'institutional', label: 'فعاليات محلية' },
  { value: 'international', label: 'فعاليات دولية' },
]
const eventType = ref('')
function selectCategory(v: string) {
  eventType.value = v
  currentPage.value = 1
  fetchEvents()
}

function formatDate(dateStr: string): string {
  return new Date(dateStr).toLocaleDateString('ar-PS', {
    year: 'numeric', month: 'long', day: 'numeric',
  })
}

async function fetchEvents() {
  isLoading.value = true
  try {
    const params: Record<string, any> = { page: currentPage.value, per_page: 9 }
    if (search.value.trim()) params.search = search.value.trim()
    if (eventType.value) params.event_type = eventType.value

    const res = await api.get('/api/v1/events', { params })
    events.value = res.data.items ?? []
    pagination.value = {
      data: res.data.items ?? [],
      current_page: res.data.meta?.current_page ?? 1,
      last_page: res.data.meta?.last_page ?? 1,
      total: res.data.meta?.total ?? 0,
      per_page: res.data.meta?.per_page ?? 9,
    }
  } catch {
    events.value = []
  } finally {
    isLoading.value = false
  }
}

function goToPage(page: number) {
  currentPage.value = page
  fetchEvents()
  window.scrollTo({ top: 0, behavior: 'smooth' })
}

let searchTimer: ReturnType<typeof setTimeout>
watch(search, () => {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => {
    currentPage.value = 1
    fetchEvents()
  }, 400)
})

onMounted(fetchEvents)
</script>

<template>
  <div dir="rtl" class="news-page">

    <!-- Page Header -->
    <div class="page-header">
      <div class="container">
        <h1 class="page-title">الفعاليات</h1>
        <p class="page-desc">تابع فعاليات ومناسبات الاتحاد القادمة والسابقة</p>
      </div>
      <div class="pub-follow-slot"><PublicSocialFollow /></div>
    </div>

    <!-- Filters -->
    <div class="filters-bar">
      <div class="container filters-inner">
        <div class="cat-tabs" role="tablist">
          <button v-for="t in categoryTabs" :key="t.value" type="button" role="tab" class="cat-tab" :class="{ active: eventType === t.value }" :aria-selected="eventType === t.value" @click="selectCategory(t.value)">{{ t.label }}</button>
        </div>
        <!-- Search -->
        <div class="search-wrap">
          <Search :size="15" class="search-icon" />
          <input
            v-model="search"
            type="text"
            placeholder="ابحث في الفعاليات..."
            class="search-input"
          />
        </div>
      </div>
    </div>

    <!-- Content -->
    <div class="container content-area">

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

      <!-- Events grid -->
      <div v-else-if="events.length > 0" class="news-grid">
        <RouterLink
          v-for="item in events"
          :key="item.id"
          :to="`/landing/events/${item.slug}`"
          class="news-card"
        >
          <div class="news-img-wrap">
            <img v-if="item.image" :src="item.image" :alt="item.title" class="news-img" />
            <div v-else class="news-img-placeholder">📅</div>
            <span class="news-cat" style="background: #15803d">فعالية</span>
            <span v-if="item.video_url" class="news-video-badge"><Play :size="22" fill="white" /></span>
            <span v-if="item.gallery && item.gallery.length > 0" class="news-gallery-badge">
              <Images :size="13" /> {{ item.gallery.length + (item.image ? 1 : 0) }}
            </span>
          </div>
          <div class="news-body">
            <p class="news-date">{{ formatDate(item.published_at) }}</p>
            <h2 class="news-title">{{ item.title }}</h2>
            <p v-if="item.event_date" class="news-event-info">
              📅 {{ formatDate(item.event_date) }}
              <span v-if="item.event_location"> — 📍 {{ item.event_location }}</span>
            </p>
            <p v-if="item.excerpt" class="news-excerpt">{{ item.excerpt }}</p>
            <span class="news-read-more">التفاصيل ←</span>
          </div>
        </RouterLink>
      </div>

      <!-- Empty state -->
      <div v-else class="empty-state">
        <div class="empty-icon"><Inbox :size="56" /></div>
        <h3>لا توجد نتائج</h3>
        <p>لم يتم العثور على فعاليات تطابق بحثك. حاول تغيير كلمة البحث.</p>
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
  color: var(--pcu-brand-ink);
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

.search-wrap:focus-within { border-color: var(--pcu-brand); }

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

/* News grid */
.news-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 1.5rem;
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
  transition: box-shadow 0.2s, transform 0.2s;
}

.news-card:hover {
  box-shadow: 0 8px 30px rgba(var(--pcu-brand-rgb), 0.1);
  transform: translateY(-4px);
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

.news-cat {
  position: absolute;
  top: 0.75rem;
  right: 0.75rem;
  color: white;
  font-size: 0.75rem;
  font-weight: 700;
  padding: 0.25rem 0.75rem;
  border-radius: 50px;
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

.news-event-info {
  font-size: 0.8rem;
  color: #15803d;
  font-weight: 600;
  margin-bottom: 0.4rem;
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
  color: var(--pcu-brand-ink);
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
  border-color: var(--pcu-brand);
  color: var(--pcu-brand-ink);
}

.page-btn.active {
  background: var(--pcu-brand);
  border-color: var(--pcu-brand);
  color: white;
}

.page-btn:disabled { opacity: 0.4; cursor: not-allowed; }

/* Responsive */
@media (max-width: 900px) {
  .news-grid { grid-template-columns: repeat(2, 1fr); }
  .filters-inner { flex-direction: column; align-items: stretch; }
  .search-input { min-width: auto; width: 100%; }
}

@media (max-width: 600px) {
  .news-grid { grid-template-columns: 1fr; }
}

/* category tabs */
.cat-tabs { display: flex; gap: .5rem; flex-wrap: wrap; }
.cat-tab { border: 1.5px solid #c5cae9; background: #fff; color: var(--pcu-brand-ink); border-radius: 50px; padding: .4rem 1.1rem; font-size: .88rem; font-weight: 700; font-family: inherit; cursor: pointer; transition: all .2s; }
.cat-tab:hover { background: #e8eaf6; }
.cat-tab.active { background: var(--pcu-brand); border-color: var(--pcu-brand); color: #fff; }
</style>

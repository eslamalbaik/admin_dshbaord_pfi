<script setup lang="ts">
import { ref, watch, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import api from '@/plugins/axios'
import { Search, Inbox } from 'lucide-vue-next'

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
  image: string | null
  category: string
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

const news = ref<NewsItem[]>([])
const pagination = ref<Pagination | null>(null)
const isLoading = ref(false)
const search = ref('')
const activeCategory = ref('')
const currentPage = ref(1)

const categories = [
  { value: '', label: 'الكل' },
  { value: 'news', label: 'أخبار' },
  { value: 'announcement', label: 'إعلانات' },
  { value: 'event', label: 'فعاليات' },
  { value: 'tender', label: 'مناقصات' },
]

const categoryColors: Record<string, string> = {
  news: '#000269',
  announcement: '#0369a1',
  event: '#15803d',
  tender: '#b45309',
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
    if (activeCategory.value) params.category = activeCategory.value
    if (search.value.trim()) params.search = search.value.trim()

    const res = await api.get('/api/v1/news', { params })
    news.value = res.data.data ?? []
    pagination.value = res.data
  } catch {
    news.value = []
  } finally {
    isLoading.value = false
  }
}

function setCategory(cat: string) {
  activeCategory.value = cat
  currentPage.value = 1
  fetchNews()
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

onMounted(fetchNews)
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
    </div>

    <!-- Filters -->
    <div class="filters-bar">
      <div class="container filters-inner">
        <!-- Category tabs -->
        <div class="category-tabs">
          <button
            v-for="cat in categories"
            :key="cat.value"
            class="cat-tab"
            :class="{ active: activeCategory === cat.value }"
            @click="setCategory(cat.value)"
          >
            {{ cat.label }}
          </button>
        </div>
        <!-- Search -->
        <div class="search-wrap">
          <Search :size="15" class="search-icon" />
          <input
            v-model="search"
            type="text"
            placeholder="ابحث في الأخبار..."
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

      <!-- News grid -->
      <div v-else-if="news.length > 0" class="news-grid">
        <RouterLink
          v-for="item in news"
          :key="item.id"
          :to="`/landing/news/${item.slug}`"
          class="news-card"
        >
          <div class="news-img-wrap">
            <img v-if="item.image" :src="item.image" :alt="item.title" class="news-img" />
            <div v-else class="news-img-placeholder">📰</div>
            <span
              class="news-cat"
              :style="`background: ${categoryColors[item.category] ?? '#000269'}`"
            >
              {{ categories.find(c => c.value === item.category)?.label ?? item.category }}
            </span>
          </div>
          <div class="news-body">
            <p class="news-date">{{ formatDate(item.published_at) }}</p>
            <h2 class="news-title">{{ item.title }}</h2>
            <p v-if="item.excerpt" class="news-excerpt">{{ item.excerpt }}</p>
            <span class="news-read-more">اقرأ المزيد ←</span>
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

    <!-- Footer -->
    
  </div>
</template>

<style scoped>
@import url('https://fonts.cdnfonts.com/css/neo-sans-arabic');
@import url('https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800;900&display=swap');

* { box-sizing: border-box; margin: 0; padding: 0; }

.news-page {
  font-family: 'Neo Sans Arabic', 'Tajawal', sans-serif;
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
  padding: 3.5rem 0;
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

.category-tabs {
  display: flex;
  gap: 0.5rem;
  flex-wrap: wrap;
}

.cat-tab {
  background: white;
  border: 1.5px solid #e5e7eb;
  border-radius: 50px;
  padding: 0.375rem 1rem;
  font-size: 0.85rem;
  font-weight: 600;
  color: #6b7280;
  cursor: pointer;
  font-family: inherit;
  transition: all 0.2s;
}

.cat-tab.active {
  background: #1a237e;
  border-color: #1a237e;
  color: white;
}

.cat-tab:hover:not(.active) { border-color: #1a237e; color: #1a237e; }

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
  box-shadow: 0 8px 30px rgba(26,35,126,0.1);
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
  .news-grid { grid-template-columns: repeat(2, 1fr); }
  .filters-inner { flex-direction: column; align-items: stretch; }
  .search-input { min-width: auto; width: 100%; }
}

@media (max-width: 600px) {
  .news-grid { grid-template-columns: 1fr; }
}
</style>

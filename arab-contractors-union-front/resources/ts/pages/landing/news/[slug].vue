<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import api from '@/plugins/axios'
import { Search } from 'lucide-vue-next'

definePage({
  meta: {
    layout: 'landing',
    public: true,
    unauthenticatedOnly: false,
  },
})

const route = useRoute()

interface NewsDetail {
  id: number
  title: string
  slug: string
  excerpt: string | null
  body: string
  image: string | null
  category: string
  published_at: string
  author?: { id: number; name: string }
}

const news = ref<NewsDetail | null>(null)
const isLoading = ref(true)
const notFound = ref(false)

const categoryLabels: Record<string, string> = {
  news: 'خبر',
  announcement: 'إعلان',
  event: 'فعالية',
  tender: 'مناقصة',
}

const categoryColors: Record<string, string> = {
  news: '#1a237e',
  announcement: '#0277bd',
  event: '#2e7d32',
  tender: '#e65100',
}

function formatDate(dateStr: string): string {
  return new Date(dateStr).toLocaleDateString('ar-PS', {
    year: 'numeric', month: 'long', day: 'numeric',
    weekday: 'long',
  })
}

onMounted(async () => {
  try {
    const slug = route.params.slug as string
    const res = await api.get(`/api/v1/news/${slug}`)
    news.value = res.data.data
  } catch (err: any) {
    if (err?.response?.status === 404) notFound.value = true
  } finally {
    isLoading.value = false
  }
})
</script>

<template>
  <div dir="rtl" class="news-detail-page">

    <!-- Loading -->
    <div v-if="isLoading" class="container loading-wrap">
      <div class="skeleton-hero" />
      <div class="skeleton-content">
        <div class="skeleton-line title" />
        <div class="skeleton-line medium" />
        <div class="skeleton-line" />
        <div class="skeleton-line" />
        <div class="skeleton-line short" />
      </div>
    </div>

    <!-- Not Found -->
    <div v-else-if="notFound" class="container not-found">
      <div class="nf-icon"><Search :size="56" /></div>
      <h2>الخبر غير موجود</h2>
      <p>لم يتم العثور على هذا الخبر أو ربما تم حذفه.</p>
      <RouterLink to="/landing/news" class="btn-primary">العودة للأخبار</RouterLink>
    </div>

    <!-- Article -->
    <template v-else-if="news">

      <!-- Hero image -->
      <div v-if="news.image" class="article-hero">
        <img :src="news.image" :alt="news.title" class="hero-img" />
        <div class="hero-overlay" />
      </div>

      <div class="container article-wrap">
        <article class="article">

          <!-- Meta -->
          <div class="article-meta">
            <span
              class="article-cat"
              :style="`background: ${categoryColors[news.category] ?? '#000269'}`"
            >
              {{ categoryLabels[news.category] ?? news.category }}
            </span>
            <span class="article-date">{{ formatDate(news.published_at) }}</span>
            <span v-if="news.author" class="article-author">بقلم: {{ news.author.name }}</span>
          </div>

          <!-- Title -->
          <h1 class="article-title">{{ news.title }}</h1>

          <!-- Excerpt -->
          <p v-if="news.excerpt" class="article-excerpt">{{ news.excerpt }}</p>

          <!-- Divider -->
          <hr class="article-divider" />

          <!-- Body -->
          <div class="article-body" v-html="news.body" />

          <!-- Share + Back -->
          <div class="article-footer">
            <RouterLink to="/landing/news" class="btn-back-article">← العودة لقائمة الأخبار</RouterLink>
          </div>
        </article>
      </div>
    </template>

  </div>
</template>

<style scoped>
@import url('https://fonts.cdnfonts.com/css/neo-sans-arabic');
@import url('https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800;900&display=swap');

* { box-sizing: border-box; margin: 0; padding: 0; }

.news-detail-page {
  font-family: 'Neo Sans Arabic', 'Tajawal', sans-serif;
  background: #f8fafc;
  min-height: 100vh;
  direction: rtl;
  color: #1a1a2e;
}

.container { max-width: 860px; margin: 0 auto; padding: 0 1.5rem; }

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
  max-width: 1200px;
  margin: 0 auto;
  padding: 0 1.5rem;
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.navbar-brand { display: flex; align-items: center; gap: 0.75rem; text-decoration: none; }
.navbar-logo { height: 44px; width: auto; object-fit: contain; }
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

/* Hero image */
.article-hero {
  width: 100%;
  height: 420px;
  position: relative;
  overflow: hidden;
}

.hero-img { width: 100%; height: 100%; object-fit: cover; }

.hero-overlay {
  position: absolute;
  inset: 0;
  background: linear-gradient(to bottom, transparent 50%, rgba(0,0,0,0.3) 100%);
}

/* Article */
.article-wrap { padding: 2.5rem 1.5rem 4rem; }

.article {
  background: white;
  border-radius: 20px;
  padding: 2.5rem;
  border: 1px solid #e5e7eb;
}

.article-meta {
  display: flex;
  align-items: center;
  gap: 1rem;
  margin-bottom: 1.25rem;
  flex-wrap: wrap;
}

.article-cat {
  color: white;
  font-size: 0.8rem;
  font-weight: 700;
  padding: 0.25rem 0.875rem;
  border-radius: 50px;
}

.article-date { font-size: 0.85rem; color: #6b7280; }
.article-author { font-size: 0.85rem; color: #6b7280; }

.article-title {
  font-size: clamp(1.5rem, 3vw, 2rem);
  font-weight: 800;
  line-height: 1.4;
  color: #1a1a2e;
  margin-bottom: 1rem;
}

.article-excerpt {
  font-size: 1.05rem;
  color: #4b5563;
  line-height: 1.8;
  margin-bottom: 1.5rem;
  font-weight: 500;
}

.article-divider {
  border: none;
  border-top: 2px solid #e5e7eb;
  margin: 1.5rem 0;
}

.article-body {
  font-size: 1rem;
  line-height: 1.9;
  color: #374151;
}

.article-body :deep(p) { margin-bottom: 1.25rem; }
.article-body :deep(h2) { font-size: 1.3rem; font-weight: 700; margin: 1.75rem 0 0.75rem; }
.article-body :deep(h3) { font-size: 1.1rem; font-weight: 700; margin: 1.5rem 0 0.5rem; }
.article-body :deep(ul), .article-body :deep(ol) { padding-inline-start: 1.5rem; margin-bottom: 1rem; }
.article-body :deep(li) { margin-bottom: 0.4rem; }
.article-body :deep(img) { max-width: 100%; border-radius: 12px; margin: 1rem 0; }
.article-body :deep(blockquote) {
  border-inline-start: 4px solid #1a237e;
  padding-inline-start: 1rem;
  color: #424242;
  font-style: italic;
  margin: 1.5rem 0;
  background: #e8eaf6;
  border-radius: 0 8px 8px 0;
  padding: .75rem 1rem;
}

.article-footer {
  margin-top: 2.5rem;
  padding-top: 1.5rem;
  border-top: 1px solid #e0e0e0;
}

.btn-back-article {
  display: inline-block;
  background: white;
  color: #1a237e;
  border: 2px solid #1a237e;
  border-radius: 10px;
  padding: 0.6rem 1.5rem;
  font-size: 0.9rem;
  font-weight: 700;
  text-decoration: none;
  transition: background 0.2s, color 0.2s;
}

.btn-back-article:hover { background: #1a237e; color: white; }

/* Skeleton */
.loading-wrap { padding: 2rem 1.5rem; }
.skeleton-hero { height: 300px; background: #e5e7eb; border-radius: 12px; margin-bottom: 2rem; animation: pulse 1.5s infinite; }
.skeleton-content { background: white; border-radius: 20px; padding: 2.5rem; border: 1px solid #e5e7eb; display: flex; flex-direction: column; gap: 1rem; }
.skeleton-line { height: 14px; background: #e5e7eb; border-radius: 6px; animation: pulse 1.5s infinite; }
.skeleton-line.title { height: 28px; width: 80%; }
.skeleton-line.medium { width: 60%; }
.skeleton-line.short { width: 35%; }

@keyframes pulse {
  0%, 100% { opacity: 1; }
  50% { opacity: 0.5; }
}

/* Not found */
.not-found {
  text-align: center;
  padding: 5rem 2rem;
}

.nf-icon { color: #9ca3af; margin-bottom: 1rem; display: flex; justify-content: center; }
.not-found h2 { font-size: 1.5rem; font-weight: 700; margin-bottom: 0.75rem; }
.not-found p { color: #6b7280; margin-bottom: 1.5rem; }

.btn-primary {
  display: inline-block;
  background: #1a237e;
  color: white;
  border-radius: 10px;
  padding: 0.75rem 2rem;
  font-size: 0.95rem;
  font-weight: 700;
  text-decoration: none;
}

/* Footer */
.footer { background: #f5f7ff; border-top: 2px solid #fdd835; padding: 1.5rem 0; text-align: center; }
.footer-copy { font-size: 0.8rem; color: #757575; }

@media (max-width: 600px) {
  .article { padding: 1.5rem; }
  .article-hero { height: 250px; }
}
</style>

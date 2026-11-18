<script setup lang="ts">
import { computed, ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '@/plugins/axios'
import { CalendarDays, ChevronLeft, ChevronRight, FolderOpen, Newspaper, Search, UserRound } from 'lucide-vue-next'

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
  video_url: string | null
  external_url: string | null
  gallery: string[] | null
  category?: 'union' | 'local' | 'international' | null
  published_at: string
  author?: { id: number; name: string }
  previous?: { id: number; title: string; slug: string } | null
  next?: { id: number; title: string; slug: string } | null
}

interface LatestItem { id: number; title: string; slug: string; image: string | null; published_at: string }
const CATEGORY_LABEL: Record<string, string> = { union: 'أخبار الاتحاد', local: 'أخبار محلية', international: 'أخبار دولية' }
const latest = ref<LatestItem[]>([])
const searchText = ref('')
const router = useRouter()
function goSearch() { router.push({ path: '/landing/news', query: searchText.value.trim() ? { search: searchText.value.trim() } : {} }) }
function shortDate(d: string) { return new Date(d).toLocaleDateString('ar-PS', { year: 'numeric', month: 'long', day: 'numeric' }) }

const news = ref<NewsDetail | null>(null)
const isLoading = ref(true)
const notFound = ref(false)

function formatDate(dateStr: string): string {
  return new Date(dateStr).toLocaleDateString('ar-PS', {
    year: 'numeric', month: 'long', day: 'numeric',
    weekday: 'long',
  })
}

function youtubeEmbedUrl(url: string): string | null {
  const m = url.match(/(?:youtu\.be\/|youtube\.com\/(?:watch\?v=|embed\/))([\w-]{11})/)
  return m ? `https://www.youtube.com/embed/${m[1]}` : null
}

// الصورة الرئيسية تظهر أيضاً كأول صورة بشريط الصور القابل للتنقل بالسحب (بدل ما تكون
// منفصلة تماماً عن المعرض) — overflow-x + scroll-snap يعطي سحب أصلي على اللمس بدون مكتبة جديدة
const allImages = computed(() => {
  if (!news.value) return []
  return [news.value.image, ...(news.value.gallery ?? [])].filter((u): u is string => !!u)
})

onMounted(async () => {
  try {
    const slug = route.params.slug as string
    const res = await api.get(`/api/v1/news/${slug}`)
    news.value = res.data.items
  } catch (err: any) {
    if (err?.response?.status === 404) notFound.value = true
  } finally {
    isLoading.value = false
  }
  try {
    const r = await api.get('/api/v1/news/latest')
    latest.value = (r.data.items ?? []).slice(0, 5)
  } catch { latest.value = [] }
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
      <div class="container article-wrap">
        <div class="detail-layout">
          <article class="article-col">
            <!-- Meta -->
            <div class="article-meta">
              <span v-if="news.category && CATEGORY_LABEL[news.category]" class="article-cat">{{ CATEGORY_LABEL[news.category] }}</span>
              <span class="article-date"><CalendarDays :size="14" /> {{ formatDate(news.published_at) }}</span>
              <span v-if="news.author" class="article-author"><UserRound :size="14" /> بواسطة {{ news.author.name }}</span>
            </div>

            <h1 class="article-title">{{ news.title }}</h1>

            <!-- Main image -->
            <div v-if="news.image" class="article-cover">
              <img :src="news.image" :alt="news.title" />
            </div>

            <!-- Content card -->
            <div class="article-card">
              <p v-if="news.excerpt" class="article-excerpt">{{ news.excerpt }}</p>

              <div v-if="news.video_url" class="article-video">
                <iframe
                  v-if="youtubeEmbedUrl(news.video_url)"
                  :src="youtubeEmbedUrl(news.video_url)!"
                  frameborder="0"
                  allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                  allowfullscreen
                />
                <a v-else :href="news.video_url" target="_blank" rel="noopener" class="video-fallback-link">مشاهدة الفيديو ↗</a>
              </div>

              <a v-if="news.external_url" :href="news.external_url" target="_blank" rel="noopener" class="article-external-link">
                رابط ذو صلة ↗
              </a>

              <div v-if="allImages.length > 1" class="article-gallery">
                <img v-for="(img, i) in allImages" :key="i" :src="img" :alt="`${news.title} - ${i + 1}`" />
              </div>

              <div class="article-body" v-html="news.body" />
            </div>

            <!-- Previous / next -->
            <nav v-if="news.previous || news.next" class="article-nav" aria-label="التنقل بين الأخبار">
              <RouterLink v-if="news.previous" :to="`/landing/news/${news.previous.slug}`" class="nav-card">
                <span class="nav-label"><ChevronRight :size="14" /> الخبر السابق</span>
                <span class="nav-title">{{ news.previous.title }}</span>
              </RouterLink>
              <span v-else />
              <RouterLink v-if="news.next" :to="`/landing/news/${news.next.slug}`" class="nav-card nav-next">
                <span class="nav-label">الخبر التالي <ChevronLeft :size="14" /></span>
                <span class="nav-title">{{ news.next.title }}</span>
              </RouterLink>
            </nav>

            <div class="article-footer">
              <RouterLink to="/landing/news" class="btn-back-article">← العودة لقائمة الأخبار</RouterLink>
            </div>
          </article>

          <aside class="detail-aside">
            <section class="aside-card">
              <h3 class="aside-h">ابحث في الأخبار</h3>
              <form class="aside-search" @submit.prevent="goSearch">
                <Search :size="15" />
                <input v-model="searchText" type="text" placeholder="ابحث..." />
              </form>
            </section>

            <section class="aside-card">
              <h3 class="aside-h">التصنيفات</h3>
              <ul class="aside-cats">
                <li><RouterLink to="/landing/news" class="aside-cat"><FolderOpen :size="15" /> كل الأخبار</RouterLink></li>
                <li v-for="(label, key) in CATEGORY_LABEL" :key="key">
                  <RouterLink :to="{ path: '/landing/news', query: { category: key } }" class="aside-cat" :class="{ active: news.category === key }"><FolderOpen :size="15" /> {{ label }}</RouterLink>
                </li>
              </ul>
            </section>

            <section v-if="latest.length" class="aside-card">
              <h3 class="aside-h">آخر الأخبار</h3>
              <RouterLink v-for="n in latest" :key="n.id" :to="`/landing/news/${n.slug}`" class="aside-post">
                <img v-if="n.image" :src="n.image" :alt="n.title" class="aside-thumb" loading="lazy" />
                <span v-else class="aside-thumb aside-thumb-ph"><Newspaper :size="20" /></span>
                <span class="aside-post-body">
                  <span class="aside-post-date">{{ shortDate(n.published_at) }}</span>
                  <span class="aside-post-title">{{ n.title }}</span>
                </span>
              </RouterLink>
            </section>
          </aside>
        </div>
      </div>
    </template>

  </div>
</template>

<style scoped>

* { box-sizing: border-box; margin: 0; padding: 0; }

.news-detail-page {
  font-family: 'Tajawal', sans-serif;
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
  top: 0; right: 0; bottom: 0; left: 0;
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

/* Video embed */
.article-video {
  margin-bottom: 1.5rem;
  border-radius: 14px;
  overflow: hidden;
  background: #000;
}

.article-video iframe {
  width: 100%;
  aspect-ratio: 16 / 9;
  border: none;
  display: block;
}

.video-fallback-link {
  display: block;
  padding: 1rem;
  color: #fff;
  text-align: center;
  font-weight: 700;
}

.article-external-link {
  display: inline-block;
  margin-bottom: 1.5rem;
  padding: 0.6rem 1.25rem;
  border: 1.5px solid #1a237e;
  border-radius: 10px;
  color: #1a237e;
  font-weight: 700;
  text-decoration: none;
}

/* Gallery — سحب أفقي أصلي على اللمس عبر scroll-snap، الصورة الرئيسية أول عنصر بالشريط */
.article-gallery {
  display: flex;
  overflow-x: auto;
  scroll-snap-type: x mandatory;
  gap: 0.75rem;
  margin-bottom: 1.5rem;
  padding-bottom: 0.25rem;
}

.article-gallery img {
  flex: 0 0 auto;
  scroll-snap-align: start;
  width: 220px;
  height: 150px;
  object-fit: cover;
  border-radius: 10px;
  border: 1px solid #e5e7eb;
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
/* ── blog-style detail layout ── */
.news-detail-page .container.article-wrap { max-width: 1200px; padding-top: 2.5rem; }
.detail-layout { display: grid; grid-template-columns: 1fr 300px; gap: 1.75rem; align-items: start; }
.article-col { min-width: 0; display: flex; flex-direction: column; gap: 1.25rem; }
.article-col .article-meta { display: flex; align-items: center; flex-wrap: wrap; gap: .75rem; margin: 0; }
.article-cat { background: #1a237e; color: #fff; border-radius: 999px; padding: .2rem .9rem; font-size: .78rem; font-weight: 800; }
.article-col .article-date, .article-col .article-author { display: inline-flex; align-items: center; gap: .35rem; }
.article-col .article-date svg, .article-col .article-author svg { color: #e65100; }
.article-col .article-title { font-size: clamp(1.5rem, 2.6vw, 2.1rem); line-height: 1.6; margin: 0; }
.article-cover { border-radius: 20px; overflow: hidden; box-shadow: 0 10px 30px rgba(13, 27, 75, .12); background: #e5e7eb; }
.article-cover img { display: block; width: 100%; max-height: 520px; object-fit: cover; }
.article-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 20px; padding: 1.75rem 1.9rem; box-shadow: 0 6px 22px rgba(13, 27, 75, .05); }
.article-card .article-excerpt { margin-top: 0; }
.article-nav { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
.nav-card { display: flex; flex-direction: column; gap: .4rem; background: #fff; border: 1px solid #e5e7eb; border-radius: 16px; padding: 1rem 1.2rem; text-decoration: none; color: inherit; transition: box-shadow .25s, transform .25s, border-color .25s; }
.nav-card:hover { box-shadow: 0 12px 28px rgba(13, 27, 75, .12); transform: translateY(-2px); border-color: #c5cae9; }
.nav-next { text-align: start; }
.nav-label { display: inline-flex; align-items: center; gap: .3rem; font-size: .78rem; font-weight: 700; color: #9aa3b5; }
.nav-title { font-size: .95rem; font-weight: 800; color: #0d1b3e; line-height: 1.6; }
.detail-aside { display: flex; flex-direction: column; gap: 1.25rem; position: sticky; top: 130px; }
.aside-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 18px; padding: 1.1rem 1.1rem 1.2rem; box-shadow: 0 6px 22px rgba(13, 27, 75, .05); }
.aside-h { font-size: 1rem; font-weight: 800; color: #0d1b3e; padding-bottom: .7rem; margin: 0 0 .9rem; border-bottom: 1px solid #eceef3; position: relative; }
.aside-h::after { content: ''; position: absolute; bottom: -1px; right: 0; width: 38px; height: 3px; background: linear-gradient(90deg, #f9a825, #e65100); border-radius: 3px; }
.aside-search { display: flex; align-items: center; gap: .5rem; border: 1.5px solid #e0e0e0; border-radius: 10px; padding: .45rem .8rem; background: #f8f9fc; color: #757575; }
.aside-search:focus-within { border-color: #1a237e; }
.aside-search input { flex: 1; min-width: 0; border: 0; outline: 0; background: transparent; font-family: inherit; font-size: .9rem; }
.aside-cats { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: .35rem; }
.aside-cat { display: flex; align-items: center; gap: .55rem; padding: .55rem .75rem; border-radius: 10px; font-size: .92rem; font-weight: 700; color: #374151; text-decoration: none; transition: background .2s, color .2s; }
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
@media (max-width: 900px) {
  .detail-layout { grid-template-columns: 1fr; }
  .detail-aside { position: static; }
  .article-nav { grid-template-columns: 1fr; }
  .article-card { padding: 1.25rem; }
}
</style>

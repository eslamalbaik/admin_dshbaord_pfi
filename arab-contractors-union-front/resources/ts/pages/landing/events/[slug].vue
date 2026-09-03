<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import api from '@/plugins/axios'
import { Search, Calendar, MapPin, CalendarPlus, Users, User } from 'lucide-vue-next'

definePage({
  meta: {
    layout: 'landing',
    public: true,
    unauthenticatedOnly: false,
  },
})

const route = useRoute()

interface Speaker { name: string; title: string | null; photo: string | null; is_keynote: boolean }

interface EventDetail {
  id: number
  title: string
  slug: string
  excerpt: string | null
  body: string
  image: string | null
  video_url: string | null
  gallery: string[] | null
  published_at: string
  event_date: string | null
  event_location: string | null
  event_format: string | null
  is_international: boolean
  speakers: Speaker[] | null
  author?: { id: number; name: string }
}

const event = ref<EventDetail | null>(null)
const isLoading = ref(true)
const notFound = ref(false)

const eventFormatLabels: Record<string, string> = {
  onsite: 'وجاهي',
  online: 'أونلاين',
  hybrid: 'وجاهي + أونلاين',
}

function formatDate(dateStr: string): string {
  return new Date(dateStr).toLocaleDateString('ar-PS', {
    year: 'numeric', month: 'long', day: 'numeric',
    weekday: 'long',
  })
}

function formatEventDateTime(dateStr: string): string {
  return new Date(dateStr).toLocaleString('ar-PS', {
    year: 'numeric', month: 'long', day: 'numeric',
    hour: 'numeric', minute: '2-digit', hour12: true,
  })
}

function youtubeEmbedUrl(url: string): string | null {
  const m = url.match(/(?:youtu\.be\/|youtube\.com\/(?:watch\?v=|embed\/))([\w-]{11})/)
  return m ? `https://www.youtube.com/embed/${m[1]}` : null
}

function addToCalendar() {
  if (!event.value?.event_date) return
  const start = new Date(event.value.event_date)
  const end = new Date(start.getTime() + 60 * 60 * 1000)
  const toGCalDate = (d: Date) => d.toISOString().replace(/[-:]|\.\d{3}/g, '')
  const params = new URLSearchParams({
    action: 'TEMPLATE',
    text: event.value.title,
    dates: `${toGCalDate(start)}/${toGCalDate(end)}`,
    details: event.value.excerpt ?? '',
    location: event.value.event_location ?? '',
  })
  window.open(`https://calendar.google.com/calendar/render?${params.toString()}`, '_blank')
}

onMounted(async () => {
  try {
    const slug = route.params.slug as string
    const res = await api.get(`/api/v1/events/${slug}`)
    event.value = res.data.items
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
      <h2>الفعالية غير موجودة</h2>
      <p>لم يتم العثور على هذه الفعالية أو ربما تم حذفها.</p>
      <RouterLink to="/landing/events" class="btn-primary">العودة للفعاليات</RouterLink>
    </div>

    <!-- Article -->
    <template v-else-if="event">

      <!-- Hero image -->
      <div v-if="event.image" class="article-hero">
        <img :src="event.image" :alt="event.title" class="hero-img" />
        <div class="hero-overlay" />
      </div>

      <div class="container article-wrap">
        <article class="article">

          <!-- Meta -->
          <div class="article-meta">
            <span class="article-cat" style="background: #2e7d32">فعالية</span>
            <span v-if="event.is_international" class="article-cat" style="background: #6a1b9a">دولية</span>
            <span class="article-date">{{ formatDate(event.published_at) }}</span>
            <span v-if="event.author" class="article-author">بقلم: {{ event.author.name }}</span>
          </div>

          <!-- Title -->
          <h1 class="article-title">{{ event.title }}</h1>

          <!-- Excerpt -->
          <p v-if="event.excerpt" class="article-excerpt">{{ event.excerpt }}</p>

          <!-- Event info -->
          <div v-if="event.event_date || event.event_location" class="event-info-box">
            <div v-if="event.event_date" class="event-info-row">
              <Calendar :size="18" />
              <div>
                <strong>تاريخ الفعالية</strong>
                <span>{{ formatEventDateTime(event.event_date) }}</span>
              </div>
            </div>
            <div v-if="event.event_location" class="event-info-row">
              <MapPin :size="18" />
              <div>
                <strong>مكان الحدث</strong>
                <span>{{ event.event_location }}</span>
              </div>
            </div>
            <div v-if="event.event_format" class="event-info-row">
              <Users :size="18" />
              <div>
                <strong>نوع الحضور</strong>
                <span>{{ eventFormatLabels[event.event_format] ?? event.event_format }}</span>
              </div>
            </div>
            <button v-if="event.event_date" class="btn-add-calendar" @click="addToCalendar">
              <CalendarPlus :size="16" /> إضافة إلى التقويم
            </button>
          </div>

          <!-- Speakers -->
          <div v-if="event.speakers?.length" class="speakers-section">
            <h3>المتحدثون</h3>
            <div class="speakers-grid">
              <div v-for="(sp, i) in event.speakers" :key="i" class="speaker-card">
                <img v-if="sp.photo" :src="sp.photo" :alt="sp.name" class="speaker-photo" />
                <div v-else class="speaker-photo speaker-photo-placeholder"><User :size="26" /></div>
                <strong class="speaker-name">{{ sp.name }}</strong>
                <span v-if="sp.title" class="speaker-title">{{ sp.title }}</span>
                <span v-if="sp.is_keynote" class="keynote-badge">متحدث رئيسي</span>
              </div>
            </div>
          </div>

          <!-- Video -->
          <div v-if="event.video_url" class="article-video">
            <iframe
              v-if="youtubeEmbedUrl(event.video_url)"
              :src="youtubeEmbedUrl(event.video_url)!"
              frameborder="0"
              allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
              allowfullscreen
            />
            <a v-else :href="event.video_url" target="_blank" rel="noopener" class="video-fallback-link">مشاهدة الفيديو ↗</a>
          </div>

          <!-- Gallery -->
          <div v-if="event.gallery && event.gallery.length > 0" class="article-gallery">
            <img v-for="(img, i) in event.gallery" :key="i" :src="img" :alt="`${event.title} - ${i + 1}`" />
          </div>

          <!-- Divider -->
          <hr class="article-divider" />

          <!-- Body -->
          <div class="article-body" v-html="event.body" />

          <!-- Share + Back -->
          <div class="article-footer">
            <RouterLink to="/landing/events" class="btn-back-article">← العودة لقائمة الفعاليات</RouterLink>
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

/* Event info box */
.event-info-box {
  background: #e8f5e9;
  border: 1px solid #a5d6a7;
  border-radius: 14px;
  padding: 1.25rem;
  margin-bottom: 1.5rem;
  display: flex;
  flex-direction: column;
  gap: 0.9rem;
}

.event-info-row { display: flex; align-items: center; gap: 0.75rem; color: #1b5e20; }
.event-info-row div { display: flex; flex-direction: column; gap: 0.1rem; }
.event-info-row strong { font-size: 0.8rem; font-weight: 700; }
.event-info-row span { font-size: 0.9rem; color: #2e7d32; }

.btn-add-calendar {
  align-self: flex-start;
  display: flex;
  align-items: center;
  gap: 0.5rem;
  background: #2e7d32;
  color: white;
  border: none;
  border-radius: 10px;
  padding: 0.6rem 1.25rem;
  font-size: 0.85rem;
  font-weight: 700;
  font-family: inherit;
  cursor: pointer;
  transition: background 0.2s;
}

.btn-add-calendar:hover { background: #1b5e20; }

/* Speakers */
.speakers-section { margin-bottom: 1.5rem; }
.speakers-section h3 { font-size: 1.1rem; font-weight: 700; color: #1a1a2e; margin-bottom: 1rem; }

.speakers-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
  gap: 1rem;
}

.speaker-card {
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
  gap: 0.35rem;
  background: #f8fafc;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  padding: 1rem 0.75rem;
}

.speaker-photo {
  width: 64px;
  height: 64px;
  border-radius: 50%;
  object-fit: cover;
}

.speaker-photo-placeholder {
  display: flex;
  align-items: center;
  justify-content: center;
  background: #e0e7ff;
  color: #1a237e;
}

.speaker-name { font-size: 0.88rem; color: #1a1a2e; }
.speaker-title { font-size: 0.78rem; color: #6b7280; }

.keynote-badge {
  font-size: 0.7rem;
  font-weight: 700;
  color: #92400e;
  background: #fef3c7;
  padding: 0.15rem 0.6rem;
  border-radius: 50px;
  margin-top: 0.25rem;
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

/* Gallery */
.article-gallery {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
  gap: 0.75rem;
  margin-bottom: 1.5rem;
}

.article-gallery img {
  width: 100%;
  height: 110px;
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

@media (max-width: 600px) {
  .article { padding: 1.5rem; }
  .article-hero { height: 250px; }
}
</style>

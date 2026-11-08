<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { ChevronLeft, Radio } from 'lucide-vue-next'
import api from '@/plugins/axios'

// "آخر التحديثات" ticker, styled like a TV news lower-third. Sits under the home hero.
// The bar always renders (fallback line when there is no news) so the layout never jumps.

interface TickerItem { key: string; title: string; to: string }

const FALLBACK: TickerItem[] = [
  { key: 'fallback', title: 'تابعوا آخر أخبار ونشاطات اتحاد المقاولين الفلسطينيين', to: '/landing/news' },
]

const items = ref<TickerItem[]>(FALLBACK)

onMounted(async () => {
  try {
    const r = await api.get('/api/v1/news', { params: { per_page: 8 } })
    const list = (r.data.items ?? []) as any[]
    if (list.length) {
      items.value = list
        .filter(n => n?.title)
        .map(n => ({ key: `news-${n.id}`, title: String(n.title).trim(), to: `/landing/news/${n.id}` }))
    }
  }
  catch {}
})

// ~ constant reading speed whatever the number/length of headlines
const duration = computed(() => {
  const chars = items.value.reduce((sum, i) => sum + i.title.length, 0)
  return `${Math.max(25, Math.round(chars * 0.28))}s`
})
</script>

<template>
  <div class="nt" role="region" aria-label="آخر التحديثات">
    <div class="nt-label">
      <span class="nt-live"><Radio :size="16" /></span>
      <span class="nt-label-text">آخر التحديثات</span>
    </div>

    <div class="nt-viewport">
      <!-- two copies of the list so the loop is seamless -->
      <div class="nt-track" :style="{ animationDuration: duration }">
        <template v-for="copy in 2" :key="copy">
          <RouterLink
            v-for="item in items" :key="`${copy}-${item.key}`"
            :to="item.to" class="nt-item" dir="rtl"
            :aria-hidden="copy === 2 ? 'true' : undefined" :tabindex="copy === 2 ? -1 : undefined"
          >
            <i class="nt-sep" />{{ item.title }}
          </RouterLink>
        </template>
      </div>
    </div>

    <RouterLink to="/landing/news" class="nt-archive">
      عرض الأرشيف <ChevronLeft :size="16" />
    </RouterLink>
  </div>
</template>

<style scoped>
.nt {
  position: relative; z-index: 199;
  display: flex; align-items: stretch; height: 46px; overflow: hidden;
  background: linear-gradient(90deg, #12147f 0%, #1a237e 45%, #2b3aa8 100%);
  border-bottom: 3px solid #f5a623;
  font-family: 'Tajawal', 'Cairo', sans-serif; color: #fff;
}
/* subtle diagonal sheen like a broadcast lower-third */
.nt::before {
  content: ''; position: absolute; top: 0; right: 0; bottom: 0; left: 0; pointer-events: none;
  background: repeating-linear-gradient(-60deg, rgba(255, 255, 255, .035) 0 14px, transparent 14px 28px);
}

/* label block on the right, slanted towards the headlines */
.nt-label {
  position: relative; z-index: 2; flex-shrink: 0;
  display: flex; align-items: center; gap: .55rem;
  padding-inline: 1.6rem 2.4rem;
  background: #f5a623; color: #00006e;
  clip-path: polygon(100% 0, 0 0, 22px 100%, 100% 100%);
  font-weight: 800; font-size: 1rem; white-space: nowrap;
}
/* dark slanted stripe between the label and the headlines */
.nt-label::after {
  content: ''; position: absolute; top: 0; bottom: 0; left: 6px; width: 10px;
  background: #00006e; transform: skewX(25deg);
}
.nt-live { display: inline-flex; animation: nt-pulse 1.6s ease-in-out infinite; }
@keyframes nt-pulse { 50% { opacity: .35; } }

.nt-viewport {
  position: relative; z-index: 1; flex: 1; min-width: 0; overflow: hidden;
  direction: ltr; display: flex; align-items: center;
  -webkit-mask-image: linear-gradient(90deg, transparent 0, #000 40px, #000 calc(100% - 40px), transparent 100%);
  mask-image: linear-gradient(90deg, transparent 0, #000 40px, #000 calc(100% - 40px), transparent 100%);
}
/* Arabic ticker: headlines travel left → right */
.nt-track {
  display: flex; width: max-content; flex-shrink: 0;
  animation: nt-scroll 40s linear infinite;
}
.nt:hover .nt-track, .nt-track:focus-within { animation-play-state: paused; }
@keyframes nt-scroll { from { transform: translateX(-50%); } to { transform: translateX(0); } }

.nt-item {
  display: inline-flex; align-items: center; gap: 1.1rem; padding-inline: 1.1rem;
  color: #fff; text-decoration: none; white-space: nowrap; font-size: .95rem; font-weight: 500;
}
.nt-item:hover { color: #ffd27a; }
.nt-sep { width: 8px; height: 8px; background: #f5a623; transform: rotate(45deg); flex-shrink: 0; }

.nt-archive {
  position: relative; z-index: 2; flex-shrink: 0;
  display: flex; align-items: center; gap: .3rem;
  padding-inline: 2.2rem 1.4rem;
  background: #00006e; color: #fff; text-decoration: none;
  clip-path: polygon(0 0, calc(100% - 22px) 0, 100% 100%, 0 100%);
  font-size: .9rem; font-weight: 700; white-space: nowrap; transition: background .2s;
}
.nt-archive:hover { background: #d67a00; }

@media (max-width: 1100px) {
  .nt { height: 40px; }
  .nt-label { padding-inline: 1rem 2rem; font-size: .88rem; }
  .nt-item { font-size: .85rem; }
}
@media (max-width: 600px) {
  .nt-label-text { display: none; }
  .nt-label { padding-inline: .9rem 1.8rem; }
  .nt-archive { padding-inline: 1.7rem .8rem; font-size: .8rem; }
}
@media (prefers-reduced-motion: reduce) {
  .nt-track { animation: none; }
  .nt-viewport { overflow-x: auto; }
  .nt-live { animation: none; }
}
</style>

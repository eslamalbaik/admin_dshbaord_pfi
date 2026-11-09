<script setup lang="ts">
import { computed, nextTick, onUnmounted, ref, watch } from 'vue'
import { Check, Copy, Eye, Gauge, Link2, Share2, X } from 'lucide-vue-next'
import api from '@/plugins/axios'

/**
 * نافذة فيديو "كيف تسجّل دخولك" بالصفحة الرئيسية.
 * - الفيديو ملف عام على الموقع: /videos/login-guide.mp4
 * - بيشتغل افتراضياً على سرعة 1.2 (المستخدم بيقدر يغيّرها)
 * - رابط المشاركة بيفتح الصفحة الرئيسية والنافذة مفتوحة: /landing?video=login
 * - المشاهدة بتنحسب مرة وحدة بكل جلسة متصفح، أول ما يبلش التشغيل
 */
const props = defineProps<{ modelValue: boolean }>()
const emit = defineEmits<{ (e: 'update:modelValue', v: boolean): void }>()

const VIDEO_KEY = 'login-guide'
const VIDEO_SRC = '/videos/login-guide.mp4'
const POSTER_SRC = '/videos/login-guide-poster.jpg'
const DEFAULT_RATE = 1.2
const RATES = [1, 1.2, 1.5, 2]
const SEEN_KEY = 'pcu_video_seen_login-guide'

const videoEl = ref<HTMLVideoElement | null>(null)
const rate = ref(DEFAULT_RATE)
const views = ref<number | null>(null)
const copied = ref(false)
let viewSent = false
let copiedTimer: ReturnType<typeof setTimeout> | undefined

const shareUrl = computed(() =>
  typeof window === 'undefined' ? '/landing?video=login' : `${window.location.origin}/landing?video=login`,
)

const shareText = 'شرح خطوات تفعيل الحساب وتسجيل الدخول في تطبيق اتحاد المقاولين الفلسطينيين'

const viewsLabel = computed(() => {
  if (views.value === null)
    return '—'

  return new Intl.NumberFormat('ar-EG').format(views.value)
})

function close() {
  emit('update:modelValue', false)
}

function applyRate(r = rate.value) {
  rate.value = r

  const v = videoEl.value
  if (!v)
    return
  v.defaultPlaybackRate = r
  v.playbackRate = r
}

async function loadViews() {
  try {
    const r = await api.get(`/api/v1/site-videos/${VIDEO_KEY}`)

    views.value = Number(r.data?.data?.views ?? 0)
  }
  catch { /* العداد مش ضروري لعرض الفيديو */ }
}

function alreadySeen() {
  try { return sessionStorage.getItem(SEEN_KEY) === '1' }
  catch { return false }
}

async function onPlay() {
  applyRate()
  if (viewSent || alreadySeen())
    return
  viewSent = true
  try {
    const r = await api.post(`/api/v1/site-videos/${VIDEO_KEY}/view`)

    views.value = Number(r.data?.data?.views ?? views.value ?? 0)
    try { sessionStorage.setItem(SEEN_KEY, '1') }
    catch { /* private mode */ }
  }
  catch { viewSent = false }
}

async function copyLink() {
  const text = shareUrl.value
  try {
    await navigator.clipboard.writeText(text)
  }
  catch {
    const ta = document.createElement('textarea')

    ta.value = text
    ta.setAttribute('readonly', '')
    ta.style.position = 'fixed'
    ta.style.opacity = '0'
    document.body.appendChild(ta)
    ta.select()
    try { document.execCommand('copy') }
    finally { document.body.removeChild(ta) }
  }
  copied.value = true
  clearTimeout(copiedTimer)
  copiedTimer = setTimeout(() => (copied.value = false), 2200)
}

const shareOpen = ref(false)

const shareTargets = computed(() => {
  const u = encodeURIComponent(shareUrl.value)
  const t = encodeURIComponent(shareText)

  return [
    { key: 'whatsapp', label: 'واتساب', href: `https://wa.me/?text=${t}%20${u}`, color: '#25d366' },
    { key: 'facebook', label: 'فيسبوك', href: `https://www.facebook.com/sharer/sharer.php?u=${u}`, color: '#1877f2' },
    { key: 'telegram', label: 'تيليجرام', href: `https://t.me/share/url?url=${u}&text=${t}`, color: '#229ed9' },
    { key: 'x', label: 'إكس', href: `https://twitter.com/intent/tweet?url=${u}&text=${t}`, color: '#0b0b0f' },
  ]
})

async function share() {
  if (typeof navigator !== 'undefined' && typeof navigator.share === 'function') {
    try {
      await navigator.share({ title: 'كيف تسجّل دخولك', text: shareText, url: shareUrl.value })

      return
    }
    catch (e: any) {
      if (e?.name === 'AbortError')
        return
    }
  }
  shareOpen.value = !shareOpen.value
}

function onKey(e: KeyboardEvent) {
  if (e.key === 'Escape')
    close()
}

watch(() => props.modelValue, async open => {
  if (typeof document === 'undefined')
    return
  if (open) {
    document.addEventListener('keydown', onKey)
    document.documentElement.style.overflow = 'hidden'
    shareOpen.value = false
    loadViews()
    await nextTick()
    applyRate(DEFAULT_RATE)
    videoEl.value?.play().catch(() => { /* المتصفح منع التشغيل التلقائي — المستخدم بيضغط تشغيل */ })
  }
  else {
    document.removeEventListener('keydown', onKey)
    document.documentElement.style.overflow = ''
    videoEl.value?.pause()
  }
}, { immediate: true })

onUnmounted(() => {
  if (typeof document === 'undefined')
    return
  document.removeEventListener('keydown', onKey)
  document.documentElement.style.overflow = ''
  clearTimeout(copiedTimer)
})
</script>

<template>
  <Teleport to="body">
    <Transition name="lgv">
      <div
        v-if="modelValue"
        class="lgv-backdrop"
        dir="rtl"
        @click.self="close"
      >
        <div
          class="lgv-card"
          role="dialog"
          aria-modal="true"
          aria-labelledby="lgv-title"
        >
          <header class="lgv-head">
            <div class="lgv-head-text">
              <span class="lgv-kicker"><i />دليل المقاول</span>
              <h2 id="lgv-title">
                كيف تسجّل دخولك؟
              </h2>
              <p>خطوات تفعيل الحساب وتسجيل الدخول في تطبيق اتحاد المقاولين الفلسطينيين</p>
            </div>
            <button
              type="button"
              class="lgv-close"
              aria-label="إغلاق"
              @click="close"
            >
              <X :size="20" />
            </button>
          </header>

          <div class="lgv-player">
            <video
              ref="videoEl"
              :src="VIDEO_SRC"
              :poster="POSTER_SRC"
              controls
              playsinline
              preload="metadata"
              controlslist="nodownload"
              @loadedmetadata="applyRate(rate)"
              @play="onPlay"
            />
          </div>

          <div class="lgv-bar">
            <div class="lgv-meta">
              <span
                class="lgv-views"
                title="عدد المشاهدات"
              >
                <Eye :size="17" />
                <b>{{ viewsLabel }}</b> مشاهدة
              </span>
              <div
                class="lgv-speed"
                role="group"
                aria-label="سرعة التشغيل"
              >
                <Gauge :size="16" />
                <button
                  v-for="r in RATES"
                  :key="r"
                  type="button"
                  :class="{ active: rate === r }"
                  @click="applyRate(r)"
                >
                  {{ r }}×
                </button>
              </div>
            </div>

            <div class="lgv-link">
              <span class="lgv-link-icon"><Link2 :size="16" /></span>
              <input
                :value="shareUrl"
                readonly
                dir="ltr"
                aria-label="رابط الفيديو"
                @focus="($event.target as HTMLInputElement).select()"
              >
              <button
                type="button"
                class="lgv-btn lgv-btn-copy"
                :class="{ done: copied }"
                @click="copyLink"
              >
                <component
                  :is="copied ? Check : Copy"
                  :size="16"
                />
                {{ copied ? 'تم النسخ' : 'نسخ الرابط' }}
              </button>
              <button
                type="button"
                class="lgv-btn lgv-btn-share"
                @click="share"
              >
                <Share2 :size="16" /> مشاركة
              </button>
            </div>

            <Transition name="lgv-fade">
              <div
                v-if="shareOpen"
                class="lgv-share"
              >
                <a
                  v-for="s in shareTargets"
                  :key="s.key"
                  :href="s.href"
                  target="_blank"
                  rel="noopener noreferrer"
                  :style="{ '--c': s.color }"
                >{{ s.label }}</a>
              </div>
            </Transition>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.lgv-backdrop {
  --navy: #00006e;
  --navy-2: #0a1a9c;
  --yellow: #ffb61a;
  --orange: #d67a00;

  position: fixed; inset: 0; z-index: 3000;
  display: grid; place-items: center; padding: 20px 16px;
  background: radial-gradient(circle at 50% 30%, rgba(10, 26, 156, .55), rgba(0, 0, 40, .82));
  backdrop-filter: blur(6px);
  font-family: 'Tajawal', 'Cairo', sans-serif;
  overflow-y: auto;
}
.lgv-card {
  width: min(920px, 100%);
  background: #fff; border-radius: 22px; overflow: hidden;
  box-shadow: 0 40px 90px rgba(0, 0, 50, .45), 0 0 0 1px rgba(255, 255, 255, .08);
}
.lgv-head {
  position: relative; display: flex; align-items: flex-start; justify-content: space-between; gap: 16px;
  padding: 22px 26px 20px; color: #fff;
  background: linear-gradient(120deg, var(--navy) 0%, var(--navy-2) 100%);
  border-bottom: 4px solid var(--yellow);
}
.lgv-head-text { min-width: 0; }
.lgv-kicker {
  display: inline-flex; align-items: center; gap: 8px; font-size: 12px; font-weight: 700;
  padding: 4px 12px; border-radius: 999px; background: rgba(255, 255, 255, .12); margin-bottom: 8px;
}
.lgv-kicker i { width: 8px; height: 8px; border-radius: 50%; background: var(--yellow); }
.lgv-head h2 { margin: 0 0 4px; font-size: clamp(20px, 2.4vw, 26px); font-weight: 800; color: #fff; font-family: inherit; }
.lgv-head p { margin: 0; font-size: 14px; font-weight: 500; color: rgba(255, 255, 255, .85); line-height: 1.7; }
.lgv-close {
  flex-shrink: 0; width: 40px; height: 40px; border-radius: 12px; border: 0; cursor: pointer;
  display: grid; place-items: center; color: var(--navy); background: var(--yellow);
  transition: transform .2s, background .2s;
}
.lgv-close:hover { transform: rotate(90deg); background: #fff; }

.lgv-player { background: #000; aspect-ratio: 16 / 9; }
.lgv-player video { display: block; width: 100%; height: 100%; background: #000; }

.lgv-bar { padding: 18px 22px 22px; display: flex; flex-direction: column; gap: 14px; }
.lgv-meta { display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; }
.lgv-views {
  display: inline-flex; align-items: center; gap: 8px; font-size: 15px; font-weight: 700; color: var(--navy);
  background: #e8e8f6; border-radius: 999px; padding: 7px 16px;
}
.lgv-views b { font-size: 17px; font-weight: 800; }
.lgv-speed {
  display: inline-flex; align-items: center; gap: 4px; color: #5b6474;
  background: #f4f5f9; border-radius: 999px; padding: 4px 6px 4px 12px;
}
.lgv-speed svg { margin-inline-end: 4px; }
.lgv-speed button {
  border: 0; background: transparent; cursor: pointer; font: inherit; font-size: 13px; font-weight: 700;
  color: #5b6474; padding: 5px 11px; border-radius: 999px; direction: ltr; transition: background .2s, color .2s;
}
.lgv-speed button:hover { color: var(--navy); }
.lgv-speed button.active { background: var(--navy); color: #fff; }

.lgv-link {
  display: flex; align-items: center; gap: 8px; padding: 6px; border: 1.5px solid #dfe2ea; border-radius: 14px;
  background: #fafbfd;
}
.lgv-link-icon { width: 34px; height: 34px; flex-shrink: 0; border-radius: 10px; display: grid; place-items: center; background: #fff4e3; color: var(--orange); }
.lgv-link input {
  flex: 1; min-width: 0; border: 0; outline: 0; background: transparent; font: inherit; font-size: 14px;
  color: #0b0b0f; text-align: left; padding: 0 4px;
}
.lgv-btn {
  flex-shrink: 0; display: inline-flex; align-items: center; gap: 7px; border: 0; cursor: pointer;
  font: inherit; font-size: 14px; font-weight: 700; padding: 10px 16px; border-radius: 10px;
  transition: background .2s, transform .2s, color .2s;
}
.lgv-btn:hover { transform: translateY(-1px); }
.lgv-btn-copy { background: var(--navy); color: #fff; }
.lgv-btn-copy.done { background: #11903a; }
.lgv-btn-share { background: var(--yellow); color: var(--navy); }

.lgv-share { display: flex; gap: 8px; flex-wrap: wrap; }
.lgv-share a {
  flex: 1 1 110px; text-align: center; padding: 10px 12px; border-radius: 10px; font-size: 14px; font-weight: 700;
  color: #fff; background: var(--c); text-decoration: none; transition: filter .2s;
}
.lgv-share a:hover { filter: brightness(1.1); }

.lgv-enter-active, .lgv-leave-active { transition: opacity .25s ease; }
.lgv-enter-active .lgv-card, .lgv-leave-active .lgv-card { transition: transform .3s cubic-bezier(.22, 1, .36, 1); }
.lgv-enter-from, .lgv-leave-to { opacity: 0; }
.lgv-enter-from .lgv-card, .lgv-leave-to .lgv-card { transform: translateY(24px) scale(.96); }
.lgv-fade-enter-active, .lgv-fade-leave-active { transition: opacity .2s; }
.lgv-fade-enter-from, .lgv-fade-leave-to { opacity: 0; }

@media (max-width: 600px) {
  .lgv-backdrop { padding: 12px 8px; }
  .lgv-card { border-radius: 16px; }
  .lgv-head { padding: 16px 16px 14px; }
  .lgv-head p { font-size: 13px; }
  .lgv-bar { padding: 14px; }
  .lgv-link { flex-wrap: wrap; }
  .lgv-link input { flex-basis: calc(100% - 46px); height: 34px; }
  .lgv-btn { flex: 1; justify-content: center; }
  .lgv-share a { flex-basis: calc(50% - 4px); }
}
</style>

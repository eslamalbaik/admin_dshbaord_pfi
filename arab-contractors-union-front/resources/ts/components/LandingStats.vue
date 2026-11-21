<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import { MotionDirective } from '@vueuse/motion'
import { Award, Building2, CalendarDays, Layers } from 'lucide-vue-next'

/**
 * شريط إحصائيات الاتحاد (شركات مصنفة، مجالات، اختصاصات، سنوات) — مشترك بين الرئيسية و"من نحن".
 * الأرقام بتعدّ من صفر لقيمتها أول ما الشريط يبين بالشاشة (ومرة ثانية لو تغيّرت القيمة بعد وصول بيانات الـ API).
 */
const props = withDefaults(defineProps<{ years?: number; specializations?: number }>(), { years: 30, specializations: 0 })

const vReveal = MotionDirective({
  initial: { opacity: 0, y: 40 },
  visibleOnce: { opacity: 1, y: 0, transition: { duration: 700, ease: [0.22, 1, 0.36, 1] } },
})

const stats = computed(() => [
  { icon: Building2, num: 350, suffix: '+', label: 'شركة مقاولات مصنفة', tone: 'navy' },
  { icon: Award, num: 5, suffix: '', label: 'مجالات تصنيف معتمدة', tone: 'orange' },
  { icon: Layers, num: props.specializations || 40, suffix: props.specializations ? '' : '+', label: 'اختصاصاً معتمداً', tone: 'navy' },
  { icon: CalendarDays, num: props.years, suffix: '+', label: 'عاماً من العمل المؤسسي والمهني', tone: 'orange' },
])
const statsEl = ref<HTMLElement | null>(null)
const statShown = ref<number[]>([0, 0, 0, 0])
let statsStarted = false
let statsRaf = 0
function countStats() {
  cancelAnimationFrame(statsRaf)
  const from = [...statShown.value]
  const to = stats.value.map(x => x.num)
  if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) { statShown.value = to; return }
  const t0 = performance.now()
  const dur = 1800
  const tick = (now: number) => {
    const t = Math.min(1, (now - t0) / dur)
    const e = 1 - Math.pow(1 - t, 3) // easeOutCubic
    statShown.value = to.map((v, i) => Math.round(from[i] + (v - from[i]) * e))
    if (t < 1) statsRaf = requestAnimationFrame(tick)
  }
  statsRaf = requestAnimationFrame(tick)
}
watch(() => stats.value.map(x => x.num).join(), () => { if (statsStarted) countStats() })
let statsObserver: IntersectionObserver | undefined
onMounted(() => {
  if (!statsEl.value) return
  statsObserver = new IntersectionObserver((entries) => {
    if (entries.some(e => e.isIntersecting) && !statsStarted) {
      statsStarted = true
      countStats()
      statsObserver?.disconnect()
    }
  }, { threshold: 0.35 })
  statsObserver.observe(statsEl.value)
})
onUnmounted(() => { cancelAnimationFrame(statsRaf); statsObserver?.disconnect() })
</script>

<template>
  <div ref="statsEl" v-reveal class="stats">
    <div v-for="(s, i) in stats" :key="s.label" class="stat" :class="`stat-${s.tone}`" :style="{ '--d': `${i * 90}ms` }">
      <span class="stat-icon"><component :is="s.icon" :size="26" :stroke-width="1.8" /></span>
      <strong><span class="stat-num" dir="ltr">{{ statShown[i] }}{{ s.suffix }}</span></strong>
      <span class="stat-label">{{ s.label }}</span>
    </div>
  </div>
</template>

<style scoped>
.stats { --navy: #00006e; --orange: #d67a00; --yellow: #ffb61a; font-family: 'Tajawal', 'Cairo', sans-serif; direction: rtl; }
.stats {
  position: relative; overflow: hidden; display: grid; grid-template-columns: repeat(4, 1fr); gap: 18px; margin-top: var(--stats-mt, 72px); padding: 22px;
  border-radius: 24px; background: linear-gradient(135deg, #00004f 0%, #00006e 55%, #1a1a8f 100%);
  box-shadow: 0 22px 50px rgba(0, 0, 110, .25);
}
.stats::before, .stats::after { content: ''; position: absolute; border-radius: 50%; pointer-events: none; }
.stats::before { width: 260px; height: 260px; top: -120px; inset-inline-start: -60px; background: radial-gradient(circle, rgba(255, 182, 26, .25), transparent 70%); }
.stats::after { width: 220px; height: 220px; bottom: -110px; inset-inline-end: -40px; background: radial-gradient(circle, rgba(255, 255, 255, .12), transparent 70%); }
.stat {
  position: relative; z-index: 1; display: flex; flex-direction: column; align-items: center; text-align: center; gap: 6px;
  padding: 26px 14px 22px; border-radius: 18px; background: rgba(255, 255, 255, .07); border: 1px solid rgba(255, 255, 255, .14);
  backdrop-filter: blur(4px); transition: transform .3s ease, background .3s ease;
}
.stat:hover { transform: translateY(-6px); background: rgba(255, 255, 255, .12); }
.stat::after { content: ''; position: absolute; bottom: 0; left: 24%; right: 24%; height: 3px; border-radius: 3px 3px 0 0; background: linear-gradient(90deg, var(--orange), var(--yellow)); opacity: .9; }
.stat-icon { width: 58px; height: 58px; border-radius: 16px; display: grid; place-items: center; margin-bottom: 6px; background: #fff; box-shadow: 0 8px 20px rgba(0, 0, 0, .2); }
.stat strong { font-size: clamp(34px, 3.2vw, 46px); font-weight: 900; line-height: 1.1; color: #fff; direction: ltr; font-variant-numeric: tabular-nums; }
.stat-num { display: inline-block; }
.stat-label { color: rgba(255, 255, 255, .8); font-size: 14.5px; font-weight: 700; }
.stat-navy .stat-icon { color: var(--navy); }
.stat-orange .stat-icon { color: var(--orange); }
.stat-orange strong { color: var(--yellow); }

@media (max-width: 1000px) {
  .stats { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 720px) {
  .stats { grid-template-columns: 1fr 1fr; gap: 12px; padding: 14px; margin-top: calc(var(--stats-mt, 72px) - 24px); }
  .stat { padding: 20px 8px 18px; }
}
</style>

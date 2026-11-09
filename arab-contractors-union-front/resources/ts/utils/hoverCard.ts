import type { Directive } from 'vue'
import './hoverCard.css'

/**
 * v-hover-card: حركة الكروت العامة (العطاءات والأخبار) لما يمر الماوس فوقها.
 * بيحط الكلاس .hc وبيحدّث متغيرات CSS حسب مكان الماوس:
 *   --mx / --my  : مكان الضوء اللي بيلحق الماوس
 *   --hc-rot     : ميلان ثلاثي الأبعاد خفيف باتجاه الماوس
 * القيمة اختيارية: v-hover-card="{ tilt: 0 }" بيلغي الميلان (للكروت العريضة).
 * باللمس (موبايل) ما في hover أصلاً، والكود بيتجاهل غير الماوس. ومع prefers-reduced-motion ما في ميلان.
 */
interface HoverCardOptions { tilt?: number }

interface HoverCardEl extends HTMLElement {
  __hc?: { move: (e: PointerEvent) => void; leave: () => void; tilt: number; frame: number }
}

const reducedMotion = () =>
  typeof window !== 'undefined' && window.matchMedia?.('(prefers-reduced-motion: reduce)').matches

export const vHoverCard: Directive<HoverCardEl, HoverCardOptions | undefined> = {
  mounted(el, binding) {
    el.classList.add('hc')

    const state = {
      tilt: binding.value?.tilt ?? 7,
      frame: 0,
      move(e: PointerEvent) {
        if (e.pointerType !== 'mouse') return
        cancelAnimationFrame(state.frame)
        state.frame = requestAnimationFrame(() => {
          const r = el.getBoundingClientRect()
          if (!r.width || !r.height) return
          const px = (e.clientX - r.left) / r.width // 0..1
          const py = (e.clientY - r.top) / r.height
          el.style.setProperty('--mx', `${(px * 100).toFixed(1)}%`)
          el.style.setProperty('--my', `${(py * 100).toFixed(1)}%`)

          const dx = px - 0.5
          const dy = py - 0.5
          const mag = Math.min(1, Math.hypot(dx, dy) * 2)
          if (!state.tilt || reducedMotion() || mag < 0.02) {
            el.style.setProperty('--hc-rot', '0 0 1 0deg')
            return
          }
          // الكرت بيميل لجهة الماوس: محور الدوران عمودي على اتجاه الماوس
          el.style.setProperty('--hc-rot', `${(-dy).toFixed(3)} ${dx.toFixed(3)} 0 ${(state.tilt * mag).toFixed(2)}deg`)
        })
      },
      leave() {
        cancelAnimationFrame(state.frame)
        el.style.setProperty('--hc-rot', '0 0 1 0deg')
      },
    }

    el.__hc = state
    el.addEventListener('pointermove', state.move, { passive: true })
    el.addEventListener('pointerleave', state.leave)
  },
  updated(el, binding) {
    if (el.__hc) el.__hc.tilt = binding.value?.tilt ?? 7
  },
  unmounted(el) {
    const state = el.__hc
    if (!state) return
    cancelAnimationFrame(state.frame)
    el.removeEventListener('pointermove', state.move)
    el.removeEventListener('pointerleave', state.leave)
    delete el.__hc
  },
}

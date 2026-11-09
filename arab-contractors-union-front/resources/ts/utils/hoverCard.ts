import type { Directive } from 'vue'
import './hoverCard.css'

/**
 * v-hover-card: حركة hover رسمية وهادية لكروت العطاءات والأخبار العامة.
 * بس بتحط الكلاس .hc — الحركة كلها CSS (رفعة خفيفة + ظل + خط سفلي بألوان الاتحاد).
 * ما في ميلان ولا لحاق للماوس (طلب المالك 2026-11-09).
 */
export const vHoverCard: Directive<HTMLElement> = {
  mounted(el) {
    el.classList.add('hc')
  },
}

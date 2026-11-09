<script setup lang="ts">
import { ref } from 'vue'

// "دخول المقاول" button: on hover/focus a contractor's helmet flies onto the person's head,
// and flies off again when the pointer leaves. Size, background etc. come from the parent's class.
defineProps<{ label?: string }>()

// idle: no helmet (first load) | on: helmet on the head | off: helmet flew away
const hat = ref<'idle' | 'on' | 'off'>('idle')
const hatOn = () => { hat.value = 'on' }
const hatOff = () => { if (hat.value === 'on') hat.value = 'off' }
</script>

<template>
  <button
    type="button"
    class="plb"
    :class="{ 'plb--hat-on': hat === 'on', 'plb--hat-off': hat === 'off' }"
    @mouseenter="hatOn" @mouseleave="hatOff" @focus="hatOn" @blur="hatOff"
  >
    <span class="plb__icon" aria-hidden="true">
      <span class="plb__scale">
        <span class="plb__figure">
          <svg class="plb__person" viewBox="0 0 48 48" fill="none">
            <circle cx="24" cy="18" r="7" stroke="#fff" stroke-width="2.6" />
            <path d="M10 42c0-8 6.3-13 14-13s14 5 14 13" stroke="#fff" stroke-width="2.6" stroke-linecap="round" />
          </svg>
          <svg class="plb__helmet" viewBox="0 0 26 18">
            <path d="M3.5 14a9.5 9.5 0 0 1 19 0Z" fill="#f5a623" />
            <rect x="0.5" y="13" width="25" height="3.6" rx="1.8" fill="#f5a623" />
            <rect x="11.5" y="3.6" width="3" height="9.6" rx="1.2" fill="#d67a00" />
            <path d="M6.5 10.5a7 7 0 0 1 3-4" stroke="#fff" stroke-opacity=".55" stroke-width="1.3" stroke-linecap="round" fill="none" />
          </svg>
        </span>
      </span>
    </span>
    <span class="plb__label">{{ label ?? 'دخول المقاول' }}</span>
  </button>
</template>

<style scoped>
.plb { -webkit-tap-highlight-color: transparent; }
.plb:active { transform: scale(.96) !important; }
.plb:focus-visible { outline: 3px solid #f5a623; outline-offset: 3px; }

/* the 48px drawing is scaled down to fit the header button; keyframes keep their 48px values */
.plb__icon { position: relative; display: inline-block; width: 30px; height: 30px; flex: none; }
.plb__scale { position: absolute; top: 0; left: 0; width: 48px; height: 48px; transform: scale(.625); transform-origin: 0 0; }
.plb__figure { position: relative; display: block; width: 48px; height: 48px; transform-origin: 50% 100%; }
.plb__person { display: block; width: 48px; height: 48px; }

/* helmet waits hidden above the person */
.plb__helmet {
  position: absolute; left: 11px; top: 1px; width: 26px; height: 18px;
  opacity: 0; pointer-events: none; transform-origin: 50% 90%;
}

/* helmet flies in and lands on the head */
.plb--hat-on .plb__helmet { animation: plb-hat-in .7s cubic-bezier(.3, .7, .4, 1) forwards; }
.plb--hat-on .plb__figure { animation: plb-squash .7s ease forwards; }
/* helmet flies off */
.plb--hat-off .plb__helmet { animation: plb-hat-out .45s cubic-bezier(.5, 0, .75, 0) forwards; }

@keyframes plb-hat-in {
  0% { opacity: 0; transform: translate(-46px, -48px) rotate(-70deg) scale(.7); }
  15% { opacity: 1; }
  55% { opacity: 1; transform: translate(-6px, -16px) rotate(18deg) scale(1.05); }
  72% { transform: translate(0, 1px) rotate(-4deg) scale(1); }
  84% { transform: translate(0, -4px) rotate(3deg); }
  100% { opacity: 1; transform: translate(0, 0) rotate(0); }
}
@keyframes plb-hat-out {
  0% { opacity: 1; transform: translate(0, 0) rotate(0); }
  30% { opacity: 1; transform: translate(4px, -10px) rotate(12deg); }
  100% { opacity: 0; transform: translate(42px, -50px) rotate(75deg) scale(.7); }
}
/* the person squashes a little when the helmet lands */
@keyframes plb-squash {
  0%, 66% { transform: scale(1, 1); }
  74% { transform: scale(1.08, .88); }
  86% { transform: scale(.97, 1.04); }
  100% { transform: scale(1, 1); }
}

@media (prefers-reduced-motion: reduce) {
  .plb--hat-on .plb__helmet { animation: none; opacity: 1; transform: none; }
  .plb--hat-on .plb__figure { animation: none; }
  .plb--hat-off .plb__helmet { animation: none; opacity: 0; }
}
</style>

<script setup lang="ts">
const { y } = useWindowScroll()

const scrollToTop = () => {
  window.scrollTo({
    top: 0,
    behavior: 'smooth',
  })
}
</script>

<template>
  <VScaleTransition
    style="transform-origin: center;"
    class="scroll-to-top d-print-none"
  >
    <button
      v-show="y > 200"
      type="button"
      class="scroll-to-top__btn"
      aria-label="العودة إلى الأعلى"
      @click="scrollToTop"
    >
      <VIcon
        size="22"
        icon="tabler-arrow-up"
      />
    </button>
  </VScaleTransition>
</template>

<style lang="scss">
.scroll-to-top {
  position: fixed !important;

  // To keep button on top of v-layout. E.g. Email app
  z-index: 999;
  inset-block-end: 5%;
  inset-inline-end: 25px;
}

.scroll-to-top__btn {
  display: flex;
  align-items: center;
  justify-content: center;
  inline-size: 46px;
  block-size: 46px;
  border: 2px solid rgba(255, 255, 255, 0.85);
  border-radius: 50%;
  background: linear-gradient(145deg, #1a237e, #0d1b4b);
  color: #fff !important;
  cursor: pointer;
  box-shadow: 0 6px 18px rgba(13, 27, 75, 0.35);
  transition: transform 0.2s, box-shadow 0.2s, background 0.2s, color 0.2s;

  &:hover {
    background: #f9a825;
    color: #0d1b4b !important;
    box-shadow: 0 10px 24px rgba(13, 27, 75, 0.4);
    transform: translateY(-3px);
  }

  &:focus-visible {
    outline: 3px solid #f9a825;
    outline-offset: 2px;
  }
}
</style>

<script setup lang="ts">
import type { PublicSocialKey } from '@/utils/publicSocialLinks'

withDefaults(defineProps<{ name: PublicSocialKey; size?: number }>(), { size: 18 })
</script>

<template>
  <svg v-if="name === 'facebook'" :width="size" :height="size" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
    <path d="M13.5 21.95V14h2.66l.4-3.1H13.5V8.92c0-.9.25-1.5 1.54-1.5h1.64V4.65A22 22 0 0 0 14.29 4.5c-2.37 0-3.99 1.45-3.99 4.1v2.29H7.62V14h2.68v7.95A10 10 0 1 1 13.5 21.95z" />
  </svg>
  <svg v-else-if="name === 'instagram'" :width="size" :height="size" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" aria-hidden="true">
    <rect x="3" y="3" width="18" height="18" rx="5" /><circle cx="12" cy="12" r="4" /><circle cx="17.5" cy="6.5" r=".9" fill="currentColor" stroke="none" />
  </svg>
  <svg v-else :width="size" :height="size" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
    <path d="M12.04 2a9.9 9.9 0 0 0-8.5 15l-1.4 5.1 5.24-1.37A9.9 9.9 0 1 0 12.04 2zm0 18.1a8.2 8.2 0 0 1-4.18-1.15l-.3-.18-3.1.82.83-3.03-.2-.31a8.2 8.2 0 1 1 6.95 3.85zm4.5-6.14c-.25-.12-1.46-.72-1.69-.8-.23-.09-.39-.13-.56.12-.16.25-.64.8-.78.97-.15.16-.29.18-.54.06a6.7 6.7 0 0 1-1.98-1.22 7.4 7.4 0 0 1-1.37-1.71c-.14-.25 0-.38.11-.5l.37-.44c.13-.15.17-.25.25-.42.08-.16.04-.31-.02-.43-.06-.12-.56-1.35-.77-1.85-.2-.48-.4-.42-.56-.42h-.48a.92.92 0 0 0-.66.31 2.8 2.8 0 0 0-.87 2.07c0 1.22.89 2.4 1.01 2.57.12.16 1.75 2.67 4.24 3.74.59.26 1.05.41 1.41.52.6.19 1.13.16 1.55.1.48-.07 1.46-.6 1.67-1.17.2-.58.2-1.07.15-1.17-.06-.11-.23-.17-.48-.29z" />
  </svg>
</template>

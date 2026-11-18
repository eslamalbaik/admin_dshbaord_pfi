<script setup lang="ts">
/** صورة المحادث بنافذة المساعد: روبوت "سامي" أو الزائر */
defineProps<{ kind: 'bot' | 'user'; size?: number }>()
</script>

<template>
  <svg v-if="kind === 'bot'" :width="size ?? 32" :height="size ?? 32" viewBox="0 0 64 64" aria-hidden="true" class="asst-avatar">
    <circle cx="32" cy="32" r="30" fill="#e8f3ff" />
    <line x1="32" y1="10" x2="32" y2="17" stroke="#00006e" stroke-width="2.4" stroke-linecap="round" />
    <circle cx="32" cy="9" r="3" fill="#f5a623" />
    <rect x="14" y="17" width="36" height="28" rx="11" fill="#00006e" />
    <rect x="18.5" y="22" width="27" height="17" rx="8" fill="#a8e6ff" />
    <circle cx="26" cy="30" r="3.2" fill="#00006e" /><circle cx="38" cy="30" r="3.2" fill="#00006e" />
    <circle cx="27" cy="29" r="1" fill="#fff" /><circle cx="39" cy="29" r="1" fill="#fff" />
    <path d="M27.5 34.5q4.5 3 9 0" fill="none" stroke="#00006e" stroke-width="1.8" stroke-linecap="round" />
    <rect x="9" y="26" width="5" height="10" rx="2.5" fill="#f5a623" /><rect x="50" y="26" width="5" height="10" rx="2.5" fill="#f5a623" />
    <path d="M20 47h24l2 9a3 3 0 0 1-3 3H21a3 3 0 0 1-3-3z" fill="#00006e" />
    <circle cx="32" cy="52" r="2.6" fill="#f5a623" />
  </svg>
  <svg v-else :width="size ?? 32" :height="size ?? 32" viewBox="0 0 64 64" aria-hidden="true" class="asst-avatar">
    <defs><clipPath id="asst-user-clip"><circle cx="32" cy="32" r="30" /></clipPath></defs>
    <circle cx="32" cy="32" r="30" fill="#fff4e0" />
    <g clip-path="url(#asst-user-clip)">
      <path d="M8 64c0-14 10.7-23 24-23s24 9 24 23z" fill="#f5a623" />
      <path d="M26 41l6 8 6-8" fill="#fff" />
    </g>
    <circle cx="32" cy="26" r="11.5" fill="#c98a4b" />
    <path d="M20.6 24.5c.6-7 5.6-11 11.4-11s10.8 4 11.4 11c-3-2.6-7-3.8-11.4-3.8s-8.4 1.2-11.4 3.8z" fill="#2b2b3a" />
    <circle cx="32" cy="32" r="30" fill="none" stroke="#f5a623" stroke-width="2" />
  </svg>
</template>

<style scoped>
.asst-avatar { flex: none; display: block; border-radius: 50%; }
</style>

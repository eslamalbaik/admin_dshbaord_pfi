<script setup lang="ts">
import { committees } from '@/data/unionCommittees'
import { BadgeCheck, FileText, HeartHandshake, MessageSquare, Presentation, Receipt, Scale, Truck, Users } from 'lucide-vue-next'

// بنفس ترتيب اللجان في unionCommittees
const icons = [BadgeCheck, Scale, Presentation, Users, FileText, MessageSquare, HeartHandshake, Truck, Receipt]
</script>

<template>
  <div class="cm-grid">
    <article v-for="(c, i) in committees" :key="c.name" class="cm-card" :class="{ alt: i % 2 === 1 }">
      <div class="cm-top">
        <div class="cm-icon"><component :is="icons[i] ?? Users" :size="26" /></div>
        <h3 class="cm-name">{{ c.name }}</h3>
      </div>
      <p class="cm-desc">{{ c.desc }}</p>
      <div class="cm-foot">
        <span class="cm-chair">رئيس اللجنة: {{ c.chair }}</span>
      </div>
    </article>
  </div>
</template>

<style scoped>
.cm-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.5rem; }
.cm-card { --cc: #1a237e; --ct: #e8eef9; display: flex; flex-direction: column; background: linear-gradient(160deg, #fff 60%, #f7f9ff); border-radius: 18px; border-inline-start: 4px solid var(--cc); padding: 1.6rem 1.5rem 1.25rem; box-shadow: 0 4px 18px rgba(13,27,75,.08); transition: transform .25s, box-shadow .25s; }
.cm-card.alt { --cc: #e8790a; --ct: #fdf1e0; background: linear-gradient(160deg, #fff 60%, #fffaf2); }
.cm-card:hover { transform: translateY(-4px); box-shadow: 0 12px 28px rgba(13,27,75,.14); }
.cm-top { display: flex; align-items: center; gap: .9rem; margin-bottom: .9rem; }
.cm-icon { width: 56px; height: 56px; border-radius: 14px; background: var(--ct); color: var(--cc); display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.cm-name { margin: 0; font-family: 'Tajawal', 'Cairo', sans-serif; font-size: 1.08rem; font-weight: 800; color: #0d1b3e; line-height: 1.5; }
.cm-desc { margin: 0 0 1.1rem; font-size: .88rem; color: #4b5563; line-height: 1.85; }
.cm-foot { margin-top: auto; display: flex; align-items: center; justify-content: space-between; gap: .75rem; padding-top: .9rem; border-top: 1px solid #eef0f6; }
.cm-chair { font-size: .82rem; font-weight: 700; color: #0d1b3e; }
@media (max-width: 900px) { .cm-grid { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 640px) { .cm-grid { grid-template-columns: 1fr; } }
@media (prefers-reduced-motion: reduce) { .cm-card { transition: none; } .cm-card:hover { transform: none; } }
</style>

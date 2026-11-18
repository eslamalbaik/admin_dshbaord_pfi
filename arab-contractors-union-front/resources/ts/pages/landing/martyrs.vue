<script setup lang="ts">
import SocialPageHero from '@/components/SocialPageHero.vue'
import { Flame, CalendarDays, User, Quote } from 'lucide-vue-next'
import { martyrs, formatSocialDate } from '@/utils/socialData'

definePage({ meta: { layout: 'landing', public: true, unauthenticatedOnly: false } })
</script>

<template>
  <div dir="rtl" class="soc-page">
    <SocialPageHero
      title="شهداء المهنة"
      subtitle="وفاءً لمن أعطوا .. وإرثاً لمن سيبنون"
      :icon="Flame"
    />

    <div class="soc-wrap">
      <div class="intro-card">
        <Flame :size="38" class="intro-ico" />
        <p>
          نستذكر بكل فخر واعتزاز أبناءنا من العاملين في ميدان العمل، الذين قدموا أرواحهم وأعطوا
          أداء واجبهم في سبيل خدمة وطنهم.
        </p>
      </div>

      <h2 class="sec-title">أسماؤهم باقية في ذاكرتنا<span class="sec-rule" /></h2>

      <div class="grid">
        <article v-for="m in martyrs" :key="m.id" class="card">
          <div class="photo">
            <span class="corner" aria-hidden="true" />
            <User :size="92" :stroke-width="1" />
          </div>
          <h3>{{ m.name }}</h3>
          <span class="role">{{ m.title }}</span>
          <span class="date"><CalendarDays :size="14" />{{ formatSocialDate(m.date) }}</span>
        </article>
      </div>

      <div class="quote">
        <Quote :size="30" class="q" />
        <p>لن ننسى من صنعوا الفرق .. وتركوا بصمة في مسيرة الوطن</p>
      </div>
    </div>
  </div>
</template>

<style scoped>
.soc-page { font-family: 'Tajawal', sans-serif; direction: rtl; background: #f4f7fc; min-height: 100vh; color: #374151; padding-bottom: 4rem; }
.soc-wrap { max-width: 1050px; margin: 0 auto; padding: 1rem 1.25rem 0; }

.intro-card { display: flex; align-items: center; gap: 1.25rem; background: #fff; border-radius: 18px; padding: 1.5rem 1.75rem; box-shadow: 0 8px 30px rgba(26,35,126,.08); border: 1px solid #e8ecf6; line-height: 2; }
.intro-card p { margin: 0; }
.intro-ico { color: #f9a825; flex-shrink: 0; }

.sec-title { text-align: center; font-size: 1.5rem; font-weight: 800; color: #1a237e; margin: 2.5rem 0 1.75rem; }
.sec-rule { display: block; width: 56px; height: 3px; background: #f9a825; border-radius: 3px; margin: .6rem auto 0; }

.grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.25rem; }
.card { background: #fff; border-radius: 18px; padding: 1rem 1rem 1.25rem; border: 1px solid #e8ecf6; box-shadow: 0 4px 18px rgba(26,35,126,.05); text-align: center; display: flex; flex-direction: column; align-items: center; gap: .35rem; transition: transform .2s, box-shadow .2s; }
.card:hover { transform: translateY(-4px); box-shadow: 0 16px 36px rgba(26,35,126,.13); }
.photo { position: relative; width: 100%; height: 170px; border-radius: 14px; overflow: hidden; margin-bottom: .6rem; display: flex; align-items: flex-end; justify-content: center; color: #9aa3b8; background: linear-gradient(180deg, #eef1f8, #dfe4f0); }
.corner { position: absolute; top: 0; right: 0; width: 54px; height: 54px; background: #111; clip-path: polygon(0 0, 100% 0, 100% 100%); }
.card h3 { margin: 0; font-size: 1.05rem; font-weight: 800; color: #111827; }
.role { font-size: .82rem; color: #6b7280; }
.date { display: inline-flex; align-items: center; gap: .35rem; font-size: .8rem; color: #1a237e; margin-top: .25rem; }

.quote { margin-top: 2.75rem; position: relative; overflow: hidden; border-radius: 22px; padding: 2.5rem 2rem; text-align: center; color: #fff; background: linear-gradient(120deg, #0a1440 0%, #283593 100%); }
.quote::after { content: ''; position: absolute; width: 320px; height: 320px; border-radius: 50%; background: radial-gradient(circle, rgba(249,168,37,.22), transparent 65%); right: -80px; bottom: -120px; }
.quote .q { color: #f9a825; margin-bottom: .5rem; position: relative; }
.quote p { position: relative; margin: 0; font-size: 1.4rem; font-weight: 700; line-height: 1.8; }

@media (max-width: 900px) { .grid { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 560px) {
  .grid { grid-template-columns: 1fr; }
  .intro-card { flex-direction: column; text-align: center; }
}
</style>

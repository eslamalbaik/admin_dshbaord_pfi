<script setup lang="ts">
import SocialPageHero from '@/components/SocialPageHero.vue'
import { Flower2, CalendarDays, ChevronDown, HeartHandshake } from 'lucide-vue-next'
import { ref } from 'vue'
import { condolences, formatSocialDate } from '@/utils/socialData'

definePage({ meta: { layout: 'landing', public: true, unauthenticatedOnly: false } })

const openId = ref<number | null>(null)
const toggle = (id: number) => { openId.value = openId.value === id ? null : id }
</script>

<template>
  <div dir="rtl" class="soc-page">
    <SocialPageHero
      title="تعازي"
      subtitle="بقلوب مؤمنة بقضاء الله وقدره .. نتقدم بأحر التعازي"
      :icon="Flower2"
      accent="#ffffff"
    />

    <div class="soc-wrap">
      <div class="intro-card">
        <HeartHandshake :size="34" class="intro-ico" />
        <p>
          يتقدم اتحاد المقاولين بخالص العزاء والمواساة إلى أسر الزملاء والأهالي الكرام في وفاة فقيدهم،
          سائلين الله أن يتغمدهم بواسع رحمته ويلهم ذويهم الصبر والسلوان.
        </p>
      </div>

      <h2 class="sec-title">رسائل التعزية<span class="sec-rule" /></h2>

      <div class="list">
        <article v-for="c in condolences" :key="c.id" class="row" :class="{ open: openId === c.id }">
          <div class="ribbon" aria-hidden="true">
            <svg viewBox="0 0 48 64" width="40" height="54">
              <path d="M24 6c-9 0-15 6-15 13 0 12 15 22 15 22s15-10 15-22c0-7-6-13-15-13z" fill="none" stroke="currentColor" stroke-width="4" stroke-linejoin="round" />
              <path d="M16 36 8 58l9-5 5 8 4-18M32 36l8 22-9-5-5 8" fill="none" stroke="currentColor" stroke-width="4" stroke-linejoin="round" stroke-linecap="round" />
            </svg>
          </div>
          <div class="body">
            <h3>{{ c.title }}</h3>
            <span class="date"><CalendarDays :size="14" />{{ formatSocialDate(c.date) }}</span>
            <p class="ex" :class="{ clamp: openId !== c.id }">{{ c.excerpt }}</p>
            <button class="more" type="button" @click="toggle(c.id)">
              {{ openId === c.id ? 'إخفاء' : 'قراءة المزيد' }}
              <ChevronDown :size="15" class="chev" />
            </button>
          </div>
        </article>
      </div>

      <div class="verse">
        <p>«رحم الله من رحلوا .. وطيّب الله ثراهم»</p>
      </div>
    </div>
  </div>
</template>

<style scoped>
.soc-page { font-family: 'Tajawal', sans-serif; direction: rtl; background: #f4f7fc; min-height: 100vh; color: #374151; padding-bottom: 4rem; }
.soc-wrap { max-width: 900px; margin: 0 auto; padding: 1rem 1.25rem 0; }

.intro-card { display: flex; align-items: center; gap: 1.25rem; background: #fff; border-radius: 18px; padding: 1.5rem 1.75rem; box-shadow: 0 8px 30px rgba(26,35,126,.08); border: 1px solid #e8ecf6; line-height: 2; font-size: 1rem; }
.intro-card p { margin: 0; }
.intro-ico { color: #1a237e; flex-shrink: 0; }

.sec-title { position: relative; text-align: center; font-size: 1.5rem; font-weight: 800; color: #1a237e; margin: 2.5rem 0 1.75rem; }
.sec-rule { display: block; width: 56px; height: 3px; background: #f9a825; border-radius: 3px; margin: .6rem auto 0; }

.list { display: flex; flex-direction: column; gap: 1rem; }
.row { display: flex; gap: 1.25rem; background: #fff; border-radius: 18px; padding: 1.25rem 1.5rem; border: 1px solid #e8ecf6; box-shadow: 0 4px 18px rgba(26,35,126,.05); transition: transform .2s, box-shadow .2s; }
.row:hover { transform: translateY(-3px); box-shadow: 0 14px 34px rgba(26,35,126,.12); }
.ribbon { color: #1a237e; display: flex; align-items: flex-start; padding-top: .25rem; }
.body { flex: 1; min-width: 0; }
.body h3 { margin: 0 0 .35rem; font-size: 1.1rem; font-weight: 800; color: #111827; }
.date { display: inline-flex; align-items: center; gap: .35rem; font-size: .8rem; color: #6b7280; }
.ex { margin: .75rem 0 .6rem; line-height: 1.9; font-size: .93rem; color: #4b5563; }
.ex.clamp { display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.more { display: inline-flex; align-items: center; gap: .3rem; background: none; border: 0; padding: 0; cursor: pointer; font: inherit; font-size: .85rem; font-weight: 700; color: #1a237e; }
.more:hover { color: #f9a825; }
.chev { transition: transform .2s; }
.row.open .chev { transform: rotate(180deg); }

.verse { margin-top: 2.5rem; border-radius: 20px; padding: 2.25rem 1.5rem; text-align: center; color: #fff; font-size: 1.35rem; font-weight: 700; background: linear-gradient(120deg, #0a1440, #283593); position: relative; overflow: hidden; }
.verse::after { content: ''; position: absolute; width: 280px; height: 280px; border-radius: 50%; background: radial-gradient(circle, rgba(249,168,37,.25), transparent 65%); left: -60px; top: -80px; }
.verse p { position: relative; margin: 0; }

@media (max-width: 600px) {
  .intro-card { flex-direction: column; text-align: center; }
  .row { padding: 1rem; gap: .85rem; }
}
</style>

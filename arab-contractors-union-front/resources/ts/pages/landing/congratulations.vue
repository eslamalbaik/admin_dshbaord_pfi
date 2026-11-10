<script setup lang="ts">
import SocialPageHero from '@/components/SocialPageHero.vue'
import { Gift, CalendarDays, Star, Flag, Moon, PartyPopper } from 'lucide-vue-next'
import { computed, ref } from 'vue'
import { congratulations, congratulationCategories, formatSocialDate } from '@/utils/socialData'

definePage({ meta: { layout: 'landing', public: true, unauthenticatedOnly: false } })

const active = ref<string>('all')
const items = computed(() =>
  active.value === 'all' ? congratulations : congratulations.filter(i => i.category === active.value),
)

const catIcon = { national: Flag, religious: Moon, special: PartyPopper } as const
const catLabel = { national: 'وطنية', religious: 'دينية', special: 'خاصة' } as const
</script>

<template>
  <div dir="rtl" class="soc-page">
    <SocialPageHero
      title="تهاني"
      subtitle="مشاركتكم فرحتنا .. تزيدنا سعادة"
      :icon="Gift"
    />

    <div class="soc-wrap">
      <div class="intro-card">
        <Star :size="32" class="intro-ico" />
        <p>يتقدم اتحاد المقاولين بأطيب التهاني والتبريكات بمناسبة المناسبات المختلفة والنجاحات.</p>
      </div>

      <div class="chips">
        <button
          v-for="c in congratulationCategories"
          :key="c.key"
          type="button"
          class="chip"
          :class="{ on: active === c.key }"
          @click="active = c.key"
        >{{ c.label }}</button>
      </div>

      <div class="list">
        <article v-for="i in items" :key="i.id" class="card">
          <div class="thumb" :class="i.category">
            <component :is="catIcon[i.category]" :size="38" :stroke-width="1.4" />
          </div>
          <div class="body">
            <span class="tag" :class="i.category">{{ catLabel[i.category] }}</span>
            <h3>{{ i.title }}</h3>
            <p>{{ i.excerpt }}</p>
            <span class="date"><CalendarDays :size="14" />{{ formatSocialDate(i.date) }}</span>
          </div>
        </article>
        <p v-if="!items.length" class="empty">لا توجد تبريكات في هذا التصنيف.</p>
      </div>
    </div>
  </div>
</template>

<style scoped>
.soc-page { font-family: 'Tajawal', sans-serif; direction: rtl; background: #f4f7fc; min-height: 100vh; color: #374151; padding-bottom: 4rem; }
.soc-wrap { max-width: 900px; margin: 0 auto; padding: 1rem 1.25rem 0; }

.intro-card { display: flex; align-items: center; gap: 1.25rem; background: #fff; border-radius: 18px; padding: 1.35rem 1.75rem; box-shadow: 0 8px 30px rgba(26,35,126,.08); border: 1px solid #e8ecf6; line-height: 1.9; }
.intro-card p { margin: 0; }
.intro-ico { color: #f9a825; fill: #f9a825; flex-shrink: 0; }

.chips { display: flex; flex-wrap: wrap; gap: .65rem; margin: 1.75rem 0; }
.chip { border: 1px solid #d6dcee; background: #fff; color: #1a237e; border-radius: 999px; padding: .5rem 1.3rem; font: inherit; font-size: .9rem; font-weight: 700; cursor: pointer; transition: all .2s; }
.chip:hover { border-color: #1a237e; }
.chip.on { background: #1a237e; border-color: #1a237e; color: #fff; box-shadow: 0 6px 16px rgba(26,35,126,.3); }

.list { display: flex; flex-direction: column; gap: 1rem; }
.card { display: flex; gap: 1.25rem; align-items: center; background: #fff; border-radius: 18px; padding: 1rem 1.25rem; border: 1px solid #e8ecf6; box-shadow: 0 4px 18px rgba(26,35,126,.05); transition: transform .2s, box-shadow .2s; }
.card:hover { transform: translateY(-3px); box-shadow: 0 14px 34px rgba(26,35,126,.12); }
.thumb { width: 112px; height: 88px; border-radius: 14px; flex-shrink: 0; display: flex; align-items: center; justify-content: center; color: #fff; }
.thumb.national { background: linear-gradient(135deg, #1b5e20, #c62828); }
.thumb.religious { background: linear-gradient(135deg, #0a1440, #5c6bc0); }
.thumb.special { background: linear-gradient(135deg, #f9a825, #ef6c00); }
.body { flex: 1; min-width: 0; }
.tag { display: inline-block; font-size: .72rem; font-weight: 800; padding: .15rem .7rem; border-radius: 999px; margin-bottom: .4rem; }
.tag.national { background: #e8f5e9; color: #1b5e20; }
.tag.religious { background: #e8eaf6; color: #1a237e; }
.tag.special { background: #fff3e0; color: #e65100; }
.body h3 { margin: 0 0 .3rem; font-size: 1.1rem; font-weight: 800; color: #111827; }
.body p { margin: 0 0 .5rem; font-size: .92rem; color: #4b5563; line-height: 1.8; }
.date { display: inline-flex; align-items: center; gap: .35rem; font-size: .8rem; color: #6b7280; }
.empty { text-align: center; color: #9ca3af; padding: 3rem 0; }

@media (max-width: 600px) {
  .card { flex-direction: column; align-items: stretch; }
  .thumb { width: 100%; height: 110px; }
}
</style>

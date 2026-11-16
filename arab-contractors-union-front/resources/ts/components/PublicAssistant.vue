<script setup lang="ts">
import { computed, nextTick, ref } from 'vue'
import api from '@/plugins/axios'
import { RefreshCw, SendHorizontal, X } from 'lucide-vue-next'

/**
 * المساعد الافتراضي (نسخة تجريبية): بيجاوب من "الأسئلة الشائعة" المخزّنة بلوحة التحكم (GET /api/v1/faqs)
 * ببحث نصي بسيط على الكلمات (بدون ذكاء اصطناعي خارجي). لو ما لقى جواب قريب بيقترح أقرب الأسئلة وبيحوّل لصفحة التواصل.
 */
interface Faq { id: number; question: string; answer: string }
interface PageLink { label: string; to: string }
interface Msg { id: number; from: 'bot' | 'user'; text: string; faqs?: Faq[]; link?: boolean; links?: PageLink[] }

const GREETING = 'مرحباً! كيف يمكنني مساعدتك اليوم؟'

const open = ref(false)
const input = ref('')
const typing = ref(false)
const faqs = ref<Faq[]>([])
const loaded = ref(false)
const failed = ref(false)
const messages = ref<Msg[]>([{ id: 1, from: 'bot', text: GREETING }])
const bodyEl = ref<HTMLElement | null>(null)
let seq = 1

const suggestions = computed(() => faqs.value.slice(0, 4))

async function loadFaqs() {
  if (loaded.value) return
  loaded.value = true
  try {
    const r = await api.get('/api/v1/faqs')
    faqs.value = Array.isArray(r.data) ? r.data : []
  }
  catch { failed.value = true; loaded.value = false }
}

function toggle() {
  open.value = !open.value
  if (open.value) { loadFaqs(); scrollDown() }
}

function reset() {
  lastBotAskedMore = false
  seq = 1
  messages.value = [{ id: 1, from: 'bot', text: GREETING }]
  typing.value = false
  input.value = ''
}

async function scrollDown() {
  await nextTick()
  bodyEl.value?.scrollTo({ top: bodyEl.value.scrollHeight, behavior: 'smooth' })
}

function push(m: Omit<Msg, 'id'>) {
  messages.value.push({ id: ++seq, ...m })
  scrollDown()
}

// ── مطابقة عربية بسيطة: توحيد الحروف، حذف التشكيل و"ال"، وتجاهل كلمات الوصل ──
const STOP = new Set(['ما', 'ماذا', 'هل', 'كيف', 'متى', 'اين', 'في', 'من', 'الى', 'على', 'عن', 'هو', 'هي', 'هذا', 'هذه', 'ان', 'او', 'و', 'لا', 'نعم', 'انا', 'اريد', 'ابغى', 'بدي', 'عايز', 'ممكن', 'يمكن', 'يمكنني', 'لو', 'كم', 'ذلك', 'التي', 'الذي', 'لدي', 'عندي', 'مع', 'بعد', 'قبل', 'اذا', 'ايضا'])

function norm(t: string) {
  return t
    .replace(/[ً-ٰٟـ]/g, '')
    .replace(/[إأآ]/g, 'ا')
    .replace(/ى/g, 'ي')
    .replace(/ة/g, 'ه')
    .replace(/[؟?!.,،؛:()"«»]/g, ' ')
    .toLowerCase()
}

function tokens(t: string) {
  return norm(t)
    .split(/\s+/)
    .map(w => w.replace(/^(وال|بال|لل|ال)/, '').replace(/^[وبل](?=.{4,})/, ''))
    .filter(w => w.length > 1 && !STOP.has(w))
}

function score(q: string[], f: Faq) {
  const fq = norm(f.question)
  const fa = norm(f.answer)
  let s = 0
  for (const w of q) {
    if (fq.includes(w)) s += 3
    else if (fa.includes(w)) s += 1
  }

  return s
}

// ── محادثة بسيطة: تحية، شكر، وداع، تعريف بالمساعد، بيانات التواصل، وتوجيه لصفحات الموقع ──
const CONTACT_LINK: PageLink = { label: 'تواصل معنا', to: '/landing/contact-us' }
const PAGES: { words: string[]; text: string; link: PageLink }[] = [
  { words: ['عطاء', 'عطاءات', 'مناقصه', 'مناقصات'], text: 'يمكنك تصفّح العطاءات والمناقصات المطروحة ومتابعة مواعيد إغلاقها من بوابة العطاءات.', link: { label: 'بوابة العطاءات', to: '/landing/public-tenders' } },
  { words: ['تدريب', 'دوره', 'دورات', 'ورشه', 'ورش'], text: 'تجد البرامج والدورات التدريبية للاتحاد في صفحة التدريب وبناء القدرات.', link: { label: 'التدريب وبناء القدرات', to: '/landing/training-center' } },
  { words: ['تحقق', 'التحقق', 'صحه', 'صلاحيه'], text: 'للتحقق من صحة شهادة صادرة عن الاتحاد استخدم صفحة التحقق من الشهادة.', link: { label: 'التحقق من شهادة', to: '/landing/verify-certificate' } },
  { words: ['دليل', 'شركات', 'مقاول', 'مقاولين', 'مقاولات'], text: 'يمكنك البحث عن الشركات المسجلة والمصنفة لدى الاتحاد في دليل شركات المقاولات.', link: { label: 'دليل شركات المقاولات', to: '/landing/members' } },
  { words: ['خدمات', 'خدمه'], text: 'تجد خدمات الاتحاد (العضوية، التصنيف، العطاءات، التدريب وغيرها) في صفحة الخدمات الإلكترونية.', link: { label: 'الخدمات الإلكترونية', to: '/landing/services' } },
  { words: ['اخبار', 'خبر', 'تعميم', 'تعاميم', 'اعلان', 'اعلانات'], text: 'آخر أخبار الاتحاد وتعاميمه في المركز الإعلامي.', link: { label: 'الأخبار', to: '/landing/news' } },
  { words: ['دخول', 'تسجيل الدخول', 'حسابي', 'بوابه'], text: 'يمكن للأعضاء تسجيل الدخول إلى بوابة المقاولين لمتابعة العضوية والطلبات والشهادات.', link: { label: 'تسجيل الدخول', to: '/contractor/login' } },
]

function words(text: string) {
  return norm(text).split(/\s+/).filter(Boolean)
}

/** كلمة كاملة أو عبارة (بعد التوحيد) موجودة بالنص */
function has(text: string, list: string[]) {
  const t = ` ${words(text).join(' ')} `

  return list.some(x => t.includes(` ${norm(x)} `))
}

let lastBotAskedMore = false

function smallTalk(text: string): boolean {
  const n = words(text)
  const short = n.length <= 6
  const asked = lastBotAskedMore
  lastBotAskedMore = false

  if (short && has(text, ['شكرا', 'شكرا لك', 'مشكور', 'يعطيك العافيه', 'تسلم', 'يسلمو', 'ممنون']))
    return lastBotAskedMore = true, push({ from: 'bot', text: 'العفو! هل تريد معرفة شيء آخر عن الاتحاد؟' }), true

  if (short && has(text, ['مع السلامه', 'وداعا', 'باي', 'الى اللقاء', 'تصبح على خير', 'سلام']) && !has(text, ['السلام عليكم']))
    return push({ from: 'bot', text: 'مع السلامة! يسعدنا خدمتك دائماً، ولا تتردد بالعودة متى أحببت.' }), true

  if (short && has(text, ['السلام عليكم', 'مرحبا', 'اهلا', 'هلا', 'هاي', 'صباح الخير', 'مساء الخير', 'اهلين', 'يسعد صباحك', 'يسعد مساك']))
    return push({ from: 'bot', text: has(text, ['السلام عليكم']) ? 'وعليكم السلام ورحمة الله! أنا المساعد الافتراضي لاتحاد المقاولين الفلسطينيين، كيف أستطيع مساعدتك؟' : 'أهلاً بك! أنا المساعد الافتراضي لاتحاد المقاولين الفلسطينيين، تفضّل بسؤالك عن العضوية أو التصنيف أو الخدمات.', faqs: suggestions.value }), true

  if (has(text, ['من انت', 'مين انت', 'شو انت', 'ما انت', 'ما اسمك', 'شو اسمك', 'ماذا تفعل', 'شو بتعمل', 'شو بتقدر', 'ماذا تستطيع', 'كيف تساعدني', 'ساعدني', 'مساعده', 'مساعدة']))
    return push({ from: 'bot', text: 'أنا المساعد الافتراضي (نسخة تجريبية) لاتحاد المقاولين الفلسطينيين. أجيب عن أسئلتك حول العضوية والتصنيف والخدمات والعطاءات، وأدلّك على الصفحة المناسبة في الموقع. جرّب أحد هذه الأسئلة:', faqs: suggestions.value }), true

  if (has(text, ['رقمكم', 'رقم الهاتف', 'رقم الجوال', 'هاتف', 'تلفون', 'جوال', 'موبايل', 'اتصال', 'اتصل', 'ايميل', 'بريد', 'عنوانكم', 'وين', 'اين', 'مقر', 'مكانكم', 'دوام', 'ساعات', 'مواعيد', 'تواصل', 'واتساب', 'واتس']))
    return push({
      from: 'bot',
      text: 'بيانات التواصل مع الاتحاد:\nمقر غزة: الرمال\nالجوال: +970597233422\nالبريد: info@pcu.org.ps\nفرع رام الله (الضفة الغربية): شارع الإرسال، البريد: info@pcu.ps\nأوقات الدوام: الأحد - الخميس، 8 ص - 4 م',
      links: [CONTACT_LINK],
    }), true

  // رد قصير على سؤال "هل تريد شيئاً آخر؟"
  if (asked && n.length <= 3 && has(text, ['نعم', 'ايوه', 'اه', 'ايه', 'اكيد', 'طبعا']))
    return push({ from: 'bot', text: 'تفضّل، اكتب سؤالك أو اختر أحد الأسئلة الشائعة:', faqs: suggestions.value }), true

  if (asked && n.length <= 3 && has(text, ['لا', 'كلا', 'لا شكرا', 'يكفي']))
    return push({ from: 'bot', text: 'حسناً، أنا هنا إن احتجت أي مساعدة أخرى.' }), true

  return false
}

/** سؤال عن موضوع له صفحة بالموقع (عطاءات، تدريب...) وما لقينا له جواب بالأسئلة الشائعة */
function pageHint(text: string): boolean {
  const hit = PAGES.find(pg => has(text, pg.words))
  if (!hit) return false
  push({ from: 'bot', text: hit.text, links: [hit.link] })

  return true
}

function answer(text: string) {
  const q = tokens(text)
  const ranked = faqs.value
    .map(f => ({ f, s: q.length ? score(q, f) : 0 }))
    .sort((a, b) => b.s - a.s)

  const best = ranked[0]
  if (best && best.s >= 3)
    return push({ from: 'bot', text: best.f.answer })

  if (pageHint(text)) return

  const related = ranked.filter(r => r.s > 0).slice(0, 3).map(r => r.f)
  push({
    from: 'bot',
    text: related.length
      ? 'لم أجد إجابة مطابقة تماماً، هل تقصد أحد هذه الأسئلة؟'
      : 'عذراً، لم أجد إجابة لسؤالك. يمكنك اختيار أحد الأسئلة الشائعة أو التواصل مع فريق الاتحاد مباشرة.',
    faqs: related.length ? related : suggestions.value,
    link: true,
  })
}

/** جواب الذكاء الاصطناعي من الخادم (Gemini)؛ null لو مش متاح أو فشل، فنجاوب محلياً من الأسئلة الشائعة */
async function askAi(text: string, history: { role: 'user' | 'bot'; text: string }[]) {
  try {
    const r = await api.post('/api/v1/assistant/chat', { message: text, history }, { timeout: 15000 })

    return r.data?.available && r.data.reply ? String(r.data.reply) : null
  }
  catch { return null }
}

/** تطابق قوي مع سؤال شائع: نجاوب منه مباشرة بدون استهلاك حصة الذكاء الاصطناعي */
function strongFaq(text: string) {
  const q = tokens(text)
  if (!q.length) return null
  const best = faqs.value.map(f => ({ f, s: score(q, f) })).sort((a, b) => b.s - a.s)[0]

  return best && best.s >= 6 ? best.f : null
}

async function send(text = input.value) {
  const t = text.trim()
  if (!t || typing.value) return
  input.value = ''
  // آخر رسائل قبل هذا السؤال (للسياق)
  const history = messages.value.slice(-6).map(m => ({ role: m.from, text: m.text.slice(0, 600) }))
  push({ from: 'user', text: t })
  typing.value = true
  await loadFaqs()

  if (smallTalk(t)) { setTimeout(() => { typing.value = false }, 400); return }

  const strong = strongFaq(t)
  if (strong) { setTimeout(() => { push({ from: 'bot', text: strong.answer }); typing.value = false }, 400); return }

  const ai = await askAi(t, history)
  if (ai) push({ from: 'bot', text: ai })
  else answer(t)
  typing.value = false
}

function ask(f: Faq) {
  if (typing.value) return
  push({ from: 'user', text: f.question })
  typing.value = true
  setTimeout(() => { push({ from: 'bot', text: f.answer }); typing.value = false }, 350)
}
</script>

<template>
  <div class="asst" dir="rtl">
    <Transition name="asst-pop">
      <section v-if="open" class="asst-panel" role="dialog" aria-label="المساعد الافتراضي">
        <header class="asst-head">
          <button type="button" class="asst-icon-btn" aria-label="إغلاق" @click="toggle"><X :size="18" /></button>
          <button type="button" class="asst-icon-btn" aria-label="محادثة جديدة" title="محادثة جديدة" @click="reset"><RefreshCw :size="16" /></button>
          <h3 class="asst-title">المساعد الافتراضي : نسخة تجريبية</h3>
        </header>

        <div ref="bodyEl" class="asst-body">
          <template v-for="m in messages" :key="m.id">
            <div class="asst-msg" :class="m.from === 'user' ? 'is-user' : 'is-bot'">
              <p>{{ m.text }}</p>
            </div>
            <div v-if="m.faqs?.length" class="asst-chips">
              <button v-for="f in m.faqs" :key="f.id" type="button" class="asst-chip" @click="ask(f)">{{ f.question }}</button>
            </div>
            <RouterLink v-if="m.link" to="/landing/contact-us" class="asst-contact" @click="open = false">تواصل معنا</RouterLink>
            <div v-if="m.links?.length" class="asst-links">
              <RouterLink v-for="l in m.links" :key="l.to" :to="l.to" class="asst-contact" @click="open = false">{{ l.label }}</RouterLink>
            </div>
          </template>

          <div v-if="messages.length === 1 && suggestions.length" class="asst-chips">
            <button v-for="f in suggestions" :key="f.id" type="button" class="asst-chip" @click="ask(f)">{{ f.question }}</button>
          </div>
          <p v-if="failed && messages.length === 1" class="asst-note">تعذّر تحميل الأسئلة حالياً، يمكنك كتابة سؤالك أو المحاولة لاحقاً.</p>

          <div v-if="typing" class="asst-msg is-bot asst-typing" aria-label="يكتب"><i /><i /><i /></div>
        </div>

        <form class="asst-form" @submit.prevent="send()">
          <input v-model="input" type="text" placeholder="ادخل سؤالك" maxlength="300" aria-label="سؤالك">
          <button type="submit" class="asst-send" :disabled="!input.trim() || typing" aria-label="إرسال"><SendHorizontal :size="18" /></button>
        </form>
        <p class="asst-foot">يجيب من الأسئلة الشائعة للاتحاد · نسخة تجريبية</p>
      </section>
    </Transition>

    <button type="button" class="asst-fab" :class="{ 'is-open': open }" :aria-label="open ? 'إغلاق المساعد' : 'فتح المساعد الافتراضي'" :aria-expanded="open" @click="toggle">
      <span v-if="!open" class="asst-fab-ring" />
      <svg v-if="!open" class="asst-robot" viewBox="0 0 64 64" aria-hidden="true">
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
      <X v-else :size="26" />
    </button>
  </div>
</template>

<style scoped>
.asst { position: fixed; right: 20px; bottom: 20px; z-index: 9000; font-family: 'Tajawal', 'Cairo', sans-serif; }
.asst-fab { position: relative; display: grid; place-items: center; width: 68px; height: 68px; border-radius: 50%; border: 3px solid #fff; padding: 0; cursor: pointer; background: #fff; box-shadow: 0 10px 28px rgba(0, 0, 110, .35); transition: transform .2s, box-shadow .2s; }
.asst-fab:hover { transform: translateY(-3px) scale(1.04); box-shadow: 0 14px 34px rgba(0, 0, 110, .42); }
.asst-fab.is-open { width: 52px; height: 52px; background: #00006e; color: #fff; border-color: #00006e; }
.asst-fab-ring { position: absolute; inset: -7px; border-radius: 50%; border: 2px solid rgba(0, 0, 110, .35); animation: asst-ring 2.4s ease-out infinite; pointer-events: none; }
@keyframes asst-ring { 0% { transform: scale(.9); opacity: .9; } 100% { transform: scale(1.25); opacity: 0; } }
.asst-robot { width: 100%; height: 100%; border-radius: 50%; }

.asst-panel { position: absolute; right: 0; bottom: 84px; width: min(370px, calc(100vw - 32px)); height: min(540px, calc(100vh - 130px)); display: flex; flex-direction: column; background: #fff; border-radius: 20px; overflow: hidden; box-shadow: 0 24px 60px rgba(0, 0, 60, .32); }
.asst-head { display: flex; align-items: center; gap: .5rem; padding: .9rem 1rem; background: linear-gradient(135deg, #00004f, #00006e 60%, #1a1a8f); color: #fff; }
.asst-title { flex: 1; margin: 0; text-align: start; font-size: .98rem; font-weight: 800; color: #fff !important; }
.asst-icon-btn { display: grid; place-items: center; width: 30px; height: 30px; border-radius: 50%; border: 0; background: rgba(255, 255, 255, .14); color: #fff; cursor: pointer; transition: background .2s; }
.asst-icon-btn:hover { background: rgba(255, 255, 255, .28); }

.asst-body { flex: 1; overflow-y: auto; padding: 1rem; display: flex; flex-direction: column; gap: .7rem; background: #f6f8fc; }
.asst-msg { max-width: 86%; padding: .65rem .9rem; border-radius: 14px; font-size: .92rem; line-height: 1.85; white-space: pre-line; animation: asst-in .3s ease both; }
.asst-msg p { margin: 0; }
.asst-msg.is-bot { align-self: flex-start; background: #fff; color: #1f2a44; border: 1px solid #e3e7f1; border-top-right-radius: 4px; }
.asst-msg.is-user { align-self: flex-end; background: #00006e; color: #fff; border-top-left-radius: 4px; }
@keyframes asst-in { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: none; } }
.asst-chips { display: flex; flex-direction: column; gap: .4rem; align-self: stretch; }
.asst-chip { text-align: start; border: 1.5px solid #c5cae9; background: #fff; color: #00006e; border-radius: 12px; padding: .5rem .8rem; font-family: inherit; font-size: .85rem; font-weight: 700; line-height: 1.6; cursor: pointer; transition: background .2s, border-color .2s; }
.asst-chip:hover { background: #eef0fb; border-color: #00006e; }
.asst-contact { align-self: flex-start; background: #f5a623; color: #1b1300; border-radius: 999px; padding: .4rem 1.1rem; font-size: .85rem; font-weight: 800; text-decoration: none; }
.asst-links { display: flex; flex-wrap: wrap; gap: .4rem; align-self: flex-start; }
.asst-note { font-size: .78rem; color: #9aa3b5; text-align: center; margin: 0; }
.asst-typing { display: inline-flex; gap: 5px; padding: .8rem 1rem; }
.asst-typing i { width: 7px; height: 7px; border-radius: 50%; background: #9aa3b5; animation: asst-dot 1s ease-in-out infinite; }
.asst-typing i:nth-child(2) { animation-delay: .15s; }
.asst-typing i:nth-child(3) { animation-delay: .3s; }
@keyframes asst-dot { 0%, 80%, 100% { transform: scale(.7); opacity: .5; } 40% { transform: scale(1.1); opacity: 1; } }

.asst-form { display: flex; align-items: center; gap: .5rem; margin: .6rem .8rem .2rem; padding: .35rem .5rem .35rem .4rem; border: 1.5px solid #e0e4ef; border-radius: 14px; background: #fff; }
.asst-form:focus-within { border-color: #00006e; }
.asst-form input { flex: 1; min-width: 0; border: 0; outline: 0; background: transparent; font-family: inherit; font-size: .92rem; padding: .45rem .5rem; color: #1f2a44; }
.asst-send { display: grid; place-items: center; width: 36px; height: 36px; border-radius: 10px; border: 0; background: #f5a623; color: #1b1300; cursor: pointer; transition: opacity .2s, transform .2s; }
.asst-send svg { transform: scaleX(-1); }
.asst-send:disabled { opacity: .4; cursor: not-allowed; }
.asst-send:not(:disabled):hover { transform: scale(1.06); }
.asst-foot { margin: 0; padding: .35rem 1rem .7rem; text-align: center; font-size: .72rem; color: #9aa3b5; }

.asst-pop-enter-active, .asst-pop-leave-active { transition: opacity .22s ease, transform .22s ease; transform-origin: bottom right; }
.asst-pop-enter-from, .asst-pop-leave-to { opacity: 0; transform: translateY(12px) scale(.96); }
@media (max-width: 560px) {
  .asst { right: 12px; bottom: 12px; }
  .asst-panel { bottom: 76px; }
}
@media (prefers-reduced-motion: reduce) {
  .asst-fab-ring, .asst-typing i { animation: none; }
  .asst-pop-enter-active, .asst-pop-leave-active, .asst-msg { transition: none; animation: none; }
}
</style>

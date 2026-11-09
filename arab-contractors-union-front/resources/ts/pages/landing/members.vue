<script setup lang="ts">
import PublicSocialFollow from '@/components/PublicSocialFollow.vue'
import api from '@/plugins/axios'
import { ref, computed, watch, onMounted, onBeforeUnmount } from 'vue'
import { Search, Award, Filter, ChevronLeft, ChevronUp, Building2, X, Hash, CalendarDays, MapPin, Layers, CircleCheck, HardHat, Route, Droplets, Zap, Wrench, Factory } from 'lucide-vue-next'

definePage({ meta: { layout: 'landing', public: true, unauthenticatedOnly: false } })

interface FieldOpt { value: number; label: string; specializations: { value: number; label: string }[] }
interface Member {
  name: string
  membership_number: string
  city: string | null
  logo_url: string | null
  member_since: number | null
  membership_status: 'active' | 'expired' | 'suspended'
  status_label: string
  fields: { name: string; grades: string[]; specializations?: { name: string; grade: string | null }[] }[]
  specializations: { name: string; grade: string | null }[]
}

const PER_PAGE = 24

const searchQ = ref('')
const filterStatus = ref('')
const filterField = ref('')
const filterSpec = ref('')
const filterGrade = ref('')

const fieldOptions = ref<FieldOpt[]>([])
const gradeOptions = ref<{ value: string; label: string }[]>([])
const statusOptions = ref<{ value: string; label: string }[]>([
  { value: 'active', label: 'فعّالة' },
  { value: 'expired', label: 'منتهية' },
  { value: 'suspended', label: 'موقوفة' },
])

const members = ref<Member[]>([])
const total = ref(0)
const page = ref(1)
const lastPage = ref(1)
const loading = ref(false)
let requestId = 0
let timer: ReturnType<typeof setTimeout> | undefined

// الاختصاصات المتاحة: تابعة للمجال المختار، أو كلها (بدون تكرار) إن لم يُختر مجال
const specOptions = computed(() => {
  const src = filterField.value
    ? fieldOptions.value.filter(f => String(f.value) === filterField.value)
    : fieldOptions.value
  const seen = new Map<number, string>()
  src.forEach(f => f.specializations.forEach(s => seen.set(s.value, s.label)))
  return [...seen].map(([value, label]) => ({ value, label }))
})

const hasFilters = computed(() =>
  !!(searchQ.value || filterStatus.value || filterField.value || filterSpec.value || filterGrade.value))

const statusClass = (s: string) => (s === 'active' ? 'active' : s === 'suspended' ? 'suspended' : 'expired')

const cardGrades = (m: Member) => [...new Set(m.fields.flatMap(f => f.grades))]

interface FieldGroup { name: string; grades: string[]; specs: { name: string; grade: string | null }[] }

// الاختصاصات فرع من المجالات: نجمّع كل اختصاص تحت مجاله.
// إن رجّع الـ API الاختصاصات داخل المجال نستخدمها، وإلا نطابقها مع شجرة المجالات من /filters.
function groupFields(m: Member): FieldGroup[] {
  if (m.fields.some(f => Array.isArray(f.specializations)))
    return m.fields.map(f => ({ name: f.name, grades: f.grades, specs: f.specializations ?? [] }))

  const left = [...m.specializations]
  const groups: FieldGroup[] = m.fields.map(f => {
    const opt = fieldOptions.value.find(o => o.label === f.name)
    const labels = new Set(opt?.specializations.map(s => s.label) ?? [])
    const specs: FieldGroup['specs'] = []

    for (let i = 0; i < left.length;) {
      if (labels.has(left[i].name))
        specs.push(...left.splice(i, 1))
      else
        i++
    }

    return { name: f.name, grades: f.grades, specs }
  })

  if (left.length) {
    if (groups.length === 1)
      groups[0].specs.push(...left)
    else
      groups.push({ name: 'اختصاصات أخرى', grades: [], specs: left })
  }

  return groups
}

// نافذة بيانات تصنيف الشركة — من نفس بيانات الدليل العام، بدون أي طلب إضافي
const selected = ref<Member | null>(null)
// أيقونة لكل مجال حسب اسمه (أبنية، طرق، مياه، كهرباء...) والباقي مفتاح
function fieldIcon(name: string) {
  if (/أبني|ابني|مبان|إنشاء|انشاء/.test(name)) return HardHat
  if (/طرق|مواصلات|جسور/.test(name)) return Route
  if (/مياه|صرف|ري/.test(name)) return Droplets
  if (/كهرب|طاقة/.test(name)) return Zap
  if (/صناع|ميكانيك/.test(name)) return Factory

  return Wrench
}

const selectedGroups = computed(() => (selected.value ? groupFields(selected.value) : []))

function openMember(m: Member) {
  selected.value = m
  document.body.style.overflow = 'hidden'
}

function closeMember() {
  selected.value = null
  document.body.style.overflow = ''
}

function onKey(e: KeyboardEvent) {
  if (e.key === 'Escape' && selected.value) closeMember()
}

async function load(p = 1) {
  const id = ++requestId
  loading.value = true
  try {
    const r = await api.get('/api/v1/contractors-directory', {
      params: {
        search: searchQ.value.trim() || undefined,
        status: filterStatus.value || undefined,
        field: filterField.value || undefined,
        specialization: filterSpec.value || undefined,
        grade: filterGrade.value || undefined,
        page: p,
        per_page: PER_PAGE,
      },
    })
    if (id !== requestId) return
    const items: Member[] = r.data.items ?? []
    members.value = p === 1 ? items : [...members.value, ...items]
    total.value = r.data.meta?.total ?? items.length
    page.value = r.data.meta?.current_page ?? p
    lastPage.value = r.data.meta?.last_page ?? p
  }
  catch {
    if (id === requestId) { members.value = []; total.value = 0; lastPage.value = 1 }
  }
  finally {
    if (id === requestId) loading.value = false
  }
}

function reset() {
  searchQ.value = ''; filterStatus.value = ''; filterField.value = ''; filterSpec.value = ''; filterGrade.value = ''
}

// تغيير المجال يصفّر الاختصاص إن لم يعد ضمنه
watch(filterField, () => {
  if (filterSpec.value && !specOptions.value.some(s => String(s.value) === filterSpec.value)) filterSpec.value = ''
})
watch([filterStatus, filterField, filterSpec, filterGrade], () => load(1))
watch(searchQ, () => { clearTimeout(timer); timer = setTimeout(() => load(1), 350) })

onBeforeUnmount(() => {
  window.removeEventListener('keydown', onKey)
  document.body.style.overflow = ''
})

onMounted(async () => {
  window.addEventListener('keydown', onKey)
  load(1)
  try {
    const r = await api.get('/api/v1/contractors-directory/filters')
    fieldOptions.value = r.data.items?.fields ?? []
    gradeOptions.value = r.data.items?.grades ?? []
    if (r.data.items?.statuses?.length) statusOptions.value = r.data.items.statuses
  }
  catch { /* الفلاتر اختيارية — القائمة تعمل بدونها */ }
})
</script>

<template>
  <div dir="rtl" class="pub-page">

    <div class="page-hero">
      <div class="page-hero-shapes"><div class="ph-s ph-s1" /><div class="ph-s ph-s2" /></div>
      <div class="pub-cont page-hero-inner">
        <div class="page-breadcrumb">
          <RouterLink to="/landing">الرئيسية</RouterLink><span>/</span><span>أعضاء الاتحاد</span>
        </div>
        <h1 class="page-hero-title">أعضاء الاتحاد</h1>
        <p class="page-hero-desc">دليل شركات المقاولات الأعضاء في اتحاد المقاولين — يمكنك البحث والتصفية حسب الحالة والاختصاص والدرجة</p>
      </div>
      <div class="pub-follow-slot"><PublicSocialFollow /></div>
    </div>

    <section class="pub-section">
      <div class="pub-cont">
        <div class="filters-bar">
          <div class="search-wrap">
            <Search :size="17" class="search-ico" />
            <input v-model="searchQ" type="text" placeholder="ابحث باسم الشركة أو رقم العضوية..." class="search-input" />
          </div>
          <div class="filters-row">
            <select v-model="filterStatus" class="filter-sel">
              <option value="">كل الحالات</option>
              <option v-for="s in statusOptions" :key="s.value" :value="s.value">{{ s.label }}</option>
            </select>
            <select v-model="filterField" class="filter-sel">
              <option value="">كل المجالات</option>
              <option v-for="f in fieldOptions" :key="f.value" :value="String(f.value)">{{ f.label }}</option>
            </select>
            <select v-model="filterSpec" class="filter-sel">
              <option value="">كل الاختصاصات</option>
              <option v-for="s in specOptions" :key="s.value" :value="String(s.value)">{{ s.label }}</option>
            </select>
            <select v-model="filterGrade" class="filter-sel">
              <option value="">كل الدرجات</option>
              <option v-for="g in gradeOptions" :key="g.value" :value="g.value">{{ g.label }}</option>
            </select>
            <button class="filter-reset" @click="reset">
              <Filter :size="14" /> إعادة تعيين
            </button>
          </div>
        </div>

        <p class="results-count">عُثر على <strong>{{ total }}</strong> شركة</p>

        <div class="members-grid">
          <button
            v-for="m in members"
            :key="m.membership_number"
            type="button"
            class="member-card"
            :aria-label="`عرض بيانات تصنيف ${m.name}`"
            @click="openMember(m)"
          >
            <span class="member-icon" aria-hidden="true"><Building2 :size="24" :stroke-width="1.8" /></span>
            <span class="member-body">
              <span class="member-name" :title="m.name">{{ m.name }}</span>
              <span class="member-num">رقم العضوية: {{ m.membership_number }}</span>
              <span v-if="m.specializations.length" class="member-specs">
                <span v-for="(s, i) in m.specializations.slice(0, 3)" :key="i" class="member-spec" :title="s.name">{{ s.name }}</span>
                <span v-if="m.specializations.length > 3" class="member-spec more">+{{ m.specializations.length - 3 }}</span>
              </span>
              <span class="member-footer">
                <span v-for="g in cardGrades(m).slice(0, 2)" :key="g" class="member-class">
                  <Award :size="11" /> {{ g }}
                </span>
                <span class="member-status" :class="statusClass(m.membership_status)">{{ m.status_label }}</span>
                <span v-if="m.member_since" class="member-since">منذ {{ m.member_since }}</span>
              </span>
              <span class="member-more">عرض بيانات التصنيف <ChevronLeft :size="14" /></span>
            </span>
          </button>
        </div>

        <div v-if="loading && !members.length" class="empty-state"><p>جارٍ التحميل...</p></div>

        <div v-if="!loading && !members.length" class="empty-state">
          <Search :size="48" />
          <p>لا توجد نتائج مطابقة للبحث</p>
          <button v-if="hasFilters" @click="reset">إعادة تعيين الفلاتر</button>
        </div>

        <div v-if="page < lastPage" class="load-more-wrap">
          <button class="load-more" :disabled="loading" @click="load(page + 1)">
            {{ loading ? 'جارٍ التحميل...' : 'عرض المزيد' }}
          </button>
        </div>
      </div>
    </section>

    <Teleport to="body">
      <Transition name="mm-fade">
        <div v-if="selected" class="mm-overlay" dir="rtl" @click.self="closeMember">
          <div class="mm-dialog" role="dialog" aria-modal="true" :aria-label="`بيانات تصنيف ${selected.name}`">
            <div class="mm-head">
              <div class="mm-head-photo" aria-hidden="true" />
              <svg class="mm-head-swoosh" viewBox="0 0 400 60" preserveAspectRatio="none" aria-hidden="true">
                <path d="M0 60 L0 18 C 90 30, 190 46, 400 60 Z" fill="#f9a825" />
                <path d="M0 60 L0 30 C 110 40, 230 52, 400 60 Z" fill="#fbc02d" opacity=".55" />
              </svg>
              <span class="mm-icon" aria-hidden="true"><Building2 :size="34" :stroke-width="1.6" /></span>
              <div class="mm-head-text">
                <h2 class="mm-title">{{ selected.name }}</h2>
                <span class="mm-status" :class="statusClass(selected.membership_status)">
                  <CircleCheck v-if="selected.membership_status === 'active'" :size="15" />
                  {{ selected.status_label }}
                </span>
              </div>
              <button type="button" class="mm-close" aria-label="إغلاق" @click="closeMember"><X :size="22" :stroke-width="2.5" /></button>
            </div>

            <div class="mm-body">
              <div class="mm-info">
                <div class="mm-info-item">
                  <span class="mm-info-ico"><Hash :size="20" :stroke-width="2.4" /></span>
                  <div><span class="mm-lbl">رقم العضوية</span><span class="mm-val">{{ selected.membership_number }}</span></div>
                </div>
                <div v-if="selected.member_since" class="mm-info-item">
                  <span class="mm-info-ico"><CalendarDays :size="20" :stroke-width="2.2" /></span>
                  <div><span class="mm-lbl">تاريخ المنح</span><span class="mm-val">{{ selected.member_since }}</span></div>
                </div>
                <div v-if="selected.city" class="mm-info-item">
                  <span class="mm-info-ico"><MapPin :size="20" :stroke-width="2.2" /></span>
                  <div><span class="mm-lbl">المدينة</span><span class="mm-val">{{ selected.city }}</span></div>
                </div>
              </div>

              <h3 class="mm-sec-title"><Layers :size="22" /> <span>التصنيف: المجالات والاختصاصات</span></h3>
              <div v-if="selectedGroups.length" class="mm-fields">
                <div v-for="(f, i) in selectedGroups" :key="i" class="mm-field">
                  <div class="mm-field-head">
                    <span class="mm-field-ico"><component :is="fieldIcon(f.name)" :size="22" :stroke-width="2.2" /></span>
                    <span class="mm-field-name">{{ f.name }}</span>
                    <span v-if="!f.specs.length" class="mm-grades">
                      <span v-for="g in f.grades" :key="g" class="mm-grade"><ChevronUp :size="14" :stroke-width="3" /> {{ g }}</span>
                    </span>
                  </div>
                  <ul v-if="f.specs.length" class="mm-specs">
                    <li v-for="(s, j) in f.specs" :key="j" class="mm-spec">
                      <span class="mm-spec-name">{{ s.name }}</span>
                      <span v-if="s.grade" class="mm-grade"><ChevronUp :size="14" :stroke-width="3" /> {{ s.grade }}</span>
                      <span v-else class="mm-grade none">دون درجة</span>
                    </li>
                  </ul>
                </div>
              </div>
              <p v-else class="mm-muted">لا يوجد تصنيف مسجّل</p>
            </div>

            <svg class="mm-skyline" viewBox="0 0 600 70" preserveAspectRatio="xMidYMax slice" aria-hidden="true">
              <g fill="#c5cae9">
                <rect x="40" y="57" width="14" height="13" rx="1" /><rect x="57" y="60" width="22" height="10" rx="1" /><rect x="81" y="58" width="18" height="12" rx="1" /><rect x="102" y="53" width="18" height="17" rx="1" /><rect x="124" y="54" width="12" height="16" rx="1" /><rect x="138" y="30" width="16" height="40" rx="1" /><rect x="156" y="51" width="12" height="19" rx="1" /><rect x="172" y="53" width="16" height="17" rx="1" /><rect x="192" y="42" width="10" height="28" rx="1" /><rect x="206" y="53" width="22" height="17" rx="1" /><rect x="232" y="31" width="18" height="39" rx="1" /><rect x="252" y="54" width="12" height="16" rx="1" /><rect x="268" y="38" width="12" height="32" rx="1" /><rect x="283" y="22" width="12" height="48" rx="1" /><rect x="297" y="37" width="18" height="33" rx="1" /><rect x="319" y="45" width="22" height="25" rx="1" /><rect x="343" y="44" width="18" height="26" rx="1" /><rect x="364" y="52" width="10" height="18" rx="1" /><rect x="378" y="43" width="10" height="27" rx="1" /><rect x="391" y="22" width="22" height="48" rx="1" /><rect x="416" y="27" width="14" height="43" rx="1" /><rect x="434" y="33" width="16" height="37" rx="1" /><rect x="453" y="45" width="12" height="25" rx="1" /><rect x="469" y="51" width="12" height="19" rx="1" /><rect x="485" y="23" width="14" height="47" rx="1" /><rect x="502" y="45" width="14" height="25" rx="1" /><rect x="519" y="60" width="18" height="10" rx="1" /><rect x="539" y="46" width="18" height="24" rx="1" /><rect x="559" y="57" width="14" height="13" rx="1" />
                <g stroke="#c5cae9" stroke-width="2" fill="none"><path d="M210 70 V10 M204 12 H262 M256 12 V26" /><path d="M392 70 V6 M386 8 H436 M430 8 V22" /></g>
              </g>
            </svg>
          </div>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800;900&family=Tajawal:wght@400;500;700;800&display=swap');
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
.pub-page { font-family: 'Tajawal', 'Cairo', sans-serif; direction: rtl; color: #374151; background: #fff; }
.pub-cont { max-width: 1240px; margin: 0 auto; padding: 0 1.5rem; }
.pub-section { padding: 5rem 0; }
.page-hero { background: linear-gradient(145deg, #0d1b4b 0%, #1a237e 60%, #283593 100%); margin-top: calc(-1 * var(--pub-nav-h, 114px)); padding: calc(var(--pub-nav-h, 114px) + 2.5rem) 0 3rem; position: relative; overflow: hidden; }
.page-hero-shapes { position: absolute; top: 0; right: 0; bottom: 0; left: 0; pointer-events: none; }
.ph-s { position: absolute; border-radius: 50%; }
.ph-s1 { width: 400px; height: 400px; background: radial-gradient(circle, rgba(249,168,37,.12), transparent 65%); top: -100px; left: 5%; }
.ph-s2 { width: 300px; height: 300px; background: radial-gradient(circle, rgba(255,255,255,.05), transparent 65%); bottom: -80px; right: 10%; }
.page-hero-inner { position: relative; z-index: 2; }
.page-breadcrumb { display: flex; align-items: center; gap: .5rem; font-size: .82rem; color: rgba(255,255,255,.6); margin-bottom: 1rem; }
.page-breadcrumb a { color: rgba(255,255,255,.6); text-decoration: none; } .page-breadcrumb a:hover { color: #f9a825; }
.page-hero-title { font-family: 'Tajawal', 'Cairo', sans-serif; font-size: clamp(2rem,4vw,3rem); font-weight: 900; color: #fff; margin-bottom: .75rem; }
.page-hero-desc { font-size: 1rem; color: rgba(255,255,255,.75); line-height: 1.75; max-width: 600px; }

.filters-bar { background: #f9fafb; border: 1.5px solid #e5e7eb; border-radius: 16px; padding: 1.5rem; margin-bottom: 1.75rem; display: flex; flex-direction: column; gap: 1rem; }
.search-wrap { position: relative; }
.search-ico { position: absolute; top: 50%; right: 1rem; transform: translateY(-50%); color: #9ca3af; }
.search-input { width: 100%; border: 1.5px solid #e5e7eb; border-radius: 10px; padding: .75rem 2.75rem .75rem 1rem; font-size: .9rem; font-family: 'Tajawal', sans-serif; color: #374151; background: #fff; outline: none; transition: border-color .2s; }
.search-input:focus { border-color: #1a237e; box-shadow: 0 0 0 3px rgba(26,35,126,.08); }
.filters-row { display: flex; gap: .875rem; flex-wrap: wrap; }
.filter-sel { border: 1.5px solid #e5e7eb; border-radius: 9px; padding: .55rem 1rem; font-size: .85rem; font-family: 'Tajawal', sans-serif; color: #374151; background: #fff; outline: none; cursor: pointer; transition: border-color .2s; }
.filter-sel:focus { border-color: #1a237e; }
.filter-reset { display: flex; align-items: center; gap: .4rem; background: #e8eaf6; color: #1a237e; border: 1.5px solid #c5cae9; border-radius: 9px; padding: .55rem 1rem; font-size: .83rem; font-weight: 600; cursor: pointer; font-family: 'Tajawal', sans-serif; transition: all .2s; }
.filter-reset:hover { background: #1a237e; color: #fff; }
.results-count { font-size: .88rem; color: #6b7280; margin-bottom: 1.5rem; }
.results-count strong { color: #1a237e; }

.members-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 1.25rem; }
.member-card { min-width: 0; width: 100%; text-align: right; font: inherit; color: inherit; cursor: pointer; background: #fff; border: 1.5px solid #e5e7eb; border-radius: 14px; padding: 1.25rem; display: flex; align-items: flex-start; gap: 1rem; transition: box-shadow .25s, border-color .25s, transform .25s; position: relative; overflow: hidden; }
.member-card::before { content: ''; position: absolute; top: 0; right: 0; width: 4px; height: 100%; background: linear-gradient(180deg, #f9a825, #1a237e); opacity: 0; transition: opacity .25s; }
.member-card:hover, .member-card:focus-visible { box-shadow: 0 8px 24px rgba(13,27,75,.1); border-color: #c5cae9; transform: translateY(-3px); outline: none; }
.member-card:hover::before, .member-card:focus-visible::before { opacity: 1; }
.member-icon { width: 52px; height: 52px; border-radius: 14px; background: linear-gradient(135deg,#0d1b4b,#1a237e 55%,#3949ab); color: #f9a825; display: flex; align-items: center; justify-content: center; flex-shrink: 0; box-shadow: inset 0 0 0 2px rgba(249,168,37,.25); }
.member-body { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: .5rem; }
.member-name { font-family: 'Tajawal', 'Cairo', sans-serif; font-size: .95rem; font-weight: 800; color: #0d1b3e; line-height: 1.5; overflow-wrap: anywhere; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.member-num { font-size: .76rem; color: #6b7280; overflow-wrap: anywhere; }
.member-specs { display: flex; flex-wrap: wrap; gap: .35rem; min-width: 0; }
.member-spec { max-width: 100%; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: .74rem; color: #4b5563; background: #f3f4f6; border-radius: 50px; padding: .15rem .6rem; }
.member-spec.more { background: #e8eaf6; color: #1a237e; flex-shrink: 0; }
.member-footer { display: flex; align-items: center; gap: .45rem; flex-wrap: wrap; min-width: 0; }
.member-class { display: inline-flex; align-items: center; gap: .25rem; max-width: 100%; font-size: .72rem; font-weight: 700; border: 1px solid #1a237e; background: #e8eaf6; color: #1a237e; border-radius: 50px; padding: .15rem .6rem; white-space: nowrap; }
.member-status { display: inline-block; font-size: .72rem; font-weight: 700; border-radius: 50px; padding: .15rem .6rem; white-space: nowrap; }
.member-status.active { background: #e8f5e9; color: #2e7d32; }
.member-status.expired { background: #fce4ec; color: #c62828; }
.member-status.suspended { background: #fff8e1; color: #e65100; }
.member-since { font-size: .72rem; color: #9ca3af; white-space: nowrap; }
.member-more { display: inline-flex; align-items: center; gap: .2rem; align-self: flex-start; margin-top: .15rem; font-size: .76rem; font-weight: 700; color: #1a237e; transition: color .2s, gap .2s; }
.member-card:hover .member-more { color: #f9a825; gap: .45rem; }
.load-more-wrap { text-align: center; margin-top: 2rem; }
.load-more { background: #1a237e; color: #fff; border: none; border-radius: 9px; padding: .7rem 2rem; font-size: .9rem; cursor: pointer; font-family: 'Tajawal', sans-serif; }
.load-more:disabled { opacity: .6; cursor: default; }

/* نافذة بيانات التصنيف */
.mm-overlay { position: fixed; inset: 0; z-index: 2000; background: rgba(13,27,75,.55); backdrop-filter: blur(4px); display: flex; align-items: center; justify-content: center; padding: 1rem; font-family: 'Tajawal', 'Cairo', sans-serif; direction: rtl; }
.mm-dialog { position: relative; background: linear-gradient(180deg, #fff 0%, #fff 70%, #f4f5fd 100%); width: 100%; max-width: 680px; max-height: calc(100vh - 2rem); max-height: calc(100dvh - 2rem); border-radius: 22px; display: flex; flex-direction: column; overflow: hidden; box-shadow: 0 30px 70px rgba(13,27,75,.4); }
.mm-head { position: relative; display: flex; align-items: center; gap: 1.1rem; padding: 1.5rem 1.6rem 1.75rem; background: linear-gradient(120deg, #0d1b4b 0%, #1a237e 55%, #1f2c8f 100%); overflow: hidden; flex-shrink: 0; }
.mm-head-photo { position: absolute; top: 0; bottom: 0; left: 0; width: 55%; background: url('/images/landing/inner-hero-sm.webp') 100% 20% / 240% auto no-repeat; opacity: .42; mix-blend-mode: luminosity; -webkit-mask-image: linear-gradient(90deg, #000 30%, transparent 100%); mask-image: linear-gradient(90deg, #000 30%, transparent 100%); }
.mm-head-swoosh { position: absolute; left: 0; bottom: 0; width: 42%; height: 34px; }
.mm-head > :not(.mm-head-photo):not(.mm-head-swoosh) { position: relative; z-index: 1; }
.mm-icon { width: 72px; height: 72px; border-radius: 18px; background: rgba(13,27,75,.55); color: #f9a825; display: flex; align-items: center; justify-content: center; flex-shrink: 0; border: 2px solid rgba(249,168,37,.75); }
.mm-head-text { flex: 1; min-width: 0; display: flex; flex-direction: column; align-items: flex-start; gap: .6rem; }
.mm-title { font-size: 1.2rem; font-weight: 800; color: #fff; line-height: 1.6; overflow-wrap: anywhere; }
.mm-status { display: inline-flex; align-items: center; gap: .35rem; font-size: .8rem; font-weight: 800; border-radius: 50px; padding: .3rem .85rem; background: #e8f5e9; color: #2e7d32; }
.mm-status.active svg { color: #fff; fill: #2e7d32; }
.mm-status.expired { background: #fce4ec; color: #c62828; }
.mm-status.suspended { background: #fff8e1; color: #e65100; }
.mm-close { flex-shrink: 0; align-self: flex-start; width: 46px; height: 46px; border-radius: 50%; border: none; background: rgba(13,27,75,.6); color: #fff; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: background .2s, color .2s; }
.mm-close:hover { background: #f9a825; color: #0d1b4b; }
.mm-body { position: relative; z-index: 1; padding: 1.4rem 1.6rem 4.5rem; overflow-y: auto; overflow-x: hidden; }
.mm-info { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 1rem; margin-bottom: 1.6rem; }
.mm-info-item { display: flex; align-items: center; gap: .85rem; min-width: 0; background: #f8f9fe; border: 1.5px solid #e6e9f5; border-radius: 14px; padding: .85rem 1rem; }
.mm-info-ico { width: 46px; height: 46px; border-radius: 50%; background: #e8ecfb; color: #1a237e; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.mm-info-item > div { display: flex; flex-direction: column; min-width: 0; }
.mm-lbl { font-size: .8rem; color: #4b5563; }
.mm-val { font-size: 1.15rem; font-weight: 800; color: #0d1b3e; overflow-wrap: anywhere; }
.mm-sec-title { display: flex; align-items: center; gap: .55rem; font-size: 1.1rem; font-weight: 800; color: #0d1b3e; margin: 0 0 1rem; }
.mm-sec-title svg { color: #f9a825; flex-shrink: 0; }
.mm-sec-title::after { content: ''; flex: 1; height: 2px; background: #f9a825; margin-right: .75rem; border-radius: 2px; }
.mm-fields { display: flex; flex-direction: column; gap: 1rem; }
.mm-field { border: 1.5px solid #e3e7f5; border-radius: 14px; overflow: hidden; background: #fff; }
.mm-field-head { display: flex; align-items: center; flex-wrap: wrap; gap: .85rem; padding: 0 0 0 1rem; background: #e8ecfb; min-height: 52px; }
.mm-field-ico { width: 54px; align-self: stretch; min-height: 52px; background: #d3dbf8; color: #1a237e; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.mm-field-name { flex: 1; font-size: 1.02rem; font-weight: 800; color: #0d1b3e; overflow-wrap: anywhere; min-width: 0; padding: .6rem 0; }
.mm-grades { display: flex; flex-wrap: wrap; gap: .4rem; }
.mm-specs { list-style: none; }
.mm-spec { display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding: .6rem 1rem .6rem 1rem; padding-right: 2.6rem; position: relative; }
.mm-spec + .mm-spec { border-top: 1px solid #eef0f6; }
.mm-spec::before { content: ''; position: absolute; right: 1.5rem; top: 50%; width: 7px; height: 7px; border-radius: 50%; background: #1a237e; transform: translateY(-50%); }
.mm-spec-name { font-size: .95rem; color: #1f2937; overflow-wrap: anywhere; min-width: 0; }
.mm-grade { display: inline-flex; align-items: center; justify-content: center; gap: .3rem; flex-shrink: 0; min-width: 104px; font-size: .85rem; font-weight: 800; color: #1a237e; background: #fff; border: 1.5px solid #1a237e; border-radius: 50px; padding: .3rem .9rem; white-space: nowrap; }
.mm-grade.none { color: #6b7280; border-color: #d1d5db; background: #f9fafb; font-weight: 600; }
.mm-muted { font-size: .85rem; color: #9ca3af; margin-bottom: 1.25rem; }
.mm-skyline { position: absolute; left: 0; right: 0; bottom: 0; width: 100%; height: 60px; opacity: .4; pointer-events: none; z-index: 0; }
.mm-fade-enter-active, .mm-fade-leave-active { transition: opacity .2s; }
.mm-fade-enter-active .mm-dialog, .mm-fade-leave-active .mm-dialog { transition: transform .2s; }
.mm-fade-enter-from, .mm-fade-leave-to { opacity: 0; }
.mm-fade-enter-from .mm-dialog, .mm-fade-leave-to .mm-dialog { transform: translateY(14px) scale(.98); }

.empty-state { text-align: center; padding: 4rem 2rem; color: #9ca3af; }
.empty-state svg { margin-bottom: 1rem; }
.empty-state p { font-size: 1rem; margin-bottom: 1.25rem; }
.empty-state button { background: #1a237e; color: #fff; border: none; border-radius: 9px; padding: .65rem 1.5rem; font-size: .875rem; cursor: pointer; font-family: 'Tajawal', sans-serif; }

.filter-sel { max-width: 100%; text-overflow: ellipsis; }
.page-hero-desc, .page-hero-title { overflow-wrap: anywhere; }

@media (max-width: 900px) { .members-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
@media (max-width: 540px) {
  .pub-section { padding: 2.5rem 0; }
  .pub-cont { padding: 0 1rem; }
  .filters-bar { padding: 1rem; }
  .members-grid { grid-template-columns: minmax(0, 1fr); }
  .filters-row { flex-direction: column; }
  .filter-sel, .filter-reset { width: 100%; justify-content: center; }
  .mm-overlay { align-items: flex-end; padding: 0; }
  .mm-dialog { max-width: none; border-radius: 20px 20px 0 0; max-height: 92vh; max-height: 92dvh; }
  .mm-head { padding: 1.1rem 1rem 1.4rem; gap: .75rem; }
  .mm-head-photo { width: 80%; }
  .mm-title { font-size: 1rem; }
  .mm-icon { width: 54px; height: 54px; border-radius: 14px; }
  .mm-icon svg { width: 26px; height: 26px; }
  .mm-close { width: 38px; height: 38px; }
  .mm-body { padding: 1rem 1rem 4rem; }
  .mm-info { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .6rem; }
  .mm-info-item { padding: .7rem; gap: .55rem; }
  .mm-info-ico { width: 38px; height: 38px; }
  .mm-val { font-size: 1rem; }
  .mm-sec-title { font-size: .98rem; }
  .mm-field-ico { width: 46px; }
  .mm-field-name { font-size: .92rem; }
  .mm-spec { padding: .55rem .75rem; padding-right: 2.1rem; gap: .6rem; }
  .mm-spec::before { right: 1.15rem; }
  .mm-spec-name { font-size: .88rem; }
  .mm-grade { min-width: 84px; font-size: .78rem; padding: .25rem .6rem; }
}
</style>

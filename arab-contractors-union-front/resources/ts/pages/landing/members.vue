<script setup lang="ts">
import PublicSocialFollow from '@/components/PublicSocialFollow.vue'
import api from '@/plugins/axios'
import { ref, computed, watch, onMounted, onBeforeUnmount } from 'vue'
import { Search, Award, Filter, ChevronLeft, Building2, X, Hash, CalendarDays, MapPin, BadgeCheck, Layers } from 'lucide-vue-next'

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
  fields: { name: string; grades: string[] }[]
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

// نافذة بيانات تصنيف الشركة — من نفس بيانات الدليل العام، بدون أي طلب إضافي
const selected = ref<Member | null>(null)

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
              <span class="mm-icon" aria-hidden="true"><Building2 :size="30" :stroke-width="1.7" /></span>
              <div class="mm-head-text">
                <h2 class="mm-title">{{ selected.name }}</h2>
                <span class="member-status" :class="statusClass(selected.membership_status)">{{ selected.status_label }}</span>
              </div>
              <button type="button" class="mm-close" aria-label="إغلاق" @click="closeMember"><X :size="20" /></button>
            </div>

            <div class="mm-body">
              <div class="mm-info">
                <div class="mm-info-item">
                  <Hash :size="16" />
                  <div><span class="mm-lbl">رقم العضوية</span><span class="mm-val">{{ selected.membership_number }}</span></div>
                </div>
                <div v-if="selected.member_since" class="mm-info-item">
                  <CalendarDays :size="16" />
                  <div><span class="mm-lbl">عضو منذ</span><span class="mm-val">{{ selected.member_since }}</span></div>
                </div>
                <div v-if="selected.city" class="mm-info-item">
                  <MapPin :size="16" />
                  <div><span class="mm-lbl">المدينة</span><span class="mm-val">{{ selected.city }}</span></div>
                </div>
              </div>

              <h3 class="mm-sec-title"><Layers :size="17" /> المجالات والدرجات</h3>
              <div v-if="selected.fields.length" class="mm-fields">
                <div v-for="(f, i) in selected.fields" :key="i" class="mm-field">
                  <span class="mm-field-name">{{ f.name }}</span>
                  <span class="mm-grades">
                    <span v-for="g in f.grades" :key="g" class="member-class"><Award :size="11" /> {{ g }}</span>
                    <span v-if="!f.grades.length" class="mm-muted">بدون درجة</span>
                  </span>
                </div>
              </div>
              <p v-else class="mm-muted">لا توجد مجالات مسجّلة</p>

              <h3 class="mm-sec-title"><BadgeCheck :size="17" /> الاختصاصات</h3>
              <div v-if="selected.specializations.length" class="mm-table-wrap">
                <table class="mm-table">
                  <thead><tr><th>الاختصاص</th><th>الدرجة</th></tr></thead>
                  <tbody>
                    <tr v-for="(s, i) in selected.specializations" :key="i">
                      <td>{{ s.name }}</td>
                      <td>{{ s.grade || '—' }}</td>
                    </tr>
                  </tbody>
                </table>
              </div>
              <p v-else class="mm-muted">لا توجد اختصاصات مسجّلة</p>
            </div>
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
.mm-overlay { position: fixed; inset: 0; z-index: 2000; background: rgba(13,27,75,.55); backdrop-filter: blur(3px); display: flex; align-items: center; justify-content: center; padding: 1rem; font-family: 'Tajawal', 'Cairo', sans-serif; direction: rtl; }
.mm-dialog { background: #fff; width: 100%; max-width: 620px; max-height: calc(100vh - 2rem); max-height: calc(100dvh - 2rem); border-radius: 18px; display: flex; flex-direction: column; overflow: hidden; box-shadow: 0 24px 60px rgba(13,27,75,.35); }
.mm-head { display: flex; align-items: center; gap: 1rem; padding: 1.25rem 1.5rem; background: linear-gradient(145deg, #0d1b4b 0%, #1a237e 60%, #283593 100%); border-bottom: 3px solid #f9a825; }
.mm-icon { width: 58px; height: 58px; border-radius: 16px; background: rgba(255,255,255,.1); color: #f9a825; display: flex; align-items: center; justify-content: center; flex-shrink: 0; box-shadow: inset 0 0 0 2px rgba(249,168,37,.35); }
.mm-head-text { flex: 1; min-width: 0; display: flex; flex-direction: column; align-items: flex-start; gap: .45rem; }
.mm-title { font-size: 1.12rem; font-weight: 800; color: #fff; line-height: 1.5; overflow-wrap: anywhere; }
.mm-close { flex-shrink: 0; align-self: flex-start; width: 36px; height: 36px; border-radius: 10px; border: none; background: rgba(255,255,255,.12); color: #fff; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: background .2s; }
.mm-close:hover { background: #f9a825; color: #0d1b4b; }
.mm-body { padding: 1.25rem 1.5rem 1.5rem; overflow-y: auto; overflow-x: hidden; }
.mm-info { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: .75rem; margin-bottom: 1.4rem; }
.mm-info-item { display: flex; align-items: center; gap: .6rem; min-width: 0; background: #f9fafb; border: 1px solid #eef0f4; border-radius: 12px; padding: .7rem .85rem; color: #1a237e; }
.mm-info-item > div { display: flex; flex-direction: column; min-width: 0; }
.mm-lbl { font-size: .72rem; color: #6b7280; }
.mm-val { font-size: .9rem; font-weight: 700; color: #0d1b3e; overflow-wrap: anywhere; }
.mm-sec-title { display: flex; align-items: center; gap: .45rem; font-size: .95rem; font-weight: 800; color: #1a237e; margin: 0 0 .75rem; }
.mm-sec-title svg { color: #f9a825; }
.mm-fields { display: flex; flex-direction: column; gap: .55rem; margin-bottom: 1.4rem; }
.mm-field { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: .5rem; border: 1px solid #e5e7eb; border-radius: 12px; padding: .65rem .85rem; }
.mm-field-name { font-size: .88rem; font-weight: 700; color: #374151; overflow-wrap: anywhere; min-width: 0; }
.mm-grades { display: flex; flex-wrap: wrap; gap: .35rem; }
.mm-table-wrap { border: 1px solid #e5e7eb; border-radius: 12px; overflow: hidden; }
.mm-table { width: 100%; border-collapse: collapse; table-layout: fixed; font-size: .85rem; }
.mm-table th { background: #e8eaf6; color: #1a237e; font-weight: 800; text-align: right; padding: .6rem .85rem; }
.mm-table th:last-child, .mm-table td:last-child { width: 34%; }
.mm-table td { padding: .6rem .85rem; border-top: 1px solid #f0f1f4; color: #374151; overflow-wrap: anywhere; vertical-align: top; }
.mm-table tbody tr:nth-child(even) td { background: #fafbff; }
.mm-table td:last-child { font-weight: 700; color: #1a237e; }
.mm-muted { font-size: .82rem; color: #9ca3af; margin-bottom: 1.25rem; }
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
  .mm-dialog { max-width: none; border-radius: 18px 18px 0 0; max-height: 92vh; max-height: 92dvh; }
  .mm-head { padding: 1rem 1.1rem; }
  .mm-body { padding: 1rem 1.1rem 1.25rem; }
  .mm-icon { width: 48px; height: 48px; }
}
</style>

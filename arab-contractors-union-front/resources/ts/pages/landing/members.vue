<script setup lang="ts">
import PublicSocialFollow from '@/components/PublicSocialFollow.vue'
import api from '@/plugins/axios'
import { ref, computed, watch, onMounted } from 'vue'
import { Search, Award, Filter, ChevronLeft } from 'lucide-vue-next'

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

onMounted(async () => {
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
          <div v-for="m in members" :key="m.membership_number" class="member-card">
            <div class="member-avatar">
              <img v-if="m.logo_url" :src="m.logo_url" :alt="m.name" class="member-logo">
              <template v-else>{{ m.name.charAt(0) }}</template>
            </div>
            <div class="member-body">
              <h3 class="member-name" :title="m.name">{{ m.name }}</h3>
              <div class="member-meta">
                <span class="member-num">رقم العضوية: {{ m.membership_number }}</span>
              </div>
              <div v-if="m.specializations.length" class="member-specs">
                <span v-for="(s, i) in m.specializations.slice(0, 3)" :key="i" class="member-spec">{{ s.name }}</span>
                <span v-if="m.specializations.length > 3" class="member-spec more">+{{ m.specializations.length - 3 }}</span>
              </div>
              <div class="member-footer">
                <span v-for="g in [...new Set(m.fields.flatMap(f => f.grades))].slice(0, 2)" :key="g" class="member-class">
                  <Award :size="11" /> {{ g }}
                </span>
                <span class="member-status" :class="statusClass(m.membership_status)">{{ m.status_label }}</span>
                <span v-if="m.member_since" class="member-since">منذ {{ m.member_since }}</span>
              </div>
            </div>
            <span class="member-link"><ChevronLeft :size="16" /></span>
          </div>
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

.members-grid { display: grid; grid-template-columns: repeat(3,1fr); gap: 1.25rem; }
.member-card { background: #fff; border: 1.5px solid #e5e7eb; border-radius: 14px; padding: 1.25rem; display: flex; align-items: flex-start; gap: 1rem; transition: all .25s; position: relative; }
.member-card:hover { box-shadow: 0 8px 24px rgba(13,27,75,.1); border-color: #c5cae9; transform: translateY(-3px); }
.member-avatar { width: 46px; height: 46px; border-radius: 12px; background: linear-gradient(135deg,#1a237e,#3949ab); color: #fff; display: flex; align-items: center; justify-content: center; font-family: 'Tajawal', 'Cairo', sans-serif; font-size: 1.1rem; font-weight: 800; flex-shrink: 0; }
.member-body { flex: 1; min-width: 0; }
.member-name { font-family: 'Tajawal', 'Cairo', sans-serif; font-size: .88rem; font-weight: 800; color: #0d1b3e; margin-bottom: .5rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.member-meta { display: flex; gap: .65rem; margin-bottom: .6rem; }
.member-city { display: flex; align-items: center; gap: .25rem; font-size: .76rem; color: #6b7280; }
.member-logo { width: 100%; height: 100%; object-fit: contain; border-radius: 12px; background: #fff; }
.member-num { font-size: .76rem; color: #6b7280; }
.member-specs { display: flex; flex-wrap: wrap; gap: .35rem; margin-bottom: .6rem; }
.member-spec.more { background: #e8eaf6; color: #1a237e; }
.load-more-wrap { text-align: center; margin-top: 2rem; }
.load-more { background: #1a237e; color: #fff; border: none; border-radius: 9px; padding: .7rem 2rem; font-size: .9rem; cursor: pointer; font-family: 'Tajawal', sans-serif; }
.load-more:disabled { opacity: .6; cursor: default; }
.member-spec { font-size: .76rem; color: #6b7280; background: #f3f4f6; border-radius: 50px; padding: .15rem .6rem; }
.member-footer { display: flex; align-items: center; gap: .5rem; flex-wrap: wrap; }
.member-class { display: flex; align-items: center; gap: .25rem; font-size: .72rem; font-weight: 700; border: 1px solid #1a237e; background: #e8eaf6; color: #1a237e; border-radius: 50px; padding: .15rem .6rem; }
.member-status { font-size: .72rem; font-weight: 700; border-radius: 50px; padding: .15rem .6rem; }
.member-status.active { background: #e8f5e9; color: #2e7d32; }
.member-status.expired { background: #fce4ec; color: #c62828; }
.member-status.suspended { background: #fff8e1; color: #e65100; }
.member-since { font-size: .72rem; color: #9ca3af; }
.member-link { position: absolute; top: 50%; left: 1rem; transform: translateY(-50%); color: #9ca3af; transition: color .2s; }
.member-card:hover .member-link { color: #1a237e; }

.empty-state { text-align: center; padding: 4rem 2rem; color: #9ca3af; }
.empty-state svg { margin-bottom: 1rem; }
.empty-state p { font-size: 1rem; margin-bottom: 1.25rem; }
.empty-state button { background: #1a237e; color: #fff; border: none; border-radius: 9px; padding: .65rem 1.5rem; font-size: .875rem; cursor: pointer; font-family: 'Tajawal', sans-serif; }

@media (max-width: 900px) { .members-grid { grid-template-columns: repeat(2,1fr); } }
@media (max-width: 540px) { .members-grid { grid-template-columns: 1fr; } .filters-row { flex-direction: column; } .filter-sel { width: 100%; } }
</style>

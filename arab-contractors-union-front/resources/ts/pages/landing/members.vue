<script setup lang="ts">
import PublicSocialFollow from '@/components/PublicSocialFollow.vue'
import api from '@/plugins/axios'
import { ref, onMounted, onBeforeUnmount } from 'vue'
import { Search, MapPin, Award, Filter } from 'lucide-vue-next'

definePage({ meta: { layout: 'landing', public: true, unauthenticatedOnly: false } })

interface DirectoryItem {
  name: string
  membership_number: string
  city: string | null
  membership_status: 'active' | 'expired' | 'suspended'
  status_label: string
  fields: { name: string; grades: string[] }[]
}

const searchQ = ref('')
const filterField = ref('')
const filterStatus = ref('')

const fieldOptions = ref<{ id: number; name: string }[]>([])
const statuses = [
  { value: 'active', label: 'فعّالة' },
  { value: 'expired', label: 'منتهية' },
  { value: 'suspended', label: 'موقوفة' },
]

const items = ref<DirectoryItem[]>([])
const total = ref(0)
const page = ref(1)
const lastPage = ref(1)
const loading = ref(false)
let requestId = 0
let timer: ReturnType<typeof setTimeout> | undefined

async function load(p = 1) {
  const id = ++requestId
  loading.value = true
  try {
    const r = await api.get('/api/v1/contractors-directory', {
      params: {
        search: searchQ.value.trim() || undefined,
        field_id: filterField.value || undefined,
        status: filterStatus.value || undefined,
        page: p,
        per_page: 12,
      },
    })
    if (id !== requestId) return
    const list: DirectoryItem[] = r.data.items ?? []
    items.value = p === 1 ? list : [...items.value, ...list]
    total.value = r.data.meta?.total ?? items.value.length
    page.value = r.data.meta?.current_page ?? p
    lastPage.value = r.data.meta?.last_page ?? p
  }
  catch {
    if (id === requestId) { items.value = []; total.value = 0 }
  }
  finally {
    if (id === requestId) loading.value = false
  }
}

function reload() { clearTimeout(timer); timer = setTimeout(() => load(1), 300) }
function reset() { searchQ.value = ''; filterField.value = ''; filterStatus.value = ''; load(1) }

onMounted(async () => {
  load(1)
  try {
    const r = await api.get('/api/v1/app/specialties-catalog')
    fieldOptions.value = r.data.fields ?? []
  }
  catch { /* الفلتر يبقى فارغاً */ }
})
onBeforeUnmount(() => clearTimeout(timer))
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
        <p class="page-hero-desc">دليل شركات المقاولات الأعضاء في اتحاد المقاولين الفلسطينيين — يمكنك البحث والتصفية</p>
      </div>
      <div class="pub-follow-slot"><PublicSocialFollow /></div>
    </div>

    <section class="pub-section">
      <div class="pub-cont">
        <div class="filters-bar">
          <div class="search-wrap">
            <Search :size="17" class="search-ico" />
            <input v-model="searchQ" type="text" placeholder="ابحث باسم الشركة أو رقم العضوية..." class="search-input" @input="reload" />
          </div>
          <div class="filters-row">
            <select v-model="filterField" class="filter-sel" @change="load(1)">
              <option value="">كل الاختصاصات</option>
              <option v-for="f in fieldOptions" :key="f.id" :value="f.id">{{ f.name }}</option>
            </select>
            <select v-model="filterStatus" class="filter-sel" @change="load(1)">
              <option value="">كل الحالات</option>
              <option v-for="s in statuses" :key="s.value" :value="s.value">{{ s.label }}</option>
            </select>
            <button class="filter-reset" @click="reset">
              <Filter :size="14" /> إعادة تعيين
            </button>
          </div>
        </div>

        <p class="results-count">عُثر على <strong>{{ total }}</strong> شركة</p>

        <div class="members-grid">
          <div v-for="m in items" :key="m.membership_number" class="member-card">
            <div class="member-avatar">{{ m.name.replace(/^شركة\s*/, '').charAt(0) }}</div>
            <div class="member-body">
              <h3 class="member-name" :title="m.name">{{ m.name }}</h3>
              <div class="member-meta">
                <span v-if="m.city" class="member-city"><MapPin :size="12" /> {{ m.city }}</span>
                <span class="member-spec">رقم العضوية {{ m.membership_number }}</span>
              </div>
              <div class="member-fields">
                <span v-for="f in m.fields" :key="f.name" class="member-class">
                  <Award :size="11" /> {{ f.name }}<template v-if="f.grades.length"> — {{ f.grades.join('، ') }}</template>
                </span>
              </div>
              <div class="member-footer">
                <span class="member-status" :class="m.membership_status === 'active' ? 'active' : m.membership_status === 'suspended' ? 'suspended' : 'expired'">
                  {{ m.membership_status === 'active' ? 'فعّالة' : m.status_label }}
                </span>
              </div>
            </div>
          </div>
        </div>

        <div v-if="!loading && !items.length" class="empty-state">
          <Search :size="48" />
          <p>لا توجد نتائج مطابقة للبحث</p>
          <button @click="reset">إعادة تعيين الفلاتر</button>
        </div>

        <div v-if="page < lastPage" class="more-wrap">
          <button class="more-btn" :disabled="loading" @click="load(page + 1)">{{ loading ? 'جارٍ التحميل...' : 'عرض المزيد' }}</button>
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
.member-spec { font-size: .76rem; color: #6b7280; background: #f3f4f6; border-radius: 50px; padding: .15rem .6rem; }
.member-footer { display: flex; align-items: center; gap: .5rem; flex-wrap: wrap; }
.member-class { display: flex; align-items: center; gap: .25rem; font-size: .72rem; font-weight: 700; border: 1px solid; border-radius: 50px; padding: .15rem .6rem; }
.member-status { font-size: .72rem; font-weight: 700; border-radius: 50px; padding: .15rem .6rem; }
.member-status.active { background: #e8f5e9; color: #2e7d32; }
.member-status.expired { background: #fce4ec; color: #c62828; }
.member-status.suspended { background: #fff8e1; color: #e65100; }
.member-since { font-size: .72rem; color: #9ca3af; }
.member-fields { display: flex; flex-wrap: wrap; gap: .35rem; margin-bottom: .6rem; }
.member-fields .member-class { background: #e8eaf6; color: #1a237e; border-color: #c5cae9; }
.more-wrap { text-align: center; margin-top: 2rem; }
.more-btn { background: #1a237e; color: #fff; border: none; border-radius: 9px; padding: .7rem 2rem; font-size: .9rem; cursor: pointer; font-family: 'Tajawal', sans-serif; }
.more-btn:disabled { opacity: .6; cursor: default; }
.member-link { position: absolute; top: 50%; left: 1rem; transform: translateY(-50%); color: #9ca3af; transition: color .2s; }
.member-card:hover .member-link { color: #1a237e; }

.empty-state { text-align: center; padding: 4rem 2rem; color: #9ca3af; }
.empty-state svg { margin-bottom: 1rem; }
.empty-state p { font-size: 1rem; margin-bottom: 1.25rem; }
.empty-state button { background: #1a237e; color: #fff; border: none; border-radius: 9px; padding: .65rem 1.5rem; font-size: .875rem; cursor: pointer; font-family: 'Tajawal', sans-serif; }

@media (max-width: 900px) { .members-grid { grid-template-columns: repeat(2,1fr); } }
@media (max-width: 540px) { .members-grid { grid-template-columns: 1fr; } .filters-row { flex-direction: column; } .filter-sel { width: 100%; } }
</style>

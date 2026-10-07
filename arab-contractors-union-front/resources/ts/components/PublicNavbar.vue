<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import { Menu, X, HardHat, Smartphone, ChevronDown, LayoutDashboard, User, LogOut, Bell } from 'lucide-vue-next'
import api from '@/plugins/axios'
import axios from 'axios'

const BASE = import.meta.env.VITE_API_BASE_URL ?? ''

defineProps<{
  contractorProfile?: any
}>()

const router = useRouter()
const scrolled = ref(false)
const mobileOpen = ref(false)

const localProfile = ref<any>(null)

function onScroll() { scrolled.value = window.scrollY > 50 }
onMounted(() => window.addEventListener('scroll', onScroll, { passive: true }))
onUnmounted(() => window.removeEventListener('scroll', onScroll))

const navLinks = [
  { label: 'الرئيسية', href: '/landing' },
  {
    label: 'الخدمات الالكترونية', href: '#',
    children: [
      { label: 'خدمات الاتحاد', href: '/landing/services' },
      { label: 'بوابة العطاءات', href: '/landing/public-tenders' },
      { label: 'التدريب', href: '/landing/training-center' },
      { label: 'التشريعات', href: '/landing/legislation' },
      { label: 'مكتبة الملفات', href: '/landing/library' },
    ],
  },
  {
    label: 'عن الاتحاد', href: '#',
    children: [
      { label: 'عن الاتحاد', href: '/landing/about' },
      { label: 'مجلس الإدارة', href: '/landing/board' },
      { label: 'اللجان والفروع', href: '/landing/committees' },
      { label: 'أعضاء الاتحاد', href: '/landing/members' },
    ],
  },
  {
    label: 'المركز الاعلامي', href: '#',
    children: [
      { label: 'الأخبار', href: '/landing/news' },
      { label: 'الفعاليات', href: '/landing/events' },
      { label: 'المشاريع والمعارض', href: '/landing/projects' },
    ],
  },
  { label: 'تواصل معنا', href: '/landing/contact-us' },
]

const initials = (name?: string) => (name ?? '').trim().charAt(0)

const openDropdown = ref<string | null>(null)
const showUserMenu = ref(false)
function toggleDropdown(label: string) {
  openDropdown.value = openDropdown.value === label ? null : label
}
function closeDropdowns() { openDropdown.value = null; showUserMenu.value = false; showNotifDropdown.value = false }

onMounted(() => document.addEventListener('click', closeDropdowns))
onUnmounted(() => document.removeEventListener('click', closeDropdowns))

function toggleUserMenu() { showUserMenu.value = !showUserMenu.value }
function goToDashboard() { showUserMenu.value = false; mobileOpen.value = false; router.push('/contractor/dashboard') }
function goToProfile() { showUserMenu.value = false; mobileOpen.value = false; router.push('/contractor/dashboard?tab=profile') }

function navigate(href: string) {
  mobileOpen.value = false
  openDropdown.value = null
  if (href !== '#') router.push(href)
}

async function fetchMe() {
  const savedToken = localStorage.getItem('contractor_token')
  if (savedToken) {
    try {
      const r = await api.get('/api/v1/contractor/auth/profile', {
        headers: { Authorization: `Bearer ${savedToken}` },
      })
      localProfile.value = r.data.items ?? r.data
      fetchNotifications()
    } catch {
      localStorage.removeItem('contractor_token')
      localProfile.value = null
    }
  } else {
    localProfile.value = null
  }
}

// ─── Notifications Inbox ───
interface AppNotification { id: string; data: Record<string, any>; read_at: string | null; created_at: string }
const notifications = ref<AppNotification[]>([])
const unreadCount = ref(0)
const showNotifDropdown = ref(false)

async function fetchNotifications() {
  const savedToken = localStorage.getItem('contractor_token')
  if (!savedToken) return
  try {
    const r = await axios.get(`${BASE}/api/v1/contractor/auth/notifications`, {
      headers: { Authorization: `Bearer ${savedToken}` },
    })
    notifications.value = r.data.items?.notifications?.data ?? []
    unreadCount.value = r.data.items?.unread_count ?? 0
  } catch {}
}

function toggleNotifDropdown() {
  showNotifDropdown.value = !showNotifDropdown.value
}

function fmtNotifTime(d: string) {
  return new Date(d).toLocaleDateString('ar-PS', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' })
}

async function markNotificationRead(n: AppNotification) {
  if (n.read_at) return
  const savedToken = localStorage.getItem('contractor_token')
  try {
    await axios.patch(`${BASE}/api/v1/contractor/auth/notifications/${n.id}/mark-as-read`, {}, {
      headers: { Authorization: `Bearer ${savedToken}` },
    })
    n.read_at = new Date().toISOString()
    unreadCount.value = Math.max(0, unreadCount.value - 1)
  } catch {}
}

function handleRegisterClick() {
  router.push('/contractor/login')
}

function handleLogout() {
  showUserMenu.value = false
  mobileOpen.value = false
  localProfile.value = null
  notifications.value = []
  unreadCount.value = 0
  localStorage.removeItem('contractor_token')
  window.dispatchEvent(new CustomEvent('contractor-logged-out'))
}

onMounted(() => {
  fetchMe()
  window.addEventListener('contractor-logged-in', fetchMe)
  window.addEventListener('contractor-logged-out', () => {
    localProfile.value = null
  })
})
</script>

<template>
  <div>
    <!-- Top Bar -->
    <div class="pub-top-bar">
      <div class="pub-container pub-tb-inner">
        <div class="pub-tb-contacts">
          <a href="tel:+970592373805" class="pub-tb-link" dir="ltr">
            (970) 0592373805 <Smartphone :size="15" />
          </a>
          <a href="mailto:info@pcu.ps" class="pub-tb-link">info@pcu.ps</a>
        </div>
        <div class="pub-tb-social">
          <a href="#" class="pub-tb-soc" aria-label="Facebook">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor"><path d="M14 8V6.5c0-.8.2-1.3 1.4-1.3H17V2.2C16.7 2.1 15.7 2 14.6 2 12.2 2 10.6 3.5 10.6 6.1V8H8v3.3h2.6V22H14V11.3h2.7L17.1 8z" /></svg>
          </a>
          <a href="#" class="pub-tb-soc" aria-label="Instagram">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><rect x="3" y="3" width="18" height="18" rx="5" /><circle cx="12" cy="12" r="4" /><circle cx="17.5" cy="6.5" r=".8" fill="currentColor" /></svg>
          </a>
          <a href="#" class="pub-tb-soc" aria-label="X">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor"><path d="M17.8 3h3.1l-6.8 7.7L22 21h-6.2l-4.9-6.3L5.3 21H2.2l7.2-8.3L2 3h6.3l4.4 5.8zm-1.1 16.2h1.7L7.4 4.7H5.6z" /></svg>
          </a>
        </div>
      </div>
    </div>

    <!-- Navbar -->
    <nav class="pub-navbar" :class="{ scrolled }">
      <div class="pub-container pub-nb-inner">
        <RouterLink to="/landing" class="pub-brand">
          <img src="/logo.png" alt="اتحاد المقاولين الفلسطينيين" class="pub-brand-logo" />
        </RouterLink>

        <div class="pub-nav-links">
          <div
            v-for="l in navLinks" :key="l.label"
            class="pub-nav-item"
            @click.stop="l.children ? toggleDropdown(l.label) : navigate(l.href)"
          >
            <RouterLink v-if="!l.children" :to="l.href" class="pub-nav-link">{{ l.label }}</RouterLink>
            <button v-else class="pub-nav-link pub-nav-btn">
              {{ l.label }} <ChevronDown :size="13" class="pub-nav-chevron" :class="{ open: openDropdown === l.label }" />
            </button>
            <div v-if="l.children && openDropdown === l.label" class="pub-dropdown">
              <RouterLink v-for="c in l.children" :key="c.href" :to="c.href" class="pub-dd-link" @click="openDropdown = null">
                {{ c.label }}
              </RouterLink>
            </div>
          </div>
        </div>

        <div class="pub-nb-actions">
          <button v-if="!localProfile" class="pub-btn-register" @click="handleRegisterClick">
            <HardHat :size="15" /> تسجيل الدخول
          </button>
          <span v-if="localProfile?.status === 'active'" class="pub-member-pill"><i />عضوية سارية</span>
          <div v-if="localProfile" class="pub-nb-notif" @click.stop="toggleNotifDropdown">
            <Bell :size="24" fill="currentColor" />
            <span v-if="unreadCount > 0" class="pub-nb-notif-badge">{{ unreadCount > 9 ? '9+' : unreadCount }}</span>
            <div v-if="showNotifDropdown" class="pub-dropdown pub-notif-dropdown" @click.stop>
              <div class="pub-dd-header">الإشعارات</div>
              <div v-if="!notifications.length" class="pub-notif-empty">لا توجد إشعارات بعد.</div>
              <button
                v-for="n in notifications" :key="n.id"
                class="pub-notif-item" :class="{ unread: !n.read_at }"
                @click="markNotificationRead(n)"
              >
                <p>{{ n.data.message }}</p>
                <span>{{ fmtNotifTime(n.created_at) }}</span>
              </button>
            </div>
          </div>
          <div v-if="localProfile" class="pub-nb-user" @click.stop="toggleUserMenu">
            <div class="pub-nb-avatar">{{ initials(localProfile.name) }}</div>
            <ChevronDown :size="16" class="pub-nav-chevron" :class="{ open: showUserMenu }" />
            <div v-if="showUserMenu" class="pub-dropdown pub-user-dropdown" @click.stop>
              <div class="pub-dd-header">{{ localProfile.name }}</div>
              <button class="pub-dd-link pub-dd-btn" @click="goToDashboard">
                <LayoutDashboard :size="14" /> لوحة تحكمي
              </button>
              <button class="pub-dd-link pub-dd-btn" @click="goToProfile">
                <User :size="14" /> ملفي التعريفي
              </button>
              <button class="pub-dd-link pub-dd-btn pub-dd-danger" @click="handleLogout">
                <LogOut :size="14" /> تسجيل خروج
              </button>
            </div>
          </div>
        </div>

        <button class="pub-hamburger" @click="mobileOpen = !mobileOpen" aria-label="قائمة">
          <X v-if="mobileOpen" :size="22" />
          <Menu v-else :size="22" />
        </button>
      </div>

      <!-- Mobile Menu -->
      <div v-if="mobileOpen" class="pub-mobile-menu">
        <template v-for="l in navLinks" :key="l.label">
          <RouterLink v-if="!l.children" :to="l.href" class="pub-mob-link" @click="mobileOpen = false">{{ l.label }}</RouterLink>
          <div v-else class="pub-mob-group">
            <button class="pub-mob-link pub-mob-group-btn" @click="toggleDropdown(l.label)">
              {{ l.label }} <ChevronDown :size="14" :class="{ 'rotate-180': openDropdown === l.label }" style="transition:transform .2s" />
            </button>
            <div v-if="openDropdown === l.label" class="pub-mob-sub">
              <RouterLink v-for="c in l.children" :key="c.href" :to="c.href" class="pub-mob-sub-link" @click="mobileOpen = false">{{ c.label }}</RouterLink>
            </div>
          </div>
        </template>
        <div class="pub-mob-actions">
          <button v-if="!localProfile" class="pub-btn-register pub-mob-cta" @click="handleRegisterClick(); mobileOpen = false">
            <HardHat :size="15" /> تسجيل العضوية
          </button>
          <div v-else class="pub-mob-user-wrap">
            <div class="pub-mob-user">
              <div class="pub-nb-avatar">{{ initials(localProfile.name) }}</div>
              <span class="pub-nb-name">{{ localProfile.name }}</span>
            </div>
            <button class="pub-mob-link" @click="goToDashboard">
              <span style="display:flex;align-items:center;gap:.5rem"><LayoutDashboard :size="15" /> لوحة تحكمي</span>
            </button>
            <button class="pub-mob-link" @click="goToProfile">
              <span style="display:flex;align-items:center;gap:.5rem"><User :size="15" /> ملفي التعريفي</span>
            </button>
            <button class="pub-mob-link" style="color:#c62828" @click="handleLogout">
              <span style="display:flex;align-items:center;gap:.5rem"><LogOut :size="15" /> تسجيل خروج</span>
            </button>
          </div>
        </div>
      </div>
    </nav>
  </div>
</template>

<style scoped>
@import url('https://fonts.cdnfonts.com/css/neo-sans-arabic');
@import url('https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800;900&family=Tajawal:wght@400;500;700;800&display=swap');
@import url('https://fonts.cdnfonts.com/css/dubai');

@import url('https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800;900&display=swap');

div, button { font-family: 'Tajawal', 'Cairo', sans-serif; }

.pub-top-bar { background: linear-gradient(90deg, #000080, #0000c8); padding: .35rem 0; }
.pub-container { max-width: 1200px; margin: 0 auto; padding: 0 24px; }
.pub-tb-inner { display: flex; align-items: center; justify-content: space-between; }
.pub-tb-contacts { display: flex; gap: 1.75rem; }
.pub-tb-link { color: #fff; font-size: .9rem; text-decoration: none; font-weight: 700; display: flex; align-items: center; gap: .4rem; }
.pub-tb-link:hover { color: #ffbe1a; }
.pub-tb-social { display: flex; gap: .6rem; }
.pub-tb-soc { width: 22px; height: 22px; border-radius: 5px; background: rgba(255,255,255,.12); color: #fff; display: flex; align-items: center; justify-content: center; text-decoration: none; border: 1px solid rgba(255,255,255,.35); transition: all .2s; }
.pub-tb-soc:hover { background: #d67a00; border-color: #d67a00; }

.pub-navbar {
  position: sticky; top: 0; z-index: 200;
  background: #fff; padding: .45rem 0;
  transition: box-shadow .3s;
}
.pub-navbar.scrolled { box-shadow: 0 4px 24px rgba(0,0,80,.1); }
.pub-nb-inner { display: flex; align-items: center; gap: 1rem; }

.pub-brand { display: flex; align-items: center; text-decoration: none; flex-shrink: 0; }
.pub-brand-logo { height: 70px; width: auto; object-fit: contain; }

.pub-nav-links { display: flex; gap: 1.6rem; margin-inline-start: 2.4rem; margin-inline-end: auto; }
.pub-nav-item { position: relative; }
.pub-nav-link {
  display: flex; align-items: center; gap: .35rem;
  padding: .45rem 0; color: #5b6474; font-size: 1rem;
  font-weight: 500; text-decoration: none;
  transition: color .2s; white-space: nowrap; background: none; border: none;
  cursor: pointer;
}
.pub-nav-link:hover, .router-link-exact-active.pub-nav-link { color: #000090; }
.router-link-exact-active.pub-nav-link { font-weight: 700; }
.pub-member-pill {
  display: inline-flex; align-items: center; gap: .5rem; background: #e3f6e8; color: #11903a;
  border-radius: 14px; padding: .6rem 1.1rem; font-size: .95rem; font-weight: 600; white-space: nowrap;
}
.pub-member-pill i { width: 9px; height: 9px; border-radius: 50%; background: #11903a; }
.pub-nav-chevron { transition: transform .25s; flex-shrink: 0; }
.pub-nav-chevron.open { transform: rotate(-180deg); }

.pub-dropdown {
  position: absolute; top: calc(100% + .5rem); right: 0;
  background: #fff; border: 1.5px solid #e5e7eb; border-radius: 12px;
  padding: .5rem; min-width: 160px;
  box-shadow: 0 12px 36px rgba(0,0,0,.12);
  z-index: 300;
}
.pub-dd-link {
  display: block; padding: .5rem .875rem; color: #374151; font-size: .83rem;
  font-weight: 600; text-decoration: none; border-radius: 8px;
  transition: all .15s; white-space: nowrap;
}
.pub-dd-link:hover { background: #e8eaf6; color: #1a237e; }

.pub-btn-register {
  display: flex; align-items: center; gap: .4rem;
  background: linear-gradient(90deg, #000080, #0000e0);
  color: #fff; border: none; border-radius: 9px;
  padding: .5rem 1.2rem; font-size: .82rem; font-weight: 700;
  cursor: pointer; font-family: 'Neo Sans Arabic', 'Tajawal', sans-serif; white-space: nowrap;
  flex-shrink: 0; text-decoration: none;
  transition: all .25s; box-shadow: 0 4px 14px rgba(13,27,75,.25);
}
.pub-btn-register:hover { transform: translateY(-1px); box-shadow: 0 6px 20px rgba(13,27,75,.3); }

.pub-hamburger { display: none; background: none; border: none; cursor: pointer; padding: 4px; color: #374151; flex-shrink: 0; }

.pub-mobile-menu {
  padding: .75rem 1.5rem 1.25rem; border-top: 1px solid #e5e7eb;
  display: flex; flex-direction: column; gap: .2rem; background: #fff;
}
.pub-mob-link {
  padding: .6rem .875rem; color: #374151; font-size: .9rem; font-weight: 600;
  text-decoration: none; border-radius: 8px; display: flex; align-items: center;
  justify-content: space-between; background: none; border: none; cursor: pointer;
  font-family: 'Neo Sans Arabic', 'Tajawal', sans-serif; width: 100%; text-align: right;
}
.pub-mob-link:hover { background: #e8eaf6; color: #1a237e; }
.pub-mob-sub { padding-inline-start: 1rem; display: flex; flex-direction: column; gap: .15rem; margin-bottom: .25rem; }
.pub-mob-sub-link { padding: .45rem .875rem; color: #6b7280; font-size: .84rem; text-decoration: none; border-radius: 7px; font-weight: 600; }
.pub-mob-sub-link:hover { background: #e8eaf6; color: #1a237e; }
.pub-mob-cta { width: 100%; justify-content: center; margin-top: .75rem; border-radius: 9px; padding: .65rem 1.5rem; }

.pub-nb-actions { display: flex; align-items: center; gap: 1.4rem; flex-shrink: 0; }
.pub-nb-notif { position: relative; display: flex; align-items: center; justify-content: center; width: 42px; height: 42px; border-radius: 50%; cursor: pointer; color: #9aa1ad; transition: background .2s; }
.pub-nb-notif:hover { background: #f3f4f6; }
.pub-nb-notif-badge {
  position: absolute; top: 2px; inset-inline-end: 2px;
  background: #dc2626; color: #fff; font-size: .62rem; font-weight: 800;
  min-width: 16px; height: 16px; border-radius: 50%; display: flex; align-items: center; justify-content: center;
  padding: 0 3px; border: 2px solid #fff;
}
.pub-notif-dropdown { min-width: 300px; max-width: 340px; max-height: 380px; overflow-y: auto; padding: .4rem; }
.pub-notif-empty { padding: 1.5rem 1rem; text-align: center; font-size: .82rem; color: #9ca3af; }
.pub-notif-item {
  display: block; width: 100%; text-align: right; background: none; border: none; cursor: pointer;
  font-family: inherit; padding: .65rem .875rem; border-radius: 8px; margin-bottom: .15rem; transition: background .15s;
}
.pub-notif-item:hover { background: #f3f4f6; }
.pub-notif-item.unread { background: #eef2ff; }
.pub-notif-item.unread:hover { background: #e0e7ff; }
.pub-notif-item p { margin: 0 0 .25rem; font-size: .82rem; font-weight: 600; color: #1f2937; line-height: 1.5; white-space: normal; }
.pub-notif-item span { font-size: .72rem; color: #9ca3af; }
.pub-nb-user { display: flex; align-items: center; gap: .6rem; position: relative; cursor: pointer; padding: .3rem .5rem; border-radius: 9px; transition: background .2s; }
.pub-nb-user:hover { background: #f3f4f6; }
.pub-nb-avatar { width: 46px; height: 46px; border-radius: 50%; background: #00006e; color: #fff; display: flex; align-items: center; justify-content: center; font-size: 1.05rem; font-weight: 700; flex-shrink: 0; }
.pub-nb-name { font-size: .8rem; font-weight: 700; color: #0d1b4b; max-width: 120px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.pub-nb-logout { background: transparent; border: 1.5px solid #e5e7eb; border-radius: 7px; padding: .3rem .75rem; font-size: .75rem; color: #6b7280; cursor: pointer; font-family: inherit; transition: all .2s; }
.pub-nb-logout:hover { border-color: #c62828; color: #c62828; }

.pub-user-dropdown { min-width: 200px; padding: .4rem; }
.pub-dd-header { padding: .5rem .875rem .6rem; font-size: .8rem; font-weight: 800; color: #0d1b4b; border-bottom: 1px solid #e5e7eb; margin-bottom: .35rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.pub-dd-btn { display: flex; align-items: center; gap: .55rem; width: 100%; background: none; border: none; cursor: pointer; font-family: inherit; text-align: right; }
.pub-dd-btn.pub-dd-danger { color: #c62828; }
.pub-dd-btn.pub-dd-danger:hover { background: #fce4ec; color: #c62828; }

.pub-mob-actions { margin-top: .75rem; width: 100%; }
.pub-mob-user-wrap { margin-top: .5rem; border-top: 1px dashed #e5e7eb; padding-top: .5rem; display: flex; flex-direction: column; gap: .15rem; }
.pub-mob-user { display: flex; align-items: center; gap: .75rem; padding: .5rem .875rem; }
.pub-mob-user .pub-nb-name { max-width: none; flex: 1; font-size: .88rem; }

@media (max-width: 1100px) {
  .pub-nav-links { display: none; }
  .pub-hamburger { display: flex; }
  .pub-nb-actions { display: none; }
}
</style>

<script setup lang="ts">
// خريطة مقر الاتحاد في غزة: Leaflet + بلاطات OpenStreetMap (مجانية بدون مفتاح API).
// المؤشر أحمر بشكل مبنى، والنافذة المنبثقة فيها العنوان وأوقات الدوام (مفتوح/مغلق الآن) ورابط الاتجاهات.
import { onBeforeUnmount, onMounted, ref } from 'vue'
import 'leaflet/dist/leaflet.css'
import { Navigation, ExternalLink } from 'lucide-vue-next'

const HQ = { lat: 31.517535, lng: 34.438499 }
const NAME = 'اتحاد المقاولين الفلسطينيين'
const ADDRESS = 'غزة — الكتيبة — عمارة النجمة'
const GMAPS_LINK = 'https://maps.app.goo.gl/QmxW1VYAGmYBqRSC7'
const DIRECTIONS_LINK = `https://www.google.com/maps/dir/?api=1&destination=${HQ.lat},${HQ.lng}`

// الدوام: الأحد — الخميس، 8 ص — 4 م (توقيت غزة)
const OPEN_DAYS = [0, 1, 2, 3, 4] // Sun..Thu
const OPEN_FROM = 8
const OPEN_TO = 16

function isOpenNow() {
  const parts = new Intl.DateTimeFormat('en-US', { timeZone: 'Asia/Gaza', weekday: 'short', hour: 'numeric', hourCycle: 'h23' }).formatToParts(new Date())
  const wd = parts.find(p => p.type === 'weekday')?.value ?? ''
  const hour = Number(parts.find(p => p.type === 'hour')?.value ?? 0)
  const day = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'].indexOf(wd)
  return OPEN_DAYS.includes(day) && hour >= OPEN_FROM && hour < OPEN_TO
}

const BUILDING_SVG = '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 22V4a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v18Z"/><path d="M6 12H4a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h2"/><path d="M18 9h2a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2h-2"/><path d="M10 6h4"/><path d="M10 10h4"/><path d="M10 14h4"/><path d="M10 18h4"/></svg>'

const el = ref<HTMLDivElement | null>(null)
const open = ref(isOpenNow())
let map: any = null

function popupHtml() {
  const status = open.value
    ? '<span class="hq-pop-badge open">مفتوح الآن</span>'
    : '<span class="hq-pop-badge closed">مغلق الآن</span>'
  return `
    <div class="hq-pop" dir="rtl">
      <div class="hq-pop-head">
        <span class="hq-pop-ico">${BUILDING_SVG}</span>
        <div>
          <strong>${NAME}</strong>
          <small>المقر الرئيسي — ${ADDRESS}</small>
        </div>
      </div>
      <div class="hq-pop-hours">
        <div class="hq-pop-hours-top"><span>أوقات الدوام</span>${status}</div>
        <div class="hq-pop-row"><span>الأحد — الخميس</span><b dir="ltr">8:00 AM – 4:00 PM</b></div>
        <div class="hq-pop-row off"><span>الجمعة والسبت</span><b>عطلة</b></div>
      </div>
      <a class="hq-pop-dir" href="${DIRECTIONS_LINK}" target="_blank" rel="noopener noreferrer">احصل على الاتجاهات</a>
    </div>`
}

onMounted(async () => {
  const L = (await import('leaflet')).default
  if (!el.value) return
  map = L.map(el.value, { center: [HQ.lat, HQ.lng], zoom: 16, scrollWheelZoom: false, zoomControl: true, attributionControl: true })
  // CARTO basemaps صارت تطلب API key، فبنستخدم بلاطات OpenStreetMap الرسمية (مجانية مع ذكر المصدر)
  L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank">OpenStreetMap</a>',
  }).addTo(map)

  const icon = L.divIcon({
    className: 'hq-marker',
    html: `<span class="hq-marker-pulse"></span><span class="hq-marker-pin"><span class="hq-marker-ico">${BUILDING_SVG}</span></span>`,
    iconSize: [48, 60],
    iconAnchor: [24, 58],
    popupAnchor: [0, -54],
  })
  const marker = L.marker([HQ.lat, HQ.lng], { icon, title: NAME, riseOnHover: true }).addTo(map)
  marker.bindPopup(popupHtml(), { className: 'hq-popup', maxWidth: 300, minWidth: 250, autoPanPadding: [20, 20] })
  // الخريطة تفتح والنافذة ظاهرة، مع إزاحة المركز لتحت شوي عشان النافذة تبان كاملة
  map.setView([HQ.lat + 0.0012, HQ.lng], 16)
  marker.openPopup()
})

onBeforeUnmount(() => {
  map?.remove()
  map = null
})
</script>

<template>
  <section class="hq-map-section" aria-label="موقع مقر الاتحاد على الخريطة">
    <div class="hq-map-wrap">
      <div ref="el" class="hq-map" />
      <div class="hq-map-card">
        <span class="hq-map-card-label">مقر الاتحاد</span>
        <p class="hq-map-card-addr">{{ ADDRESS }}</p>
        <div class="hq-map-card-actions">
          <a :href="DIRECTIONS_LINK" target="_blank" rel="noopener noreferrer" class="hq-btn primary"><Navigation :size="15" /> الاتجاهات</a>
          <a :href="GMAPS_LINK" target="_blank" rel="noopener noreferrer" class="hq-btn"><ExternalLink :size="15" /> خرائط جوجل</a>
        </div>
      </div>
    </div>
  </section>
</template>

<style scoped>
.hq-map-section { padding: 0 1.5rem 5rem; }
.hq-map-wrap { position: relative; max-width: 1240px; margin: 0 auto; border-radius: 24px; overflow: hidden; border: 1.5px solid #e5e7eb; box-shadow: 0 18px 48px rgba(13, 27, 75, .12); }
.hq-map { height: 460px; width: 100%; background: #eef0f6; z-index: 0; }
.hq-map-card { position: absolute; z-index: 500; top: 1.25rem; right: 1.25rem; background: rgba(255, 255, 255, .96); backdrop-filter: blur(6px); border: 1px solid #e5e7eb; border-radius: 16px; padding: 1rem 1.15rem; box-shadow: 0 8px 24px rgba(13, 27, 75, .14); font-family: 'Tajawal', 'Cairo', sans-serif; max-width: 290px; direction: rtl; }
.hq-map-card-label { display: inline-block; background: #fdecea; color: #c62828; border-radius: 50px; padding: .15rem .7rem; font-size: .72rem; font-weight: 800; margin-bottom: .4rem; }
.hq-map-card-addr { font-size: .95rem; font-weight: 800; color: #0d1b3e; margin-bottom: .75rem; }
.hq-map-card-actions { display: flex; gap: .5rem; flex-wrap: wrap; }
.hq-btn { display: inline-flex; align-items: center; gap: .35rem; padding: .5rem .85rem; border-radius: 10px; font-size: .8rem; font-weight: 800; text-decoration: none; color: #1a237e; background: #e8eaf6; border: 1.5px solid #c5cae9; transition: all .2s; }
.hq-btn.primary { background: #c62828; border-color: #c62828; color: #fff; }
.hq-btn:hover { transform: translateY(-1px); box-shadow: 0 6px 16px rgba(13, 27, 75, .15); }

@media (max-width: 640px) {
  .hq-map-section { padding: 0 1rem 3rem; }
  .hq-map { height: 440px; }
  .hq-map-card { position: static; max-width: none; border: none; border-top: 1px solid #e5e7eb; border-radius: 0; box-shadow: none; padding: .9rem 1rem 1rem; }
}
</style>

<style>
/* المؤشر والنافذة يُنشآن خارج نطاق scoped (Leaflet يرسمهم مباشرة) */
.hq-marker { background: none; border: none; }
.hq-marker-pin { position: absolute; left: 4px; top: 0; width: 40px; height: 40px; border-radius: 50% 50% 50% 0; transform: rotate(-45deg); background: linear-gradient(135deg, #ef4444, #b71c1c); border: 3px solid #fff; box-shadow: 0 6px 16px rgba(183, 28, 28, .45); display: flex; align-items: center; justify-content: center; }
.hq-marker-ico { transform: rotate(45deg); color: #fff; display: flex; }
.hq-marker-pulse { position: absolute; left: 12px; top: 46px; width: 24px; height: 10px; border-radius: 50%; background: rgba(198, 40, 40, .35); animation: hq-pulse 1.8s ease-out infinite; }
@keyframes hq-pulse { 0% { transform: scale(.6); opacity: .9; } 100% { transform: scale(2.6); opacity: 0; } }

.hq-popup .leaflet-popup-content-wrapper { border-radius: 16px; padding: 0; box-shadow: 0 12px 32px rgba(13, 27, 75, .22); }
.hq-popup .leaflet-popup-content { margin: 0; width: auto !important; min-width: 250px; }
.hq-pop { font-family: 'Tajawal', 'Cairo', sans-serif; padding: .9rem 1rem 1rem; color: #374151; }
.hq-pop-head { display: flex; gap: .65rem; align-items: center; margin-bottom: .75rem; }
.hq-pop-head strong { display: block; color: #0d1b3e; font-size: .92rem; font-weight: 900; line-height: 1.4; }
.hq-pop-head small { display: block; color: #6b7280; font-size: .75rem; margin-top: .1rem; }
.hq-pop-ico { flex-shrink: 0; width: 38px; height: 38px; border-radius: 11px; background: #fdecea; color: #c62828; display: flex; align-items: center; justify-content: center; }
.hq-pop-hours { background: #f8fafc; border: 1px solid #e5e7eb; border-radius: 12px; padding: .6rem .75rem; margin-bottom: .75rem; }
.hq-pop-hours-top { display: flex; justify-content: space-between; align-items: center; font-size: .78rem; font-weight: 800; color: #1a237e; margin-bottom: .4rem; }
.hq-pop-badge { font-size: .68rem; font-weight: 800; border-radius: 50px; padding: .1rem .55rem; }
.hq-pop-badge.open { background: #e8f5e9; color: #2e7d32; }
.hq-pop-badge.closed { background: #fdecea; color: #c62828; }
.hq-pop-row { display: flex; justify-content: space-between; font-size: .8rem; padding: .15rem 0; }
.hq-pop-row b { color: #0d1b3e; font-weight: 800; }
.hq-pop-row.off b { color: #9ca3af; }
.hq-pop .hq-pop-dir { display: block; text-align: center; background: #c62828; color: #fff; border-radius: 10px; padding: .55rem; font-size: .82rem; font-weight: 800; text-decoration: none; }
.hq-pop .hq-pop-dir:hover { background: #b71c1c; color: #fff; }
.hq-popup .leaflet-popup-close-button { left: 6px; right: auto; top: 6px; }
</style>

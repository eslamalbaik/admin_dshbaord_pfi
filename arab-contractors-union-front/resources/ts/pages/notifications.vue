<script setup lang="ts">
import api from '@/plugins/axios'
import { notificationLink } from '@/utils/notificationLink'
import { notificationMessage, notificationMeta, relativeTimeAr } from '@/utils/notificationMeta'

definePage({
  meta: { requiresAdmin: true },
})

// ─── Types ────────────────────────────────────────────────────
interface NotificationItem {
  id: string
  title: string
  message: string
  time: string
  isSeen: boolean
  color: string
  icon: string
  financial: boolean
  link?: string
}

// ─── State ────────────────────────────────────────────────────
const page            = ref(1)
const notifications   = ref<NotificationItem[]>([])
const rawNotifications = ref<any[]>([])
const unreadCount     = ref(0)
const totalPages      = ref(1)
const isLoading       = ref(false)
const isError         = ref(false)
const markingAllRead  = ref(false)

// ─── Notification type map ────────────────────────────────────
// العنوان/الأيقونة/اللون من notificationMeta — نفس شكل الجرس والتنبيه المنبثق.
function mapRawNotification(n: any): NotificationItem {
  const d = n.data || {}
  const meta = notificationMeta(d)

  return {
    id:        n.id,
    title:     meta.title,
    message:   notificationMessage(d, meta.title),
    time:      relativeTimeAr(n.created_at),
    isSeen:    n.read_at !== null,
    color:     meta.color,
    icon:      meta.icon,
    financial: meta.financial,
    link:      d.link ?? undefined,
  }
}

// ─── Fetch ────────────────────────────────────────────────────
const router = useRouter()

async function fetchNotifications() {
  isLoading.value = true
  isError.value   = false
  try {
    const res = await api.get(`/api/v1/notifications?page=${page.value}`)

    // acu-api يغلّف الرد بـ { status, message, status_code, items }، و interceptor
    // الـ axios يفكّه فقط حين يكون `items` مصفوفة — وهنا كائن، فيبقى مستوى أدنى.
    // قراءة res.data مباشرة تُرجع undefined فتظهر الصفحة فارغة بلا أي خطأ.
    const payload    = res.data?.items ?? res.data
    const paginator  = payload?.notifications || {}
    const raw        = paginator.data || []
    unreadCount.value = payload?.unread_count ?? 0
    totalPages.value  = paginator.last_page ?? 1
    rawNotifications.value = raw
    notifications.value = raw.map(mapRawNotification)
  }
  catch (err) {
    console.error('Error fetching notifications:', err)
    isError.value = true
    notifications.value = []
    rawNotifications.value = []
  }
  finally {
    isLoading.value = false
  }
}

async function markAsRead(id: string) {
  try {
    await api.patch(`/api/v1/notifications/${id}/mark-as-read`)
    const item = notifications.value.find(n => n.id === id)
    if (item) {
      item.isSeen = true
      unreadCount.value = Math.max(0, unreadCount.value - 1)
    }
  }
  catch (err) { console.error(err) }
}

async function markAllRead() {
  markingAllRead.value = true
  try {
    await api.post('/api/v1/notifications/read')
    notifications.value.forEach(n => (n.isSeen = true))
    unreadCount.value = 0
  }
  catch (err) { console.error(err) }
  finally { markingAllRead.value = false }
}

function handleClick(item: NotificationItem) {
  if (!item.isSeen) markAsRead(item.id)

  // البحث عن البيانات الأصلية للإشعار
  const rawNotif = rawNotifications.value.find(n => n.id === item.id)
  if (!rawNotif) return

  const d = rawNotif.data || {}

  const target = notificationLink(d)
  if (target)
    router.push(target)
}

watch(page, fetchNotifications)
onMounted(fetchNotifications)
</script>

<template>
  <div style="font-family: Cairo, sans-serif;">
    <!-- ─── Header ─── -->
    <div class="d-flex align-center justify-space-between flex-wrap gap-4 mb-6">
      <div>
        <h4 class="text-h5 font-weight-bold d-flex align-center gap-2">
          <VIcon icon="tabler-bell" class="text-primary" />
          صندوق الإشعارات
          <VChip v-if="unreadCount > 0" color="error" size="small" class="ms-1">
            {{ unreadCount }}
          </VChip>
        </h4>
        <p class="text-body-2 text-medium-emphasis mt-1 mb-0">
          عرض وإدارة تنبيهات نشاط المقاولين والنظام
        </p>
      </div>

      <VBtn
        v-if="unreadCount > 0"
        variant="tonal"
        color="primary"
        prepend-icon="tabler-mail-opened"
        :loading="markingAllRead"
        @click="markAllRead"
      >
        تعليم الكل كمقروء
      </VBtn>
    </div>

    <!-- ─── Card ─── -->
    <VCard elevation="1">
      <VProgressLinear v-if="isLoading" indeterminate color="primary" />

      <!-- ERROR -->
      <VCardText v-if="!isLoading && isError" class="text-center py-14">
        <VIcon icon="tabler-alert-triangle" size="64" color="error" class="mb-3" />
        <p class="text-h6 text-medium-emphasis mb-4">تعذّر تحميل الإشعارات</p>
        <VBtn color="primary" variant="tonal" prepend-icon="tabler-refresh" @click="fetchNotifications">
          إعادة المحاولة
        </VBtn>
      </VCardText>

      <!-- EMPTY -->
      <VCardText
        v-else-if="!isLoading && notifications.length === 0"
        class="text-center py-14"
      >
        <VIcon icon="tabler-bell-off" size="64" color="secondary" class="mb-3 opacity-40" />
        <p class="text-h6 text-medium-emphasis mb-0">لا توجد إشعارات</p>
      </VCardText>

      <!-- LIST -->
      <VList v-else class="py-0">
        <template v-for="(item, index) in notifications" :key="item.id">
          <VDivider v-if="index > 0" />

          <VListItem
            link
            class="py-4 notif-row"
            :class="{ 'unread-item': !item.isSeen, 'financial-item': item.financial }"
            :style="{ '--notif-accent': item.color.startsWith('#') ? item.color : `rgb(var(--v-theme-${item.color}))` }"
            @click="handleClick(item)"
          >
            <!-- أيقونة -->
            <template #prepend>
              <VAvatar :color="item.color" variant="tonal" rounded="lg" class="me-4" size="44">
                <VIcon :icon="item.icon" size="22" />
              </VAvatar>
            </template>

            <!-- العنوان والرسالة -->
            <VListItemTitle class="mb-1 d-flex align-center gap-2" :class="item.isSeen ? 'font-weight-medium' : 'font-weight-bold'">
              <span v-if="item.financial" class="financial-tag">مالي</span>
              {{ item.title }}
              <VBadge v-if="!item.isSeen" dot inline color="error" />
            </VListItemTitle>
            <VListItemSubtitle class="text-body-2">
              {{ item.message }}
            </VListItemSubtitle>

            <!-- الوقت + زر القراءة -->
            <template #append>
              <div class="d-flex flex-column align-end gap-1">
                <span class="text-caption text-medium-emphasis">{{ item.time }}</span>
                <VBtn
                  v-if="!item.isSeen"
                  size="x-small"
                  variant="text"
                  color="primary"
                  @click.stop="markAsRead(item.id)"
                >
                  تعليم كمقروء
                </VBtn>
              </div>
            </template>
          </VListItem>
        </template>
      </VList>
    </VCard>

    <!-- ─── Pagination ─── -->
    <div v-if="totalPages > 1" class="d-flex justify-center mt-6">
      <VPagination v-model="page" :length="totalPages" :total-visible="$vuetify.display.xs ? 5 : 7" />
    </div>
  </div>
</template>

<style scoped>
.notif-row {
  position: relative;
}

.notif-row::before {
  position: absolute;
  background: transparent;
  content: "";
  inline-size: 4px;
  inset-block: 0;
  inset-inline-start: 0;
}

.unread-item {
  background-color: rgba(var(--v-theme-primary), 0.04);
}

.unread-item::before,
.financial-item::before {
  background: var(--notif-accent);
}

.financial-item {
  background-color: rgba(246, 196, 83, 0.1);
}

.financial-item.unread-item {
  background-color: rgba(246, 196, 83, 0.2);
}

.financial-tag {
  padding: 0 8px;
  border-radius: 999px;
  background: var(--notif-accent);
  color: #fff;
  font-size: 0.72rem;
  font-weight: 700;
  line-height: 1.6;
}
</style>

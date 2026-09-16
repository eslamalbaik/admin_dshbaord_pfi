<script setup lang="ts">
import api from '@/plugins/axios'

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
  link?: string
}

// ─── State ────────────────────────────────────────────────────
const page            = ref(1)
const notifications   = ref<NotificationItem[]>([])
const unreadCount     = ref(0)
const totalPages      = ref(1)
const isLoading       = ref(false)
const isError         = ref(false)
const markingAllRead  = ref(false)

// ─── Notification type map ────────────────────────────────────
function mapRawNotification(n: any): NotificationItem {
  const d = n.data || {}

  // تحديد العنوان والأيقونة واللون بحسب نوع الإشعار
  // المفاتيح هنا يجب أن تطابق حرفياً قيمة 'type' في app/Notifications/*.php —
  // أي مفتاح لا يطابق يسقط إلى 'general' فيضيع العنوان والأيقونة واللون.
  const typeMap: Record<string, { title: string; icon: string; color: string }> = {
    // طلبات المقاولين
    name_change_request_submitted:   { title: 'طلب تعديل اسم شركة', icon: 'tabler-signature', color: 'warning' },
    name_change_request_status:      { title: 'تحديث طلب تعديل الاسم', icon: 'tabler-signature', color: 'info' },
    profile_update_request_submitted: { title: 'طلب تعديل بيانات', icon: 'tabler-user-edit', color: 'warning' },
    profile_update_request_status:   { title: 'تحديث طلب تعديل البيانات', icon: 'tabler-user-edit', color: 'info' },
    certificate_request_submitted:   { title: 'طلب شهادة جديد', icon: 'tabler-certificate', color: 'warning' },
    certificate_request_status:      { title: 'تحديث طلب شهادة', icon: 'tabler-certificate', color: 'info' },
    // حركة مالية
    payment_submitted:   { title: 'إشعار تحويل بانتظار التأكيد', icon: 'tabler-cash', color: 'warning' },
    payment_confirmed:   { title: 'تم تأكيد الدفعة', icon: 'tabler-circle-check', color: 'success' },
    payment_rejected:    { title: 'رُفضت الدفعة', icon: 'tabler-credit-card-off', color: 'error' },
    // عضوية
    contractor_activated:            { title: 'تفعيل حساب مقاول', icon: 'tabler-user-check', color: 'info' },
    complete_profile:                { title: 'استكمال الملف الشخصي', icon: 'tabler-user-exclamation', color: 'secondary' },
    membership_expiry_reminder:      { title: 'العضوية على وشك الانتهاء', icon: 'tabler-clock-exclamation', color: 'warning' },
    membership_grace_period_reminder: { title: 'مهلة تجديد العضوية', icon: 'tabler-clock-off', color: 'error' },
    // فعاليات ودعم
    event_joined:                    { title: 'تسجيل حضور فعالية', icon: 'tabler-calendar-event', color: 'primary' },
    support_ticket_created:          { title: 'طلب دعم جديد', icon: 'tabler-lifebuoy', color: 'warning' },
    support_ticket_replied:          { title: 'رد على طلب دعم', icon: 'tabler-message-reply', color: 'info' },
    support_ticket_contractor_replied: { title: 'رد المقاول على طلب دعم', icon: 'tabler-message-reply', color: 'warning' },
    // نظام
    admin_broadcast:                 { title: 'إعلان من الاتحاد', icon: 'tabler-speakerphone', color: 'primary' },
    pma_rates_fetch_failed:          { title: 'تعذّر جلب أسعار الصرف', icon: 'tabler-alert-triangle', color: 'error' },
    // عام
    general:             { title: 'إشعار',                    icon: 'tabler-bell', color: 'primary' },
  }

  const matched = typeMap[d.type] ?? typeMap['general']

  // بناء نص الرسالة من بيانات الإشعار
  let message = d.message || ''
  if (!message) {
    if (d.contractor_name) message = `المقاول: ${d.contractor_name}`
    else if (d.amount)     message = `المبلغ: ${Number(d.amount).toLocaleString('ar-SA')} ₪`
    else                   message = matched.title
  }

  return {
    id:      n.id,
    title:   matched.title,
    message,
    time:    formatTime(n.created_at),
    isSeen:  n.read_at !== null,
    color:   matched.color,
    icon:    matched.icon,
    link:    d.link ?? undefined,
  }
}

function formatTime(dateStr: string): string {
  if (!dateStr) return ''
  const date = new Date(dateStr)
  const now  = new Date()
  const diff = now.getTime() - date.getTime()
  const mins = Math.floor(diff / 60000)
  if (mins < 1)   return 'الآن'
  if (mins < 60)  return `منذ ${mins} دقيقة`
  const hrs = Math.floor(mins / 60)
  if (hrs < 24)   return `منذ ${hrs} ساعة`
  const days = Math.floor(hrs / 24)
  if (days < 7)   return `منذ ${days} يوم`
  return date.toLocaleDateString('ar-SA', { year: 'numeric', month: 'short', day: 'numeric' })
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
    notifications.value = raw.map(mapRawNotification)
  }
  catch (err) {
    console.error('Error fetching notifications:', err)
    isError.value = true
    notifications.value = []
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
  if (item.link)    router.push(item.link)
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
            class="py-4"
            :class="!item.isSeen ? 'unread-item' : ''"
            @click="handleClick(item)"
          >
            <!-- أيقونة -->
            <template #prepend>
              <VAvatar :color="item.color" variant="tonal" class="me-4" size="42">
                <VIcon :icon="item.icon" size="22" />
              </VAvatar>
            </template>

            <!-- العنوان والرسالة -->
            <VListItemTitle class="font-weight-semibold mb-1 d-flex align-center gap-2">
              {{ item.title }}
              <VBadge v-if="!item.isSeen" dot inline color="primary" />
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
      <VPagination v-model="page" :length="totalPages" total-visible="7" />
    </div>
  </div>
</template>

<style scoped>
.unread-item {
  background-color: rgba(var(--v-theme-primary), 0.04);
}
</style>

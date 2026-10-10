<script lang="ts" setup>
import { notificationLink } from '@/utils/notificationLink'
import { notificationMessage, notificationMeta, relativeTimeAr } from '@/utils/notificationMeta'
import { computed } from 'vue'
import type { Notification } from '@layouts/types'
import { useRouter } from 'vue-router'
import { useNotifications } from '@/composables/useNotifications'

// Single source of truth — all fetching/caching/polling handled by Vue Query.
// This component performs ZERO manual axios calls.
const { rawNotifications, unreadCount, markRead, markReadMany } = useNotifications()

const router = useRouter()

// Derive the view-model reactively from the query cache. No local mirror state.
// العنوان/الأيقونة/اللون من notificationMeta حتى يطابق التنبيه المنبثق وصفحة الإشعارات.
const notifications = computed<Notification[]>(() =>
  rawNotifications.value.map(n => {
    const d = n.data || {}
    const meta = notificationMeta(d)

    return {
      id: n.id,
      title: meta.title,
      subtitle: notificationMessage(d, meta.title),
      time: relativeTimeAr(n.created_at),
      isSeen: n.read_at !== null,
      color: meta.color,
      icon: meta.icon,
      financial: meta.financial,
    } as Notification
  }),
)

const removeNotification = async (notificationId: string | number) => {
  await markRead(notificationId)
}

const onRead = async (notificationIds: (string | number)[]) => {
  await markReadMany(notificationIds)
}

// "Mark as unread" is a UI-only affordance; there is no backend endpoint to
// re-flag a read notification, so this is intentionally a no-op.
const onUnread = (_notificationIds: (string | number)[]) => {}

const handleNotificationClick = async (notification: Notification) => {
  if (!notification.isSeen)
    await markRead(notification.id)

  const dbN = rawNotifications.value.find(n => n.id === notification.id)
  if (!dbN || !dbN.data)
    return

  const d = dbN.data

  const target = notificationLink(d)
  if (target)
    router.push(target)
}
</script>

<template>
  <Notifications
    :notifications="notifications"
    :unread-count="unreadCount"
    @remove="removeNotification"
    @read="onRead"
    @unread="onUnread"
    @click:notification="handleNotificationClick"
  />
</template>

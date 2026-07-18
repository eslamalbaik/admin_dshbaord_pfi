<script lang="ts" setup>
import { computed } from 'vue'
import type { Notification } from '@layouts/types'
import { useRouter } from 'vue-router'
import { useNotifications } from '@/composables/useNotifications'

// Single source of truth — all fetching/caching/polling handled by Vue Query.
// This component performs ZERO manual axios calls.
const { rawNotifications, markRead, markReadMany } = useNotifications()

const router = useRouter()
const { t } = useI18n()

const formatTime = (dateStr: string) => {
  const date = new Date(dateStr)
  return date.toLocaleDateString(undefined, {
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}

// Derive the view-model reactively from the query cache. No local mirror state.
const notifications = computed<Notification[]>(() =>
  rawNotifications.value.map(n => {
    const d = n.data || {}
    let title = ''
    let subtitle = ''
    let color = 'primary'
    let icon = 'tabler-bell'

    if (d.type === 'course_rated') {
      title = 'notifications.course_rated_title'
      subtitle = 'notifications.course_rated_desc'
      color = 'primary'
      icon = 'tabler-star'
    }
    else if (d.type === 'course_commented') {
      title = 'notifications.course_commented_title'
      subtitle = 'notifications.course_commented_desc'
      color = 'warning'
      icon = 'tabler-message'
    }
    else if (d.type === 'course_completed') {
      title = 'notifications.course_completed_title'
      subtitle = 'notifications.course_completed_desc'
      color = 'success'
      icon = 'tabler-trophy'
    }
    else {
      title = 'notifications.title'
      subtitle = d.message || ''
    }

    return {
      id: n.id,
      title: t(title),
      subtitle: t(subtitle, {
        student: d.student_name || 'Student',
        course: d.course_title || '',
        rating: d.rating || '',
        lesson: d.lesson_title || '',
      }),
      time: formatTime(n.created_at),
      isSeen: n.read_at !== null,
      color,
      icon,
    } as any
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
  if (dbN && dbN.data && dbN.data.course_id)
    router.push(`/courses/${dbN.data.course_id}/builder`)
}
</script>

<template>
  <Notifications
    :notifications="notifications"
    @remove="removeNotification"
    @read="onRead"
    @unread="onUnread"
    @click:notification="handleNotificationClick"
  />
</template>

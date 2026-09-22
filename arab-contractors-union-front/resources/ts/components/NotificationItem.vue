<template>
  <v-card
    :class="['notification-item', { 'notification-item--unread': !notification.read_at }]"
    @click="handleClick"
  >
    <v-card-content class="pa-4">
      <div class="d-flex justify-space-between align-start gap-3">
        <!-- Icon/Indicator -->
        <div class="notification-icon">
          <v-icon v-if="!notification.read_at" small color="primary">mdi-circle-outline</v-icon>
          <v-icon v-else small color="grey-lighten-1">mdi-circle-medium</v-icon>
        </div>

        <!-- Content -->
        <div class="notification-content flex-grow-1">
          <div class="d-flex justify-space-between align-start gap-2 mb-2">
            <div>
              <h3 class="text-subtitle1 font-weight-medium">{{ notification.title }}</h3>
              <p class="text-body2 text-grey-darken-1 mb-2">{{ notification.body }}</p>
            </div>
            <div class="notification-timestamp text-caption text-grey">
              {{ formatDate(notification.created_at) }}
            </div>
          </div>

          <!-- Notification Type Badge -->
          <div v-if="notification.type" class="mb-3">
            <v-chip
              :color="getTypeColor(notification.type)"
              size="small"
              variant="tonal"
            >
              {{ getTypeLabel(notification.type) }}
            </v-chip>
          </div>

          <!-- Action Button -->
          <div v-if="notification.action_url" class="notification-actions">
            <v-btn
              size="small"
              variant="tonal"
              color="primary"
              @click.stop="$emit('navigate', notification)"
            >
              عرض التفاصيل
            </v-btn>
          </div>
        </div>

        <!-- Mark as Read Button -->
        <div class="notification-actions-menu">
          <v-menu>
            <template #activator="{ props }">
              <v-btn
                icon="mdi-dots-vertical"
                size="small"
                variant="text"
                v-bind="props"
                @click.stop
              ></v-btn>
            </template>
            <v-list>
              <v-list-item
                v-if="!notification.read_at"
                @click.stop="$emit('mark-as-read', notification.id)"
              >
                <v-icon start>mdi-check</v-icon>
                <v-list-item-title>وضع علامة كمقروء</v-list-item-title>
              </v-list-item>
              <v-list-item
                v-if="notification.action_url"
                @click.stop="$emit('navigate', notification)"
              >
                <v-icon start>mdi-open-in-new</v-icon>
                <v-list-item-title>فتح الرابط</v-list-item-title>
              </v-list-item>
            </v-list>
          </v-menu>
        </div>
      </div>
    </v-card-content>
  </v-card>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { type AppNotification } from '@/services/notificationService'
import { formatDistanceToNow } from 'date-fns'
import { ar } from 'date-fns/locale'

interface Props {
  notification: AppNotification
}

defineProps<Props>()

defineEmits<{
  'mark-as-read': [id: number]
  'navigate': [notification: AppNotification]
}>()

/**
 * Format notification timestamp
 */
const formatDate = (dateString: string): string => {
  try {
    return formatDistanceToNow(new Date(dateString), {
      addSuffix: true,
      locale: ar,
    })
  } catch {
    return dateString
  }
}

/**
 * Get notification type color
 */
const getTypeColor = (type: string): string => {
  const colors: Record<string, string> = {
    announcement: 'info',
    payment_reminder: 'warning',
    expiry_reminder: 'error',
  }
  return colors[type] || 'primary'
}

/**
 * Get notification type label
 */
const getTypeLabel = (type: string): string => {
  const labels: Record<string, string> = {
    announcement: 'إعلان',
    payment_reminder: 'تذكير الدفع',
    expiry_reminder: 'تنبيه الانتهاء',
  }
  return labels[type] || 'إشعار'
}

/**
 * Handle click on notification card
 */
const handleClick = (event: MouseEvent) => {
  // Only navigate if clicking on the main content area, not on buttons
  const target = event.target as HTMLElement
  if (!target.closest('button') && !target.closest('.notification-actions-menu')) {
    // Optionally emit navigate event or just let the action button handle it
  }
}
</script>

<style scoped>
.notification-item {
  cursor: pointer;
  border-left: 4px solid transparent;
  transition: all 0.2s ease;

  &:hover {
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
  }

  &--unread {
    background-color: rgba(33, 150, 243, 0.05);
    border-left-color: #2196f3;
    font-weight: 500;
  }
}

.notification-icon {
  flex-shrink: 0;
  padding-top: 0.25rem;
}

.notification-content {
  min-width: 0;
}

.notification-timestamp {
  white-space: nowrap;
  flex-shrink: 0;
}

.notification-actions {
  margin-top: 0.5rem;
}

.notification-actions-menu {
  flex-shrink: 0;
}
</style>

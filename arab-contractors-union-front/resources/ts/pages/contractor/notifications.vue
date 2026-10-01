<template>
  <div class="notifications-page">
    <v-container>
      <v-row>
        <v-col cols="12">
          <div class="d-flex justify-space-between align-center mb-4">
            <h1>الإشعارات</h1>
            <v-chip v-if="unreadCount > 0" color="error" text-color="white">
              {{ unreadCount }} جديد
            </v-chip>
          </div>
        </v-col>
      </v-row>

      <!-- Loading State -->
      <v-row v-if="loading" class="mt-4">
        <v-col cols="12">
          <div class="text-center">
            <v-progress-circular indeterminate color="primary"></v-progress-circular>
            <p class="mt-4">جاري التحميل...</p>
          </div>
        </v-col>
      </v-row>

      <!-- Empty State -->
      <v-row v-else-if="notifications.length === 0" class="mt-4">
        <v-col cols="12">
          <v-alert type="info" variant="outlined" class="text-center">
            <p>لا توجد إشعارات حالياً</p>
          </v-alert>
        </v-col>
      </v-row>

      <!-- Notifications List -->
      <v-row v-else class="mt-4">
        <v-col cols="12">
          <div class="notifications-list">
            <notification-item
              v-for="notification in notifications"
              :key="notification.id"
              :notification="notification"
              @mark-as-read="handleMarkAsRead"
              @navigate="handleNavigate"
            />
          </div>
        </v-col>
      </v-row>

      <!-- Pagination -->
      <v-row v-if="pagination.last_page > 1" class="mt-6 mb-4">
        <v-col cols="12" class="d-flex justify-center">
          <v-pagination
            v-model="currentPage"
            :length="pagination.last_page"
            @update:modelValue="handlePageChange"
          ></v-pagination>
        </v-col>
      </v-row>

      <!-- Error Alert -->
      <v-row v-if="error" class="mt-4">
        <v-col cols="12">
          <v-alert type="error" closable @update:modelValue="error = null">
            {{ error }}
          </v-alert>
        </v-col>
      </v-row>
    </v-container>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import notificationService, { type AppNotification } from '@/services/notificationService'
import NotificationItem from '@/components/NotificationItem.vue'

const notifications = ref<AppNotification[]>([])
const loading = ref(false)
const error = ref<string | null>(null)
const currentPage = ref(1)
const unreadCount = ref(0)

const pagination = ref({
  current_page: 1,
  last_page: 1,
  per_page: 15,
  total: 0,
})

/**
 * Fetch notifications for the current page
 */
const fetchNotifications = async (page: number = 1) => {
  loading.value = true
  error.value = null

  try {
    const response = await notificationService.fetchNotifications(page, pagination.value.per_page)
    notifications.value = response.items
    pagination.value = response.meta
    await fetchUnreadCount()
  } catch (err) {
    error.value = 'فشل تحميل الإشعارات. يرجى المحاولة لاحقاً.'
    console.error(err)
  } finally {
    loading.value = false
  }
}

/**
 * Fetch unread notification count
 */
const fetchUnreadCount = async () => {
  try {
    unreadCount.value = await notificationService.getUnreadCount()
  } catch (err) {
    console.error('Failed to fetch unread count:', err)
  }
}

/**
 * Handle marking a notification as read
 */
const handleMarkAsRead = async (notificationId: number) => {
  try {
    await notificationService.markAsRead(notificationId)
    // Refresh the list and unread count
    await fetchNotifications(currentPage.value)
  } catch (err) {
    error.value = 'فشل تحديث الإشعار. يرجى المحاولة لاحقاً.'
    console.error(err)
  }
}

/**
 * Handle navigating to notification action URL
 */
const handleNavigate = (notification: AppNotification) => {
  // Mark as read before navigating
  if (!notification.read_at) {
    handleMarkAsRead(notification.id)
  }

  // Navigate after a brief delay to ensure read is processed
  setTimeout(() => {
    notificationService.navigateToAction(notification)
  }, 300)
}

/**
 * Handle page change
 */
const handlePageChange = (page: number) => {
  currentPage.value = page
  fetchNotifications(page)
}

onMounted(() => {
  fetchNotifications(currentPage.value)
})
</script>

<style scoped>
.notifications-page {
  padding: 2rem 0;
}

.notifications-list {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}
</style>

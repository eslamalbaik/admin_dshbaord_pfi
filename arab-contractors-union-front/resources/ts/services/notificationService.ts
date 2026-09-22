/**
 * Notification Service — App Notifications (Announcements, Reminders)
 * Handles fetching and managing contractor notifications from the API
 */

import { api } from '@/utils/api'

export interface AppNotification {
  id: number
  contractor_id: number
  type: 'announcement' | 'payment_reminder' | 'expiry_reminder'
  title: string
  body: string
  reference_id?: number
  reference_type?: string
  action_url?: string
  read_at?: string | null
  created_at: string
  updated_at: string
}

export interface PaginatedNotifications {
  status: boolean
  message: string
  status_code: number
  items: AppNotification[]
  meta: {
    current_page: number
    last_page: number
    per_page: number
    total: number
  }
}

export interface UnreadCountResponse {
  status: boolean
  data: {
    unread_count: number
  }
}

class NotificationService {
  /**
   * Fetch paginated notifications for the authenticated contractor
   * GET /contractor/app-notifications
   */
  async fetchNotifications(page: number = 1, perPage: number = 15): Promise<PaginatedNotifications> {
    try {
      const response = await api.get<PaginatedNotifications>('/contractor/app-notifications', {
        params: {
          page,
          per_page: perPage,
        },
      })
      return response.data
    } catch (error) {
      console.error('Failed to fetch notifications:', error)
      throw error
    }
  }

  /**
   * Mark a specific notification as read
   * PATCH /contractor/app-notifications/{id}
   */
  async markAsRead(notificationId: number): Promise<AppNotification> {
    try {
      const response = await api.patch<{ status: boolean; data: AppNotification }>(`/contractor/app-notifications/${notificationId}`)
      return response.data.data
    } catch (error) {
      console.error(`Failed to mark notification ${notificationId} as read:`, error)
      throw error
    }
  }

  /**
   * Get count of unread notifications
   * GET /contractor/app-notifications/unread-count
   */
  async getUnreadCount(): Promise<number> {
    try {
      const response = await api.get<{ status: boolean; data: { unread_count: number } }>('/contractor/app-notifications/unread-count')
      return response.data.data.unread_count
    } catch (error) {
      console.error('Failed to fetch unread count:', error)
      return 0
    }
  }

  /**
   * Get notification by ID (for detail view)
   */
  async getNotification(notificationId: number): Promise<AppNotification | null> {
    try {
      const response = await api.get<{ status: boolean; data: AppNotification }>(`/contractor/app-notifications/${notificationId}`)
      return response.data.data
    } catch (error) {
      console.error(`Failed to fetch notification ${notificationId}:`, error)
      return null
    }
  }

  /**
   * Navigate to notification action URL
   */
  navigateToAction(notification: AppNotification): void {
    if (notification.action_url) {
      window.location.href = notification.action_url
    }
  }
}

export default new NotificationService()

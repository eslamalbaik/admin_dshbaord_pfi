/**
 * Notification Service — App Notifications (Announcements, Reminders)
 * Handles fetching and managing contractor notifications from the API.
 *
 * Uses the project's shared ofetch client ($api from utils/api.ts) — which
 * bakes in the API base URL and bearer auth. Response envelopes follow the
 * Laravel ApiResponseTrait shape: lists come back as { status, items, meta }
 * and single payloads under `items` (NOT axios-style `.data.data`).
 */

import { $api } from '@/utils/api'

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

class NotificationService {
  /**
   * Fetch paginated notifications for the authenticated contractor.
   * GET /contractor/app-notifications
   */
  async fetchNotifications(page = 1, perPage = 15): Promise<PaginatedNotifications> {
    return await $api<PaginatedNotifications>('/contractor/app-notifications', {
      query: { page, per_page: perPage },
    })
  }

  /**
   * Mark a specific notification as read.
   * PATCH /contractor/app-notifications/{id}
   */
  async markAsRead(notificationId: number): Promise<void> {
    await $api(`/contractor/app-notifications/${notificationId}`, { method: 'PATCH' })
  }

  /**
   * Get count of unread notifications.
   * GET /contractor/app-notifications/unread-count
   */
  async getUnreadCount(): Promise<number> {
    try {
      const res = await $api<{ status: boolean; items: { unread_count: number } }>(
        '/contractor/app-notifications/unread-count',
      )

      return res.items.unread_count
    }
    catch (error) {
      console.error('Failed to fetch unread count:', error)

      return 0
    }
  }

  /**
   * Navigate to a notification's action URL, if any.
   */
  navigateToAction(notification: AppNotification): void {
    if (notification.action_url)
      window.location.href = notification.action_url
  }
}

export default new NotificationService()

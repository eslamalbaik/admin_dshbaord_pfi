import { computed } from 'vue'
import { useQuery, useMutation, useQueryClient } from '@tanstack/vue-query'
import api from '@/plugins/axios'

/**
 * Single source of truth for admin notifications.
 *
 * All fetching/caching/refetching is delegated to TanStack Vue Query —
 * there is NO manual axios polling, NO AbortController, NO custom cache.
 * Components consume this composable and never touch the network directly.
 */

export interface RawNotification {
  id: string | number
  type: string
  data: Record<string, any>
  read_at: string | null
  created_at: string
}

interface NotificationsResponse {
  notifications: {
    data: RawNotification[]
    current_page: number
    last_page: number
    total: number
  }
  unread_count: number
}

export const NOTIFICATIONS_KEY = ['admin-notifications'] as const

export function useNotifications() {
  const queryClient = useQueryClient()

  // ─── Query: the ONLY place notifications are fetched ────────────────────────
  const query = useQuery({
    queryKey: NOTIFICATIONS_KEY,
    queryFn: async (): Promise<NotificationsResponse> => {
      const res = await api.get('/api/v1/notifications')

      // acu-api wraps every response as { status, message, status_code, items }.
      // The axios interceptor only mirrors `items` → `data` when `items` is an
      // ARRAY; here it is an object ({ notifications, unread_count }), so it is
      // left untouched and the payload stays one level down. Unwrap it here —
      // reading res.data directly yields undefined and silently degrades to an
      // empty list with a zero unread count (empty bell + hidden badge).
      return res.data?.items ?? res.data
    },
    staleTime: 30000, // 30s — treat data as fresh, dedupes rapid mounts
    refetchInterval: 60000, // poll once a minute
    refetchIntervalInBackground: false, // pause polling when tab is hidden
    refetchOnWindowFocus: true, // refetch when user returns to the tab
    retry: 1,
  })

  const rawNotifications = computed<RawNotification[]>(
    () => query.data.value?.notifications?.data ?? [],
  )
  const unreadCount = computed<number>(
    () => query.data.value?.unread_count ?? 0,
  )

  // ─── Mutation: mark a single notification as read ───────────────────────────
  // On success we invalidate the query so Vue Query refetches once — no manual
  // refetch chains, no duplicate requests.
  const markReadMutation = useMutation({
    mutationFn: async (id: string | number) => {
      await api.patch(`/api/v1/notifications/${id}/mark-as-read`)
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: NOTIFICATIONS_KEY })
    },
  })

  const markAllReadMutation = useMutation({
    mutationFn: async () => {
      await api.post('/api/v1/notifications/read')
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: NOTIFICATIONS_KEY })
    },
  })

  // Mark several notifications read with a SINGLE refetch afterwards.
  // PATCH calls fire in parallel; the cache is invalidated exactly once —
  // this is what eliminates the previous "loop + full refetch" request storm.
  const markReadMany = async (ids: (string | number)[]) => {
    if (!ids.length)
      return
    await Promise.all(
      ids.map(id => api.patch(`/api/v1/notifications/${id}/mark-as-read`)),
    )
    queryClient.invalidateQueries({ queryKey: NOTIFICATIONS_KEY })
  }

  return {
    rawNotifications,
    unreadCount,
    isLoading: query.isLoading,
    isError: query.isError,
    isFetching: query.isFetching,
    refetch: query.refetch,
    markRead: (id: string | number) => markReadMutation.mutateAsync(id),
    markReadMany,
    markAllRead: () => markAllReadMutation.mutateAsync(),
  }
}

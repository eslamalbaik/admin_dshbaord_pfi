import { defineStore } from 'pinia'
import { ref, shallowRef } from 'vue'
import api from '@/plugins/axios'

export const useQuizResultsStore = defineStore('quizResults', () => {
  const attempts = shallowRef<any[]>([])
  const summary = ref<any>(null)
  
  const isLoading = ref(false)
  const isSummaryLoading = ref(false)
  const error = ref<string | null>(null)
  const summaryError = ref<string | null>(null)
  
  const nextCursor = ref<string | null>(null)
  const snapshotTime = ref<string | null>(null)

  const fetchAttempts = async (cursor: string | null = null, search: string = '', force = false) => {
    // If not paginating/searching and already loaded, cache the first page
    if (!cursor && !search && attempts.value.length > 0 && !force) {
      return
    }

    isLoading.value = true
    error.value = null

    // Lock snapshot time on first page load to avoid cursor drift under writes
    if (!cursor) {
      snapshotTime.value = new Date().toISOString().slice(0, 19).replace('T', ' ')
      attempts.value = []
    }

    try {
      const response = await api.get('/api/v1/dashboard/quizzes/attempts/history', {
        params: {
          cursor,
          snapshot_time: snapshotTime.value,
          search: search || undefined
        }
      })

      const data = response?.data || {}
      const newAttempts = data?.data || []
      
      attempts.value = cursor ? [...attempts.value, ...newAttempts] : newAttempts
      nextCursor.value = data?.next_cursor || null
    } catch (err: any) {
      error.value = err?.message || 'Failed to fetch quiz attempts history'
      console.error(err)
    } finally {
      isLoading.value = false
    }
  }

  const modalAttempts = ref<any[]>([])
  const isModalLoading = ref(false)
  const modalError = ref<string | null>(null)

  const fetchModalAttempts = async (userId: number, quizId: number) => {
    isModalLoading.value = true
    modalError.value = null
    modalAttempts.value = []

    console.log('fetchModalAttempts initiated for user:', userId, 'and quiz:', quizId)

    try {
      const response = await api.get('/api/v1/dashboard/quizzes/attempts/history', {
        params: {
          user_id: userId,
          quiz_id: quizId
        }
      })
      console.log('fetchModalAttempts response received:', response?.data)
      modalAttempts.value = response?.data?.data || []
    } catch (err: any) {
      modalError.value = err?.message || 'Failed to fetch attempts history'
      console.error('fetchModalAttempts failed:', err)
    } finally {
      isModalLoading.value = false
    }
  }

  const fetchSummary = async (quizId: number) => {
    isSummaryLoading.value = true
    summaryError.value = null

    try {
      const response = await api.get(`/api/v1/dashboard/quizzes/${quizId}/summary`)
      summary.value = response?.data || null
    } catch (err: any) {
      summaryError.value = err?.message || 'Failed to fetch quiz summary'
      console.error(err)
    } finally {
      isSummaryLoading.value = false
    }
  }

  return {
    attempts,
    summary,
    isLoading,
    isSummaryLoading,
    error,
    summaryError,
    nextCursor,
    fetchAttempts,
    fetchSummary,
    modalAttempts,
    isModalLoading,
    modalError,
    fetchModalAttempts
  }
})

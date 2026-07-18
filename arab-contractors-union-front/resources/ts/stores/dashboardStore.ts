import { defineStore } from 'pinia'
import { ref, shallowRef } from 'vue'
import api from '@/plugins/axios'

export const useDashboardStore = defineStore('dashboard', () => {
  const stats = ref({
    total_contractors: 0,
    active_memberships: 0,
    pending_requests: 0,
    total_revenue: 0,
    expiring_soon: 0,
  })

  const latestContractors = shallowRef<any[]>([])
  const latestPayments = shallowRef<any[]>([])
  const revenueChart = shallowRef<number[]>([])

  const isLoading = ref(false)
  const error = ref<string | null>(null)
  const isLoaded = ref(false)

  const fetchDashboardData = async (forceRefresh = false) => {
    if (isLoaded.value && !forceRefresh)
      return

    isLoading.value = true
    error.value = null

    try {
      const response = await api.get('/api/v1/dashboard/stats')
      const data = response?.data || {}

      stats.value = data?.stats || {
        total_contractors: 0,
        active_memberships: 0,
        pending_requests: 0,
        total_revenue: 0,
        expiring_soon: 0,
          }

      latestContractors.value = data?.latestContractors?.length ? data.latestContractors : []
      latestPayments.value = data?.latestPayments?.length ? data.latestPayments : []
      revenueChart.value = data?.revenueChart?.length ? data.revenueChart : []

      isLoaded.value = true
    }
    catch (err: any) {
      error.value = err?.message || 'Failed to fetch dashboard data'
      console.error('Error fetching dashboard data:', err)

      stats.value = { total_contractors: 0, active_memberships: 0, pending_requests: 0, total_revenue: 0, expiring_soon: 0 }
      latestContractors.value = []
      latestPayments.value = []
      revenueChart.value = []
    }
    finally {
      isLoading.value = false
    }
  }

  return {
    stats,
    latestContractors,
    latestPayments,
    revenueChart,
    isLoading,
    error,
    isLoaded,
    fetchDashboardData,
  }
})

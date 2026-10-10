import { computed } from 'vue'
import { useQuery } from '@tanstack/vue-query'
import api from '@/plugins/axios'

/**
 * Live currency exchange rates (top-bar widget) — REQ-17.
 * All fetching/caching/polling delegated to TanStack Vue Query, mirrors useNotifications.ts.
 */

export interface RawExchangeRate {
  rate_to_jod: number
  fetched_at: string
  source: string
}

interface ExchangeRatesResponse {
  latest: Record<string, RawExchangeRate | null>
  history: any[]
}

const EXCHANGE_RATES_KEY = ['admin-exchange-rates'] as const

export function useExchangeRates() {
  const query = useQuery({
    queryKey: EXCHANGE_RATES_KEY,
    queryFn: async (): Promise<ExchangeRatesResponse> => {
      const res = await api.get('/api/v1/dashboard/exchange-rates')
      return res.data.items
    },
    staleTime: 300000, // 5 min
    // Rates are fetched from the external API once a day (plus rare manual overrides),
    // so polling every 10 minutes is plenty; a manual override in this tab refetches itself.
    refetchInterval: 600000,
    refetchIntervalInBackground: false,
    refetchOnWindowFocus: true,
    retry: 1,
  })

  const ils = computed<RawExchangeRate | null>(() => query.data.value?.latest?.ILS ?? null)
  const usd = computed<RawExchangeRate | null>(() => query.data.value?.latest?.USD ?? null)

  return {
    ils,
    usd,
    isLoading: query.isLoading,
    isError: query.isError,
    refetch: query.refetch,
  }
}

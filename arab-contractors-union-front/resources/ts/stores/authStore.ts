import { defineStore } from 'pinia'
import axiosIns from '@/plugins/axios'
import { normalizeUser } from '@/utils/normalizeUser'

export interface User {
  id: number
  name: string
  email: string
  role: string
  avatar?: string
  [key: string]: any
}

let fetchPromise: Promise<void> | null = null

const getStoredUser = (): User | null => {
  if (typeof localStorage === 'undefined')
    return null
  const stored = localStorage.getItem('userData')
  if (!stored)
    return null
  try {
    return normalizeUser(JSON.parse(stored))
  }
  catch {
    return null
  }
}

// Helper to check token existence (kept for compatibility)
const hasStoredToken = (): boolean => {
  return typeof localStorage !== 'undefined' && !!localStorage.getItem('accessToken')
}

export const useAuthStore = defineStore('auth', {
  state: () => {
    const storedToken = typeof localStorage !== 'undefined' ? localStorage.getItem('accessToken') : null
    // Only show loading screen if there is a stored token that needs validation.
    // If there is no token, the user is a guest — go straight to login with no delay.
    const hasToken = !!storedToken
    return {
      token: storedToken,
      user: getStoredUser(),
      isLoggedIn: hasToken,
      isInitialized: !hasToken,
      isInitializing: hasToken,
    }
  },
  getters: {
    userRole: state => state.user?.role || null,
  },
  actions: {
    fetchUser() {
      if (this.isInitialized) {
        return Promise.resolve()
      }
      this.isInitializing = true
      if (fetchPromise)
        return fetchPromise

      if (!hasStoredToken()) {
        this.user = null
        this.isLoggedIn = false
        this.isInitialized = true
        this.isInitializing = false
        return Promise.resolve()
      }

      fetchPromise = axiosIns.get('/api/v1/user')
        .then(response => {
          const normalized = normalizeUser(response.data)
          if (!normalized) {
            throw new Error('Invalid user payload')
          }
          this.user = normalized
          this.isLoggedIn = true
          if (typeof localStorage !== 'undefined') {
            localStorage.setItem('userData', JSON.stringify(normalized))
          }
        })
        .catch(() => {
          this.user = null
          this.isLoggedIn = false
          if (typeof localStorage !== 'undefined') {
            localStorage.removeItem('accessToken')
            localStorage.removeItem('userData')
          }
        })
        .finally(() => {
          this.isInitialized = true
          this.isInitializing = false
          fetchPromise = null
        })

      return fetchPromise
    },
    login(user: User, token: string) {
      const normalized = normalizeUser(user)
      if (!normalized)
        return

      this.user = normalized
      this.token = token
      this.isLoggedIn = true
      this.isInitialized = true
      this.isInitializing = false
      if (typeof localStorage !== 'undefined') {
        localStorage.setItem('accessToken', token)
        localStorage.setItem('userData', JSON.stringify(normalized))
      }
    },
    async revokeSession() {
      if (typeof localStorage !== 'undefined' && localStorage.getItem('accessToken')) {
        try {
          await axiosIns.post('/api/v1/auth/logout')
        }
        catch {
          // Token may already be revoked
        }
      }
      this.logout()
    },
    clearAuth() {
      this.token = null
      this.user = null
      this.isLoggedIn = false
      this.isInitialized = true
      this.isInitializing = false
      fetchPromise = null
      if (typeof localStorage !== 'undefined') {
        localStorage.removeItem('accessToken')
        localStorage.removeItem('userData')
      }
    },
    logout() {
      // Alias for clearAuth to maintain existing calls
      this.clearAuth()
    },
  },
})

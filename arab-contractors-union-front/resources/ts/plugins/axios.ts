import axios from 'axios'

const apiBaseUrl = import.meta.env.VITE_API_BASE_URL || 'http://127.0.0.1:8000'

const axiosIns = axios.create({
  baseURL: apiBaseUrl,
  // 20s — `php artisan serve` rebuilds config/routes on a cold first request,
  // so cold login (~4s) + CORS preflight (~1s) can spike. 8s was too tight.
  timeout: 20000,
  headers: {
    'Accept': 'application/json',
    'Content-Type': 'application/json',
  }
})

// Reads the admin UI language from the `language` cookie (set by vue-i18n).
function currentLocale(): string {
  if (typeof document === 'undefined')
    return 'en'
  const match = document.cookie.match(/(?:^|;\s*)language=([^;]+)/)
  const lang = match ? decodeURIComponent(match[1]).slice(0, 2) : 'en'
  return lang === 'ar' ? 'ar' : 'en'
}

// Request Interceptor
axiosIns.interceptors.request.use(config => {
  if (typeof localStorage !== 'undefined') {
    const token = localStorage.getItem('accessToken')
    if (token) {
      config.headers.Authorization = `Bearer ${token}`
    }
  }
  // Backend returns validation/error messages in this language.
  config.headers['X-Locale'] = currentLocale()

  // When sending FormData (file uploads), remove Content-Type so axios sets
  // multipart/form-data with the correct boundary automatically.
  if (config.data instanceof FormData) {
    delete config.headers['Content-Type']
  }

  return config
})

// acu-api wraps every response as { status, message, status_code, items, meta }
// instead of Laravel's classic paginator shape ({ data, total, current_page, ... }).
// Pages across the app were written against the classic shape (`data.data`,
// `data.total`). Rather than touching every page, normalize the envelope here
// so both shapes work: mirror `items` → `data` and flatten `meta` fields onto
// the response body, without removing the original `items`/`meta` keys.
function normalizeAcuApiEnvelope(data: any) {
  if (!data || typeof data !== 'object' || Array.isArray(data))
    return data

  if (Array.isArray(data.items) && data.data === undefined)
    data.data = data.items

  if (data.meta && typeof data.meta === 'object') {
    for (const key of ['total', 'current_page', 'last_page', 'per_page']) {
      if (data.meta[key] !== undefined && data[key] === undefined)
        data[key] = data.meta[key]
    }
  }

  // Some pages read `data.items` off a single-record response expecting the
  // record itself; leave `items` as-is when it isn't an array (single object).
  return data
}

// Response Interceptor
axiosIns.interceptors.response.use(
  response => {
    response.data = normalizeAcuApiEnvelope(response.data)
    return response
  },
  error => {
    // Handle 401 Unauthorized errors (session expired)
    if (error.response && error.response.status === 401) {
      // If the 401 comes from the /api/v1/user check, it's just a guest checking auth state. Do not redirect.
      if (error.config && (error.config.url === '/api/v1/user' || error.config.url?.endsWith('/api/v1/user'))) {
        return Promise.reject(error)
      }

      // Clear auth state in store
      import('@/stores/authStore')
        .then(({ useAuthStore }) => {
          useAuthStore().logout()
        })
        .catch(() => {
          // Ignore if store not available
        })

      if (typeof localStorage !== 'undefined') {
        // FTR-001: Persist a flag so the login page can show "session terminated" message
        const isLoginPage = window.location.pathname === '/login' || window.location.pathname === '/admin/login'
        if (!isLoginPage) {
          localStorage.setItem('session_terminated', '1')
        }
        localStorage.removeItem('accessToken')
        localStorage.removeItem('userData')
      }

      // Redirect to the login page. The app has a single login route (/admin/login);
      // there is no bare "/login" route, so always target /admin/login to avoid a 404.
      const currentPath = window.location.pathname
      const targetLoginPath = '/admin/login'

      if (currentPath !== '/login' && currentPath !== '/admin/login') {
        window.location.href = targetLoginPath
      }
    }

    return Promise.reject(error)
  }
)

export default axiosIns

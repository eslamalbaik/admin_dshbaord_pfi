import { createApp } from 'vue'

import App from '@/App.vue'
import { registerPlugins } from '@core/utils/plugins'

// Styles
import '@core-scss/template/index.scss'
import '@styles/styles.scss'
import { VueQueryPlugin } from '@tanstack/vue-query'

// Create vue app
const app = createApp(App)

// Register plugins
registerPlugins(app)

// Register Vue Query — default staleTime keeps recently-fetched page data fresh
// across route navigations instead of refetching (and showing a loading spinner)
// every time a table/dashboard page is revisited.
app.use(VueQueryPlugin, {
  queryClientConfig: {
    defaultOptions: {
      queries: {
        staleTime: 30 * 1000,
        refetchOnWindowFocus: false,
      },
    },
  },
})

// Mount vue app immediately (Reactive Gating handles the splash screen)
app.mount('#app')

// Trigger fetchUser in the background to initialize auth state
import { useAuthStore } from '@/stores/authStore'
const authStore = useAuthStore()
authStore.fetchUser()

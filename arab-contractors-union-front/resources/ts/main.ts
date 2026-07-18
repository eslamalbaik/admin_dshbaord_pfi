import { createApp } from 'vue'

import App from '@/App.vue'
import { registerPlugins } from '@core/utils/plugins'

// Styles
import '@core-scss/template/index.scss'
import '@styles/styles.scss'
import { VueQueryPlugin } from '@tanstack/vue-query'
import 'iconify-icon'

// Create vue app
const app = createApp(App)

// Register plugins
registerPlugins(app)

// Register Vue Query
app.use(VueQueryPlugin)

// Mount vue app immediately (Reactive Gating handles the splash screen)
app.mount('#app')

// Trigger fetchUser in the background to initialize auth state
import { useAuthStore } from '@/stores/authStore'
const authStore = useAuthStore()
authStore.fetchUser()

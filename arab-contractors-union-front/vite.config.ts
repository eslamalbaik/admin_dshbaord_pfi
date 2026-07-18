import { fileURLToPath } from 'node:url'
import { transform } from 'esbuild'
import VueI18nPlugin from '@intlify/unplugin-vue-i18n/vite'
import tailwindcss from '@tailwindcss/vite'
import react from '@vitejs/plugin-react'
import vue from '@vitejs/plugin-vue'
import vueJsx from '@vitejs/plugin-vue-jsx'
import AutoImport from 'unplugin-auto-import/vite'
import Components from 'unplugin-vue-components/vite'
import { VueRouterAutoImports, getPascalCaseRouteName } from 'unplugin-vue-router'
import VueRouter from 'unplugin-vue-router/vite'
import { defineConfig } from 'vite'
import MetaLayouts from 'vite-plugin-vue-meta-layouts'
import vuetify from 'vite-plugin-vuetify'
import svgLoader from 'vite-svg-loader'
import { visualizer } from 'rollup-plugin-visualizer'

// https://vitejs.dev/config/
export default defineConfig({
  plugins: [
    // ✅ Tailwind v4 MUST be first
    tailwindcss(),
    // ℹ️ This plugin should be placed before vue plugin
    VueRouter({
      getRouteName: routeNode => {
        // Convert pascal case to kebab case
        return getPascalCaseRouteName(routeNode)
          .replace(/([a-z\d])([A-Z])/g, '$1-$2')
          .toLowerCase()
      },
      routesFolder: 'resources/ts/pages',
    }),
    // --- الحل الجذري والنهائي لملفات React ---
    {
      name: 'force-react-compiler',
      enforce: 'pre',
      async transform(code, id) {
        // نستهدف فقط مجلد landing وأي ملف داخله ينتهي بـ tsx أو jsx
        const cleanId = id.split('?')[0] // Clean query params like ?t=123
        if (cleanId.includes('/landing/') && (cleanId.endsWith('.tsx') || cleanId.endsWith('.jsx'))) {
          const result = await transform(code, {
            loader: cleanId.endsWith('.tsx') ? 'tsx' : 'jsx',
            jsx: 'automatic',
            sourcemap: true
          })
          return {
            code: result.code,
            map: result.map || null
          }
        }
      }
    },
    // ------------------------------------------

    vue({
      template: {
        transformAssetUrls: {
          base: null,
          includeAbsolute: false,
        },
      },
    }),
    // 1. نجعل Vue تتجاهل مجلد صفحة الهبوط تماماً
    vueJsx({
      exclude: [/resources\/ts\/landing\/.*/],
    }),
    vuetify({
      autoImport: true,
      styles: {
        configFile: 'resources/styles/variables/_vuetify.scss',
      },
    }),
    MetaLayouts({
      target: './resources/ts/layouts',
      defaultLayout: 'default',
    }),
    Components({
      dirs: ['resources/ts/@core/components', 'resources/ts/components'],
      dts: true,
      resolvers: [
        componentName => {
          // Auto import `VueApexCharts`
          if (componentName === 'VueApexCharts')
            return { name: 'default', from: 'vue3-apexcharts', as: 'VueApexCharts' }
        },
      ],
    }),
    AutoImport({
      imports: ['vue', VueRouterAutoImports, '@vueuse/core', '@vueuse/math', 'vue-i18n', 'pinia'],
      dirs: [
        './resources/ts/@core/utils',
        './resources/ts/@core/composable/',
        './resources/ts/composables/',
        './resources/ts/utils/',
        './resources/ts/plugins/*/composables/*',
      ],
      vueTemplate: true,

      // ℹ️ Disabled to avoid confusion & accidental usage
      ignore: ['useCookies', 'useStorage'],
    }),
    VueI18nPlugin({
      runtimeOnly: true,
      compositionOnly: true,
      include: [
        fileURLToPath(new URL('./resources/ts/plugins/i18n/locales/**', import.meta.url)),
      ],
    }),
    svgLoader(),
    visualizer({
      filename: 'stats.html',
      open: false,
      gzipSize: true,
      brotliSize: true,
    }),
  ],
  define: { 'process.env': {} },
  resolve: {
    alias: {
      '@core-scss': fileURLToPath(new URL('./resources/styles/@core', import.meta.url)),
      '@': fileURLToPath(new URL('./resources/ts', import.meta.url)),
      '@themeConfig': fileURLToPath(new URL('./themeConfig.ts', import.meta.url)),
      '@core': fileURLToPath(new URL('./resources/ts/@core', import.meta.url)),
      '@layouts': fileURLToPath(new URL('./resources/ts/@layouts', import.meta.url)),
      '@images': fileURLToPath(new URL('./resources/images/', import.meta.url)),
      '@styles': fileURLToPath(new URL('./resources/styles/', import.meta.url)),
      '@configured-variables': fileURLToPath(new URL('./resources/styles/variables/_template.scss', import.meta.url)),
    },
  },
  build: {
    chunkSizeWarningLimit: 5000,
    minify: 'esbuild',
    rollupOptions: {
      treeshake: true,
      output: {
        manualChunks(id) {
          if (id.includes('node_modules')) {
            if (id.includes('vuetify'))
              return 'vendor_vuetify'
            if (id.includes('vue-router') || id.includes('@vue/'))
              return 'vendor_vue'
            if (id.includes('pinia'))
              return 'vendor_state'
            if (id.includes('apexcharts') || id.includes('vue3-apexcharts'))
              return 'vendor_charts'
            if (id.includes('@intlify') || id.includes('vue-i18n'))
              return 'vendor_i18n'
            if (id.includes('react') || id.includes('framer-motion') || id.includes('lucide-react'))
              return 'vendor_react_landing'
            if (id.includes('mapbox-gl'))
              return 'vendor_mapbox'
            if (id.includes('@fullcalendar'))
              return 'vendor_calendar'
            return 'vendor_core'
          }
        }
      }
    }
  },
  css: {
    devSourcemap: false,
  },
  // ─── Pre-transform the most-visited admin pages at server startup ──────────
  // Vite compiles these (and their imports) before the first navigation so the
  // section-to-section switch is instant instead of compiling on demand.
  server: {
    warmup: {
      clientFiles: [
        './resources/ts/App.vue',
        './resources/ts/pages/dashboards/lms.vue',
        './resources/ts/pages/courses/index.vue',
        './resources/ts/pages/courses/create.vue',
        './resources/ts/pages/courses/[id]/builder.vue',
        './resources/ts/pages/certificates/index.vue',
        './resources/ts/pages/notifications.vue',
        './resources/ts/layouts/components/NavBarNotifications.vue',
      ],
    },
  },
  optimizeDeps: {
    esbuildOptions: {
      sourcemap: false,
    },
    // Explicitly pre-bundle every heavy dependency. Without this, Vite serves
    // each module as a separate on-demand request (the 237-request / 34s cold
    // start). Pre-bundling collapses them into a handful of cached bundles.
    include: [
      'vue',
      'vue-router',
      'pinia',
      'vuetify',
      'axios',
      'vue-i18n',
      '@tanstack/vue-query',
      '@vueuse/core',
      '@vueuse/math',
      'apexcharts',
      'vue3-apexcharts',
      'chart.js',
      'swiper',
      '@casl/ability',
      '@casl/vue',
      '@tiptap/vue-3',
      '@tiptap/starter-kit',
      'nprogress',
      'jwt-decode',
    ],
    // Only crawl Vue source for dep discovery — exclude the React landing
    // micro-frontend so React/framer-motion aren't pulled into the admin scan.
    entries: [
      './resources/ts/**/*.vue',
    ],
  },
})

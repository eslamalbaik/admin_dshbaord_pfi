<script lang="ts" setup>
import allNavItems from '@/navigation/horizontal'
import { useAuthStore } from '@/stores/authStore'
import { canAccessRoute } from '@/utils/permissions'

import { themeConfig } from '@themeConfig'

// Components
import CurrencyRatesWidget from '@/layouts/components/CurrencyRatesWidget.vue'
import Footer from '@/layouts/components/Footer.vue'
import NavBarNotifications from '@/layouts/components/NavBarNotifications.vue'
import NavSearchBar from '@/layouts/components/NavSearchBar.vue'
import NavbarShortcuts from '@/layouts/components/NavbarShortcuts.vue'
import NavbarThemeSwitcher from '@/layouts/components/NavbarThemeSwitcher.vue'
import UserProfile from '@/layouts/components/UserProfile.vue'
import { HorizontalNavLayout } from '@layouts'
import { VNodeRenderer } from '@layouts/components/VNodeRenderer'

// إخفاء الصفحات اللي المشرف ما عنده عليها صلاحية (الأدمن والمحاسب ما بيتأثروا)
const authStore = useAuthStore()

const navItems = computed(() => (allNavItems as any[])
  .map(item => item.children
    ? { ...item, children: item.children.filter((child: any) => canAccessRoute(authStore.user, child.to)) }
    : item)
  .filter(item => item.children ? item.children.length > 0 : canAccessRoute(authStore.user, item.to)))
</script>

<template>
  <HorizontalNavLayout :nav-items="navItems">
    <!-- 👉 navbar -->
    <template #navbar>
      <RouterLink
        to="/"
        class="app-logo d-flex align-center gap-x-3"
      >
        <VNodeRenderer :nodes="themeConfig.app.logo" />

        <h1 class="app-title font-weight-bold leading-normal text-xl text-capitalize">
          {{ themeConfig.app.title }}
        </h1>
      </RouterLink>
      <VSpacer />

      <NavSearchBar trigger-btn-class="ms-lg-n3" />

      <NavbarThemeSwitcher />
      <NavbarShortcuts />
      <CurrencyRatesWidget />
      <NavBarNotifications class="me-2" />
      <UserProfile />
    </template>

    <!-- 👉 Pages -->
    <slot />

    <!-- 👉 Footer -->
    <template #footer>
      <Footer />
    </template>

    <!-- 👉 Customizer -->
    <TheCustomizer />
  </HorizontalNavLayout>
</template>

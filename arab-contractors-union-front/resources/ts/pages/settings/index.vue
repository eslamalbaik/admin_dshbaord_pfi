<script setup lang="ts">
import { ref } from 'vue'
import { useAuthStore } from '@/stores/authStore'
import { canAccessRoute } from '@/utils/permissions'
import AccountProfileForm from '@/components/account/AccountProfileForm.vue'

definePage({ meta: { requiresAdmin: true } })

const currentTab = ref('profile')

const authStore = useAuthStore()
const canOpen = (routeName: string) => canAccessRoute(authStore.user, routeName)
</script>

<template>
  <div>
    <h4 class="text-h4 mb-6">{{ $t('Settings') }}</h4>

    <VRow>
      <VCol cols="12" md="3">
        <VCard>
          <VList nav>
            <VListItem
              value="profile"
              prepend-icon="tabler-user-circle"
              :title="$t('My Profile')"
              :active="currentTab === 'profile'"
              @click="currentTab = 'profile'"
            />

            <VDivider class="my-2" />

            <VListItem
              v-if="canOpen('settings-terms')"
              prepend-icon="tabler-file-text"
              title="الشروط والأحكام"
              :to="{ name: 'settings-terms' }"
            />
            <VListItem
              v-if="canOpen('settings-privacy-policy')"
              prepend-icon="tabler-shield-lock"
              title="سياسة الخصوصية"
              :to="{ name: 'settings-privacy-policy' }"
            />
            <VListItem
              v-if="canOpen('settings-app-settings')"
              prepend-icon="tabler-adjustments"
              title="إعدادات التطبيق"
              :to="{ name: 'settings-app-settings' }"
            />
            <VListItem
              v-if="canOpen('settings-exchange-rates')"
              prepend-icon="tabler-currency-dollar"
              title="أسعار الصرف"
              :to="{ name: 'settings-exchange-rates' }"
            />
            <VListItem
              v-if="canOpen('settings-pages')"
              prepend-icon="tabler-file-plus"
              title="الصفحات الديناميكية"
              :to="{ name: 'settings-pages' }"
            />
            <VListItem
              v-if="canOpen('settings-grade-fees')"
              prepend-icon="tabler-cash"
              title="رسوم الدرجات"
              :to="{ name: 'settings-grade-fees' }"
            />
            <VListItem
              v-if="canOpen('settings-contractor-lookups')"
              prepend-icon="tabler-list-details"
              title="المجالات والاختصاصات والدرجات"
              :to="{ name: 'settings-contractor-lookups' }"
            />
            <VListItem
              v-if="canOpen('settings-governorates')"
              prepend-icon="tabler-map-2"
              title="المحافظات والمدن"
              :to="{ name: 'settings-governorates' }"
            />
            <VListItem
              v-if="canOpen('settings-country-codes')"
              prepend-icon="tabler-flag"
              title="مقدمات الدول وأعلامها"
              :to="{ name: 'settings-country-codes' }"
            />
            <VListItem
              v-if="canOpen('tenders-categories')"
              prepend-icon="tabler-category"
              title="تصنيفات العطاءات"
              :to="{ name: 'tenders-categories' }"
            />

            <VDivider class="my-2" />

            <VListItem
              v-if="canOpen('settings-activity-log')"
              prepend-icon="tabler-history"
              title="سجل النشاط"
              :to="{ name: 'settings-activity-log' }"
            />
            <VListItem
              v-if="canOpen('supervisors')"
              prepend-icon="tabler-shield-check"
              title="المشرفون والصلاحيات"
              :to="{ name: 'supervisors' }"
            />
          </VList>
        </VCard>
      </VCol>

      <VCol cols="12" md="9">
        <!-- My Profile — نفس صفحة «الملف الشخصي» -->
        <AccountProfileForm v-if="currentTab === 'profile'" />
      </VCol>
    </VRow>
  </div>
</template>

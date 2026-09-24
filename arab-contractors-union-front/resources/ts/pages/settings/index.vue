<script setup lang="ts">
import { ref, computed } from 'vue'
import api from '@/plugins/axios'
import { useAuthStore } from '@/stores/authStore'

definePage({ meta: { requiresAdmin: true } })

const currentTab = ref('profile')

const authStore = useAuthStore()
const API_ORIGIN = (import.meta.env.VITE_API_BASE_URL || 'http://localhost:8000').replace(/\/$/, '')

const avatarFile = ref<File | null>(null)
const isUploading = ref(false)
const avatarError = ref('')
const avatarSuccess = ref('')

const currentAvatar = computed(() => {
  const a = (authStore.user as any)?.avatar
  return a ? `${API_ORIGIN}/storage/${a}` : ''
})

const userInitial = computed(() => (authStore.user?.name?.[0] || 'A').toUpperCase())

const onAvatarPicked = (f: any) => {
  avatarFile.value = Array.isArray(f) ? f[0] : f
  avatarError.value = ''
  avatarSuccess.value = ''
}

const uploadAvatar = async () => {
  if (!avatarFile.value)
    return
  isUploading.value = true
  avatarError.value = ''
  avatarSuccess.value = ''
  try {
    const fd = new FormData()
    fd.append('avatar', avatarFile.value)
    const res = await api.post('/api/v1/profile/avatar', fd, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    // Sync the store so the topbar avatar updates immediately.
    if (authStore.user && res.data?.avatar) {
      ;(authStore.user as any).avatar = res.data.avatar
      if (typeof localStorage !== 'undefined')
        localStorage.setItem('userData', JSON.stringify(authStore.user))
    }
    avatarFile.value = null
    avatarSuccess.value = 'Profile picture updated successfully.'
  }
  catch (e: any) {
    avatarError.value = e?.response?.data?.message || 'Upload failed. Use JPG/PNG up to 4MB.'
  }
  finally {
    isUploading.value = false
  }
}
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
            <VListItem
              value="branding"
              prepend-icon="tabler-palette"
              :title="$t('Branding')"
              :active="currentTab === 'branding'"
              @click="currentTab = 'branding'"
            />

            <VDivider class="my-2" />

            <VListItem
              prepend-icon="tabler-file-text"
              title="الشروط والأحكام"
              :to="{ name: 'settings-terms' }"
            />
            <VListItem
              prepend-icon="tabler-shield-lock"
              title="سياسة الخصوصية"
              :to="{ name: 'settings-privacy-policy' }"
            />
            <VListItem
              prepend-icon="tabler-library"
              title="المكتبة القانونية"
              :to="{ name: 'settings-legal-library' }"
            />
            <VListItem
              prepend-icon="tabler-adjustments"
              title="إعدادات التطبيق"
              :to="{ name: 'settings-app-settings' }"
            />
            <VListItem
              prepend-icon="tabler-currency-dollar"
              title="أسعار الصرف"
              :to="{ name: 'settings-exchange-rates' }"
            />
            <VListItem
              prepend-icon="tabler-file-plus"
              title="الصفحات الديناميكية"
              :to="{ name: 'settings-pages' }"
            />
            <VListItem
              prepend-icon="tabler-cash"
              title="رسوم الدرجات"
              :to="{ name: 'settings-grade-fees' }"
            />
            <VListItem
              prepend-icon="tabler-list-details"
              title="المجالات والاختصاصات والدرجات"
              :to="{ name: 'settings-contractor-lookups' }"
            />
            <VListItem
              prepend-icon="tabler-map-2"
              title="المحافظات والمدن"
              :to="{ name: 'settings-governorates' }"
            />
            <VListItem
              prepend-icon="tabler-photo"
              title="صور تصنيفات العطاءات"
              :to="{ name: 'settings-tender-category-images' }"
            />
          </VList>
        </VCard>
      </VCol>

      <VCol cols="12" md="9">
        <VCard>
          <!-- My Profile -->
          <VCardText v-if="currentTab === 'profile'">
            <h5 class="text-h5 mb-4">{{ $t('My Profile') }}</h5>

            <div class="d-flex align-center gap-6 mb-6 flex-wrap">
              <VAvatar size="96" color="primary" variant="tonal">
                <VImg v-if="currentAvatar" :src="currentAvatar" cover />
                <span v-else class="text-h4">{{ userInitial }}</span>
              </VAvatar>
              <div>
                <h6 class="text-h6 mb-1">{{ authStore.user?.name }}</h6>
                <p class="text-body-2 text-medium-emphasis mb-0">{{ authStore.user?.email }}</p>
              </div>
            </div>

            <VAlert v-if="avatarSuccess" type="success" variant="tonal" class="mb-4">{{ avatarSuccess }}</VAlert>
            <VAlert v-if="avatarError" type="error" variant="tonal" class="mb-4">{{ avatarError }}</VAlert>

            <VFileInput
              :label="$t('Profile Picture')"
              accept="image/png,image/jpeg,image/jpg,image/webp"
              prepend-icon="tabler-camera"
              :hint="$t('JPG or PNG, up to 4MB')"
              persistent-hint
              class="mb-4"
              @update:model-value="onAvatarPicked"
            />
            <VBtn color="primary" :loading="isUploading" :disabled="!avatarFile" @click="uploadAvatar">
              {{ $t('Upload Picture') }}
            </VBtn>
          </VCardText>

          <!-- Branding -->
          <VCardText v-if="currentTab === 'branding'">
            <h5 class="text-h5 mb-4">Branding</h5>
            <VTextField label="Primary Color" placeholder="#7C3AED" class="mb-4" />
            <VTextField label="Secondary Color" placeholder="#06B6D4" class="mb-4" />
            <VSelect label="Font Family" :items="['Inter', 'Roboto', 'Outfit', 'Cairo']" class="mb-4" />
            <VBtn color="primary">Save Changes</VBtn>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>
  </div>
</template>

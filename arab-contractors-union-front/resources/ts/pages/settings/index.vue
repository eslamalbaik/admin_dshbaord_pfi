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
              value="general"
              prepend-icon="tabler-settings"
              :title="$t('General')"
              :active="currentTab === 'general'"
              @click="currentTab = 'general'"
            />
            <VListItem
              value="branding"
              prepend-icon="tabler-palette"
              :title="$t('Branding')"
              :active="currentTab === 'branding'"
              @click="currentTab = 'branding'"
            />
            <VListItem
              value="email"
              prepend-icon="tabler-mail"
              :title="$t('Email (SMTP)')"
              :active="currentTab === 'email'"
              @click="currentTab = 'email'"
            />
            <VListItem
              value="payments"
              prepend-icon="tabler-credit-card"
              :title="$t('Payments')"
              :active="currentTab === 'payments'"
              @click="currentTab = 'payments'"
            />
            <VListItem
              value="ai"
              prepend-icon="tabler-brain"
              :title="$t('AI (Gemini)')"
              :active="currentTab === 'ai'"
              @click="currentTab = 'ai'"
            />
            <VListItem
              value="security"
              prepend-icon="tabler-shield-lock"
              :title="$t('Security')"
              :active="currentTab === 'security'"
              @click="currentTab = 'security'"
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

          <!-- General -->
          <VCardText v-if="currentTab === 'general'">
            <h5 class="text-h5 mb-4">{{ $t('General Settings') }}</h5>
            <VTextField :label="$t('Site Name')" placeholder="Pharmacy LMS" class="mb-4" />
            <VFileInput :label="$t('Logo')" accept="image/*" class="mb-4" />
            <VFileInput :label="$t('Favicon')" accept="image/*" class="mb-4" />
            <VBtn color="primary">{{ $t('Save Changes') }}</VBtn>
          </VCardText>

          <!-- Branding -->
          <VCardText v-if="currentTab === 'branding'">
            <h5 class="text-h5 mb-4">Branding</h5>
            <VTextField label="Primary Color" placeholder="#7C3AED" class="mb-4" />
            <VTextField label="Secondary Color" placeholder="#06B6D4" class="mb-4" />
            <VSelect label="Font Family" :items="['Inter', 'Roboto', 'Outfit', 'Cairo']" class="mb-4" />
            <VBtn color="primary">Save Changes</VBtn>
          </VCardText>

          <!-- Email -->
          <VCardText v-if="currentTab === 'email'">
            <h5 class="text-h5 mb-4">Email Settings (SMTP)</h5>
            <VTextField label="SMTP Host" placeholder="smtp.gmail.com" class="mb-4" />
            <VTextField label="SMTP Port" placeholder="587" class="mb-4" />
            <VTextField label="SMTP Username" class="mb-4" />
            <VTextField label="SMTP Password" type="password" class="mb-4" />
            <VTextField label="From Email" placeholder="noreply@pharmacy-lms.com" class="mb-4" />
            <VTextField label="From Name" placeholder="Pharmacy LMS" class="mb-4" />
            <VBtn color="primary">Save Changes</VBtn>
          </VCardText>

          <!-- Payments -->
          <VCardText v-if="currentTab === 'payments'">
            <h5 class="text-h5 mb-4">Payment Gateway Settings</h5>
            <VSelect label="Payment Provider" :items="['Moyasar', 'Tap', 'HyperPay']" class="mb-4" />
            <VTextField label="API Key" class="mb-4" />
            <VTextField label="Secret Key" type="password" class="mb-4" />
            <VSwitch label="Test Mode" class="mb-4" />
            <VBtn color="primary">Save Changes</VBtn>
          </VCardText>

          <!-- AI -->
          <VCardText v-if="currentTab === 'ai'">
            <h5 class="text-h5 mb-4">AI Settings (Gemini)</h5>
            <VTextField label="Gemini API Key" type="password" class="mb-4" />
            <VSwitch label="Enable AI Quiz Generation" class="mb-4" />
            <VSwitch label="Enable Smart Summaries" class="mb-4" />
            <VSwitch label="Enable Student Analysis" class="mb-4" />
            <VBtn color="primary">Save Changes</VBtn>
          </VCardText>

          <!-- Security -->
          <VCardText v-if="currentTab === 'security'">
            <h5 class="text-h5 mb-4">Security Settings</h5>
            <VTextField label="Max Devices Per Account" type="number" placeholder="2" class="mb-4" />
            <VSwitch label="Enable Device Fingerprinting" class="mb-4" />
            <VSwitch label="Enable OTP Login" class="mb-4" />
            <VSwitch label="Enable Google Login" class="mb-4" />
            <VSwitch label="Force Email Verification" class="mb-4" />
            <VBtn color="primary">Save Changes</VBtn>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>
  </div>
</template>

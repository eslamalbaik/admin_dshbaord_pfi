<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useGenerateImageVariant } from '@core/composable/useGenerateImageVariant'
import { VNodeRenderer } from '@layouts/components/VNodeRenderer'
import { themeConfig } from '@themeConfig'
import api from '@/plugins/axios'

import authV2ResetPasswordIllustrationDark from '@images/pages/auth-v2-reset-password-illustration-dark.png'
import authV2ResetPasswordIllustrationLight from '@images/pages/auth-v2-reset-password-illustration-light.png'
import authV2MaskDark from '@images/pages/misc-mask-dark.png'
import authV2MaskLight from '@images/pages/misc-mask-light.png'

definePage({
  meta: {
    layout: 'blank',
    unauthenticatedOnly: true,
  },
})

const landingUrl = (import.meta.env.VITE_LANDING_URL || 'http://localhost:8080').replace(/\/$/, '')

const route = useRoute()
const router = useRouter()

const token = ref('')
const email = ref('')
const password = ref('')
const passwordConfirmation = ref('')

const isPasswordVisible = ref(false)
const isConfirmPasswordVisible = ref(false)
const isSubmitting = ref(false)
const successMessage = ref('')
const errorMessage = ref('')
const isLinkInvalid = ref(false)

const authThemeImg = useGenerateImageVariant(authV2ResetPasswordIllustrationLight, authV2ResetPasswordIllustrationDark)
const authThemeMask = useGenerateImageVariant(authV2MaskLight, authV2MaskDark)

onMounted(() => {
  token.value = (route.query.token as string) || ''
  email.value = (route.query.email as string) || ''

  if (!token.value || !email.value) {
    isLinkInvalid.value = true
    errorMessage.value = 'The password reset link is invalid or has expired. Please request a new one.'
  }
})

const handleSubmit = async () => {
  if (isLinkInvalid.value) return

  if (!password.value || !passwordConfirmation.value) {
    errorMessage.value = 'Please fill in all password fields.'
    return
  }

  if (password.value !== passwordConfirmation.value) {
    errorMessage.value = 'Passwords do not match.'
    return
  }

  isSubmitting.value = true
  successMessage.value = ''
  errorMessage.value = ''

  try {
    const response = await api.post('/api/v1/auth/reset-password', {
      token: token.value,
      email: email.value,
      password: password.value,
      password_confirmation: passwordConfirmation.value
    })

    if (response?.data?.success) {
      successMessage.value = response?.data?.message || 'Your password has been successfully reset. Redirecting to login...'
      
      // Redirect to landing login page after 2.5 seconds
      setTimeout(() => {
        window.location.href = `${landingUrl}/login`
      }, 2500)
    } else {
      errorMessage.value = response?.data?.message || 'Failed to reset password.'
    }
  } catch (error: any) {
    console.error('Reset password error:', error)
    if (error?.response?.data?.errors) {
      const firstErrorKey = Object.keys(error.response.data.errors)[0]
      errorMessage.value = error.response.data.errors[firstErrorKey][0]
    } else {
      errorMessage.value = error?.response?.data?.message || 'Failed to reset password. The link may have expired.'
    }
  } finally {
    isSubmitting.value = false
  }
}
</script>

<template>
  <RouterLink to="/">
    <div class="auth-logo d-flex align-center gap-x-3">
      <VNodeRenderer :nodes="themeConfig.app.logo" />
      <h1 class="auth-title">
        {{ themeConfig.app.title }}
      </h1>
    </div>
  </RouterLink>

  <VRow
    class="auth-wrapper bg-surface"
    no-gutters
  >
    <VCol
      md="8"
      class="d-none d-md-flex"
    >
      <div class="position-relative bg-background w-100 me-0">
        <div
          class="d-flex align-center justify-center w-100 h-100"
          style="padding-inline: 150px;"
        >
          <VImg
            max-width="400"
            :src="authThemeImg"
            class="auth-illustration mt-16 mb-2"
          />
        </div>

        <img
          class="auth-footer-mask"
          :src="authThemeMask"
          alt="auth-footer-mask"
          height="280"
          width="100"
        >
      </div>
    </VCol>

    <VCol
      cols="12"
      md="4"
      class="d-flex align-center justify-center"
    >
      <VCard
        flat
        :max-width="500"
        class="mt-12 mt-sm-0 pa-4"
      >
        <VCardText>
          <h4 class="text-h4 mb-1">
            Reset Password 🔒
          </h4>
          <p class="mb-0">
            Enter your new password below to complete the reset process
          </p>
        </VCardText>

        <VCardText>
          <!-- Success Alert -->
          <VAlert
            v-if="successMessage"
            type="success"
            variant="tonal"
            class="mb-4"
          >
            {{ successMessage }}
          </VAlert>

          <!-- Error Alert -->
          <VAlert
            v-if="errorMessage"
            type="error"
            variant="tonal"
            class="mb-4"
          >
            {{ errorMessage }}
          </VAlert>

          <VForm v-if="!isLinkInvalid" @submit.prevent="handleSubmit">
            <VRow>
              <!-- new password -->
              <VCol cols="12">
                <AppTextField
                  v-model="password"
                  autofocus
                  label="New Password"
                  :type="isPasswordVisible ? 'text' : 'password'"
                  placeholder="&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;"
                  :append-inner-icon="isPasswordVisible ? 'tabler-eye-off' : 'tabler-eye'"
                  :disabled="isSubmitting"
                  required
                  @click:append-inner="isPasswordVisible = !isPasswordVisible"
                />
              </VCol>

              <!-- confirm password -->
              <VCol cols="12">
                <AppTextField
                  v-model="passwordConfirmation"
                  label="Confirm Password"
                  :type="isConfirmPasswordVisible ? 'text' : 'password'"
                  placeholder="&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;"
                  :append-inner-icon="isConfirmPasswordVisible ? 'tabler-eye-off' : 'tabler-eye'"
                  :disabled="isSubmitting"
                  required
                  @click:append-inner="isConfirmPasswordVisible = !isConfirmPasswordVisible"
                />
              </VCol>

              <!-- Reset button -->
              <VCol cols="12">
                <VBtn
                  block
                  type="submit"
                  :loading="isSubmitting"
                  :disabled="isSubmitting"
                >
                  Set New Password
                </VBtn>
              </VCol>
            </VRow>
          </VForm>

          <!-- Link Invalid Action -->
          <div v-else class="text-center mt-4">
            <RouterLink
              to="/forgot-password"
              class="d-inline-block"
            >
              <VBtn color="primary">
                Request New Link
              </VBtn>
            </RouterLink>
          </div>

          <!-- back to login -->
          <VCol cols="12" class="text-center mt-4 px-0">
            <a
              class="d-flex align-center justify-center text-primary"
              :href="`${landingUrl}/login`"
            >
              <VIcon
                icon="tabler-chevron-left"
                size="20"
                class="me-1 flip-in-rtl"
              />
              <span>Back to login</span>
            </a>
          </VCol>
        </VCardText>
      </VCard>
    </VCol>
  </VRow>
</template>

<style lang="scss">
@use "@core-scss/template/pages/page-auth.scss";
</style>

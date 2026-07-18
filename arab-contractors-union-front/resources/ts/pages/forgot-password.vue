<script setup lang="ts">
import { ref } from 'vue'
import { useGenerateImageVariant } from '@core/composable/useGenerateImageVariant'
import { VNodeRenderer } from '@layouts/components/VNodeRenderer'
import { themeConfig } from '@themeConfig'
import api from '@/plugins/axios'

import authV2ForgotPasswordIllustrationDark from '@images/pages/auth-v2-forgot-password-illustration-dark.png'
import authV2ForgotPasswordIllustrationLight from '@images/pages/auth-v2-forgot-password-illustration-light.png'
import authV2MaskDark from '@images/pages/misc-mask-dark.png'
import authV2MaskLight from '@images/pages/misc-mask-light.png'

definePage({
  meta: {
    layout: 'blank',
    unauthenticatedOnly: true,
  },
})

const landingUrl = (import.meta.env.VITE_LANDING_URL || 'http://localhost:8080').replace(/\/$/, '')

const email = ref('')
const isSubmitting = ref(false)
const successMessage = ref('')
const errorMessage = ref('')

const authThemeImg = useGenerateImageVariant(authV2ForgotPasswordIllustrationLight, authV2ForgotPasswordIllustrationDark)
const authThemeMask = useGenerateImageVariant(authV2MaskLight, authV2MaskDark)

const handleSubmit = async () => {
  if (!email.value) {
    errorMessage.value = 'Please enter your email address.'
    return
  }

  isSubmitting.value = true
  successMessage.value = ''
  errorMessage.value = ''

  try {
    const response = await api.post('/api/v1/auth/forgot-password', {
      email: email.value
    })
    
    if (response?.data?.success) {
      successMessage.value = response?.data?.message || 'A password reset link has been sent to your email.'
    } else {
      errorMessage.value = response?.data?.message || 'Failed to send reset link.'
    }
  } catch (error: any) {
    console.error('Forgot password error:', error)
    if (error?.response?.data?.errors?.email) {
      errorMessage.value = error.response.data.errors.email[0]
    } else {
      errorMessage.value = error?.response?.data?.message || 'We could not find a user with that email address.'
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
            max-width="468"
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
            Forgot Password? 🔒
          </h4>
          <p class="mb-0">
            Enter your email and we'll send you instructions to reset your password
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

          <VForm @submit.prevent="handleSubmit">
            <VRow>
              <!-- email -->
              <VCol cols="12">
                <AppTextField
                  v-model="email"
                  autofocus
                  label="Email"
                  type="email"
                  placeholder="johndoe@email.com"
                  :disabled="isSubmitting"
                  required
                />
              </VCol>

              <!-- Reset link -->
              <VCol cols="12">
                <VBtn
                  block
                  type="submit"
                  :loading="isSubmitting"
                  :disabled="isSubmitting"
                >
                  Send Reset Link
                </VBtn>
              </VCol>

              <!-- back to login -->
              <VCol cols="12">
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
            </VRow>
          </VForm>
        </VCardText>
      </VCard>
    </VCol>
  </VRow>
</template>

<style lang="scss">
@use "@core-scss/template/pages/page-auth.scss";
</style>

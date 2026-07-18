<!-- ❗Errors in the form are set on line 60 -->
<script setup lang="ts">
import { VForm } from 'vuetify/components/VForm'

import { VNodeRenderer } from '@layouts/components/VNodeRenderer'
import { themeConfig } from '@themeConfig'

definePage({
  meta: {
    layout: 'blank',
    unauthenticatedOnly: true,
  },
})

const isPasswordVisible = ref(false)

const route = useRoute()
const router = useRouter()

const errors = ref<Record<string, string | undefined>>({
  email: undefined,
  password: undefined,
})

const refVForm = ref<VForm>()

const credentials = ref({
  email: 'admin@union.ps',
  password: 'admin123',
})

const rememberMe = ref(false)

import api from '@/plugins/axios'
import { useAuthStore } from '@/stores/authStore'

const isSubmitting = ref(false)
const isSuccess = ref(false)

const SUCCESS_DELAY_MS = 500

const getLoginErrorMessage = (err: any) => {
  // No HTTP response → connection-level failure. Distinguish the cause so the
  // admin knows whether it's the server, the network/CORS, or a slow boot.
  if (!err.response) {
    if (err.code === 'ECONNABORTED')
      return 'استغرق الخادم وقتاً طويلاً للاستجابة. قد يكون قيد التشغيل — حاول مرة أخرى بعد قليل.'
    if (err.code === 'ERR_NETWORK')
      return 'خطأ في الشبكة: لا يمكن الوصول إلى خادم API (تأكد من تشغيله على المنفذ 8000).'

    return 'لا يمكن الوصول إلى خادم API. تأكد من تشغيله على المنفذ 8000.'
  }

  if (err.response.status === 401 || err.response.status === 422) {
    const msg = err.response.data?.errors?.email?.[0] || err.response.data?.message || ''
    if (msg === 'invalid_credentials' || !msg)
      return 'البريد الإلكتروني أو كلمة المرور غير صحيحة.'
    return msg
  }

  if (err.response.status === 419)
    return 'تم رفض طلب تسجيل الدخول بواسطة حماية CSRF.'

  if (err.response.status === 429)
    return 'محاولات تسجيل دخول كثيرة. يرجى الانتظار لمدة دقيقة والمحاولة مرة أخرى.'

  return err.response.data?.message || 'فشل تسجيل الدخول. يرجى المحاولة مرة أخرى.'
}

const login = async () => {
  isSubmitting.value = true
  isSuccess.value = false
  errors.value.email = undefined
  errors.value.password = undefined

  try {
    const res = await api.post('/api/v1/auth/login', {
      email: credentials.value.email,
      password: credentials.value.password,
    })

    const items = res.data?.items
    if (!items?.user || !items?.token)
      throw new Error('Login response did not include a user and token.')

    if (!['admin', 'accountant'].includes(items.user.role)) {
      errors.value.email = 'ليس لديك صلاحية الدخول للوحة التحكم.'
      isSubmitting.value = false
      return
    }

    const authStore = useAuthStore()
    authStore.login(items.user, items.token)

    isSubmitting.value = false
    isSuccess.value = true
    await new Promise(resolve => setTimeout(resolve, SUCCESS_DELAY_MS))
    await router.replace('/dashboards')
  }
  catch (err: any) {
    console.error(err)
    isSubmitting.value = false
    isSuccess.value = false
    errors.value.email = err.message === 'Login response did not include a user and token.'
      ? 'The API login response is missing an auth token. Make sure port 8000 is running the LMS API.'
      : getLoginErrorMessage(err)
  }
}

const onSubmit = () => {
  refVForm.value?.validate()
    .then(({ valid: isValid }) => {
      if (isValid)
        login()
    })
}
</script>

<template>
  <div
    class="d-flex align-center justify-center"
    style="min-height:100dvh;background-color:#f4f5f7;"
  >
    <VCard
      flat
      rounded="lg"
      elevation="3"
      style="width:100%;max-width:440px;"
      class="pa-6 pa-sm-8 mx-4"
    >
      <!-- الشعار -->
      <div class="d-flex justify-center mb-6">
        <RouterLink to="/">
          <VNodeRenderer :nodes="themeConfig.app.logo" />
        </RouterLink>
      </div>

      <!-- العنوان -->
      <VCardText class="pa-0 mb-5 text-center">
        <h4 class="text-h5 font-weight-bold mb-1" style="font-family:Cairo,sans-serif;">
          تسجيل الدخول
          <VIcon icon="tabler-shield-lock" size="22" class="text-primary ms-1" />
        </h4>
        <p class="text-body-2 text-medium-emphasis mb-0" style="font-family:Cairo,sans-serif;">
          بوابة إدارة اتحاد المقاولين العرب
        </p>
      </VCardText>

      <!-- الفورم -->
      <VCardText class="pa-0">
        <VForm ref="refVForm" @submit.prevent="onSubmit">
          <VRow>
            <VCol cols="12">
              <AppTextField
                v-model="credentials.email"
                label="البريد الإلكتروني"
                placeholder="admin@union.ps"
                type="email"
                autofocus
                :rules="[requiredValidator, emailValidator]"
                :error-messages="errors.email"
              />
            </VCol>

            <VCol cols="12">
              <AppTextField
                v-model="credentials.password"
                label="كلمة المرور"
                placeholder="············"
                :rules="[requiredValidator]"
                :type="isPasswordVisible ? 'text' : 'password'"
                autocomplete="current-password"
                :error-messages="errors.password"
                :append-inner-icon="isPasswordVisible ? 'tabler-eye-off' : 'tabler-eye'"
                @click:append-inner="isPasswordVisible = !isPasswordVisible"
              />

              <div class="d-flex align-center my-4">
                <VCheckbox v-model="rememberMe" label="تذكرني" hide-details />
              </div>

              <VBtn
                block
                type="submit"
                size="large"
                :loading="isSubmitting"
                :disabled="isSubmitting || isSuccess"
                :color="isSuccess ? 'success' : 'primary'"
                :prepend-icon="isSuccess ? 'tabler-check' : 'tabler-login'"
                style="font-family:Cairo,sans-serif;"
              >
                {{ isSuccess ? 'تم بنجاح' : 'دخول آمن' }}
              </VBtn>
            </VCol>
          </VRow>
        </VForm>
      </VCardText>
    </VCard>
  </div>
</template>

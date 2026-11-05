<script setup lang="ts">
// جوال المقاول (حساب التطبيق): رمز الدولة من قائمة + الرقم الوطني بدون الصفر بحقل منفصل.
// الخادم يخزّنهما بعمودين (phone + phone_country_code) — القائمة من نفس endpoint تطبيق
// الموبايل (contractor/auth/countries) حتى لا تختلف الدول المدعومة بين اللوحة والتطبيق.
import api from '@/plugins/axios'

interface PhoneCountry {
  iso: string
  code: string
  dial_code: string
  name_ar: string
  flag: string
  min_length: number
  max_length: number
  example: string
}

const props = withDefaults(defineProps<{
  label?: string
  required?: boolean
  errorMessages?: string | string[]
}>(), { label: 'رقم الجوال', required: false })

const phone = defineModel<string>('phone', { default: '' })
const countryCode = defineModel<string>('countryCode', { default: '970' })

// احتياطي لو فشل الطلب — فلسطين فقط، كالسلوك القديم
const countries = ref<PhoneCountry[]>([
  { iso: 'PS', code: '970', dial_code: '+970', name_ar: 'فلسطين', flag: '🇵🇸', min_length: 9, max_length: 9, example: '599123456' },
])

onMounted(async () => {
  try {
    const { data } = await api.get('/api/v1/contractor/auth/countries')
    if (data?.items?.countries?.length)
      countries.value = data.items.countries
  }
  catch {}
})

const selected = computed(() => countries.value.find(c => c.code === (countryCode.value || '970')) ?? countries.value[0])

const items = computed(() => countries.value.map(c => ({ title: `${c.flag} ${c.dial_code}  ${c.name_ar}`, value: c.code })))

const onInput = (value: string) => {
  // الصفر بأول الرقم زائد مع رمز الدولة — بنشيله تلقائياً
  phone.value = (value ?? '').replace(/\D+/g, '').replace(/^0+/, '').slice(0, selected.value.max_length)
}

const rules = computed(() => [
  (v: string) => !props.required || !!v || 'رقم الجوال مطلوب',
  (v: string) => {
    if (!v)
      return true
    const { min_length: min, max_length: max, example } = selected.value
    const len = min === max ? `${min} أرقام` : `${min} إلى ${max} أرقام`

    return (v.length >= min && v.length <= max) || `يجب إدخال ${len} بدون الصفر (مثال: ${example})`
  },
])
</script>

<template>
  <div class="d-flex gap-2 align-start">
    <VSelect
      v-model="countryCode"
      :items="items"
      label="الدولة"
      dir="ltr"
      hide-details
      style="flex:0 0 150px"
    >
      <template #selection>
        {{ selected.flag }} {{ selected.dial_code }}
      </template>
    </VSelect>
    <VTextField
      :model-value="phone"
      :label="required ? `${label} *` : label"
      :placeholder="selected.example"
      inputmode="numeric"
      dir="ltr"
      :error-messages="errorMessages"
      :rules="rules"
      class="flex-grow-1"
      @update:model-value="onInput"
    />
  </div>
</template>

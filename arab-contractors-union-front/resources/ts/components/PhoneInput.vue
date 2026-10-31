<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import type { DialCode } from '@/utils/dialCodes'
import { acceptsNational, describeRule, dialCodes, formatPhone, loadDialCodes, parsePhone } from '@/utils/dialCodes'
import { vNumeric } from '@/utils/contractorValidation'

/**
 * خانة جوال بمقدمة دولة وعلمها (القائمة من إعدادات ← مقدمات الدول) + الرقم المحلي.
 * القيمة الخارجة: +970599123456 (أو فارغة).
 */
const props = defineProps<{
  modelValue?: string | null
  label?: string
  required?: boolean
  icon?: string
  errorMessages?: string | string[]
}>()

const emit = defineEmits<{ (e: 'update:modelValue', v: string): void }>()

const dial = ref<string | undefined>()
const national = ref('')

const selected = computed<DialCode | undefined>(() => dialCodes.value.find(c => c.dial_code === dial.value))

const syncFromValue = () => {
  const parsed = parsePhone(props.modelValue)
  if (formatPhone(selected.value, national.value) === (props.modelValue ?? '') && dial.value)
    return
  dial.value = parsed.code?.dial_code ?? dial.value ?? dialCodes.value.find(c => c.is_default)?.dial_code
  national.value = parsed.national
}

onMounted(async () => {
  await loadDialCodes()
  syncFromValue()
})

watch(() => props.modelValue, syncFromValue)

watch([dial, national], () => {
  // 0599123456 ← 599123456: صفر البداية المحلي ما بينكتب بعد المقدمة
  if (national.value.startsWith('0')) {
    national.value = national.value.replace(/^0+/, '')

    return
  }
  const next = formatPhone(selected.value, national.value)
  if (next !== (props.modelValue ?? ''))
    emit('update:modelValue', next)
})

const rule = (v: string) => {
  if (!v)
    return props.required ? `${props.label ?? 'الرقم'} مطلوب` : true
  if (!selected.value)
    return 'اختر مقدمة الدولة'

  return acceptsNational(selected.value, v) || `يجب إدخال ${describeRule(selected.value)}`
}

const placeholder = computed(() => {
  const c = selected.value
  if (!c)
    return ''
  const first = (c.starts_with ?? '').split(',')[0]?.trim() ?? ''

  return (first + 'X'.repeat(Math.max(0, c.max_digits - first.length)))
})
</script>

<template>
  <div class="phone-input d-flex gap-2 align-start">
    <VTextField
      v-model="national"
      v-numeric
      class="flex-grow-1"
      :label="label"
      :placeholder="placeholder"
      inputmode="tel"
      :maxlength="(selected?.max_digits ?? 14) + 1"
      :prepend-inner-icon="icon"
      :rules="[rule]"
      :error-messages="errorMessages"
    />
    <VSelect
      v-model="dial"
      class="dial-select"
      :items="dialCodes"
      item-value="dial_code"
      item-title="name"
      label="المقدمة"
      :menu-props="{ maxHeight: 320 }"
      hide-details
    >
      <template #selection="{ item }">
        <img
          v-if="item.raw.flag_url"
          :src="item.raw.flag_url"
          :alt="item.raw.name"
          class="dial-flag"
        >
        <span dir="ltr">+{{ item.raw.dial_code }}</span>
      </template>
      <template #item="{ props: itemProps, item }">
        <VListItem
          v-bind="itemProps"
          :title="item.raw.name"
        >
          <template #prepend>
            <img
              v-if="item.raw.flag_url"
              :src="item.raw.flag_url"
              :alt="item.raw.name"
              class="dial-flag me-2"
            >
          </template>
          <template #append>
            <span
              dir="ltr"
              class="text-medium-emphasis"
            >+{{ item.raw.dial_code }}</span>
          </template>
        </VListItem>
      </template>
    </VSelect>
  </div>
</template>

<style scoped>
.dial-select {
  flex: 0 0 132px;
  max-inline-size: 132px;
}

.dial-flag {
  border-radius: 2px;
  block-size: 14px;
  box-shadow: 0 0 0 1px rgba(0, 0, 0, 8%);
  inline-size: 20px;
  margin-inline-end: 6px;
  object-fit: cover;
}
</style>

<script setup lang="ts">
// رقم واتساب المالك (REQ-08 #13): مقدمة دولية 970 أو 972 تُختار من قائمة، والرقم المحلي (9 خانات
// تبدأ بـ5) بحقل منفصل. القيمة المرتبطة بـ v-model هي الرقم الكامل بدون + (970599123456) —
// نفس الصيغة اللي بيتحقق منها الخادم وبيحتاجها رابط wa.me بالتطبيق.
const props = defineProps<{ modelValue: string }>()
const emit = defineEmits<{ (e: 'update:modelValue', value: string): void }>()

const PREFIXES = [
  { title: '+970', value: '970' },
  { title: '+972', value: '972' },
]

const prefix = ref('970')
const local  = ref('')

// يفكّك القيمة المحفوظة: "970599123456" ← 970 + 599123456. القيم القديمة بالصيغة المحلية
// ("0599-123456") بتنحسب بمقدمة 970 بعد شيل الصفر، فبتنحفظ بالصيغة الجديدة عند أول تعديل.
const parse = (value: string) => {
  let digits = (value ?? '').replace(/\D+/g, '')
  if (digits.startsWith('00'))
    digits = digits.slice(2)

  const known = PREFIXES.find(p => digits.startsWith(p.value) && digits.length > 9)
  if (known) {
    prefix.value = known.value
    local.value  = digits.slice(known.value.length)
  }
  else {
    local.value = digits.replace(/^0+/, '')
  }
}

const compose = () => local.value ? `${prefix.value}${local.value}` : ''

watch(() => props.modelValue, value => {
  if (value !== compose())
    parse(value)
}, { immediate: true })

const onLocalInput = (value: string) => {
  // الصفر بأول الرقم المحلي (0599...) زائد مع المقدمة الدولية — بنشيله تلقائياً
  local.value = (value ?? '').replace(/\D+/g, '').replace(/^0+/, '').slice(0, 9)
  emit('update:modelValue', compose())
}

watch(prefix, () => emit('update:modelValue', compose()))

const rules = [
  (v: string) => !v || /^5\d{8}$/.test(v) || 'رقم الجوال 9 خانات ويبدأ بـ5 (بدون الصفر)، مثال: 599123456',
]
</script>

<template>
  <div class="d-flex gap-2 align-start">
    <VSelect
      v-model="prefix"
      :items="PREFIXES"
      label="المقدمة"
      variant="outlined"
      density="compact"
      hide-details
      dir="ltr"
      style="flex:0 0 112px;font-family:Cairo,sans-serif"
    />
    <VTextField
      :model-value="local"
      label="رقم واتساب المالك"
      placeholder="599123456"
      inputmode="numeric"
      dir="ltr"
      :rules="rules"
      hint="المقدمة 970 أو 972، والرقم بدون الصفر"
      class="flex-grow-1"
      persistent-hint
      variant="outlined"
      density="compact"
      style="font-family:Cairo,sans-serif"
      @update:model-value="onLocalInput"
    />
  </div>
</template>

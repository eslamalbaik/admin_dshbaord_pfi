<script setup lang="ts">
/**
 * خانات النسخة الإنجليزية من شهادة العضوية بنوافذ الإصدار وإعادة الإصدار.
 * النسخة الإنجليزية بتطلع جنب العربية (نفس الرقم التسلسلي) لما يكون للمقاول اسم إنجليزي
 * بملفه — الأدمن بس بيعبّيه من "تعديل المقاول".
 */
const props = defineProps<{
  contractorId?: number | null
  nameEn?: string | null
  loading?: boolean
}>()

const include = defineModel<boolean>('include', { default: true })
const addressEn = defineModel<string>('addressEn', { default: '' })

const hasName = computed(() => !!props.nameEn?.trim())
const englishRule = (v: string) => !v || /^[A-Za-z0-9 .,&'()/\-#]+$/.test(v) || 'حروف إنجليزية وأرقام فقط'
</script>

<template>
  <div class="cert-en mb-4">
    <VAlert
      v-if="!loading && !hasName"
      type="warning"
      variant="tonal"
      density="compact"
    >
      ما في اسم للشركة بالإنجليزي بملف المقاول، فالشهادة بتطلع بالعربي بس.
      <RouterLink
        v-if="contractorId"
        :to="{ name: 'contractors-edit-id', params: { id: contractorId } }"
        target="_blank"
      >
        أضف الاسم الإنجليزي
      </RouterLink>
    </VAlert>

    <template v-else-if="hasName">
      <VCheckbox
        v-model="include"
        density="compact"
        hide-details
        label="إصدار نسخة إنجليزية كمان (English)"
      />
      <div
        v-if="include"
        class="ps-2"
      >
        <div class="text-body-2 text-medium-emphasis mb-2">
          الاسم على النسخة الإنجليزية:
          <b dir="ltr">{{ nameEn }}</b>
        </div>
        <VTextField
          v-model="addressEn"
          label="العنوان بالإنجليزي"
          dir="ltr"
          placeholder="e.g. Gaza"
          :loading="loading"
          :rules="[englishRule]"
        />
      </div>
    </template>
  </div>
</template>

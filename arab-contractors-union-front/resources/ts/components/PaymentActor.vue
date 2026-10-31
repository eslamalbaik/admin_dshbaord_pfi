<script setup lang="ts">
import { computed } from 'vue'
import { UNION_NAME, avatarUrl } from '@/utils/avatarUrl'

/**
 * مين أكّد/رفض/أرجع الدفعة — صورة الموظف واسمه (confirmed_by_user من PaymentResource).
 * بيظهر بسجل المدفوعات تحت حالة الدفعة وبقائمة الإجراءات.
 */
export interface PaymentStaff {
  id: number
  name: string | null
  avatar: string | null
  role: string | null
  role_label: string | null
}

const props = withDefaults(defineProps<{
  user: PaymentStaff | null | undefined
  status: string
  size?: number
  showRole?: boolean
}>(), { size: 22, showRole: false })

const VERBS: Record<string, string> = {
  paid: 'أكّدها',
  rejected: 'رفضها',
  pending: 'أعادها للمراجعة',
}

const name = computed(() => {
  const n = (props.user?.name ?? '').trim()
  if (props.user?.role === 'admin' && (!n || /^admin$/i.test(n)))
    return UNION_NAME

  return n || 'موظف'
})

const src = computed(() => avatarUrl(props.user?.avatar))
const verb = computed(() => VERBS[props.status] ?? 'آخر إجراء')
</script>

<template>
  <div
    v-if="user"
    class="payment-actor d-flex align-center gap-1"
  >
    <VAvatar
      :size="size"
      :color="src ? undefined : 'primary'"
      :variant="src ? undefined : 'tonal'"
    >
      <VImg
        v-if="src"
        :src="src"
        :alt="name"
        cover
      />
      <span
        v-else
        class="text-caption font-weight-medium"
      >{{ name.charAt(0) }}</span>
    </VAvatar>
    <span class="text-caption text-medium-emphasis text-truncate">
      {{ verb }}: <span class="text-high-emphasis font-weight-medium">{{ name }}</span>
      <span v-if="showRole && user.role_label"> ({{ user.role_label }})</span>
    </span>
  </div>
</template>

<style scoped>
.payment-actor {
  max-inline-size: 240px;
  min-inline-size: 0;
}
</style>

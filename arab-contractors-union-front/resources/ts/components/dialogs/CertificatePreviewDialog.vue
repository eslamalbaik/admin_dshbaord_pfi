<script setup lang="ts">
import { computed } from 'vue'
import { useQuery } from '@tanstack/vue-query'
import api from '@/plugins/axios'

/**
 * عرض شهادة صادرة داخل نافذة (بدل فتح الرابط بتبويب جديد) + حالة وصولها للمقاول:
 * إشعار التطبيق، فتح الإشعار، وفتح ملف الشهادة نفسه. البيانات من
 * GET dashboard/certificate-requests/{id} (حقل delivery)، وبتتحدّث كل 15 ثانية
 * طول ما النافذة مفتوحة — الإشعار بيمرّ على الطابور، و"فتحها" بيصير لاحقاً.
 */
interface Delivery {
  notified_at: string | null
  notification_read_at: string | null
  has_device: boolean
  viewed_at: string | null
  last_viewed_at: string | null
  views_count: number
}

const props = defineProps<{ requestId: number | null }>()

const open = defineModel<boolean>({ default: false })

const { data, isFetching } = useQuery({
  queryKey: computed(() => ['certificate-request', props.requestId]),
  queryFn: async () => (await api.get(`/api/v1/dashboard/certificate-requests/${props.requestId}`)).data,
  enabled: computed(() => open.value && !!props.requestId),
  refetchInterval: computed(() => (open.value ? 15000 : false)),
})

const cert = computed(() => data.value?.items ?? null)
const delivery = computed<Delivery | null>(() => cert.value?.delivery ?? null)

// إعادة الإصدار بتكتب نفس المسار، فبنكسر كاش المتصفح بوقت الإصدار
const fileUrl = computed(() => {
  const url = cert.value?.certificate_url
  if (!url)
    return null

  return `${url}${url.includes('?') ? '&' : '?'}v=${encodeURIComponent(cert.value?.issue_date ?? '')}`
})

const serial = computed(() => cert.value?.id ? `MC-${String(cert.value.id).padStart(6, '0')}` : '')

function fmt(d: string | null | undefined) {
  if (!d)
    return null

  return new Date(d).toLocaleString('ar-EG', { dateStyle: 'medium', timeStyle: 'short', timeZone: 'Asia/Gaza' })
}

const steps = computed(() => {
  const d = delivery.value
  if (!cert.value || cert.value.status !== 'issued')
    return []

  return [
    {
      done: true,
      icon: 'tabler-certificate',
      title: 'صدرت الشهادة',
      text: fmt(cert.value.issue_date),
    },
    {
      done: !!d?.notified_at,
      icon: 'tabler-send',
      title: d?.notified_at ? 'وصلت للمقاول' : 'جارٍ إرسال الإشعار للمقاول…',
      text: d?.notified_at
        ? `${fmt(d.notified_at)} — ${d.has_device ? 'بالتطبيق وإشعار على جواله' : 'بصندوق إشعارات التطبيق (ما عنده جهاز مسجّل للإشعارات)'}`
        : null,
    },
    {
      done: !!d?.notification_read_at,
      icon: 'tabler-bell-check',
      title: d?.notification_read_at ? 'قرأ الإشعار' : 'لم يقرأ الإشعار بعد',
      text: fmt(d?.notification_read_at),
    },
    {
      done: !!d?.viewed_at,
      icon: 'tabler-eye-check',
      title: d?.viewed_at ? 'فتح الشهادة' : 'لم يفتح الشهادة بعد',
      text: d?.viewed_at
        ? `أول مرة ${fmt(d.viewed_at)}${d.views_count > 1 ? ` — ${d.views_count} مرات، آخرها ${fmt(d.last_viewed_at)}` : ''}`
        : null,
    },
  ]
})
</script>

<template>
  <VDialog
    v-model="open"
    max-width="1000"
    scrollable
    :fullscreen="$vuetify.display.xs"
  >
    <VCard>
      <VCardTitle class="d-flex align-center gap-3 pt-4">
        <VIcon
          icon="tabler-certificate"
          color="success"
        />
        <div class="flex-grow-1 min-w-0">
          <div class="text-h6">
            {{ cert?.type_label ?? 'الشهادة' }}
            <span
              v-if="serial"
              class="text-body-2 text-medium-emphasis"
              dir="ltr"
            >{{ serial }}</span>
          </div>
          <div class="text-body-2 text-medium-emphasis">
            {{ cert?.contractor }}
            <template v-if="cert?.membership_number">
              — {{ cert.membership_number }}
            </template>
          </div>
        </div>
        <VBtn
          icon="tabler-x"
          variant="text"
          size="small"
          @click="open = false"
        />
      </VCardTitle>

      <VProgressLinear
        v-if="isFetching && !cert"
        indeterminate
        color="primary"
      />

      <VCardText v-if="cert">
        <!-- ─── حالة الوصول ─── -->
        <div
          v-if="steps.length"
          class="delivery-steps d-flex flex-wrap gap-3 mb-4"
        >
          <div
            v-for="s in steps"
            :key="s.icon"
            class="delivery-step d-flex align-start gap-2 pa-3 rounded-lg"
            :class="{ 'is-done': s.done }"
          >
            <VIcon
              :icon="s.done ? s.icon : 'tabler-clock'"
              :color="s.done ? 'success' : 'secondary'"
              size="20"
            />
            <div class="min-w-0">
              <div class="text-body-2 font-weight-medium">
                {{ s.title }}
              </div>
              <div
                v-if="s.text"
                class="text-caption text-medium-emphasis"
              >
                {{ s.text }}
              </div>
            </div>
          </div>
        </div>

        <!-- ─── الشهادة ─── -->
        <iframe
          v-if="fileUrl"
          :src="fileUrl"
          class="certificate-frame rounded-lg"
          title="الشهادة"
        />
        <VAlert
          v-else
          type="warning"
          variant="tonal"
        >
          لا يوجد ملف شهادة لهذا الطلب.
        </VAlert>
      </VCardText>

      <VCardActions>
        <VBtn
          v-if="fileUrl"
          variant="tonal"
          prepend-icon="tabler-external-link"
          :href="fileUrl"
          target="_blank"
          rel="noopener noreferrer"
        >
          فتح بتبويب جديد
        </VBtn>
        <VSpacer />
        <VBtn
          variant="tonal"
          @click="open = false"
        >
          إغلاق
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style scoped>
.delivery-step {
  flex: 1 1 200px;
  border: 1px dashed rgba(var(--v-border-color), var(--v-border-opacity));
}

.delivery-step.is-done {
  border-style: solid;
  border-color: rgba(var(--v-theme-success), 0.4);
  background: rgba(var(--v-theme-success), 0.05);
}

.certificate-frame {
  display: block;
  inline-size: 100%;
  block-size: 70vh;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}

.min-w-0 {
  min-inline-size: 0;
}
</style>

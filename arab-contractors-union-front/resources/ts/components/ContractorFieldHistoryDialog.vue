<script setup lang="ts">
import api from '@/plugins/axios'

/**
 * سجل التغييرات لمقاول: كل تعديل على بياناته (من التطبيق أو لوحة التحكم أو النظام)
 * — الحقل، القيمة قبل وبعد، مين عمله وإمتى. المستندات القديمة بتضل قابلة للفتح.
 */
const props = defineProps<{ contractorId: number | null; contractorName?: string }>()
const model = defineModel<boolean>({ default: false })

const loading = ref(false)
const rows = ref<any[]>([])
const page = ref(1)
const lastPage = ref(1)
const pendingCount = ref(0)

const sourceColor = (s: string) => ({ app: 'primary', dashboard: 'info', system: 'secondary' } as Record<string, string>)[s] ?? 'secondary'

const fmt = (iso?: string | null) => iso
  ? new Intl.DateTimeFormat('ar-EG-u-nu-latn', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(iso))
  : '—'

const load = async (reset = false) => {
  if (!props.contractorId)
    return
  if (reset) {
    page.value = 1
    rows.value = []
  }
  loading.value = true
  try {
    const { data } = await api.get(`/api/v1/contractors/${props.contractorId}/field-history`, { params: { page: page.value } })

    rows.value = reset ? data.items.data : [...rows.value, ...data.items.data]
    lastPage.value = data.items.meta.last_page
    pendingCount.value = data.items.pending_count
  }
  catch {
    if (reset)
      rows.value = []
  }
  finally {
    loading.value = false
  }
}

const more = () => {
  page.value++
  load()
}

watch(model, open => {
  if (open)
    load(true)
})
</script>

<template>
  <VDialog
    v-model="model"
    max-width="820"
    scrollable
  >
    <VCard style="font-family: Cairo, sans-serif;">
      <VCardTitle class="d-flex align-center gap-2 pa-4">
        <VIcon
          icon="tabler-history"
          color="primary"
        />
        <span class="text-truncate">سجل التغييرات{{ contractorName ? ` — ${contractorName}` : '' }}</span>
        <VSpacer />
        <VBtn
          icon="tabler-x"
          variant="text"
          size="small"
          @click="model = false"
        />
      </VCardTitle>
      <VDivider />

      <VCardText
        style="max-block-size: 70vh;"
        class="pa-4"
      >
        <VAlert
          v-if="pendingCount"
          type="warning"
          variant="tonal"
          density="compact"
          class="mb-4"
        >
          عند المقاول {{ pendingCount }} تعديل بانتظار الاعتماد.
          <RouterLink
            :to="{ path: '/contractors/profile-changes', query: { contractor: contractorId } }"
            class="font-weight-bold"
          >
            مراجعة الآن
          </RouterLink>
        </VAlert>

        <div
          v-if="loading && !rows.length"
          class="d-flex justify-center pa-8"
        >
          <VProgressCircular
            indeterminate
            color="primary"
          />
        </div>
        <div
          v-else-if="!rows.length"
          class="text-center text-medium-emphasis pa-8"
        >
          ما في تغييرات مسجّلة بعد
        </div>

        <VTimeline
          v-else
          side="end"
          density="compact"
          align="start"
          truncate-line="both"
        >
          <VTimelineItem
            v-for="row in rows"
            :key="row.id"
            :dot-color="sourceColor(row.source)"
            size="x-small"
          >
            <div class="d-flex align-center gap-2 flex-wrap mb-1">
              <span class="font-weight-bold">{{ row.label }}</span>
              <VChip
                :color="sourceColor(row.source)"
                size="x-small"
                label
                variant="tonal"
              >
                {{ row.source_label }}
              </VChip>
              <span class="text-caption text-medium-emphasis">{{ fmt(row.created_at) }}{{ row.actor_name ? ` · ${row.actor_name}` : '' }}</span>
            </div>
            <div
              v-if="row.is_file"
              class="d-flex gap-3 text-body-2 flex-wrap"
            >
              <a
                v-if="row.old_file_url"
                :href="row.old_file_url"
                target="_blank"
                rel="noopener noreferrer"
              >المستند السابق</a>
              <span
                v-else
                class="text-medium-emphasis"
              >لا يوجد سابق</span>
              <VIcon
                icon="tabler-arrow-left"
                size="14"
              />
              <a
                v-if="row.new_file_url"
                :href="row.new_file_url"
                target="_blank"
                rel="noopener noreferrer"
              >المستند الجديد</a>
              <span
                v-else
                class="text-medium-emphasis"
              >أزيل</span>
            </div>
            <div
              v-else
              class="d-flex align-center gap-2 text-body-2 flex-wrap"
              style="overflow-wrap: anywhere;"
            >
              <span class="text-medium-emphasis">{{ row.old_display || 'فارغ' }}</span>
              <VIcon
                icon="tabler-arrow-left"
                size="14"
              />
              <span class="font-weight-medium">{{ row.new_display || 'فارغ' }}</span>
            </div>
            <div
              v-if="row.note"
              class="text-caption text-medium-emphasis mt-1"
            >
              {{ row.note }}
            </div>
          </VTimelineItem>
        </VTimeline>

        <div
          v-if="page < lastPage"
          class="d-flex justify-center mt-2"
        >
          <VBtn
            variant="tonal"
            size="small"
            :loading="loading"
            @click="more"
          >
            عرض المزيد
          </VBtn>
        </div>
      </VCardText>
    </VCard>
  </VDialog>
</template>

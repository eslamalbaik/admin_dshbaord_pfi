<script setup lang="ts">
import type { ChangeRow } from '@/utils/activityLogDetails'
import { buildRows, extractChanges, formatAny, formatDateTime, subjectLabel } from '@/utils/activityLogDetails'

// نافذة «تفاصيل الإجراء» بسجل النشاط: معلومات أساسية (مين، متى، على أي عنصر)،
// التغييرات قبل ← بعد، وباقي الحقول كقائمة «الحقل: القيمة» بالعربي — بدون JSON.
const props = defineProps<{ log: any; title: string }>()
const model = defineModel<boolean>({ default: false })

const meta = computed<Record<string, any>>(() => props.log?.meta ?? {})

const changes = computed<ChangeRow[]>(() => {
  const log = props.log
  if (!log)
    return []

  // الأحداث الحرجة: التغييرات جاهزة من الباك إند بمسمّيات حقولها
  if (log.critical?.changes?.length) {
    const ctx = { ...meta.value, ...(meta.value.after ?? {}) }

    return log.critical.changes.map((c: any) => ({
      key: c.field,
      label: c.label,
      before: formatAny(c.field, c.before, ctx),
      after: formatAny(c.field, c.after, ctx),
    }))
  }

  return extractChanges(log.action, meta.value).changes
})

const rows = computed(() => {
  const log = props.log
  if (!log)
    return []

  const hidden = ['reason', ...extractChanges(log.action, meta.value).used]
  if (log.critical)
    hidden.push('before', 'after')

  // المقاول معروض بالمعلومات الأساسية فوق
  if (log.contractor || meta.value.contractor_name)
    hidden.push('contractor_id', 'contractor_name', 'membership_number')

  return buildRows(meta.value, hidden)
})

const reason = computed(() => props.log?.critical?.reason ?? meta.value.reason ?? null)

const contractorName = computed(() => props.log?.contractor?.name ?? meta.value.contractor_name ?? null)
const membershipNumber = computed(() => props.log?.contractor?.membership_number ?? meta.value.membership_number ?? null)

const info = computed(() => {
  const log = props.log
  if (!log)
    return []

  return [
    { label: 'الإجراء', value: props.title },
    { label: 'نفّذه', value: log.actor_name ? `${log.actor_name}${log.actor_email ? ` (${log.actor_email})` : ''}` : 'النظام (تلقائي)' },
    { label: 'الوقت', value: log.created_at ? formatDateTime(log.created_at) : '—' },
    contractorName.value
      ? { label: 'المقاول', value: `${contractorName.value}${membershipNumber.value ? ` — عضوية ${membershipNumber.value}` : ''}` }
      : null,
    log.subject_type && log.subject_type !== 'Contractor'
      ? { label: 'العنصر المتأثر', value: subjectLabel(log.subject_type, log.subject_id) }
      : null,
    { label: 'رقم السجل', value: `#${log.id}` },
  ].filter(Boolean) as { label: string; value: string }[]
})
</script>

<template>
  <VDialog v-model="model" max-width="680" scrollable>
    <VCard v-if="log" dir="rtl" style="font-family:Cairo,sans-serif">
      <VCardTitle class="d-flex align-center flex-wrap gap-2 pt-5">
        <VIcon :icon="log.is_critical ? 'tabler-alert-triangle' : 'tabler-file-description'" :color="log.is_critical ? 'error' : 'primary'" />
        <span>تفاصيل الإجراء</span>
        <VSpacer />
        <VChip v-if="log.is_financial" size="small" color="warning" variant="tonal" label prepend-icon="tabler-cash">
          مالي
        </VChip>
        <VChip v-if="log.is_critical" size="small" color="error" variant="tonal" label>
          {{ log.critical?.category_label ?? 'محدد هام' }}
        </VChip>
      </VCardTitle>

      <VCardText>
        <!-- المعلومات الأساسية -->
        <VTable density="compact" class="border rounded mb-4 details-table">
          <tbody>
            <tr v-for="i in info" :key="i.label">
              <th>{{ i.label }}</th>
              <td>{{ i.value }}</td>
            </tr>
          </tbody>
        </VTable>

        <!-- التغييرات قبل ← بعد -->
        <template v-if="changes.length">
          <div class="text-subtitle-2 font-weight-bold mb-2">التغييرات</div>
          <VTable density="compact" class="border rounded mb-4">
            <thead>
              <tr>
                <th>البند</th>
                <th>قبل</th>
                <th />
                <th>بعد</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="c in changes" :key="c.key">
                <td class="font-weight-medium">{{ c.label }}</td>
                <td class="text-error">{{ c.before }}</td>
                <td class="text-medium-emphasis px-0">←</td>
                <td class="text-success">{{ c.after }}</td>
              </tr>
            </tbody>
          </VTable>
        </template>
        <div v-else-if="log.critical" class="text-body-2 text-medium-emphasis mb-4">
          سُجّل هذا الحدث قبل تفعيل تسجيل القيم قبل/بعد.
        </div>

        <!-- باقي التفاصيل -->
        <template v-if="rows.length">
          <div class="text-subtitle-2 font-weight-bold mb-2">تفاصيل إضافية</div>
          <VTable density="compact" class="border rounded mb-4 details-table">
            <tbody>
              <template v-for="r in rows" :key="r.key">
                <tr>
                  <th>{{ r.label }}</th>
                  <td :class="{ 'long-text': r.long }">{{ r.value }}</td>
                </tr>
                <template v-if="r.children">
                  <template v-for="c in r.children" :key="c.key">
                    <tr>
                      <th class="ps-8 text-medium-emphasis">{{ c.label }}</th>
                      <td :class="{ 'long-text': c.long }">{{ c.value }}</td>
                    </tr>
                    <tr v-for="g in c.children ?? []" :key="g.key">
                      <th class="ps-12 text-medium-emphasis">{{ g.label }}</th>
                      <td>{{ g.children ? g.children.map(x => `${x.label}: ${x.value}`).join('، ') : g.value }}</td>
                    </tr>
                  </template>
                </template>
              </template>
            </tbody>
          </VTable>
        </template>

        <VAlert v-if="reason || log.critical" type="info" variant="tonal" density="compact">
          <strong>السبب:</strong> {{ reason || 'لم يُذكر سبب' }}
        </VAlert>
      </VCardText>

      <VCardActions>
        <VSpacer />
        <VBtn variant="tonal" @click="model = false">إغلاق</VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style scoped>
.details-table th {
  width: 38%;
  font-weight: 500;
  text-align: start;
  color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity));
}

.details-table td {
  word-break: break-word;
}

.long-text {
  white-space: pre-wrap;
  max-height: 160px;
  overflow-y: auto;
  display: block;
  padding-block: 8px;
}
</style>

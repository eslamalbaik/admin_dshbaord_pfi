<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query'
import api from '@/plugins/axios'
import AssistantAvatar from '@/components/AssistantAvatar.vue'

definePage({ meta: { requiresAdmin: true } })

/** سجل محادثات المساعد الافتراضي "سامي" مع زوار الموقع العام */
interface ConvRow { id: number; first_question: string | null; messages_count: number; questions_count: number; ai_answers_count: number; unanswered_count: number; page: string | null; device: string | null; started_at: string; last_message_at: string | null }
interface ConvMsg { id: number; role: 'user' | 'bot'; text: string; source: string | null; created_at: string }

const SOURCES: Record<string, { label: string; color: string }> = {
  ai: { label: 'ذكاء اصطناعي', color: 'primary' },
  faq: { label: 'سؤال شائع', color: 'success' },
  smalltalk: { label: 'رد جاهز', color: 'info' },
  page: { label: 'توجيه لصفحة', color: 'secondary' },
  fallback: { label: 'بدون جواب', color: 'warning' },
}

const queryClient = useQueryClient()
const search = ref('')
const debounced = ref('')
const source = ref<string | null>(null)
const page = ref(1)
let t: ReturnType<typeof setTimeout> | undefined
watch(search, v => { clearTimeout(t); t = setTimeout(() => { debounced.value = v.trim(); page.value = 1 }, 350) })
watch(source, () => { page.value = 1 })

const { data, isLoading, isFetching } = useQuery({
  queryKey: ['assistant-conversations', debounced, source, page],
  queryFn: async () => (await api.get('/api/v1/dashboard/assistant-conversations', {
    params: { search: debounced.value || undefined, source: source.value || undefined, page: page.value, per_page: 20 },
  })).data,
  placeholderData: prev => prev,
})
const rows = computed<ConvRow[]>(() => data.value?.items ?? [])
const meta = computed(() => data.value?.meta ?? { total: 0, last_page: 1, today: 0 })

const openId = ref<number | null>(null)
const { data: detail, isLoading: detailLoading } = useQuery({
  queryKey: ['assistant-conversation', openId],
  queryFn: async () => (await api.get(`/api/v1/dashboard/assistant-conversations/${openId.value}`)).data,
  enabled: computed(() => openId.value !== null),
})
const dialog = computed({ get: () => openId.value !== null, set: v => { if (!v) openId.value = null } })
const msgs = computed<ConvMsg[]>(() => detail.value?.messages ?? [])

const del = useMutation({
  mutationFn: async (id: number) => (await api.delete(`/api/v1/dashboard/assistant-conversations/${id}`)).data,
  onSuccess: () => { openId.value = null; queryClient.invalidateQueries({ queryKey: ['assistant-conversations'] }) },
})
function confirmDelete(id: number) {
  if (window.confirm('حذف هذه المحادثة نهائياً من السجل؟')) del.mutate(id)
}

const fmt = (iso?: string | null) => iso
  ? new Intl.DateTimeFormat('ar-EG-u-nu-latn', { dateStyle: 'medium', timeStyle: 'short', timeZone: 'Asia/Gaza' }).format(new Date(iso))
  : '—'
const time = (iso?: string | null) => iso
  ? new Intl.DateTimeFormat('ar-EG-u-nu-latn', { timeStyle: 'short', timeZone: 'Asia/Gaza' }).format(new Date(iso))
  : ''
</script>

<template>
  <div>
    <div class="d-flex justify-space-between align-center mb-6 flex-wrap gap-4">
      <div class="d-flex align-center gap-3">
        <AssistantAvatar kind="bot" :size="48" />
        <div>
          <h1 class="text-h4 font-weight-bold">سجل محادثات المساعد</h1>
          <p class="text-body-2 text-medium-emphasis mb-0">أسئلة زوار الموقع لسامي، المساعد الافتراضي، وردوده عليهم</p>
        </div>
      </div>
      <div class="d-flex gap-2 flex-wrap">
        <VChip color="primary" variant="tonal" prepend-icon="tabler-messages">{{ meta.total }} محادثة</VChip>
        <VChip color="success" variant="tonal" prepend-icon="tabler-calendar">{{ meta.today }} اليوم</VChip>
        <VBtn variant="tonal" prepend-icon="tabler-help-circle" :to="{ name: 'faqs' }">الأسئلة الشائعة</VBtn>
      </div>
    </div>

    <VCard>
      <VCardText class="d-flex flex-wrap gap-4">
        <AppTextField v-model="search" placeholder="ابحث بنص السؤال أو الجواب" prepend-inner-icon="tabler-search" clearable style="min-inline-size: 240px; flex: 1;" />
        <AppSelect v-model="source" :items="[{ title: 'كل الردود', value: null }, ...Object.entries(SOURCES).map(([value, s]) => ({ title: s.label, value }))]" style="min-inline-size: 200px; max-inline-size: 260px;" />
      </VCardText>
      <VProgressLinear v-if="isLoading || isFetching" indeterminate color="primary" />

      <div v-if="!isLoading && rows.length === 0" class="text-center py-12">
        <VIcon icon="tabler-message-off" size="56" color="disabled" class="mb-3" />
        <p class="text-h6 text-medium-emphasis mb-0">لا توجد محادثات {{ debounced || source ? 'مطابقة' : 'بعد' }}</p>
      </div>

      <VTable v-else class="text-no-wrap conv-table" hover>
        <thead>
          <tr>
            <th>أول سؤال</th>
            <th class="text-center">الأسئلة</th>
            <th>الردود</th>
            <th>الصفحة</th>
            <th>الجهاز</th>
            <th>آخر رسالة</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="r in rows" :key="r.id" class="cursor-pointer" @click="openId = r.id">
            <td class="conv-q">{{ r.first_question || '—' }}</td>
            <td class="text-center">{{ r.questions_count }}</td>
            <td>
              <div class="d-flex gap-1">
                <VChip v-if="r.ai_answers_count" size="x-small" color="primary" variant="tonal">ذكاء اصطناعي {{ r.ai_answers_count }}</VChip>
                <VChip v-if="r.unanswered_count" size="x-small" color="warning" variant="tonal">بدون جواب {{ r.unanswered_count }}</VChip>
              </div>
            </td>
            <td dir="ltr" class="text-caption text-medium-emphasis">{{ r.page || '—' }}</td>
            <td>{{ r.device || '—' }}</td>
            <td>{{ fmt(r.last_message_at) }}</td>
          </tr>
        </tbody>
      </VTable>

      <VCardText v-if="meta.last_page > 1" class="d-flex justify-center">
        <VPagination v-model="page" :length="meta.last_page" :total-visible="5" />
      </VCardText>
    </VCard>

    <VDialog v-model="dialog" max-width="620" scrollable>
      <VCard>
        <VCardTitle class="d-flex align-center justify-space-between gap-2 pa-4">
          <div>
            <div class="text-h6">محادثة #{{ openId }}</div>
            <div class="text-caption text-medium-emphasis">{{ fmt(detail?.started_at) }} · {{ detail?.device || '—' }} · <span dir="ltr">{{ detail?.page || '' }}</span></div>
          </div>
          <div class="d-flex gap-1">
            <VBtn icon="tabler-trash" color="error" variant="text" size="small" :loading="del.isPending.value" @click="openId && confirmDelete(openId)" />
            <VBtn icon="tabler-x" variant="text" size="small" @click="openId = null" />
          </div>
        </VCardTitle>
        <VDivider />
        <VCardText class="conv-body">
          <VProgressLinear v-if="detailLoading" indeterminate color="primary" />
          <div v-for="m in msgs" :key="m.id" class="conv-row" :class="`is-${m.role}`">
            <AssistantAvatar :kind="m.role" :size="32" />
            <div class="conv-msg" :class="`is-${m.role}`">
              <div class="d-flex align-center gap-2 mb-1">
                <strong class="text-caption">{{ m.role === 'user' ? 'الزائر' : 'سامي' }}</strong>
                <VChip v-if="m.role === 'bot' && m.source && SOURCES[m.source]" size="x-small" :color="SOURCES[m.source].color" variant="tonal">{{ SOURCES[m.source].label }}</VChip>
                <span class="text-caption conv-time">{{ time(m.created_at) }}</span>
              </div>
              <p class="mb-0">{{ m.text }}</p>
            </div>
          </div>
        </VCardText>
      </VCard>
    </VDialog>
  </div>
</template>

<style scoped>
.conv-q { max-inline-size: 360px; overflow: hidden; text-overflow: ellipsis; font-weight: 600; }
.conv-body { display: flex; flex-direction: column; gap: .8rem; background: rgba(var(--v-theme-on-surface), .03); }
.conv-row { display: flex; align-items: flex-end; gap: .5rem; max-inline-size: 90%; }
.conv-row.is-bot { align-self: flex-start; }
.conv-row.is-user { align-self: flex-end; flex-direction: row-reverse; }
.conv-msg { padding: .6rem .85rem; border-radius: 14px; white-space: pre-line; line-height: 1.8; font-size: .92rem; }
.conv-msg.is-bot { background: rgb(var(--v-theme-surface)); border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity)); border-bottom-right-radius: 4px; }
.conv-msg.is-user { background: #00006e; color: #fff; border-bottom-left-radius: 4px; }
.conv-msg.is-user .conv-time, .conv-msg.is-user strong { color: rgba(255, 255, 255, .85); }
.conv-time { opacity: .7; margin-inline-start: auto; }
</style>

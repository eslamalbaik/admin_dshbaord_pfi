<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { useQuery, useMutation, useQueryClient } from '@tanstack/vue-query'
import { useRoute, useRouter } from 'vue-router'
import api from '@/plugins/axios'

definePage({ meta: { requiresAdmin: true } })

const queryClient = useQueryClient()
const route = useRoute()
const router = useRouter()

const page = ref(Number(route.query.page) || 1)
const statusFilter = ref<string | null>((route.query.status as string) || null)
const categoryFilter = ref<string | null>((route.query.category as string) || null)
const search = ref((route.query.search as string) || '')

const statusOptions = [
  { value: 'open', title: 'مفتوح' },
  { value: 'in_progress', title: 'قيد المعالجة' },
  { value: 'answered', title: 'تم الرد' },
  { value: 'closed', title: 'مغلق' },
]

const categoryOptions = [
  { value: 'technical', title: 'مشكلة تقنية' },
  { value: 'complaint', title: 'شكوى' },
  { value: 'inquiry', title: 'استفسار' },
  { value: 'suggestion', title: 'اقتراح' },
  { value: 'other', title: 'أخرى' },
]

const statusColor: Record<string, string> = {
  open: 'warning',
  in_progress: 'info',
  answered: 'success',
  closed: 'secondary',
}

watch([statusFilter, categoryFilter, search], () => page.value = 1)

const { data, isLoading } = useQuery({
  queryKey: computed(() => ['support-tickets', page.value, statusFilter.value, categoryFilter.value, search.value]),
  queryFn: async () => {
    const params: Record<string, any> = { page: page.value }
    if (statusFilter.value) params.status = statusFilter.value
    if (categoryFilter.value) params.category = categoryFilter.value
    if (search.value) params.search = search.value

    return (await api.get('/api/v1/dashboard/support-tickets', { params })).data
  },
})

const tickets = computed(() => data.value?.items ?? [])
const lastPage = computed(() => data.value?.meta?.last_page ?? 1)

// ─── عرض التذكرة والرد ───
const isViewOpen = ref(false)
const selected = ref<any>(null)
const replyText = ref('')
const actionError = ref('')

const openTicket = (t: any) => {
  selected.value = t
  replyText.value = ''
  actionError.value = ''
  isViewOpen.value = true
}

// ─── حفظ الحالة في رابط الصفحة ───
watch([page, statusFilter, categoryFilter, search, isViewOpen], () => {
  const query: Record<string, any> = {}
  if (page.value > 1) query.page = page.value
  if (statusFilter.value) query.status = statusFilter.value
  if (categoryFilter.value) query.category = categoryFilter.value
  if (search.value) query.search = search.value
  if (isViewOpen.value && selected.value) query.ticket = selected.value.id

  router.replace({ query })
})

const isRestoring = ref(true)
watch(tickets, (newTickets) => {
  if (isRestoring.value && route.query.ticket) {
    const tId = Number(route.query.ticket)
    const t = newTickets.find((x: any) => x.id === tId)
    if (t) {
      openTicket(t)
    }
    isRestoring.value = false
  }
}, { immediate: true })

const replyMutation = useMutation({
  mutationFn: async () =>
    (await api.post(`/api/v1/dashboard/support-tickets/${selected.value.id}/reply`, { reply: replyText.value })).data,
  onSuccess: (d: any) => {
    queryClient.invalidateQueries({ queryKey: ['support-tickets'] })
    selected.value = d?.items ?? selected.value
    replyText.value = ''
  },
  onError: (e: any) => {
    actionError.value = e?.response?.data?.message || 'فشل إرسال الرد.'
  },
})

const statusMutation = useMutation({
  mutationFn: async (status: string) =>
    (await api.patch(`/api/v1/dashboard/support-tickets/${selected.value.id}/status`, { status })).data,
  onSuccess: (d: any) => {
    queryClient.invalidateQueries({ queryKey: ['support-tickets'] })
    selected.value = d?.items ?? selected.value
  },
  onError: (e: any) => {
    actionError.value = e?.response?.data?.message || 'فشل تحديث الحالة.'
  },
})

const deleteMutation = useMutation({
  mutationFn: async (id: number) => (await api.delete(`/api/v1/dashboard/support-tickets/${id}`)).data,
  onSuccess: () => {
    queryClient.invalidateQueries({ queryKey: ['support-tickets'] })
    isViewOpen.value = false
  },
})

const confirmDelete = (t: any) => {
  if (confirm('هل تريد حذف هذا الطلب نهائيًا؟'))
    deleteMutation.mutate(t.id)
}

function fmtDate(d: string | null) {
  if (!d) return '—'
  return new Date(d).toLocaleDateString('ar-EG', { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' })
}

// رقم واتساب المقاول بصيغة wa.me (يشيل أي رمز غير رقمي)
function waLink(phone: string | null) {
  if (!phone) return null
  return `https://wa.me/${phone.replace(/\D/g, '')}`
}
</script>

<template>
  <div>
    <div class="d-flex justify-space-between align-center mb-6 flex-wrap gap-4">
      <div>
        <h1 class="text-h4 font-weight-bold">الدعم الفني والشكاوى</h1>
        <p class="text-body-2 text-medium-emphasis mb-0">
          إدارة طلبات الدعم والشكاوى الواردة من المقاولين والرد عليها
        </p>
      </div>
    </div>

    <!-- Filters -->
    <VCard class="mb-6 pa-4">
      <VRow dense>
        <VCol cols="12" md="4">
          <VTextField
            v-model="search"
            label="بحث بالموضوع أو اسم المقاول"
            prepend-inner-icon="tabler-search"
            density="compact"
            clearable
          />
        </VCol>
        <VCol cols="6" md="4">
          <VSelect
            v-model="statusFilter"
            :items="statusOptions"
            label="الحالة"
            density="compact"
            clearable
          />
        </VCol>
        <VCol cols="6" md="4">
          <VSelect
            v-model="categoryFilter"
            :items="categoryOptions"
            label="التصنيف"
            density="compact"
            clearable
          />
        </VCol>
      </VRow>
    </VCard>

    <VProgressLinear v-if="isLoading" indeterminate color="primary" />

    <VCard v-else-if="tickets.length === 0" class="text-center py-12">
      <VIcon icon="tabler-headset-off" size="64" color="disabled" class="mb-3" />
      <p class="text-h6 text-medium-emphasis">لا توجد تذاكر مطابقة.</p>
    </VCard>

    <template v-else>
      <VCard>
        <VTable>
          <thead>
            <tr>
              <th>#</th>
              <th>المقاول</th>
              <th>الموضوع</th>
              <th>واتساب</th>
              <th>التصنيف</th>
              <th>الحالة</th>
              <th>التاريخ</th>
              <th class="text-center">إجراءات</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="t in tickets" :key="t.id" style="cursor: pointer" @click="openTicket(t)">
              <td>{{ t.id }}</td>
              <td class="font-weight-medium">{{ t.contractor ?? '—' }}</td>
              <td>{{ t.subject }}</td>
              <td>
                <a
                  v-if="waLink(t.whatsapp_phone)"
                  :href="waLink(t.whatsapp_phone)!"
                  target="_blank"
                  class="text-success"
                  @click.stop
                >
                  {{ t.whatsapp_phone }}
                </a>
                <span v-else>—</span>
              </td>
              <td>
                <VChip size="x-small" variant="tonal">{{ t.category_label ?? t.category }}</VChip>
              </td>
              <td>
                <VChip size="x-small" :color="statusColor[t.status] ?? 'secondary'">
                  {{ t.status_label ?? t.status }}
                </VChip>
              </td>
              <td class="text-body-2">{{ fmtDate(t.created_at) }}</td>
              <td class="text-center" @click.stop>
                <VBtn icon="tabler-eye" size="x-small" variant="text" @click="openTicket(t)" />
                <VBtn icon="tabler-trash" size="x-small" variant="text" color="error" @click="confirmDelete(t)" />
              </td>
            </tr>
          </tbody>
        </VTable>
      </VCard>

      <div v-if="lastPage > 1" class="d-flex justify-center mt-4">
        <VPagination v-model="page" :length="lastPage" total-visible="7" />
      </div>
    </template>

    <!-- ─── Ticket Dialog ─── -->
    <VDialog v-model="isViewOpen" max-width="720" scrollable>
      <VCard v-if="selected">
        <VCardTitle class="d-flex align-center justify-space-between pt-4">
          <span class="text-h6">تذكرة #{{ selected.id }} — {{ selected.subject }}</span>
          <VChip size="small" :color="statusColor[selected.status] ?? 'secondary'">
            {{ selected.status_label ?? selected.status }}
          </VChip>
        </VCardTitle>

        <VCardText>
          <VAlert v-if="actionError" type="error" variant="tonal" class="mb-4">
            {{ actionError }}
          </VAlert>

          <div class="d-flex flex-wrap gap-4 mb-4 text-body-2">
            <div><strong>المقاول:</strong> {{ selected.contractor ?? '—' }}</div>
            <div><strong>التصنيف:</strong> {{ selected.category_label ?? selected.category }}</div>
            <div><strong>التاريخ:</strong> {{ fmtDate(selected.created_at) }}</div>
            <div v-if="selected.whatsapp_phone">
              <strong>واتساب:</strong>
              <a :href="waLink(selected.whatsapp_phone)!" target="_blank" class="text-success">
                {{ selected.whatsapp_phone }}
              </a>
            </div>
          </div>

          <!-- ─── المحادثة: الرسالة الأصلية أولاً ثم كل الردود بالترتيب الزمني ─── -->
          <div class="chat-thread mb-4">
            <div class="chat-bubble chat-bubble--contractor">
              <div class="d-flex justify-space-between align-center mb-1">
                <span class="text-caption font-weight-bold">{{ selected.contractor ?? 'المقاول' }}</span>
                <span class="text-caption text-medium-emphasis">{{ fmtDate(selected.created_at) }}</span>
              </div>
              <p class="text-body-2 mb-0" style="white-space: pre-wrap; line-height: 1.8">{{ selected.message }}</p>
              <VBtn
                v-if="selected.attachment_url"
                class="mt-2"
                size="small"
                variant="tonal"
                prepend-icon="tabler-paperclip"
                :href="selected.attachment_url"
                target="_blank"
              >
                عرض المرفق
              </VBtn>
            </div>

            <div
              v-for="m in selected.messages"
              :key="m.id"
              class="chat-bubble"
              :class="m.sender_type === 'admin' ? 'chat-bubble--admin' : 'chat-bubble--contractor'"
            >
              <div class="d-flex justify-space-between align-center mb-1">
                <span class="text-caption font-weight-bold">{{ m.sender_name ?? (m.sender_type === 'admin' ? 'الإدارة' : 'المقاول') }}</span>
                <span class="text-caption text-medium-emphasis">{{ fmtDate(m.created_at) }}</span>
              </div>
              <p class="text-body-2 mb-0" style="white-space: pre-wrap; line-height: 1.8">{{ m.message }}</p>
              <VBtn
                v-if="m.attachment_url"
                class="mt-2"
                size="small"
                variant="tonal"
                prepend-icon="tabler-paperclip"
                :href="m.attachment_url"
                target="_blank"
              >
                عرض المرفق
              </VBtn>
            </div>
          </div>

          <VTextarea
            v-model="replyText"
            label="الرد على المقاول (يُرسل بريدًا وإشعارًا في التطبيق)"
            rows="4"
            dir="rtl"
          />

          <div class="d-flex align-center gap-3 mt-4 flex-wrap">
            <span class="text-body-2 font-weight-medium">تغيير الحالة:</span>
            <VBtn
              v-for="s in statusOptions"
              :key="s.value"
              size="x-small"
              :color="statusColor[s.value]"
              :variant="selected.status === s.value ? 'flat' : 'tonal'"
              :loading="statusMutation.isPending.value"
              @click="statusMutation.mutate(s.value)"
            >
              {{ s.title }}
            </VBtn>
          </div>
        </VCardText>

        <VCardActions>
          <VBtn color="error" variant="text" @click="confirmDelete(selected)">حذف</VBtn>
          <VSpacer />
          <VBtn variant="text" @click="isViewOpen = false">إغلاق</VBtn>
          <VBtn
            color="primary"
            prepend-icon="tabler-send"
            :disabled="!replyText"
            :loading="replyMutation.isPending.value"
            @click="replyMutation.mutate()"
          >
            إرسال الرد
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>

<style scoped>
.chat-thread {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
  max-height: 360px;
  overflow-y: auto;
  padding: 0.25rem;
}
.chat-bubble {
  max-width: 85%;
  padding: 0.75rem 1rem;
  border-radius: 0.5rem;
}
.chat-bubble--contractor {
  align-self: flex-start;
  background: rgba(var(--v-theme-secondary), 0.12);
  border-inline-start: 3px solid rgb(var(--v-theme-secondary));
}
.chat-bubble--admin {
  align-self: flex-end;
  background: rgba(var(--v-theme-success), 0.12);
  border-inline-end: 3px solid rgb(var(--v-theme-success));
}
</style>

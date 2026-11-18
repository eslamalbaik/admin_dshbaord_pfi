<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query'
import { useRoute } from 'vue-router'
import api from '@/plugins/axios'

definePage({ meta: { requiresAdmin: true } })

const queryClient = useQueryClient()
const route = useRoute()

const page = ref(1)
const search = ref('')
watch(search, () => page.value = 1)

const { data, isLoading } = useQuery({
  queryKey: computed(() => ['contact-messages', page.value, search.value]),
  queryFn: async () => (await api.get('/api/v1/dashboard/contact-messages', { params: { page: page.value, search: search.value || undefined } })).data,
})

const items = computed<any[]>(() => data.value?.items ?? [])
const lastPage = computed(() => data.value?.meta?.last_page ?? 1)

const isOpen = ref(false)
const selected = ref<any>(null)

const markRead = useMutation({
  mutationFn: (id: number) => api.patch(`/api/v1/dashboard/contact-messages/${id}/read`),
  onSuccess: () => queryClient.invalidateQueries({ queryKey: ['contact-messages'] }),
})
const remove = useMutation({
  mutationFn: (id: number) => api.delete(`/api/v1/dashboard/contact-messages/${id}`),
  onSuccess: () => { isOpen.value = false; queryClient.invalidateQueries({ queryKey: ['contact-messages'] }) },
})

function open(m: any) {
  selected.value = m
  isOpen.value = true
  if (!m.read_at) markRead.mutate(m.id)
}

// الفتح التلقائي عند القدوم من الإشعار (?id=)
watch(items, list => {
  const id = Number(route.query.id)
  if (id && !isOpen.value) {
    const m = list.find(x => x.id === id)
    if (m) open(m)
  }
})

const fmt = (d: string) => new Date(d).toLocaleString('ar-EG')
</script>

<template>
  <VCard>
    <VCardItem title="رسائل تواصل معنا">
      <template #append>
        <AppTextField v-model="search" placeholder="بحث بالاسم أو الموضوع..." style="inline-size: 260px;" />
      </template>
    </VCardItem>
    <VDivider />
    <VProgressLinear v-if="isLoading" indeterminate />
    <VTable hover>
      <thead>
        <tr>
          <th>المرسل</th>
          <th>الموضوع</th>
          <th>النوع</th>
          <th>التاريخ</th>
          <th />
        </tr>
      </thead>
      <tbody>
        <tr v-for="m in items" :key="m.id" style="cursor: pointer;" @click="open(m)">
          <td>
            <VChip v-if="!m.read_at" size="x-small" color="info" class="me-2">جديد</VChip>{{ m.name }}
          </td>
          <td>{{ m.subject }}</td>
          <td>{{ m.type || '—' }}</td>
          <td>{{ fmt(m.created_at) }}</td>
          <td><VIcon icon="tabler-eye" /></td>
        </tr>
        <tr v-if="!isLoading && !items.length">
          <td colspan="5" class="text-center pa-6">لا توجد رسائل</td>
        </tr>
      </tbody>
    </VTable>
    <VPagination v-if="lastPage > 1" v-model="page" :length="lastPage" class="pa-4" />
  </VCard>

  <VDialog v-model="isOpen" max-width="560">
    <VCard v-if="selected" :title="selected.subject">
      <VCardText>
        <div class="mb-2"><strong>الاسم:</strong> {{ selected.name }}</div>
        <div class="mb-2"><strong>البريد:</strong> <a :href="`mailto:${selected.email}`">{{ selected.email }}</a></div>
        <div v-if="selected.phone" class="mb-2"><strong>الهاتف:</strong> <span dir="ltr">{{ selected.phone }}</span></div>
        <div v-if="selected.type" class="mb-2"><strong>نوع الاستفسار:</strong> {{ selected.type }}</div>
        <div class="mb-2 text-medium-emphasis">{{ fmt(selected.created_at) }}</div>
        <VDivider class="my-3" />
        <p style="white-space: pre-line;">{{ selected.message }}</p>
      </VCardText>
      <VCardActions>
        <VBtn color="error" variant="text" :loading="remove.isPending.value" @click="remove.mutate(selected.id)">حذف</VBtn>
        <VSpacer />
        <VBtn @click="isOpen = false">إغلاق</VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

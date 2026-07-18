<script setup lang="ts">
import { ref } from 'vue'
import { useQuery, useMutation, useQueryClient } from '@tanstack/vue-query'
import api from '@/plugins/axios'

definePage({ meta: { requiresAdmin: true } })

const queryClient = useQueryClient()

interface PageItem {
  id: number
  slug: string
  title: string
  content: string | null
  is_published: boolean
  created_by?: string | null
  updated_at?: string
}

const successMessage = ref('')
const errorMessage = ref('')

const { data, isLoading } = useQuery({
  queryKey: ['dashboard-pages'],
  queryFn: async () => (await api.get('/api/v1/dashboard/pages', { params: { per_page: 100 } })).data,
})

// ─── نموذج إنشاء/تعديل ───
const dialog = ref(false)
const editing = ref<PageItem | null>(null)
const form = ref({ slug: '', title: '', content: '', is_published: true })

function openCreate() {
  editing.value = null
  form.value = { slug: '', title: '', content: '', is_published: true }
  dialog.value = true
}

function openEdit(p: PageItem) {
  editing.value = p
  form.value = {
    slug: p.slug,
    title: p.title,
    content: p.content ?? '',
    is_published: p.is_published,
  }
  dialog.value = true
}

const saveMutation = useMutation({
  mutationFn: async () => {
    if (editing.value)
      return (await api.put(`/api/v1/dashboard/pages/${editing.value.id}`, form.value)).data

    return (await api.post('/api/v1/dashboard/pages', form.value)).data
  },
  onSuccess: () => {
    queryClient.invalidateQueries({ queryKey: ['dashboard-pages'] })
    dialog.value = false
    successMessage.value = 'تم حفظ الصفحة بنجاح.'
    errorMessage.value = ''
    setTimeout(() => successMessage.value = '', 4000)
  },
  onError: (e: any) => {
    errorMessage.value = e?.response?.data?.message
      || Object.values(e?.response?.data?.errors ?? {}).flat().join('، ')
      || 'فشل حفظ الصفحة.'
  },
})

const deleteMutation = useMutation({
  mutationFn: async (id: number) => (await api.delete(`/api/v1/dashboard/pages/${id}`)).data,
  onSuccess: () => {
    queryClient.invalidateQueries({ queryKey: ['dashboard-pages'] })
    successMessage.value = 'تم حذف الصفحة.'
    setTimeout(() => successMessage.value = '', 4000)
  },
})

function publicUrl(slug: string) {
  return `${window.location.origin}/${slug}`
}
</script>

<template>
  <div>
    <div class="d-flex align-center justify-space-between mb-6">
      <div>
        <h1 class="text-h4 font-weight-bold">الصفحات الديناميكية</h1>
        <p class="text-body-2 text-medium-emphasis mb-0">
          صفحات عامة على روابط مخصصة، مثال: pcu.org.ps/testing
        </p>
      </div>
      <VBtn prepend-icon="tabler-plus" @click="openCreate">
        صفحة جديدة
      </VBtn>
    </div>

    <VAlert v-if="successMessage" type="success" variant="tonal" class="mb-4">
      {{ successMessage }}
    </VAlert>
    <VAlert v-if="errorMessage" type="error" variant="tonal" class="mb-4">
      {{ errorMessage }}
    </VAlert>

    <VCard>
      <VProgressLinear v-if="isLoading" indeterminate color="primary" />
      <VTable>
        <thead>
          <tr>
            <th>العنوان</th>
            <th>الرابط</th>
            <th>الحالة</th>
            <th>آخر تحديث</th>
            <th class="text-end">إجراءات</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="p in (data?.items ?? [])" :key="p.id">
            <td>{{ p.title }}</td>
            <td dir="ltr">
              <a :href="publicUrl(p.slug)" target="_blank" rel="noopener">/{{ p.slug }}</a>
            </td>
            <td>
              <VChip :color="p.is_published ? 'success' : 'secondary'" size="small">
                {{ p.is_published ? 'منشورة' : 'مسودة' }}
              </VChip>
            </td>
            <td>{{ p.updated_at ? new Date(p.updated_at).toLocaleDateString('ar-EG') : '—' }}</td>
            <td class="text-end">
              <VBtn icon="tabler-edit" size="small" variant="text" @click="openEdit(p)" />
              <VBtn
                icon="tabler-trash"
                size="small"
                variant="text"
                color="error"
                @click="deleteMutation.mutate(p.id)"
              />
            </td>
          </tr>
          <tr v-if="!isLoading && !(data?.items ?? []).length">
            <td colspan="5" class="text-center text-medium-emphasis py-8">
              لا توجد صفحات بعد
            </td>
          </tr>
        </tbody>
      </VTable>
    </VCard>

    <!-- ─── Dialog إنشاء/تعديل ─── -->
    <VDialog v-model="dialog" max-width="800">
      <VCard :title="editing ? 'تعديل الصفحة' : 'صفحة جديدة'">
        <VCardText>
          <VRow>
            <VCol cols="12" md="6">
              <VTextField v-model="form.title" label="عنوان الصفحة" dir="rtl" />
            </VCol>
            <VCol cols="12" md="6">
              <VTextField
                v-model="form.slug"
                label="الرابط (slug)"
                dir="ltr"
                hint="أحرف إنجليزية وأرقام وشرطات فقط"
                persistent-hint
              />
            </VCol>
            <VCol cols="12">
              <VTextarea
                v-model="form.content"
                label="المحتوى (HTML)"
                rows="12"
                dir="rtl"
              />
            </VCol>
            <VCol cols="12">
              <VSwitch v-model="form.is_published" label="منشورة" color="success" />
            </VCol>
          </VRow>
        </VCardText>
        <VCardActions class="justify-end pb-4 px-6">
          <VBtn variant="tonal" color="secondary" @click="dialog = false">
            إلغاء
          </VBtn>
          <VBtn
            color="primary"
            :loading="saveMutation.isPending.value"
            @click="saveMutation.mutate()"
          >
            حفظ
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>

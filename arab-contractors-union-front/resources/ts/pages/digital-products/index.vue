<script setup lang="ts">
import { ref, computed } from 'vue'
import { useQuery, useMutation, useQueryClient } from '@tanstack/vue-query'
import { useI18n } from 'vue-i18n'
import api from '@/plugins/axios'

definePage({ meta: { requiresAdmin: true } })

const queryClient = useQueryClient()
const { t } = useI18n()

const API_ORIGIN = (import.meta.env.VITE_API_BASE_URL || 'http://localhost:8000').replace(/\/$/, '')
const storageUrl = (path?: string | null) => (path ? `${API_ORIGIN}/storage/${path}` : '')

// ─── List products ────────────────────────────────────────────────────────────
const { data: listData, isLoading } = useQuery({
  queryKey: ['digital-products'],
  queryFn: async () => (await api.get('/api/dashboard/digital-products')).data,
})
const products = computed(() => listData.value?.data ?? [])

// ─── Create / Edit dialog ─────────────────────────────────────────────────────
const isFormOpen = ref(false)
const isEditing = ref(false)
const editingId = ref<number | null>(null)
const form = ref({ title: '', description: '', price: 0, is_active: true, is_free: false, access_days: null as number | null })
const thumbnailFile = ref<File | null>(null)
const formError = ref('')

const openCreate = () => {
  isEditing.value = false
  editingId.value = null
  form.value = { title: '', description: '', price: 0, is_active: true, is_free: false, access_days: null }
  thumbnailFile.value = null
  formError.value = ''
  isFormOpen.value = true
}

const openEdit = (p: any) => {
  isEditing.value = true
  editingId.value = p.id
  form.value = {
    title: p.title,
    description: p.description ?? '',
    price: Number(p.price),
    is_active: !!p.is_active,
    is_free: !!p.is_free,
    access_days: p.access_days ?? null,
  }
  thumbnailFile.value = null
  formError.value = ''
  isFormOpen.value = true
}

const saveMutation = useMutation({
  mutationFn: async () => {
    const fd = new FormData()
    fd.append('title', form.value.title)
    fd.append('description', form.value.description ?? '')
    fd.append('price', String(form.value.price))
    fd.append('is_active', form.value.is_active ? '1' : '0')
    fd.append('is_free', form.value.is_free ? '1' : '0')
    if (form.value.access_days)
      fd.append('access_days', String(form.value.access_days))
    if (thumbnailFile.value)
      fd.append('thumbnail', thumbnailFile.value)

    const url = isEditing.value
      ? `/api/dashboard/digital-products/${editingId.value}`
      : '/api/dashboard/digital-products'

    return (await api.post(url, fd, { headers: { 'Content-Type': 'multipart/form-data' } })).data
  },
  onSuccess: () => {
    queryClient.invalidateQueries({ queryKey: ['digital-products'] })
    isFormOpen.value = false
  },
  onError: (e: any) => {
    formError.value = e?.response?.data?.message || t('digital_products.save_failed')
  },
})

// ─── Delete ───────────────────────────────────────────────────────────────────
const deleteMutation = useMutation({
  mutationFn: async (id: number) => (await api.delete(`/api/dashboard/digital-products/${id}`)).data,
  onSuccess: () => queryClient.invalidateQueries({ queryKey: ['digital-products'] }),
})
const confirmDelete = (p: any) => {
  if (confirm(t('digital_products.confirm_delete', { title: p.title })))
    deleteMutation.mutate(p.id)
}

// ─── Files dialog (multi-file uploader) ───────────────────────────────────────
const isFilesOpen = ref(false)
const activeProductId = ref<number | null>(null)
const filesToUpload = ref<File[]>([])
const uploadError = ref('')

const { data: detailData, refetch: refetchDetail } = useQuery({
  queryKey: ['digital-product-detail', activeProductId],
  queryFn: async () => {
    if (!activeProductId.value)
      return null
    return (await api.get(`/api/dashboard/digital-products/${activeProductId.value}`)).data
  },
  enabled: computed(() => activeProductId.value !== null),
})
const productFiles = computed(() => detailData.value?.files ?? [])

const openFiles = (p: any) => {
  activeProductId.value = p.id
  filesToUpload.value = []
  uploadError.value = ''
  isFilesOpen.value = true
  refetchDetail()
}

const onFilesPicked = (e: Event) => {
  const input = e.target as HTMLInputElement
  filesToUpload.value = input.files ? Array.from(input.files) : []
}

const uploadMutation = useMutation({
  mutationFn: async () => {
    const fd = new FormData()
    filesToUpload.value.forEach(f => fd.append('files[]', f))
    return (await api.post(`/api/dashboard/digital-products/${activeProductId.value}/files`, fd, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })).data
  },
  onSuccess: () => {
    filesToUpload.value = []
    refetchDetail()
    queryClient.invalidateQueries({ queryKey: ['digital-products'] })
  },
  onError: (e: any) => {
    uploadError.value = e?.response?.data?.message || t('digital_products.upload_failed')
  },
})

const deleteFileMutation = useMutation({
  mutationFn: async (fileId: number) => (await api.delete(`/api/dashboard/digital-product-files/${fileId}`)).data,
  onSuccess: () => {
    refetchDetail()
    queryClient.invalidateQueries({ queryKey: ['digital-products'] })
  },
})

// ─── Sales dialog ─────────────────────────────────────────────────────────────
const isSalesOpen = ref(false)
const salesProductId = ref<number | null>(null)

const { data: salesData, isLoading: isSalesLoading, refetch: refetchSales } = useQuery({
  queryKey: ['digital-product-sales', salesProductId],
  queryFn: async () => {
    if (!salesProductId.value)
      return null
    return (await api.get(`/api/dashboard/digital-products/${salesProductId.value}/sales`)).data
  },
  enabled: computed(() => salesProductId.value !== null),
})
const sales = computed(() => salesData.value?.data ?? [])

const openSales = (p: any) => {
  salesProductId.value = p.id
  isSalesOpen.value = true
  refetchSales()
}

const formatBytes = (bytes: number) => {
  if (!bytes)
    return '0 B'
  const k = 1024
  const sizes = ['B', 'KB', 'MB', 'GB']
  const i = Math.floor(Math.log(bytes) / Math.log(k))
  return `${(bytes / k ** i).toFixed(1)} ${sizes[i]}`
}
</script>

<template>
  <div>
    <div class="d-flex justify-space-between align-center mb-6">
      <div>
        <h1 class="text-h4 font-weight-bold">{{ $t('digital_products.title') }}</h1>
        <p class="text-body-2 text-medium-emphasis mb-0">{{ $t('digital_products.subtitle') }}</p>
      </div>
      <VBtn color="primary" prepend-icon="tabler-plus" @click="openCreate">
        {{ $t('digital_products.new_product') }}
      </VBtn>
    </div>

    <VRow v-if="isLoading">
      <VCol v-for="i in 3" :key="i" cols="12" md="4">
        <VSkeletonLoader type="image, article" />
      </VCol>
    </VRow>

    <VCard v-else-if="products.length === 0" class="text-center py-12">
      <VIcon icon="tabler-shopping-bag" size="64" color="disabled" class="mb-3" />
      <p class="text-h6 text-medium-emphasis">{{ $t('digital_products.no_products') }}</p>
    </VCard>

    <VRow v-else>
      <VCol v-for="p in products" :key="p.id" cols="12" md="4">
        <VCard>
          <VImg
            :src="storageUrl(p.thumbnail_path) || undefined"
            height="160"
            cover
            class="bg-grey-lighten-3"
          >
            <template #placeholder>
              <div class="d-flex align-center justify-center fill-height">
                <VIcon icon="tabler-photo" size="48" color="disabled" />
              </div>
            </template>
          </VImg>
          <VCardItem>
            <VCardTitle class="d-flex align-center justify-space-between">
              <span class="text-truncate">{{ p.title }}</span>
              <VChip :color="p.is_active ? 'success' : 'secondary'" size="small" label>
                {{ p.is_active ? $t('digital_products.active') : $t('digital_products.hidden') }}
              </VChip>
            </VCardTitle>
            <VCardSubtitle>
              <span v-if="p.is_free" class="text-success font-weight-bold">{{ $t('digital_products.free') }}</span>
              <span v-else>${{ Number(p.price).toFixed(2) }}</span>
            </VCardSubtitle>
          </VCardItem>
          <VCardText class="d-flex gap-3 text-body-2 text-medium-emphasis">
            <span><VIcon icon="tabler-files" size="16" /> {{ p.files_count ?? 0 }} {{ $t('digital_products.files') }}</span>
            <span><VIcon icon="tabler-shopping-cart" size="16" /> {{ p.purchases_count ?? 0 }} {{ $t('digital_products.sales') }}</span>
          </VCardText>
          <VDivider />
          <VCardActions>
            <VBtn size="small" prepend-icon="tabler-files" @click="openFiles(p)">{{ $t('digital_products.btn_files') }}</VBtn>
            <VBtn size="small" prepend-icon="tabler-chart-bar" @click="openSales(p)">{{ $t('digital_products.btn_sales') }}</VBtn>
            <VSpacer />
            <IconBtn @click="openEdit(p)"><VIcon icon="tabler-edit" /></IconBtn>
            <IconBtn color="error" @click="confirmDelete(p)"><VIcon icon="tabler-trash" /></IconBtn>
          </VCardActions>
        </VCard>
      </VCol>
    </VRow>

    <!-- Create / Edit dialog -->
    <VDialog v-model="isFormOpen" max-width="560" persistent>
      <VCard>
        <VCardTitle>{{ isEditing ? $t('digital_products.edit_product') : $t('digital_products.new_product') }}</VCardTitle>
        <VCardText>
          <VAlert v-if="formError" type="error" variant="tonal" class="mb-4">{{ formError }}</VAlert>
          <VTextField v-model="form.title" :label="$t('digital_products.form_title')" class="mb-3" />
          <VTextarea v-model="form.description" :label="$t('digital_products.form_description')" rows="3" class="mb-3" />
          <VTextField v-model.number="form.price" :label="$t('digital_products.form_price')" type="number" min="0" step="0.01" :disabled="form.is_free" class="mb-3" />
          <VTextField
            v-model.number="form.access_days"
            :label="$t('digital_products.form_access_days')"
            :hint="$t('digital_products.form_access_days_hint')"
            persistent-hint
            type="number"
            min="1"
            class="mb-3"
          />
          <VFileInput
            :label="$t('digital_products.form_thumbnail')"
            accept="image/*"
            prepend-icon="tabler-photo"
            class="mb-3"
            @update:model-value="(f: any) => (thumbnailFile = Array.isArray(f) ? f[0] : f)"
          />
          <VSwitch v-model="form.is_free" :label="$t('digital_products.form_free')" color="success" class="mb-2" />
          <VSwitch v-model="form.is_active" :label="$t('digital_products.form_active')" color="success" />
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn variant="text" @click="isFormOpen = false">{{ $t('digital_products.btn_cancel') }}</VBtn>
          <VBtn color="primary" :loading="saveMutation.isPending.value" @click="saveMutation.mutate()">{{ $t('digital_products.btn_save') }}</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Files dialog (multi-file uploader) -->
    <VDialog v-model="isFilesOpen" max-width="640">
      <VCard>
        <VCardTitle class="d-flex align-center justify-space-between">
          {{ $t('digital_products.files_dialog_title') }}
          <IconBtn @click="isFilesOpen = false"><VIcon icon="tabler-x" /></IconBtn>
        </VCardTitle>
        <VCardText>
          <VAlert v-if="uploadError" type="error" variant="tonal" class="mb-4">{{ uploadError }}</VAlert>

          <div class="mb-4">
            <input type="file" multiple class="mb-2" @change="onFilesPicked">
            <VBtn
              color="primary"
              size="small"
              :disabled="filesToUpload.length === 0"
              :loading="uploadMutation.isPending.value"
              prepend-icon="tabler-upload"
              @click="uploadMutation.mutate()"
            >
              {{ $t('digital_products.btn_upload', { count: filesToUpload.length }) }}
            </VBtn>
          </div>

          <VList v-if="productFiles.length" lines="two" border rounded>
            <VListItem v-for="f in productFiles" :key="f.id">
              <template #prepend>
                <VIcon icon="tabler-file" />
              </template>
              <VListItemTitle>{{ f.file_name }}</VListItemTitle>
              <VListItemSubtitle>{{ formatBytes(f.file_size) }}</VListItemSubtitle>
              <template #append>
                <IconBtn color="error" @click="deleteFileMutation.mutate(f.id)">
                  <VIcon icon="tabler-trash" />
                </IconBtn>
              </template>
            </VListItem>
          </VList>
          <p v-else class="text-medium-emphasis text-center py-4">{{ $t('digital_products.no_files') }}</p>
        </VCardText>
      </VCard>
    </VDialog>

    <!-- Sales dialog -->
    <VDialog v-model="isSalesOpen" max-width="640">
      <VCard>
        <VCardTitle class="d-flex align-center justify-space-between">
          {{ $t('digital_products.sales_dialog_title') }}
          <IconBtn @click="isSalesOpen = false"><VIcon icon="tabler-x" /></IconBtn>
        </VCardTitle>
        <VCardText>
          <VProgressLinear v-if="isSalesLoading" indeterminate color="primary" />
          <VTable v-else-if="sales.length">
            <thead>
              <tr>
                <th>{{ $t('digital_products.table_buyer') }}</th>
                <th>{{ $t('digital_products.table_email') }}</th>
                <th>{{ $t('digital_products.table_status') }}</th>
                <th>{{ $t('digital_products.table_date') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="s in sales" :key="s.id">
                <td>{{ s.user?.name ?? '—' }}</td>
                <td>{{ s.user?.email ?? '—' }}</td>
                <td><VChip size="small" :color="s.status === 'completed' ? 'success' : 'warning'">{{ s.status }}</VChip></td>
                <td>{{ new Date(s.created_at).toLocaleDateString() }}</td>
              </tr>
            </tbody>
          </VTable>
          <p v-else class="text-medium-emphasis text-center py-4">{{ $t('digital_products.no_sales') }}</p>
        </VCardText>
      </VCard>
    </VDialog>
  </div>
</template>

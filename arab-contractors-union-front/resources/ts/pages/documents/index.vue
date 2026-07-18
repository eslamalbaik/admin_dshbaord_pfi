<script setup lang="ts">
import api from '@/plugins/axios'

definePage({ meta: { requiresAdmin: true,
    adminOnly: true } })

const loading = ref(false)
const uploading = ref(false)
const documents = ref<any[]>([])
const search = ref('')
const page = ref(1)
const total = ref(0)

const uploadDialog = ref(false)
const uploadFile = ref<File | null>(null)
const uploadForm = ref({ contractor_id: '', title: '', type: 'license' })

const docTypes = [
  { title: 'ترخيص', value: 'license' },
  { title: 'هوية', value: 'id' },
  { title: 'عقد', value: 'contract' },
  { title: 'شهادة', value: 'certificate' },
  { title: 'أخرى', value: 'other' },
]

const headers = [
  { title: 'العنوان', key: 'title' },
  { title: 'المقاول', key: 'contractor_name' },
  { title: 'النوع', key: 'type' },
  { title: 'الحجم', key: 'size' },
  { title: 'تاريخ الرفع', key: 'created_at' },
  { title: 'تحميل', key: 'actions', sortable: false },
]

const fetchDocuments = async () => {
  loading.value = true
  try {
    const { data } = await api.get('/api/v1/documents', {
      params: { search: search.value, page: page.value },
    })
    documents.value = data.data || data || []
    total.value = data.total || documents.value.length
  }
  catch {
    documents.value = []
  }
  finally {
    loading.value = false
  }
}

const getTypeLabel = (t: string) =>
  docTypes.find(d => d.value === t)?.title || t

const uploadDocument = async () => {
  if (!uploadFile.value) return
  uploading.value = true
  try {
    const fd = new FormData()
    fd.append('file', uploadFile.value)
    fd.append('title', uploadForm.value.title)
    fd.append('type', uploadForm.value.type)
    fd.append('contractor_id', uploadForm.value.contractor_id)
    await api.post('/api/v1/documents', fd, { headers: { 'Content-Type': 'multipart/form-data' } })
    uploadDialog.value = false
    uploadFile.value = null
    uploadForm.value = { contractor_id: '', title: '', type: 'license' }
    fetchDocuments()
  }
  catch (err) {
    console.error(err)
  }
  finally {
    uploading.value = false
  }
}

const formatSize = (bytes: number) => {
  if (!bytes) return '—'
  if (bytes < 1024) return `${bytes} B`
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`
  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`
}

watchEffect(() => fetchDocuments())
</script>

<template>
  <div>
    <div class="d-flex justify-space-between align-center mb-6">
      <div>
        <h1 class="text-h4 font-weight-bold" style="font-family:Cairo,sans-serif">إدارة الوثائق</h1>
        <p class="text-body-2 text-medium-emphasis mb-0" style="font-family:Cairo,sans-serif">وثائق ومستندات المقاولين</p>
      </div>
      <VBtn color="primary" prepend-icon="tabler-upload" @click="uploadDialog = true">
        رفع وثيقة
      </VBtn>
    </div>

    <VCard>
      <VCardText>
        <VTextField
          v-model="search"
          placeholder="بحث في الوثائق..."
          prepend-inner-icon="tabler-search"
          density="compact"
          style="max-width:300px"
          @update:model-value="page = 1"
        />
      </VCardText>

      <VDataTableServer
        :headers="headers"
        :items="documents"
        :items-length="total"
        :loading="loading"
        v-model:page="page"
      >
        <template #item.title="{ item }">
          <div class="d-flex align-center gap-2">
            <VIcon icon="tabler-file-description" color="primary" size="20" />
            <span class="font-weight-medium" style="font-family:Cairo,sans-serif">{{ item.title }}</span>
          </div>
        </template>

        <template #item.contractor_name="{ item }">
          <span style="font-family:Cairo,sans-serif">{{ item.contractor_name || item.contractor?.name || '—' }}</span>
        </template>

        <template #item.type="{ item }">
          <VChip color="secondary" size="small" label variant="tonal" style="font-family:Cairo,sans-serif">
            {{ getTypeLabel(item.type) }}
          </VChip>
        </template>

        <template #item.size="{ item }">
          {{ formatSize(item.size) }}
        </template>

        <template #item.created_at="{ item }">
          {{ item.created_at ? new Date(item.created_at).toLocaleDateString('ar-PS') : '—' }}
        </template>

        <template #item.actions="{ item }">
          <VBtn
            v-if="item.url"
            icon
            size="small"
            variant="text"
            color="primary"
            :href="item.url"
            target="_blank"
          >
            <VIcon icon="tabler-download" />
          </VBtn>
        </template>

        <template #no-data>
          <div class="text-center pa-6 text-medium-emphasis" style="font-family:Cairo,sans-serif">لا توجد وثائق</div>
        </template>
      </VDataTableServer>
    </VCard>

    <!-- Upload Dialog -->
    <VDialog v-model="uploadDialog" max-width="480">
      <VCard>
        <VCardTitle style="font-family:Cairo,sans-serif">رفع وثيقة جديدة</VCardTitle>
        <VCardText>
          <VRow>
            <VCol cols="12">
              <VTextField v-model="uploadForm.contractor_id" label="رقم هوية المقاول (ID)" />
            </VCol>
            <VCol cols="12">
              <VTextField v-model="uploadForm.title" label="عنوان الوثيقة" />
            </VCol>
            <VCol cols="12">
              <VSelect
                v-model="uploadForm.type"
                :items="docTypes"
                item-title="title"
                item-value="value"
                label="نوع الوثيقة"
              />
            </VCol>
            <VCol cols="12">
              <VFileInput
                v-model="uploadFile"
                label="اختر الملف"
                accept=".pdf,.jpg,.jpeg,.png,.doc,.docx"
                prepend-icon="tabler-paperclip"
              />
            </VCol>
          </VRow>
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn variant="tonal" @click="uploadDialog = false">إلغاء</VBtn>
          <VBtn color="primary" :loading="uploading" :disabled="!uploadFile" @click="uploadDocument">رفع</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>

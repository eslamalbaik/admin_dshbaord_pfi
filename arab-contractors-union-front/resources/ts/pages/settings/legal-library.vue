<script setup lang="ts">
import { ref, computed } from 'vue'
import { useQuery, useMutation, useQueryClient } from '@tanstack/vue-query'
import api from '@/plugins/axios'

definePage({ meta: { requiresAdmin: true } })

const queryClient = useQueryClient()

// ─── State ────────────────────────────────────────────────────────────────
const filterCategory = ref<string>('')
const search = ref('')
const page = ref(1)

const categories = [
  { key: 'legislation', label: 'تشريعات',         color: 'primary',  icon: 'tabler-scale' },
  { key: 'mou',         label: 'مذكرات تفاهم',     color: 'success',  icon: 'tabler-handshake' },
  { key: 'other',       label: 'أخرى',             color: 'secondary', icon: 'tabler-files' },
]

const catMap = Object.fromEntries(categories.map(c => [c.key, c]))

// ─── Fetch list ───────────────────────────────────────────────────────────
const { data, isLoading } = useQuery({
  queryKey: ['legal-files', filterCategory, search, page],
  queryFn: async () => {
    const params: Record<string, any> = { page: page.value }
    if (filterCategory.value) params.category = filterCategory.value
    if (search.value)         params.search   = search.value
    return (await api.get('/api/v1/dashboard/legal-files', { params })).data
  },
  keepPreviousData: true,
})

const files = computed(() => data.value?.items ?? [])
const meta  = computed(() => data.value?.meta  ?? null)

// ─── Form ─────────────────────────────────────────────────────────────────
const emptyForm = () => ({
  title: '', title_en: '',
  description: '', description_en: '',
  category: 'legislation',
  sort: 0, is_active: true,
  file: null as File | null,
})

const isFormOpen = ref(false)
const isEditing  = ref(false)
const editingId  = ref<number | null>(null)
const form       = ref<any>(emptyForm())
const formError  = ref('')
const tab        = ref('ar')
const fileInput  = ref<HTMLInputElement | null>(null)

const openCreate = () => {
  isEditing.value = false; editingId.value = null
  form.value = emptyForm()
  form.value.sort = files.value.length
  formError.value = ''; tab.value = 'ar'; isFormOpen.value = true
}
const openEdit = (f: any) => {
  isEditing.value = true; editingId.value = f.id
  form.value = { ...f, file: null }
  formError.value = ''; tab.value = 'ar'; isFormOpen.value = true
}

function onFileChange(e: Event) {
  const inp = e.target as HTMLInputElement
  form.value.file = inp.files?.[0] ?? null
}

// ─── Save (multipart/form-data) ───────────────────────────────────────────
const saveMutation = useMutation({
  mutationFn: async () => {
    const fd = new FormData()
    fd.append('title',     form.value.title)
    fd.append('category',  form.value.category)
    fd.append('sort',      String(form.value.sort ?? 0))
    fd.append('is_active', form.value.is_active ? '1' : '0')
    if (form.value.title_en)       fd.append('title_en',       form.value.title_en)
    if (form.value.description)    fd.append('description',    form.value.description)
    if (form.value.description_en) fd.append('description_en', form.value.description_en)
    if (form.value.file)           fd.append('file', form.value.file)

    if (isEditing.value) {
      fd.append('_method', 'PUT')
      return (await api.post(`/api/v1/dashboard/legal-files/${editingId.value}`, fd, {
        headers: { 'Content-Type': 'multipart/form-data' },
      })).data
    }
    return (await api.post('/api/v1/dashboard/legal-files', fd, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })).data
  },
  onSuccess: () => {
    queryClient.invalidateQueries({ queryKey: ['legal-files'] })
    isFormOpen.value = false
  },
  onError: (e: any) => {
    formError.value = e?.response?.data?.message || 'فشل حفظ الملف.'
  },
})

// ─── Delete ───────────────────────────────────────────────────────────────
const deleteMutation = useMutation({
  mutationFn: async (id: number) => (await api.delete(`/api/v1/dashboard/legal-files/${id}`)).data,
  onSuccess: () => queryClient.invalidateQueries({ queryKey: ['legal-files'] }),
})
const confirmDelete = (f: any) => {
  if (confirm(`هل تريد حذف "${f.title}"؟`)) deleteMutation.mutate(f.id)
}

// ─── Helpers ──────────────────────────────────────────────────────────────
function mimeIcon(mime: string) {
  if (!mime) return 'tabler-file'
  if (mime.includes('pdf'))   return 'tabler-file-type-pdf'
  if (mime.includes('word'))  return 'tabler-file-type-doc'
  if (mime.includes('sheet')) return 'tabler-file-type-xls'
  return 'tabler-file-text'
}

function openFile(url: string) {
  window.open(url, '_blank')
}
</script>

<template>
  <div>
    <!-- ─── Header ─────────────────────────────────────────────── -->
    <div class="d-flex justify-space-between align-center mb-6 flex-wrap gap-4">
      <div>
        <h1 class="text-h4 font-weight-bold">المكتبة القانونية</h1>
        <p class="text-body-2 text-medium-emphasis mb-0">
          إدارة الملفات والوثائق القانونية (تشريعات، مذكرات تفاهم، أخرى)
        </p>
      </div>
      <VBtn color="primary" prepend-icon="tabler-upload" @click="openCreate">
        رفع ملف جديد
      </VBtn>
    </div>

    <!-- ─── Filter Bar ────────────────────────────────────────── -->
    <VCard class="mb-5" variant="outlined">
      <VCardText class="d-flex flex-wrap gap-3 align-center py-3">
        <!-- Category chips -->
        <VChip
          :color="!filterCategory ? 'primary' : 'default'"
          :variant="!filterCategory ? 'elevated' : 'tonal'"
          class="cursor-pointer"
          @click="filterCategory = ''; page = 1"
        >
          الكل
        </VChip>
        <VChip
          v-for="cat in categories"
          :key="cat.key"
          :color="filterCategory === cat.key ? cat.color : 'default'"
          :variant="filterCategory === cat.key ? 'elevated' : 'tonal'"
          :prepend-icon="cat.icon"
          class="cursor-pointer"
          @click="filterCategory = cat.key; page = 1"
        >
          {{ cat.label }}
        </VChip>

        <VSpacer />

        <VTextField
          v-model="search"
          placeholder="بحث بالعنوان..."
          prepend-inner-icon="tabler-search"
          density="compact"
          style="max-width: 260px"
          hide-details
          clearable
          @update:modelValue="page = 1"
        />
      </VCardText>
    </VCard>

    <!-- ─── Loading ──────────────────────────────────────────── -->
    <VProgressLinear v-if="isLoading" indeterminate color="primary" class="mb-4" />

    <!-- ─── Empty ─────────────────────────────────────────────── -->
    <VCard v-else-if="files.length === 0" class="text-center py-14" variant="flat">
      <VIcon icon="tabler-library" size="72" color="disabled" class="mb-3" />
      <p class="text-h6 text-medium-emphasis">لا توجد ملفات في هذا التصنيف</p>
      <VBtn class="mt-4" color="primary" prepend-icon="tabler-upload" @click="openCreate">
        ارفع أول ملف
      </VBtn>
    </VCard>

    <!-- ─── Files Grid ────────────────────────────────────────── -->
    <div v-else>
      <VRow>
        <VCol
          v-for="f in files"
          :key="f.id"
          cols="12"
          sm="6"
          lg="4"
        >
          <VCard height="100%" class="d-flex flex-column" variant="outlined">
            <VCardText class="flex-grow-1">
              <!-- Category badge + file type icon -->
              <div class="d-flex align-center justify-space-between mb-3">
                <VChip
                  :color="catMap[f.category]?.color ?? 'secondary'"
                  :prepend-icon="catMap[f.category]?.icon ?? 'tabler-files'"
                  size="small"
                  variant="tonal"
                >
                  {{ f.category_label }}
                </VChip>
                <VChip size="x-small" :color="f.is_active ? 'success' : 'default'" variant="tonal">
                  {{ f.is_active ? 'نشط' : 'مخفي' }}
                </VChip>
              </div>

              <!-- File icon + title -->
              <div class="d-flex gap-3 align-start mb-2">
                <div class="file-ico" :class="`cat-${f.category}`">
                  <VIcon :icon="mimeIcon(f.mime_type)" size="28" />
                </div>
                <div class="flex-grow-1 min-w-0">
                  <p class="text-subtitle-2 font-weight-bold mb-0 text-truncate" :title="f.title">
                    {{ f.title }}
                  </p>
                  <p v-if="f.title_en" class="text-caption text-medium-emphasis text-truncate" dir="ltr">
                    {{ f.title_en }}
                  </p>
                </div>
              </div>

              <!-- Description -->
              <p v-if="f.description" class="text-body-2 text-medium-emphasis mb-2" style="font-size:.8rem;line-height:1.5">
                {{ f.description }}
              </p>

              <!-- Meta -->
              <div class="d-flex gap-3 flex-wrap mt-2">
                <span class="text-caption text-medium-emphasis">
                  <VIcon icon="tabler-file-info" size="13" class="me-1" />
                  {{ f.formatted_size }}
                </span>
                <span class="text-caption text-medium-emphasis">
                  <VIcon icon="tabler-sort-ascending" size="13" class="me-1" />
                  ترتيب {{ f.sort }}
                </span>
              </div>
            </VCardText>

            <VDivider />
            <VCardActions>
              <VBtn
                size="small"
                prepend-icon="tabler-external-link"
                variant="text"
                @click="openFile(f.url)"
              >
                فتح
              </VBtn>
              <VBtn
                size="small"
                prepend-icon="tabler-edit"
                variant="text"
                @click="openEdit(f)"
              >
                تعديل
              </VBtn>
              <VSpacer />
              <VBtn
                size="small"
                color="error"
                variant="text"
                prepend-icon="tabler-trash"
                :loading="deleteMutation.isPending.value"
                @click="confirmDelete(f)"
              />
            </VCardActions>
          </VCard>
        </VCol>
      </VRow>

      <!-- Pagination -->
      <div v-if="meta && meta.last_page > 1" class="d-flex justify-center mt-6">
        <VPagination
          v-model="page"
          :length="meta.last_page"
          :total-visible="6"
        />
      </div>
    </div>

    <!-- ─── Upload / Edit Dialog ──────────────────────────────── -->
    <VDialog v-model="isFormOpen" max-width="700" persistent scrollable>
      <VCard>
        <VCardTitle class="pt-4 pb-0 d-flex align-center gap-2">
          <VIcon :icon="isEditing ? 'tabler-edit' : 'tabler-upload'" />
          <span>{{ isEditing ? 'تعديل الملف' : 'رفع ملف جديد' }}</span>
        </VCardTitle>

        <VCardText class="pt-4">
          <VAlert v-if="formError" type="error" variant="tonal" class="mb-4">
            {{ formError }}
          </VAlert>

          <!-- Category + Sort + Active -->
          <VRow class="mb-2">
            <VCol cols="12" sm="5">
              <VSelect
                v-model="form.category"
                label="التصنيف *"
                :items="categories.map(c => ({ title: c.label, value: c.key }))"
              />
            </VCol>
            <VCol cols="6" sm="3">
              <VTextField
                v-model.number="form.sort"
                label="الترتيب"
                type="number"
                min="0"
              />
            </VCol>
            <VCol cols="6" sm="4" class="d-flex align-center">
              <VSwitch v-model="form.is_active" label="نشط" color="success" />
            </VCol>
          </VRow>

          <!-- File picker -->
          <VCard variant="tonal" color="secondary" class="pa-3 mb-4 rounded-lg">
            <p class="text-caption font-weight-bold mb-2">
              {{ isEditing ? 'استبدال الملف (اختياري)' : 'الملف *' }}
            </p>
            <input
              ref="fileInput"
              type="file"
              accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx"
              class="d-none"
              @change="onFileChange"
            />
            <div class="d-flex align-center gap-3">
              <VBtn
                size="small"
                prepend-icon="tabler-folder-open"
                variant="tonal"
                color="primary"
                @click="fileInput?.click()"
              >
                اختر ملفاً
              </VBtn>
              <span class="text-body-2 text-medium-emphasis">
                {{ form.file ? form.file.name : (isEditing ? 'الملف الحالي محفوظ' : 'لم يُختر ملف') }}
              </span>
            </div>
            <p class="text-caption text-medium-emphasis mt-2 mb-0">
              مسموح: PDF, DOC, DOCX, XLS, XLSX, PPT, PPTX — الحد الأقصى: 50 MB
            </p>
          </VCard>

          <!-- Bilingual Tabs -->
          <VTabs v-model="tab" class="mb-4">
            <VTab value="ar">🇵🇸 عربي</VTab>
            <VTab value="en">🇬🇧 English</VTab>
          </VTabs>

          <VWindow v-model="tab">
            <VWindowItem value="ar">
              <VTextField
                v-model="form.title"
                label="عنوان الملف (عربي) *"
                class="mb-3"
                dir="rtl"
              />
              <VTextarea
                v-model="form.description"
                label="وصف مختصر (عربي)"
                rows="3"
                dir="rtl"
              />
            </VWindowItem>
            <VWindowItem value="en">
              <VTextField
                v-model="form.title_en"
                label="Title (English)"
                class="mb-3"
                dir="ltr"
              />
              <VTextarea
                v-model="form.description_en"
                label="Short Description (English)"
                rows="3"
                dir="ltr"
              />
            </VWindowItem>
          </VWindow>
        </VCardText>

        <VCardActions>
          <VSpacer />
          <VBtn variant="text" @click="isFormOpen = false">إلغاء</VBtn>
          <VBtn
            color="primary"
            prepend-icon="tabler-device-floppy"
            :loading="saveMutation.isPending.value"
            :disabled="!form.title || !form.category || (!isEditing && !form.file)"
            @click="saveMutation.mutate()"
          >
            حفظ
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>

<style scoped>
.file-ico {
  width: 52px;
  height: 52px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}
.cat-legislation { background: #e8eaf6; color: #1a237e; }
.cat-mou         { background: #e8f5e9; color: #2e7d32; }
.cat-other       { background: #f3f4f6; color: #6b7280; }
</style>

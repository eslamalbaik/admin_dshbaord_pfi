<script setup lang="ts">
import { ref, computed, watch, toRaw } from 'vue'
import { useInfiniteQuery, useQueryClient } from '@tanstack/vue-query'
import api from '@/plugins/axios'

definePage({ meta: { requiresAdmin: true } })

const queryClient = useQueryClient()

// ─── Search ───────────────────────────────────────────────────────────────────
const searchQuery    = ref('')
const searchDebounced = ref('')
let debounceTimer: any = null
watch(searchQuery, val => {
  clearTimeout(debounceTimer)
  debounceTimer = setTimeout(() => { searchDebounced.value = val || '' }, 400)
})

// ─── Fetch questions ──────────────────────────────────────────────────────────
const { data, fetchNextPage, hasNextPage, isFetchingNextPage, isLoading } =
  useInfiniteQuery({
    queryKey: ['questions', searchDebounced],
    queryFn: ({ pageParam }: { pageParam?: string }) =>
      api.get('/api/v1/tenant/questions', {
        params: { cursor: pageParam || undefined, search: searchDebounced.value || undefined },
      }).then(r => r.data),
    getNextPageParam: (last: any) => last.next_cursor || undefined,
    initialPageParam: '',
  })

const allQuestions = computed(() =>
  data.value?.pages.flatMap((p: any) => p.data || []) ?? []
)

// ─── Form state ───────────────────────────────────────────────────────────────
type OptionForm = {
  option_text : string
  is_correct  : boolean
  imageFile   : File | null
  image_path  : string | null
}

const blankOption = (correct = false): OptionForm => ({
  option_text: '',
  is_correct : correct,
  imageFile  : null,
  image_path : null,
})

const isDialogOpen   = ref(false)
const isSubmitting   = ref(false)
const editingQuestion = ref<any>(null)

const qText   = ref('')
const options = ref<OptionForm[]>([blankOption(true), blankOption()])

const openCreate = () => {
  editingQuestion.value = null
  qText.value   = ''
  options.value = [blankOption(true), blankOption()]
  isDialogOpen.value = true
}

const openEdit = (q: any) => {
  editingQuestion.value = q
  qText.value   = q.question_text
  options.value = q.options.map((o: any) => ({
    option_text: o.option_text,
    is_correct : !!o.is_correct,
    imageFile  : null,
    image_path : o.image_path || null,
  }))
  isDialogOpen.value = true
}

const addOption    = () => options.value.push(blankOption())
const removeOption = (i: number) => options.value.splice(i, 1)

const selectCorrect = (i: number) =>
  options.value.forEach((o, idx) => { o.is_correct = idx === i })

const onFileChange = (i: number, event: Event) => {
  const f = (event.target as HTMLInputElement).files?.[0] ?? null
  options.value[i].imageFile  = f ?? null
  if (f) options.value[i].image_path = null
}

const clearImage = (i: number) => {
  options.value[i].imageFile  = null
  options.value[i].image_path = null
}

const previewUrl = (i: number): string | null => {
  const o = options.value[i]
  if (o.imageFile) return URL.createObjectURL(o.imageFile)
  if (o.image_path) return `/storage/${o.image_path}`
  return null
}

// ─── Save ─────────────────────────────────────────────────────────────────────
const save = async () => {
  if (!qText.value.trim()) { showAlert('خطأ', 'نص السؤال مطلوب.'); return }
  if (options.value.filter(o => o.is_correct).length !== 1) {
    showAlert('خطأ', 'يرجى تحديد إجابة صحيحة واحدة فقط.')
    return
  }

  isSubmitting.value = true
  try {
    const fd = new FormData()
    fd.append('question_text', qText.value)
    options.value.forEach((o, i) => {
      fd.append(`options[${i}][option_text]`, o.option_text)
      fd.append(`options[${i}][is_correct]`,  o.is_correct ? '1' : '0')
      if (o.imageFile) fd.append(`option_images[${i}]`, toRaw(o.imageFile))
    })

    if (editingQuestion.value) {
      fd.append('_method', 'PUT')
      await api.post(`/api/v1/tenant/questions/${editingQuestion.value.id}`, fd)
    } else {
      await api.post('/api/v1/tenant/questions', fd)
    }

    isDialogOpen.value = false
    queryClient.invalidateQueries({ queryKey: ['questions'] })
  } catch (e: any) {
    showAlert('خطأ', e?.response?.data?.message || 'فشل حفظ السؤال.')
  } finally {
    isSubmitting.value = false
  }
}

// ─── Delete ───────────────────────────────────────────────────────────────────
const deleteQuestion = (id: number) =>
  showConfirm('حذف السؤال', 'هل أنت متأكد من حذف هذا السؤال؟', async () => {
    await api.delete(`/api/v1/tenant/questions/${id}`)
    queryClient.invalidateQueries({ queryKey: ['questions'] })
  })

// ─── Alert / Confirm helpers ──────────────────────────────────────────────────
const alertOpen  = ref(false); const alertTitle  = ref(''); const alertMsg = ref('')
const confirmOpen = ref(false); const confirmTitle = ref(''); const confirmMsg = ref('')
const confirmAction = ref<(() => Promise<void>) | null>(null)
const confirmLoading = ref(false)

const showAlert = (t: string, m: string) => { alertTitle.value = t; alertMsg.value = m; alertOpen.value = true }
const showConfirm = (t: string, m: string, fn: () => Promise<void>) => {
  confirmTitle.value = t; confirmMsg.value = m; confirmAction.value = fn; confirmOpen.value = true
}
const runConfirm = async () => {
  if (!confirmAction.value) return
  confirmLoading.value = true
  try { await confirmAction.value() } catch { showAlert('خطأ', 'فشلت العملية.') }
  finally { confirmLoading.value = false; confirmOpen.value = false }
}
</script>

<template>
  <VCard>
    <!-- Header -->
    <VCardItem>
      <VCardTitle class="d-flex align-center gap-2">
        <VIcon icon="tabler-database" />
        {{ $t('question.bank.title', 'Question Bank') }}
      </VCardTitle>
      <VCardSubtitle>{{ $t('question.bank.subtitle', 'Manage standalone questions and answers') }}</VCardSubtitle>
      <template #append>
        <div class="d-flex gap-2">
          <VBtn color="primary" prepend-icon="tabler-plus" size="small" @click="openCreate">
            {{ $t('question.bank.add_question', 'Add Question') }}
          </VBtn>
          <VBtn color="secondary" prepend-icon="tabler-clipboard-check" size="small" to="/quizzes/exams">
            {{ $t('common.actions.manage_quizzes', 'Manage Quizzes') }}
          </VBtn>
        </div>
      </template>
    </VCardItem>

    <VCardText>
      <!-- Search -->
      <VTextField
        v-model="searchQuery"
        :placeholder="$t('question.bank.search_placeholder', 'Search questions...')"
        prepend-inner-icon="tabler-search"
        variant="outlined"
        clearable
        class="mb-6"
      />

      <div v-if="isLoading" class="d-flex justify-center pa-8">
        <VProgressCircular indeterminate color="primary" />
      </div>

      <div v-else>
        <VTable class="border rounded">
          <thead>
            <tr>
              <th style="min-width:350px">{{ $t('question.bank.question', 'Question') }}</th>
              <th>{{ $t('question.bank.options', 'Options') }}</th>
              <th class="text-center">{{ $t('common.actions.title', 'Actions') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="q in allQuestions" :key="q.id">
              <td class="py-3">
                <span class="font-weight-medium d-block text-body-1">{{ q.question_text }}</span>
              </td>
              <td>
                <div class="d-flex flex-column gap-1 my-2">
                  <div
                    v-for="o in q.options" :key="o.id"
                    class="text-caption d-flex align-center gap-1"
                    :class="o.is_correct ? 'text-success font-weight-bold' : 'text-medium-emphasis'"
                  >
                    <VIcon :icon="o.is_correct ? 'tabler-circle-check-filled' : 'tabler-circle'" size="14" />
                    <VImg v-if="o.image_path" :src="`/storage/${o.image_path}`" width="24" height="24" cover class="rounded" />
                    <span>{{ o.option_text }}</span>
                  </div>
                </div>
              </td>
              <td class="text-center">
                <div class="d-flex gap-1 justify-center">
                  <VBtn icon="tabler-edit"  variant="text" size="small" color="primary" @click="openEdit(q)" />
                  <VBtn icon="tabler-trash" variant="text" size="small" color="error"   @click="deleteQuestion(q.id)" />
                </div>
              </td>
            </tr>
            <tr v-if="allQuestions.length === 0">
              <td colspan="3" class="text-center text-medium-emphasis pa-8">
                {{ $t('question.bank.no_results', 'No questions in the bank yet.') }}
              </td>
            </tr>
          </tbody>
        </VTable>

        <div v-if="hasNextPage" class="text-center mt-6">
          <VBtn color="primary" variant="outlined" :loading="isFetchingNextPage"
            prepend-icon="tabler-arrow-down" @click="() => fetchNextPage()">
            {{ $t('common.actions.load_more', 'Load More Questions') }}
          </VBtn>
        </div>
      </div>
    </VCardText>

    <!-- ═══════════════════════════════════════════════════════════ Dialog -->
    <VDialog v-model="isDialogOpen" max-width="620" scrollable>
      <VCard>
        <VCardTitle class="pa-5 bg-primary text-white d-flex justify-space-between align-center">
          <span>{{ editingQuestion ? $t('question.bank.edit_title', 'Edit Question') : $t('question.bank.create_title', 'Create Question') }}</span>
          <VBtn icon="tabler-x" variant="text" size="small" color="white" @click="isDialogOpen = false" />
        </VCardTitle>

        <VCardText class="pa-5">
          <!-- Question text -->
          <VTextarea
            v-model="qText"
            :label="$t('question.bank.question_text', 'Question Text')"
            rows="3" variant="outlined" class="mb-5" required
          />

          <!-- Options -->
          <div class="text-subtitle-1 font-weight-bold mb-3">
            {{ $t('question.bank.options_title', 'Options (Select the correct one)') }}
          </div>

          <div
            v-for="(opt, i) in options" :key="i"
            class="mb-3 rounded border pa-3"
          >
            <!-- Row 1: radio + text + trash -->
            <div class="d-flex align-center gap-2 mb-3">
              <!-- Radio circle -->
              <div
                class="flex-shrink-0"
                style="width:22px;height:22px;border-radius:50%;border:2px solid #bbb;display:flex;align-items:center;justify-content:center;cursor:pointer;"
                :style="opt.is_correct ? 'border-color:#4CAF50;background:#4CAF50' : ''"
                @click="selectCorrect(i)"
              >
                <div v-if="opt.is_correct" style="width:9px;height:9px;border-radius:50%;background:#fff" />
              </div>

              <VTextField
                v-model="opt.option_text"
                :label="`${$t('question.bank.option','Option')} ${i + 1}`"
                variant="outlined" density="compact" hide-details class="flex-grow-1"
              />

              <VBtn
                v-if="options.length > 2"
                icon="tabler-trash" variant="text" color="error" size="small"
                @click="removeOption(i)"
              />
            </div>

            <!-- Row 2: image upload — native HTML only, no Vuetify -->
            <div style="padding-inline-start:30px; margin-top:10px;">
              <img
                v-if="previewUrl(i)"
                :src="previewUrl(i)!"
                style="display:block; width:60px; height:60px; object-fit:cover; border-radius:6px; border:1px solid #ddd; margin-bottom:6px;"
              />
              <div style="display:flex; align-items:center; gap:8px;">
                <input
                  type="file"
                  accept="image/jpg,image/jpeg,image/png,image/webp"
                  style="font-size:13px; cursor:pointer;"
                  @change="onFileChange(i, $event)"
                />
                <button
                  v-if="previewUrl(i)"
                  type="button"
                  style="font-size:12px; color:red; background:none; border:none; cursor:pointer; padding:0;"
                  @click="clearImage(i)"
                >✕ حذف</button>
              </div>
            </div>
          </div>

          <VBtn variant="text" size="small" color="primary" prepend-icon="tabler-plus" @click="addOption">
            {{ $t('question.bank.add_option', 'Add Option') }}
          </VBtn>
        </VCardText>

        <VCardActions class="pa-5 border-t bg-grey-lighten-5 justify-end">
          <VBtn variant="text" color="secondary" @click="isDialogOpen = false">
            {{ $t('common.actions.cancel', 'Cancel') }}
          </VBtn>
          <VBtn color="primary" variant="elevated" :loading="isSubmitting" @click="save">
            {{ $t('common.actions.save', 'Save Question') }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- ═══════════════════════════════════════════════════════ Confirm Dialog -->
    <VDialog v-model="confirmOpen" max-width="450" persistent>
      <VCard class="text-center pa-6">
        <VCardText class="pt-6">
          <VIcon icon="tabler-alert-triangle" size="64" color="warning" class="mb-4" />
          <h3 class="text-h5 mb-2">{{ confirmTitle }}</h3>
          <p class="text-body-1 text-medium-emphasis mb-6">{{ confirmMsg }}</p>
        </VCardText>
        <VCardActions class="justify-center gap-4">
          <VBtn variant="text" color="secondary" @click="confirmOpen = false">
            {{ $t('common.actions.cancel', 'Cancel') }}
          </VBtn>
          <VBtn color="error" variant="elevated" :loading="confirmLoading" @click="runConfirm">
            {{ $t('common.actions.confirm_delete', 'Delete') }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- ════════════════════════════════════════════════════════ Alert Dialog -->
    <VDialog v-model="alertOpen" max-width="400">
      <VCard class="text-center pa-6">
        <VCardText class="pt-6">
          <VIcon icon="tabler-alert-circle" size="64" color="error" class="mb-4" />
          <h3 class="text-h5 mb-2">{{ alertTitle }}</h3>
          <p class="text-body-1 text-medium-emphasis mb-6">{{ alertMsg }}</p>
        </VCardText>
        <VCardActions class="justify-center">
          <VBtn color="primary" variant="elevated" @click="alertOpen = false">
            {{ $t('common.actions.ok', 'OK') }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </VCard>
</template>

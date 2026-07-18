<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { storeToRefs } from 'pinia'
import { useQuery, useQueryClient, useInfiniteQuery } from '@tanstack/vue-query'
import api from '@/plugins/axios'
import { useQuizCRUDStore } from '@/stores/quizCRUDStore'

definePage({ meta: { requiresAdmin: true } })

const queryClient = useQueryClient()
const quizCRUDStore = useQuizCRUDStore()
const { activeQuiz } = storeToRefs(quizCRUDStore)

// Computed getter/setter to guarantee Pinia reactivity with VSelect
const selectedModuleId = computed({
  get: () => activeQuiz.value.course_module_id,
  set: (val: number | null) => { activeQuiz.value.course_module_id = val ? Number(val) : null }
})

// Dialog and Submitting States
const isCreateDialog = ref(false)
const isSubmitting = ref(false)
const editingQuizId = ref<number | null>(null)

const isConfirmOpen = ref(false)
const confirmTitle = ref('')
const confirmText = ref('')
const onConfirm = ref<(() => void) | null>(null)
const isConfirmLoading = ref(false)

const showConfirm = (title: string, text: string, action: () => void) => {
  confirmTitle.value = title
  confirmText.value = text
  onConfirm.value = action
  isConfirmOpen.value = true
}

const cancelConfirm = () => {
  isConfirmOpen.value = false
  onConfirm.value = null
}

const executeConfirm = async () => {
  if (onConfirm.value) {
    isConfirmLoading.value = true
    try {
      await onConfirm.value()
    } finally {
      isConfirmLoading.value = false
      isConfirmOpen.value = false
      onConfirm.value = null
    }
  }
}

const isAlertOpen = ref(false)
const alertTitle = ref('')
const alertText = ref('')

const showAlert = (title: string, text: string) => {
  alertTitle.value = title
  alertText.value = text
  isAlertOpen.value = true
}

// Question Picker States
const isPickerOpen = ref(false)
const pickerSearch = ref('')
const pickerSearchDebounced = ref('')
let pickerDebounceTimeout: any = null

// New Question inside Picker Dialog
const isNewQuestionDialogOpen = ref(false)
const isSavingNewQuestion = ref(false)
const newQuestionForm = ref({
  question_text: '',
  options: [
    { option_text: '', is_correct: true },
    { option_text: '', is_correct: false }
  ]
})

// Debounce picker search
watch(pickerSearch, (newVal) => {
  if (pickerDebounceTimeout) clearTimeout(pickerDebounceTimeout)
  pickerDebounceTimeout = setTimeout(() => {
    pickerSearchDebounced.value = newVal || ''
  }, 400)
})

// Fetch Quizzes using TanStack Query
const { data: quizzes, isLoading: isQuizzesLoading } = useQuery({
  queryKey: ['quizzes'],
  queryFn: async () => {
    const res = await api.get('/api/dashboard/quizzes')
    return res.data
  }
})

// Fetch Courses with Modules using TanStack Query
const { data: courses } = useQuery({
  queryKey: ['courses'],
  queryFn: async () => {
    const res = await api.get('/api/dashboard/courses?include=modules')
    return res.data.data || res.data
  }
})

// Fetch Standalone Question Bank for Picker Dialog
const {
  data: pickerData,
  fetchNextPage: fetchNextPickerPage,
  hasNextPage: hasNextPickerPage,
  isFetchingNextPage: isFetchingNextPickerPage
} = useInfiniteQuery({
  queryKey: ['questions', pickerSearchDebounced],
  queryFn: async ({ pageParam }) => {
    const res = await api.get('/api/v1/tenant/questions', {
      params: {
        cursor: pageParam || undefined,
        search: pickerSearchDebounced.value || undefined
      }
    })
    return res.data
  },
  getNextPageParam: (lastPage: any) => lastPage.next_cursor || undefined,
  initialPageParam: ''
})

const pickerQuestions = computed(() => {
  if (!pickerData.value) return []
  return pickerData.value.pages.flatMap((page: any) => page.data || [])
})

const moduleOptions = computed(() => {
  const opts: any[] = []
  if (Array.isArray(courses.value)) {
    courses.value.forEach(c => {
      const courseTitle = c.title || c.name || 'Unknown Course'
      c.modules?.forEach((m: any) => {
        const moduleTitle = m.title || m.name || `Module ${m.id}`
        opts.push({
          value: m.id,
          title: `${courseTitle} — ${moduleTitle}`
        })
      })
    })
  }
  return opts
})

// CRUD Methods
const openCreateDialog = () => {
  editingQuizId.value = null
  quizCRUDStore.clearActiveQuiz()
  isCreateDialog.value = true
}

const openEditDialog = (quiz: any) => {
  editingQuizId.value = quiz.id
  quizCRUDStore.setActiveQuiz(quiz)
  isCreateDialog.value = true
}

const deleteQuiz = (id: number) => {
  showConfirm(
    'Delete Quiz',
    'Are you sure you want to delete this quiz?',
    async () => {
      try {
        await api.delete(`/api/dashboard/quizzes/${id}`)
        queryClient.invalidateQueries({ queryKey: ['quizzes'] })
      } catch (error) {
        console.error(error)
        showAlert('Error', 'Failed to delete quiz.')
      }
    }
  )
}

// Picker Question Management
const toggleQuestionSelection = (question: any) => {
  if (quizCRUDStore.activeQuiz.questionIds.includes(question.id)) {
    quizCRUDStore.removeQuestionFromQuiz(question.id)
  } else {
    quizCRUDStore.addQuestionToQuiz(question)
  }
}

// Reordering selected questions
const moveQuestionUp = (index: number) => {
  if (index === 0) return
  const ids = [...quizCRUDStore.activeQuiz.questionIds]
  const temp = ids[index]
  ids[index] = ids[index - 1]
  ids[index - 1] = temp
  quizCRUDStore.reorderQuizQuestions(ids)
}

const moveQuestionDown = (index: number) => {
  const ids = [...quizCRUDStore.activeQuiz.questionIds]
  if (index === ids.length - 1) return
  const temp = ids[index]
  ids[index] = ids[index + 1]
  ids[index + 1] = temp
  quizCRUDStore.reorderQuizQuestions(ids)
}

// Option management for nested question creator dialog
const addNewQuestionOption = () => {
  newQuestionForm.value.options.push({ option_text: '', is_correct: false })
}
const removeNewQuestionOption = (idx: number) => {
  newQuestionForm.value.options.splice(idx, 1)
}
const setNewQuestionCorrectOption = (idx: number) => {
  newQuestionForm.value.options.forEach((o, oIdx) => {
    o.is_correct = (oIdx === idx)
  })
}

// Save atomic question inside picker dialog
const saveNewQuestion = async () => {
  if (!newQuestionForm.value.question_text.trim()) {
    showAlert('Validation Error', 'Question text is required.')
    return
  }
  const correctCount = newQuestionForm.value.options.filter(o => o.is_correct).length
  if (correctCount !== 1) {
    showAlert('Validation Error', 'Please select exactly one correct option.')
    return
  }

  isSavingNewQuestion.value = true
  try {
    const res = await api.post('/api/v1/tenant/questions', newQuestionForm.value)
    const newQ = res.data
    
    // Add to local active quiz store
    quizCRUDStore.addQuestionToQuiz(newQ)
    
    // Invalidate questions query key
    queryClient.invalidateQueries({ queryKey: ['questions'] })
    
    // Close nested dialog
    isNewQuestionDialogOpen.value = false
  } catch (e: any) {
    console.error(e)
    showAlert('Error', e?.response?.data?.message || 'Failed to create question.')
  } finally {
    isSavingNewQuestion.value = false
  }
}

// Save Quiz
const saveQuiz = async () => {
  const title = activeQuiz.value.title
  const passing_score = activeQuiz.value.passing_score
  const course_module_id = activeQuiz.value.course_module_id
  const questionIds = activeQuiz.value.questionIds

  if (!title || !title.trim()) {
    showAlert('Validation Error', 'Please fill in the quiz title.')
    return
  }
  if (!course_module_id) {
    showAlert('Validation Error', 'Please select a course module.')
    return
  }

  isSubmitting.value = true
  try {
    let quizId = editingQuizId.value
    
    const payload = {
      title,
      passing_score: Number(passing_score),
      course_module_id,
      questions: [] // Backward compatibility for quizzes model endpoints
    }

    if (quizId) {
      await api.put(`/api/dashboard/quizzes/${quizId}`, payload)
    } else {
      const res = await api.post('/api/dashboard/quizzes', payload)
      quizId = res.data.quiz.id
    }

    // Sync questions relation using pivot table sync endpoint
    await api.post(`/api/v1/tenant/quizzes/${quizId}/sync-questions`, {
      question_ids: questionIds
    })

    // Surgical Cache Invalidation
    queryClient.invalidateQueries({ queryKey: ['quizzes'] })
    queryClient.invalidateQueries({ queryKey: ['quiz', quizId] })

    isCreateDialog.value = false
  } catch (error: any) {
    console.error(error)
    showAlert('Error', 'Failed to save quiz. Make sure all fields are filled.')
  } finally {
    isSubmitting.value = false
  }
}
</script>

<template>
  <VCard>
    <VCardItem>
      <VCardTitle class="d-flex align-center gap-2">
        <VIcon icon="tabler-clipboard-list" />
        {{ $t('quiz.editor.title', 'Exams & Quizzes') }}
      </VCardTitle>
      <VCardSubtitle>{{ $t('quiz.editor.subtitle', 'Manage quiz assessments and question linkings') }}</VCardSubtitle>

      <template #append>
        <div class="d-flex gap-2">
          <VBtn color="primary" prepend-icon="tabler-plus" size="small" @click="openCreateDialog">
            {{ $t('common.actions.create_exam', 'Create Exam') }}
          </VBtn>
          <VBtn color="secondary" prepend-icon="tabler-database" size="small" to="/quizzes/bank">
            {{ $t('common.actions.question_bank', 'Question Bank') }}
          </VBtn>
        </div>
      </template>
    </VCardItem>

    <VCardText>
      <div v-if="isQuizzesLoading" class="d-flex justify-center pa-8">
        <VProgressCircular indeterminate color="primary" />
      </div>
      <VTable v-else class="border rounded">
        <thead>
          <tr>
            <th>{{ $t('quiz.editor.exam_title', 'Exam Title') }}</th>
            <th>{{ $t('quiz.editor.course', 'Course') }}</th>
            <th>{{ $t('quiz.editor.questions', 'Questions') }}</th>
            <th>{{ $t('quiz.editor.passing_score', 'Passing Score') }}</th>
            <th class="text-center">{{ $t('common.actions.title', 'Actions') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="quiz in (quizzes ?? [])" :key="quiz.id">
            <td class="font-weight-medium">{{ quiz.title ?? '—' }}</td>
            <td>
              <VChip size="small" color="primary" variant="tonal">
                {{ quiz.module?.course?.title ?? 'Unknown' }}
              </VChip>
            </td>
            <td>
              <VChip size="small" color="secondary" variant="outlined">
                {{ quiz.questions?.length ?? 0 }} {{ $t('quiz.editor.questions_unit', 'questions') }}
              </VChip>
            </td>
            <td class="font-weight-bold">{{ quiz.passing_score }}%</td>
            <td class="text-center">
              <div class="d-flex gap-1 justify-center">
                <VBtn icon="tabler-edit" variant="text" size="small" color="primary" @click="openEditDialog(quiz)" />
                <VBtn icon="tabler-trash" variant="text" size="small" color="error" @click="deleteQuiz(quiz.id)" />
              </div>
            </td>
          </tr>
          <tr v-if="!quizzes?.length">
            <td colspan="5" class="text-center text-medium-emphasis pa-8">
              {{ $t('quiz.editor.no_exams', 'No exams created yet.') }}
            </td>
          </tr>
        </tbody>
      </VTable>
    </VCardText>

    <!-- Create/Edit Quiz Dialog -->
    <VDialog v-model="isCreateDialog" max-width="900" scrollable>
      <VCard>
        <VCardTitle class="pa-6 bg-primary text-white d-flex justify-space-between align-center">
          <span>{{ editingQuizId ? $t('quiz.editor.edit_exam', 'Edit Quiz') : $t('quiz.editor.create_exam', 'Create New Quiz') }}</span>
          <VBtn icon="tabler-x" variant="text" size="small" color="white" @click="isCreateDialog = false" />
        </VCardTitle>

        <VCardText class="pa-6">
          <VRow>
            <VCol cols="12" md="6">
              <VTextField v-model="quizCRUDStore.activeQuiz.title" :label="$t('quiz.editor.quiz_title', 'Quiz Title')" variant="outlined" />
            </VCol>
            <VCol cols="12" md="6">
              <VTextField v-model="quizCRUDStore.activeQuiz.passing_score" :label="$t('quiz.editor.passing_score_label', 'Passing Score (%)')" type="number" variant="outlined" />
            </VCol>
            <VCol cols="12">
              <VSelect
                v-model="selectedModuleId"
                :items="moduleOptions"
                item-title="title"
                item-value="value"
                :return-object="false"
                :label="$t('quiz.editor.select_module', 'Select Course Module')"
                variant="outlined"
              />
            </VCol>
          </VRow>

          <VDivider class="my-6" />
          
          <div class="d-flex justify-space-between align-center mb-4">
            <h3 class="text-h6 d-flex align-center gap-2">
              <VIcon icon="tabler-list" size="20" />
              {{ $t('quiz.editor.selected_questions', 'Linked Questions') }}
              <VChip size="x-small" color="primary" variant="flat" class="ms-1">
                {{ quizCRUDStore.activeQuiz.questionIds.length }}
              </VChip>
            </h3>
            <VBtn color="secondary" size="small" prepend-icon="tabler-plus" @click="isPickerOpen = true">
              {{ $t('quiz.editor.link_questions', 'Link Questions from Bank') }}
            </VBtn>
          </div>

          <!-- Selected Quiz Questions List (Flattened State) -->
          <div v-if="quizCRUDStore.activeQuiz.questionIds.length === 0" class="border rounded pa-8 text-center text-medium-emphasis bg-light">
            <VIcon icon="tabler-clipboard-off" size="36" class="mb-2 text-disabled" />
            <div>{{ $t('quiz.editor.no_selected_questions', 'No questions linked to this quiz yet. Click the button above to select questions.') }}</div>
          </div>

          <div v-else class="d-flex flex-column gap-4">
            <div 
              v-for="(qId, idx) in quizCRUDStore.activeQuiz.questionIds" 
              :key="qId" 
              class="border rounded-lg pa-4 bg-grey-lighten-4 d-flex gap-4 align-start"
            >
              <!-- Order actions -->
              <div class="d-flex flex-column align-center">
                <VBtn icon="tabler-chevron-up" variant="text" size="x-small" :disabled="idx === 0" @click="moveQuestionUp(idx)" />
                <span class="font-weight-bold text-body-2">{{ idx + 1 }}</span>
                <VBtn icon="tabler-chevron-down" variant="text" size="x-small" :disabled="idx === quizCRUDStore.activeQuiz.questionIds.length - 1" @click="moveQuestionDown(idx)" />
              </div>

              <!-- Question details -->
              <div class="flex-grow-1">
                <div class="font-weight-bold mb-2 text-body-1">
                  {{ quizCRUDStore.questions[qId]?.question_text }}
                </div>
                <div class="ms-2">
                  <div 
                    v-for="o in quizCRUDStore.questions[qId]?.options" 
                    :key="o.id" 
                    class="text-caption d-flex align-center gap-1 my-1"
                    :class="o.is_correct ? 'text-success font-weight-bold' : 'text-medium-emphasis'"
                  >
                    <VIcon :icon="o.is_correct ? 'tabler-circle-check-filled' : 'tabler-circle'" size="14" />
                    <span>{{ o.option_text }}</span>
                  </div>
                </div>
              </div>

              <!-- Remove button -->
              <VBtn icon="tabler-x" color="error" variant="text" size="small" @click="quizCRUDStore.removeQuestionFromQuiz(qId)" />
            </div>
          </div>

        </VCardText>

        <VCardActions class="pa-6 border-t bg-grey-lighten-5 justify-end">
          <VBtn variant="text" color="secondary" @click="isCreateDialog = false">{{ $t('common.actions.cancel', 'Cancel') }}</VBtn>
          <VBtn color="primary" variant="elevated" :loading="isSubmitting" @click="saveQuiz">
            {{ $t('common.actions.save', 'Save Quiz') }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Question Picker Dialog -->
    <VDialog v-model="isPickerOpen" max-width="700" scrollable>
      <VCard>
        <VCardTitle class="pa-6 bg-secondary text-white d-flex justify-space-between align-center">
          <span>{{ $t('question.bank.picker_title', 'Select Questions') }}</span>
          <VBtn icon="tabler-x" variant="text" size="small" color="white" @click="isPickerOpen = false" />
        </VCardTitle>

        <VCardText class="pa-6">
          <div class="d-flex gap-3 mb-4">
            <VTextField
              v-model="pickerSearch"
              :placeholder="$t('question.bank.search_placeholder', 'Search bank...')"
              prepend-inner-icon="tabler-search"
              variant="outlined"
              density="compact"
              hide-details
              class="flex-grow-1"
            />
            <VBtn color="primary" prepend-icon="tabler-plus" @click="isNewQuestionDialogOpen = true">
              {{ $t('question.bank.new_question', 'New Question') }}
            </VBtn>
          </div>

          <!-- Virtual Scroll container -->
          <div class="border rounded">
            <div v-if="pickerQuestions.length === 0" class="text-center text-medium-emphasis pa-8 bg-grey-lighten-4">
              {{ $t('question.bank.no_results', 'No questions found.') }}
            </div>

            <VVirtualScroll
              v-else
              :items="pickerQuestions"
              height="350"
            >
              <template #default="{ item }">
                <div class="d-flex align-center justify-space-between border-b py-3 px-4">
                  <div class="pe-4 flex-grow-1">
                    <span class="font-weight-medium d-block text-body-2 mb-1">{{ item.question_text }}</span>
                    <span class="text-caption text-medium-emphasis">
                      {{ item.options?.length || 0 }} Options
                    </span>
                  </div>

                  <VBtn
                    v-if="quizCRUDStore.activeQuiz.questionIds.includes(item.id)"
                    color="success"
                    variant="tonal"
                    size="small"
                    prepend-icon="tabler-check"
                    @click="toggleQuestionSelection(item)"
                  >
                    Linked
                  </VBtn>
                  <VBtn
                    v-else
                    color="primary"
                    variant="outlined"
                    size="small"
                    prepend-icon="tabler-plus"
                    @click="toggleQuestionSelection(item)"
                  >
                    Link
                  </VBtn>
                </div>
              </template>
            </VVirtualScroll>
          </div>

          <!-- Infinite pagination load more -->
          <div v-if="hasNextPickerPage" class="text-center mt-4">
            <VBtn 
              variant="outlined" 
              size="small" 
              color="secondary" 
              :loading="isFetchingNextPickerPage"
              @click="() => fetchNextPickerPage()"
            >
              {{ $t('common.actions.load_more', 'Load More') }}
            </VBtn>
          </div>
        </VCardText>

        <VCardActions class="pa-4 border-t justify-end">
          <VBtn color="primary" variant="elevated" @click="isPickerOpen = false">
            {{ $t('common.actions.done', 'Done') }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Create Standalone Question Modal (nested inside Picker) -->
    <VDialog v-model="isNewQuestionDialogOpen" max-width="550" persistent scrollable>
      <VCard>
        <VCardTitle class="pa-6 bg-primary text-white d-flex justify-space-between align-center">
          <span>{{ $t('question.bank.create_title', 'Create Question') }}</span>
          <VBtn icon="tabler-x" variant="text" size="small" color="white" @click="isNewQuestionDialogOpen = false" />
        </VCardTitle>

        <VCardText class="pa-6">
          <VTextarea 
            v-model="newQuestionForm.question_text" 
            :label="$t('question.bank.question_text', 'Question Text')" 
            rows="3" 
            variant="outlined" 
            class="mb-4"
            required
          />

          <div class="text-subtitle-1 font-weight-bold mb-2">{{ $t('question.bank.options_title', 'Options (Select correct)') }}</div>
          
          <VRadioGroup 
            :model-value="newQuestionForm.options.findIndex(o => o.is_correct)" 
            @update:model-value="(val) => setNewQuestionCorrectOption(val as number)"
            hide-details
            class="mb-4"
          >
            <div v-for="(opt, oIdx) in newQuestionForm.options" :key="oIdx" class="d-flex align-center gap-2 mb-3">
              <VRadio :value="oIdx" color="success" />
              <VTextField 
                v-model="opt.option_text" 
                :label="`${$t('question.bank.option', 'Option')} ${oIdx + 1}`" 
                variant="outlined" 
                density="compact" 
                hide-details 
                class="flex-grow-1"
                required
              />
              <VBtn icon="tabler-trash" variant="text" color="error" size="small" @click="removeNewQuestionOption(oIdx)" v-if="newQuestionForm.options.length > 2" />
            </div>
          </VRadioGroup>

          <VBtn variant="text" size="small" color="primary" prepend-icon="tabler-plus" @click="addNewQuestionOption">
            {{ $t('question.bank.add_option', 'Add Option') }}
          </VBtn>
        </VCardText>

        <VCardActions class="pa-6 border-t bg-grey-lighten-5 justify-end">
          <VBtn variant="text" color="secondary" @click="isNewQuestionDialogOpen = false">{{ $t('common.actions.cancel', 'Cancel') }}</VBtn>
          <VBtn color="primary" variant="elevated" :loading="isSavingNewQuestion" @click="saveNewQuestion">
            {{ $t('common.actions.save', 'Save Question') }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Confirm Dialog -->
    <VDialog v-model="isConfirmOpen" max-width="450" persistent>
      <VCard class="text-center pa-6">
        <VCardText class="pt-6">
          <VIcon icon="tabler-alert-triangle" size="64" color="warning" class="mb-4" />
          <h3 class="text-h5 mb-2">{{ confirmTitle }}</h3>
          <p class="text-body-1 text-medium-emphasis mb-6">
            {{ confirmText }}
          </p>
        </VCardText>
        <VCardActions class="justify-center gap-4">
          <VBtn variant="text" color="secondary" @click="cancelConfirm">
            {{ $t('common.actions.cancel', 'Cancel') }}
          </VBtn>
          <VBtn color="error" variant="elevated" :loading="isConfirmLoading" @click="executeConfirm">
            {{ $t('common.actions.confirm_delete', 'Delete') }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Alert Dialog -->
    <VDialog v-model="isAlertOpen" max-width="400">
      <VCard class="text-center pa-6">
        <VCardText class="pt-6">
          <VIcon icon="tabler-alert-circle" size="64" color="error" class="mb-4" />
          <h3 class="text-h5 mb-2">{{ alertTitle }}</h3>
          <p class="text-body-1 text-medium-emphasis mb-6">
            {{ alertText }}
          </p>
        </VCardText>
        <VCardActions class="justify-center">
          <VBtn color="primary" variant="elevated" @click="isAlertOpen = false">
            {{ $t('common.actions.ok', 'OK') }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </VCard>
</template>

<style scoped>
.bg-light {
  background-color: rgba(var(--v-theme-on-surface), 0.02);
}
.gap-2 {
  gap: 8px;
}
.gap-4 {
  gap: 16px;
}
</style>

<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue'
import axios from '@/plugins/axios'
import { useI18n } from 'vue-i18n'

const props = defineProps<{
  quizId: number
}>()

const emit = defineEmits(['completed'])
const { t } = useI18n()

const quiz = ref<any>(null)
const lastAttempt = ref<any>(null)
const isLoading = ref(true)
const isSubmitting = ref(false)

// UI States: 'entry' | 'active' | 'results'
const currentState = ref<'entry' | 'active' | 'results'>('entry')

// Answers state: question_id -> option_id
const userAnswers = ref<Record<number, number | null>>({})

// Result state after submission
const resultData = ref<any>(null)

const fetchQuiz = async () => {
  isLoading.value = true
  try {
    const response = await axios.get(`/api/student/quizzes/${props.quizId}`)
    quiz.value = response.data.quiz
    lastAttempt.value = response.data.last_attempt
    
    // Initialize userAnswers with null
    if (quiz.value?.questions) {
      quiz.value.questions.forEach((q: any) => {
        userAnswers.value[q.id] = null
      })
    }
  } catch (error) {
    console.error('Failed to load quiz:', error)
  } finally {
    isLoading.value = false
  }
}

watch(() => props.quizId, () => {
  currentState.value = 'entry'
  userAnswers.value = {}
  resultData.value = null
  fetchQuiz()
}, { immediate: true })

const startQuiz = () => {
  currentState.value = 'active'
  if (quiz.value?.questions) {
    quiz.value.questions.forEach((q: any) => {
      userAnswers.value[q.id] = null
    })
  }
  resultData.value = null
}

const progress = computed(() => {
  if (!quiz.value?.questions) return 0
  const answered = Object.values(userAnswers.value).filter(val => val !== null).length
  const total = quiz.value.questions.length
  return total > 0 ? (answered / total) * 100 : 0
})

const submitQuiz = async () => {
  isSubmitting.value = true
  
  try {
    // CRITICAL: Send null for option_id if skipped
    const payload = quiz.value.questions.map((q: any) => ({
      question_id: q.id,
      option_id: userAnswers.value[q.id]
    }))

    const response = await axios.post(`/api/student/quizzes/${props.quizId}/submit`, { answers: payload })
    resultData.value = response.data
    currentState.value = 'results'
    emit('completed', response.data)
  } catch (error) {
    console.error('Submission failed', error)
  } finally {
    isSubmitting.value = false
  }
}

// Result Helper Functions
const getQuestionResult = (questionId: number) => {
  if (!resultData.value || !resultData.value.results) return null
  return resultData.value.results.find((r: any) => r.question_id === questionId)
}

</script>

<template>
  <VContainer class="px-0 py-4 d-flex justify-center h-100" style="max-width: 800px; min-height: 500px;">
    
    <div v-if="isLoading" class="w-100 mt-4">
      <VSkeletonLoader type="card-avatar, article, actions" />
      <VSkeletonLoader type="list-item-avatar-two-line@3" class="mt-4" />
    </div>

    <VWindow v-else-if="quiz" v-model="currentState" class="w-100 h-100">
      
      <!-- ================= ENTRY SCREEN ================= -->
      <VWindowItem value="entry" class="h-100 d-flex align-center justify-center">
        <VCard elevation="4" rounded="xl" class="pa-8 w-100 max-w-md text-center">
          <VAvatar color="primary" size="80" variant="tonal" class="mb-4">
            <VIcon icon="tabler-clipboard-check" size="40" />
          </VAvatar>
          
          <h2 class="text-h4 font-weight-bold mb-2">{{ quiz.title }}</h2>
          <p class="text-body-1 text-medium-emphasis mb-6">{{ t('quiz.test_knowledge') }}</p>
          
          <div class="d-flex justify-center gap-3 mb-8">
            <VChip color="info" variant="flat" size="large">
              <VIcon start icon="tabler-list-check" />
              {{ t('quiz.questions_count', { count: quiz.questions?.length || 0 }) }}
            </VChip>
            <VChip color="success" variant="flat" size="large">
              <VIcon start icon="tabler-target" />
              {{ t('quiz.passing_grade', { score: quiz.passing_score }) }}
            </VChip>
          </div>

          <div v-if="lastAttempt" class="mb-6 pa-4 bg-primary-lighten-5 rounded-lg border-primary border-opacity-25" style="border-width: 1px; border-style: solid;">
            <p class="text-subtitle-2 text-primary font-weight-bold mb-1">{{ t('quiz.last_attempt', { score: lastAttempt.score }) }}</p>
            <p class="text-caption text-medium-emphasis">
              {{ lastAttempt.passed ? t('quiz.you_passed_msg') : t('quiz.you_failed_msg') }}
            </p>
          </div>

          <VBtn size="x-large" color="primary" block elevation="2" @click="startQuiz">
            {{ lastAttempt ? t('quiz.retake_quiz') : t('quiz.start_quiz') }}
          </VBtn>
        </VCard>
      </VWindowItem>

      <!-- ================= ACTIVE QUIZ SCREEN ================= -->
      <VWindowItem value="active" class="h-100">
        <div class="position-sticky top-0 bg-background z-10 pt-2 pb-4 border-b" style="z-index: 10;">
          <div class="d-flex justify-space-between align-center mb-2">
            <span class="text-h6 font-weight-bold">{{ quiz.title }}</span>
            <span class="text-caption font-weight-bold text-primary">{{ t('quiz.completed_percent', { percent: Math.round(progress) }) }}</span>
          </div>
          <VProgressLinear :model-value="progress" color="primary" height="8" rounded="pill" />
        </div>

        <div class="py-6 d-flex flex-column gap-8">
          <div v-for="(question, index) in quiz.questions" :key="question.id" class="quiz-question">
            <div class="d-flex gap-4 mb-4">
              <VAvatar color="primary" size="32" variant="tonal" class="flex-shrink-0 font-weight-bold">
                {{ index + 1 }}
              </VAvatar>
              <h3 class="text-h5 font-weight-bold" style="line-height: 1.4;">{{ question.question_text }}</h3>
            </div>

            <!-- Custom Interactive Options Grid -->
            <VItemGroup v-model="userAnswers[question.id]">
              <div class="d-flex flex-column gap-3 ps-12">
                <VItem 
                  v-for="option in question.options" 
                  :key="option.id" 
                  :value="option.id"
                  v-slot="{ isSelected, toggle }"
                >
                  <VCard
                    @click="toggle"
                    :class="[
                      'cursor-pointer transition-all',
                      isSelected ? 'border-primary bg-primary-lighten-5' : 'border-opacity-25'
                    ]"
                    style="border-width: 2px; border-style: solid;"
                    :style="{ borderColor: isSelected ? 'rgb(var(--v-theme-primary))' : 'rgba(var(--v-theme-on-surface), 0.12)' }"
                    elevation="0"
                    rounded="lg"
                    class="pa-4 d-flex align-center gap-4 hover:bg-grey-100"
                  >
                    <VIcon
                      :icon="isSelected ? 'tabler-circle-check-filled' : 'tabler-circle'"
                      :color="isSelected ? 'primary' : 'grey-lighten-1'"
                      size="24"
                    />
                    <img
                      v-if="option.image_path"
                      :src="`/storage/${option.image_path}`"
                      style="width:72px; height:72px; object-fit:cover; border-radius:8px; flex-shrink:0;"
                    />
                    <span :class="['text-body-1 transition-all', isSelected ? 'font-weight-bold text-primary' : 'text-medium-emphasis']">
                      {{ option.option_text }}
                    </span>
                  </VCard>
                </VItem>
              </div>
            </VItemGroup>
          </div>
        </div>

        <div class="py-6 border-t mt-4 d-flex justify-end gap-4">
          <VBtn 
            size="large" 
            color="primary" 
            elevation="2" 
            :loading="isSubmitting"
            @click="submitQuiz"
            append-icon="tabler-send"
          >
            {{ t('quiz.submit_answers') }}
          </VBtn>
        </div>
      </VWindowItem>

      <!-- ================= RESULTS SCREEN ================= -->
      <VWindowItem value="results" class="h-100 py-4">
        
        <!-- Score Header -->
        <VCard elevation="0" rounded="xl" class="pa-8 text-center mb-8" 
               :style="{ borderWidth: '2px', borderStyle: 'solid', borderColor: resultData.passed ? 'rgba(var(--v-theme-success), 0.5)' : 'rgba(var(--v-theme-error), 0.5)' }">
          <div class="d-flex flex-column align-center">
            <VProgressCircular
              :model-value="resultData.score"
              :color="resultData.passed ? 'success' : 'error'"
              size="160"
              width="12"
              class="mb-4 transition-all"
              style="transition-duration: 1s;"
            >
              <div class="text-center">
                <div class="text-h3 font-weight-black" :class="resultData.passed ? 'text-success' : 'text-error'">
                  {{ resultData.score }}%
                </div>
              </div>
            </VProgressCircular>

            <VChip :color="resultData.passed ? 'success' : 'error'" size="x-large" class="text-subtitle-1 font-weight-bold px-6 mb-2">
              {{ resultData.passed ? t('quiz.you_passed') : t('quiz.you_failed') }}
            </VChip>
            
            <p class="text-body-1 text-medium-emphasis mb-6">
              {{ resultData.passed ? t('quiz.great_job') : t('quiz.review_answers_msg') }}
            </p>

            <VBtn 
              size="x-large" 
              :color="resultData.passed ? 'primary' : 'info'" 
              variant="elevated"
              elevation="3"
              @click="resultData.passed ? $emit('completed', resultData) : startQuiz()"
              :append-icon="resultData.passed ? 'tabler-arrow-right' : 'tabler-refresh'"
            >
              {{ resultData.passed ? t('quiz.continue_next') : t('quiz.review_try_again') }}
            </VBtn>
          </div>
        </VCard>

        <h4 class="text-h5 font-weight-bold mb-6 d-flex align-center gap-2">
          <VIcon icon="tabler-file-analytics" />
          {{ t('quiz.review_answers') }}
        </h4>

        <!-- Answers Review -->
        <div class="d-flex flex-column gap-6">
          <VCard 
            v-for="(question, index) in quiz.questions" 
            :key="question.id" 
            elevation="0"
            rounded="lg"
            :style="{ borderWidth: '2px', borderStyle: 'solid', borderColor: getQuestionResult(question.id)?.is_correct ? 'rgba(var(--v-theme-success), 0.3)' : 'rgba(var(--v-theme-error), 0.4)' }"
            class="overflow-hidden transition-all"
          >
            <!-- Question Header -->
            <div :class="['pa-4 d-flex gap-4 align-start', getQuestionResult(question.id)?.is_correct ? 'bg-success-lighten-5' : 'bg-error-lighten-5']">
              <VIcon 
                :icon="getQuestionResult(question.id)?.is_correct ? 'tabler-check' : 'tabler-x'" 
                :color="getQuestionResult(question.id)?.is_correct ? 'success' : 'error'" 
                size="28" 
                class="mt-1 flex-shrink-0"
              />
              <div>
                <div class="text-caption font-weight-bold mb-1" :class="getQuestionResult(question.id)?.is_correct ? 'text-success' : 'text-error'">
                  {{ t('quiz.question_n', { n: index + 1 }) }}
                </div>
                <h4 class="text-subtitle-1 font-weight-bold">{{ question.question_text }}</h4>
              </div>
            </div>

            <!-- Options List (Review Mode) -->
            <div class="pa-4 d-flex flex-column gap-2">
              <div 
                v-for="option in question.options" 
                :key="option.id"
                class="pa-3 rounded-md d-flex align-center justify-space-between gap-3"
                :class="[
                  // If this is the option they selected:
                  userAnswers[question.id] === option.id 
                    ? (getQuestionResult(question.id)?.is_correct ? 'bg-success-lighten-4' : 'bg-error-lighten-4') 
                    : 'bg-transparent',
                  // If this is the correct option (only populated if they passed overall)
                  getQuestionResult(question.id)?.correct_option_id === option.id && userAnswers[question.id] !== option.id
                    ? 'bg-success-lighten-5' 
                    : ''
                ]"
                :style="{
                  borderWidth: '2px', 
                  borderStyle: getQuestionResult(question.id)?.correct_option_id === option.id && userAnswers[question.id] !== option.id ? 'dashed' : 'solid', 
                  borderColor: userAnswers[question.id] === option.id 
                    ? (getQuestionResult(question.id)?.is_correct ? 'rgba(var(--v-theme-success), 1)' : 'rgba(var(--v-theme-error), 1)')
                    : (getQuestionResult(question.id)?.correct_option_id === option.id ? 'rgba(var(--v-theme-success), 0.5)' : 'transparent')
                }"
              >
                <div class="d-flex align-center gap-3">
                  <VIcon
                    :icon="userAnswers[question.id] === option.id ? (getQuestionResult(question.id)?.is_correct ? 'tabler-circle-check-filled' : 'tabler-circle-x-filled') : 'tabler-circle'"
                    :color="userAnswers[question.id] === option.id ? (getQuestionResult(question.id)?.is_correct ? 'success' : 'error') : 'grey-lighten-1'"
                    size="20"
                  />
                  <img
                    v-if="option.image_path"
                    :src="`/storage/${option.image_path}`"
                    style="width:52px; height:52px; object-fit:cover; border-radius:6px; flex-shrink:0;"
                  />
                  <span class="text-body-2 font-weight-medium" :class="{'text-medium-emphasis': userAnswers[question.id] !== option.id}">
                    {{ option.option_text }}
                  </span>
                </div>
                
                <VChip v-if="userAnswers[question.id] === option.id" size="x-small" :color="getQuestionResult(question.id)?.is_correct ? 'success' : 'error'" variant="flat">
                  {{ t('quiz.your_answer') }}
                </VChip>
                <VChip v-else-if="getQuestionResult(question.id)?.correct_option_id === option.id" size="x-small" color="success" variant="outlined">
                  {{ t('quiz.correct_answer') }}
                </VChip>
              </div>
            </div>
          </VCard>
        </div>

      </VWindowItem>

    </VWindow>
  </VContainer>
</template>

<style scoped>
@import url('https://fonts.cdnfonts.com/css/neo-sans-arabic');
.hover\:bg-grey-100:hover {
  background-color: rgba(var(--v-theme-on-surface), 0.04);
}
.transition-all {
  transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
}
.bg-background {
  background-color: rgb(var(--v-theme-background));
}
</style>

import { defineStore } from 'pinia'
import { ref } from 'vue'

export interface Option {
  id?: number
  question_id?: number
  option_text: string
  is_correct: boolean
  image_path?: string | null
}

export interface Question {
  id?: number
  question_text: string
  order?: number
  options: Option[]
}

export interface ActiveQuiz {
  id: number | null
  title: string
  passing_score: number
  course_module_id: number | null
  questionIds: number[]
}

export const useQuizCRUDStore = defineStore('quizCRUD', () => {
  // Flattened Questions Cache
  const questions = ref<Record<number, Question>>({})

  // Active Quiz representation using questionIds list
  const activeQuiz = ref<ActiveQuiz>({
    id: null,
    title: '',
    passing_score: 70,
    course_module_id: null,
    questionIds: []
  })

  const clearActiveQuiz = () => {
    activeQuiz.value = {
      id: null,
      title: '',
      passing_score: 70,
      course_module_id: null,
      questionIds: []
    }
    questions.value = {}
  }

  const setActiveQuiz = (quiz: any) => {
    activeQuiz.value = {
      id: quiz.id || null,
      title: quiz.title || '',
      passing_score: quiz.passing_score ?? 70,
      course_module_id: quiz.course_module_id || null,
      questionIds: []
    }

    const ids: number[] = []
    if (Array.isArray(quiz.questions)) {
      quiz.questions.forEach((q: any) => {
        if (q.id) {
          questions.value[q.id] = {
            id: q.id,
            question_text: q.question_text,
            order: q.pivot?.order ?? q.order ?? 0,
            options: (q.options || []).map((o: any) => ({
              id: o.id,
              question_id: o.question_id,
              option_text: o.option_text,
              is_correct: !!o.is_correct,
              image_path: o.image_path || null
            }))
          }
          ids.push(q.id)
        }
      })
    }
    activeQuiz.value.questionIds = ids
  }

  const addQuestionToQuiz = (question: Question) => {
    if (question.id) {
      questions.value[question.id] = question
      if (!activeQuiz.value.questionIds.includes(question.id)) {
        activeQuiz.value.questionIds.push(question.id)
      }
    }
  }

  const removeQuestionFromQuiz = (questionId: number) => {
    activeQuiz.value.questionIds = activeQuiz.value.questionIds.filter(id => id !== questionId)
  }

  const reorderQuizQuestions = (newIds: number[]) => {
    activeQuiz.value.questionIds = newIds
  }

  return {
    questions,
    activeQuiz,
    clearActiveQuiz,
    setActiveQuiz,
    addQuestionToQuiz,
    removeQuestionFromQuiz,
    reorderQuizQuestions
  }
})

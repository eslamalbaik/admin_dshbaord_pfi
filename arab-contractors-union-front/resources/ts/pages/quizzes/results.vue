<script setup lang="ts">
import { ref, onMounted, watch } from 'vue'
import { useQuizResultsStore } from '@/stores/quizResultsStore'

definePage({ meta: { requiresAdmin: true } })

const quizResultsStore = useQuizResultsStore()
const searchQuery = ref('')
let debounceTimeout: any = null

const isAttemptsModalOpen = ref(false)
const selectedAttempt = ref<any>(null)

// Load initial attempts
onMounted(() => {
  quizResultsStore.fetchAttempts(null, '', true)
})

// Search input debounce (400ms)
watch(searchQuery, (newVal) => {
  if (debounceTimeout) clearTimeout(debounceTimeout)
  debounceTimeout = setTimeout(() => {
    quizResultsStore.fetchAttempts(null, newVal)
  }, 400)
})

const retryFetch = () => {
  quizResultsStore.fetchAttempts(null, searchQuery.value, true)
}

const loadMore = () => {
  if (quizResultsStore.nextCursor) {
    quizResultsStore.fetchAttempts(quizResultsStore.nextCursor, searchQuery.value)
  }
}

const openAttemptsModal = (attempt: any) => {
  console.log('openAttemptsModal triggered for attempt:', attempt)
  selectedAttempt.value = attempt
  isAttemptsModalOpen.value = true
  console.log('isAttemptsModalOpen set to:', isAttemptsModalOpen.value)
  
  if (attempt && attempt.user_id && attempt.quiz_id) {
    quizResultsStore.fetchModalAttempts(attempt.user_id, attempt.quiz_id)
  } else {
    console.warn('Attempt object is missing user_id or quiz_id:', attempt)
  }
}
</script>

<template>
  <VCard class="mx-auto" max-width="1200">
    <VCardItem class="pb-0">
      <VCardTitle class="d-flex align-center gap-2 text-h5">
        <VIcon icon="tabler-chart-bar" class="text-primary" />
        {{ $t('quiz.results.title') }}
      </VCardTitle>
      <VCardSubtitle class="text-subtitle-1">
        {{ $t('quiz.results.subtitle') }}
      </VCardSubtitle>
    </VCardItem>

    <VCardText class="mt-4">
      <!-- Search Input with Debounce -->
      <VTextField
        v-model="searchQuery"
        :placeholder="$t('quiz.results.search_placeholder')"
        prepend-inner-icon="tabler-search"
        variant="outlined"
        clearable
        class="mb-6"
      />

      <!-- Error Fallback -->
      <VAlert
        v-if="quizResultsStore.error"
        type="error"
        variant="tonal"
        class="mb-6"
      >
        <div class="d-flex align-center justify-between w-100 flex-wrap gap-2">
          <span>{{ quizResultsStore.error }}</span>
          <VBtn color="error" size="small" variant="flat" @click="retryFetch">
            {{ $t('quiz.results.retry') }}
          </VBtn>
        </div>
      </VAlert>

      <!-- Main Results Table -->
      <VTable class="border rounded">
        <thead>
          <tr class="bg-light">
            <th class="text-subtitle-2 font-weight-bold">{{ $t('quiz.results.student') }}</th>
            <th class="text-subtitle-2 font-weight-bold">{{ $t('quiz.results.quiz') }}</th>
            <th class="text-subtitle-2 font-weight-bold">{{ $t('quiz.results.score') }}</th>
            <th class="text-subtitle-2 font-weight-bold">{{ $t('quiz.results.date') }}</th>
            <th class="text-subtitle-2 font-weight-bold">{{ $t('quiz.results.status') }}</th>
            <th class="text-subtitle-2 font-weight-bold text-center">{{ $t('quiz.attempts.title') }}</th>
          </tr>
        </thead>
        <tbody>
          <!-- Empty State -->
          <tr v-if="quizResultsStore.attempts.length === 0 && !quizResultsStore.isLoading">
            <td colspan="6" class="text-center text-medium-emphasis py-12">
              <VIcon icon="tabler-clipboard-off" size="40" class="mb-2 text-disabled" />
              <div>{{ $t('quiz.results.no_results') }}</div>
            </td>
          </tr>

          <!-- Data Rows -->
          <template v-for="attempt in quizResultsStore.attempts" :key="attempt.id">
            <tr>
              <td>
                <div class="font-weight-medium">{{ attempt.user?.name || 'N/A' }}</div>
                <div class="text-caption text-medium-emphasis">{{ attempt.user?.email }}</div>
              </td>
              <td>
                <div class="font-weight-medium">{{ attempt.quiz?.title || 'N/A' }}</div>
              </td>
              <td class="font-weight-bold text-primary">{{ attempt.score }}%</td>
              <td>{{ new Date(attempt.created_at).toLocaleString() }}</td>
              <td>
                <VChip
                  :color="attempt.passed ? 'success' : 'error'"
                  size="small"
                  label
                  class="font-weight-bold text-capitalize"
                >
                  {{ attempt.passed ? $t('quiz.results.passed') : $t('quiz.results.failed') }}
                </VChip>
              </td>
              <td class="text-center">
                <VBtn
                  variant="tonal"
                  size="small"
                  prepend-icon="tabler-history"
                  @click="openAttemptsModal(attempt)"
                >
                  {{ $t('quiz.attempts.title') }}
                </VBtn>
              </td>
            </tr>
          </template>

          <!-- Loading Row -->
          <tr v-if="quizResultsStore.isLoading">
            <td colspan="6" class="text-center py-6">
              <VProgressCircular indeterminate color="primary" size="28" class="me-2" />
              <span>{{ $t('quiz.results.loading') }}</span>
            </td>
          </tr>
        </tbody>
      </VTable>

      <!-- Load More Button -->
      <div v-if="quizResultsStore.nextCursor && !quizResultsStore.isLoading" class="text-center mt-6">
        <VBtn
          color="primary"
          variant="outlined"
          prepend-icon="tabler-arrow-down"
          @click="loadMore"
        >
          {{ $t('quiz.results.load_more') }}
        </VBtn>
      </div>
    </VCardText>

    <!-- Expanded Attempts History Modal (nested inside VCard to preserve Vuetify context) -->
    <VDialog v-model="isAttemptsModalOpen" max-width="600">
      <VCard>
        <VCardItem class="pb-2">
          <VCardTitle class="d-flex align-center gap-2">
            <VIcon icon="tabler-history" class="text-primary" />
            {{ $t('quiz.attempts.title') }}
          </VCardTitle>
          <VCardSubtitle v-if="selectedAttempt">
            {{ selectedAttempt.user?.name }} - {{ selectedAttempt.quiz?.title }}
          </VCardSubtitle>
        </VCardItem>

        <VCardText class="pa-4">
          <!-- Loading State -->
          <div v-if="quizResultsStore.isModalLoading" class="text-center py-6">
            <VProgressCircular indeterminate color="primary" size="28" class="me-2" />
            <div class="mt-2 text-medium-emphasis">{{ $t('quiz.results.loading') }}</div>
          </div>

          <!-- Error State -->
          <VAlert v-else-if="quizResultsStore.modalError" type="error" variant="tonal" class="mb-4">
            {{ quizResultsStore.modalError }}
          </VAlert>

          <!-- Data Content -->
          <div v-else>
            <!-- VVirtualScroll for 50+ attempts, normal list otherwise -->
            <div v-if="quizResultsStore.modalAttempts.length >= 50">
              <VVirtualScroll
                :items="quizResultsStore.modalAttempts"
                height="350"
                item-height="60"
              >
                <template #default="{ item }">
                  <div class="d-flex align-center justify-space-between border-b py-2 px-1">
                    <div>
                      <div class="font-weight-medium">{{ item.score }}%</div>
                      <div class="text-caption text-medium-emphasis">
                        {{ new Date(item.created_at).toLocaleString() }}
                      </div>
                    </div>
                    <VChip :color="item.passed ? 'success' : 'error'" size="x-small" label>
                      {{ item.passed ? $t('quiz.results.passed') : $t('quiz.results.failed') }}
                    </VChip>
                  </div>
                </template>
              </VVirtualScroll>
            </div>
            <div v-else>
              <VList border class="rounded">
                <VListItem
                  v-for="item in quizResultsStore.modalAttempts"
                  :key="item.id"
                  class="border-b last:border-0"
                >
                  <template #prepend>
                    <VIcon
                      :icon="item.passed ? 'tabler-circle-check' : 'tabler-circle-x'"
                      :color="item.passed ? 'success' : 'error'"
                      class="me-3"
                    />
                  </template>
                  <VListItemTitle class="font-weight-bold">
                    {{ item.score }}%
                  </VListItemTitle>
                  <VListItemSubtitle>
                    {{ new Date(item.created_at).toLocaleString() }}
                  </VListItemSubtitle>
                  <template #append>
                    <VChip :color="item.passed ? 'success' : 'error'" size="x-small" label>
                      {{ item.passed ? $t('quiz.results.passed') : $t('quiz.results.failed') }}
                    </VChip>
                  </template>
                </VListItem>
              </VList>
            </div>
          </div>
        </VCardText>

        <VCardActions class="justify-end px-4 pb-4 pt-0">
          <VBtn variant="tonal" color="secondary" @click="isAttemptsModalOpen = false">
            {{ $t('Close') }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </VCard>
</template>

<style scoped>
.bg-light {
  background-color: rgba(var(--v-theme-on-surface), 0.03);
}
</style>

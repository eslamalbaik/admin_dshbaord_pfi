<script setup lang="ts">
import { useDisplay } from 'vuetify'

// شريط خطوات فورم المقاول — مشترك بين صفحة التسجيل وصفحة التعديل حتى يبقى الشكل واحداً.
export type WizardStepState = 'current' | 'error' | 'done' | 'partial' | 'upcoming'

const props = defineProps<{
  steps: Array<{ title: string, subtitle: string, icon: string }>
  step: number
  maxReached: number
  stepState: (s: number) => WizardStepState
}>()

const emit = defineEmits<{ (e: 'go', step: number): void }>()

const { smAndDown } = useDisplay()
</script>

<template>
  <!-- ديسكتوب -->
  <VCard
    v-if="!smAndDown"
    class="mb-6"
  >
    <VCardText class="wizard-steps">
      <template
        v-for="(s, i) in props.steps"
        :key="i"
      >
        <button
          type="button"
          class="wizard-step"
          :class="`wizard-step--${props.stepState(i + 1)}`"
          :data-step="i + 1"
          @click="emit('go', i + 1)"
        >
          <span class="wizard-step__badge">
            <VIcon
              v-if="props.stepState(i + 1) === 'done'"
              icon="tabler-check"
              size="20"
            />
            <VIcon
              v-else-if="props.stepState(i + 1) === 'error'"
              icon="tabler-alert-triangle"
              size="20"
            />
            <VIcon
              v-else
              :icon="s.icon"
              size="20"
            />
          </span>
          <span class="wizard-step__text">
            <span class="wizard-step__num">الخطوة {{ i + 1 }}</span>
            <span class="wizard-step__title">{{ s.title }}</span>
            <span class="wizard-step__subtitle">{{ s.subtitle }}</span>
          </span>
        </button>
        <span
          v-if="i < props.steps.length - 1"
          class="wizard-connector"
          :class="{ 'wizard-connector--active': props.maxReached > i + 1 }"
        />
      </template>
    </VCardText>
  </VCard>

  <!-- موبايل (مضغوط) -->
  <VCard
    v-else
    class="mb-4"
  >
    <VCardText class="pa-4">
      <div class="d-flex align-center gap-3 mb-3">
        <VAvatar
          color="primary"
          variant="flat"
          size="40"
          rounded
        >
          <VIcon
            :icon="props.steps[props.step - 1].icon"
            size="22"
          />
        </VAvatar>
        <div class="flex-grow-1">
          <div class="text-caption text-medium-emphasis">
            الخطوة {{ props.step }} من {{ props.steps.length }}
          </div>
          <div class="text-subtitle-1 font-weight-bold">
            {{ props.steps[props.step - 1].title }}
          </div>
        </div>
      </div>
      <div class="wizard-segments">
        <button
          v-for="(s, i) in props.steps"
          :key="i"
          type="button"
          class="wizard-segment"
          :class="`wizard-segment--${props.stepState(i + 1)}`"
          :data-step="i + 1"
          :aria-label="s.title"
          @click="emit('go', i + 1)"
        />
      </div>
    </VCardText>
  </VCard>
</template>

<style scoped src="@/views/contractors/contractor-wizard.css"></style>

<script lang="ts" setup>
import { layoutConfig } from '@layouts'
import { can } from '@layouts/plugins/casl'
import { useLayoutConfigStore } from '@layouts/stores/config'
import type { NavSectionTitle } from '@layouts/types'
import { getDynamicI18nProps } from '@layouts/utils'

defineProps<{
  item: NavSectionTitle
}>()

const configStore = useLayoutConfigStore()
const shallRenderIcon = configStore.isVerticalNavMini()
</script>

<template>
  <li
    v-if="can(item.action, item.subject)"
    class="nav-section-title"
    :class="{ 'has-subtitle': item.subtitle && !shallRenderIcon }"
  >
    <div class="title-wrapper">
      <Transition
        name="vertical-nav-section-title"
        mode="out-in"
      >
        <Component
          :is="shallRenderIcon ? layoutConfig.app.iconRenderer : layoutConfig.app.i18n.enable ? 'i18n-t' : 'span'"
          :key="shallRenderIcon"
          :class="shallRenderIcon ? 'placeholder-icon' : 'title-text'"
          v-bind="{ ...layoutConfig.icons.sectionTitlePlaceholder, ...getDynamicI18nProps(item.heading, 'span') }"
        >
          {{ !shallRenderIcon ? item.heading : null }}
        </Component>
      </Transition>
      <span
        v-if="item.subtitle && !shallRenderIcon"
        class="title-subtitle"
      >{{ item.subtitle }}</span>
    </div>
  </li>
</template>

<style lang="scss">
.layout-vertical-nav .nav-section-title.has-subtitle {
  block-size: auto;
  margin-block-end: 0.75rem !important;

  // عنوان المجموعة ووصفها على نفس الخط العمودي مع أسماء العناصر (بعد عرض الأيقونة والفراغ)
  .title-wrapper {
    display: flex;
    flex-direction: column;
    gap: 2px;
    padding-inline-start: 30px;
  }

  .title-text {
    color: rgba(var(--v-theme-on-surface), var(--v-high-emphasis-opacity));
    font-weight: 700;
  }

  .title-subtitle {
    color: rgba(var(--v-theme-on-surface), var(--v-disabled-opacity));
    font-size: 0.6875rem;
    line-height: 1rem;
    text-transform: none;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }
}
</style>

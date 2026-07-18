<script setup lang="ts">
definePage({ meta: { requiresAdmin: true } })
</script>

<template>
  <VCard>
    <VCardItem>
      <VCardTitle class="d-flex align-center gap-2">
        <VIcon icon="tabler-category" />
        {{ $t('Categories') }}
      </VCardTitle>
      <VCardSubtitle>{{ $t('Manage course categories and subcategories') }}</VCardSubtitle>

      <template #append>
        <VBtn color="primary" prepend-icon="tabler-plus" size="small">
          {{ $t('Add Category') }}
        </VBtn>
      </template>
    </VCardItem>

    <VCardText>
      <VTable>
        <thead>
          <tr>
            <th>{{ $t('Name') }}</th>
            <th>{{ $t('Parent Category') }}</th>
            <th>{{ $t('Courses Count') }}</th>
            <th>{{ $t('Status') }}</th>
            <th>{{ $t('Actions') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td colspan="5" class="text-center text-medium-emphasis pa-8">
              {{ $t('No categories created yet.') }} Add your first category.
            </td>
          </tr>
        </tbody>
      </VTable>
    </VCardText>
  </VCard>
</template>

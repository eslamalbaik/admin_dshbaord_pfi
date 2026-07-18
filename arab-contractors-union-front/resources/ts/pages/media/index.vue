<script setup lang="ts">
definePage({ meta: { requiresAdmin: true } })
</script>

<template>
  <VCard>
    <VCardItem>
      <VCardTitle class="d-flex align-center gap-2">
        <VIcon icon="tabler-photo" />
        {{ $t('Media Library') }}
      </VCardTitle>
      <VCardSubtitle>{{ $t('Manage uploaded images, videos, and documents') }}</VCardSubtitle>

      <template #append>
        <VBtn color="primary" prepend-icon="tabler-upload" size="small">
          {{ $t('Upload Files') }}
        </VBtn>
      </template>
    </VCardItem>

    <VCardText>
      <div class="text-center text-medium-emphasis pa-8">
        <VIcon icon="tabler-cloud-upload" size="48" class="mb-4 text-disabled" />
        <p>{{ $t('No media files uploaded yet.') }} {{ $t('Upload images, PDFs, or other course materials.') }}</p>
      </div>
    </VCardText>
  </VCard>
</template>

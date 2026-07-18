<script setup lang="ts">
definePage({ meta: { requiresAdmin: true } })
</script>

<template>
  <VCard>
    <VCardItem>
      <VCardTitle class="d-flex align-center gap-2">
        <VIcon icon="tabler-ticket" />
        {{ $t('Coupons') }}
      </VCardTitle>
      <VCardSubtitle>{{ $t('Manage discount coupons') }}</VCardSubtitle>

      <template #append>
        <VBtn color="primary" prepend-icon="tabler-plus" size="small">
          {{ $t('Create Coupon') }}
        </VBtn>
      </template>
    </VCardItem>

    <VCardText>
      <VTable>
        <thead>
          <tr>
            <th>{{ $t('Code') }}</th>
            <th>{{ $t('Discount') }}</th>
            <th>{{ $t('Usage Limit') }}</th>
            <th>{{ $t('Expires At') }}</th>
            <th>{{ $t('Status') }}</th>
            <th>{{ $t('Actions') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td colspan="7" class="text-center text-medium-emphasis pa-8">
              {{ $t('No coupons created yet.') }}
            </td>
          </tr>
        </tbody>
      </VTable>
    </VCardText>
  </VCard>
</template>

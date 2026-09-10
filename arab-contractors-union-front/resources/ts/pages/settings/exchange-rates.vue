<script setup lang="ts">
import { computed, ref } from 'vue'
import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query'
import api from '@/plugins/axios'

definePage({ meta: { requiresAdmin: true } })

const queryClient = useQueryClient()

const { data, isLoading } = useQuery({
  queryKey: ['admin-exchange-rates'],
  queryFn: async () => (await api.get('/api/v1/dashboard/exchange-rates')).data.items,
})

const currencies = [
  { code: 'USD', label: 'دولار أمريكي (USD)', icon: 'tabler-currency-dollar' },
  { code: 'ILS', label: 'شيكل (ILS)', icon: 'tabler-currency-shekel' },
]

const sourceLabels: Record<string, string> = {
  pma_direct: 'سلطة النقد (مباشر)',
  supabase_proxy: 'سلطة النقد (مصدر مساعد)',
  manual: 'إدخال يدوي',
  api: 'مصدر عام',
}

const latest = computed(() => data.value?.latest ?? {})
const history = computed(() => data.value?.history ?? [])

// المخزَّن بالباك اند rate_to_jod = "1 [عملة] = ؟ دينار" — نعرض ونستقبل بالاتجاه المعتاد
// للمستخدم "1 دينار = ؟ [عملة]" ونحوّل عند الحفظ فقط.
const displayRate = (code: string) => {
  const r = latest.value[code]
  return r ? (1 / Number(r.rate_to_jod)).toFixed(4) : null
}

const formCurrency = ref<'USD' | 'ILS'>('USD')
const formValue = ref<number | null>(null)
const formError = ref('')

const openEditFor = (code: 'USD' | 'ILS') => {
  formCurrency.value = code
  const current = displayRate(code)
  formValue.value = current ? Number(current) : null
  formError.value = ''
  isFormOpen.value = true
}

const isFormOpen = ref(false)

const saveMutation = useMutation({
  mutationFn: async () => {
    if (!formValue.value || formValue.value <= 0)
      throw new Error('أدخل قيمة صحيحة.')

    // تحويل "1 دينار = X [عملة]" إلى rate_to_jod = "1 [عملة] = ؟ دينار" كما يخزّنه الباك اند
    const rateToJod = 1 / formValue.value

    return (await api.post('/api/v1/dashboard/exchange-rates', {
      currency: formCurrency.value,
      rate_to_jod: rateToJod,
    })).data
  },
  onSuccess: () => {
    queryClient.invalidateQueries({ queryKey: ['admin-exchange-rates'] })
    isFormOpen.value = false
  },
  onError: (e: any) => {
    formError.value = e?.response?.data?.message || e?.message || 'فشل حفظ سعر الصرف.'
  },
})

const fmtDate = (d: string | null) => {
  if (!d)
    return '—'
  return new Date(d).toLocaleString('ar-EG', { dateStyle: 'medium', timeStyle: 'short' })
}
</script>

<template>
  <div>
    <div class="mb-6">
      <h1 class="text-h4 font-weight-bold">أسعار الصرف</h1>
      <p class="text-body-2 text-medium-emphasis mb-0">
        الأسعار تُجلب تلقائياً من سلطة النقد الفلسطينية — يمكن تعديل أي سعر يدوياً عند الحاجة (خطأ بالمصدر الآلي، انقطاع، إلخ)
      </p>
    </div>

    <VProgressLinear v-if="isLoading" indeterminate color="primary" class="mb-4" />

    <VRow v-else>
      <VCol v-for="c in currencies" :key="c.code" cols="12" md="6">
        <VCard>
          <VCardText>
            <div class="d-flex align-center justify-space-between mb-3">
              <div class="d-flex align-center gap-2">
                <VIcon :icon="c.icon" size="22" color="primary" />
                <span class="text-h6">{{ c.label }}</span>
              </div>
              <VBtn size="small" variant="tonal" prepend-icon="tabler-edit" @click="openEditFor(c.code as 'USD' | 'ILS')">
                تعديل يدوي
              </VBtn>
            </div>

            <div v-if="latest[c.code]">
              <div class="text-h5 font-weight-bold mb-1">
                1 JOD = {{ displayRate(c.code) }} {{ c.code }}
              </div>
              <div class="text-caption text-medium-emphasis">
                {{ sourceLabels[latest[c.code].source] ?? latest[c.code].source }} · {{ fmtDate(latest[c.code].fetched_at) }}
              </div>
            </div>
            <div v-else class="text-body-2 text-medium-emphasis">
              لا يوجد سعر محفوظ بعد
            </div>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <VCard v-if="history.length" class="mt-6">
      <VCardTitle class="text-body-1 font-weight-bold">سجل الأسعار</VCardTitle>
      <VTable>
        <thead>
          <tr>
            <th>العملة</th>
            <th>1 دينار = ؟</th>
            <th>المصدر</th>
            <th>التاريخ</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="h in history" :key="h.id">
            <td>{{ h.currency }}</td>
            <td dir="ltr">{{ (1 / Number(h.rate_to_jod)).toFixed(4) }}</td>
            <td>{{ sourceLabels[h.source] ?? h.source }}</td>
            <td class="text-body-2">{{ fmtDate(h.fetched_at) }}</td>
          </tr>
        </tbody>
      </VTable>
    </VCard>

    <!-- Manual override dialog -->
    <VDialog v-model="isFormOpen" max-width="440" persistent>
      <VCard>
        <VCardTitle>تعديل سعر الصرف يدوياً — {{ formCurrency }}</VCardTitle>
        <VCardText>
          <VAlert v-if="formError" type="error" variant="tonal" class="mb-4">
            {{ formError }}
          </VAlert>
          <VTextField
            v-model.number="formValue"
            :label="`1 دينار (JOD) = ؟ ${formCurrency}`"
            type="number"
            step="0.0001"
            min="0.0001"
            dir="ltr"
          />
          <p class="text-caption text-medium-emphasis mt-2">
            هذا السعر يُعتمد فوراً لكل عمليات التحويل بالنظام حتى يُحدَّث تلقائياً أو يدوياً مجدداً.
          </p>
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn variant="text" @click="isFormOpen = false">إلغاء</VBtn>
          <VBtn color="primary" :loading="saveMutation.isPending.value" @click="saveMutation.mutate()">
            حفظ واعتماد
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>

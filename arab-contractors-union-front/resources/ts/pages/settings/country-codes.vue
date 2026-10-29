<script setup lang="ts">
import { computed, ref } from 'vue'
import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query'
import api from '@/plugins/axios'
import { describeRule, loadDialCodes } from '@/utils/dialCodes'
import type { DialCode } from '@/utils/dialCodes'

definePage({ meta: { requiresAdmin: true, adminOnly: true } })

const queryClient = useQueryClient()

const { data, isLoading } = useQuery({
  queryKey: ['country-codes'],
  queryFn: async () => (await api.get('/api/v1/dashboard/country-codes')).data,
})
const rows = computed<(DialCode & { has_custom_flag: boolean, sort: number })[]>(() => data.value?.items ?? [])

const refresh = () => {
  queryClient.invalidateQueries({ queryKey: ['country-codes'] })
  // خانات الجوال المفتوحة بالصفحات التانية بتاخد القائمة الجديدة
  loadDialCodes(true)
}

// ─── فورم إضافة/تعديل ─────────────────────────────────────────────────────
const formOpen = ref(false)
const editingId = ref<number | null>(null)
const formError = ref('')
const flagFile = ref<File | File[] | null>(null)
const currentFlag = ref<string | null>(null)
const removeFlag = ref(false)

const emptyForm = () => ({
  name: '',
  iso_code: '',
  dial_code: '',
  min_digits: 9 as number | string,
  max_digits: 9 as number | string,
  starts_with: '',
  sort: 0 as number | string,
  is_default: false,
})
const form = ref(emptyForm())

const openCreate = () => {
  editingId.value = null
  form.value = { ...emptyForm(), sort: rows.value.length + 1 }
  flagFile.value = null
  currentFlag.value = null
  removeFlag.value = false
  formError.value = ''
  formOpen.value = true
}

const openEdit = (row: any) => {
  editingId.value = row.id
  form.value = {
    name: row.name,
    iso_code: row.iso_code ?? '',
    dial_code: row.dial_code,
    min_digits: row.min_digits,
    max_digits: row.max_digits,
    starts_with: row.starts_with ?? '',
    sort: row.sort,
    is_default: row.is_default,
  }
  flagFile.value = null
  currentFlag.value = row.has_custom_flag ? row.flag_url : null
  removeFlag.value = false
  formError.value = ''
  formOpen.value = true
}

// معاينة العلم: المرفوع الآن ← المرفوع سابقاً ← علم الدولة من رمزها
const pickedFile = computed(() => (Array.isArray(flagFile.value) ? flagFile.value[0] : flagFile.value) ?? null)
const previewFlag = computed(() => {
  if (pickedFile.value)
    return URL.createObjectURL(pickedFile.value)
  if (currentFlag.value && !removeFlag.value)
    return currentFlag.value
  const iso = String(form.value.iso_code ?? '').trim()

  return /^[a-z]{2}$/i.test(iso) ? `https://flagcdn.com/w40/${iso.toLowerCase()}.png` : null
})

const previewRule = computed(() => describeRule({
  min_digits: Number(form.value.min_digits) || 0,
  max_digits: Number(form.value.max_digits) || 0,
  starts_with: form.value.starts_with,
} as DialCode))

const saveMutation = useMutation({
  mutationFn: async () => {
    const fd = new FormData()
    Object.entries(form.value).forEach(([k, v]) => {
      fd.append(k, typeof v === 'boolean' ? (v ? '1' : '0') : String(v ?? ''))
    })
    if (pickedFile.value)
      fd.append('flag', pickedFile.value)
    else if (removeFlag.value)
      fd.append('remove_flag', '1')

    if (editingId.value) {
      fd.append('_method', 'PATCH')

      return (await api.post(`/api/v1/dashboard/country-codes/${editingId.value}`, fd)).data
    }

    return (await api.post('/api/v1/dashboard/country-codes', fd)).data
  },
  onSuccess: () => {
    refresh()
    formOpen.value = false
  },
  onError: (e: any) => {
    const errors = e?.response?.data?.errors
    formError.value = errors
      ? Object.values(errors).flat().join(' — ')
      : (e?.response?.data?.message || 'فشل الحفظ. يرجى التحقق من المدخلات.')
  },
})

// ─── إخفاء / إعادة تفعيل ──────────────────────────────────────────────────
const hideTarget = ref<any>(null)
const hideError = ref('')

const hideMutation = useMutation({
  mutationFn: async () => api.delete(`/api/v1/dashboard/country-codes/${hideTarget.value.id}`),
  onSuccess: () => {
    refresh()
    hideTarget.value = null
  },
  onError: (e: any) => {
    hideError.value = e?.response?.data?.message || 'تعذّر الإخفاء.'
  },
})

const reactivateMutation = useMutation({
  mutationFn: async (row: any) => api.post(`/api/v1/dashboard/country-codes/${row.id}`, { _method: 'PATCH', is_active: 1 }),
  onSuccess: refresh,
})
</script>

<template>
  <div>
    <div class="mb-6">
      <h1 class="text-h4 font-weight-bold">
        مقدمات الدول وأعلامها
      </h1>
      <p class="text-body-2 text-medium-emphasis mb-0">
        هذه القائمة تظهر بجانب خانات الجوال (جوال المفوض والواتساب) في فورم المقاول، ويُفحص كل رقم
        على قاعدة دولته: عدد الأرقام بعد المقدمة وأول رقم مسموح. المقدمة الافتراضية تُختار تلقائياً،
        و"الحذف" يُخفي المقدمة من الخيارات فقط.
      </p>
    </div>

    <VCard>
      <VCardTitle class="d-flex justify-space-between align-center">
        <span>المقدمات</span>
        <VBtn
          v-if="$can('settings.country_codes', 'create')"
          size="small"
          color="primary"
          prepend-icon="tabler-plus"
          @click="openCreate"
        >
          إضافة مقدمة
        </VBtn>
      </VCardTitle>
      <VProgressLinear
        v-if="isLoading"
        indeterminate
        color="primary"
      />
      <div
        v-else
        class="table-wrap"
      >
        <VTable>
          <thead>
            <tr>
              <th>الدولة</th>
              <th>المقدمة</th>
              <th>قاعدة الرقم</th>
              <th>الحالة</th>
              <th class="text-center">
                إجراءات
              </th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="row in rows"
              :key="row.id"
            >
              <td>
                <div class="d-flex align-center gap-2">
                  <img
                    v-if="row.flag_url"
                    :src="row.flag_url"
                    :alt="row.name"
                    class="flag"
                  >
                  <span>{{ row.name }}</span>
                  <VChip
                    v-if="row.is_default"
                    size="x-small"
                    color="primary"
                    variant="tonal"
                  >
                    افتراضية
                  </VChip>
                </div>
              </td>
              <td dir="ltr" class="text-end">
                +{{ row.dial_code }}
              </td>
              <td>{{ describeRule(row) }}</td>
              <td>
                <VChip
                  size="small"
                  :color="row.is_active ? 'success' : 'default'"
                  variant="tonal"
                >
                  {{ row.is_active ? 'مفعّلة' : 'مخفية' }}
                </VChip>
              </td>
              <td class="text-center text-no-wrap">
                <VBtn
                  v-if="$can('settings.country_codes', 'update')"
                  icon="tabler-edit"
                  size="x-small"
                  variant="text"
                  @click="openEdit(row)"
                />
                <VBtn
                  v-if="row.is_active && !row.is_default && $can('settings.country_codes', 'delete')"
                  icon="tabler-trash"
                  size="x-small"
                  variant="text"
                  color="error"
                  @click="hideTarget = row; hideError = ''"
                />
                <VBtn
                  v-else-if="!row.is_active && $can('settings.country_codes', 'update')"
                  icon="tabler-refresh"
                  size="x-small"
                  variant="text"
                  color="success"
                  title="إعادة تفعيل"
                  @click="reactivateMutation.mutate(row)"
                />
              </td>
            </tr>
          </tbody>
        </VTable>
      </div>
    </VCard>

    <!-- فورم إضافة/تعديل -->
    <VDialog
      v-model="formOpen"
      max-width="520"
      persistent
    >
      <VCard>
        <VCardTitle class="pt-4 pb-0">
          <span class="text-h6">{{ editingId ? 'تعديل مقدمة' : 'إضافة مقدمة' }}</span>
        </VCardTitle>
        <VCardText class="pt-4">
          <VAlert
            v-if="formError"
            type="error"
            variant="tonal"
            class="mb-4"
          >
            {{ formError }}
          </VAlert>

          <VRow dense>
            <VCol
              cols="12"
              sm="7"
            >
              <VTextField
                v-model="form.name"
                label="اسم الدولة *"
              />
            </VCol>
            <VCol
              cols="12"
              sm="5"
            >
              <VTextField
                v-model="form.dial_code"
                label="المقدمة *"
                placeholder="970"
                prefix="+"
                dir="ltr"
                inputmode="numeric"
                maxlength="4"
              />
            </VCol>
            <VCol
              cols="6"
              sm="4"
            >
              <VTextField
                v-model="form.min_digits"
                type="number"
                label="أدنى عدد أرقام *"
                min="4"
                max="14"
              />
            </VCol>
            <VCol
              cols="6"
              sm="4"
            >
              <VTextField
                v-model="form.max_digits"
                type="number"
                label="أقصى عدد أرقام *"
                min="4"
                max="14"
              />
            </VCol>
            <VCol
              cols="12"
              sm="4"
            >
              <VTextField
                v-model="form.starts_with"
                label="يبدأ بـ"
                placeholder="5 أو 3,5,6"
                dir="ltr"
              />
            </VCol>
            <VCol cols="12">
              <p class="text-caption text-medium-emphasis mb-2">
                الرقم بعد المقدمة: {{ previewRule }}
              </p>
            </VCol>

            <VCol
              cols="12"
              sm="4"
            >
              <VTextField
                v-model="form.iso_code"
                label="رمز الدولة"
                placeholder="PS"
                dir="ltr"
                maxlength="2"
                hint="للعلم التلقائي"
                persistent-hint
              />
            </VCol>
            <VCol
              cols="12"
              sm="8"
            >
              <div class="d-flex align-center gap-3">
                <div class="flag-preview">
                  <img
                    v-if="previewFlag"
                    :src="previewFlag"
                    alt="العلم"
                  >
                  <VIcon
                    v-else
                    icon="tabler-flag"
                    color="disabled"
                  />
                </div>
                <VFileInput
                  v-model="flagFile"
                  label="رفع علم مخصص"
                  accept="image/png,image/jpeg,image/svg+xml,image/webp"
                  prepend-icon=""
                  prepend-inner-icon="tabler-upload"
                  density="compact"
                  hide-details
                  show-size
                />
              </div>
              <VCheckbox
                v-if="currentFlag && !pickedFile"
                v-model="removeFlag"
                label="حذف العلم المرفوع والرجوع للعلم التلقائي"
                density="compact"
                hide-details
              />
            </VCol>

            <VCol
              cols="6"
              sm="4"
            >
              <VTextField
                v-model="form.sort"
                type="number"
                label="الترتيب"
                min="0"
              />
            </VCol>
            <VCol
              cols="6"
              sm="8"
              class="d-flex align-center"
            >
              <VSwitch
                v-model="form.is_default"
                label="المقدمة الافتراضية"
                color="primary"
                hide-details
              />
            </VCol>
          </VRow>
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn
            variant="text"
            @click="formOpen = false"
          >
            إلغاء
          </VBtn>
          <VBtn
            color="primary"
            :loading="saveMutation.isPending.value"
            @click="saveMutation.mutate()"
          >
            حفظ
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- تأكيد الإخفاء -->
    <VDialog
      :model-value="!!hideTarget"
      max-width="420"
      @update:model-value="v => { if (!v) hideTarget = null }"
    >
      <VCard>
        <VCardTitle class="d-flex align-center gap-2">
          <VIcon
            icon="tabler-alert-triangle"
            color="error"
          />
          تأكيد الإخفاء
        </VCardTitle>
        <VCardText>
          <VAlert
            v-if="hideError"
            type="error"
            variant="tonal"
            class="mb-3"
          >
            {{ hideError }}
          </VAlert>
          هل تريد إخفاء مقدمة "{{ hideTarget?.name }} +{{ hideTarget?.dial_code }}"؟
          لن تظهر ضمن خيارات خانات الجوال، ولن تُقبل أرقام جديدة بها.
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn
            variant="tonal"
            @click="hideTarget = null"
          >
            إلغاء
          </VBtn>
          <VBtn
            color="error"
            :loading="hideMutation.isPending.value"
            @click="hideMutation.mutate()"
          >
            إخفاء
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>

<style scoped>
.table-wrap {
  overflow-x: auto;
}

.flag {
  border-radius: 2px;
  block-size: 16px;
  box-shadow: 0 0 0 1px rgba(0, 0, 0, 8%);
  inline-size: 24px;
  object-fit: cover;
}

.flag-preview {
  display: flex;
  flex: 0 0 48px;
  align-items: center;
  justify-content: center;
  border: 1px dashed rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 6px;
  block-size: 36px;
}

.flag-preview img {
  block-size: 24px;
  inline-size: 36px;
  object-fit: cover;
}
</style>

<script setup lang="ts">
import api from '@/plugins/axios'

/**
 * مراجعة وتعبئة جماعية لاسم الشركة وعنوانها بالإنجليزي (لشهادة العضوية الإنجليزية).
 * الخانات الفاضية بتتعبّى باقتراح تلقائي من السيرفر (ترجمة كلمات الشركات + نقل حرفي
 * للأسماء)، والأدمن بيراجع ويعدّل وبعدين بيحفظ. ما في شي بينحفظ قبل زر الحفظ.
 */
definePage({ meta: { requiresAdmin: true } })

interface Row {
  id: number
  membership_number: string | null
  status: string | null
  name: string
  address: string
  name_en: string | null
  address_en: string | null
  suggested_name_en: string | null
  suggested_address_en: string | null
}

interface EditRow extends Row {
  edit_name: string
  edit_address: string
  name_from_suggestion: boolean
  address_from_suggestion: boolean
}

const rows = ref<EditRow[]>([])
const loading = ref(false)
const saving = ref(false)
const loadError = ref('')
const saveError = ref('')
const saveMessage = ref('')
const search = ref('')
const filter = ref<'missing' | 'suggested' | 'all'>('missing')
const page = ref(1)
const perPage = 25

const NAME_RE = /^[A-Za-z0-9 .,&'()/\-]+$/
const ADDRESS_RE = /^[A-Za-z0-9 .,&'()/\-#]+$/

async function load() {
  loading.value = true
  loadError.value = ''
  try {
    const { data } = await api.get('/api/v1/dashboard/contractors/english-names')
    const items: Row[] = data.items?.items ?? []

    rows.value = items.map(r => ({
      ...r,
      edit_name: r.name_en || r.suggested_name_en || '',
      edit_address: r.address_en || r.suggested_address_en || '',
      name_from_suggestion: !r.name_en && !!r.suggested_name_en,
      address_from_suggestion: !r.address_en && !!r.suggested_address_en,
    }))
  }
  catch (e: any) {
    loadError.value = e?.response?.data?.message ?? 'تعذّر تحميل المقاولين.'
  }
  finally {
    loading.value = false
  }
}

onMounted(load)

const isChanged = (r: EditRow) =>
  (r.edit_name.trim() || null) !== (r.name_en || null)
  || (r.edit_address.trim() || null) !== (r.address_en || null)

const isInvalid = (r: EditRow) =>
  (!!r.edit_name.trim() && !NAME_RE.test(r.edit_name.trim()))
  || (!!r.edit_address.trim() && !ADDRESS_RE.test(r.edit_address.trim()))

const filtered = computed(() => {
  const q = search.value.trim().toLowerCase()

  return rows.value.filter(r => {
    if (filter.value === 'missing' && r.name_en && r.address_en)
      return false
    if (filter.value === 'suggested' && !r.name_from_suggestion && !r.address_from_suggestion)
      return false
    if (!q)
      return true

    return [r.name, r.membership_number, r.edit_name, r.address].some(v => (v ?? '').toLowerCase().includes(q))
  })
})

watch([search, filter], () => { page.value = 1 })

const pageCount = computed(() => Math.max(1, Math.ceil(filtered.value.length / perPage)))
const pageRows = computed(() => filtered.value.slice((page.value - 1) * perPage, page.value * perPage))

const stats = computed(() => ({
  total: rows.value.length,
  missingName: rows.value.filter(r => !r.name_en).length,
  missingAddress: rows.value.filter(r => !r.address_en).length,
  changed: rows.value.filter(isChanged).length,
  invalid: rows.value.filter(r => isChanged(r) && isInvalid(r)).length,
}))

function useSuggestion(r: EditRow) {
  if (r.suggested_name_en) {
    r.edit_name = r.suggested_name_en
    r.name_from_suggestion = true
  }
  if (r.suggested_address_en) {
    r.edit_address = r.suggested_address_en
    r.address_from_suggestion = true
  }
}

function revert(r: EditRow) {
  r.edit_name = r.name_en ?? ''
  r.edit_address = r.address_en ?? ''
  r.name_from_suggestion = false
  r.address_from_suggestion = false
}

async function saveAll() {
  const changed = rows.value.filter(isChanged)
  if (!changed.length || stats.value.invalid)
    return

  saving.value = true
  saveError.value = ''
  saveMessage.value = ''
  try {
    const { data } = await api.post('/api/v1/dashboard/contractors/english-names', {
      items: changed.map(r => ({
        id: r.id,
        name_en: r.edit_name.trim() || null,
        address_en: r.edit_address.trim() || null,
      })),
    })

    saveMessage.value = data.message ?? 'تم الحفظ.'
    await load()
  }
  catch (e: any) {
    saveError.value = e?.response?.data?.message ?? 'تعذّر الحفظ.'
  }
  finally {
    saving.value = false
  }
}
</script>

<template>
  <div>
    <div class="d-flex justify-space-between align-center flex-wrap gap-4 mb-4">
      <div>
        <h1 class="text-h4 font-weight-bold">
          الأسماء والعناوين بالإنجليزي
        </h1>
        <p class="text-body-2 text-medium-emphasis mb-0">
          للنسخة الإنجليزية من شهادة العضوية. الخانات الفاضية متعبّاية باقتراح تلقائي، راجعها وعدّل اللازم وبعدين احفظ.
        </p>
      </div>
      <VBtn
        v-if="$can('contractors.list', 'update')"
        color="primary"
        prepend-icon="tabler-device-floppy"
        :loading="saving"
        :disabled="!stats.changed || !!stats.invalid"
        @click="saveAll"
      >
        حفظ التغييرات ({{ stats.changed }})
      </VBtn>
    </div>

    <VAlert
      type="info"
      variant="tonal"
      density="compact"
      class="mb-4"
    >
      الاقتراح التلقائي بيترجم كلمات الشركات (للمقاولات ← Contracting، شركة ← Co.) وبيكتب أسماء العائلات بحروف إنجليزية،
      فممكن تحتاج تصحيح تهجئة بعض الأسماء. الصفوف المعلّمة "اقتراح" لسا ما انحفظت.
    </VAlert>

    <VAlert
      v-if="saveMessage"
      type="success"
      variant="tonal"
      density="compact"
      class="mb-4"
      closable
    >
      {{ saveMessage }}
    </VAlert>
    <VAlert
      v-if="saveError || loadError"
      type="error"
      variant="tonal"
      density="compact"
      class="mb-4"
    >
      {{ saveError || loadError }}
    </VAlert>
    <VAlert
      v-if="stats.invalid"
      type="warning"
      variant="tonal"
      density="compact"
      class="mb-4"
    >
      في {{ stats.invalid }} صف فيه حروف غير إنجليزية — صحّحه قبل الحفظ.
    </VAlert>

    <VCard>
      <VCardText class="d-flex flex-wrap align-center gap-3">
        <VBtnToggle
          v-model="filter"
          mandatory
          density="compact"
          variant="outlined"
          color="primary"
          divided
        >
          <VBtn value="missing">
            الناقص
          </VBtn>
          <VBtn value="suggested">
            اقتراحات غير محفوظة
          </VBtn>
          <VBtn value="all">
            الكل ({{ stats.total }})
          </VBtn>
        </VBtnToggle>
        <span class="text-body-2 text-medium-emphasis">
          بدون اسم إنجليزي: {{ stats.missingName }} · بدون عنوان إنجليزي: {{ stats.missingAddress }}
        </span>
        <VSpacer />
        <VTextField
          v-model="search"
          density="compact"
          placeholder="بحث بالاسم أو رقم العضوية"
          prepend-inner-icon="tabler-search"
          hide-details
          clearable
          style="max-inline-size: 280px;"
        />
      </VCardText>

      <VProgressLinear
        v-if="loading"
        indeterminate
        color="primary"
      />

      <VTable class="english-names-table">
        <thead>
          <tr>
            <th style="inline-size: 26%;">
              المقاول
            </th>
            <th style="inline-size: 32%;">
              الاسم بالإنجليزي
            </th>
            <th style="inline-size: 32%;">
              العنوان بالإنجليزي
            </th>
            <th class="text-center" />
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="r in pageRows"
            :key="r.id"
            :class="{ 'row-changed': isChanged(r) }"
          >
            <td class="py-2">
              <div class="font-weight-medium">
                {{ r.name }}
              </div>
              <div class="text-caption text-medium-emphasis">
                <bdi dir="ltr">{{ r.membership_number ?? '—' }}</bdi>
                <template v-if="r.address">
                  · {{ r.address }}
                </template>
              </div>
            </td>
            <td class="py-2">
              <VTextField
                v-model="r.edit_name"
                dir="ltr"
                density="compact"
                hide-details="auto"
                :rules="[(v: string) => !v || NAME_RE.test(v.trim()) || 'حروف إنجليزية فقط']"
                @update:model-value="r.name_from_suggestion = false"
              >
                <template
                  v-if="r.name_from_suggestion"
                  #append-inner
                >
                  <VChip
                    size="x-small"
                    color="warning"
                    variant="tonal"
                  >
                    اقتراح
                  </VChip>
                </template>
              </VTextField>
            </td>
            <td class="py-2">
              <VTextField
                v-model="r.edit_address"
                dir="ltr"
                density="compact"
                hide-details="auto"
                :rules="[(v: string) => !v || ADDRESS_RE.test(v.trim()) || 'حروف إنجليزية فقط']"
                @update:model-value="r.address_from_suggestion = false"
              >
                <template
                  v-if="r.address_from_suggestion"
                  #append-inner
                >
                  <VChip
                    size="x-small"
                    color="warning"
                    variant="tonal"
                  >
                    اقتراح
                  </VChip>
                </template>
              </VTextField>
            </td>
            <td class="text-center text-no-wrap">
              <VTooltip
                text="تعبئة بالاقتراح التلقائي"
                location="top"
              >
                <template #activator="{ props }">
                  <VBtn
                    v-bind="props"
                    icon="tabler-language"
                    size="x-small"
                    variant="text"
                    color="primary"
                    @click="useSuggestion(r)"
                  />
                </template>
              </VTooltip>
              <VTooltip
                v-if="isChanged(r)"
                text="تراجع للمحفوظ"
                location="top"
              >
                <template #activator="{ props }">
                  <VBtn
                    v-bind="props"
                    icon="tabler-arrow-back-up"
                    size="x-small"
                    variant="text"
                    @click="revert(r)"
                  />
                </template>
              </VTooltip>
            </td>
          </tr>
          <tr v-if="!loading && !pageRows.length">
            <td
              colspan="4"
              class="text-center text-medium-emphasis py-6"
            >
              ما في مقاولين بهالفلتر.
            </td>
          </tr>
        </tbody>
      </VTable>

      <VCardText
        v-if="pageCount > 1"
        class="d-flex justify-center"
      >
        <VPagination
          v-model="page"
          :length="pageCount"
          :total-visible="7"
          density="comfortable"
        />
      </VCardText>
    </VCard>
  </div>
</template>

<style scoped>
.row-changed {
  background: rgba(var(--v-theme-warning), 0.06);
}
</style>

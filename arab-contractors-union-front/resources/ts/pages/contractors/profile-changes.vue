<script setup lang="ts">
import api from '@/plugins/axios'
import { useAuthStore } from '@/stores/authStore'

definePage({ meta: { requiresAdmin: true, adminOnly: true } })

/**
 * طلبات تعديل الملفات — مراجعة تعديلات المقاولين على ملفاتهم من التطبيق (Maker-Checker).
 *   - بانتظار الاعتماد: بيانات قانونية ومستندات، ما بتصير رسمية إلا بعد الاعتماد.
 *   - مطبّقة للتأكيد: بيانات تواصل انطبّقت فوراً — تأكيد جماعي أو تراجع مع سبب.
 *   - منتهية: سجل القرارات.
 */

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()
const isAdmin = computed(() => authStore.userRole === 'admin')

type Tab = 'review' | 'applied' | 'done'
const tab = ref<Tab>((['review', 'applied', 'done'].includes(String(route.query.tab)) ? route.query.tab : 'review') as Tab)
const search = ref('')
const loading = ref(false)
const rows = ref<any[]>([])
const summary = ref<any>({ review_contractors: 0, review_fields: 0, applied: 0, overdue: 0, sla_working_days: 2, enabled: true })

const snack = ref({ show: false, text: '', color: 'success' })

const notify = (text: string, color = 'success') => {
  snack.value = { show: true, text, color }
}

const errorText = (e: any, fallback: string) => {
  const errs = e?.response?.data?.errors
  if (errs && typeof errs === 'object') {
    const first = Object.values(errs).flat()[0]
    if (first)
      return String(first)
  }

  return e?.response?.data?.message || fallback
}

const refreshBadge = () => window.dispatchEvent(new Event('profile-changes:refresh'))

// ─── تحميل ────────────────────────────────────────────────────────────────
const donePage = ref(1)
const doneTotal = ref(0)
const selected = ref<number[]>([])

const fetchSummary = async () => {
  try {
    const { data } = await api.get('/api/v1/dashboard/profile-changes/summary')

    summary.value = data.items
  }
  catch {}
}

const fetchRows = async () => {
  loading.value = true
  try {
    const { data } = await api.get('/api/v1/dashboard/profile-changes', {
      params: { tab: tab.value, search: search.value || undefined, page: tab.value === 'done' ? donePage.value : undefined },
    })

    rows.value = data.items?.data ?? []
    doneTotal.value = data.items?.meta?.total ?? rows.value.length
  }
  catch (e) {
    rows.value = []
    notify(errorText(e, 'تعذّر تحميل الطلبات'), 'error')
  }
  finally {
    loading.value = false
  }
}

const reload = async () => {
  selected.value = []
  await Promise.all([fetchRows(), fetchSummary()])
}

let searchTimer: ReturnType<typeof setTimeout> | null = null
watch(search, () => {
  if (searchTimer)
    clearTimeout(searchTimer)
  searchTimer = setTimeout(() => {
    donePage.value = 1
    fetchRows()
  }, 350)
})
watch(tab, val => {
  donePage.value = 1
  router.replace({ query: { ...route.query, tab: val, contractor: undefined } })
  reload()
})
watch(donePage, fetchRows)

// ─── التفعيل (أدمن) ───────────────────────────────────────────────────────
const toggling = ref(false)
const confirmToggle = ref(false)

const toggleEnabled = async () => {
  toggling.value = true
  try {
    const { data } = await api.put('/api/v1/dashboard/profile-changes/settings', { enabled: !summary.value.enabled })

    summary.value.enabled = data.items.enabled
    notify(data.message)
  }
  catch (e) {
    notify(errorText(e, 'تعذّر تغيير الإعداد'), 'error')
  }
  finally {
    toggling.value = false
    confirmToggle.value = false
  }
}

// ─── تواريخ ───────────────────────────────────────────────────────────────
const fmtDate = (iso?: string | null) => {
  if (!iso)
    return '—'

  return new Intl.DateTimeFormat('ar-EG-u-nu-latn', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(iso))
}

const ago = (iso?: string | null) => {
  if (!iso)
    return ''
  const mins = Math.round((Date.now() - new Date(iso).getTime()) / 60000)
  if (mins < 60)
    return `منذ ${Math.max(mins, 1)} دقيقة`
  const hours = Math.round(mins / 60)
  if (hours < 24)
    return `منذ ${hours} ساعة`

  return `منذ ${Math.round(hours / 24)} يوم`
}

// ─── الجداول ──────────────────────────────────────────────────────────────
const reviewHeaders = [
  { title: 'المقاول', key: 'contractor_name', sortable: false },
  { title: 'الحقول المعدّلة', key: 'labels', sortable: false },
  { title: 'تاريخ الطلب', key: 'submitted_at', sortable: false },
  { title: '', key: 'actions', sortable: false, align: 'end' as const },
]

const appliedHeaders = [
  { title: 'المقاول', key: 'contractor_name', sortable: false },
  { title: 'الحقل', key: 'label', sortable: false },
  { title: 'قبل ← بعد', key: 'diff', sortable: false },
  { title: 'التاريخ', key: 'submitted_at', sortable: false },
  { title: '', key: 'actions', sortable: false, align: 'end' as const },
]

const doneHeaders = [
  { title: 'المقاول', key: 'contractor_name', sortable: false },
  { title: 'الحقل', key: 'label', sortable: false },
  { title: 'قبل ← بعد', key: 'diff', sortable: false },
  { title: 'القرار', key: 'status', sortable: false },
  { title: 'بواسطة', key: 'reviewed_by', sortable: false },
]

const statusColor = (s: string) => ({ approved: 'success', confirmed: 'success', rejected: 'error', reverted: 'warning' } as Record<string, string>)[s] ?? 'secondary'

// ─── التأكيد الجماعي والتراجع (مطبّقة للتأكيد) ────────────────────────────
const confirming = ref(false)

const confirmSelected = async (ids: number[]) => {
  if (!ids.length)
    return
  confirming.value = true
  try {
    const { data } = await api.post('/api/v1/dashboard/profile-changes/confirm', { ids })

    notify(data.message)
    await reload()
  }
  catch (e) {
    notify(errorText(e, 'تعذّر التأكيد'), 'error')
  }
  finally {
    confirming.value = false
  }
}

const revertTarget = ref<any>(null)
const revertReason = ref('')
const reverting = ref(false)

const openRevert = (item: any) => {
  revertTarget.value = item
  revertReason.value = ''
}

const doRevert = async () => {
  if (!revertTarget.value || !revertReason.value.trim())
    return
  reverting.value = true
  try {
    const { data } = await api.post(`/api/v1/dashboard/profile-changes/${revertTarget.value.id}/revert`, { reason: revertReason.value.trim() })

    notify(data.message)
    revertTarget.value = null
    await reload()
  }
  catch (e) {
    notify(errorText(e, 'تعذّر التراجع'), 'error')
  }
  finally {
    reverting.value = false
  }
}

// ─── نافذة المراجعة (المقارنة جنب بعض) ────────────────────────────────────
const reviewOpen = ref(false)
const reviewLoading = ref(false)
const detail = ref<any>(null)
const decisions = ref<Record<number, { action: 'approve' | 'reject' | null; reason: string }>>({})
const submitting = ref(false)

const allItems = computed<any[]>(() => (detail.value?.groups ?? []).flatMap((g: any) => g.items))
const undecided = computed(() => allItems.value.filter(i => !decisions.value[i.id]?.action).length)
const missingReasons = computed(() => allItems.value.filter(i => decisions.value[i.id]?.action === 'reject' && !decisions.value[i.id]?.reason?.trim()).length)
const approveCount = computed(() => allItems.value.filter(i => decisions.value[i.id]?.action === 'approve').length)
const rejectCount = computed(() => allItems.value.filter(i => decisions.value[i.id]?.action === 'reject').length)

const openReview = async (contractorId: number) => {
  reviewOpen.value = true
  reviewLoading.value = true
  detail.value = null
  decisions.value = {}
  router.replace({ query: { ...route.query, contractor: String(contractorId) } })
  try {
    const { data } = await api.get(`/api/v1/dashboard/profile-changes/contractors/${contractorId}`)

    detail.value = data.items
    for (const item of allItems.value)
      decisions.value[item.id] = { action: null, reason: '' }
  }
  catch (e) {
    notify(errorText(e, 'تعذّر فتح الطلب'), 'error')
    reviewOpen.value = false
  }
  finally {
    reviewLoading.value = false
  }
}

watch(reviewOpen, open => {
  if (!open && route.query.contractor)
    router.replace({ query: { ...route.query, contractor: undefined } })
})

const approveAll = () => {
  for (const item of allItems.value)
    decisions.value[item.id] = { action: 'approve', reason: '' }
}

const submitDecisions = async () => {
  if (!detail.value || undecided.value || missingReasons.value)
    return
  submitting.value = true
  try {
    const payload = allItems.value.map(i => ({
      id: i.id,
      action: decisions.value[i.id].action,
      reason: decisions.value[i.id].action === 'reject' ? decisions.value[i.id].reason.trim() : null,
    }))

    const { data } = await api.post(`/api/v1/dashboard/profile-changes/contractors/${detail.value.contractor.id}/decide`, { decisions: payload })

    notify(data.message)
    reviewOpen.value = false
    refreshBadge()
    await reload()
  }
  catch (e) {
    notify(errorText(e, 'تعذّر حفظ القرار'), 'error')
  }
  finally {
    submitting.value = false
  }
}

// معاينة مستند
const preview = ref<{ url: string; title: string } | null>(null)
const isImage = (url: string) => /\.(?:png|jpe?g|webp|gif)$/i.test(url)
const isPdf = (url: string) => /\.pdf$/i.test(url)

const canDecide = computed(() => {
  const user = authStore.user as any
  if (user?.role !== 'supervisor')
    return true

  return (user?.permissions ?? []).includes('contractors.profile_changes.update')
})

onMounted(async () => {
  await reload()
  if (route.query.contractor && tab.value === 'review')
    openReview(Number(route.query.contractor))
})
</script>

<template>
  <div class="profile-changes-page">
    <!-- العنوان -->
    <div class="d-flex justify-space-between align-center flex-wrap gap-4 mb-6">
      <div>
        <h1 class="text-h4 font-weight-bold">
          طلبات تعديل الملفات
        </h1>
        <p class="text-body-2 text-medium-emphasis mb-0">
          تعديلات المقاولين على ملفاتهم من التطبيق. البيانات القانونية والمستندات ما بتصير رسمية إلا بعد اعتمادك.
        </p>
      </div>
      <div class="d-flex align-center gap-2 flex-wrap">
        <VChip
          v-if="!summary.enabled"
          color="warning"
          variant="tonal"
          label
          prepend-icon="tabler-alert-triangle"
        >
          المراجعة متوقفة: التعديلات تنحفظ مباشرة
        </VChip>
        <VBtn
          v-if="isAdmin"
          :color="summary.enabled ? 'secondary' : 'success'"
          variant="tonal"
          :prepend-icon="summary.enabled ? 'tabler-player-pause' : 'tabler-player-play'"
          @click="confirmToggle = true"
        >
          {{ summary.enabled ? 'إيقاف المراجعة' : 'تفعيل المراجعة' }}
        </VBtn>
        <VBtn
          variant="tonal"
          prepend-icon="tabler-refresh"
          :loading="loading"
          @click="reload"
        >
          تحديث
        </VBtn>
      </div>
    </div>

    <!-- بطاقات الملخص -->
    <VRow class="mb-2">
      <VCol
        cols="12"
        sm="4"
      >
        <VCard
          class="summary-card"
          @click="tab = 'review'"
        >
          <VCardText class="d-flex align-center gap-4">
            <VAvatar
              color="warning"
              variant="tonal"
              size="44"
              rounded="lg"
            >
              <VIcon icon="tabler-file-diff" />
            </VAvatar>
            <div>
              <div class="text-h5 font-weight-bold">
                {{ summary.review_contractors }}
              </div>
              <div class="text-body-2 text-medium-emphasis">
                مقاول بانتظار الاعتماد ({{ summary.review_fields }} حقل)
              </div>
            </div>
          </VCardText>
        </VCard>
      </VCol>
      <VCol
        cols="12"
        sm="4"
      >
        <VCard
          class="summary-card"
          @click="tab = 'review'"
        >
          <VCardText class="d-flex align-center gap-4">
            <VAvatar
              :color="summary.overdue ? 'error' : 'success'"
              variant="tonal"
              size="44"
              rounded="lg"
            >
              <VIcon icon="tabler-clock-exclamation" />
            </VAvatar>
            <div>
              <div
                class="text-h5 font-weight-bold"
                :class="{ 'text-error': summary.overdue }"
              >
                {{ summary.overdue }}
              </div>
              <div class="text-body-2 text-medium-emphasis">
                متأخر عن مهلة {{ summary.sla_working_days }} يوم عمل
              </div>
            </div>
          </VCardText>
        </VCard>
      </VCol>
      <VCol
        cols="12"
        sm="4"
      >
        <VCard
          class="summary-card"
          @click="tab = 'applied'"
        >
          <VCardText class="d-flex align-center gap-4">
            <VAvatar
              color="info"
              variant="tonal"
              size="44"
              rounded="lg"
            >
              <VIcon icon="tabler-address-book" />
            </VAvatar>
            <div>
              <div class="text-h5 font-weight-bold">
                {{ summary.applied }}
              </div>
              <div class="text-body-2 text-medium-emphasis">
                تعديل تواصل انطبّق وبانتظار التأكيد
              </div>
            </div>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <VCard>
      <VTabs
        v-model="tab"
        class="px-2"
      >
        <VTab value="review">
          بانتظار الاعتماد
          <VChip
            v-if="summary.review_contractors"
            size="x-small"
            color="warning"
            class="ms-2"
          >
            {{ summary.review_contractors }}
          </VChip>
        </VTab>
        <VTab value="applied">
          مطبّقة للتأكيد
          <VChip
            v-if="summary.applied"
            size="x-small"
            color="info"
            class="ms-2"
          >
            {{ summary.applied }}
          </VChip>
        </VTab>
        <VTab value="done">
          منتهية
        </VTab>
      </VTabs>
      <VDivider />

      <VCardText class="d-flex align-center gap-3 flex-wrap">
        <AppTextField
          v-model="search"
          placeholder="بحث باسم المقاول أو رقم العضوية"
          prepend-inner-icon="tabler-search"
          clearable
          style="max-inline-size: 360px;"
        />
        <VSpacer />
        <template v-if="tab === 'applied' && canDecide">
          <VBtn
            color="success"
            variant="tonal"
            prepend-icon="tabler-checks"
            :disabled="!selected.length"
            :loading="confirming"
            @click="confirmSelected(selected)"
          >
            تأكيد المحدد ({{ selected.length }})
          </VBtn>
        </template>
      </VCardText>

      <!-- بانتظار الاعتماد: سطر لكل مقاول -->
      <VDataTable
        v-if="tab === 'review'"
        :headers="reviewHeaders"
        :items="rows"
        :loading="loading"
        :items-per-page="20"
        mobile-breakpoint="md"
        item-value="contractor_id"
      >
        <template #item.contractor_name="{ item }">
          <div class="py-2">
            <div class="font-weight-medium">
              {{ item.contractor_name }}
            </div>
            <div class="text-caption text-medium-emphasis">
              عضوية {{ item.membership_number }}
            </div>
          </div>
        </template>
        <template #item.labels="{ item }">
          <div class="d-flex flex-wrap gap-1 py-2">
            <VChip
              v-for="label in item.labels.slice(0, 4)"
              :key="label"
              size="small"
              label
              variant="tonal"
            >
              {{ label }}
            </VChip>
            <VChip
              v-if="item.labels.length > 4"
              size="small"
              label
              variant="outlined"
            >
              +{{ item.labels.length - 4 }}
            </VChip>
          </div>
        </template>
        <template #item.submitted_at="{ item }">
          <div class="text-body-2">
            {{ ago(item.submitted_at) }}
          </div>
          <VChip
            v-if="item.overdue"
            size="x-small"
            color="error"
            label
            class="mt-1"
          >
            متأخر · {{ item.working_days }} يوم عمل
          </VChip>
        </template>
        <template #item.actions="{ item }">
          <VBtn
            size="small"
            color="primary"
            prepend-icon="tabler-eye-check"
            @click="openReview(item.contractor_id)"
          >
            مراجعة
          </VBtn>
        </template>
        <template #no-data>
          <div class="text-center pa-8 text-medium-emphasis">
            <VIcon
              icon="tabler-circle-check"
              size="40"
              color="success"
              class="mb-2"
            />
            <div>ما في تعديلات بانتظار الاعتماد</div>
          </div>
        </template>
      </VDataTable>

      <!-- مطبّقة للتأكيد -->
      <VDataTable
        v-else-if="tab === 'applied'"
        v-model="selected"
        :headers="appliedHeaders"
        :items="rows"
        :loading="loading"
        :items-per-page="25"
        :show-select="canDecide"
        item-value="id"
        mobile-breakpoint="md"
      >
        <template #item.contractor_name="{ item }">
          <div class="font-weight-medium">
            {{ item.contractor_name }}
          </div>
          <div class="text-caption text-medium-emphasis">
            عضوية {{ item.membership_number }}
          </div>
        </template>
        <template #item.diff="{ item }">
          <div class="diff-inline">
            <span class="text-medium-emphasis text-decoration-line-through">{{ item.old_display || 'فارغ' }}</span>
            <VIcon
              icon="tabler-arrow-left"
              size="14"
              class="mx-1"
            />
            <span class="font-weight-medium">{{ item.new_display || 'فارغ' }}</span>
          </div>
        </template>
        <template #item.submitted_at="{ item }">
          <span class="text-body-2">{{ ago(item.submitted_at) }}</span>
        </template>
        <template #item.actions="{ item }">
          <div
            v-if="canDecide"
            class="d-flex gap-1 justify-end"
          >
            <VBtn
              size="small"
              variant="text"
              color="success"
              @click="confirmSelected([item.id])"
            >
              تأكيد
            </VBtn>
            <VBtn
              size="small"
              variant="text"
              color="error"
              @click="openRevert(item)"
            >
              تراجع
            </VBtn>
          </div>
        </template>
        <template #no-data>
          <div class="text-center pa-8 text-medium-emphasis">
            ما في تعديلات تواصل بانتظار التأكيد
          </div>
        </template>
      </VDataTable>

      <!-- منتهية -->
      <template v-else>
        <VDataTable
          :headers="doneHeaders"
          :items="rows"
          :loading="loading"
          :items-per-page="-1"
          hide-default-footer
          mobile-breakpoint="md"
        >
          <template #item.contractor_name="{ item }">
            <div class="font-weight-medium">
              {{ item.contractor_name }}
            </div>
            <div class="text-caption text-medium-emphasis">
              عضوية {{ item.membership_number }}
            </div>
          </template>
          <template #item.diff="{ item }">
            <template v-if="item.kind === 'file'">
              <a
                v-if="item.new_file_url"
                :href="item.new_file_url"
                target="_blank"
                rel="noopener noreferrer"
              >المستند المرسل</a>
            </template>
            <div
              v-else
              class="diff-inline"
            >
              <span class="text-medium-emphasis">{{ item.old_display || 'فارغ' }}</span>
              <VIcon
                icon="tabler-arrow-left"
                size="14"
                class="mx-1"
              />
              <span class="font-weight-medium">{{ item.new_display || 'فارغ' }}</span>
            </div>
          </template>
          <template #item.status="{ item }">
            <VChip
              :color="statusColor(item.status)"
              size="small"
              label
            >
              {{ item.status_label }}
            </VChip>
            <div
              v-if="item.reject_reason"
              class="text-caption text-medium-emphasis mt-1"
            >
              {{ item.reject_reason }}
            </div>
          </template>
          <template #item.reviewed_by="{ item }">
            <div class="text-body-2">
              {{ item.reviewed_by || '—' }}
            </div>
            <div class="text-caption text-medium-emphasis">
              {{ fmtDate(item.reviewed_at) }}
            </div>
          </template>
          <template #no-data>
            <div class="text-center pa-8 text-medium-emphasis">
              لا يوجد سجل بعد
            </div>
          </template>
        </VDataTable>
        <div
          v-if="doneTotal > 20"
          class="d-flex justify-center pa-4"
        >
          <VPagination
            v-model="donePage"
            :length="Math.ceil(doneTotal / 20)"
            :total-visible="6"
            density="comfortable"
          />
        </div>
      </template>
    </VCard>

    <!-- ═══ نافذة المراجعة ═══ -->
    <VDialog
      v-model="reviewOpen"
      max-width="1100"
      scrollable
    >
      <VCard class="review-dialog">
        <div class="review-header d-flex align-center gap-3 pa-5">
          <VAvatar
            color="white"
            variant="flat"
            size="48"
          >
            <VIcon
              icon="tabler-file-diff"
              color="primary"
            />
          </VAvatar>
          <div
            class="flex-grow-1"
            style="min-inline-size: 0;"
          >
            <div class="text-h6 font-weight-bold text-white text-truncate">
              {{ detail?.contractor?.name || 'تحميل…' }}
            </div>
            <div
              v-if="detail"
              class="text-body-2 text-white"
              style="opacity: .85;"
            >
              عضوية {{ detail.contractor.membership_number }} · {{ allItems.length }} تعديل بانتظار القرار
            </div>
          </div>
          <VBtn
            icon="tabler-x"
            variant="tonal"
            color="white"
            size="small"
            @click="reviewOpen = false"
          />
        </div>

        <VCardText
          class="pa-5"
          style="max-block-size: 70vh;"
        >
          <div
            v-if="reviewLoading"
            class="d-flex justify-center pa-10"
          >
            <VProgressCircular
              indeterminate
              color="primary"
            />
          </div>

          <template v-else-if="detail">
            <VAlert
              v-if="!allItems.length"
              type="info"
              variant="tonal"
              class="mb-4"
            >
              ما في تعديلات بانتظار القرار — ممكن المقاول سحبها أو انحسمت.
            </VAlert>

            <div class="d-none d-md-flex compare-head mb-2">
              <div class="compare-label" />
              <div class="compare-col text-medium-emphasis">
                المعتمد حالياً
              </div>
              <div class="compare-col text-medium-emphasis">
                المطلوب من المقاول
              </div>
              <div class="compare-decision" />
            </div>

            <div
              v-for="group in detail.groups"
              :key="group.key"
              class="mb-5"
            >
              <div class="text-subtitle-1 font-weight-bold mb-2 d-flex align-center gap-2">
                <VIcon
                  :icon="group.key === 'documents' ? 'tabler-files' : 'tabler-building'"
                  size="18"
                  color="primary"
                />
                {{ group.label }}
              </div>

              <VCard
                v-for="item in group.items"
                :key="item.id"
                variant="outlined"
                class="compare-row mb-3"
                :class="{ 'is-approve': decisions[item.id]?.action === 'approve', 'is-reject': decisions[item.id]?.action === 'reject' }"
              >
                <div class="compare-grid pa-3">
                  <div class="compare-label">
                    <div class="font-weight-medium">
                      {{ item.label }}
                    </div>
                    <div class="text-caption text-medium-emphasis">
                      {{ ago(item.submitted_at) }}
                    </div>
                  </div>

                  <div class="compare-col old">
                    <div class="d-md-none text-caption text-medium-emphasis">
                      المعتمد حالياً
                    </div>
                    <template v-if="item.kind === 'file'">
                      <VBtn
                        v-if="item.old_file_url"
                        size="small"
                        variant="tonal"
                        prepend-icon="tabler-file"
                        @click="preview = { url: item.old_file_url, title: `${item.label} — الحالي` }"
                      >
                        عرض الحالي
                      </VBtn>
                      <span
                        v-else
                        class="text-medium-emphasis"
                      >لا يوجد مستند</span>
                    </template>
                    <span
                      v-else
                      class="value"
                    >{{ item.old_display || 'فارغ' }}</span>
                  </div>

                  <div class="compare-col new">
                    <div class="d-md-none text-caption text-medium-emphasis">
                      المطلوب
                    </div>
                    <template v-if="item.kind === 'file'">
                      <VBtn
                        v-if="item.new_file_url"
                        size="small"
                        color="primary"
                        variant="tonal"
                        prepend-icon="tabler-file-plus"
                        @click="preview = { url: item.new_file_url, title: `${item.label} — الجديد` }"
                      >
                        عرض الجديد
                      </VBtn>
                    </template>
                    <span
                      v-else
                      class="value font-weight-medium"
                    >{{ item.new_display || 'فارغ' }}</span>
                  </div>

                  <div
                    v-if="canDecide"
                    class="compare-decision"
                  >
                    <VBtnToggle
                      v-model="decisions[item.id].action"
                      density="comfortable"
                      divided
                      variant="outlined"
                      class="decision-toggle"
                    >
                      <VBtn
                        value="approve"
                        color="success"
                        size="small"
                        prepend-icon="tabler-check"
                      >
                        اعتماد
                      </VBtn>
                      <VBtn
                        value="reject"
                        color="error"
                        size="small"
                        prepend-icon="tabler-x"
                      >
                        رفض
                      </VBtn>
                    </VBtnToggle>
                  </div>
                </div>

                <VAlert
                  v-if="item.is_stale"
                  type="warning"
                  variant="tonal"
                  density="compact"
                  class="mx-3 mb-3"
                >
                  القيمة المعتمدة تغيّرت بعد ما المقاول بعت الطلب (من لوحة التحكم). راجع قبل الاعتماد.
                </VAlert>

                <div
                  v-if="decisions[item.id]?.action === 'reject'"
                  class="px-3 pb-3"
                >
                  <VCombobox
                    v-model="decisions[item.id].reason"
                    :items="detail.reject_reasons"
                    label="سبب الرفض (بيوصل للمقاول)"
                    density="compact"
                    :error="!decisions[item.id].reason?.trim()"
                    hide-details="auto"
                  />
                </div>
              </VCard>
            </div>

            <template v-if="detail.applied?.length">
              <VDivider class="my-4" />
              <div class="text-subtitle-2 font-weight-bold mb-2">
                تعديلات تواصل انطبّقت فوراً لنفس المقاول
              </div>
              <div
                v-for="a in detail.applied"
                :key="a.id"
                class="d-flex align-center gap-2 text-body-2 mb-1 flex-wrap"
              >
                <span class="font-weight-medium">{{ a.label }}:</span>
                <span class="text-medium-emphasis">{{ a.old_display || 'فارغ' }}</span>
                <VIcon
                  icon="tabler-arrow-left"
                  size="14"
                />
                <span>{{ a.new_display || 'فارغ' }}</span>
              </div>
            </template>

            <template v-if="detail.recent?.length">
              <VDivider class="my-4" />
              <div class="text-subtitle-2 font-weight-bold mb-2">
                آخر قرارات على ملف هذا المقاول
              </div>
              <div
                v-for="r in detail.recent"
                :key="r.id"
                class="d-flex align-center gap-2 text-body-2 mb-1 flex-wrap"
              >
                <VChip
                  :color="statusColor(r.status)"
                  size="x-small"
                  label
                >
                  {{ r.status_label }}
                </VChip>
                <span>{{ r.label }}</span>
                <span
                  v-if="r.reject_reason"
                  class="text-medium-emphasis"
                >— {{ r.reject_reason }}</span>
                <span class="text-caption text-medium-emphasis">{{ fmtDate(r.reviewed_at) }} · {{ r.reviewed_by }}</span>
              </div>
            </template>
          </template>
        </VCardText>

        <VDivider />
        <VCardActions
          v-if="detail && allItems.length && canDecide"
          class="pa-4 flex-wrap gap-2"
        >
          <VBtn
            variant="tonal"
            color="success"
            prepend-icon="tabler-checks"
            @click="approveAll"
          >
            اعتماد الكل
          </VBtn>
          <div class="text-body-2 text-medium-emphasis">
            <template v-if="undecided">
              باقي {{ undecided }} بدون قرار
            </template>
            <template v-else-if="missingReasons">
              اكتب سبب الرفض ({{ missingReasons }})
            </template>
            <template v-else>
              اعتماد {{ approveCount }} · رفض {{ rejectCount }}
            </template>
          </div>
          <VSpacer />
          <VBtn
            variant="text"
            @click="reviewOpen = false"
          >
            إلغاء
          </VBtn>
          <VBtn
            color="primary"
            :disabled="!!undecided || !!missingReasons"
            :loading="submitting"
            prepend-icon="tabler-send"
            @click="submitDecisions"
          >
            حفظ القرار وإبلاغ المقاول
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- معاينة مستند -->
    <VDialog
      :model-value="!!preview"
      max-width="900"
      @update:model-value="v => { if (!v) preview = null }"
    >
      <VCard v-if="preview">
        <VCardTitle class="d-flex align-center pa-4">
          <span class="text-truncate">{{ preview.title }}</span>
          <VSpacer />
          <VBtn
            :href="preview.url"
            target="_blank"
            rel="noopener noreferrer"
            variant="text"
            size="small"
            prepend-icon="tabler-external-link"
          >
            فتح بنافذة
          </VBtn>
          <VBtn
            icon="tabler-x"
            variant="text"
            size="small"
            @click="preview = null"
          />
        </VCardTitle>
        <VCardText class="pa-0">
          <img
            v-if="isImage(preview.url)"
            :src="preview.url"
            alt=""
            style="inline-size: 100%; max-block-size: 75vh; object-fit: contain;"
          >
          <iframe
            v-else-if="isPdf(preview.url)"
            :src="preview.url"
            style="inline-size: 100%; block-size: 75vh; border: 0;"
          />
          <div
            v-else
            class="pa-6 text-center"
          >
            <VBtn
              :href="preview.url"
              target="_blank"
              rel="noopener noreferrer"
              color="primary"
              prepend-icon="tabler-download"
            >
              تحميل المستند
            </VBtn>
          </div>
        </VCardText>
      </VCard>
    </VDialog>

    <!-- تراجع عن تعديل تواصل -->
    <VDialog
      :model-value="!!revertTarget"
      max-width="480"
      @update:model-value="v => { if (!v) revertTarget = null }"
    >
      <VCard
        v-if="revertTarget"
        class="pa-2"
      >
        <VCardTitle>التراجع عن تعديل {{ revertTarget.label }}</VCardTitle>
        <VCardText>
          <p class="text-body-2 mb-4">
            رح ترجع القيمة السابقة «{{ revertTarget.old_display || 'فارغ' }}» بدل «{{ revertTarget.new_display || 'فارغ' }}»، والمقاول {{ revertTarget.contractor_name }} بيوصله إشعار بالسبب.
          </p>
          <AppTextarea
            v-model="revertReason"
            label="سبب التراجع"
            rows="2"
            auto-grow
          />
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn
            variant="text"
            @click="revertTarget = null"
          >
            إلغاء
          </VBtn>
          <VBtn
            color="error"
            :disabled="!revertReason.trim()"
            :loading="reverting"
            @click="doRevert"
          >
            تراجع
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- تأكيد تشغيل/إيقاف المراجعة -->
    <VDialog
      v-model="confirmToggle"
      max-width="500"
    >
      <VCard class="pa-2">
        <VCardTitle>{{ summary.enabled ? 'إيقاف مراجعة تعديلات الملفات؟' : 'تفعيل مراجعة تعديلات الملفات؟' }}</VCardTitle>
        <VCardText class="text-body-2">
          <template v-if="summary.enabled">
            بعد الإيقاف، أي تعديل من التطبيق رح ينحفظ مباشرة بدون اعتماد (مع بقاء سجل التغييرات). الطلبات المعلّقة الحالية بتضل هون لحد ما تحسمها.
          </template>
          <template v-else>
            بعد التفعيل، تعديلات البيانات القانونية والمستندات من التطبيق رح تستنى اعتمادك، وبيانات التواصل بتنحفظ فوراً وبتظهر هون للتأكيد.
          </template>
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn
            variant="text"
            @click="confirmToggle = false"
          >
            إلغاء
          </VBtn>
          <VBtn
            :color="summary.enabled ? 'error' : 'success'"
            :loading="toggling"
            @click="toggleEnabled"
          >
            {{ summary.enabled ? 'إيقاف' : 'تفعيل' }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <VSnackbar
      v-model="snack.show"
      :timeout="4000"
      :color="snack.color"
      location="top"
    >
      {{ snack.text }}
    </VSnackbar>
  </div>
</template>

<style scoped>
.profile-changes-page {
  font-family: Cairo, sans-serif;
}

.summary-card {
  cursor: pointer;
  transition: transform 0.15s ease, box-shadow 0.15s ease;
}

.summary-card:hover {
  transform: translateY(-2px);
}

.review-header {
  background: linear-gradient(135deg, rgb(var(--v-theme-primary)) 0%, rgba(var(--v-theme-primary), 0.78) 100%);
}

.compare-grid,
.compare-head {
  display: grid;
  align-items: center;
  gap: 12px;
  grid-template-columns: 1.1fr 1.4fr 1.4fr auto;
}

.compare-col {
  min-inline-size: 0;
}

.compare-col .value {
  overflow-wrap: anywhere;
  white-space: pre-line;
}

.compare-col.old .value {
  color: rgba(var(--v-theme-on-surface), 0.6);
}

.compare-col.new {
  border-radius: 8px;
  background: rgba(var(--v-theme-primary), 0.06);
  padding-block: 6px;
  padding-inline: 10px;
}

.compare-row {
  transition: border-color 0.15s ease;
}

.compare-row.is-approve {
  border-color: rgb(var(--v-theme-success)) !important;
}

.compare-row.is-reject {
  border-color: rgb(var(--v-theme-error)) !important;
}

.diff-inline {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  overflow-wrap: anywhere;
}

@media (max-width: 959px) {
  .compare-grid {
    grid-template-columns: 1fr;
  }

  .compare-decision {
    justify-self: start;
  }
}
</style>

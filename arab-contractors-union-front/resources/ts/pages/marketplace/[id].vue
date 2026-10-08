<script setup lang="ts">
import api from '@/plugins/axios'
import { useRoute } from 'vue-router'

// صفحة معاينة الآلية (REQ-08 #19) — بيوصلها زر "معاينة" بعد الإضافة ومن قائمة الآليات
definePage({ meta: { requiresAdmin: true,
    adminOnly: true } })

const route = useRoute()
const id = computed(() => String((route.params as { id: string }).id))

const item     = ref<any>(null)
const loading  = ref(true)
const notFound = ref(false)
const activeImage = ref(0)

const fetchItem = async () => {
  loading.value = true
  try {
    const { data } = await api.get(`/api/v1/equipment/${id.value}`)
    item.value = data

    const cover = (data.images ?? []).findIndex((img: any) => img.is_primary)
    activeImage.value = cover === -1 ? 0 : cover
  }
  catch {
    notFound.value = true
  }
  finally {
    loading.value = false
  }
}

onMounted(fetchItem)

const images = computed(() => item.value?.images ?? [])

// نوع ملغى (معطَّل من شاشة الأنواع أو محذوف) بينعرض "غير محدد" بدل اسمه القديم أو فراغ (REQ-08 #21)
const typeLabel = computed(() => item.value?.type?.is_active ? item.value.type.name_ar : 'غير محدد')
const typeIsUndefined = computed(() => !item.value?.type?.is_active)

const statusMeta: Record<string, { color: string; label: string }> = {
  visible:   { color: 'success', label: 'ظاهر في السوق' },
  hidden:    { color: 'warning', label: 'مخفي (بقرار المالك)' },
  suspended: { color: 'error',   label: 'موقوف (بقرار الإدارة)' },
}

const conditionLabel: Record<string, string> = {
  excellent: 'ممتازة', good: 'جيدة', needs_maintenance: 'بحاجة صيانة',
}

const contractTypeLabel: Record<string, string> = {
  daily: 'تأجير يومي', weekly: 'تأجير أسبوعي', monthly: 'تأجير شهري',
}

const isScheduled = computed(() => !!item.value?.published_at && new Date(item.value.published_at) > new Date())

const formatDate = (value?: string | null) => value ? new Date(value).toLocaleDateString('ar-PS') : '—'

// سبب عدم ظهور الآلية بالسوق حالياً (إن وجد) — يوضّح للمشرف ليش ما بيشوفها المقاولين
const hiddenReason = computed(() => {
  const e = item.value
  if (!e) return ''
  if (e.status === 'hidden' || e.is_hidden) return 'الآلية مخفية بقرار المالك، وما بتظهر بالسوق.'
  if (e.status === 'suspended') return 'الآلية موقوفة بقرار الإدارة، وما بتظهر بالسوق.'
  if (e.needs_maintenance) return 'الآلية بحاجة صيانة، ومخفية من السوق مؤقتاً.'
  if (isScheduled.value) return `الآلية مجدولة للنشر، وبتظهر بالسوق بتاريخ ${formatDate(e.published_at)}.`

  return ''
})

const whatsappLink = computed(() => item.value?.owner_phone ? `https://wa.me/${String(item.value.owner_phone).replace(/\D+/g, '')}` : '')

const details = computed(() => {
  const e = item.value
  if (!e) return []

  return [
    { label: 'المالك (المقاول)', value: e.contractor?.name ?? '—', icon: 'tabler-user' },
    { label: 'نوع المعدة', value: typeLabel.value, icon: 'tabler-category', muted: typeIsUndefined.value },
    { label: 'الماركة', value: e.brand || '—', icon: 'tabler-badge-tm' },
    { label: 'سنة الصنع', value: e.manufacture_year || '—', icon: 'tabler-calendar' },
    { label: 'القدرة / الطاقة', value: e.power || '—', icon: 'tabler-bolt' },
    { label: 'حالة الآلية', value: conditionLabel[e.condition] ?? '—', icon: 'tabler-heartbeat' },
    { label: 'نوع العقد', value: contractTypeLabel[e.contract_type] ?? '—', icon: 'tabler-file-text' },
    { label: 'الموقع', value: [e.governorate, e.city].filter(Boolean).join(' — ') || '—', icon: 'tabler-map-pin' },
    { label: 'تاريخ النشر', value: e.published_at ? formatDate(e.published_at) : formatDate(e.created_at), icon: 'tabler-send' },
  ]
})
</script>

<template>
  <div>
    <!-- Header -->
    <div class="d-flex align-center justify-space-between flex-wrap gap-4 mb-6">
      <div>
        <h1 class="text-h4 font-weight-bold" style="font-family:Cairo,sans-serif">معاينة الآلية</h1>
        <p class="text-body-2 text-medium-emphasis mb-0" style="font-family:Cairo,sans-serif">
          عرض بيانات الآلية كما سُجّلت بسوق الآليات
        </p>
      </div>
      <div class="d-flex gap-2 flex-wrap">
        <VBtn
          v-if="item && $can('marketplace.equipment', 'update')"
          color="primary"
          prepend-icon="tabler-edit"
          :to="{ name: 'marketplace-create', query: { id: item.id } }"
          style="font-family:Cairo,sans-serif"
        >
          تعديل
        </VBtn>
        <VBtn variant="tonal" color="secondary" prepend-icon="tabler-arrow-right" :to="{ name: 'marketplace' }" style="font-family:Cairo,sans-serif">
          العودة للقائمة
        </VBtn>
      </div>
    </div>

    <VCard v-if="loading" loading>
      <VCardText class="pa-10 text-center text-medium-emphasis" style="font-family:Cairo,sans-serif">
        جاري التحميل…
      </VCardText>
    </VCard>

    <VCard v-else-if="notFound || !item">
      <VCardText class="pa-10 text-center" style="font-family:Cairo,sans-serif">
        <VIcon icon="tabler-alert-circle" size="40" color="error" class="mb-2" />
        <p class="mb-0">الآلية غير موجودة أو تم حذفها.</p>
      </VCardText>
    </VCard>

    <VRow v-else>
      <!-- الصور -->
      <VCol cols="12" md="6">
        <VCard>
          <div class="preview-main">
            <img v-if="images.length" :src="images[activeImage]?.url" :alt="item.name">
            <div v-else class="preview-main__empty">
              <VIcon icon="tabler-photo-off" size="48" />
              <span>لا توجد صور لهذه الآلية</span>
            </div>
            <span v-if="images[activeImage]?.is_primary" class="preview-main__badge">صورة الغلاف</span>
          </div>
          <div v-if="images.length > 1" class="preview-thumbs pa-3">
            <button
              v-for="(img, i) in images"
              :key="img.id"
              type="button"
              class="preview-thumb"
              :class="{ 'preview-thumb--active': i === activeImage }"
              @click="activeImage = i"
            >
              <img :src="img.url" :alt="`صورة ${i + 1}`">
            </button>
          </div>
        </VCard>
      </VCol>

      <!-- البيانات -->
      <VCol cols="12" md="6">
        <VCard>
          <VCardText>
            <div class="d-flex align-center flex-wrap gap-2 mb-2">
              <h2 class="text-h5 font-weight-bold mb-0" style="font-family:Cairo,sans-serif">{{ item.name }}</h2>
              <VChip v-if="item.is_featured" color="warning" size="small" label prepend-icon="tabler-star-filled" style="font-family:Cairo,sans-serif">
                مميزة
              </VChip>
            </div>

            <div class="d-flex flex-wrap gap-2 mb-4">
              <VChip :color="statusMeta[item.status]?.color ?? 'default'" size="small" label variant="tonal" style="font-family:Cairo,sans-serif">
                {{ statusMeta[item.status]?.label ?? item.status }}
              </VChip>
              <VChip v-if="isScheduled" color="info" size="small" label variant="tonal" prepend-icon="tabler-calendar-time" style="font-family:Cairo,sans-serif">
                مجدولة {{ formatDate(item.published_at) }}
              </VChip>
              <VChip v-if="item.needs_maintenance" color="error" size="small" label variant="tonal" prepend-icon="tabler-tool" style="font-family:Cairo,sans-serif">
                بحاجة صيانة
              </VChip>
            </div>

            <VAlert v-if="hiddenReason" type="warning" variant="tonal" density="compact" class="mb-4" style="font-family:Cairo,sans-serif">
              {{ hiddenReason }}
            </VAlert>

            <VList density="compact" class="pa-0">
              <VListItem v-for="row in details" :key="row.label" class="px-0">
                <template #prepend>
                  <VIcon :icon="row.icon" size="18" class="me-2" color="primary" />
                </template>
                <div class="d-flex justify-space-between gap-4" style="font-family:Cairo,sans-serif">
                  <span class="text-medium-emphasis">{{ row.label }}</span>
                  <span class="font-weight-medium text-end" :class="{ 'text-disabled': row.muted }">{{ row.value }}</span>
                </div>
              </VListItem>
              <VListItem class="px-0">
                <template #prepend>
                  <VIcon icon="tabler-brand-whatsapp" size="18" class="me-2" color="success" />
                </template>
                <div class="d-flex justify-space-between gap-4" style="font-family:Cairo,sans-serif">
                  <span class="text-medium-emphasis">واتساب المالك</span>
                  <a v-if="whatsappLink" :href="whatsappLink" target="_blank" rel="noopener" dir="ltr" class="font-weight-medium">
                    +{{ String(item.owner_phone).replace(/\D+/g, '') }}
                  </a>
                  <span v-else class="font-weight-medium">—</span>
                </div>
              </VListItem>
            </VList>
          </VCardText>
        </VCard>

        <VCard class="mt-4">
          <VCardText>
            <p class="text-subtitle-1 font-weight-bold mb-2" style="font-family:Cairo,sans-serif;color:#000269">
              <VIcon icon="tabler-file-description" size="18" class="me-1" />
              وصف الحالة الفنية للآلية
            </p>
            <p class="text-body-1 mb-0" style="font-family:Cairo,sans-serif;white-space:pre-line">
              {{ item.description || 'لا يوجد وصف.' }}
            </p>
          </VCardText>
        </VCard>

        <VCard v-if="item.admin_notes" class="mt-4">
          <VCardText>
            <p class="text-subtitle-1 font-weight-bold mb-2" style="font-family:Cairo,sans-serif;color:#000269">
              <VIcon icon="tabler-notes" size="18" class="me-1" />
              ملاحظات داخلية
            </p>
            <p class="text-body-2 mb-0" style="font-family:Cairo,sans-serif;white-space:pre-line">{{ item.admin_notes }}</p>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>
  </div>
</template>

<style scoped>
.preview-main {
  position: relative;
  aspect-ratio: 4 / 3;
  background: #f3f4f6;
}

.preview-main img {
  display: block;
  block-size: 100%;
  inline-size: 100%;
  object-fit: cover;
}

.preview-main__empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  block-size: 100%;
  color: #9ca3af;
  font-family: Cairo, sans-serif;
  gap: 8px;
}

.preview-main__badge {
  position: absolute;
  padding: 2px 10px;
  border-radius: 6px;
  background: #000269;
  color: #fff;
  font-family: Cairo, sans-serif;
  font-size: 12px;
  inset-block-start: 10px;
  inset-inline-start: 10px;
}

.preview-thumbs {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.preview-thumb {
  overflow: hidden;
  padding: 0;
  border: 2px solid transparent;
  border-radius: 8px;
  block-size: 60px;
  cursor: pointer;
  inline-size: 80px;
}

.preview-thumb--active {
  border-color: #000269;
}

.preview-thumb img {
  block-size: 100%;
  inline-size: 100%;
  object-fit: cover;
}
</style>

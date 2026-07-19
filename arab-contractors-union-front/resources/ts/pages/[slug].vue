<script setup lang="ts">
// صفحات slug ديناميكية (CMS) على المستوى الأعلى: pcu.org.ps/{slug}
// كما تعالج slug المعاينة السري لعرض الرئيسية الحقيقية أثناء وضع الصيانة.
definePage({
  meta: { layout: 'landing', public: true },
})

const route = useRoute()
const router = useRouter()

const BASE = import.meta.env.VITE_API_BASE_URL ?? ''

const loading = ref(true)
const notFound = ref(false)
const page = ref<{ title: string; content: string; updated_at?: string } | null>(null)

onMounted(async () => {
  // تطبيع الحالة — كيبوردات الجوال تكبّر أول حرف تلقائياً (Testing → testing)
  const slug = String(route.params.slug ?? '').trim().toLowerCase()

  // 1) هل هو slug المعاينة السري لوضع الصيانة؟
  try {
    const r = await fetch(`${BASE}/api/v1/app/maintenance/preview/${encodeURIComponent(slug)}`)
    const data = await r.json()
    if (data?.items?.valid) {
      localStorage.setItem('maintenance_preview', '1')
      router.replace('/landing')

      return
    }
  }
  catch {
    // نكمل لمحاولة صفحة CMS
  }

  // 2) صفحة CMS منشورة
  try {
    const r = await fetch(`${BASE}/api/v1/pages/${encodeURIComponent(slug)}`)
    if (!r.ok) {
      notFound.value = true

      return
    }
    const data = await r.json()
    page.value = data?.items ?? null
    notFound.value = !page.value
  }
  catch {
    notFound.value = true
  }
  finally {
    loading.value = false
  }
})
</script>

<template>
  <div
    class="cms-page"
    dir="rtl"
  >
    <VContainer class="py-12">
      <div
        v-if="loading && !notFound"
        class="text-center py-16"
      >
        <VProgressCircular
          indeterminate
          color="primary"
        />
      </div>

      <template v-else-if="page">
        <h1 class="text-h3 mb-6">
          {{ page.title }}
        </h1>
        <!-- المحتوى HTML من محرر لوحة الأدمن (جهة موثوقة) -->
        <div
          class="cms-content"
          v-html="page.content"
        />
      </template>

      <div
        v-else
        class="text-center py-16"
      >
        <VIcon
          icon="tabler-file-off"
          size="64"
          class="text-disabled"
        />
        <h2 class="text-h4 mt-4 mb-2">
          الصفحة غير موجودة
        </h2>
        <VBtn
          class="mt-4"
          to="/landing"
        >
          العودة للرئيسية
        </VBtn>
      </div>
    </VContainer>
  </div>
</template>

<style scoped>
.cms-content :deep(img) {
  max-inline-size: 100%;
}

.cms-content {
  line-height: 2;
}
</style>

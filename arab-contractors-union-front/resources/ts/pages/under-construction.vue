<script setup lang="ts">
definePage({
  meta: { layout: 'blank', public: true },
})

const BASE = import.meta.env.VITE_API_BASE_URL || 'http://127.0.0.1:8000'

const message = ref('الموقع قيد الإنشاء حالياً — نعود إليكم قريباً.')

onMounted(async () => {
  try {
    const r = await fetch(`${BASE}/api/v1/app/maintenance`)
    const data = await r.json()
    if (data?.items?.message)
      message.value = data.items.message
  }
  catch {
    // نبقي الرسالة الافتراضية
  }
})
</script>

<template>
  <div class="uc-wrap" dir="rtl">
    <div class="uc-card">
      <img
        src="/logo.png"
        alt="اتحاد المقاولين الفلسطينيين"
        class="uc-logo"
      >
      <VIcon
        icon="tabler-barrier-block"
        size="72"
        color="warning"
      />
      <h1 class="text-h4 mt-6 mb-3">
        الموقع قيد الإنشاء
      </h1>
      <p class="text-body-1 text-medium-emphasis">
        {{ message }}
      </p>
      <p class="text-body-2 text-disabled mt-8">
        اتحاد المقاولين الفلسطينيين — غزة
      </p>
    </div>
  </div>
</template>

<style scoped>
.uc-wrap {
  display: flex;
  align-items: center;
  justify-content: center;
  min-block-size: 100vh;
  padding: 24px;
}

.uc-card {
  max-inline-size: 480px;
  text-align: center;
}

.uc-logo {
  display: block;
  block-size: 96px;
  inline-size: auto;
  margin: 0 auto 24px;
  object-fit: contain;
}
</style>

<script setup lang="ts">
import { ref, watch } from 'vue'
import { useQuery, useMutation, useQueryClient } from '@tanstack/vue-query'
import api from '@/plugins/axios'

definePage({ meta: { requiresAdmin: true } })

const queryClient = useQueryClient()

interface SettingItem {
  key: string
  value: string | null
  group: string
}

// key => group لكل الإعدادات القابلة للتعديل من هذه الصفحة
const KEY_GROUPS: Record<string, string> = {
  union_name: 'about',
  union_name_en: 'about',
  union_about: 'about',
  union_address: 'about',
  union_phone: 'about',
  union_phone2: 'about',
  union_email: 'about',
  support_whatsapp: 'contact',
  support_email: 'contact',
  support_phone: 'contact',
  social_facebook: 'social',
  social_instagram: 'social',
  social_twitter: 'social',
  social_linkedin: 'social',
  social_youtube: 'social',
  social_website: 'social',
  maintenance_mode: 'maintenance',
  maintenance_preview_slug: 'maintenance',
  maintenance_message: 'maintenance',
  stat_years: 'stats',
  stat_projects: 'stats',
  stat_branches: 'stats',
}

const form = ref<Record<string, string>>(
  Object.fromEntries(Object.keys(KEY_GROUPS).map(k => [k, ''])),
)
const logoUrl = ref<string | null>(null)
const successMessage = ref('')
const errorMessage = ref('')

const { data, isLoading } = useQuery({
  queryKey: ['app-settings'],
  queryFn: async () => (await api.get('/api/v1/dashboard/settings')).data,
})

watch(data, (d: any) => {
  const settings: SettingItem[] = d?.items?.settings ?? []
  for (const s of settings) {
    if (s.key in KEY_GROUPS)
      form.value[s.key] = s.value ?? ''
    if (s.key === 'union_logo' && s.value)
      logoUrl.value = `${import.meta.env.VITE_API_BASE_URL || ''}/storage/${s.value}`
  }
}, { immediate: true })

const saveMutation = useMutation({
  mutationFn: async () => {
    const settings = Object.entries(form.value).map(([key, value]) => ({
      key,
      value,
      group: KEY_GROUPS[key],
    }))

    return (await api.put('/api/v1/dashboard/settings', { settings })).data
  },
  onSuccess: () => {
    queryClient.invalidateQueries({ queryKey: ['app-settings'] })
    successMessage.value = 'تم حفظ الإعدادات بنجاح.'
    errorMessage.value = ''
    setTimeout(() => successMessage.value = '', 4000)
  },
  onError: (e: any) => {
    errorMessage.value = e?.response?.data?.message || 'فشل حفظ الإعدادات.'
  },
})

// ─── رفع الشعار ───
const logoFile = ref<File[]>([])
const logoMutation = useMutation({
  mutationFn: async () => {
    const fd = new FormData()
    fd.append('logo', logoFile.value[0])

    return (await api.post('/api/v1/dashboard/settings/logo', fd)).data
  },
  onSuccess: (d: any) => {
    logoUrl.value = d?.items?.logo_url ?? logoUrl.value
    logoFile.value = []
    successMessage.value = 'تم رفع الشعار بنجاح.'
    setTimeout(() => successMessage.value = '', 4000)
  },
  onError: (e: any) => {
    errorMessage.value = e?.response?.data?.message || 'فشل رفع الشعار.'
  },
})
</script>

<template>
  <div>
    <div class="mb-6">
      <h1 class="text-h4 font-weight-bold">إعدادات التطبيق</h1>
      <p class="text-body-2 text-medium-emphasis mb-0">
        بيانات الاتحاد المعروضة في شاشة "عن الاتحاد"، بيانات التواصل، وروابط التواصل الاجتماعي
      </p>
    </div>

    <VProgressLinear v-if="isLoading" indeterminate color="primary" class="mb-4" />

    <VAlert v-if="successMessage" type="success" variant="tonal" class="mb-4">
      {{ successMessage }}
    </VAlert>
    <VAlert v-if="errorMessage" type="error" variant="tonal" class="mb-4">
      {{ errorMessage }}
    </VAlert>

    <!-- ─── بيانات الاتحاد (عن الاتحاد) ─── -->
    <VCard class="mb-6">
      <VCardTitle class="d-flex align-center gap-2 pt-4">
        <VIcon icon="tabler-building-bank" color="primary" />
        <span>بيانات الاتحاد — شاشة "عن الاتحاد"</span>
      </VCardTitle>
      <VCardText>
        <VRow>
          <VCol cols="12" md="3" class="text-center">
            <VAvatar :size="120" rounded="lg" color="secondary" variant="tonal" class="mb-3">
              <VImg v-if="logoUrl" :src="logoUrl" />
              <VIcon v-else icon="tabler-photo" size="48" />
            </VAvatar>
            <VFileInput
              v-model="logoFile"
              label="شعار الاتحاد"
              accept="image/*"
              density="compact"
              prepend-icon="tabler-upload"
            />
            <VBtn
              size="small"
              color="primary"
              variant="tonal"
              :disabled="!logoFile.length"
              :loading="logoMutation.isPending.value"
              @click="logoMutation.mutate()"
            >
              رفع الشعار
            </VBtn>
          </VCol>
          <VCol cols="12" md="9">
            <VRow>
              <VCol cols="12" md="6">
                <VTextField v-model="form.union_name" label="اسم الاتحاد (عربي)" dir="rtl" />
              </VCol>
              <VCol cols="12" md="6">
                <VTextField v-model="form.union_name_en" label="Union Name (English)" dir="ltr" />
              </VCol>
              <VCol cols="12">
                <VTextarea v-model="form.union_about" label="نبذة عن الاتحاد" rows="4" dir="rtl" />
              </VCol>
              <VCol cols="12" md="6">
                <VTextField v-model="form.union_address" label="العنوان الحالي" prepend-inner-icon="tabler-map-pin" />
              </VCol>
              <VCol cols="12" md="6">
                <VTextField v-model="form.union_email" label="البريد الإلكتروني" prepend-inner-icon="tabler-mail" dir="ltr" />
              </VCol>
              <VCol cols="12" md="6">
                <VTextField v-model="form.union_phone" label="رقم الهاتف" prepend-inner-icon="tabler-phone" dir="ltr" />
              </VCol>
              <VCol cols="12" md="6">
                <VTextField v-model="form.union_phone2" label="رقم هاتف إضافي" prepend-inner-icon="tabler-phone" dir="ltr" />
              </VCol>
            </VRow>
          </VCol>
        </VRow>
      </VCardText>
    </VCard>

    <!-- ─── بيانات التواصل والدعم ─── -->
    <VCard class="mb-6">
      <VCardTitle class="d-flex align-center gap-2 pt-4">
        <VIcon icon="tabler-headset" color="primary" />
        <span>التواصل والدعم الفني</span>
      </VCardTitle>
      <VCardText>
        <VRow>
          <VCol cols="12" md="4">
            <VTextField v-model="form.support_whatsapp" label="رقم واتساب الدعم" prepend-inner-icon="tabler-brand-whatsapp" dir="ltr" hint="مثال: +970599123456" persistent-hint />
          </VCol>
          <VCol cols="12" md="4">
            <VTextField v-model="form.support_email" label="بريد الدعم" prepend-inner-icon="tabler-mail" dir="ltr" />
          </VCol>
          <VCol cols="12" md="4">
            <VTextField v-model="form.support_phone" label="هاتف التواصل" prepend-inner-icon="tabler-phone" dir="ltr" />
          </VCol>
        </VRow>
      </VCardText>
    </VCard>

    <!-- ─── وضع الصيانة ─── -->
    <VCard class="mb-6">
      <VCardTitle class="d-flex align-center gap-2 pt-4">
        <VIcon icon="tabler-barrier-block" color="warning" />
        <span>وضع الصيانة — "الموقع قيد الإنشاء"</span>
      </VCardTitle>
      <VCardText>
        <VAlert type="info" variant="tonal" density="compact" class="mb-4">
          عند التفعيل يرى الزوار صفحة "قيد الإنشاء" على الصفحة الرئيسية فقط، بينما تبقى الصفحة الحقيقية
          متاحة على الرابط السري (مثال: /testing). صفحات المقاولين ولوحة التحكم تعمل كالمعتاد.
        </VAlert>
        <VRow>
          <VCol cols="12" md="3">
            <VSwitch
              :model-value="form.maintenance_mode === '1'"
              label="تفعيل وضع الصيانة"
              color="warning"
              @update:model-value="(v: any) => form.maintenance_mode = v ? '1' : '0'"
            />
          </VCol>
          <VCol cols="12" md="4">
            <VTextField
              v-model="form.maintenance_preview_slug"
              label="الرابط السري للمعاينة (slug)"
              prepend-inner-icon="tabler-key"
              dir="ltr"
              hint="مثال: testing → pcu.org.ps/testing"
              persistent-hint
            />
          </VCol>
          <VCol cols="12" md="5">
            <VTextField
              v-model="form.maintenance_message"
              label="رسالة قيد الإنشاء"
              dir="rtl"
            />
          </VCol>
        </VRow>
      </VCardText>
    </VCard>

    <!-- ─── أرقام الصفحة الرئيسية ─── -->
    <VCard class="mb-6">
      <VCardTitle class="d-flex align-center gap-2 pt-4">
        <VIcon icon="tabler-chart-bar" color="primary" />
        <span>أرقام الصفحة الرئيسية (الإحصائيات التسويقية)</span>
      </VCardTitle>
      <VCardText>
        <VRow>
          <VCol cols="12" md="4">
            <VTextField v-model="form.stat_years" label="سنوات الخبرة" type="number" dir="ltr" />
          </VCol>
          <VCol cols="12" md="4">
            <VTextField v-model="form.stat_projects" label="عدد المشاريع" type="number" dir="ltr" />
          </VCol>
          <VCol cols="12" md="4">
            <VTextField v-model="form.stat_branches" label="عدد الفروع" type="number" dir="ltr" />
          </VCol>
        </VRow>
      </VCardText>
    </VCard>

    <!-- ─── روابط التواصل الاجتماعي ─── -->
    <VCard class="mb-6">
      <VCardTitle class="d-flex align-center gap-2 pt-4">
        <VIcon icon="tabler-share" color="primary" />
        <span>روابط التواصل الاجتماعي</span>
      </VCardTitle>
      <VCardText>
        <VRow>
          <VCol cols="12" md="4">
            <VTextField v-model="form.social_facebook" label="فيسبوك" prepend-inner-icon="tabler-brand-facebook" dir="ltr" />
          </VCol>
          <VCol cols="12" md="4">
            <VTextField v-model="form.social_instagram" label="إنستغرام" prepend-inner-icon="tabler-brand-instagram" dir="ltr" />
          </VCol>
          <VCol cols="12" md="4">
            <VTextField v-model="form.social_twitter" label="X (تويتر)" prepend-inner-icon="tabler-brand-x" dir="ltr" />
          </VCol>
          <VCol cols="12" md="4">
            <VTextField v-model="form.social_linkedin" label="لينكدإن" prepend-inner-icon="tabler-brand-linkedin" dir="ltr" />
          </VCol>
          <VCol cols="12" md="4">
            <VTextField v-model="form.social_youtube" label="يوتيوب" prepend-inner-icon="tabler-brand-youtube" dir="ltr" />
          </VCol>
          <VCol cols="12" md="4">
            <VTextField v-model="form.social_website" label="الموقع الإلكتروني" prepend-inner-icon="tabler-world" dir="ltr" />
          </VCol>
        </VRow>
      </VCardText>
    </VCard>

    <div class="d-flex justify-end">
      <VBtn
        color="primary"
        size="large"
        prepend-icon="tabler-device-floppy"
        :loading="saveMutation.isPending.value"
        @click="saveMutation.mutate()"
      >
        حفظ الإعدادات
      </VBtn>
    </div>
  </div>
</template>

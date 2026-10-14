<script setup lang="ts">
import api from '@/plugins/axios'
import { useAuthStore } from '@/stores/authStore'
import { UNION_NAME, avatarUrl } from '@/utils/avatarUrl'

// الملف الشخصي لمستخدم لوحة التحكم (أدمن/محاسب/مشرف): تفاصيل الحساب للقراءة،
// تعديل الاسم والصورة، وتغيير كلمة السر بعد تأكيد الحالية.

interface Profile {
  id: number
  name: string
  email: string
  role: string
  role_label: string
  avatar: string | null
  is_active: boolean
  last_login_at: string | null
  created_at: string | null
  permissions_count: number | null
}

const AVATAR_MAX_MB = 2
const AVATAR_TYPES = ['image/jpeg', 'image/png', 'image/webp']

const authStore = useAuthStore()

const profile = ref<Profile | null>(null)
const isLoading = ref(true)
const loadError = ref('')

const snackbar = ref({ show: false, text: '', color: 'success' })
const notify = (text: string, color = 'success') => {
  snackbar.value = { show: true, text, color }
}

// ── الاسم والصورة ──
const name = ref('')
const avatarFile = ref<File | null>(null)
const avatarPreview = ref('')
const removeAvatar = ref(false)
const avatarError = ref('')
const fileInput = ref<HTMLInputElement | null>(null)
const isSaving = ref(false)
const infoErrors = ref<Record<string, string>>({})

const shownAvatar = computed(() => {
  if (avatarPreview.value)
    return avatarPreview.value
  if (removeAvatar.value)
    return ''

  return avatarUrl(profile.value?.avatar)
})

const infoDirty = computed(() =>
  !!profile.value && (name.value.trim() !== profile.value.name || !!avatarFile.value || removeAvatar.value),
)

const clearPreview = () => {
  if (avatarPreview.value)
    URL.revokeObjectURL(avatarPreview.value)
  avatarPreview.value = ''
}

const onFilePicked = (e: Event) => {
  const input = e.target as HTMLInputElement
  const file = input.files?.[0]
  input.value = ''
  avatarError.value = ''
  if (!file)
    return

  if (!AVATAR_TYPES.includes(file.type)) {
    avatarError.value = 'الصورة لازم تكون JPG أو PNG أو WEBP.'

    return
  }
  if (file.size > AVATAR_MAX_MB * 1024 * 1024) {
    avatarError.value = `حجم الصورة لازم ما يزيد عن ${AVATAR_MAX_MB} ميجابايت.`

    return
  }

  clearPreview()
  avatarFile.value = file
  avatarPreview.value = URL.createObjectURL(file)
  removeAvatar.value = false
}

const onRemoveAvatar = () => {
  clearPreview()
  avatarFile.value = null
  removeAvatar.value = true
  avatarError.value = ''
}

const resetInfo = () => {
  clearPreview()
  avatarFile.value = null
  removeAvatar.value = false
  avatarError.value = ''
  infoErrors.value = {}
  name.value = profile.value?.name ?? ''
}

const applyProfile = (p: Profile) => {
  profile.value = p
  resetInfo()
  // الشريط العلوي بيقرأ من الـstore → بيتحدّث فوراً
  authStore.patchUser({ name: p.name, avatar: p.avatar ?? undefined })
}

const firstErrors = (errors: Record<string, string[]> | undefined) =>
  Object.fromEntries(Object.entries(errors ?? {}).map(([k, v]) => [k, v?.[0] ?? '']))

const saveInfo = async () => {
  if (!name.value.trim()) {
    infoErrors.value = { name: 'الاسم مطلوب.' }

    return
  }
  isSaving.value = true
  infoErrors.value = {}
  try {
    const fd = new FormData()
    fd.append('name', name.value.trim())
    if (avatarFile.value)
      fd.append('avatar', avatarFile.value)
    else if (removeAvatar.value)
      fd.append('remove_avatar', '1')

    const res = await api.post('/api/v1/account/profile', fd, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })

    applyProfile(res.data.data)
    notify(res.data.message || 'تم حفظ الملف الشخصي.')
  }
  catch (e: any) {
    infoErrors.value = firstErrors(e?.response?.data?.errors)
    if (infoErrors.value.avatar)
      avatarError.value = infoErrors.value.avatar
    notify(e?.response?.data?.message || 'تعذّر حفظ الملف الشخصي.', 'error')
  }
  finally {
    isSaving.value = false
  }
}

// ── كلمة السر ──
const pw = ref({ current_password: '', password: '', password_confirmation: '' })
const pwShow = ref({ current: false, next: false, confirm: false })
const pwErrors = ref<Record<string, string>>({})
const isSavingPw = ref(false)

const pwLocalError = computed(() => {
  if (pw.value.password && pw.value.password.length < 8)
    return { password: 'كلمة السر الجديدة لازم تكون 8 أحرف على الأقل.' }
  if (pw.value.password_confirmation && pw.value.password !== pw.value.password_confirmation)
    return { password_confirmation: 'تأكيد كلمة السر غير مطابق.' }

  return {} as Record<string, string>
})

const pwReady = computed(() =>
  !!pw.value.current_password && pw.value.password.length >= 8 && pw.value.password === pw.value.password_confirmation,
)

const savePassword = async () => {
  if (!pwReady.value)
    return
  isSavingPw.value = true
  pwErrors.value = {}
  try {
    const res = await api.post('/api/v1/account/password', pw.value)

    pw.value = { current_password: '', password: '', password_confirmation: '' }
    notify(res.data.message || 'تم تغيير كلمة السر.')
  }
  catch (e: any) {
    pwErrors.value = firstErrors(e?.response?.data?.errors)
    notify(e?.response?.data?.message || 'تعذّر تغيير كلمة السر.', 'error')
  }
  finally {
    isSavingPw.value = false
  }
}

// ── عرض ──
const formatDateTime = (iso: string | null) => {
  if (!iso)
    return '—'

  return new Intl.DateTimeFormat('ar-EG-u-nu-latn', {
    dateStyle: 'medium',
    timeStyle: 'short',
    timeZone: 'Asia/Gaza',
  }).format(new Date(iso))
}

const details = computed(() => {
  const p = profile.value
  if (!p)
    return []

  const rows = [
    { icon: 'tabler-mail', label: 'البريد الإلكتروني', value: p.email },
    { icon: 'tabler-shield', label: 'الدور', value: p.role_label },
    { icon: 'tabler-login', label: 'آخر دخول', value: formatDateTime(p.last_login_at) },
    { icon: 'tabler-calendar', label: 'تاريخ إنشاء الحساب', value: formatDateTime(p.created_at) },
  ]
  if (p.permissions_count != null)
    rows.push({ icon: 'tabler-key', label: 'عدد الصلاحيات', value: String(p.permissions_count) })

  return rows
})

const displayName = computed(() => profile.value?.name || (profile.value?.role === 'admin' ? UNION_NAME : ''))

onMounted(async () => {
  try {
    const res = await api.get('/api/v1/account/profile')

    applyProfile(res.data.data)
  }
  catch (e: any) {
    loadError.value = e?.response?.data?.message || 'تعذّر تحميل الملف الشخصي.'
  }
  finally {
    isLoading.value = false
  }
})

onBeforeUnmount(clearPreview)
</script>

<template>
  <div class="account-profile">
    <div
      v-if="isLoading"
      class="d-flex justify-center py-12"
    >
      <VProgressCircular
        indeterminate
        color="primary"
      />
    </div>

    <VAlert
      v-else-if="loadError"
      type="error"
      variant="tonal"
    >
      {{ loadError }}
    </VAlert>

    <VRow v-else-if="profile">
      <!-- بطاقة الحساب -->
      <VCol
        cols="12"
        lg="4"
      >
        <VCard>
          <VCardText class="text-center pt-8">
            <VAvatar
              size="104"
              :color="shownAvatar ? undefined : 'primary'"
              variant="tonal"
              class="mb-4"
            >
              <VImg
                v-if="shownAvatar"
                :src="shownAvatar"
                cover
              />
              <VIcon
                v-else
                icon="tabler-user"
                size="48"
              />
            </VAvatar>
            <h5 class="text-h5 mb-1">
              {{ displayName }}
            </h5>
            <VChip
              color="primary"
              size="small"
              label
            >
              {{ profile.role_label }}
            </VChip>
          </VCardText>

          <VDivider />

          <VList density="compact">
            <VListItem
              v-for="row in details"
              :key="row.label"
            >
              <template #prepend>
                <VIcon
                  :icon="row.icon"
                  size="20"
                  class="me-2"
                />
              </template>
              <VListItemSubtitle>{{ row.label }}</VListItemSubtitle>
              <VListItemTitle
                class="font-weight-medium"
                style="white-space: normal; overflow-wrap: anywhere;"
              >
                {{ row.value }}
              </VListItemTitle>
            </VListItem>
          </VList>
        </VCard>
      </VCol>

      <VCol
        cols="12"
        lg="8"
      >
        <!-- الاسم والصورة -->
        <VCard
          title="البيانات الأساسية"
          class="mb-6"
        >
          <VCardText>
            <div class="d-flex align-center gap-4 flex-wrap mb-6">
              <VAvatar
                size="72"
                :color="shownAvatar ? undefined : 'primary'"
                variant="tonal"
              >
                <VImg
                  v-if="shownAvatar"
                  :src="shownAvatar"
                  cover
                />
                <VIcon
                  v-else
                  icon="tabler-user"
                  size="32"
                />
              </VAvatar>
              <div>
                <div class="d-flex gap-2 flex-wrap mb-1">
                  <VBtn
                    size="small"
                    prepend-icon="tabler-upload"
                    @click="fileInput?.click()"
                  >
                    تغيير الصورة
                  </VBtn>
                  <VBtn
                    v-if="shownAvatar"
                    size="small"
                    variant="tonal"
                    color="error"
                    prepend-icon="tabler-trash"
                    @click="onRemoveAvatar"
                  >
                    إزالة
                  </VBtn>
                </div>
                <div class="text-caption text-medium-emphasis">
                  JPG أو PNG أو WEBP، بحد أقصى {{ AVATAR_MAX_MB }} ميجابايت.
                </div>
                <div
                  v-if="avatarError"
                  class="text-caption text-error"
                >
                  {{ avatarError }}
                </div>
              </div>
              <input
                ref="fileInput"
                type="file"
                accept="image/png,image/jpeg,image/webp"
                class="d-none"
                @change="onFilePicked"
              >
            </div>

            <VRow>
              <VCol
                cols="12"
                md="6"
              >
                <AppTextField
                  v-model="name"
                  label="الاسم"
                  :error-messages="infoErrors.name"
                  maxlength="120"
                />
              </VCol>
              <VCol
                cols="12"
                md="6"
              >
                <AppTextField
                  :model-value="profile.email"
                  label="البريد الإلكتروني"
                  readonly
                  disabled
                  hint="البريد بيتغيّر من مدير النظام فقط"
                  persistent-hint
                />
              </VCol>
            </VRow>

            <div class="d-flex gap-3 mt-6 flex-wrap">
              <VBtn
                :loading="isSaving"
                :disabled="!infoDirty"
                @click="saveInfo"
              >
                حفظ التغييرات
              </VBtn>
              <VBtn
                v-if="infoDirty"
                variant="tonal"
                color="secondary"
                @click="resetInfo"
              >
                إلغاء
              </VBtn>
            </div>
          </VCardText>
        </VCard>

        <!-- كلمة السر -->
        <VCard title="تغيير كلمة السر">
          <VCardText>
            <VRow>
              <VCol cols="12">
                <AppTextField
                  v-model="pw.current_password"
                  label="كلمة السر الحالية"
                  autocomplete="current-password"
                  :type="pwShow.current ? 'text' : 'password'"
                  :append-inner-icon="pwShow.current ? 'tabler-eye-off' : 'tabler-eye'"
                  :error-messages="pwErrors.current_password"
                  @click:append-inner="pwShow.current = !pwShow.current"
                />
              </VCol>
              <VCol
                cols="12"
                md="6"
              >
                <AppTextField
                  v-model="pw.password"
                  label="كلمة السر الجديدة"
                  autocomplete="new-password"
                  :type="pwShow.next ? 'text' : 'password'"
                  :append-inner-icon="pwShow.next ? 'tabler-eye-off' : 'tabler-eye'"
                  :error-messages="pwLocalError.password || pwErrors.password"
                  @click:append-inner="pwShow.next = !pwShow.next"
                />
              </VCol>
              <VCol
                cols="12"
                md="6"
              >
                <AppTextField
                  v-model="pw.password_confirmation"
                  label="تأكيد كلمة السر الجديدة"
                  autocomplete="new-password"
                  :type="pwShow.confirm ? 'text' : 'password'"
                  :append-inner-icon="pwShow.confirm ? 'tabler-eye-off' : 'tabler-eye'"
                  :error-messages="pwLocalError.password_confirmation || pwErrors.password_confirmation"
                  @click:append-inner="pwShow.confirm = !pwShow.confirm"
                />
              </VCol>
            </VRow>
            <div class="text-caption text-medium-emphasis mt-3">
              8 أحرف على الأقل. بعد التغيير بيتم تسجيل خروجك من باقي الأجهزة، وهاد الجهاز بيضل داخل.
            </div>
            <VBtn
              class="mt-4"
              :loading="isSavingPw"
              :disabled="!pwReady"
              @click="savePassword"
            >
              تغيير كلمة السر
            </VBtn>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <VSnackbar
      v-model="snackbar.show"
      :color="snackbar.color"
      location="top"
      :timeout="3500"
    >
      {{ snackbar.text }}
    </VSnackbar>
  </div>
</template>

<style scoped>
.account-profile {
  font-family: Cairo, sans-serif;
}
</style>

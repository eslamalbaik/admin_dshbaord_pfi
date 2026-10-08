<script setup lang="ts">
import api from '@/plugins/axios'

/**
 * المشرفون والصلاحيات — أدمن فقط (utils/permissions.ts → ADMIN_ONLY_ROUTES، والباك إند role:admin).
 * إنشاء مشرف وإعطاؤه صلاحيات (عرض/إضافة/تعديل/حذف) على كل قسم داخلي من أقسام لوحة التحكم.
 */
definePage({ meta: { requiresAdmin: true, adminOnly: true } })

interface CatalogLeaf { key: string; label: string; actions: string[] }
interface CatalogSection { key: string; label: string; children: CatalogLeaf[] }
interface Supervisor {
  id: number
  name: string
  email: string
  phone: string | null
  is_active: boolean
  permissions: string[]
  created_at: string
}

// ─── State ─────────────────────────────────────────────────────────────────
const supervisors = ref<Supervisor[]>([])
const loading = ref(false)
const search = ref('')
const statusFilter = ref<string | null>(null)

const actions = ref<{ key: string; label: string }[]>([])
const sections = ref<CatalogSection[]>([])

const snackbar = ref({ show: false, text: '', color: 'success' })

const notify = (text: string, color = 'success') => {
  snackbar.value = { show: true, text, color }
}

const errorMessage = (e: any, fallback: string) => {
  const errors = e?.response?.data?.errors
  if (errors)
    return Object.values(errors).flat().join(' — ')

  return e?.response?.data?.message || fallback
}

// ─── Load ───────────────────────────────────────────────────────────────────
const fetchSupervisors = async () => {
  loading.value = true
  try {
    const { data } = await api.get('/api/v1/dashboard/supervisors', {
      params: { search: search.value || undefined, status: statusFilter.value || undefined, per_page: 100 },
    })

    supervisors.value = data.items ?? []
  }
  catch (e: any) {
    notify(errorMessage(e, 'تعذّر تحميل المشرفين'), 'error')
  }
  finally {
    loading.value = false
  }
}

const fetchCatalog = async () => {
  const { data } = await api.get('/api/v1/dashboard/supervisors/permissions-catalog')

  actions.value = data.items?.actions ?? []
  sections.value = data.items?.sections ?? []
}

onMounted(() => {
  fetchCatalog()
  fetchSupervisors()
})

watchDebounced([search, statusFilter], fetchSupervisors, { debounce: 350 })

const allLeaves = computed(() => sections.value.flatMap(s => s.children))
const leafLabel = (key: string) => allLeaves.value.find(l => l.key === key)?.label ?? key

/** ملخّص الأقسام المسموحة لعرضه بالجدول */
const summary = (permissions: string[]) => {
  const leaves = new Set(permissions.map(p => p.slice(0, p.lastIndexOf('.'))))

  return [...leaves].map(leafLabel)
}

// ─── Create / Edit ───────────────────────────────────────────────────────────
const dialog = ref(false)
const saving = ref(false)
const editing = ref<Supervisor | null>(null)
const showPassword = ref(false)

const emptyForm = () => ({ name: '', email: '', phone: '', password: '', is_active: true })
const form = ref(emptyForm())
const selected = ref<Set<string>>(new Set())

const openCreate = () => {
  editing.value = null
  form.value = emptyForm()
  selected.value = new Set()
  dialog.value = true
}

const openEdit = (sup: Supervisor) => {
  editing.value = sup
  form.value = { name: sup.name, email: sup.email, phone: sup.phone ?? '', password: '', is_active: sup.is_active }
  selected.value = new Set(sup.permissions)
  dialog.value = true
}

const key = (leaf: string, action: string) => `${leaf}.${action}`
const has = (leaf: string, action: string) => selected.value.has(key(leaf, action))

/** تغيير خانة: أي إجراء بدون "عرض" بيضيف العرض، وإلغاء "العرض" بيلغي كل إجراءات القسم */
const toggle = (leaf: CatalogLeaf, action: string, value: boolean) => {
  const next = new Set(selected.value)
  if (value) {
    next.add(key(leaf.key, action))
    if (leaf.actions.includes('view'))
      next.add(key(leaf.key, 'view'))
  }
  else if (action === 'view') {
    leaf.actions.forEach(a => next.delete(key(leaf.key, a)))
  }
  else {
    next.delete(key(leaf.key, action))
  }
  selected.value = next
}

const leafState = (leaf: CatalogLeaf) => {
  const count = leaf.actions.filter(a => has(leaf.key, a)).length

  return { all: count === leaf.actions.length, some: count > 0 && count < leaf.actions.length }
}

const toggleLeaf = (leaf: CatalogLeaf, value: boolean) => {
  const next = new Set(selected.value)

  leaf.actions.forEach(a => value ? next.add(key(leaf.key, a)) : next.delete(key(leaf.key, a)))
  selected.value = next
}

/** عمود إجراء على مستوى القسم الرئيسي (كل الأقسام الداخلية اللي فيها هالإجراء) */
const sectionActionState = (section: CatalogSection, action: string) => {
  const leaves = section.children.filter(l => l.actions.includes(action))
  const count = leaves.filter(l => has(l.key, action)).length

  return { available: leaves.length > 0, all: leaves.length > 0 && count === leaves.length, some: count > 0 && count < leaves.length }
}

const toggleSectionAction = (section: CatalogSection, action: string, value: boolean) => {
  section.children.filter(l => l.actions.includes(action)).forEach(l => toggle(l, action, value))
}

const sectionState = (section: CatalogSection) => {
  const total = section.children.reduce((n, l) => n + l.actions.length, 0)
  const count = section.children.reduce((n, l) => n + l.actions.filter(a => has(l.key, a)).length, 0)

  return { all: count === total, some: count > 0 && count < total, count }
}

const toggleSection = (section: CatalogSection, value: boolean) => {
  section.children.forEach(l => toggleLeaf(l, value))
}

const selectAll = (value: boolean) => sections.value.forEach(s => toggleSection(s, value))

const generatePassword = () => {
  const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789'
  const bytes = crypto.getRandomValues(new Uint32Array(12))

  return Array.from(bytes, b => chars[b % chars.length]).join('')
}

const save = async () => {
  saving.value = true
  try {
    const payload: Record<string, any> = {
      name: form.value.name,
      email: form.value.email,
      phone: form.value.phone || null,
      permissions: [...selected.value],
    }

    if (editing.value) {
      await api.put(`/api/v1/dashboard/supervisors/${editing.value.id}`, payload)
      notify('تم حفظ بيانات المشرف وصلاحياته')
    }
    else {
      await api.post('/api/v1/dashboard/supervisors', { ...payload, password: form.value.password, is_active: form.value.is_active })
      notify('تم إنشاء المشرف بنجاح')
    }
    dialog.value = false
    fetchSupervisors()
  }
  catch (e: any) {
    notify(errorMessage(e, 'تعذّر حفظ المشرف'), 'error')
  }
  finally {
    saving.value = false
  }
}

// ─── Activate / Deactivate ───────────────────────────────────────────────────
const statusDialog = ref(false)
const statusTarget = ref<Supervisor | null>(null)
const statusSaving = ref(false)

const askToggleStatus = (sup: Supervisor) => {
  statusTarget.value = sup
  statusDialog.value = true
}

const confirmToggleStatus = async () => {
  if (!statusTarget.value)
    return
  statusSaving.value = true
  try {
    const { data } = await api.patch(`/api/v1/dashboard/supervisors/${statusTarget.value.id}/status`, {
      is_active: !statusTarget.value.is_active,
    })

    notify(data.message)
    statusDialog.value = false
    fetchSupervisors()
  }
  catch (e: any) {
    notify(errorMessage(e, 'تعذّر تغيير الحالة'), 'error')
  }
  finally {
    statusSaving.value = false
  }
}

// ─── Reset password ──────────────────────────────────────────────────────────
const passwordDialog = ref(false)
const passwordTarget = ref<Supervisor | null>(null)
const newPassword = ref('')
const passwordSaving = ref(false)

const openResetPassword = (sup: Supervisor) => {
  passwordTarget.value = sup
  newPassword.value = generatePassword()
  passwordDialog.value = true
}

const confirmResetPassword = async () => {
  if (!passwordTarget.value)
    return
  passwordSaving.value = true
  try {
    const { data } = await api.post(`/api/v1/dashboard/supervisors/${passwordTarget.value.id}/reset-password`, {
      password: newPassword.value,
    })

    notify(data.message)
    passwordDialog.value = false
  }
  catch (e: any) {
    notify(errorMessage(e, 'تعذّر تعيين كلمة المرور'), 'error')
  }
  finally {
    passwordSaving.value = false
  }
}

const copy = (text: string) => {
  navigator.clipboard?.writeText(text)
  notify('تم النسخ')
}

const passwordRules = [(v: string) => (v?.length ?? 0) >= 8 || 'كلمة المرور 8 أحرف على الأقل']
const requiredRule = [(v: string) => !!v?.trim() || 'هذا الحقل مطلوب']
</script>

<template>
  <div class="supervisors-page">
    <!-- Header -->
    <div class="d-flex align-center justify-space-between flex-wrap gap-4 mb-6">
      <div>
        <h1 class="text-h4 font-weight-bold">
          المشرفون والصلاحيات
        </h1>
        <p class="text-body-2 text-medium-emphasis mb-0">
          أنشئ حسابات مشرفين وحدّد لكل مشرف الأقسام اللي بيقدر يشوفها ويعدّل عليها
        </p>
      </div>
      <VBtn
        color="primary"
        prepend-icon="tabler-user-plus"
        @click="openCreate"
      >
        إضافة مشرف
      </VBtn>
    </div>

    <!-- Filters -->
    <VCard class="mb-4">
      <VCardText>
        <VRow>
          <VCol
            cols="12"
            md="8"
          >
            <VTextField
              v-model="search"
              placeholder="بحث بالاسم أو البريد"
              prepend-inner-icon="tabler-search"
              density="compact"
              clearable
              hide-details
            />
          </VCol>
          <VCol
            cols="12"
            md="4"
          >
            <VSelect
              v-model="statusFilter"
              :items="[{ title: 'فعال', value: 'active' }, { title: 'معطل', value: 'inactive' }]"
              placeholder="كل الحالات"
              density="compact"
              clearable
              hide-details
            />
          </VCol>
        </VRow>
      </VCardText>
    </VCard>

    <!-- Table -->
    <VCard :loading="loading">
      <div class="table-scroll">
        <VTable>
          <thead>
            <tr>
              <th>المشرف</th>
              <th>الهاتف</th>
              <th>الأقسام المسموحة</th>
              <th>الحالة</th>
              <th class="text-center">
                الإجراءات
              </th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!loading && supervisors.length === 0">
              <td
                colspan="5"
                class="text-center pa-8 text-medium-emphasis"
              >
                لا يوجد مشرفون بعد. اضغط «إضافة مشرف» لإنشاء أول حساب.
              </td>
            </tr>
            <tr
              v-for="sup in supervisors"
              :key="sup.id"
            >
              <td>
                <div class="font-weight-medium">
                  {{ sup.name }}
                </div>
                <div
                  class="text-caption text-medium-emphasis"
                  dir="ltr"
                  style="text-align:end"
                >
                  {{ sup.email }}
                </div>
              </td>
              <td dir="ltr">
                {{ sup.phone || '—' }}
              </td>
              <td style="max-width:360px">
                <template v-if="sup.permissions.length">
                  <VChip
                    v-for="label in summary(sup.permissions).slice(0, 4)"
                    :key="label"
                    size="small"
                    class="me-1 mb-1"
                    color="primary"
                    variant="tonal"
                  >
                    {{ label }}
                  </VChip>
                  <VChip
                    v-if="summary(sup.permissions).length > 4"
                    size="small"
                    class="mb-1"
                    variant="outlined"
                  >
                    +{{ summary(sup.permissions).length - 4 }}
                  </VChip>
                </template>
                <span
                  v-else
                  class="text-medium-emphasis"
                >بدون صلاحيات</span>
              </td>
              <td>
                <VChip
                  :color="sup.is_active ? 'success' : 'secondary'"
                  size="small"
                  label
                >
                  {{ sup.is_active ? 'فعال' : 'معطل' }}
                </VChip>
              </td>
              <td>
                <div class="d-flex gap-2 justify-center">
                  <VBtn
                    icon
                    size="small"
                    variant="tonal"
                    color="primary"
                    @click="openEdit(sup)"
                  >
                    <VIcon
                      icon="tabler-shield-check"
                      size="18"
                    />
                    <VTooltip activator="parent">
                      تعديل البيانات والصلاحيات
                    </VTooltip>
                  </VBtn>
                  <VBtn
                    icon
                    size="small"
                    variant="tonal"
                    color="info"
                    @click="openResetPassword(sup)"
                  >
                    <VIcon
                      icon="tabler-key"
                      size="18"
                    />
                    <VTooltip activator="parent">
                      إعادة تعيين كلمة المرور
                    </VTooltip>
                  </VBtn>
                  <VBtn
                    icon
                    size="small"
                    variant="tonal"
                    :color="sup.is_active ? 'error' : 'success'"
                    @click="askToggleStatus(sup)"
                  >
                    <VIcon
                      :icon="sup.is_active ? 'tabler-lock' : 'tabler-lock-open'"
                      size="18"
                    />
                    <VTooltip activator="parent">
                      {{ sup.is_active ? 'تعطيل الحساب' : 'تفعيل الحساب' }}
                    </VTooltip>
                  </VBtn>
                </div>
              </td>
            </tr>
          </tbody>
        </VTable>
      </div>
    </VCard>

    <!-- Create / Edit dialog -->
    <VDialog
      v-model="dialog"
      max-width="980"
      scrollable
    >
      <VCard>
        <VCardTitle class="pa-6 pb-2">
          {{ editing ? `تعديل المشرف: ${editing.name}` : 'إضافة مشرف جديد' }}
        </VCardTitle>
        <VCardText class="pt-2">
          <h6 class="text-h6 mb-3">
            بيانات الحساب
          </h6>
          <VRow>
            <VCol
              cols="12"
              md="6"
            >
              <VTextField
                v-model="form.name"
                label="الاسم *"
                :rules="requiredRule"
                density="compact"
              />
            </VCol>
            <VCol
              cols="12"
              md="6"
            >
              <VTextField
                v-model="form.email"
                label="البريد الإلكتروني (للدخول) *"
                type="email"
                dir="ltr"
                :rules="requiredRule"
                density="compact"
              />
            </VCol>
            <VCol
              cols="12"
              md="6"
            >
              <VTextField
                v-model="form.phone"
                label="رقم الهاتف"
                dir="ltr"
                density="compact"
              />
            </VCol>
            <VCol
              v-if="!editing"
              cols="12"
              md="6"
            >
              <VTextField
                v-model="form.password"
                label="كلمة المرور *"
                :type="showPassword ? 'text' : 'password'"
                dir="ltr"
                :rules="passwordRules"
                density="compact"
                :append-inner-icon="showPassword ? 'tabler-eye-off' : 'tabler-eye'"
                @click:append-inner="showPassword = !showPassword"
              >
                <template #append>
                  <VBtn
                    size="small"
                    variant="tonal"
                    @click="form.password = generatePassword(); showPassword = true"
                  >
                    توليد
                  </VBtn>
                </template>
              </VTextField>
            </VCol>
          </VRow>

          <div class="d-flex align-center justify-space-between flex-wrap gap-2 mt-6 mb-3">
            <div>
              <h6 class="text-h6">
                الصلاحيات حسب الأقسام
              </h6>
              <p class="text-caption text-medium-emphasis mb-0">
                أي صلاحية إضافة/تعديل/حذف بتعطي «عرض» القسم تلقائياً. إدارة المشرفين نفسها للأدمن فقط.
              </p>
            </div>
            <div class="d-flex gap-2">
              <VBtn
                size="small"
                variant="tonal"
                @click="selectAll(true)"
              >
                تحديد الكل
              </VBtn>
              <VBtn
                size="small"
                variant="text"
                color="secondary"
                @click="selectAll(false)"
              >
                إلغاء الكل
              </VBtn>
            </div>
          </div>

          <div class="table-scroll matrix-wrap">
            <VTable
              density="compact"
              class="permission-matrix"
            >
              <thead>
                <tr>
                  <th style="min-width:220px">
                    القسم
                  </th>
                  <th
                    v-for="a in actions"
                    :key="a.key"
                    class="text-center"
                  >
                    {{ a.label }}
                  </th>
                  <th class="text-center">
                    الكل
                  </th>
                </tr>
              </thead>
              <tbody>
                <template
                  v-for="section in sections"
                  :key="section.key"
                >
                  <tr class="section-row">
                    <td class="font-weight-bold">
                      {{ section.label }}
                      <span
                        v-if="sectionState(section).count"
                        class="text-caption text-primary ms-1"
                      >({{ sectionState(section).count }})</span>
                    </td>
                    <td
                      v-for="a in actions"
                      :key="a.key"
                      class="text-center"
                    >
                      <VCheckbox
                        v-if="sectionActionState(section, a.key).available"
                        :model-value="sectionActionState(section, a.key).all"
                        :indeterminate="sectionActionState(section, a.key).some"
                        hide-details
                        density="compact"
                        class="d-inline-flex"
                        @update:model-value="v => toggleSectionAction(section, a.key, !!v)"
                      />
                    </td>
                    <td class="text-center">
                      <VCheckbox
                        :model-value="sectionState(section).all"
                        :indeterminate="sectionState(section).some"
                        hide-details
                        density="compact"
                        class="d-inline-flex"
                        @update:model-value="v => toggleSection(section, !!v)"
                      />
                    </td>
                  </tr>
                  <tr
                    v-for="leaf in section.children"
                    :key="leaf.key"
                  >
                    <td class="ps-8">
                      {{ leaf.label }}
                    </td>
                    <td
                      v-for="a in actions"
                      :key="a.key"
                      class="text-center"
                    >
                      <VCheckbox
                        v-if="leaf.actions.includes(a.key)"
                        :model-value="has(leaf.key, a.key)"
                        hide-details
                        density="compact"
                        class="d-inline-flex"
                        @update:model-value="v => toggle(leaf, a.key, !!v)"
                      />
                      <span
                        v-else
                        class="text-disabled"
                      >—</span>
                    </td>
                    <td class="text-center">
                      <VCheckbox
                        :model-value="leafState(leaf).all"
                        :indeterminate="leafState(leaf).some"
                        hide-details
                        density="compact"
                        class="d-inline-flex"
                        @update:model-value="v => toggleLeaf(leaf, !!v)"
                      />
                    </td>
                  </tr>
                </template>
              </tbody>
            </VTable>
          </div>
        </VCardText>
        <VCardActions class="pa-6 pt-2">
          <VSpacer />
          <VBtn
            variant="text"
            color="secondary"
            @click="dialog = false"
          >
            إلغاء
          </VBtn>
          <VBtn
            color="primary"
            variant="elevated"
            :loading="saving"
            :disabled="!form.name || !form.email || (!editing && form.password.length < 8)"
            @click="save"
          >
            {{ editing ? 'حفظ التعديلات' : 'إنشاء المشرف' }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Activate / deactivate -->
    <VDialog
      v-model="statusDialog"
      max-width="460"
    >
      <VCard v-if="statusTarget">
        <VCardTitle class="pa-6 pb-2">
          {{ statusTarget.is_active ? 'تعطيل حساب المشرف' : 'تفعيل حساب المشرف' }}
        </VCardTitle>
        <VCardText>
          <template v-if="statusTarget.is_active">
            رح يتم تسجيل خروج <strong>{{ statusTarget.name }}</strong> من كل الأجهزة وما رح يقدر يدخل للوحة التحكم لحد ما تفعّله من جديد. صلاحياته بتضل محفوظة.
          </template>
          <template v-else>
            رح يقدر <strong>{{ statusTarget.name }}</strong> يدخل من جديد بنفس صلاحياته المحفوظة.
          </template>
        </VCardText>
        <VCardActions class="pa-6 pt-0">
          <VSpacer />
          <VBtn
            variant="text"
            color="secondary"
            @click="statusDialog = false"
          >
            إلغاء
          </VBtn>
          <VBtn
            :color="statusTarget.is_active ? 'error' : 'success'"
            variant="elevated"
            :loading="statusSaving"
            @click="confirmToggleStatus"
          >
            {{ statusTarget.is_active ? 'تعطيل' : 'تفعيل' }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Reset password -->
    <VDialog
      v-model="passwordDialog"
      max-width="480"
    >
      <VCard v-if="passwordTarget">
        <VCardTitle class="pa-6 pb-2">
          إعادة تعيين كلمة المرور
        </VCardTitle>
        <VCardText>
          <p class="mb-4">
            كلمة مرور جديدة لـ <strong>{{ passwordTarget.name }}</strong>. انسخها وأرسلها له؛ رح يتم تسجيل خروجه من كل الأجهزة.
          </p>
          <VTextField
            v-model="newPassword"
            label="كلمة المرور الجديدة"
            dir="ltr"
            :rules="passwordRules"
            density="compact"
          >
            <template #append>
              <VBtn
                icon
                size="small"
                variant="tonal"
                @click="copy(newPassword)"
              >
                <VIcon
                  icon="tabler-copy"
                  size="18"
                />
              </VBtn>
            </template>
          </VTextField>
        </VCardText>
        <VCardActions class="pa-6 pt-0">
          <VBtn
            variant="text"
            @click="newPassword = generatePassword()"
          >
            توليد غيرها
          </VBtn>
          <VSpacer />
          <VBtn
            variant="text"
            color="secondary"
            @click="passwordDialog = false"
          >
            إلغاء
          </VBtn>
          <VBtn
            color="primary"
            variant="elevated"
            :loading="passwordSaving"
            :disabled="newPassword.length < 8"
            @click="confirmResetPassword"
          >
            حفظ كلمة المرور
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

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
.table-scroll {
  overflow-x: auto;
}

.matrix-wrap {
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 8px;
}

.permission-matrix .section-row td {
  background: rgba(var(--v-theme-primary), 0.06);
}

.permission-matrix td,
.permission-matrix th {
  white-space: nowrap;
}
</style>

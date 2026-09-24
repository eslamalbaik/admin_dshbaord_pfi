<script setup lang="ts">
import api from '@/plugins/axios'

definePage({ meta: { requiresAdmin: true, adminOnly: true } })

const search = ref('')
const statusFilter = ref('')
const page = ref(1)
const itemsPerPage = ref(10)
const total = ref(0)
const loading = ref(false)
const contractors = ref<any[]>([])
const fetchError = ref('')

const deleteDialog = ref(false)
const deleteTarget = ref<any>(null)
const deleteLoading = ref(false)

// ── Snackbar (feedback) ───────────────────────────
const snackbar = ref(false)
const snackbarText = ref('')
const snackbarColor = ref('success')
const notify = (text: string, color: 'success' | 'error' = 'success') => {
  snackbarText.value = text
  snackbarColor.value = color
  snackbar.value = true
}

const detailsDialog = ref(false)
const detailsTarget = ref<any>(null)

const headers = [
  { title: 'المقاول', key: 'name', sortable: true },
  { title: 'رقم العضوية', key: 'membership_number' },
  { title: 'رقم السجل', key: 'commercial_register' },
  { title: 'التخصص', key: 'trade' },
  { title: 'الحالة', key: 'status' },
  { title: 'حالة الحساب', key: 'has_app_account', sortable: false },
  { title: 'تاريخ الانضمام', key: 'created_at' },
  { title: 'إجراءات', key: 'actions', sortable: false },
]

const statusOptions = [
  { title: 'الكل', value: '' },
  { title: 'نشط', value: 'active' },
  { title: 'معلّق', value: 'pending' },
  { title: 'موقوف', value: 'suspended' },
]

const fetchContractors = async () => {
  loading.value = true
  fetchError.value = ''
  try {
    const { data } = await api.get('/api/v1/contractors', {
      params: { search: search.value, status: statusFilter.value, page: page.value, per_page: itemsPerPage.value },
    })
    contractors.value = data.items || []
    total.value = data.meta?.total || contractors.value.length
  }
  catch (err: any) {
    // بدون إظهار الخطأ كان أي فشل في الطلب يبان كأنه "صفر نتائج"
    contractors.value = []
    total.value = 0
    fetchError.value = err?.response?.data?.message ?? 'تعذّر تحميل قائمة المقاولين.'
  }
  finally {
    loading.value = false
  }
}

// ── تصدير Excel (CSV) — يحترم فلاتر البحث/الحالة الحالية، يجيب كل النتائج بصفحة وحدة ──
const exporting = ref(false)

const exportContractors = async () => {
  exporting.value = true
  try {
    const { data } = await api.get('/api/v1/contractors', {
      params: { search: search.value, status: statusFilter.value, per_page: 10000 },
    })
    const rows: any[] = data.items || []

    // التصنيفات والتخصصات يخزّنها نموذج الإضافة في specialties[] (عمود JSON)، لا في
    // trade/classification — فكان التصدير يُخرج عمودين فارغين لكل مقاول أُضيف بالنموذج
    // الحالي (TASK-17 #4). تُقرأ الآن من نفس المصدر الذي يعرضه مودل المعاينة، وتُترجم
    // أكوادها إلى تسميات عربية بدل إخراج الكود الخام.
    const specialtiesOf = (c: any) => {
      try {
        return getSpecialtiesList(c) ?? []
      }
      catch {
        return []
      }
    }

    const fieldsColumn = (c: any) => specialtiesOf(c)
      .map((s: any) => getFieldTitle(s.field_lk_type))
      .filter((v: any) => v && v !== '—')
      .join(' / ')

    const specializationsColumn = (c: any) => specialtiesOf(c)
      .map((s: any) => getSpecializationTitle(s.specialization_lk_type))
      .filter((v: any) => v && v !== '—')
      .join(' / ')

    // «المجال: التخصص (الدرجة)» لكل صف تصنيف — أدقّ من ثلاثة أعمدة متوازية لأن الدرجة
    // تخصّ مجالها لا المقاول ككل.
    const classificationsColumn = (c: any) => specialtiesOf(c)
      .map((s: any) => {
        const field = getFieldTitle(s.field_lk_type)
        const spec = getSpecializationTitle(s.specialization_lk_type)
        const grade = getGradeTitle(s.classification)

        return `${field}: ${spec}${grade && grade !== '—' ? ` (${grade})` : ''}`
      })
      .join(' | ')

    const headerRow = [
      'اسم المنشأة', 'رقم العضوية', 'رقم السجل التجاري',
      'المجالات', 'التخصصات', 'التصنيفات التفصيلية', 'التصنيف العام', 'التخصص (نص حر)',
      'المفوض بالتوقيع', 'رقم هوية المفوض', 'رقم الجوال', 'البريد الإلكتروني', 'المدينة', 'الحالة', 'تاريخ الانضمام',
    ]
    const csvRows = [
      headerRow,
      ...rows.map(c => [
        c.name ?? '',
        c.membership_number ?? '',
        c.commercial_register ?? '',
        fieldsColumn(c),
        specializationsColumn(c),
        classificationsColumn(c),
        getGradeTitle(c.classification),
        // يبقى عموداً مستقلاً: مقاولون قدامى بياناتهم في trade وحده ولا specialties لهم
        c.trade ?? '',
        c.authorized_person ?? '',
        c.authorized_person_id_number ?? '',
        c.phone ?? '',
        c.email ?? '',
        c.city ?? '',
        getStatusLabel(c.status),
        c.created_at ? new Date(c.created_at).toLocaleDateString('ar-EG') : '',
      ]),
    ]

    // BOM لضمان قراءة Excel للعربية بترميز UTF-8
    const csv = '﻿' + csvRows.map(r => r.map(cell => `"${String(cell).replace(/"/g, '""')}"`).join(',')).join('\n')
    const url = URL.createObjectURL(new Blob([csv], { type: 'text/csv;charset=utf-8;' }))
    const link = document.createElement('a')

    link.href = url
    link.download = `contractors-${new Date().toISOString().slice(0, 10)}.csv`
    link.click()
    URL.revokeObjectURL(url)
  } catch (err) {
    console.error(err)
    notify('تعذّر تصدير الكشف.', 'error')
  } finally {
    exporting.value = false
  }
}

const getStatusColor = (status: string) => {
  switch (status) {
    case 'active': return 'success'
    case 'pending': return 'warning'
    case 'suspended': return 'error'
    case 'expired': return 'secondary'
    default: return 'secondary'
  }
}

const getStatusLabel = (status: string) => {
  switch (status) {
    case 'active': return 'نشط'
    case 'pending': return 'معلّق'
    case 'suspended': return 'موقوف'
    case 'expired': return 'منتهي'
    default: return status
  }
}

const openDelete = (contractor: any) => {
  deleteTarget.value = contractor
  deleteDialog.value = true
}

const confirmDelete = async () => {
  if (!deleteTarget.value) return
  deleteLoading.value = true
  try {
    await api.delete(`/api/v1/contractors/${deleteTarget.value.id}`)
    deleteDialog.value = false
    notify('تم حذف المقاول بنجاح.')
    // لو كان آخر عنصر بالصفحة الحالية، ارجع صفحة للخلف بدل ما تعرض صفحة فاضية
    if (contractors.value.length === 1 && page.value > 1)
      page.value -= 1
    fetchContractors()
  }
  catch (err: any) {
    console.error(err)
    notify(err?.response?.data?.message ?? 'تعذّر حذف المقاول.', 'error')
  }
  finally {
    deleteLoading.value = false
  }
}

const statusChangeOptions = [
  { title: 'نشط', value: 'active' },
  { title: 'معلّق', value: 'pending' },
  { title: 'موقوف', value: 'suspended' },
  { title: 'منتهي', value: 'expired' },
]

const statusUpdating = ref<number | null>(null)

const changeStatus = async (contractor: any, status: string) => {
  if (contractor.status === status) return
  statusUpdating.value = contractor.id
  try {
    await api.patch(`/api/v1/contractors/${contractor.id}/status`, { status })
    contractor.status = status
  }
  catch (err: any) {
    console.error(err)
    notify(err?.response?.data?.message ?? 'تعذّر تحديث حالة المقاول.', 'error')
  }
  finally {
    statusUpdating.value = null
  }
}

// ── تجميد الحساب — منفصل عن status: يقفل دخول المقاول للتطبيق فوراً (يُبطل
// كل توكناته)، بينما status=suspended يمنع التجديد فقط ولا يقفل الدخول.
const freezeUpdating = ref<number | null>(null)
const freezeConfirmTarget = ref<any>(null)

const setFrozen = async (contractor: any, frozen: boolean) => {
  freezeUpdating.value = contractor.id
  try {
    await api.patch(`/api/v1/contractors/${contractor.id}/freeze`, { frozen })
    contractor.is_frozen = frozen
    notify(frozen ? 'تم تجميد حساب المقاول — لن يقدر يدخل التطبيق حتى تُرفع.' : 'تم رفع التجميد عن الحساب.')
  }
  catch (err: any) {
    console.error(err)
    notify(err?.response?.data?.message ?? 'تعذّر تحديث حالة التجميد.', 'error')
  }
  finally {
    freezeUpdating.value = null
    freezeConfirmTarget.value = null
  }
}

// التجميد يطرد المقاول فوراً من كل أجهزته — تأكيد قبل التنفيذ. رفع التجميد
// إجراء آمن رجعي فلا يحتاج تأكيداً.
const toggleFreeze = (contractor: any) => {
  if (contractor.is_frozen) {
    setFrozen(contractor, false)
    return
  }
  freezeConfirmTarget.value = contractor
}

const openDetails = async (contractor: any) => {
  // Fetch full details
  try {
      const { data } = await api.get(`/api/v1/contractors/${contractor.id}`)
      detailsTarget.value = data.items || data.data || data || contractor
      detailsDialog.value = true
  } catch(e) {
      console.error(e)
      detailsTarget.value = contractor
      detailsDialog.value = true
  }
}

// ── تعديل سريع لاسم المفوض ورقم التواصل (من شاشة "عرض") ──
const contactEditDialog = ref(false)
const contactEditLoading = ref(false)
const contactEditError = ref('')
const contactForm = ref({ authorized_person: '', phone: '' })

const openContactEdit = () => {
  contactForm.value = {
    authorized_person: detailsTarget.value?.authorized_person ?? '',
    phone: detailsTarget.value?.phone ?? '',
  }
  contactEditError.value = ''
  contactEditDialog.value = true
}

const saveContactEdit = async () => {
  contactEditLoading.value = true
  contactEditError.value = ''
  try {
    const { data } = await api.patch(`/api/v1/contractors/${detailsTarget.value.id}/contact`, contactForm.value)
    detailsTarget.value = data.items ?? { ...detailsTarget.value, ...contactForm.value }
    const row = contractors.value.find(c => c.id === detailsTarget.value.id)
    if (row) {
      row.authorized_person = detailsTarget.value.authorized_person
      row.phone = detailsTarget.value.phone
    }
    contactEditDialog.value = false
    notify('تم تحديث بيانات التواصل بنجاح.')
  } catch (err: any) {
    console.error(err)
    contactEditError.value = err?.response?.data?.message ?? 'تعذّر تحديث بيانات التواصل.'
  } finally {
    contactEditLoading.value = false
  }
}

const downloadFile = (url: string | null, title: string) => {
  if (!url) return
  const a = document.createElement('a')
  a.href = url; a.download = title; a.target = '_blank'
  a.click()
}

// عرض الملف داخل المتصفح (تبويب جديد) بدل إجباره على التحميل مباشرة
const viewFile = (url: string | null) => {
  if (!url) return
  window.open(url, '_blank')
}

const getFieldTitle = (val: number) => {
  const options = [
    { title: 'غير محدد', value: 10 },
    { title: 'طرق', value: 20 },
    { title: 'ابنية', value: 30 },
    { title: 'كهروميكانيك', value: 40 },
    { title: 'الميــاه/المجــارى', value: 50 },
    { title: 'أشغال عامه', value: 60 },
  ]
  return options.find(o => o.value === Number(val))?.title || val
}

const getSpecializationTitle = (val: number) => {
  const options = [
    { title: 'غير محدد', value: 10 },
    { title: 'الطرق', value: 20 },
    { title: 'خلطات اسفلتيه', value: 30 },
    { title: 'خرسانه جسور وعبارات', value: 40 },
    { title: 'اشغال ترابيه', value: 50 },
    { title: 'الأبنية', value: 60 },
    { title: 'خرسانه مصنعه', value: 70 },
    { title: 'منشأت معدنية', value: 80 },
    { title: 'أبنية جاهزه بريفاف', value: 90 },
    { title: 'صيانة الابنيه', value: 100 },
    { title: 'كهروميكانيك', value: 110 },
    { title: 'صيانة كهروميكانيك', value: 120 },
    { title: 'ميكانيك', value: 130 },
    { title: 'كـهرباء', value: 140 },
    { title: 'الكترونيات', value: 150 },
    { title: 'المياه والمجاري', value: 160 },
    { title: 'محطات التنقيه', value: 170 },
    { title: 'الري والصرف', value: 180 },
    { title: 'حفريات وتعدين', value: 190 },
    { title: 'اشغال عامه', value: 200 },
    { title: 'سكك حديدية', value: 210 },
    { title: 'حفر آبار', value: 220 },
  ]
  return options.find(o => o.value === Number(val))?.title || val
}

// درجات التصنيف الموحَّدة (المادة 37) — "اولى أ" الدرجة الخاصة المقصورة على مجالي
// طرق(20) وابنية(30)، و"اولى ب" الدرجة الأولى العادية المتاحة لبقية المجالات.
const getGradeTitle = (val: string | null) => {
  const labels: Record<string, string> = {
    'اولى أ': 'الدرجة الأولى (أ)',
    'اولى ب': 'الدرجة الأولى (ب)',
    'ثانية':  'الدرجة الثانية',
    'ثالثة':  'الدرجة الثالثة',
    'رابعة':  'الدرجة الرابعة',
    'خامسة':  'الدرجة الخامسة',
  }
  return val ? (labels[val] || val) : '—'
}

const getSpecialtiesList = (contractor: any) => {
  if (!contractor) return []
  if (contractor.specialties) {
    return typeof contractor.specialties === 'string'
      ? JSON.parse(contractor.specialties)
      : contractor.specialties
  }
  if (contractor.field_lk_type || contractor.specialization_lk_type) {
    return [{
      field_lk_type: contractor.field_lk_type,
      specialization_lk_type: contractor.specialization_lk_type,
      classification: contractor.classification
    }]
  }
  return []
}

// ── بيانات بطاقات "تفاصيل المقاول" — مصفوفات label/value تُغذّي الكروت بدل تكرار <p> يدوياً ──
const membershipRows = computed(() => {
  const t = detailsTarget.value
  if (!t) return []
  return [
    { label: 'رقم العضوية', value: t.membership_number || '—', icon: 'tabler-id-badge-2' },
    { label: 'رقم السجل التجاري', value: t.commercial_register || '—', icon: 'tabler-file-certificate' },
    { label: 'رأس المال', value: t.capital || '—', icon: 'tabler-currency-dollar' },
    { label: 'تاريخ التسجيل', value: t.registration_date || '—', icon: 'tabler-calendar-event' },
    { label: 'الشكل القانوني', value: t.legal_form || '—', icon: 'tabler-gavel' },
    { label: 'غايات الشركة', value: t.company_purposes || '—', icon: 'tabler-target-arrow' },
  ]
})

const managementRows = computed(() => {
  const t = detailsTarget.value
  if (!t) return []
  return [
    { label: 'صاحب المنشأة', value: t.owner_name || '—', icon: 'tabler-user' },
    { label: 'أسماء الشركاء', value: t.partners || '—', icon: 'tabler-users' },
    { label: 'المفوض بالتوقيع', value: t.authorized_person || '—', icon: 'tabler-signature' },
    { label: 'رقم هوية المفوض', value: t.authorized_person_id_number || '—', icon: 'tabler-id' },
    { label: 'رقم جوال المفوض', value: t.authorized_person_phone || '—', dir: 'ltr', icon: 'tabler-device-mobile' },
    { label: 'رقم واتساب المفوض', value: t.authorized_person_whatsapp || '—', dir: 'ltr', icon: 'tabler-brand-whatsapp' },
  ]
})

const contactRows = computed(() => {
  const t = detailsTarget.value
  if (!t) return []
  return [
    { label: 'الجوال', value: t.phone || '—', dir: 'ltr', icon: 'tabler-device-mobile' },
    { label: 'الهاتف/الفاكس', value: t.fax || '—', dir: 'ltr', icon: 'tabler-phone' },
    { label: 'البريد الإلكتروني', value: t.email || '—', icon: 'tabler-mail' },
    { label: 'المحافظة', value: t.governorate?.name || '—', icon: 'tabler-map' },
    { label: 'المدينة', value: t.city || '—', icon: 'tabler-map-pin' },
    { label: 'الحي', value: t.district || '—', icon: 'tabler-map-pin-2' },
    { label: 'العمارة', value: t.building || '—', icon: 'tabler-building' },
    { label: 'الطابق', value: t.floor || '—', icon: 'tabler-stairs' },
    { label: 'العنوان التفصيلي', value: t.address || '—', icon: 'tabler-map-2' },
  ]
})

const activityRows = computed(() => {
  const t = detailsTarget.value
  if (!t) return []
  return [
    { label: 'التخصص العام', value: t.trade || '—', icon: 'tabler-briefcase' },
    { label: 'التصنيف العام', value: getGradeTitle(t.classification), icon: 'tabler-award' },
    { label: 'رقم رخصة البلدية', value: t.license_number || '—', icon: 'tabler-license' },
    { label: 'تاريخ التأسيس', value: t.established_date ? String(t.established_date).substring(0, 10) : '—', icon: 'tabler-calendar-star' },
  ]
})

const documentFields = [
  { key: 'cr_file', label: 'السجل التجاري', icon: 'tabler-file-certificate' },
  { key: 'company_register', label: 'سجل الشركة', icon: 'tabler-building' },
  { key: 'municipal_license', label: 'رخصة البلدية', icon: 'tabler-stamp' },
  { key: 'company_approval_letter', label: 'موافقة الانتساب', icon: 'tabler-checkbox' },
  { key: 'lease_or_ownership_contract', label: 'عقد المقر', icon: 'tabler-home' },
  { key: 'articles_of_association', label: 'عقد التأسيس', icon: 'tabler-file-text' },
  { key: 'internal_bylaws', label: 'النظام الداخلي', icon: 'tabler-book' },
  { key: 'bank_dealing_letter', label: 'تعامل البنك', icon: 'tabler-building-bank' },
  { key: 'secretary_contract', label: 'عقد سكرتير', icon: 'tabler-briefcase' },
  { key: 'full_time_engineer_certificate', label: 'شهادة مهندس متفرغ', icon: 'tabler-certificate' },
  { key: 'accountant_certificate_or_contract', label: 'شهادة تفرغ محاسب / عقد مكتب محاسبين', icon: 'tabler-report-money' },
  { key: 'partners_ids', label: 'هويات الشركاء', icon: 'tabler-id-badge-2' },
  { key: 'authorization_letter', label: 'تفويض توقيع', icon: 'tabler-signature' },
]

const documentUrl = (key: string) => detailsTarget.value?.[`${key}_url`] ?? detailsTarget.value?.[key] ?? null
</script>

<template>
  <div>
    <div class="d-flex justify-space-between align-center mb-6">
      <div>
        <h1 class="text-h4 font-weight-bold" style="font-family:Cairo,sans-serif">المقاولون</h1>
        <p class="text-body-2 text-medium-emphasis mb-0" style="font-family:Cairo,sans-serif">إدارة جميع المقاولين المسجلين في الاتحاد</p>
      </div>
      <div class="d-flex gap-2">
        <VBtn variant="tonal" prepend-icon="tabler-file-spreadsheet" :loading="exporting" @click="exportContractors">
          تصدير Excel
        </VBtn>
        <VBtn color="primary" prepend-icon="tabler-plus" :to="{ name: 'contractors-create' }">
          تسجيل مقاول جديد
        </VBtn>
      </div>
    </div>

    <VCard>
      <VCardText class="d-flex gap-4 flex-wrap">
        <VTextField
          v-model="search"
          placeholder="بحث باسم المقاول، العضوية، السجل..."
          prepend-inner-icon="tabler-search"
          density="compact"
          style="max-width:360px"
          @update:model-value="page = 1; fetchContractors()"
        />
        <VSelect
          v-model="statusFilter"
          :items="statusOptions"
          item-title="title"
          item-value="value"
          label="الحالة"
          density="compact"
          style="max-width:160px"
          @update:model-value="page = 1; fetchContractors()"
        />
      </VCardText>

      <VDataTableServer
        :headers="headers"
        :items="contractors"
        :items-length="total"
        :loading="loading"
        v-model:page="page"
        v-model:items-per-page="itemsPerPage"
        :items-per-page-options="[10, 25, 50]"
        mobile-breakpoint="sm"
        @update:options="fetchContractors"
      >
        <template #item.name="{ item }">
          <div class="d-flex align-center gap-3">
            <VAvatar color="primary" variant="tonal" size="36">
              <span class="text-body-2">{{ item.name?.substring(0, 2) }}</span>
            </VAvatar>
            <div>
              <div class="font-weight-medium" style="font-family:Cairo,sans-serif">{{ item.name }}</div>
              <div class="text-caption text-medium-emphasis">{{ item.email || item.phone }}</div>
            </div>
          </div>
        </template>

        <template #item.status="{ item }">
          <div class="d-flex align-center gap-1">
            <VMenu>
              <template #activator="{ props: menuProps }">
                <VChip v-bind="menuProps" :color="getStatusColor(item.status)" :loading="statusUpdating === item.id" size="small" label style="cursor:pointer">
                  {{ getStatusLabel(item.status) }}
                  <VIcon icon="tabler-chevron-down" size="14" class="ms-1" />
                </VChip>
              </template>
              <VList density="compact">
                <VListItem v-for="opt in statusChangeOptions" :key="opt.value" :active="opt.value === item.status" @click="changeStatus(item, opt.value)">
                  <VListItemTitle style="font-family:Cairo,sans-serif">{{ opt.title }}</VListItemTitle>
                </VListItem>
              </VList>
            </VMenu>
            <VChip v-if="item.is_frozen" color="info" size="small" label prepend-icon="tabler-snowflake" title="الحساب مجمّد — لا يقدر يدخل التطبيق">
              مجمّد
            </VChip>
          </div>
        </template>

        <template #item.has_app_account="{ item }">
          <VChip :color="item.has_app_account ? 'success' : 'secondary'" size="small" label>
            {{ item.has_app_account ? 'فاتح حساب' : 'لم يفتح بعد' }}
          </VChip>
        </template>

        <template #item.created_at="{ item }">
          {{ item.created_at ? new Date(item.created_at).toLocaleDateString('ar-PS') : '—' }}
        </template>

        <template #item.actions="{ item }">
          <VBtn icon size="small" variant="text" color="info" @click="openDetails(item)" title="عرض التفاصيل">
            <VIcon icon="tabler-eye" />
          </VBtn>
          <VBtn icon size="small" variant="text" color="primary" :to="{ name: 'contractors-edit-id', params: { id: item.id } }" title="تعديل">
            <VIcon icon="tabler-edit" />
          </VBtn>
          <VBtn
            icon size="small" variant="text"
            :color="item.is_frozen ? 'info' : 'default'"
            :loading="freezeUpdating === item.id"
            @click="toggleFreeze(item)"
            :title="item.is_frozen ? 'رفع التجميد' : 'تجميد الحساب'"
          >
            <VIcon :icon="item.is_frozen ? 'tabler-snowflake-off' : 'tabler-snowflake'" />
          </VBtn>
          <VBtn icon size="small" variant="text" color="error" @click="openDelete(item)" title="حذف">
            <VIcon icon="tabler-trash" />
          </VBtn>
        </template>
        <template #no-data>
          <div class="text-center pa-6 text-medium-emphasis" style="font-family:Cairo,sans-serif">
            {{ fetchError || 'لا توجد نتائج' }}
          </div>
        </template>
      </VDataTableServer>
    </VCard>

    <!-- Details Dialog -->
    <VDialog v-model="detailsDialog" max-width="960">
      <VCard v-if="detailsTarget" class="contractor-details-card" style="border-radius: 20px; overflow: hidden;">
        <!-- ─── Header banner ─── -->
        <div
          class="d-flex align-center gap-4 pa-6"
          style="background: linear-gradient(135deg, rgb(var(--v-theme-primary)) 0%, rgba(var(--v-theme-primary), 0.75) 100%);"
        >
          <VAvatar size="68" color="white" variant="flat" style="box-shadow: 0 4px 14px rgba(0,0,0,.18);">
            <span class="text-h5 font-weight-bold text-primary">{{ detailsTarget.name?.substring(0, 2) }}</span>
          </VAvatar>
          <div class="flex-grow-1" style="min-width:0">
            <h2 class="text-h5 font-weight-bold text-white text-truncate" style="font-family:Cairo,sans-serif">
              {{ detailsTarget.name }}
            </h2>
            <div class="d-flex align-center gap-2 flex-wrap mt-2">
              <VChip size="small" variant="tonal" label style="font-family:Cairo,sans-serif; background: rgba(255,255,255,0.18); color: #fff;">
                <VIcon icon="tabler-id-badge-2" size="14" start />
                عضوية {{ detailsTarget.membership_number }}
              </VChip>
              <VChip :color="getStatusColor(detailsTarget.status)" size="small" label style="font-family:Cairo,sans-serif">
                {{ getStatusLabel(detailsTarget.status) }}
              </VChip>
              <VChip :color="detailsTarget.has_app_account ? 'success' : 'secondary'" variant="flat" size="small" label style="font-family:Cairo,sans-serif">
                <VIcon :icon="detailsTarget.has_app_account ? 'tabler-device-mobile-check' : 'tabler-device-mobile-off'" size="14" start />
                {{ detailsTarget.has_app_account ? 'فاتح حساب' : 'لم يفتح حساب' }}
              </VChip>
            </div>
          </div>
          <VBtn icon="tabler-x" variant="tonal" color="white" size="small" @click="detailsDialog = false" />
        </div>

        <VCardText style="font-family:Cairo,sans-serif; max-height: 72vh; overflow-y: auto;" class="pa-5" >
          <VRow>
            <!-- معلومات العضوية والتأسيس -->
            <VCol cols="12" md="6">
              <VCard variant="outlined" class="h-100 details-section-card">
                <VCardItem>
                  <template #prepend>
                    <VAvatar color="primary" variant="tonal" size="38" rounded="lg">
                      <VIcon icon="tabler-building-bank" size="20" />
                    </VAvatar>
                  </template>
                  <VCardTitle class="text-subtitle-1 font-weight-bold">معلومات العضوية والتأسيس</VCardTitle>
                </VCardItem>
                <VCardText class="pt-0">
                  <div v-for="row in membershipRows" :key="row.label" class="details-row">
                    <span class="d-flex align-center gap-2 text-body-2 details-row-label">
                      <VIcon :icon="row.icon" size="16" color="primary" />
                      {{ row.label }}
                    </span>
                    <span class="text-body-2 font-weight-medium text-end details-row-value">{{ row.value }}</span>
                  </div>
                </VCardText>
              </VCard>
            </VCol>

            <!-- الإدارة والشركاء -->
            <VCol cols="12" md="6">
              <VCard variant="outlined" class="h-100 details-section-card">
                <VCardItem>
                  <template #prepend>
                    <VAvatar color="info" variant="tonal" size="38" rounded="lg">
                      <VIcon icon="tabler-users-group" size="20" />
                    </VAvatar>
                  </template>
                  <VCardTitle class="text-subtitle-1 font-weight-bold">الإدارة والشركاء</VCardTitle>
                  <template #append>
                    <VBtn size="small" variant="text" color="primary" prepend-icon="tabler-edit" @click="openContactEdit">
                      تعديل
                    </VBtn>
                  </template>
                </VCardItem>
                <VCardText class="pt-0">
                  <div v-for="row in managementRows" :key="row.label" class="details-row">
                    <span class="d-flex align-center gap-2 text-body-2 details-row-label">
                      <VIcon :icon="row.icon" size="16" color="info" />
                      {{ row.label }}
                    </span>
                    <span class="text-body-2 font-weight-medium text-end details-row-value" :dir="row.dir">{{ row.value }}</span>
                  </div>
                </VCardText>
              </VCard>
            </VCol>

            <!-- العنوان والاتصال -->
            <VCol cols="12" md="6">
              <VCard variant="outlined" class="h-100 details-section-card">
                <VCardItem>
                  <template #prepend>
                    <VAvatar color="success" variant="tonal" size="38" rounded="lg">
                      <VIcon icon="tabler-map-pin" size="20" />
                    </VAvatar>
                  </template>
                  <VCardTitle class="text-subtitle-1 font-weight-bold">العنوان والاتصال</VCardTitle>
                </VCardItem>
                <VCardText class="pt-0">
                  <div v-for="row in contactRows" :key="row.label" class="details-row">
                    <span class="d-flex align-center gap-2 text-body-2 details-row-label">
                      <VIcon :icon="row.icon" size="16" color="success" />
                      {{ row.label }}
                    </span>
                    <span class="text-body-2 font-weight-medium text-end details-row-value" :dir="row.dir">{{ row.value }}</span>
                  </div>
                </VCardText>
              </VCard>
            </VCol>

            <!-- بيانات النشاط والشركة -->
            <VCol cols="12" md="6">
              <VCard variant="outlined" class="h-100 details-section-card">
                <VCardItem>
                  <template #prepend>
                    <VAvatar color="primary" variant="tonal" size="38" rounded="lg">
                      <VIcon icon="tabler-briefcase" size="20" />
                    </VAvatar>
                  </template>
                  <VCardTitle class="text-subtitle-1 font-weight-bold">بيانات النشاط والشركة</VCardTitle>
                </VCardItem>
                <VCardText class="pt-0">
                  <div v-for="row in activityRows" :key="row.label" class="details-row">
                    <span class="d-flex align-center gap-2 text-body-2 details-row-label">
                      <VIcon :icon="row.icon" size="16" color="primary" />
                      {{ row.label }}
                    </span>
                    <span class="text-body-2 font-weight-medium text-end details-row-value">{{ row.value }}</span>
                  </div>
                </VCardText>
              </VCard>
            </VCol>

            <!-- المجالات والتصنيفات -->
            <VCol cols="12" md="6">
              <VCard variant="outlined" class="h-100 details-section-card">
                <VCardItem>
                  <template #prepend>
                    <VAvatar color="warning" variant="tonal" size="38" rounded="lg">
                      <VIcon icon="tabler-category" size="20" />
                    </VAvatar>
                  </template>
                  <VCardTitle class="text-subtitle-1 font-weight-bold">المجالات والتصنيفات</VCardTitle>
                </VCardItem>
                <VCardText class="pt-0">
                  <div v-if="getSpecialtiesList(detailsTarget).length > 0" class="d-flex flex-column gap-2">
                    <div
                      v-for="(spec, idx) in getSpecialtiesList(detailsTarget)"
                      :key="idx"
                      class="d-flex align-center gap-1 flex-wrap pa-2 rounded-lg"
                      style="background: rgba(var(--v-theme-on-surface), 0.03);"
                    >
                      <VChip size="small" color="primary" variant="tonal" label>{{ getFieldTitle(spec.field_lk_type) }}</VChip>
                      <VChip size="small" color="info" variant="tonal" label>{{ getSpecializationTitle(spec.specialization_lk_type) }}</VChip>
                      <VChip size="small" color="success" variant="tonal" label>{{ getGradeTitle(spec.classification) }}</VChip>
                    </div>
                  </div>
                  <div v-else class="details-row">
                    <span class="text-body-2 text-medium-emphasis">التصنيف</span>
                    <span class="text-body-2 font-weight-medium">{{ getGradeTitle(detailsTarget.classification) }}</span>
                  </div>
                </VCardText>
              </VCard>
            </VCol>

            <!-- المستندات المرفقة -->
            <VCol cols="12">
              <VCard variant="outlined" class="details-section-card">
                <VCardItem>
                  <template #prepend>
                    <VAvatar color="secondary" variant="tonal" size="38" rounded="lg">
                      <VIcon icon="tabler-folder" size="20" />
                    </VAvatar>
                  </template>
                  <VCardTitle class="text-subtitle-1 font-weight-bold">المستندات المرفقة</VCardTitle>
                </VCardItem>
                <VCardText class="pt-0">
                  <VRow dense>
                    <VCol v-for="doc in documentFields" :key="doc.key" cols="12" sm="6" md="4">
                      <div
                        class="d-flex align-center gap-2 pa-2 rounded-lg"
                        :style="`background: rgba(var(--v-theme-on-surface), ${documentUrl(doc.key) ? 0.03 : 0.015});`"
                      >
                        <VAvatar :color="documentUrl(doc.key) ? 'primary' : 'secondary'" variant="tonal" size="32" rounded="lg">
                          <VIcon :icon="doc.icon" size="16" />
                        </VAvatar>
                        <span
                          class="text-body-2 text-truncate flex-grow-1"
                          :class="documentUrl(doc.key) ? 'font-weight-medium' : 'text-medium-emphasis'"
                          style="font-family:Cairo,sans-serif"
                        >
                          {{ doc.label }}
                        </span>
                        <template v-if="documentUrl(doc.key)">
                          <VBtn icon size="x-small" variant="text" color="primary" @click="viewFile(documentUrl(doc.key))">
                            <VIcon icon="tabler-eye" size="16" />
                            <VTooltip activator="parent">عرض</VTooltip>
                          </VBtn>
                          <VBtn icon size="x-small" variant="text" color="primary" @click="downloadFile(documentUrl(doc.key), doc.label)">
                            <VIcon icon="tabler-download" size="16" />
                            <VTooltip activator="parent">تحميل</VTooltip>
                          </VBtn>
                        </template>
                        <VIcon v-else icon="tabler-file-off" size="16" color="secondary" />
                      </div>
                    </VCol>
                  </VRow>
                </VCardText>
              </VCard>
            </VCol>

            <!-- ملاحظات إضافية -->
            <VCol v-if="detailsTarget.notes" cols="12">
              <VCard variant="outlined" class="details-section-card" color="warning">
                <VCardItem>
                  <template #prepend>
                    <VAvatar color="warning" variant="tonal" size="38" rounded="lg">
                      <VIcon icon="tabler-notes" size="20" />
                    </VAvatar>
                  </template>
                  <VCardTitle class="text-subtitle-1 font-weight-bold">ملاحظات إضافية</VCardTitle>
                </VCardItem>
                <VCardText class="pt-0">
                  <p class="text-body-2 mb-0">{{ detailsTarget.notes }}</p>
                </VCardText>
              </VCard>
            </VCol>
          </VRow>
        </VCardText>
        <VDivider />
        <VCardActions class="pa-4">
          <VSpacer />
          <VBtn variant="tonal" @click="detailsDialog = false">إغلاق</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Contact Edit Dialog — اسم المفوض ورقم التواصل -->
    <VDialog v-model="contactEditDialog" max-width="440" persistent>
      <VCard>
        <VCardTitle style="font-family:Cairo,sans-serif">تعديل المفوض ورقم التواصل</VCardTitle>
        <VCardText>
          <VAlert v-if="contactEditError" type="error" variant="tonal" class="mb-4">
            {{ contactEditError }}
          </VAlert>
          <VTextField
            v-model="contactForm.authorized_person"
            label="المفوض بالتوقيع"
            class="mb-4"
            style="font-family:Cairo,sans-serif"
          />
          <VTextField
            v-model="contactForm.phone"
            label="رقم الجوال"
            dir="ltr"
          />
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn variant="text" @click="contactEditDialog = false">إلغاء</VBtn>
          <VBtn color="primary" :loading="contactEditLoading" @click="saveContactEdit">حفظ</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Delete Dialog -->
    <VDialog v-model="deleteDialog" max-width="400">
      <VCard>
        <VCardTitle style="font-family:Cairo,sans-serif">تأكيد الحذف</VCardTitle>
        <VCardText style="font-family:Cairo,sans-serif">
          هل أنت متأكد من حذف المقاول <strong>{{ deleteTarget?.name }}</strong>؟ لا يمكن التراجع عن هذا الإجراء.
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn variant="tonal" @click="deleteDialog = false">إلغاء</VBtn>
          <VBtn color="error" :loading="deleteLoading" @click="confirmDelete">حذف</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Freeze Confirm Dialog -->
    <VDialog :model-value="!!freezeConfirmTarget" max-width="420" @update:model-value="freezeConfirmTarget = null">
      <VCard v-if="freezeConfirmTarget">
        <VCardTitle style="font-family:Cairo,sans-serif">تأكيد التجميد</VCardTitle>
        <VCardText style="font-family:Cairo,sans-serif">
          هل أنت متأكد من تجميد حساب <strong>{{ freezeConfirmTarget.name }}</strong>؟
          سيتم إخراجه فوراً من التطبيق على كل أجهزته ولن يقدر يدخل مرة تانية لحد ما تُرفع التجميد.
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn variant="tonal" @click="freezeConfirmTarget = null">إلغاء</VBtn>
          <VBtn color="info" :loading="freezeUpdating === freezeConfirmTarget.id" @click="setFrozen(freezeConfirmTarget, true)">تجميد</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Feedback Snackbar -->
    <VSnackbar
      v-model="snackbar"
      :timeout="3500"
      :color="snackbarColor"
      location="bottom end"
      variant="elevated"
    >
      <span style="font-family:Cairo,sans-serif">{{ snackbarText }}</span>
      <template #actions>
        <VBtn variant="text" size="small" @click="snackbar = false">إغلاق</VBtn>
      </template>
    </VSnackbar>
  </div>
</template>
<style scoped>
.details-section-card {
  border-radius: 14px !important;
  transition: box-shadow 0.2s ease, transform 0.2s ease;
}
.details-section-card:hover {
  box-shadow: 0 4px 16px rgba(var(--v-theme-on-surface), 0.08);
}
.details-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding-block: 8px;
  border-bottom: 1px dashed rgba(var(--v-theme-on-surface), 0.1);
}
.details-row:last-child {
  border-bottom: none;
}
.details-row-label {
  color: rgba(var(--v-theme-on-surface), var(--v-high-emphasis-opacity));
  font-weight: 500;
  white-space: nowrap;
}
.details-row-value {
  color: rgba(var(--v-theme-on-surface), var(--v-high-emphasis-opacity));
}
</style>

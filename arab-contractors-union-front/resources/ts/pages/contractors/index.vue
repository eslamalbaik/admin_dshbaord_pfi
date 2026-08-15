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

const detailsDialog = ref(false)
const detailsTarget = ref<any>(null)

const headers = [
  { title: 'المقاول', key: 'name', sortable: true },
  { title: 'رقم العضوية', key: 'membership_number' },
  { title: 'رقم السجل', key: 'commercial_register' },
  { title: 'التخصص', key: 'trade' },
  { title: 'الحالة', key: 'status' },
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
    fetchContractors()
  }
  catch (err) {
    console.error(err)
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
  catch (err) {
    console.error(err)
  }
  finally {
    statusUpdating.value = null
  }
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

const downloadFile = (url: string | null, title: string) => {
  if (!url) return
  const a = document.createElement('a')
  a.href = url; a.download = title; a.target = '_blank'
  a.click()
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

</script>

<template>
  <div>
    <div class="d-flex justify-space-between align-center mb-6">
      <div>
        <h1 class="text-h4 font-weight-bold" style="font-family:Cairo,sans-serif">المقاولون</h1>
        <p class="text-body-2 text-medium-emphasis mb-0" style="font-family:Cairo,sans-serif">إدارة جميع المقاولين المسجلين في الاتحاد</p>
      </div>
      <VBtn color="primary" prepend-icon="tabler-plus" :to="{ name: 'contractors-create' }">
        تسجيل مقاول جديد
      </VBtn>
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
    <VDialog v-model="detailsDialog" max-width="900">
      <VCard v-if="detailsTarget">
        <VCardTitle class="d-flex justify-space-between align-center" style="font-family:Cairo,sans-serif">
          <span>تفاصيل المقاول: {{ detailsTarget.name }}</span>
          <VBtn icon="tabler-x" variant="text" size="small" @click="detailsDialog = false" />
        </VCardTitle>
        <VDivider />
        <VCardText style="font-family:Cairo,sans-serif; max-height: 70vh; overflow-y: auto;">
          <VRow>
            <VCol cols="12" md="6">
              <h3 class="text-h6 mb-3 text-primary">معلومات العضوية والتأسيس</h3>
              <p><strong>رقم العضوية:</strong> {{ detailsTarget.membership_number }}</p>
              <p><strong>رقم السجل التجاري:</strong> {{ detailsTarget.commercial_register }}</p>
              <p><strong>رأس المال:</strong> {{ detailsTarget.capital || '—' }}</p>
              <p><strong>تاريخ التسجيل:</strong> {{ detailsTarget.registration_date || '—' }}</p>
              <p><strong>الشكل القانوني:</strong> {{ detailsTarget.legal_form || '—' }}</p>
              <p><strong>غايات الشركة:</strong> {{ detailsTarget.company_purposes || '—' }}</p>
              <div v-if="getSpecialtiesList(detailsTarget).length > 0">
                <p class="mb-1"><strong>المجالات والتصنيفات:</strong></p>
                <div class="d-flex flex-column gap-1 mb-3">
                  <div v-for="(spec, idx) in getSpecialtiesList(detailsTarget)" :key="idx" class="d-flex align-center gap-1 flex-wrap">
                    <VChip size="small" color="primary" label>{{ getFieldTitle(spec.field_lk_type) }}</VChip>
                    <VChip size="small" color="info" label>{{ getSpecializationTitle(spec.specialization_lk_type) }}</VChip>
                    <VChip size="small" color="success" label>تصنيف: {{ spec.classification }}</VChip>
                  </div>
                </div>
              </div>
              <div v-else>
                <p><strong>التخصص:</strong> {{ detailsTarget.trade || '—' }}</p>
                <p><strong>التصنيف:</strong> {{ detailsTarget.classification || '—' }}</p>
              </div>
            </VCol>
            <VCol cols="12" md="6">
              <h3 class="text-h6 mb-3 text-primary">الإدارة والشركاء</h3>
              <p><strong>صاحب المنشأة:</strong> {{ detailsTarget.owner_name || '—' }}</p>
              <p><strong>أسماء الشركاء:</strong> {{ detailsTarget.partners || '—' }}</p>
              <p><strong>المفوض بالتوقيع:</strong> {{ detailsTarget.authorized_person || '—' }}</p>
            </VCol>
            
            <VCol cols="12">
                <VDivider class="my-4" />
            </VCol>

            <VCol cols="12" md="6">
              <h3 class="text-h6 mb-3 text-primary">العنوان والاتصال</h3>
              <p><strong>الجوال:</strong> <span dir="ltr">{{ detailsTarget.phone || '—' }}</span></p>
              <p><strong>الهاتف/الفاكس:</strong> <span dir="ltr">{{ detailsTarget.fax || '—' }}</span></p>
              <p><strong>البريد الإلكتروني:</strong> {{ detailsTarget.email || '—' }}</p>
              <p><strong>المدينة:</strong> {{ detailsTarget.city || '—' }}</p>
              <p><strong>العنوان التفصيلي:</strong> {{ detailsTarget.address || '—' }}</p>
            </VCol>

            <VCol cols="12" md="6">
              <h3 class="text-h6 mb-3 text-primary">المستندات المرفقة</h3>
              <div class="d-flex flex-column gap-2">
                <VBtn size="small" variant="tonal" prepend-icon="tabler-download" @click="downloadFile(detailsTarget.cr_file_url ?? detailsTarget.cr_file, 'السجل التجاري')" :disabled="!detailsTarget.cr_file">السجل التجاري</VBtn>
                <VBtn size="small" variant="tonal" prepend-icon="tabler-download" @click="downloadFile(detailsTarget.company_register_url ?? detailsTarget.company_register, 'سجل الشركة')" :disabled="!detailsTarget.company_register">سجل الشركة</VBtn>
                <VBtn size="small" variant="tonal" prepend-icon="tabler-download" @click="downloadFile(detailsTarget.municipal_license_url ?? detailsTarget.municipal_license, 'رخصة البلدية')" :disabled="!detailsTarget.municipal_license">رخصة البلدية</VBtn>
                <VBtn size="small" variant="tonal" prepend-icon="tabler-download" @click="downloadFile(detailsTarget.company_approval_letter_url ?? detailsTarget.company_approval_letter, 'موافقة الانتساب')" :disabled="!detailsTarget.company_approval_letter">موافقة الانتساب</VBtn>
                <VBtn size="small" variant="tonal" prepend-icon="tabler-download" @click="downloadFile(detailsTarget.lease_or_ownership_contract_url ?? detailsTarget.lease_or_ownership_contract, 'عقد المقر')" :disabled="!detailsTarget.lease_or_ownership_contract">عقد المقر (إيجار/تمليك)</VBtn>
                <VBtn size="small" variant="tonal" prepend-icon="tabler-download" @click="downloadFile(detailsTarget.articles_of_association_url ?? detailsTarget.articles_of_association, 'عقد التأسيس')" :disabled="!detailsTarget.articles_of_association">عقد التأسيس</VBtn>
                <VBtn size="small" variant="tonal" prepend-icon="tabler-download" @click="downloadFile(detailsTarget.internal_bylaws_url ?? detailsTarget.internal_bylaws, 'النظام الداخلي')" :disabled="!detailsTarget.internal_bylaws">النظام الداخلي</VBtn>
                <VBtn size="small" variant="tonal" prepend-icon="tabler-download" @click="downloadFile(detailsTarget.bank_dealing_letter_url ?? detailsTarget.bank_dealing_letter, 'تعامل البنك')" :disabled="!detailsTarget.bank_dealing_letter">كتاب تعامل البنك</VBtn>
                <VBtn size="small" variant="tonal" prepend-icon="tabler-download" @click="downloadFile(detailsTarget.secretary_contract_url ?? detailsTarget.secretary_contract, 'عقد سكرتير')" :disabled="!detailsTarget.secretary_contract">عقد سكرتير</VBtn>
                <VBtn size="small" variant="tonal" prepend-icon="tabler-download" @click="downloadFile(detailsTarget.full_time_engineer_certificate_url ?? detailsTarget.full_time_engineer_certificate, 'شهادة مهندس')" :disabled="!detailsTarget.full_time_engineer_certificate">شهادة مهندس متفرغ</VBtn>
                <VBtn size="small" variant="tonal" prepend-icon="tabler-download" @click="downloadFile(detailsTarget.partners_ids_url ?? detailsTarget.partners_ids, 'هويات الشركاء')" :disabled="!detailsTarget.partners_ids">هويات الشركاء</VBtn>
                <VBtn size="small" variant="tonal" prepend-icon="tabler-download" @click="downloadFile(detailsTarget.authorization_letter_url ?? detailsTarget.authorization_letter, 'تفويض توقيع')" :disabled="!detailsTarget.authorization_letter">تفويض المفوض</VBtn>
              </div>
            </VCol>
          </VRow>
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn variant="tonal" @click="detailsDialog = false">إغلاق</VBtn>
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
  </div>
</template>
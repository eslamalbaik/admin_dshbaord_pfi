<script setup lang="ts">
import api from '@/plugins/axios'
import { useRouter } from 'vue-router'

definePage({ meta: { requiresAdmin: true, adminOnly: true } })

const router = useRouter()
const step = ref(1)
const loading = ref(false)
const errorMsg = ref('')

const isNewMembership = ref(true)
const nextMembershipNumber = ref('')

const fetchNextMembership = async () => {
  try {
    const { data } = await api.get('/api/v1/contractors/next-membership-number')
    // الاستجابة الموحدة الجديدة تضع البيانات تحت items — مع دعم الشكل القديم احتياطاً
    nextMembershipNumber.value = data.items?.next_membership_number ?? data.next_membership_number
    if (isNewMembership.value) {
      form.value.membership_number = nextMembershipNumber.value
    }
  } catch (err) {
    console.error('Failed to fetch next membership number', err)
  }
}

onMounted(() => {
  fetchNextMembership()
})

watch(isNewMembership, (newVal) => {
  if (newVal) {
    form.value.membership_number = nextMembershipNumber.value
  } else {
    form.value.membership_number = ''
  }
})

const validationErrors = ref<Record<string, string[]>>({})

const form = ref({
  // Step 1: معلومات العضوية والتأسيس
  membership_number: '',
  commercial_register: '',
  name: '',
  capital: '',
  registration_date: '',
  legal_form: '',
  company_purposes: '',

  // Step 2: بيانات الإدارة والشركاء
  owner_name: '',
  partners: [] as string[],
  authorized_person: '',
  authorized_person_id_number: '',
  authorized_person_phone: '',
  authorized_person_whatsapp: '',

  // Step 3: العنوان وبيانات الاتصال
  phone: '',
  fax: '',
  email: '',
  city: '',
  address: '',
  trade: '',
  specialties: [
    { field_lk_type: null as number | null, specialization_lk_type: null as number | null, classification: '' }
  ] as Array<{ field_lk_type: number | null, specialization_lk_type: number | null, classification: string }>,
  established_date: '',
  license_number: '',

  // Step 4: الوثائق والمستندات المطلوبة
  lease_or_ownership_contract: null as File | null,
  company_approval_letter: null as File | null,
  municipal_license: null as File | null,
  company_register: null as File | null,
  cr_file: null as File | null, // السجل التجاري
  articles_of_association: null as File | null,
  internal_bylaws: null as File | null,
  bank_dealing_letter: null as File | null,
  secretary_contract: null as File | null,
  full_time_engineer_certificate: null as File | null,
  accountant_certificate_or_contract: null as File | null,
  partners_ids: null as File | null,
  authorization_letter: null as File | null,
  notes: '',
})

const newPartnerName = ref('')

const addPartner = () => {
  const name = newPartnerName.value.trim()
  if (name) {
    if (!form.value.partners) {
      form.value.partners = []
    }
    if (!form.value.partners.includes(name)) {
      form.value.partners.push(name)
    }
    newPartnerName.value = ''
  }
}

const removePartner = (index: number) => {
  form.value.partners.splice(index, 1)
}

const addSpecialty = () => {
  form.value.specialties.push({ field_lk_type: null, specialization_lk_type: null, classification: '' })
}

const removeSpecialty = (index: number) => {
  if (form.value.specialties.length > 1) {
    form.value.specialties.splice(index, 1)
  }
}

const fieldOptions = [
  { title: 'غير محدد', value: 10 },
  { title: 'طرق', value: 20 },
  { title: 'ابنية', value: 30 },
  { title: 'كهروميكانيك', value: 40 },
  { title: 'الميــاه/المجــارى', value: 50 },
  { title: 'أشغال عامه', value: 60 },
]

const specializationOptions = [
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

// درجات التصنيف الموحَّدة (المادة 37) — "اولى أ" لا تصحّ إلا لمجالي طرق(20) وابنية(30)
const TOP_TIER_FIELDS = [20, 30]
const gradeOptions = [
  { title: 'الدرجة الأولى (أ)', value: 'اولى أ' },
  { title: 'الدرجة الأولى (ب)', value: 'اولى ب' },
  { title: 'الدرجة الثانية', value: 'ثانية' },
  { title: 'الدرجة الثالثة', value: 'ثالثة' },
  { title: 'الدرجة الرابعة', value: 'رابعة' },
  { title: 'الدرجة الخامسة', value: 'خامسة' },
]
const gradeOptionsFor = (fieldLkType: number | null) =>
  TOP_TIER_FIELDS.includes(fieldLkType as number) ? gradeOptions : gradeOptions.filter(g => g.value !== 'اولى أ')

const nextStep = () => { if (step.value < 4) step.value++ }
const prevStep = () => { if (step.value > 1) step.value-- }

const submit = async () => {
  loading.value = true
  errorMsg.value = ''
  validationErrors.value = {}
  try {
    const fd = new FormData()
    Object.entries(form.value).forEach(([k, v]) => {
      if (v !== null && v !== '') {
        if (k === 'partners' && Array.isArray(v)) {
          if (v.length > 0) fd.append(k, v.join(','))
        } else if (k === 'specialties' && Array.isArray(v)) {
          fd.append(k, JSON.stringify(v))
        } else {
          fd.append(k, v as any)
        }
      }
    })
    await api.post('/api/v1/contractors', fd, { headers: { 'Content-Type': 'multipart/form-data' } })
    router.push({ name: 'contractors' })
  }
  catch (err: any) {
    errorMsg.value = err?.response?.data?.message || 'فشل تسجيل المقاول. يرجى التحقق من المدخلات.'
    
    const errors = err?.response?.data?.errors
    if (errors) {
        validationErrors.value = errors
        // Jump to the first step with an error if available
        if (errors.membership_number || errors.commercial_register || errors.name || errors.capital || errors.registration_date || errors.legal_form) {
            step.value = 1
        } else if (errors.owner_name || errors.partners || errors.authorized_person) {
            step.value = 2
        } else if (errors.phone || errors.fax || errors.email || errors.city || errors.address || errors.license_number || errors.established_date || errors.specialties) {
            step.value = 3
        } else {
            step.value = 4
        }
    }
  }
  finally {
    loading.value = false
  }
}
</script>

<template>
  <div>
    <div class="mb-6">
      <h1 class="text-h4 font-weight-bold" style="font-family:Cairo,sans-serif">تسجيل مقاول جديد</h1>
      <p class="text-body-2 text-medium-emphasis mb-0" style="font-family:Cairo,sans-serif">يرجى تعبئة كافة بيانات طلب الانتساب وإرفاق الوثائق المطلوبة.</p>
    </div>

    <!-- Stepper Header -->
    <VCard class="mb-6">
      <VCardText>
        <VStepper
            v-model="step"
            :items="['معلومات العضوية والتأسيس', 'بيانات الإدارة والشركاء', 'العنوان وبيانات الاتصال', 'الوثائق والمستندات المطلوبة']"
            alt-labels
            hide-actions
            style="font-family:Cairo,sans-serif"
        />
      </VCardText>
    </VCard>

    <VAlert v-if="errorMsg" type="error" class="mb-6" style="font-family:Cairo,sans-serif">
      {{ errorMsg }}
    </VAlert>

    <!-- Step 1: معلومات العضوية والتأسيس -->
    <VCard v-if="step === 1">
      <VCardTitle style="font-family:Cairo,sans-serif">معلومات العضوية والتأسيس</VCardTitle>
      <VCardText>
        <VRow>
          <VCol cols="12" md="5">
            <VTextField v-model="form.name" label="اسم طالب الانتساب (الشركة / المؤسسة) *" :rules="[v => !!v || 'مطلوب']" :error-messages="validationErrors.name" />
          </VCol>
          <VCol cols="12" md="3" class="d-flex align-center">
            <VCheckbox v-model="isNewMembership" label="عضوية جديدة (توليد تلقائي)" hide-details density="compact" />
          </VCol>
          <VCol cols="12" md="4">
            <VTextField
              v-model="form.membership_number"
              label="رقم العضوية بالاتحاد *"
              :readonly="isNewMembership"
              :placeholder="isNewMembership ? 'جاري التوليد...' : 'مثال: 123'"
              :error-messages="validationErrors.membership_number"
              :rules="[
                v => !!v || 'مطلوب',
                v => {
                  if (/^[0-9]+$/.test(v)) return (parseInt(v) >= 1 && parseInt(v) <= 927) || 'أرقام العضوية القديمة يجب أن تكون بين 1 و 927'
                  if (/^[0-9]+_g$/.test(v)) return parseInt(v.split('_')[0]) >= 928 || 'أرقام العضوية الجديدة يجب أن تبدأ من 928_g'
                  return 'صيغة غير صحيحة (مثال: 100 أو 928_g)'
                }
              ]"
            />
          </VCol>
          <VCol cols="12" md="3">
            <VTextField v-model="form.commercial_register" label="رقم السجل التجاري *" :error-messages="validationErrors.commercial_register" :rules="[
              v => !!v || 'مطلوب',
              v => /^[0-9]{9}$/.test(v) || 'يجب أن يتكون من 9 أرقام بالضبط'
            ]" />
          </VCol>
          <VCol cols="12" md="4">
            <VTextField v-model="form.capital" label="رأس مال الشركة" :error-messages="validationErrors.capital" />
          </VCol>
          <VCol cols="12" md="4">
            <VTextField v-model="form.registration_date" label="تاريخ التسجيل" type="date" :error-messages="validationErrors.registration_date" />
          </VCol>
          <VCol cols="12" md="4">
            <VTextField v-model="form.legal_form" label="الشكل القانوني للشركة" placeholder="مثال: مساهمة، تضامن، إلخ" :error-messages="validationErrors.legal_form" />
          </VCol>
          <VCol cols="12">
            <VTextarea v-model="form.company_purposes" label="غايات الشركة" rows="3" :error-messages="validationErrors.company_purposes" />
          </VCol>
        </VRow>
      </VCardText>
      <VCardActions class="pa-4">
        <VSpacer />
        <VBtn color="primary" @click="nextStep" append-icon="tabler-arrow-left">التالي</VBtn>
      </VCardActions>
    </VCard>

    <!-- Step 2: بيانات الإدارة والشركاء -->
    <VCard v-if="step === 2">
      <VCardTitle style="font-family:Cairo,sans-serif">بيانات الإدارة والشركاء</VCardTitle>
      <VCardText>
        <VRow>
          <VCol cols="12" md="6">
            <VTextField v-model="form.owner_name" label="اسم صاحب المنشأة" :error-messages="validationErrors.owner_name" />
          </VCol>
          <VCol cols="12" md="6">
            <VTextField v-model="form.authorized_person" label="اسم المفوض بالتوقيع" :error-messages="validationErrors.authorized_person" />
          </VCol>
          <VCol cols="12" md="4">
            <VTextField v-model="form.authorized_person_id_number" label="رقم هوية المفوض" :error-messages="validationErrors.authorized_person_id_number" />
          </VCol>
          <VCol cols="12" md="4">
            <VTextField v-model="form.authorized_person_phone" label="رقم جوال المفوض" :error-messages="validationErrors.authorized_person_phone" />
          </VCol>
          <VCol cols="12" md="4">
            <VTextField v-model="form.authorized_person_whatsapp" label="رقم الواتساب للمفوض" :error-messages="validationErrors.authorized_person_whatsapp" />
          </VCol>
          <VCol cols="12">
            <div class="d-flex align-center gap-2">
              <VTextField
                v-model="newPartnerName"
                label="إضافة اسم شريك"
                placeholder="اكتب اسم الشريك واضغط إضافة أو Enter"
                prepend-icon="tabler-users"
                :error-messages="validationErrors.partners"
                @keydown.enter.prevent="addPartner"
              />
              <VBtn color="primary" @click="addPartner" style="height: 42px;">
                إضافة شريك
              </VBtn>
            </div>
            
            <!-- قائمة الشركاء المضافين -->
            <div class="d-flex flex-wrap gap-2 mt-3" v-if="form.partners && form.partners.length > 0">
              <VChip
                v-for="(partner, index) in form.partners"
                :key="index"
                closable
                color="primary"
                @click:close="removePartner(index)"
              >
                {{ partner }}
              </VChip>
            </div>
          </VCol>
        </VRow>
      </VCardText>
      <VCardActions class="pa-4">
        <VBtn variant="tonal" @click="prevStep" prepend-icon="tabler-arrow-right">السابق</VBtn>
        <VSpacer />
        <VBtn color="primary" @click="nextStep" append-icon="tabler-arrow-left">التالي</VBtn>
      </VCardActions>
    </VCard>

    <!-- Step 3: العنوان وبيانات الاتصال -->
    <VCard v-if="step === 3">
      <VCardTitle style="font-family:Cairo,sans-serif">العنوان وبيانات الاتصال</VCardTitle>
      <VCardText>
        <VRow>
          <VCol cols="12" md="4">
            <VTextField
              v-model="form.phone"
              label="رقم الجوال *"
              prefix="🇵🇸 +970"
              placeholder="59XXXXXXX"
              :error-messages="validationErrors.phone"
              :rules="[v => !!v || 'مطلوب', v => /^[0-9]{9}$/.test(v) || 'يجب إدخال 9 أرقام (مثال: 599123456)']"
            />
          </VCol>
          <VCol cols="12" md="4">
            <VTextField v-model="form.fax" label="الهاتف / الفاكس" :error-messages="validationErrors.fax" />
          </VCol>
          <VCol cols="12" md="4">
            <VTextField v-model="form.email" label="البريد الإلكتروني" type="email" :error-messages="validationErrors.email" />
          </VCol>
          <VCol cols="12" md="4">
            <VSelect
              v-model="form.city"
              :items="['شمال غزة', 'غزة', 'الوسطى', 'خان يونس', 'رفح']"
              label="المدينة / المحافظة (غزة) *"
              :error-messages="validationErrors.city"
              :rules="[v => !!v || 'مطلوب']"
            />
          </VCol>
          <VCol cols="12" md="8">
            <VTextField v-model="form.address" label="العنوان التفصيلي (الحي، الشارع، البناية، الطابق) *" :error-messages="validationErrors.address" :rules="[v => !!v || 'مطلوب']" />
          </VCol>
          <!-- التخصصات والتصنيفات المتعددة -->
          <VCol cols="12">
            <h3 class="text-subtitle-1 font-weight-bold mb-4" style="font-family:Cairo,sans-serif">مجالات وتصنيفات المقاول</h3>
            
            <VRow v-for="(spec, index) in form.specialties" :key="index" class="align-center mb-4">
              <VCol cols="12" md="4">
                <VSelect
                  v-model="spec.field_lk_type"
                  :items="fieldOptions"
                  item-title="title"
                  item-value="value"
                  label="المجال *"
                  :rules="[v => !!v || 'مطلوب']"
                />
              </VCol>
              <VCol cols="12" md="4">
                <VSelect
                  v-model="spec.specialization_lk_type"
                  :items="specializationOptions"
                  item-title="title"
                  item-value="value"
                  label="التخصص *"
                  :rules="[v => !!v || 'مطلوب']"
                />
              </VCol>
              <VCol cols="12" md="3">
                <VSelect
                  v-model="spec.classification"
                  :items="gradeOptionsFor(spec.field_lk_type)"
                  item-title="title"
                  item-value="value"
                  label="تصنيف المقاول لهذا المجال *"
                  :rules="[v => !!v || 'مطلوب']"
                />
              </VCol>
              <VCol cols="12" md="1" class="text-center">
                <VBtn
                  icon
                  variant="text"
                  color="error"
                  :disabled="form.specialties.length <= 1"
                  @click="removeSpecialty(index)"
                  title="حذف هذا المجال"
                >
                  <VIcon icon="tabler-trash" />
                </VBtn>
              </VCol>
            </VRow>
            
            <VBtn
              prepend-icon="tabler-plus"
              variant="tonal"
              size="small"
              color="primary"
              @click="addSpecialty"
              class="mt-2 mb-4"
            >
              إضافة مجال/تصنيف آخر
            </VBtn>
            <VAlert v-if="validationErrors.specialties" type="error" class="mt-2" style="font-family:Cairo,sans-serif" density="compact">
              {{ validationErrors.specialties[0] }}
            </VAlert>
          </VCol>
          <VCol cols="12" md="4">
            <VTextField v-model="form.license_number" label="رقم رخصة البلدية (5 أرقام) *" :error-messages="validationErrors.license_number" :rules="[
              v => !!v || 'مطلوب',
              v => /^[0-9]{5}$/.test(v) || 'يجب أن يتكون من 5 أرقام بالضبط'
            ]" />
          </VCol>
          <VCol cols="12" md="4">
            <VTextField v-model="form.established_date" label="تاريخ التأسيس *" type="date" :error-messages="validationErrors.established_date" :rules="[v => !!v || 'مطلوب']" />
          </VCol>
        </VRow>
      </VCardText>
      <VCardActions class="pa-4">
        <VBtn variant="tonal" @click="prevStep" prepend-icon="tabler-arrow-right">السابق</VBtn>
        <VSpacer />
        <VBtn color="primary" @click="nextStep" append-icon="tabler-arrow-left">التالي</VBtn>
      </VCardActions>
    </VCard>

    <!-- Step 4: الوثائق والمستندات المطلوبة -->
    <VCard v-if="step === 4">
      <VCardTitle style="font-family:Cairo,sans-serif">الوثائق والمستندات المطلوبة</VCardTitle>
      <VCardText>
        <p class="text-body-2 text-medium-emphasis mb-4">يرجى رفع المستندات التالية بصيغة PDF أو كصور واضحة (يتحول الحقل للون الأخضر عند نجاح الاختيار):</p>
        <VRow>
          <VCol cols="12" md="6">
            <VFileInput
              v-model="form.cr_file"
              label="السجل التجاري *"
              accept=".pdf,image/*"
              class="custom-file-input"
              persistent-placeholder
              placeholder="انقر هنا لاختيار الملف أو سحبه"
              :color="form.cr_file ? 'success' : ''"
              :prepend-icon="form.cr_file ? 'tabler-circle-check' : 'tabler-cloud-upload'"
              :error-messages="validationErrors.cr_file"
              :rules="[v => !!v || 'مطلوب']"
            />
          </VCol>
          <VCol cols="12" md="6">
            <VFileInput
              v-model="form.company_register"
              label="مستخرج عن سجل الشركة *"
              accept=".pdf,image/*"
              class="custom-file-input"
              persistent-placeholder
              placeholder="انقر هنا لاختيار الملف أو سحبه"
              :color="form.company_register ? 'success' : ''"
              :prepend-icon="form.company_register ? 'tabler-circle-check' : 'tabler-cloud-upload'"
              :error-messages="validationErrors.company_register"
              :rules="[v => !!v || 'مطلوب']"
            />
          </VCol>
          <VCol cols="12" md="6">
            <VFileInput
              v-model="form.municipal_license"
              label="رخصة المهن (الحرف) سارية المفعول *"
              accept=".pdf,image/*"
              class="custom-file-input"
              persistent-placeholder
              placeholder="انقر هنا لاختيار الملف أو سحبه"
              :color="form.municipal_license ? 'success' : ''"
              :prepend-icon="form.municipal_license ? 'tabler-circle-check' : 'tabler-cloud-upload'"
              :error-messages="validationErrors.municipal_license"
              :rules="[v => !!v || 'مطلوب']"
            />
          </VCol>
          <VCol cols="12" md="6">
            <VFileInput
              v-model="form.bank_dealing_letter"
              label="شهادة تعامل للشركة مع بنك *"
              accept=".pdf,image/*"
              class="custom-file-input"
              persistent-placeholder
              placeholder="انقر هنا لاختيار الملف أو سحبه"
              :color="form.bank_dealing_letter ? 'success' : ''"
              :prepend-icon="form.bank_dealing_letter ? 'tabler-circle-check' : 'tabler-cloud-upload'"
              :error-messages="validationErrors.bank_dealing_letter"
              :rules="[v => !!v || 'مطلوب']"
            />
          </VCol>
          <VCol cols="12" md="6">
            <VFileInput
              v-model="form.articles_of_association"
              label="عقد تأسيس الشركة *"
              accept=".pdf,image/*"
              class="custom-file-input"
              persistent-placeholder
              placeholder="انقر هنا لاختيار الملف أو سحبه"
              :color="form.articles_of_association ? 'success' : ''"
              :prepend-icon="form.articles_of_association ? 'tabler-circle-check' : 'tabler-cloud-upload'"
              :error-messages="validationErrors.articles_of_association"
              :rules="[v => !!v || 'مطلوب']"
            />
          </VCol>
          <VCol cols="12" md="6">
            <VFileInput
              v-model="form.internal_bylaws"
              label="النظام الداخلي *"
              accept=".pdf,image/*"
              class="custom-file-input"
              persistent-placeholder
              placeholder="انقر هنا لاختيار الملف أو سحبه"
              :color="form.internal_bylaws ? 'success' : ''"
              :prepend-icon="form.internal_bylaws ? 'tabler-circle-check' : 'tabler-cloud-upload'"
              :error-messages="validationErrors.internal_bylaws"
              :rules="[v => !!v || 'مطلوب']"
            />
          </VCol>
          <VCol cols="12" md="6">
            <VFileInput
              v-model="form.lease_or_ownership_contract"
              label="عقد الإيجار أو الملكية لمقر الشركة *"
              accept=".pdf,image/*"
              class="custom-file-input"
              persistent-placeholder
              placeholder="انقر هنا لاختيار الملف أو سحبه"
              :color="form.lease_or_ownership_contract ? 'success' : ''"
              :prepend-icon="form.lease_or_ownership_contract ? 'tabler-circle-check' : 'tabler-cloud-upload'"
              :error-messages="validationErrors.lease_or_ownership_contract"
              :rules="[v => !!v || 'مطلوب']"
            />
          </VCol>
          <VCol cols="12" md="6">
            <VFileInput
              v-model="form.partners_ids"
              label="صور هويات الشركاء *"
              accept=".pdf,image/*"
              class="custom-file-input"
              persistent-placeholder
              placeholder="انقر هنا لاختيار الملف أو سحبه"
              :color="form.partners_ids ? 'success' : ''"
              :prepend-icon="form.partners_ids ? 'tabler-circle-check' : 'tabler-cloud-upload'"
              :error-messages="validationErrors.partners_ids"
              :rules="[v => !!v || 'مطلوب']"
            />
          </VCol>
          <VCol cols="12" md="6">
            <VFileInput
              v-model="form.authorization_letter"
              label="كتاب تفويض المعتمد بالتوقيع *"
              accept=".pdf,image/*"
              class="custom-file-input"
              persistent-placeholder
              placeholder="انقر هنا لاختيار الملف أو سحبه"
              :color="form.authorization_letter ? 'success' : ''"
              :prepend-icon="form.authorization_letter ? 'tabler-circle-check' : 'tabler-cloud-upload'"
              :error-messages="validationErrors.authorization_letter"
              :rules="[v => !!v || 'مطلوب']"
            />
          </VCol>
          <VCol cols="12" md="6">
            <VFileInput
              v-model="form.company_approval_letter"
              label="كتاب من الشركة بالموافقة على الانتساب *"
              accept=".pdf,image/*"
              class="custom-file-input"
              persistent-placeholder
              placeholder="انقر هنا لاختيار الملف أو سحبه"
              :color="form.company_approval_letter ? 'success' : ''"
              :prepend-icon="form.company_approval_letter ? 'tabler-circle-check' : 'tabler-cloud-upload'"
              :error-messages="validationErrors.company_approval_letter"
              :rules="[v => !!v || 'مطلوب']"
            />
          </VCol>
          <VCol cols="12" md="6">
            <VFileInput
              v-model="form.full_time_engineer_certificate"
              label="شهادة مهندس متفرغ *"
              accept=".pdf,image/*"
              class="custom-file-input"
              persistent-placeholder
              placeholder="انقر هنا لاختيار الملف أو سحبه"
              :color="form.full_time_engineer_certificate ? 'success' : ''"
              :prepend-icon="form.full_time_engineer_certificate ? 'tabler-circle-check' : 'tabler-cloud-upload'"
              :error-messages="validationErrors.full_time_engineer_certificate"
              :rules="[v => !!v || 'مطلوب']"
            />
          </VCol>
          <VCol cols="12" md="6">
            <VFileInput
              v-model="form.accountant_certificate_or_contract"
              label="شهادة تفرغ محاسب من نقابة المحاسبين / أو عقد مع مكتب محاسبين معتمد *"
              accept=".pdf,image/*"
              class="custom-file-input"
              persistent-placeholder
              placeholder="انقر هنا لاختيار الملف أو سحبه"
              :color="form.accountant_certificate_or_contract ? 'success' : ''"
              :prepend-icon="form.accountant_certificate_or_contract ? 'tabler-circle-check' : 'tabler-cloud-upload'"
              :error-messages="validationErrors.accountant_certificate_or_contract"
              :rules="[v => !!v || 'مطلوب']"
            />
          </VCol>
          <VCol cols="12" md="6">
            <VFileInput
              v-model="form.secretary_contract"
              label="عقد سكرتير *"
              accept=".pdf,image/*"
              class="custom-file-input"
              persistent-placeholder
              placeholder="انقر هنا لاختيار الملف أو سحبه"
              :color="form.secretary_contract ? 'success' : ''"
              :prepend-icon="form.secretary_contract ? 'tabler-circle-check' : 'tabler-cloud-upload'"
              :error-messages="validationErrors.secretary_contract"
              :rules="[v => !!v || 'مطلوب']"
            />
          </VCol>
          <VCol cols="12">
            <VTextarea v-model="form.notes" label="ملاحظات إضافية للمراجعة" rows="3" />
          </VCol>
        </VRow>
      </VCardText>
      <VCardActions class="pa-4">
        <VBtn variant="tonal" @click="prevStep" prepend-icon="tabler-arrow-right">السابق</VBtn>
        <VSpacer />
        <VBtn color="primary" :loading="loading" @click="submit" prepend-icon="tabler-check">
          حفظ وتسجيل المقاول
        </VBtn>
      </VCardActions>
    </VCard>
  </div>
</template>

<style scoped>
:deep(.v-chip__close) {
  color: #ff4d4f !important;
  opacity: 1 !important;
}

:deep(.custom-file-input .v-field) {
  border: 2px dashed rgba(var(--v-theme-primary), 0.3) !important;
  background-color: rgba(var(--v-theme-primary), 0.02) !important;
  border-radius: 8px !important;
  transition: border-color 0.2s ease, background-color 0.2s ease;
  min-height: 56px;
}
:deep(.custom-file-input .v-field:hover) {
  border-color: rgba(var(--v-theme-primary), 0.8) !important;
  background-color: rgba(var(--v-theme-primary), 0.05) !important;
}
:deep(.custom-file-input.v-field--success .v-field) {
  border: 2px solid rgba(var(--v-theme-success), 0.5) !important;
  background-color: rgba(var(--v-theme-success), 0.02) !important;
}
</style>

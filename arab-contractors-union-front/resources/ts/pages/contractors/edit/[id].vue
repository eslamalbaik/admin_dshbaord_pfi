<script setup lang="ts">
import api from '@/plugins/axios'
import { useRoute, useRouter } from 'vue-router'

definePage({ meta: { requiresAdmin: true, adminOnly: true } })

const route = useRoute()
const router = useRouter()
const step = ref(1)
const loading = ref(false)
const initLoading = ref(true)
const errorMsg = ref('')

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
  governorate_id: null as number | null,
  city_id: null as number | null,
  district: '',
  address: '',
  building: '',
  floor: '',
  trade: '',
  specialties: [
    { field_lk_type: null as number | null, specialization_lk_type: null as number | null, classification: '' }
  ] as Array<{ field_lk_type: number | null, specialization_lk_type: number | null, classification: string }>,
  established_date: '',
  license_number: '',
  classification: '',

  // Step 4: الوثائق والمستندات المطلوبة
  lease_or_ownership_contract: null as File | null,
  company_approval_letter: null as File | null,
  municipal_license: null as File | null,
  company_register: null as File | null,
  cr_file: null as File | null,
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

const removeSpecialtyDialog = ref(false)
const removingSpecialtyIndex = ref<number | null>(null)

const confirmRemoveSpecialty = (index: number) => {
  removingSpecialtyIndex.value = index
  removeSpecialtyDialog.value = true
}

const removeSpecialty = () => {
  if (removingSpecialtyIndex.value !== null)
    form.value.specialties.splice(removingSpecialtyIndex.value, 1)
  removeSpecialtyDialog.value = false
  removingSpecialtyIndex.value = null
}

// المجالات/الاختصاصات/الدرجات وربط المحافظات-المدن تُجلب من الخادم (مصدر واحد REQ-01 #6،
// بدل تكرار القوائم هنا ثابتة يدوياً كما كان سابقاً).
const fieldOptions = ref<Array<{ title: string, value: number }>>([])
const specializationOptions = ref<Array<{ title: string, value: number }>>([])
const fieldSpecializations = ref<Record<number, number[]>>({})
const gradeOptions = ref<Array<{ title: string, value: string }>>([])
const topTierFields = ref<number[]>([])
// التصنيف العام (contractors.classification) — منفصل عن تصنيف كل مجال/تخصص، ويقدر المقاول
// يعدّله من التطبيق (dashboard.vue: editClassification) لكن كان غير معروض إطلاقاً بفورم لوحة
// الأدمن، فيبدو للأدمن أن تعديل المقاول من التطبيق "لم ينعكس" رغم نجاح الحفظ فعلياً (TASK-03).
const overallGradeOptions = ref<Array<{ title: string, value: string }>>([])

const specializationOptionsFor = (fieldLkType: number | null) => {
  if (!fieldLkType || !fieldSpecializations.value[fieldLkType])
    return specializationOptions.value
  const allowed = fieldSpecializations.value[fieldLkType]
  return specializationOptions.value.filter(s => allowed.includes(s.value))
}

const gradeOptionsFor = (fieldLkType: number | null) =>
  topTierFields.value.includes(fieldLkType as number) ? gradeOptions.value : gradeOptions.value.filter(g => g.value !== 'اولى أ')

const governorates = ref<Array<{ id: number, name: string, cities: Array<{ id: number, name: string }> }>>([])
const citiesForGovernorate = (governorateId: number | null) =>
  governorates.value.find(g => g.id === governorateId)?.cities ?? []

const fetchCatalog = async () => {
  try {
    const { data } = await api.get('/api/v1/app/specialties-catalog')
    const items = data.items ?? data
    fieldOptions.value = (items.fields ?? []).map((f: any) => ({ title: f.name, value: f.id }))
    specializationOptions.value = (items.specializations ?? []).map((s: any) => ({ title: s.name, value: s.id }))
    fieldSpecializations.value = items.field_specializations ?? {}
    gradeOptions.value = (items.grades ?? []).map((g: any) => ({ title: g.label, value: g.value }))
    topTierFields.value = (items.grades ?? []).find((g: any) => g.eligible_fields)?.eligible_fields ?? []
    overallGradeOptions.value = (items.overall_grades ?? []).map((g: any) => ({ title: g.label, value: g.value }))
  } catch (err) {
    console.error('Failed to fetch specialties catalog', err)
  }
}

const fetchGovernorates = async () => {
  try {
    const { data } = await api.get('/api/v1/app/governorates')
    governorates.value = (data.items ?? data).governorates ?? []
  } catch (err) {
    console.error('Failed to fetch governorates', err)
  }
}

// روابط الملفات المرفوعة مسبقاً — تُعرض للمعاينة/التحميل جنب كل حقل رفع، بدل ما يظهر الحقل فاضي
const documentKeys = [
  'lease_or_ownership_contract', 'company_approval_letter', 'municipal_license', 'company_register',
  'cr_file', 'articles_of_association', 'internal_bylaws', 'bank_dealing_letter',
  'secretary_contract', 'full_time_engineer_certificate', 'accountant_certificate_or_contract',
  'partners_ids', 'authorization_letter',
] as const

const existingFiles = ref<Record<string, string | null>>({})

const fetchContractor = async () => {
  try {
    const { data } = await api.get(`/api/v1/contractors/${route.params.id}`)
    const contractor = data.data || data.items || data
    
    // Populate text fields only
    const textFields = [
      'membership_number', 'commercial_register', 'name', 'capital', 'registration_date',
      'legal_form', 'company_purposes', 'owner_name', 'authorized_person',
      'authorized_person_id_number', 'authorized_person_phone', 'authorized_person_whatsapp',
      'phone', 'fax', 'email', 'governorate_id', 'city_id', 'district', 'building', 'floor',
      'address', 'trade', 'established_date', 'license_number', 'classification', 'notes'
    ]
    
    const dateFields = ['established_date']

    textFields.forEach(field => {
      if (contractor[field] !== null && contractor[field] !== undefined) {
        (form.value as any)[field] = dateFields.includes(field)
          ? String(contractor[field]).substring(0, 10)
          : contractor[field]
      }
    })

    // Handle specialties mapping (multiple or single fallback)
    if (contractor.specialties) {
      form.value.specialties = typeof contractor.specialties === 'string'
        ? JSON.parse(contractor.specialties)
        : contractor.specialties
    } else {
      form.value.specialties = [
        {
          field_lk_type: contractor.field_lk_type,
          specialization_lk_type: contractor.specialization_lk_type,
          classification: contractor.classification
        }
      ]
    }

    // Handle partners mapping from comma-separated string to array
    if (contractor.partners) {
      form.value.partners = typeof contractor.partners === 'string'
        ? contractor.partners.split(',').filter(Boolean)
        : contractor.partners
    } else {
      form.value.partners = []
    }

    documentKeys.forEach(key => {
      existingFiles.value[key] = contractor[`${key}_url`] ?? contractor[key] ?? null
    })
  } catch (err) {
    errorMsg.value = 'فشل جلب بيانات المقاول.'
  } finally {
    // ننتظر أن يُفرَّغ الـ watch الخاص بـ governorate_id (يُجدوَل كـ microtask) قبل رفع initLoading،
    // وإلا يقرأ الـ watch القيمة الجديدة لـ initLoading قبل تنفيذه فعلياً ويمسح city_id المحمَّل لتوّه.
    await nextTick()
    initLoading.value = false
  }
}

onMounted(() => {
  fetchContractor()
  fetchCatalog()
  fetchGovernorates()
})

const nextStep = () => { if (step.value < 4) step.value++ }
const prevStep = () => { if (step.value > 1) step.value-- }

// لا يُفرَّغ city_id إلا عند تغيير المحافظة يدوياً بعد تحميل الملف — وإلا يُمسح city_id
// المحمَّل من fetchContractor فوراً لأن ضبط governorate_id يُطلق هذا الـ watch أيضاً.
watch(() => form.value.governorate_id, () => {
  if (!initLoading.value)
    form.value.city_id = null
})

const uploadProgress = ref(0)

const submit = async () => {
  loading.value = true
  uploadProgress.value = 0
  errorMsg.value = ''
  validationErrors.value = {}
  try {
    const fd = new FormData()
    fd.append('_method', 'PUT') // Laravel uses PUT for file updates via POST request

    Object.entries(form.value).forEach(([k, v]) => {
      // VFileInput يُعيد [] (وليس null) عند تفريغ حقل الملف — بدون هذا الفحص كان يُرسَل
      // مصفوفة فارغة كقيمة للحقل فيرفضها الخادم بخطأ "يجب أن يكون ملفاً" رغم عدم تعديل
      // المستخدم للملف أصلاً، وهو سبب الخطأ المُبلَّغ عنه بعد فشل التحديث وحذف كل المرفقات (REQ-01 #4).
      if (Array.isArray(v) && v.length === 0 && k !== 'partners' && k !== 'specialties')
        return
      if (v !== null) {
        if (k === 'partners' && Array.isArray(v)) {
          fd.append(k, v.join(','))
        } else if (k === 'specialties' && Array.isArray(v)) {
          fd.append(k, JSON.stringify(v))
        } else if (!(v instanceof File) || v.size > 0) {
          fd.append(k, v as any)
        }
      }
    })
    await api.post(`/api/v1/contractors/${route.params.id}`, fd, {
      headers: { 'Content-Type': 'multipart/form-data' },
      onUploadProgress: e => {
        uploadProgress.value = e.total ? Math.round((e.loaded / e.total) * 100) : 0
      },
    })
    router.push({ name: 'contractors' })
  }
  catch (err: any) {
    errorMsg.value = err?.response?.data?.message || 'فشل تحديث المقاول. يرجى التحقق من المدخلات.'
    
    const errors = err?.response?.data?.errors
    if (errors) {
        validationErrors.value = errors
        // Jump to the first step with an error if available
        if (errors.membership_number || errors.commercial_register || errors.name || errors.capital || errors.registration_date || errors.legal_form) {
            step.value = 1
        } else if (errors.owner_name || errors.partners || errors.authorized_person) {
            step.value = 2
        } else if (errors.phone || errors.fax || errors.email || errors.governorate_id || errors.city_id || errors.district || errors.address || errors.building || errors.floor || errors.license_number || errors.established_date || errors.classification || errors.specialties) {
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
  <div v-if="!initLoading">
    <div class="mb-6 d-flex align-center gap-4">
      <VBtn icon="tabler-arrow-right" variant="tonal" :to="{ name: 'contractors' }" />
      <div>
        <h1 class="text-h4 font-weight-bold" style="font-family:Cairo,sans-serif">تعديل بيانات المقاول</h1>
        <p class="text-body-2 text-medium-emphasis mb-0" style="font-family:Cairo,sans-serif">يمكنك تحديث بيانات المقاول أو رفع وثائق جديدة. سيتم الاحتفاظ بالوثائق القديمة إن لم ترفع جديدة.</p>
      </div>
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
          <VCol cols="12" md="6">
            <VTextField v-model="form.name" label="اسم طالب الانتساب (الشركة / المؤسسة) *" :rules="[v => !!v || 'مطلوب']" :error-messages="validationErrors.name" />
          </VCol>
          <VCol cols="12" md="3">
            <VTextField v-model="form.membership_number" readonly label="رقم العضوية بالاتحاد *" :error-messages="validationErrors.membership_number" :rules="[
              v => !!v || 'مطلوب',
              v => {
                if (/^[0-9]+$/.test(v)) return (parseInt(v) >= 1 && parseInt(v) <= 927) || 'أرقام العضوية القديمة يجب أن تكون بين 1 و 927'
                if (/^[0-9]+_g$/.test(v)) return parseInt(v.split('_')[0]) >= 928 || 'أرقام العضوية الجديدة يجب أن تبدأ من 928_g'
                return 'صيغة غير صحيحة (مثال: 100 أو 928_g)'
              }
            ]" />
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
                :error-messages="validationErrors.partners"
                placeholder="اكتب اسم الشريك واضغط إضافة أو Enter"
                prepend-icon="tabler-users"
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
              v-model="form.governorate_id"
              :items="governorates"
              item-title="name"
              item-value="id"
              label="المحافظة *"
              :error-messages="validationErrors.governorate_id"
              :rules="[v => !!v || 'مطلوب']"
            />
          </VCol>
          <VCol cols="12" md="4">
            <VSelect
              v-model="form.city_id"
              :items="citiesForGovernorate(form.governorate_id)"
              item-title="name"
              item-value="id"
              label="المدينة *"
              :disabled="!form.governorate_id"
              :error-messages="validationErrors.city_id"
              :rules="[v => !!v || 'مطلوب']"
            />
          </VCol>
          <VCol cols="12" md="4">
            <VTextField v-model="form.district" label="الحي *" :error-messages="validationErrors.district" :rules="[v => !!v || 'مطلوب']" />
          </VCol>
          <VCol cols="12" md="8">
            <VTextField v-model="form.address" label="العنوان التفصيلي (الشارع) *" :error-messages="validationErrors.address" :rules="[v => !!v || 'مطلوب']" />
          </VCol>
          <VCol cols="12" md="2">
            <VTextField v-model="form.building" label="العمارة *" :error-messages="validationErrors.building" :rules="[v => !!v || 'مطلوب']" />
          </VCol>
          <VCol cols="12" md="2">
            <VTextField v-model="form.floor" label="الطابق *" :error-messages="validationErrors.floor" :rules="[v => !!v || 'مطلوب']" />
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
                  @update:model-value="spec.specialization_lk_type = null"
                />
              </VCol>
              <VCol cols="12" md="4">
                <VSelect
                  v-model="spec.specialization_lk_type"
                  :items="specializationOptionsFor(spec.field_lk_type)"
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
                  @click="confirmRemoveSpecialty(index)"
                  title="حذف هذا المجال"
                >
                  <VIcon icon="tabler-trash" />
                </VBtn>
              </VCol>
            </VRow>

            <p v-if="!form.specialties.length" class="text-body-2 text-medium-emphasis mb-3" style="font-family:Cairo,sans-serif">
              لا يوجد مجال/تصنيف مضاف.
            </p>

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
            <VTextField v-model="form.established_date" label="تاريخ التأسيس *" :error-messages="validationErrors.established_date" type="date" :rules="[v => !!v || 'مطلوب']" />
          </VCol>
          <VCol cols="12" md="4">
            <VSelect
              v-model="form.classification"
              :items="overallGradeOptions"
              item-title="title"
              item-value="value"
              label="التصنيف العام"
              clearable
              :error-messages="validationErrors.classification"
              hint="يقدر المقاول يعدّله من التطبيق أيضاً — هذا الحقل يعرض ويسمح بتعديل نفس القيمة من لوحة الأدمن"
              persistent-hint
            />
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
        <p class="text-body-2 text-medium-emphasis mb-4">يرجى رفع المستندات الجديدة فقط في حال أردت تحديث المستندات القديمة (يتحول الحقل للون الأخضر عند اختيار ملف جديد):</p>
        <VRow>
          <VCol cols="12" md="6">
            <div v-if="existingFiles.cr_file && !form.cr_file" class="d-flex align-center gap-2 mb-1">
              <VChip size="small" color="success" variant="tonal" prepend-icon="tabler-circle-check">مرفوع</VChip>
              <a :href="existingFiles.cr_file!" target="_blank" class="text-body-2 d-flex align-center gap-1">
                <VIcon icon="tabler-eye" size="14" /> عرض الملف الحالي
              </a>
            </div>
            <VFileInput
              v-model="form.cr_file"
              label="السجل التجاري (للتحديث)"
              :error-messages="validationErrors.cr_file"
              accept=".pdf,.doc,.docx,image/*"
              class="custom-file-input"
              persistent-placeholder
              placeholder="انقر هنا لاختيار الملف أو سحبه"
              :color="form.cr_file ? 'success' : ''"
              :prepend-icon="form.cr_file ? 'tabler-circle-check' : 'tabler-cloud-upload'"
            />
          </VCol>
          <VCol cols="12" md="6">
            <div v-if="existingFiles.company_register && !form.company_register" class="d-flex align-center gap-2 mb-1">
              <VChip size="small" color="success" variant="tonal" prepend-icon="tabler-circle-check">مرفوع</VChip>
              <a :href="existingFiles.company_register!" target="_blank" class="text-body-2 d-flex align-center gap-1">
                <VIcon icon="tabler-eye" size="14" /> عرض الملف الحالي
              </a>
            </div>
            <VFileInput
              v-model="form.company_register"
              label="مستخرج عن سجل الشركة (للتحديث)"
              :error-messages="validationErrors.company_register"
              accept=".pdf,.doc,.docx,image/*"
              class="custom-file-input"
              persistent-placeholder
              placeholder="انقر هنا لاختيار الملف أو سحبه"
              :color="form.company_register ? 'success' : ''"
              :prepend-icon="form.company_register ? 'tabler-circle-check' : 'tabler-cloud-upload'"
            />
          </VCol>
          <VCol cols="12" md="6">
            <div v-if="existingFiles.municipal_license && !form.municipal_license" class="d-flex align-center gap-2 mb-1">
              <VChip size="small" color="success" variant="tonal" prepend-icon="tabler-circle-check">مرفوع</VChip>
              <a :href="existingFiles.municipal_license!" target="_blank" class="text-body-2 d-flex align-center gap-1">
                <VIcon icon="tabler-eye" size="14" /> عرض الملف الحالي
              </a>
            </div>
            <VFileInput
              v-model="form.municipal_license"
              label="رخصة المهن سارية المفعول (للتحديث)"
              :error-messages="validationErrors.municipal_license"
              accept=".pdf,.doc,.docx,image/*"
              class="custom-file-input"
              persistent-placeholder
              placeholder="انقر هنا لاختيار الملف أو سحبه"
              :color="form.municipal_license ? 'success' : ''"
              :prepend-icon="form.municipal_license ? 'tabler-circle-check' : 'tabler-cloud-upload'"
            />
          </VCol>
          <VCol cols="12" md="6">
            <div v-if="existingFiles.bank_dealing_letter && !form.bank_dealing_letter" class="d-flex align-center gap-2 mb-1">
              <VChip size="small" color="success" variant="tonal" prepend-icon="tabler-circle-check">مرفوع</VChip>
              <a :href="existingFiles.bank_dealing_letter!" target="_blank" class="text-body-2 d-flex align-center gap-1">
                <VIcon icon="tabler-eye" size="14" /> عرض الملف الحالي
              </a>
            </div>
            <VFileInput
              v-model="form.bank_dealing_letter"
              label="شهادة تعامل للشركة مع بنك (للتحديث)"
              :error-messages="validationErrors.bank_dealing_letter"
              accept=".pdf,.doc,.docx,image/*"
              class="custom-file-input"
              persistent-placeholder
              placeholder="انقر هنا لاختيار الملف أو سحبه"
              :color="form.bank_dealing_letter ? 'success' : ''"
              :prepend-icon="form.bank_dealing_letter ? 'tabler-circle-check' : 'tabler-cloud-upload'"
            />
          </VCol>
          <VCol cols="12" md="6">
            <div v-if="existingFiles.articles_of_association && !form.articles_of_association" class="d-flex align-center gap-2 mb-1">
              <VChip size="small" color="success" variant="tonal" prepend-icon="tabler-circle-check">مرفوع</VChip>
              <a :href="existingFiles.articles_of_association!" target="_blank" class="text-body-2 d-flex align-center gap-1">
                <VIcon icon="tabler-eye" size="14" /> عرض الملف الحالي
              </a>
            </div>
            <VFileInput
              v-model="form.articles_of_association"
              label="عقد تأسيس الشركة (للتحديث)"
              :error-messages="validationErrors.articles_of_association"
              accept=".pdf,.doc,.docx,image/*"
              class="custom-file-input"
              persistent-placeholder
              placeholder="انقر هنا لاختيار الملف أو سحبه"
              :color="form.articles_of_association ? 'success' : ''"
              :prepend-icon="form.articles_of_association ? 'tabler-circle-check' : 'tabler-cloud-upload'"
            />
          </VCol>
          <VCol cols="12" md="6">
            <div v-if="existingFiles.internal_bylaws && !form.internal_bylaws" class="d-flex align-center gap-2 mb-1">
              <VChip size="small" color="success" variant="tonal" prepend-icon="tabler-circle-check">مرفوع</VChip>
              <a :href="existingFiles.internal_bylaws!" target="_blank" class="text-body-2 d-flex align-center gap-1">
                <VIcon icon="tabler-eye" size="14" /> عرض الملف الحالي
              </a>
            </div>
            <VFileInput
              v-model="form.internal_bylaws"
              label="النظام الداخلي (للتحديث)"
              :error-messages="validationErrors.internal_bylaws"
              accept=".pdf,.doc,.docx,image/*"
              class="custom-file-input"
              persistent-placeholder
              placeholder="انقر هنا لاختيار الملف أو سحبه"
              :color="form.internal_bylaws ? 'success' : ''"
              :prepend-icon="form.internal_bylaws ? 'tabler-circle-check' : 'tabler-cloud-upload'"
            />
          </VCol>
          <VCol cols="12" md="6">
            <div v-if="existingFiles.lease_or_ownership_contract && !form.lease_or_ownership_contract" class="d-flex align-center gap-2 mb-1">
              <VChip size="small" color="success" variant="tonal" prepend-icon="tabler-circle-check">مرفوع</VChip>
              <a :href="existingFiles.lease_or_ownership_contract!" target="_blank" class="text-body-2 d-flex align-center gap-1">
                <VIcon icon="tabler-eye" size="14" /> عرض الملف الحالي
              </a>
            </div>
            <VFileInput
              v-model="form.lease_or_ownership_contract"
              label="عقد الإيجار أو الملكية لمقر الشركة (للتحديث)"
              :error-messages="validationErrors.lease_or_ownership_contract"
              accept=".pdf,.doc,.docx,image/*"
              class="custom-file-input"
              persistent-placeholder
              placeholder="انقر هنا لاختيار الملف أو سحبه"
              :color="form.lease_or_ownership_contract ? 'success' : ''"
              :prepend-icon="form.lease_or_ownership_contract ? 'tabler-circle-check' : 'tabler-cloud-upload'"
            />
          </VCol>
          <VCol cols="12" md="6">
            <div v-if="existingFiles.partners_ids && !form.partners_ids" class="d-flex align-center gap-2 mb-1">
              <VChip size="small" color="success" variant="tonal" prepend-icon="tabler-circle-check">مرفوع</VChip>
              <a :href="existingFiles.partners_ids!" target="_blank" class="text-body-2 d-flex align-center gap-1">
                <VIcon icon="tabler-eye" size="14" /> عرض الملف الحالي
              </a>
            </div>
            <VFileInput
              v-model="form.partners_ids"
              label="صور هويات الشركاء (للتحديث)"
              :error-messages="validationErrors.partners_ids"
              accept=".pdf,.doc,.docx,image/*"
              class="custom-file-input"
              persistent-placeholder
              placeholder="انقر هنا لاختيار الملف أو سحبه"
              :color="form.partners_ids ? 'success' : ''"
              :prepend-icon="form.partners_ids ? 'tabler-circle-check' : 'tabler-cloud-upload'"
            />
          </VCol>
          <VCol cols="12" md="6">
            <div v-if="existingFiles.authorization_letter && !form.authorization_letter" class="d-flex align-center gap-2 mb-1">
              <VChip size="small" color="success" variant="tonal" prepend-icon="tabler-circle-check">مرفوع</VChip>
              <a :href="existingFiles.authorization_letter!" target="_blank" class="text-body-2 d-flex align-center gap-1">
                <VIcon icon="tabler-eye" size="14" /> عرض الملف الحالي
              </a>
            </div>
            <VFileInput
              v-model="form.authorization_letter"
              label="كتاب تفويض المعتمد بالتوقيع (للتحديث)"
              :error-messages="validationErrors.authorization_letter"
              accept=".pdf,.doc,.docx,image/*"
              class="custom-file-input"
              persistent-placeholder
              placeholder="انقر هنا لاختيار الملف أو سحبه"
              :color="form.authorization_letter ? 'success' : ''"
              :prepend-icon="form.authorization_letter ? 'tabler-circle-check' : 'tabler-cloud-upload'"
            />
          </VCol>
          <VCol cols="12" md="6">
            <div v-if="existingFiles.company_approval_letter && !form.company_approval_letter" class="d-flex align-center gap-2 mb-1">
              <VChip size="small" color="success" variant="tonal" prepend-icon="tabler-circle-check">مرفوع</VChip>
              <a :href="existingFiles.company_approval_letter!" target="_blank" class="text-body-2 d-flex align-center gap-1">
                <VIcon icon="tabler-eye" size="14" /> عرض الملف الحالي
              </a>
            </div>
            <VFileInput
              v-model="form.company_approval_letter"
              label="كتاب موافقة على الانتساب (للتحديث)"
              :error-messages="validationErrors.company_approval_letter"
              accept=".pdf,.doc,.docx,image/*"
              class="custom-file-input"
              persistent-placeholder
              placeholder="انقر هنا لاختيار الملف أو سحبه"
              :color="form.company_approval_letter ? 'success' : ''"
              :prepend-icon="form.company_approval_letter ? 'tabler-circle-check' : 'tabler-cloud-upload'"
            />
          </VCol>
          <VCol cols="12" md="6">
            <div v-if="existingFiles.full_time_engineer_certificate && !form.full_time_engineer_certificate" class="d-flex align-center gap-2 mb-1">
              <VChip size="small" color="success" variant="tonal" prepend-icon="tabler-circle-check">مرفوع</VChip>
              <a :href="existingFiles.full_time_engineer_certificate!" target="_blank" class="text-body-2 d-flex align-center gap-1">
                <VIcon icon="tabler-eye" size="14" /> عرض الملف الحالي
              </a>
            </div>
            <VFileInput
              v-model="form.full_time_engineer_certificate"
              label="شهادة مهندس متفرغ (للتحديث)"
              :error-messages="validationErrors.full_time_engineer_certificate"
              accept=".pdf,.doc,.docx,image/*"
              class="custom-file-input"
              persistent-placeholder
              placeholder="انقر هنا لاختيار الملف أو سحبه"
              :color="form.full_time_engineer_certificate ? 'success' : ''"
              :prepend-icon="form.full_time_engineer_certificate ? 'tabler-circle-check' : 'tabler-cloud-upload'"
            />
          </VCol>
          <VCol cols="12" md="6">
            <div v-if="existingFiles.accountant_certificate_or_contract && !form.accountant_certificate_or_contract" class="d-flex align-center gap-2 mb-1">
              <VChip size="small" color="success" variant="tonal" prepend-icon="tabler-circle-check">مرفوع</VChip>
              <a :href="existingFiles.accountant_certificate_or_contract!" target="_blank" class="text-body-2 d-flex align-center gap-1">
                <VIcon icon="tabler-eye" size="14" /> عرض الملف الحالي
              </a>
            </div>
            <VFileInput
              v-model="form.accountant_certificate_or_contract"
              label="شهادة تفرغ محاسب من نقابة المحاسبين / أو عقد مع مكتب محاسبين معتمد (للتحديث)"
              :error-messages="validationErrors.accountant_certificate_or_contract"
              accept=".pdf,.doc,.docx,image/*"
              class="custom-file-input"
              persistent-placeholder
              placeholder="انقر هنا لاختيار الملف أو سحبه"
              :color="form.accountant_certificate_or_contract ? 'success' : ''"
              :prepend-icon="form.accountant_certificate_or_contract ? 'tabler-circle-check' : 'tabler-cloud-upload'"
            />
          </VCol>
          <VCol cols="12" md="6">
            <div v-if="existingFiles.secretary_contract && !form.secretary_contract" class="d-flex align-center gap-2 mb-1">
              <VChip size="small" color="success" variant="tonal" prepend-icon="tabler-circle-check">مرفوع</VChip>
              <a :href="existingFiles.secretary_contract!" target="_blank" class="text-body-2 d-flex align-center gap-1">
                <VIcon icon="tabler-eye" size="14" /> عرض الملف الحالي
              </a>
            </div>
            <VFileInput
              v-model="form.secretary_contract"
              label="عقد سكرتير (للتحديث)"
              :error-messages="validationErrors.secretary_contract"
              accept=".pdf,.doc,.docx,image/*"
              class="custom-file-input"
              persistent-placeholder
              placeholder="انقر هنا لاختيار الملف أو سحبه"
              :color="form.secretary_contract ? 'success' : ''"
              :prepend-icon="form.secretary_contract ? 'tabler-circle-check' : 'tabler-cloud-upload'"
            />
          </VCol>
          <VCol cols="12">
            <VTextarea v-model="form.notes" label="ملاحظات إضافية للمراجعة" rows="3" />
          </VCol>
        </VRow>
      </VCardText>
      <VCardText v-if="loading" class="pt-0">
        <VProgressLinear :model-value="uploadProgress" color="success" height="8" rounded striped>
          <template #default>
            <span class="text-caption font-weight-medium">{{ uploadProgress }}%</span>
          </template>
        </VProgressLinear>
      </VCardText>
      <VCardActions class="pa-4">
        <VBtn variant="tonal" @click="prevStep" prepend-icon="tabler-arrow-right">السابق</VBtn>
        <VSpacer />
        <VBtn color="primary" :loading="loading" @click="submit" prepend-icon="tabler-check">
          حفظ التعديلات
        </VBtn>
      </VCardActions>
    </VCard>

    <!-- Confirm remove specialty dialog -->
    <VDialog v-model="removeSpecialtyDialog" max-width="400">
      <VCard>
        <VCardTitle class="d-flex align-center gap-2" style="font-family:Cairo,sans-serif">
          <VIcon icon="tabler-alert-triangle" color="error" />
          تأكيد الحذف
        </VCardTitle>
        <VCardText style="font-family:Cairo,sans-serif">
          هل أنت متأكد من حذف هذا المجال والتصنيف؟ لا يمكن التراجع عن هذا الإجراء.
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn variant="tonal" @click="removeSpecialtyDialog = false">إلغاء</VBtn>
          <VBtn color="error" @click="removeSpecialty">حذف</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
  <div v-else class="d-flex justify-center align-center h-100 mt-10">
    <VProgressCircular indeterminate color="primary" />
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

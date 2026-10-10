<script setup lang="ts">
import api from '@/plugins/axios'
import { subjectLabel } from '@/utils/activityLogDetails'

definePage({ meta: { requiresAdmin: true, adminOnly: true } })

const logs = ref<any[]>([])
const loading = ref(false)
const total = ref(0)
const page = ref(1)
const itemsPerPage = ref(20)

const search = ref('')
const actionFilter = ref('')
const fromFilter = ref('')
const toFilter = ref('')

const actionOptions = ref<string[]>([])

// تبويب «المحددات الهامة»: أحداث مخالفة للوضع الطبيعي (تعديل رسوم العضوية، تعديل
// إصدار شهادة عضوية...) — تعريفها بالباك إند (App\Support\CriticalEvents)، فأي نوع
// جديد بيظهر هون تلقائياً مع مسمّاه وتفاصيله قبل/بعد.
// تبويب «سجل النشاط المالي»: الدفعات والذمم والخصومات والغرامات وتعديل الرصيد والرسوم —
// تصنيفها بالباك إند (App\Support\FinancialEvents).
const tab = ref<'all' | 'critical' | 'financial'>('all')
const categoryFilter = ref('')
const criticalSummary = ref<{ total: number; categories: { key: string; label: string; count: number }[] }>({ total: 0, categories: [] })
const isCritical = computed(() => tab.value === 'critical')
const isFinancial = computed(() => tab.value === 'financial')
const financialTotal = ref(0)

const fetchActions = async () => {
  try {
    const { data } = await api.get('/api/v1/dashboard/activity-logs/actions', {
      params: { critical: isCritical.value ? 1 : undefined, financial: isFinancial.value ? 1 : undefined },
    })
    actionOptions.value = data.items ?? []
  }
  catch (err) {
    console.error(err)
  }
}

const fetchCriticalSummary = async () => {
  try {
    const { data } = await api.get('/api/v1/dashboard/activity-logs/critical-summary')
    criticalSummary.value = data.items ?? { total: 0, categories: [] }
  }
  catch (err) {
    console.error(err)
  }
}

const fetchFinancialSummary = async () => {
  try {
    const { data } = await api.get('/api/v1/dashboard/activity-logs/financial-summary')
    financialTotal.value = data.items?.total ?? 0
  }
  catch (err) {
    console.error(err)
  }
}

watch(tab, () => {
  page.value = 1
  actionFilter.value = ''
  categoryFilter.value = ''
  fetchActions()
})

const fetchLogs = async () => {
  loading.value = true
  try {
    const { data } = await api.get('/api/v1/dashboard/activity-logs', {
      params: {
        search: search.value,
        action: actionFilter.value,
        critical: isCritical.value ? 1 : undefined,
        financial: isFinancial.value ? 1 : undefined,
        category: isCritical.value && categoryFilter.value ? categoryFilter.value : undefined,
        from: fromFilter.value || undefined,
        to: toFilter.value || undefined,
        page: page.value,
        per_page: itemsPerPage.value,
      },
    })
    logs.value = data.items ?? []
    total.value = data.meta?.total ?? logs.value.length
  }
  catch (err) {
    console.error(err)
    logs.value = []
    total.value = 0
  }
  finally {
    loading.value = false
  }
}

// ترجمة أكواد الإجراء (action) المخزّنة بالإنجليزي لعرض عربي مفهوم — أي كود غير
// موجود بالقائمة يظهر كما هو بدل ما يختفي أو يسبب خطأ.
const actionLabels: Record<string, string> = {
  'contractor.frozen': 'تجميد حساب مقاول',
  'contractor.unfrozen': 'رفع تجميد حساب مقاول',
  'contractor.equipment_banned': 'حظر معدّات مقاول من السوق',
  'contractor.equipment_unbanned': 'رفع حظر معدّات مقاول',
  'certificate.admin_issued_membership': 'إصدار شهادة عضوية يدوياً',
  'certificate.regenerated': 'إعادة توليد شهادة',
  'certificate.bulk_deleted': 'حذف شهادات بالجملة',
  'city.created': 'إضافة مدينة',
  'city.updated': 'تعديل مدينة',
  'city.deactivated': 'تعطيل مدينة',
  'governorate.created': 'إضافة محافظة',
  'governorate.updated': 'تعديل محافظة',
  'governorate.deactivated': 'تعطيل محافظة',
  'contractor_field.created': 'إضافة مجال',
  'contractor_field.updated': 'تعديل مجال',
  'contractor_field.deactivated': 'تعطيل مجال',
  'contractor_specialization.created': 'إضافة تخصص',
  'contractor_specialization.updated': 'تعديل تخصص',
  'contractor_specialization.deactivated': 'تعطيل تخصص',
  'contractor_grade.created': 'إضافة درجة تصنيف',
  'contractor_grade.updated': 'تعديل درجة تصنيف',
  'contractor_grade.deactivated': 'تعطيل درجة تصنيف',
  'grade_fee.updated': 'تعديل رسوم درجة',
  'payment.confirmed': 'اعتماد دفعة',
  'payment.rejected': 'رفض دفعة',
  'payment.receipt_image_uploaded': 'رفع إيصال دفعة',
  'payment.created': 'تسجيل معاملة دفع',
  'payment.manual_created': 'تسجيل دفعة يدوية',
  'payment.status_changed': 'تغيير حالة دفعة',
  'balance.adjusted': 'تعديل رصيد مقاول يدوياً',
  'membership.renewal_reversed': 'إلغاء تجديد عضوية بعد عكس دفعتها',
  'supervisor.created': 'إنشاء مشرف',
  'supervisor.updated': 'تعديل بيانات أو صلاحيات مشرف',
  'supervisor.status_changed': 'تفعيل/تعطيل حساب مشرف',
  'supervisor.password_reset': 'إعادة تعيين كلمة مرور مشرف',
  'settings.service_icon_uploaded': 'رفع أيقونة خدمة',
  'legal_file_category.created': 'إضافة تصنيف ملفات قانونية',
  'legal_file_category.updated': 'تعديل تصنيف ملفات قانونية',
  'legal_file_category.deleted': 'حذف تصنيف ملفات قانونية',
  'notifications.broadcast': 'إرسال إشعار جماعي',
  'security.unauthorized_access_attempt': 'محاولة وصول غير مصرّح بها',

  // المقاولون
  'contractor.created': 'إضافة مقاول',
  'contractor.updated': 'تعديل بيانات مقاول',
  'contractor.deleted': 'حذف مقاول',
  'contractor.status_changed': 'تغيير حالة مقاول',
  'contractor.contact_updated': 'تعديل بيانات تواصل مقاول',

  // الأخبار
  'news.created': 'نشر خبر',
  'news.updated': 'تعديل خبر',
  'news.deleted': 'حذف خبر',

  // الفعاليات
  'event.created': 'إضافة فعالية',
  'event.updated': 'تعديل فعالية',
  'event.deleted': 'حذف فعالية',

  // التعميمات
  'announcement.created': 'نشر تعميم',
  'announcement.updated': 'تعديل تعميم',
  'announcement.deleted': 'حذف تعميم',
  'announcement_category.created': 'إضافة تصنيف تعميمات',
  'announcement_category.updated': 'تعديل تصنيف تعميمات',
  'announcement_category.deleted': 'حذف تصنيف تعميمات',

  // الشروط والأحكام
  'term.created': 'إضافة نص شروط',
  'term.updated': 'تعديل نص شروط',
  'term.deleted': 'حذف نص شروط',

  // المكتبة القانونية
  'legal_file.created': 'رفع ملف قانوني',
  'legal_file.updated': 'تعديل ملف قانوني',
  'legal_file.deleted': 'حذف ملف قانوني',

  // الحسابات البنكية
  'bank_account.created': 'إضافة حساب بنكي',
  'bank_account.updated': 'تعديل حساب بنكي',
  'bank_account.deleted': 'حذف حساب بنكي',

  // الصفحات الديناميكية
  'page.created': 'إضافة صفحة',
  'page.updated': 'تعديل صفحة',
  'page.deleted': 'حذف صفحة',

  // إعدادات النظام
  'settings.updated': 'تعديل الإعدادات العامة',
  'settings.logo_uploaded': 'رفع شعار الاتحاد',
  'settings.cover_image_uploaded': 'رفع صورة الغلاف',

  // العضويات
  'membership.created': 'إنشاء طلب عضوية',
  'membership.approved': 'الموافقة على عضوية',
  'membership.rejected': 'رفض طلب عضوية',

  // شهادات العضوية
  'certificate.issued': 'إصدار شهادة',
  'certificate.deleted': 'حذف طلب شهادة',

  // تذاكر الدعم الفني
  'support_ticket.replied': 'الرد على تذكرة دعم',
  'support_ticket.status_changed': 'تغيير حالة تذكرة دعم',
  'support_ticket.deleted': 'حذف تذكرة دعم',

  // الغرامات
  'penalty.created': 'إضافة غرامة',
  'penalty.status_changed': 'تغيير حالة غرامة',
  'penalty.deleted': 'حذف غرامة',
  'penalty.settled': 'تسديد غرامة',
  'penalty.settled_from_credit': 'تسديد غرامة من الرصيد الدائن',
  'penalty.settlement_reversed': 'عكس تسديد غرامة',

  // العطاءات
  'tender.created': 'إضافة عطاء',
  'tender.updated': 'تعديل عطاء',
  'tender.deleted': 'حذف عطاء',
  'tender.attachment_added': 'إضافة مرفق عطاء',
  'tender.attachment_deleted': 'حذف مرفق عطاء',
  'tender_category.created': 'إضافة تصنيف عطاءات',
  'tender_category.updated': 'تعديل تصنيف عطاءات',
  'tender_category.deleted': 'حذف تصنيف عطاءات',

  // الوثائق
  'document.created': 'رفع وثيقة',
  'document.deleted': 'حذف وثيقة',

  // الذمم المالية
  'due.created': 'إضافة ذمة مالية',
  'due.updated': 'تعديل ذمة مالية',
  'due.settled': 'تسوية ذمة مالية',
  'due.deleted': 'حذف ذمة مالية',
  'due.deleted_bulk': 'حذف ذمم بالجملة',
  'due.settled_from_credit': 'تسديد ذمة من الرصيد الدائن',
  'due.settlement_reversed': 'عكس تسديد ذمة',
  'due.contractor_payment': 'تسجيل دفعة ذمم لمقاول',
  'due.excel_imported': 'استيراد ذمم من ملف Excel',
  'due.fee_generated': 'توليد ذمة رسوم عضوية',
  'due.fee_generated_bulk': 'توليد ذمم رسوم بالجملة',
  'due.discount_applied': 'تطبيق خصم على ذمة',
  'due.discount_applied_bulk': 'تطبيق خصم جماعي على الذمم',

  // سوق الآليات
  'equipment.created': 'إضافة آلية',
  'equipment.updated': 'تعديل آلية',
  'equipment.deleted': 'حذف آلية',
  'equipment_type.created': 'إضافة نوع آلية',
  'equipment_type.updated': 'تعديل نوع آلية',
  'equipment_type.deleted': 'حذف نوع آلية',
  'equipment_package.created': 'إضافة باقة اشتراك',
  'equipment_package.updated': 'تعديل باقة اشتراك',
  'equipment_package.deleted': 'حذف باقة اشتراك',
  'equipment_report.status_changed': 'تحديث حالة بلاغ آلية',

  // أسعار الصرف
  'exchange_rate.manual_override': 'تحديد سعر صرف يدوي',
}

const actionLabel = (action: string) => actionLabels[action] ?? action

// الحدث الحرج بيعرض مسمّاه من سجل المحددات الهامة (أدق، مثلاً «استبدال ملف شهادة صادرة»)
const logLabel = (log: any) => log.critical?.label ?? actionLabel(log.action)

const actionColor = (action: string) => {
  if (action.startsWith('security.')) return 'error'
  if (action.includes('reject') || action.includes('ban') || action.includes('freeze') || action.includes('frozen') || action.includes('deleted') || action.includes('deactivat')) return 'warning'
  if (action.includes('created') || action.includes('confirmed') || action.includes('issued')) return 'success'

  return 'info'
}

watchEffect(() => fetchLogs())
onMounted(() => {
  fetchActions()
  fetchCriticalSummary()
  fetchFinancialSummary()
})

const formatDate = (v: string) => v ? new Date(v).toLocaleString('ar-PS', { year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit' }) : '—'

const headers = [
  { title: 'الإجراء', key: 'action' },
  { title: 'قام به', key: 'actor_name' },
  { title: 'العنصر المتأثر', key: 'subject' },
  { title: 'الوقت', key: 'created_at' },
  { title: 'تفاصيل', key: 'meta', sortable: false },
]

const metaDialog = ref(false)
const metaItem = ref<any>(null)
const openMeta = (log: any) => { metaItem.value = log; metaDialog.value = true }
</script>

<template>
  <div>
    <div class="mb-6">
      <h1 class="text-h4 font-weight-bold" style="font-family:Cairo,sans-serif">سجل النشاط</h1>
      <p class="text-body-2 text-medium-emphasis mb-0" style="font-family:Cairo,sans-serif">
        كل الإجراءات الحساسة اللي بينفذها فريق الإدارة على لوحة التحكم — لأغراض المتابعة والمساءلة
      </p>
    </div>

    <VCard>
      <VTabs v-model="tab" class="px-4" style="font-family:Cairo,sans-serif">
        <VTab value="all">
          <VIcon icon="tabler-list-details" start />
          كل النشاط
        </VTab>
        <VTab value="critical">
          <VIcon icon="tabler-alert-triangle" start color="error" />
          المحددات الهامة
          <VChip v-if="criticalSummary.total" size="x-small" color="error" class="ms-2">
            {{ criticalSummary.total }}
          </VChip>
        </VTab>
        <VTab value="financial">
          <VIcon icon="tabler-cash" start color="warning" />
          سجل النشاط المالي
          <VChip v-if="financialTotal" size="x-small" color="warning" class="ms-2">
            {{ financialTotal }}
          </VChip>
        </VTab>
      </VTabs>
      <VDivider />

      <VAlert v-if="isCritical" type="warning" variant="tonal" density="compact" class="ma-4 mb-0" style="font-family:Cairo,sans-serif">
        تعديلات مخالفة للوضع الطبيعي: تعديل رسوم العضوية (جدول الرسوم والخصومات) وأي تعديل على إصدار شهادة العضوية، وإنشاء المشرفين وتعديل صلاحياتهم — مع القيمة قبل وبعد التعديل والسبب.
      </VAlert>

      <VAlert v-if="isFinancial" type="info" variant="tonal" density="compact" class="ma-4 mb-0" style="font-family:Cairo,sans-serif">
        الإجراءات المالية فقط: الدفعات واعتمادها أو رفضها أو تغيير حالتها، الذمم والخصومات والتسويات، الغرامات، تعديل الرصيد اليدوي، رسوم الدرجات وسعر الصرف.
      </VAlert>

      <VCardText class="d-flex gap-4 flex-wrap">
        <VTextField
          v-model="search"
          placeholder="بحث باسم المستخدم أو نوع الإجراء..."
          prepend-inner-icon="tabler-search"
          density="compact"
          style="max-width:280px"
          @update:model-value="page = 1"
        />
        <VSelect
          v-model="actionFilter"
          :items="[{ title: 'كل الإجراءات', value: '' }, ...actionOptions.map(a => ({ title: actionLabel(a), value: a }))]"
          item-title="title"
          item-value="value"
          label="الإجراء"
          density="compact"
          clearable
          style="max-width:220px"
          @update:model-value="page = 1"
        />
        <VSelect
          v-if="isCritical"
          v-model="categoryFilter"
          :items="[{ title: 'كل الفئات', value: '' }, ...criticalSummary.categories.map(c => ({ title: `${c.label} (${c.count})`, value: c.key }))]"
          item-title="title"
          item-value="value"
          label="الفئة"
          density="compact"
          style="max-width:220px"
          @update:model-value="page = 1"
        />
        <VTextField
          v-model="fromFilter"
          type="date"
          label="من تاريخ"
          density="compact"
          clearable
          style="max-width:170px"
          @update:model-value="page = 1"
        />
        <VTextField
          v-model="toFilter"
          type="date"
          label="إلى تاريخ"
          density="compact"
          clearable
          style="max-width:170px"
          @update:model-value="page = 1"
        />
      </VCardText>

      <VDataTableServer
        :headers="headers"
        :items="logs"
        :items-length="total"
        :loading="loading"
        v-model:page="page"
        v-model:items-per-page="itemsPerPage"
        :items-per-page-options="[20, 50, 100]"
        mobile-breakpoint="sm"
        @update:options="fetchLogs"
      >
        <template #item.action="{ item }">
          <VChip
            :color="item.is_critical ? 'error' : actionColor(item.action)"
            :prepend-icon="item.is_critical ? 'tabler-alert-triangle' : undefined"
            size="small"
            label
            style="font-family:Cairo,sans-serif"
          >
            {{ logLabel(item) }}
          </VChip>
        </template>

        <template #item.actor_name="{ item }">
          <div v-if="item.actor_name" style="font-family:Cairo,sans-serif">
            <div class="font-weight-medium">{{ item.actor_name }}</div>
            <div class="text-caption text-medium-emphasis">{{ item.actor_email }}</div>
          </div>
          <span v-else class="text-medium-emphasis text-body-2">النظام</span>
        </template>

        <template #item.subject="{ item }">
          <div v-if="item.contractor?.name || item.meta?.contractor_name" class="text-body-2" style="font-family:Cairo,sans-serif">
            <div>{{ item.contractor?.name ?? item.meta.contractor_name }}</div>
            <div v-if="item.contractor?.membership_number ?? item.meta?.membership_number" class="text-caption text-medium-emphasis">
              عضوية {{ item.contractor?.membership_number ?? item.meta.membership_number }}
            </div>
          </div>
          <span v-else-if="item.meta?.grade_label && item.action === 'grade_fee.updated'" class="text-body-2" style="font-family:Cairo,sans-serif">
            درجة {{ item.meta.grade_label }}
          </span>
          <span v-else-if="item.subject_type" class="text-body-2" style="font-family:Cairo,sans-serif">
            {{ subjectLabel(item.subject_type, item.subject_id) }}
          </span>
          <span v-else class="text-medium-emphasis text-body-2">—</span>
        </template>

        <template #item.created_at="{ item }">
          <span dir="ltr">{{ formatDate(item.created_at) }}</span>
        </template>

        <template #item.meta="{ item }">
          <VBtn icon size="small" variant="text" color="info" @click="openMeta(item)">
            <VIcon icon="tabler-info-circle" />
            <VTooltip activator="parent">عرض التفاصيل</VTooltip>
          </VBtn>
        </template>

        <template #no-data>
          <div class="text-center pa-6 text-medium-emphasis" style="font-family:Cairo,sans-serif">لا توجد سجلات</div>
        </template>
      </VDataTableServer>
    </VCard>

    <ActivityLogDetailsDialog v-model="metaDialog" :log="metaItem" :title="metaItem ? logLabel(metaItem) : ''" />
  </div>
</template>

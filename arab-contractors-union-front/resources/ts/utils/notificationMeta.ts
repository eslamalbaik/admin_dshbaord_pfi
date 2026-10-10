/**
 * شكل الإشعار في لوحة التحكم (العنوان، الأيقونة، اللون، وهل هو مالي) بحسب نوعه.
 * مصدر واحد يستخدمه التنبيه المنبثق، والقائمة المنسدلة للجرس، وصفحة /notifications
 * حتى يظهر الإشعار نفسه بالشكل نفسه في كل مكان.
 *
 * المفاتيح يجب أن تطابق حرفياً قيمة 'type' في app/Notifications/*.php.
 */

// لون ثابت للإشعارات المالية — ذهبي يتميّز عن الكحلي الأساسي للوحة وعن ألوان
// النجاح/التحذير/الخطأ المستخدمة لباقي الأنواع.
export const FINANCIAL_COLOR = '#B7791F'

export interface NotificationMeta {
  title: string
  icon: string
  color: string
  financial: boolean
}

const TYPE_MAP: Record<string, Omit<NotificationMeta, 'financial'> & { financial?: boolean }> = {
  // حركة مالية
  payment_submitted: { title: 'إشعار تحويل بانتظار التأكيد', icon: 'tabler-wallet', color: FINANCIAL_COLOR, financial: true },
  payment_confirmed: { title: 'تم تأكيد الدفعة', icon: 'tabler-wallet', color: FINANCIAL_COLOR, financial: true },
  payment_rejected: { title: 'رُفضت الدفعة', icon: 'tabler-wallet', color: FINANCIAL_COLOR, financial: true },
  payment_reminder: { title: 'تذكير بالدفع', icon: 'tabler-wallet', color: FINANCIAL_COLOR, financial: true },
  penalty_added: { title: 'غرامة جديدة', icon: 'tabler-wallet', color: FINANCIAL_COLOR, financial: true },
  dues_overdue: { title: 'ذمم متأخرة', icon: 'tabler-wallet', color: FINANCIAL_COLOR, financial: true },

  // طلبات المقاولين
  name_change_request_submitted: { title: 'طلب تعديل اسم شركة', icon: 'tabler-signature', color: 'warning' },
  name_change_request_status: { title: 'تحديث طلب تعديل الاسم', icon: 'tabler-signature', color: 'info' },
  profile_update_request_submitted: { title: 'طلب تعديل بيانات', icon: 'tabler-user-edit', color: 'warning' },
  profile_update_request_status: { title: 'تحديث طلب تعديل البيانات', icon: 'tabler-user-edit', color: 'info' },
  contractor_profile_updated: { title: 'تحديث الملف التعريفي', icon: 'tabler-user-edit', color: 'info' },
  certificate_request_submitted: { title: 'طلب شهادة جديد', icon: 'tabler-certificate', color: 'warning' },
  certificate_request_status: { title: 'تحديث طلب شهادة', icon: 'tabler-certificate', color: 'info' },

  // عضوية
  contractor_activated: { title: 'تفعيل حساب مقاول', icon: 'tabler-user-check', color: 'success' },
  complete_profile: { title: 'استكمال الملف التعريفي', icon: 'tabler-user-exclamation', color: 'secondary' },
  membership_expiry_reminder: { title: 'العضوية على وشك الانتهاء', icon: 'tabler-clock-exclamation', color: 'warning' },
  membership_grace_period_reminder: { title: 'مهلة تجديد العضوية', icon: 'tabler-clock-off', color: 'error' },

  // فعاليات ودعم
  event_joined: { title: 'تسجيل حضور فعالية', icon: 'tabler-calendar-event', color: 'primary' },
  support_ticket_created: { title: 'طلب دعم جديد', icon: 'tabler-lifebuoy', color: 'warning' },
  support_ticket_replied: { title: 'رد على طلب دعم', icon: 'tabler-message-reply', color: 'info' },
  support_ticket_contractor_replied: { title: 'رد المقاول على طلب دعم', icon: 'tabler-message-reply', color: 'warning' },

  // نظام
  admin_broadcast: { title: 'إعلان من الاتحاد', icon: 'tabler-speakerphone', color: 'primary' },
  pma_rates_fetch_failed: { title: 'تعذّر جلب أسعار الصرف', icon: 'tabler-alert-triangle', color: 'error' },
}

// البث الفوري (Reverb) يستبدل data.type باسم الكلاس، مثل
// App\Notifications\PaymentSubmittedNotification — نرجعه لنفس مفتاح قاعدة البيانات.
const CLASS_TO_TYPE: Record<string, string> = {
  PaymentSubmittedNotification: 'payment_submitted',
  ContractorActivatedNotification: 'contractor_activated',
  ContractorProfileUpdatedNotification: 'contractor_profile_updated',
  EventJoinedNotification: 'event_joined',
  AdminBroadcastNotification: 'admin_broadcast',
}

// أي نوع جديد اسمه يدل على حركة مالية يُعامل كمالي تلقائياً حتى لو لم يُضف للقائمة أعلاه.
const FINANCIAL_PATTERN = /payment|(?:^|_)dues?(?:_|$)|penalt|balance|transfer|wallet|refund/i

export function normalizeNotificationType(type: unknown): string {
  if (typeof type !== 'string' || !type)
    return 'general'

  const className = type.split('\\').pop() || type

  return CLASS_TO_TYPE[className] ?? type
}

export function notificationMeta(d: Record<string, any> | null | undefined): NotificationMeta {
  const type = normalizeNotificationType(d?.type)
  const known = TYPE_MAP[type]

  if (known)
    return { ...known, financial: !!known.financial }

  if (FINANCIAL_PATTERN.test(type))
    return { title: 'إشعار مالي', icon: 'tabler-wallet', color: FINANCIAL_COLOR, financial: true }

  return { title: d?.title || 'إشعار جديد', icon: 'tabler-bell', color: 'primary', financial: false }
}

/** نص الإشعار: الرسالة القادمة من الخادم، وإلا ما يمكن بناؤه من بياناته. */
export function notificationMessage(d: Record<string, any> | null | undefined, fallback = ''): string {
  if (d?.message)
    return String(d.message)
  if (d?.contractor_name)
    return `المقاول: ${d.contractor_name}`
  if (d?.amount)
    return `المبلغ: ${Number(d.amount).toLocaleString('en-US')}`

  return fallback
}

/** وقت نسبي قصير بالعربي («الآن»، «منذ 5 دقائق»...). */
export function relativeTimeAr(dateStr: string | null | undefined): string {
  if (!dateStr)
    return ''
  const date = new Date(dateStr)
  if (Number.isNaN(date.getTime()))
    return ''

  const mins = Math.floor((Date.now() - date.getTime()) / 60000)
  if (mins < 1)
    return 'الآن'
  if (mins < 60)
    return `منذ ${mins} دقيقة`
  const hrs = Math.floor(mins / 60)
  if (hrs < 24)
    return `منذ ${hrs} ساعة`
  const days = Math.floor(hrs / 24)
  if (days < 7)
    return `منذ ${days} يوم`

  return date.toLocaleDateString('ar-EG', { year: 'numeric', month: 'short', day: 'numeric' })
}

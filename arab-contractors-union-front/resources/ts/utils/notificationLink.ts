/**
 * يحدّد الصفحة التي يفتحها الضغط على إشعار في لوحة التحكم، بناءً على نوعه.
 * يُستخدم في قائمة الإشعارات المنسدلة وفي صفحة /notifications حتى يبقى
 * السلوك واحداً. يرجع null إذا لم يكن للإشعار صفحة مرتبطة.
 */
export function notificationLink(d: Record<string, any> | null | undefined): string | null {
  if (!d)
    return null

  const q = (params: Record<string, any>) => {
    const s = new URLSearchParams()
    Object.entries(params).forEach(([k, v]) => {
      if (v !== null && v !== undefined && v !== '')
        s.set(k, String(v))
    })
    const str = s.toString()

    return str ? `?${str}` : ''
  }

  switch (d.type) {
    case 'payment_submitted':
      return `/payments/transactions${q({ id: d.payment_id })}`

    case 'contractor_activated':
    case 'contractor_profile_updated':
      return d.contractor_id ? `/contractors/edit/${d.contractor_id}` : '/contractors'

    case 'event_joined':
      return `/events${q({ search: d.event_title })}`

    case 'support_ticket_created':
    case 'support_ticket_contractor_replied':
      return `/support-tickets${q({ ticket: d.ticket_id })}`

    case 'certificate_request_submitted':
      return `/certificate-requests${q({ id: d.request_id })}`

    case 'pma_rates_fetch_failed':
      return '/settings/exchange-rates'
  }

  // روابط داخلية فقط (تبدأ بـ /) حتى لا يفتح الإشعار موقعاً خارجياً
  if (typeof d.link === 'string' && d.link.startsWith('/') && !d.link.startsWith('//'))
    return d.link

  return null
}

// رسالة خطأ واضحة لطلب فشل بدون رسالة من الخادم (انتهاء المهلة، انقطاع النت، خطأ
// 500/502/504 من nginx). قبل هيك كانت كل هالحالات تطلع "يرجى التحقق من المدخلات"
// فيضيع السبب الحقيقي ويفكر المستخدم إن الخطأ بالبيانات.

// مهلة رفع الملفات: مهلة axios الافتراضية (60 ثانية) ما بتكفي لرفع 13 مستند على نت بطيء.
export const UPLOAD_TIMEOUT_MS = 10 * 60 * 1000

export function describeRequestError(err: any, fallback: string): string {
  const serverMessage = err?.response?.data?.message
  if (typeof serverMessage === 'string' && serverMessage.trim() !== '')
    return serverMessage

  if (err?.code === 'ECONNABORTED' || err?.code === 'ETIMEDOUT')
    return 'انتهت مهلة رفع البيانات قبل ما يكتمل الإرسال (غالباً النت بطيء أو المرفقات كبيرة). جرّب مرة ثانية أو قلّل حجم الملفات.'

  const status = err?.response?.status
  if (!err?.response)
    return 'تعذّر الاتصال بالخادم (انقطاع بالإنترنت أو انقطع الاتصال أثناء رفع الملفات). تأكد من الاتصال وجرّب مرة ثانية.'

  if (status === 413)
    return 'حجم المرفقات يتجاوز الحد الأقصى المسموح به. يرجى تقليل حجم الملفات.'

  if (status === 502 || status === 503 || status === 504)
    return `الخادم ما ردّ بالوقت (خطأ ${status}). جرّب مرة ثانية بعد قليل.`

  if (status >= 500)
    return `صار خطأ بالخادم (رمز ${status}) — المشكلة مش بالمدخلات. جرّب مرة ثانية، وإذا تكرر بلّغ الدعم الفني.`

  return status ? `${fallback} (رمز ${status})` : fallback
}

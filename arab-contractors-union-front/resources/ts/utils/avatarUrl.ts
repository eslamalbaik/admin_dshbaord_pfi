const API_ORIGIN = (import.meta.env.VITE_API_BASE_URL || 'http://localhost:8000').replace(/\/$/, '')

/** رابط صورة المستخدم: الروابط الكاملة (Google) كما هي، والمسارات النسبية من storage. */
export function avatarUrl(avatar?: string | null): string {
  if (!avatar)
    return ''

  return /^https?:\/\//.test(avatar) ? avatar : `${API_ORIGIN}/storage/${avatar}`
}

/** الاسم المعروض لمستخدم لوحة التحكم (الأدمن بدون اسم → اسم الاتحاد). */
export const UNION_NAME = 'اتحاد المقاولين الفلسطينيين'

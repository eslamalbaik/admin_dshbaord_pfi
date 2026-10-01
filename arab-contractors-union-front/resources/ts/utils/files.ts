/**
 * VFileInput بدون `multiple` يُصدِر `File` مفرداً (Vuetify ≥ 3.9) لا `File[]`،
 * فقراءة `ref.value[0]` ترجع undefined ويُرسل الطلب بلا ملف بصمت.
 * هذه الدالة تطبّع الشكلين معاً.
 */
export const firstFile = (value: File | File[] | null | undefined): File | null => {
  if (!value)
    return null

  return Array.isArray(value) ? value[0] ?? null : value
}

/** يطبّع مخرجات VFileInput المتعدد إلى مصفوفة دائماً */
export const toFileArray = (value: File | File[] | null | undefined): File[] => {
  if (!value)
    return []

  return Array.isArray(value) ? value : [value]
}

/** نوع v-model المناسب لـ VFileInput مفرد — يقبل ما تصدره Vuetify بكل الحالات */
export type SingleFileModel = File | File[] | null

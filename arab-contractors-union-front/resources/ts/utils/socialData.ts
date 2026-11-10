// بيانات تجريبية لصفحات "اجتماعيات" (تعازي، تهاني، شهداء المهنة).
// لا يوجد API لها بعد؛ استبدل هذه القوائم باستدعاء الـAPI عند توفره.

export interface CondolenceItem {
  id: number
  title: string
  excerpt: string
  date: string
}

export interface CongratulationItem {
  id: number
  category: 'national' | 'religious' | 'special'
  title: string
  excerpt: string
  date: string
}

export interface MartyrItem {
  id: number
  name: string
  title: string
  date: string
}

export const condolences: CondolenceItem[] = [
  { id: 1, title: 'في وفاة المهندس أحمد محمد علي', excerpt: 'يتقدم اتحاد المقاولين بخالص العزاء والمواساة إلى أسرة الفقيد وذويه، سائلين الله أن يتغمده بواسع رحمته.', date: '2026-10-14' },
  { id: 2, title: 'في وفاة الموظفة سارة خالد أحمد', excerpt: 'بقلوب مؤمنة بقضاء الله وقدره ننعى إلى الزملاء والأهل الكرام فقيدتنا، رحمها الله وأسكنها فسيح جناته.', date: '2026-10-10' },
  { id: 3, title: 'في وفاة المهندس يوسف علي حسن', excerpt: 'نتقدم بأحر التعازي إلى عائلة الفقيد ورفاق دربه في قطاع المقاولات، وإنا لله وإنا إليه راجعون.', date: '2026-10-05' },
  { id: 4, title: 'في وفاة الحاج محمود سليمان الأغا', excerpt: 'ينعى الاتحاد أحد رواد العمل الإنشائي وأصحاب البصمة الطيبة، ويسأل الله له المغفرة والرضوان.', date: '2026-09-28' },
]

export const congratulationCategories = [
  { key: 'all', label: 'كل التبريكات' },
  { key: 'national', label: 'مناسبات وطنية' },
  { key: 'religious', label: 'مناسبات دينية' },
  { key: 'special', label: 'مناسبات خاصة' },
] as const

export const congratulations: CongratulationItem[] = [
  { id: 1, category: 'national', title: 'تهنئة بمناسبة ذكرى يوم الأرض', excerpt: 'كل عام وأرضنا بخير، وكل عام ونحن أكثر تمسكاً بها وإعماراً لها.', date: '2026-03-30' },
  { id: 2, category: 'special', title: 'تهنئة بالنجاح والتفوق', excerpt: 'نبارك لأبناء أعضاء الاتحاد تفوقهم ونجاحهم، ومستقبل مشرق بإبداعكم وتميزكم.', date: '2026-07-28' },
  { id: 3, category: 'religious', title: 'تهنئة بمناسبة شهر رمضان المبارك', excerpt: 'تقبل الله منا ومنكم صالح الأعمال، وكل عام وأنتم بخير.', date: '2026-02-18' },
  { id: 4, category: 'religious', title: 'تهنئة بمناسبة عيد الأضحى المبارك', excerpt: 'كل عام وأنتم بخير، أعاده الله علينا وعليكم باليمن والبركات.', date: '2026-05-27' },
  { id: 5, category: 'special', title: 'تهنئة بالمولود الجديد', excerpt: 'نبارك لزميلنا قدوم مولوده الجديد، جعله الله قرة عين لوالديه وبارك لهما فيه.', date: '2026-08-12' },
]

export const martyrs: MartyrItem[] = [
  { id: 1, name: 'م. أحمد محمد علي', title: 'مهندس موقع', date: '2025-08-12' },
  { id: 2, name: 'م. خالد سامي النجار', title: 'مهندس مدني', date: '2025-08-05' },
  { id: 3, name: 'محمود إبراهيم شعث', title: 'مقاول إنشاءات', date: '2025-07-19' },
  { id: 4, name: 'م. يوسف عمر الحلو', title: 'مشرف مشاريع', date: '2025-07-02' },
  { id: 5, name: 'رائد سالم أبو عودة', title: 'مقاول طرق', date: '2025-06-14' },
  { id: 6, name: 'م. سعيد ماهر القيشاوي', title: 'مهندس معماري', date: '2025-05-30' },
]

export function formatSocialDate(d: string): string {
  const [y, m, day] = d.split('-')
  return `${y} / ${m} / ${day}`
}

// حزمة أيقونات محلية (offline) — بدل الاعتماد على iconify-icon Web Component اللي كان
// يجلب كل أيقونة عبر شبكة api.iconify.design في كل تصفّح، وده كان سبب بطء/تعليق واضح
// بكل تنقّل بلوحة التحكم. الحزمة الحالية مقلَّصة لأيقونات tabler المستخدمة فعلياً فقط.
import './icons.css'

export default function () {
  // This plugin just requires icons import
}

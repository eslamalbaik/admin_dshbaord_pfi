# 🏗️ اتحاد المقاولين الفلسطينيين - Micro Dashboard

**لوحة تحكم متقدمة للمقاول** مع صفحات متخصصة للدفع، الدعم الفني، وطلب الشهادات.

---

## 📋 المحتويات

- [نظرة عامة](#نظرة-عامة)
- [المتطلبات](#المتطلبات)
- [التثبيت](#التثبيت)
- [البدء السريع](#البدء-السريع)
- [هيكل المشروع](#هيكل-المشروع)
- [الصفحات](#الصفحات)
- [الـ API](#الـ-api)
- [الاختبار](#الاختبار)
- [الدعم](#الدعم)

---

## 🎯 نظرة عامة

هذا المشروع يتضمن:

### ✅ **Frontend (Vue 3 + TypeScript)**
- 🎯 لوحة تحكم مقاول شاملة
- 🏦 بوابة دفع متقدمة
- 💬 نظام الدعم الفني والشكاوي
- 🏆 إدارة طلبات الشهادات
- 📱 تصميم استجابي (Responsive)

### ⚙️ **Backend (Laravel)**
- 🔐 مصادقة آمنة (Bearer Token)
- 📊 إدارة البيانات
- 💾 قاعدة بيانات MariaDB
- 🚀 API RESTful موثقة

---

## 📦 المتطلبات

### **Frontend**
- Node.js >= 16
- npm أو pnpm
- Vue 3
- TypeScript
- Vite

### **Backend**
- PHP >= 8.1
- Laravel >= 10
- Composer
- MariaDB >= 10.5
- Docker (اختياري)

---

## 🚀 التثبيت

### **خطوة 1: استنساخ المستودع**
```bash
git clone https://github.com/eslamalbaik/admin_dshbaord_pfi.git
cd admin_dshbaord_pfi
```

### **خطوة 2: الفرع الحالي**
```bash
git checkout feature/arab-contractors-union
```

### **خطوة 3: تثبيت الاعتماديات**

#### Frontend:
```bash
cd arab-contractors-union-front
npm install
# أو
pnpm install
```

#### Backend:
```bash
cd arab-contractors-union-api
composer install
```

### **خطوة 4: إعداد البيئة**

#### Frontend (.env):
```bash
cp .env.example .env
# عدّل VITE_API_BASE_URL
VITE_API_BASE_URL=http://localhost:8000/api/v1
```

#### Backend (.env):
```bash
cp .env.example .env
php artisan key:generate
php artisan migrate
```

---

## 🎬 البدء السريع

### **تشغيل الخادم المحلي**

#### Frontend:
```bash
cd arab-contractors-union-front
npm run dev
# سيفتح على: http://localhost:5173
```

#### Backend:
```bash
cd arab-contractors-union-api
php artisan serve
# سيفتح على: http://localhost:8000
```

### **الوصول للتطبيق**
- **الواجهة:** http://localhost:5173
- **لوحة التحكم:** http://localhost:5173/contractor/dashboard
- **API Base:** http://localhost:8000/api/v1

---

## 📂 هيكل المشروع

```
admin_dshbaord_pfi/
├── arab-contractors-union-front/          # الواجهة الأمامية (Vue 3)
│   ├── resources/ts/pages/
│   │   └── contractor/
│   │       ├── dashboard.vue              # لوحة التحكم الرئيسية
│   │       ├── payment-gateway.vue        # بوابة الدفع ✨ جديد
│   │       ├── support.vue                # الدعم الفني ✨ جديد
│   │       └── certificate-request.vue    # طلب الشهادة ✨ جديد
│   └── vite.config.ts
├── arab-contractors-union-api/            # الـ Backend (Laravel)
│   ├── app/Http/Controllers/
│   ├── app/Models/
│   ├── routes/api.php
│   └── composer.json
├── CONTRACTOR_DASHBOARD_API.postman_collection.json  # Postman ✨ جديد
└── CONTRACTOR_DASHBOARD_SUMMARY.md        # توثيق شاملة ✨ جديد
```

---

## 📄 الصفحات

### 🎯 **لوحة التحكم** (`/contractor/dashboard`)
**الميزات:**
- ✅ 5 تبويبات (الملف الشخصي، العضوية، المعاملات، الوثائق، المكتبة القانونية)
- ✅ شريط الإحصائيات
- ✅ روابط سريعة للصفحات الجديدة
- ✅ بطاقة العضوية في الشريط الجانبي

### 🏦 **بوابة الدفع** (`/contractor/payment-gateway`) ✨
**الميزات:**
- ✅ عرض بيانات البنك (IBAN، SWIFT)
- ✅ نسخ IBAN بزر واحد
- ✅ نموذج تحميل إشعار الدفع
- ✅ معاينة الصور
- ✅ عرض حالة الدفع

### 💬 **الدعم الفني** (`/contractor/support`) ✨
**الميزات:**
- ✅ نموذج شكاوي مع 6 فئات
- ✅ إرفاق ملفات (5 ملفات، 10MB)
- ✅ قائمة الطلبات السابقة
- ✅ روابط تواصل سريعة (هاتف، بريد، واتساب)

### 🏆 **طلب الشهادة** (`/contractor/certificate-request`) ✨
**الميزات:**
- ✅ عرض المتطلبات المتبقية
- ✅ نموذج طلب شهادة (4 أنواع)
- ✅ قائمة الطلبات السابقة
- ✅ تحميل الشهادات

---

## 🔌 الـ API

### **المصادقة**
جميع الطلبات تتطلب:
```bash
Authorization: Bearer {contractor_token}
```

### **الـ Endpoints الجديدة**

#### 🏦 بوابة الدفع
```bash
GET  /api/v1/contractor/payment-gateway         # البيانات
POST /api/v1/contractor/payment-submit          # إرسال الإشعار
```

#### 💬 الدعم الفني
```bash
GET  /api/v1/contractor/support                 # الطلبات
POST /api/v1/contractor/support/create          # طلب جديد
```

#### 🏆 الشهادات
```bash
GET  /api/v1/contractor/certificate-request    # البيانات
POST /api/v1/contractor/certificate-request/create  # طلب جديد
```

### **استيراد في Postman**
```bash
1. افتح Postman
2. اضغط Import
3. اختر: CONTRACTOR_DASHBOARD_API.postman_collection.json
4. عيّن contractor_token في المتغيرات
5. اختبر الـ endpoints
```

---

## 🧪 الاختبار

### **اختبار الواجهة**
```bash
# تشغيل الخادم
cd arab-contractors-union-front
npm run dev

# زيارة الصفحات
http://localhost:5173/contractor/dashboard
http://localhost:5173/contractor/payment-gateway
http://localhost:5173/contractor/support
http://localhost:5173/contractor/certificate-request
```

### **اختبار الـ API**
```bash
# استخدام Postman (موصى به)
استورد: CONTRACTOR_DASHBOARD_API.postman_collection.json

# أو استخدم curl:
curl -H "Authorization: Bearer TOKEN" \
  http://localhost:8000/api/v1/contractor/payment-gateway
```

### **اختبارات الوحدة**
```bash
# Frontend
cd arab-contractors-union-front
npm run test

# Backend
cd arab-contractors-union-api
php artisan test
```

---

## 📚 الموارد الإضافية

### **ملفات التوثيق**
- 📖 [`CONTRACTOR_DASHBOARD_SUMMARY.md`](./CONTRACTOR_DASHBOARD_SUMMARY.md) - ملخص شامل
- 📖 [`SERVER-MAP.md`](./SERVER-MAP.md) - خريطة الخادم
- 📖 [`Arab_Contractors_Union_API.postman_collection.json`](./Arab_Contractors_Union_API.postman_collection.json) - جميع الـ APIs

### **المجلدات المهمة**
```
Frontend:
- resources/ts/pages/contractor/          # صفحات المقاول
- resources/ts/components/                 # المكونات المشتركة
- resources/ts/plugins/                    # الإضافات (axios, etc)
- resources/ts/stores/                     # Pinia stores

Backend:
- app/Http/Controllers/                    # المتحكمات
- app/Models/                              # نماذج Eloquent
- routes/api.php                           # تعريفات الـ API
- database/migrations/                     # الهجرات
```

---

## 💡 نصائح التطوير

### **أثناء التطوير**
```bash
# تشغيل سيرفر التطوير مع Hot Reload
npm run dev

# التحقق من الأخطاء
npm run lint

# بناء الإنتاج
npm run build
```

### **المتغيرات البيئية**
```bash
# Frontend
VITE_API_BASE_URL=http://localhost:8000/api/v1

# Backend
APP_ENV=local
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_DATABASE=acu_db
```

### **الدعم متعدد اللغات**
```bash
# جميع الصفحات تدعم العربية (RTL)
# استخدم المكون I18n للترجمة
```

---

## 🐛 حل المشاكل الشائعة

### **خطأ: "Cannot find module"**
```bash
rm -rf node_modules package-lock.json
npm install
```

### **خطأ: "CORS"**
```bash
# تأكد من أن الـ API يرسل رؤوس CORS الصحيحة
# Backend config/cors.php
```

### **خطأ: "401 Unauthorized"**
```bash
# تأكد من أن الـ token موجود و صحيح
# تحقق من localStorage: localStorage.getItem('contractor_token')
```

---

## 📞 الدعم

### **المساعدة والاستفسارات**
- 📧 البريد: eslamahmad2000t@gmail.com
- 🐙 GitHub: https://github.com/eslamalbaik/admin_dshbaord_pfi
- 📱 WhatsApp: +970 5X XXX XXXX

### **الإبلاغ عن الأخطاء**
```bash
git checkout -b bugfix/issue-name
# قم بإصلاح المشكلة
git commit -m "fix: وصف المشكلة"
git push origin bugfix/issue-name
# أنشئ Pull Request
```

---

## 📝 الترخيص

هذا المشروع مرخص تحت [MIT License](LICENSE)

---

## 👤 المؤلف

**Eslam AlBaik**
- 🐙 GitHub: [@eslamalbaik](https://github.com/eslamalbaik)
- 📧 Email: eslamahmad2000t@gmail.com

---

## 🙏 الشكر والتقدير

شكراً لاستخدام لوحة تحكم اتحاد المقاولين الفلسطينيين!

---

**آخر تحديث:** 2026-07-07  
**الإصدار:** 1.0.0 (Beta)  
**الحالة:** 🟢 جاهز للاختبار

---

## 📋 قائمة المهام المتبقية

- [ ] بناء الـ API endpoints في Backend
- [ ] اختبار التكامل بين Frontend و Backend
- [ ] اختبار الاستجابة على أجهزة الهاتف
- [ ] تطبيق معالجة الأخطاء الشاملة
- [ ] إضافة الاختبارات الآلية
- [ ] نشر في الإنتاج

---

**شكراً لاستخدامك هذا المشروع! 🎉**

# 🎯 ملخص بناء Micro Dashboard للمقاول

## ✅ تم إنجازه

### 📱 الصفحات المُنشأة

#### 1️⃣ **صفحة بوابة الدفع**
📂 `arab-contractors-union-front/resources/ts/pages/contractor/payment-gateway.vue`

**الميزات:**
- ✅ عرض بيانات البنك (اسم البنك، IBAN، SWIFT)
- ✅ نسخ IBAN بزر واحد
- ✅ نموذج لإدخال المبلغ وصورة الإشعار
- ✅ معاينة الصور قبل الإرسال
- ✅ عرض حالة آخر دفع
- ✅ رسائل خطأ ونجاح تفاعلية
- ✅ استجابة كاملة (موبايل + ديسك توب)

**الـ API المطلوب:**
```
GET  /api/v1/contractor/payment-gateway          ← الحصول على البيانات
POST /api/v1/contractor/payment-submit           ← إرسال الإشعار (multipart)
```

**Response المتوقع:**
```json
{
  "status": true,
  "items": {
    "contractor": { "name": "...", "membership_number": "..." },
    "union_bank": { 
      "bank_name": "...", 
      "iban": "...",
      "swift_code": "...",
      "account_holder": "..."
    },
    "current_payment": { 
      "id": 1, 
      "amount": "1000", 
      "status": "pending",
      "proof_image_url": "...",
      "created_at": "2026-07-07"
    }
  }
}
```

---

#### 2️⃣ **صفحة الدعم الفني والشكاوي**
📂 `arab-contractors-union-front/resources/ts/pages/contractor/support.vue`

**الميزات:**
- ✅ تبويبات: "طلب جديد" و "الطلبات السابقة"
- ✅ نموذج شكاوي مع 6 فئات (فواتير، عضوية، فني، شكوى، استفسار، أخرى)
- ✅ إرفاق ملفات (حتى 5 ملفات، 10MB لكل ملف)
- ✅ معاينة الملفات المرفقة
- ✅ **روابط سريعة للتواصل:**
  - 📞 هاتف
  - 📧 بريد إلكتروني
  - 💬 **واتساب** (رابط مباشر)
- ✅ عرض الطلبات السابقة مع الحالات والردود
- ✅ استجابة كاملة

**الـ API المطلوب:**
```
GET  /api/v1/contractor/support                  ← الحصول على الطلبات
POST /api/v1/contractor/support/create           ← إنشاء طلب جديد (multipart)
```

**Response المتوقع:**
```json
{
  "status": true,
  "items": {
    "contractor": { 
      "name": "...", 
      "email": "...",
      "phone": "...",
      "membership_number": "..."
    },
    "tickets": [
      {
        "id": 1,
        "subject": "...",
        "category": "technical",
        "description": "...",
        "status": "open|in_progress|resolved|closed",
        "priority": "low|medium|high|urgent",
        "response": "...",
        "attachments_count": 0,
        "created_at": "2026-07-07",
        "replied_at": null
      }
    ]
  }
}
```

---

#### 3️⃣ **صفحة طلب شهادة العضوية**
📂 `arab-contractors-union-front/resources/ts/pages/contractor/certificate-request.vue`

**الميزات:**
- ✅ عرض المتطلبات المتبقية (إن وجدت)
  - غرامات التأخير
  - رسوم الاشتراك المتأخرة
  - نزاعات قيد المعالجة
  - وثائق مفقودة
- ✅ نموذج طلب شهادة (4 أنواع)
  - شهادة الانتساب
  - شهادة حسن السير والسلوك
  - شهادة التصنيف
  - شهادة الخبرة والمشاريع
- ✅ قائمة الطلبات السابقة مع:
  - الحالات (قيد المراجعة، موافق، مصدرة، مرفوضة)
  - الملاحظات
  - أسباب الرفض (إن وجدت)
  - رابط التحميل
- ✅ استجابة كاملة

**الـ API المطلوب:**
```
GET  /api/v1/contractor/certificate-request      ← الحصول على البيانات
POST /api/v1/contractor/certificate-request/create ← إنشاء طلب جديد
```

**Response المتوقع:**
```json
{
  "status": true,
  "items": {
    "contractor": { "name": "...", "membership_number": "..." },
    "can_request": true,
    "requirement_issues": [
      {
        "type": "late_fees|overdue_subscription|pending_dispute|missing_documents",
        "description": "...",
        "amount": "500",
        "due_date": "2026-07-15"
      }
    ],
    "requests": [
      {
        "id": 1,
        "type": "membership|good_standing|classification|experience",
        "status": "pending|approved|issued|rejected",
        "request_date": "2026-07-07",
        "issue_date": null,
        "certificate_url": null,
        "reject_reason": null,
        "notes": "..."
      }
    ]
  }
}
```

---

#### 4️⃣ **تحديث لوحة التحكم**
📂 `arab-contractors-union-front/resources/ts/pages/contractor/dashboard.vue`

**التحديثات:**
- ✅ إضافة قسم "روابط سريعة" (Quick Links)
  - 🏦 **بوابة الدفع** → `/contractor/payment-gateway`
  - 💬 **الدعم الفني** → `/contractor/support`
  - 🏆 **طلب الشهادة** → `/contractor/certificate-request`
- ✅ إضافة الآيقونة `MessageSquare`
- ✅ استجابة كاملة (الروابط تظهر في صف واحد على الشاشات الكبيرة)

---

### 📋 ملفات Postman Collection

#### 1. **CONTRACTOR_DASHBOARD_API.postman_collection.json**
ملف Postman جديد يحتوي على:
- ✅ جميع الـ endpoints الجديدة
- ✅ أمثلة الطلبات (Request)
- ✅ توصيف الاستجابات (Response)
- ✅ شرح المعاملات والقيم المقبولة

**كيفية الاستخدام:**
1. افتح Postman
2. اضغط `Import`
3. اختر الملف `CONTRACTOR_DASHBOARD_API.postman_collection.json`
4. عيّن قيمة `contractor_token` في المتغيرات
5. اختبر الـ endpoints

---

## 🔗 روابط GitHub

**الفرع:** `feature/arab-contractors-union`

**الـ Commit:**
```
bb6afad - feat: إضافة 3 صفحات جديدة للـ Micro Dashboard للمقاول
```

**الملفات المُضافة:**
- `arab-contractors-union-front/resources/ts/pages/contractor/payment-gateway.vue` (46KB)
- `arab-contractors-union-front/resources/ts/pages/contractor/support.vue` (24KB)
- `arab-contractors-union-front/resources/ts/pages/contractor/certificate-request.vue` (25KB)
- `arab-contractors-union-front/resources/ts/pages/contractor/dashboard.vue` (محدّث)
- `CONTRACTOR_DASHBOARD_API.postman_collection.json` (6KB)

---

## ⚙️ الخطوات التالية

### 1️⃣ **على الـ Backend**

تحتاج إلى بناء الـ API endpoints التالية:

```php
// بوابة الدفع
Route::middleware('auth:contractor')->group(function () {
    Route::get('/contractor/payment-gateway', [PaymentController::class, 'gateway']);
    Route::post('/contractor/payment-submit', [PaymentController::class, 'submitPayment']);
    
    // الدعم الفني
    Route::get('/contractor/support', [SupportController::class, 'index']);
    Route::post('/contractor/support/create', [SupportController::class, 'store']);
    
    // الشهادات
    Route::get('/contractor/certificate-request', [CertificateController::class, 'index']);
    Route::post('/contractor/certificate-request/create', [CertificateController::class, 'store']);
});
```

### 2️⃣ **الاختبار**

```bash
# اختبر الصفحات في المتصفح
http://localhost:3000/contractor/payment-gateway
http://localhost:3000/contractor/support
http://localhost:3000/contractor/certificate-request

# اختبر الـ endpoints باستخدام Postman
- استورد الملف CONTRACTOR_DASHBOARD_API.postman_collection.json
- عيّن الـ contractor_token
- جرّب كل endpoint
```

### 3️⃣ **التكامل مع الواجهة**

```bash
# على الـ Frontend
npm install
npm run dev

# افتح المتصفح على:
http://localhost:5173/contractor/dashboard
```

---

## 📊 إحصائيات

| المقياس | القيمة |
|--------|--------|
| **عدد الصفحات الجديدة** | 3 |
| **عدد أسطر الكود (Vue)** | ~2,300 سطر |
| **عدد الـ API endpoints** | 6 |
| **دعم العربية** | ✅ كامل |
| **دعم الاستجابة** | ✅ موبايل + ديسك توب |
| **التصميم** | ✅ موحّد مع Dashboard |

---

## 📝 ملاحظات مهمة

✅ **جميع الصفحات:**
- تتطلب مصادقة (Bearer Token)
- تتضمن رسائل خطأ واضحة بالعربية
- تدعم التحميل والإرسال
- تحتوي على معالجة الأخطاء الشاملة
- مصممة بأسلوب موحّد

⚠️ **التحقق من البيانات:**
- تحقق من أن الـ endpoints ترجع البيانات بنفس الهيكل المتوقع
- اختبر جميع حالات الخطأ (401, 403, 422, 500)
- تأكد من صحة معالجة الملفات المرفقة

---

## 🚀 الخطوة الأخيرة

لدمج الفرع في الفرع الرئيسي (main):

```bash
git checkout main
git pull origin main
git merge feature/arab-contractors-union
git push origin main
```

أو استخدم **Pull Request** على GitHub:
1. انتقل إلى المستودع
2. انقر `New Pull Request`
3. اختر `feature/arab-contractors-union` → `main`
4. أضف الوصف والتفاصيل
5. انقر `Create Pull Request`

---

**آخر تحديث:** 2026-07-07
**الحالة:** ✅ جاهز للاختبار

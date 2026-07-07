# 🔨 دليل التطبيق والتطوير

**دليل شامل لتطبيق الـ Micro Dashboard للمقاول**

---

## 📑 جدول المحتويات

1. [نظرة عامة](#نظرة-عامة)
2. [الـ API المطلوبة](#الـ-api-المطلوبة)
3. [خطوات التطوير](#خطوات-التطوير)
4. [الاختبار](#الاختبار)
5. [حل المشاكل](#حل-المشاكل)
6. [الإطلاق](#الإطلاق)

---

## 🎯 نظرة عامة

هذا المشروع يتطلب:

```
Frontend (Vue 3)          ←→        Backend (Laravel)
├── payment-gateway.vue            ├── PaymentController
├── support.vue                    ├── SupportController
├── certificate-request.vue        └── CertificateController
└── dashboard.vue (محدّث)
```

---

## 🔌 الـ API المطلوبة

### 1️⃣ بوابة الدفع (Payment Gateway)

#### `GET /api/v1/contractor/payment-gateway`

**الغرض:** الحصول على بيانات البنك والدفع الحالي

**المصادقة:** Bearer Token

**الاستجابة:**
```json
{
  "status": true,
  "message": "تم جلب البيانات بنجاح",
  "status_code": 200,
  "items": {
    "contractor": {
      "id": 1,
      "name": "محمد عبدالله",
      "membership_number": "928_g",
      "email": "contractor@example.com",
      "phone": "+970599123456"
    },
    "union_bank": {
      "id": 1,
      "bank_name": "البنك الإسلامي الفلسطيني",
      "bank_icon_url": "https://...",
      "account_holder": "اتحاد المقاولين الفلسطينيين",
      "iban": "PS12PBNK2345678901234567",
      "swift_code": "PBNKPS22"
    },
    "current_payment": {
      "id": 15,
      "amount": "1000",
      "reference_number": "REF-2026-1234",
      "status": "pending",
      "proof_image_url": "https://...",
      "notes": "تجديد الاشتراك",
      "created_at": "2026-07-07T12:00:00Z",
      "verified_at": null
    }
  }
}
```

**الحالات الخاصة:**
- بدون دفع حالي: `current_payment` يكون `null`

---

#### `POST /api/v1/contractor/payment-submit`

**الغرض:** إرسال إشعار دفع جديد

**المصادقة:** Bearer Token

**نوع البيانات:** `multipart/form-data`

**المعاملات:**
```
- amount          (string, مطلوب)   المبلغ المحول
- proof_image     (file, مطلوب)     صورة الإشعار (PNG, JPG, WebP حتى 5MB)
- notes           (string, اختياري) ملاحظات
```

**الاستجابة:**
```json
{
  "status": true,
  "message": "تم إرسال إشعار الدفع بنجاح",
  "status_code": 200,
  "items": {
    "payment": {
      "id": 16,
      "amount": "1000",
      "reference_number": null,
      "status": "pending",
      "proof_image_url": "https://...",
      "notes": "تجديد الاشتراك",
      "created_at": "2026-07-07T13:30:00Z",
      "verified_at": null
    }
  }
}
```

**الأخطاء:**
```json
// 401 - غير مصرح
{ "status": false, "message": "غير مصرح", "status_code": 401 }

// 422 - بيانات غير صحيحة
{
  "status": false,
  "status_code": 422,
  "errors": {
    "amount": ["المبلغ مطلوب"],
    "proof_image": ["الصورة مطلوبة"]
  }
}
```

---

### 2️⃣ الدعم الفني والشكاوي (Support)

#### `GET /api/v1/contractor/support`

**الغرض:** الحصول على الطلبات السابقة

**المصادقة:** Bearer Token

**الاستجابة:**
```json
{
  "status": true,
  "status_code": 200,
  "items": {
    "contractor": {
      "id": 1,
      "name": "محمد عبدالله",
      "email": "contractor@example.com",
      "phone": "+970599123456",
      "membership_number": "928_g"
    },
    "tickets": [
      {
        "id": 1,
        "subject": "مشكلة في تسجيل الدخول",
        "category": "technical",
        "description": "لا أستطيع تسجيل الدخول...",
        "status": "in_progress",
        "priority": "high",
        "response": "تم تحويل المشكلة للفريق التقني",
        "attachments_count": 2,
        "created_at": "2026-07-05T10:00:00Z",
        "updated_at": "2026-07-07T14:30:00Z",
        "replied_at": "2026-07-06T09:00:00Z"
      },
      {
        "id": 2,
        "subject": "استفسار عن الرسوم",
        "category": "billing",
        "description": "هل يمكن معرفة...",
        "status": "resolved",
        "priority": "medium",
        "response": "الرسوم الحالية...",
        "attachments_count": 0,
        "created_at": "2026-07-01T08:00:00Z",
        "replied_at": "2026-07-02T11:00:00Z"
      }
    ]
  }
}
```

---

#### `POST /api/v1/contractor/support/create`

**الغرض:** إنشاء طلب دعم جديد

**المصادقة:** Bearer Token

**نوع البيانات:** `multipart/form-data`

**المعاملات:**
```
- subject         (string, مطلوب)   عنوان الطلب
- category        (string, مطلوب)   نوع الطلب
- description     (string, مطلوب)   الوصف التفصيلي
- attachments[]   (file, اختياري)   ملفات (حتى 5 ملفات، 10MB لكل ملف)
```

**الفئات المتاحة:**
```
- billing         → مشاكل الفواتير والدفع
- membership      → مشاكل العضوية
- technical       → مشاكل فنية
- complaint       → شكوى
- inquiry         → استفسار
- other           → أخرى
```

**الاستجابة:**
```json
{
  "status": true,
  "message": "تم إنشاء الطلب بنجاح",
  "status_code": 201,
  "items": {
    "ticket": {
      "id": 3,
      "subject": "مشكلة في التحميل",
      "category": "technical",
      "description": "الصفحة تأخذ وقت طويل...",
      "status": "open",
      "priority": "medium",
      "response": null,
      "attachments_count": 1,
      "created_at": "2026-07-07T15:00:00Z",
      "replied_at": null
    }
  }
}
```

---

### 3️⃣ طلبات الشهادات (Certificates)

#### `GET /api/v1/contractor/certificate-request`

**الغرض:** الحصول على طلبات الشهادات والمتطلبات

**المصادقة:** Bearer Token

**الاستجابة:**
```json
{
  "status": true,
  "status_code": 200,
  "items": {
    "contractor": {
      "id": 1,
      "name": "محمد عبدالله",
      "membership_number": "928_g",
      "status": "active",
      "is_frozen": false
    },
    "can_request": true,
    "requirement_issues": [
      {
        "type": "late_fees",
        "description": "غرامات تأخير متراكمة",
        "amount": "500",
        "due_date": "2026-07-15"
      }
    ],
    "requests": [
      {
        "id": 1,
        "type": "membership",
        "status": "issued",
        "request_date": "2026-06-01",
        "issue_date": "2026-06-05",
        "certificate_url": "https://...",
        "reject_reason": null,
        "notes": "شهادة موثقة"
      },
      {
        "id": 2,
        "type": "good_standing",
        "status": "pending",
        "request_date": "2026-07-07",
        "issue_date": null,
        "certificate_url": null,
        "reject_reason": null,
        "notes": null
      }
    ]
  }
}
```

**الشروط الخاصة:**
- إذا `can_request` هو `false`: المستخدم لا يمكنه تقديم طلب جديد
- إذا `requirement_issues` موجود: يجب حل المتطلبات أولاً

---

#### `POST /api/v1/contractor/certificate-request/create`

**الغرض:** إنشاء طلب شهادة جديد

**المصادقة:** Bearer Token

**نوع البيانات:** `application/x-www-form-urlencoded`

**المعاملات:**
```
- type      (string, مطلوب)   نوع الشهادة
- notes     (string, اختياري) ملاحظات
```

**الأنواع المتاحة:**
```
- membership       → شهادة الانتساب
- good_standing   → شهادة حسن السير والسلوك
- classification  → شهادة التصنيف
- experience      → شهادة الخبرة والمشاريع
```

**الاستجابة:**
```json
{
  "status": true,
  "message": "تم تقديم الطلب بنجاح",
  "status_code": 201,
  "items": {
    "request": {
      "id": 3,
      "type": "classification",
      "status": "pending",
      "request_date": "2026-07-07T15:30:00Z",
      "issue_date": null,
      "certificate_url": null,
      "reject_reason": null,
      "notes": "شهادة معترف بها دولياً"
    }
  }
}
```

---

## 🛠️ خطوات التطوير

### **المرحلة 1: إعداد البيئة**

```bash
# 1. استنساخ المستودع
git clone https://github.com/eslamalbaik/admin_dshbaord_pfi.git
cd admin_dshbaord_pfi

# 2. الفرع الحالي
git checkout feature/arab-contractors-union

# 3. تثبيت الاعتماديات
cd arab-contractors-union-front
npm install

cd ../arab-contractors-union-api
composer install
```

---

### **المرحلة 2: إنشاء الـ Controllers**

#### Backend Controllers:

```php
// app/Http/Controllers/PaymentController.php
<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function gateway(Request $request)
    {
        $contractor = $request->user('contractor');
        
        return response()->json([
            'status' => true,
            'items' => [
                'contractor' => $contractor,
                'union_bank' => UnionBank::first(),
                'current_payment' => $contractor->payments()->latest()->first()
            ]
        ]);
    }
    
    public function submitPayment(Request $request)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:1',
            'proof_image' => 'required|image|max:5120',
            'notes' => 'nullable|string|max:500'
        ]);
        
        $path = $request->file('proof_image')->store('payments');
        
        $payment = $request->user('contractor')->payments()->create([
            'amount' => $validated['amount'],
            'proof_image_url' => $path,
            'notes' => $validated['notes'] ?? null,
            'status' => 'pending'
        ]);
        
        return response()->json([
            'status' => true,
            'message' => 'تم إرسال الإشعار بنجاح',
            'items' => ['payment' => $payment]
        ], 201);
    }
}
```

```php
// app/Http/Controllers/SupportController.php
<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;

class SupportController extends Controller
{
    public function index(Request $request)
    {
        $contractor = $request->user('contractor');
        
        return response()->json([
            'status' => true,
            'items' => [
                'contractor' => $contractor,
                'tickets' => $contractor->supportTickets()->latest()->get()
            ]
        ]);
    }
    
    public function store(Request $request)
    {
        $validated = $request->validate([
            'subject' => 'required|string|max:255',
            'category' => 'required|in:billing,membership,technical,complaint,inquiry,other',
            'description' => 'required|string|min:20|max:5000',
            'attachments' => 'nullable|array|max:5',
            'attachments.*' => 'file|max:10240'
        ]);
        
        $ticket = $request->user('contractor')->supportTickets()->create([
            'subject' => $validated['subject'],
            'category' => $validated['category'],
            'description' => $validated['description'],
            'status' => 'open',
            'priority' => 'medium'
        ]);
        
        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $ticket->attachments()->create([
                    'file_path' => $file->store('tickets')
                ]);
            }
        }
        
        return response()->json([
            'status' => true,
            'message' => 'تم إنشاء الطلب بنجاح',
            'items' => ['ticket' => $ticket]
        ], 201);
    }
}
```

```php
// app/Http/Controllers/CertificateController.php
<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;

class CertificateController extends Controller
{
    public function index(Request $request)
    {
        $contractor = $request->user('contractor');
        
        return response()->json([
            'status' => true,
            'items' => [
                'contractor' => $contractor,
                'can_request' => $this->canRequest($contractor),
                'requirement_issues' => $this->getRequirementIssues($contractor),
                'requests' => $contractor->certificateRequests()->latest()->get()
            ]
        ]);
    }
    
    public function store(Request $request)
    {
        $validated = $request->validate([
            'type' => 'required|in:membership,good_standing,classification,experience',
            'notes' => 'nullable|string|max:500'
        ]);
        
        $contractor = $request->user('contractor');
        
        if (!$this->canRequest($contractor)) {
            return response()->json([
                'status' => false,
                'message' => 'لا يمكن تقديم طلب جديد حالياً'
            ], 403);
        }
        
        $request = $contractor->certificateRequests()->create([
            'type' => $validated['type'],
            'notes' => $validated['notes'] ?? null,
            'status' => 'pending'
        ]);
        
        return response()->json([
            'status' => true,
            'message' => 'تم تقديم الطلب بنجاح',
            'items' => ['request' => $request]
        ], 201);
    }
    
    private function canRequest($contractor)
    {
        // تحقق من المتطلبات المتبقية
        return $contractor->requirementIssues()->count() === 0;
    }
    
    private function getRequirementIssues($contractor)
    {
        // استرجع المتطلبات المتبقية
        return $contractor->requirementIssues()->get();
    }
}
```

---

### **المرحلة 3: إنشاء الـ Routes**

```php
// routes/api.php
<?php

use Illuminate\Support\Facades\Route;

Route::middleware('auth:contractor')->group(function () {
    // بوابة الدفع
    Route::get('/contractor/payment-gateway', [App\Http\Controllers\PaymentController::class, 'gateway']);
    Route::post('/contractor/payment-submit', [App\Http\Controllers\PaymentController::class, 'submitPayment']);
    
    // الدعم الفني
    Route::get('/contractor/support', [App\Http\Controllers\SupportController::class, 'index']);
    Route::post('/contractor/support/create', [App\Http\Controllers\SupportController::class, 'store']);
    
    // الشهادات
    Route::get('/contractor/certificate-request', [App\Http\Controllers\CertificateController::class, 'index']);
    Route::post('/contractor/certificate-request/create', [App\Http\Controllers\CertificateController::class, 'store']);
});
```

---

## 🧪 الاختبار

### **1. اختبار الـ Backend**

```bash
# استخدام Postman
1. استورد: CONTRACTOR_DASHBOARD_API.postman_collection.json
2. عيّن contractor_token في المتغيرات
3. اختبر كل endpoint

# أو استخدم curl:
curl -X GET \
  http://localhost:8000/api/v1/contractor/payment-gateway \
  -H "Authorization: Bearer YOUR_TOKEN"
```

### **2. اختبار الـ Frontend**

```bash
# تشغيل الخادم
npm run dev

# زيارة الصفحات
http://localhost:5173/contractor/payment-gateway
http://localhost:5173/contractor/support
http://localhost:5173/contractor/certificate-request
```

### **3. اختبار التكامل**

```bash
# 1. تأكد أن الـ API يعمل
curl http://localhost:8000/api/v1/health

# 2. تسجيل الدخول وحصول على token
curl -X POST http://localhost:8000/api/v1/login \
  -d "membership_number=928_g&password=Test@12345"

# 3. استخدم الـ token في الطلبات
curl -X GET \
  http://localhost:8000/api/v1/contractor/payment-gateway \
  -H "Authorization: Bearer TOKEN"
```

---

## 🐛 حل المشاكل

### **المشكلة 1: CORS Error**
```
Error: Access to XMLHttpRequest blocked by CORS policy
```
**الحل:**
```php
// config/cors.php
'paths' => ['api/*', 'sanctum/csrf-cookie'],
'allowed_methods' => ['*'],
'allowed_origins' => ['http://localhost:5173', 'http://localhost:3000'],
'allowed_origins_patterns' => [],
'allowed_headers' => ['*'],
'exposed_headers' => [],
'max_age' => 0,
'supports_credentials' => true,
```

### **المشكلة 2: 401 Unauthorized**
```
Error: 401 Unauthorized
```
**الحل:**
- تحقق من أن الـ token موجود
- تحقق من صلاحية الـ token
- تأكد من رفع الـ Authorization header بشكل صحيح

### **المشكلة 3: File Upload Error**
```
Error: SQLSTATE[HY000]: General error: 1030 Got error
```
**الحل:**
- تحقق من صلاحيات المجلد `storage`
- قم بتشغيل: `php artisan storage:link`

---

## 🚀 الإطلاق

### **Before Going Live:**

```bash
# 1. Build Frontend
cd arab-contractors-union-front
npm run build

# 2. Migrate Database
cd ../arab-contractors-union-api
php artisan migrate --force

# 3. Cache Config
php artisan config:cache
php artisan view:cache
php artisan route:cache

# 4. Set Production Env
APP_ENV=production
APP_DEBUG=false
```

### **Deploy to Server:**

```bash
# استخدم GitHub Actions أو خدمة نشر مثل Vercel, Heroku, إلخ
# أو انشر يدويًا على الخادم
```

---

## 📞 الدعم والمساعدة

- 📧 Email: eslamahmad2000t@gmail.com
- 🐙 GitHub Issues: https://github.com/eslamalbaik/admin_dshbaord_pfi/issues
- 💬 WhatsApp: +970 5X XXX XXXX

---

**تم الإنجاز بنجاح! 🎉**

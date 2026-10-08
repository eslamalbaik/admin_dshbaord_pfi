<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\ContractorController;
use App\Http\Controllers\Api\MembershipController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\PenaltyController;
use App\Http\Controllers\Api\TenderController;
use App\Http\Controllers\Api\TenderCategoryController;
use App\Http\Controllers\Api\DocumentController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\ContractorAuthController;
use App\Http\Controllers\Api\ContractorRegisterController;
use App\Http\Controllers\Api\ContractorDashboardController;
use App\Http\Controllers\Api\EquipmentTypeController;
use App\Http\Controllers\Api\EquipmentController;
use App\Http\Controllers\Api\ReportsController;
use App\Http\Controllers\Api\NewsController;
use App\Http\Controllers\Api\EventController;
use App\Http\Controllers\Api\TermsController;
use App\Http\Controllers\Api\LegalFileController;
use App\Http\Controllers\Api\LegalFileCategoryController;
use App\Http\Controllers\Api\BankAccountController;
use App\Http\Controllers\Api\SettingController;
use App\Http\Controllers\Api\CertificateRequestController;
use App\Http\Controllers\Api\SupportTicketController;
use App\Http\Controllers\Api\ContractorHomeController;

// ============================================================
//  API v1
// ============================================================
Route::prefix('v1')->group(function () {

    // --------------------------------------------------------
    //  Contractor Mobile App — Auth (Public)
    // --------------------------------------------------------
    Route::prefix('contractor/auth')->group(function () {
        // throttle:auth (5 محاولات كل دقيقة) لمنع brute force
        Route::middleware('throttle:auth')->post('login', [ContractorAuthController::class, 'login']);

        // تسجيل العضو (خطوتان): التحقق من الهوية ثم تعيين كلمة المرور
        Route::middleware('throttle:auth')->group(function () {
            Route::post('verify-identity', [ContractorRegisterController::class, 'verifyIdentity']);
            Route::post('set-password',    [ContractorRegisterController::class, 'setPassword']);
            Route::post('verify-otp',      [ContractorRegisterController::class, 'verifyOtp']);
            Route::post('resend-otp',      [ContractorRegisterController::class, 'resendOtp']);
            Route::post('forgot-password/send-otp', [ContractorRegisterController::class, 'forgotPasswordSendOtp']);
            Route::post('forgot-password/reset',    [ContractorRegisterController::class, 'forgotPasswordReset']);
        });
    });

    // --------------------------------------------------------
    //  Contractor Mobile App — Auth (Protected)
    // --------------------------------------------------------
    Route::middleware(['auth:sanctum', 'contractor.active'])->prefix('contractor/auth')->group(function () {
        Route::post('logout',          [ContractorAuthController::class, 'logout']);
        Route::get('profile',          [ContractorAuthController::class, 'profile']);
        Route::patch('profile',        [ContractorAuthController::class, 'updateProfile']);
        Route::post('profile/update',  [ContractorAuthController::class, 'updateFullProfile']);
        Route::post('profile/phone/request-otp', [ContractorAuthController::class, 'requestPhoneChangeOtp']);
        Route::post('profile/phone/verify-otp',  [ContractorAuthController::class, 'verifyPhoneChangeOtp']);
        Route::post('logo',            [ContractorAuthController::class, 'updateLogo']);
        Route::get('profile/pdf',      [ContractorAuthController::class, 'exportPdf']);
        Route::get('profile/download-file/{field}', [ContractorAuthController::class, 'downloadFile']);
        Route::post('change-password', [ContractorAuthController::class, 'changePassword']);

        // إشعارات المقاول (صندوق الوارد) — نظام الإشعارات القديم (Laravel notifications)
        Route::get('notifications',                    [NotificationController::class, 'index']);
        Route::get('notifications/unread-count',       [NotificationController::class, 'unreadCount']);
        Route::post('notifications/read',              [NotificationController::class, 'markAllRead']);
        Route::patch('notifications/{id}/mark-as-read', [NotificationController::class, 'markAsRead']);

        // إشعارات التطبيق الجديدة (App Notifications — Announcements, Reminders, etc.)
        Route::get('app-notifications',                 [NotificationController::class, 'getContractorNotifications']);
        Route::get('app-notifications/unread-count',    [NotificationController::class, 'getContractorUnreadCount']);
        Route::patch('app-notifications/{notification}', [NotificationController::class, 'markNotificationAsRead']);
    });

    // --------------------------------------------------------
    //  Contractor Dashboard (Protected)
    // --------------------------------------------------------
    Route::middleware(['auth:sanctum', 'contractor.active'])->prefix('contractor')->group(function () {
        // الشاشة الرئيسية للتطبيق (نداء واحد مجمّع) + سجل التحديثات الكامل
        Route::get('home',         [ContractorHomeController::class, 'index']);
        Route::get('home/updates', [ContractorHomeController::class, 'updates']);

        Route::get('dashboard',    [ContractorDashboardController::class, 'index']);
        Route::get('memberships',  [ContractorDashboardController::class, 'memberships']);
        Route::get('payments',     [ContractorDashboardController::class, 'payments']);
        Route::get('documents',    [ContractorDashboardController::class, 'documents']);
        Route::post('documents',               [DocumentController::class, 'contractorStore']);
        Route::delete('documents/{document}',  [DocumentController::class, 'contractorDestroy']);

        // شاشة الدفع — رفع إشعار التحويل ومتابعته
        Route::post('payments/transfer', [PaymentController::class, 'submitTransfer']);
        Route::get('payments/transfer',  [PaymentController::class, 'myTransfers']);
        // سجل المدفوعات التفصيلي (كشف حساب): الذمم والغرامات والدفعات والمتبقي بعد كل حركة
        Route::get('payments/statement', [\App\Http\Controllers\Api\ContractorBalanceController::class, 'myStatement']);
        Route::get('payments/{payment}/receipt', [PaymentController::class, 'receipt']);
        Route::get('payments/{payment}',         [PaymentController::class, 'show']);

        // شاشة الدعم الفني — أُعيد تفعيلها بتصميم جديد (REQ-23 تراجع عنها): تذكرة مع رقم واتساب
        // للتواصل بدل تحويل مباشر بالكامل خارج التطبيق.
        Route::get('support-tickets',           [SupportTicketController::class, 'myTickets']);
        Route::post('support-tickets',          [SupportTicketController::class, 'store']);
        Route::get('support-tickets/{ticket}',  [SupportTicketController::class, 'showMine']);
        Route::post('support-tickets/{ticket}/reply', [SupportTicketController::class, 'replyMine']);

        // شاشة طلب شهادة العضوية
        Route::get('certificate-requests',  [CertificateRequestController::class, 'index']);
        Route::post('certificate-requests', [CertificateRequestController::class, 'store']);
        Route::get('certificates/status',   [CertificateRequestController::class, 'certificatesStatus']);

        // شاشة الملف المالي — كشف حساب "ما له وما عليه"
        Route::get('financial', [ContractorDashboardController::class, 'financial']);

        // أهلية تجديد العضوية (تُمنع مع ذمم غير مسدَّدة)
        Route::get('renewal-eligibility', [ContractorDashboardController::class, 'renewalEligibility']);

        // كائن موحّد لتفاصيل الاشتراك ووضعه — لإعادة الاستخدام بأكثر من شاشة
        Route::get('subscription', [ContractorDashboardController::class, 'subscription']);

        // رصيد المقاول الصافي (له − عليه) — نفس حسبة شاشة الأرصدة بالداشبورد
        Route::get('balance', [\App\Http\Controllers\Api\ContractorBalanceController::class, 'mine']);

        // شاشة العطاءات — تصفح موثَّق (فعّال/مؤرشف/مجالاتي) + حفظ بالمفضلة (REQ-09/11/13)
        Route::get('tenders',                   [TenderController::class, 'contractorIndex']);
        Route::get('tenders/bookmarked',         [TenderController::class, 'bookmarked']);
        Route::get('tenders/{tender}',           [TenderController::class, 'contractorShow']);
        Route::post('tenders/{tender}/bookmark', [TenderController::class, 'bookmark']);
        Route::delete('tenders/{tender}/bookmark', [TenderController::class, 'unbookmark']);

        // التعميمات الثابتة — Pop-up أول فتح + إقرار القراءة (REQ-20)
        Route::get('circulars/pending', [\App\Http\Controllers\Api\AnnouncementController::class, 'pending']);
        Route::post('circulars/{announcement}/acknowledge', [\App\Http\Controllers\Api\AnnouncementController::class, 'acknowledge']);

        // أرشيف التعميمات + فتح تفاصيل التعميم من الإشعار (Deep linking)
        Route::get('announcements/{announcement}', [\App\Http\Controllers\Api\AnnouncementController::class, 'show']);

        // الفعاليات — تصفح + انضمام/إلغاء (RSVP) — REQ-21
        Route::get('events',                  [EventController::class, 'contractorEvents']);
        Route::get('events/{event}',          [EventController::class, 'contractorEventShow']);
        Route::post('events/{event}/join',    [EventController::class, 'joinEvent']);
        Route::delete('events/{event}/join',  [EventController::class, 'leaveEvent']);

        // سوق الآليات — "آلياتي" + تصفح السوق + بلاغات + الباقات (REQ-04→08)
        Route::get('equipment-packages',            [\App\Http\Controllers\Api\ContractorEquipmentController::class, 'packages']);
        Route::get('equipment/form-options',         [\App\Http\Controllers\Api\ContractorEquipmentController::class, 'formOptions']);
        Route::get('equipment/subscription-status',  [\App\Http\Controllers\Api\ContractorEquipmentController::class, 'subscriptionStatus']);
        Route::get('equipment/marketplace',          [\App\Http\Controllers\Api\ContractorEquipmentController::class, 'marketplace']);
        Route::get('equipment/marketplace/{equipment}', [\App\Http\Controllers\Api\ContractorEquipmentController::class, 'show']);
        Route::get('equipment/marketplace/{equipment}/availability', [\App\Http\Controllers\Api\ContractorEquipmentController::class, 'availability']);
        Route::post('equipment/{equipment}/reservations', [\App\Http\Controllers\Api\ContractorEquipmentController::class, 'requestReservation']);
        Route::get('my-equipment-reservations',           [\App\Http\Controllers\Api\ContractorEquipmentController::class, 'myReservations']);
        Route::delete('equipment-reservations/{equipmentReservation}', [\App\Http\Controllers\Api\ContractorEquipmentController::class, 'cancelReservation']);
        Route::get('equipment',                      [\App\Http\Controllers\Api\ContractorEquipmentController::class, 'index']);
        Route::post('equipment',                     [\App\Http\Controllers\Api\ContractorEquipmentController::class, 'store']);
        Route::patch('equipment/{equipment}',        [\App\Http\Controllers\Api\ContractorEquipmentController::class, 'update']);
        Route::delete('equipment/{equipment}',       [\App\Http\Controllers\Api\ContractorEquipmentController::class, 'destroy']);
        Route::post('equipment/{equipment}/report',  [\App\Http\Controllers\Api\ContractorEquipmentController::class, 'report']);
        // إدارة صور الإعلان بعد النشر (REQ-08 #2/#3/#5) — لم تكن موجودة أصلاً
        Route::post('equipment/{equipment}/images',                          [\App\Http\Controllers\Api\ContractorEquipmentController::class, 'uploadImages']);
        Route::delete('equipment/{equipment}/images/{equipmentImage}',       [\App\Http\Controllers\Api\ContractorEquipmentController::class, 'deleteImage']);
        Route::post('equipment/{equipment}/images/{equipmentImage}/primary', [\App\Http\Controllers\Api\ContractorEquipmentController::class, 'setPrimaryImage']);
    });

    // --------------------------------------------------------
    //  Tenders — Public (no auth) — بدون union_notes وبيانات إدارية
    // --------------------------------------------------------
    Route::get('tenders-public',          [TenderController::class, 'publicIndex']);
    Route::get('tenders-public/{tender}', [TenderController::class, 'publicShow']);
    Route::get('tender-categories-public', [TenderCategoryController::class, 'publicIndex']);

    // --------------------------------------------------------
    //  News — Public (no auth)
    // --------------------------------------------------------
    Route::prefix('news')->group(function () {
        Route::get('latest',  [NewsController::class, 'latest']);
        Route::get('/',       [NewsController::class, 'index']);
        // Accepts the numeric id (matches `reference_id` in the contractor home feed);
        // a slug still resolves too, for older clients.
        Route::get('{news}',  [NewsController::class, 'show']);
    });

    // --------------------------------------------------------
    //  Events — Public (no auth) — كيان مستقل عن الأخبار
    // --------------------------------------------------------
    Route::prefix('events')->group(function () {
        Route::get('latest',  [EventController::class, 'latest']);
        Route::get('/',       [EventController::class, 'index']);
        Route::get('{slug}',  [EventController::class, 'show']);
    });

    // --------------------------------------------------------
    //  Announcements — Public (no auth) — كيان مستقل عن الأخبار
    // --------------------------------------------------------
    Route::get('announcements',              [\App\Http\Controllers\Api\AnnouncementController::class, 'index']);
    Route::get('announcements/{announcement}', [\App\Http\Controllers\Api\AnnouncementController::class, 'show']);

    // --------------------------------------------------------
    //  Dynamic Pages — Public (صفحات slug ديناميكية)
    // --------------------------------------------------------
    Route::get('pages/{slug}', [\App\Http\Controllers\Api\PageController::class, 'showPublic']);

    // --------------------------------------------------------
    //  Terms & Conditions — Public (no auth)
    // --------------------------------------------------------
    Route::get('terms', [TermsController::class, 'public']);

    // --------------------------------------------------------
    //  Privacy Policy — Public (no auth)
    // --------------------------------------------------------
    Route::get('privacy-policy', [TermsController::class, 'publicPrivacy']);

    // --------------------------------------------------------
    //  Legal Library — Public (no auth)
    // --------------------------------------------------------
    Route::get('legal-files', [LegalFileController::class, 'publicIndex']);

    // --------------------------------------------------------
    //  Bank Accounts (شاشة الدفع) — Public (no auth)
    // --------------------------------------------------------
    Route::get('bank-accounts', [BankAccountController::class, 'publicIndex']);

    // --------------------------------------------------------
    //  Certificate Verification (QR) — Public (no auth)
    // --------------------------------------------------------
    Route::get('certificates/verify/{token}', [\App\Http\Controllers\Api\CertificateRequestController::class, 'verify']);
    // ملف الشهادة للمقاول برابط موقَّع — بيسجّل فتحه (اللوحة بتعرض "فتحها المقاول")
    Route::get('certificates/{certificateRequest}/certificate.pdf', [\App\Http\Controllers\Api\CertificateRequestController::class, 'file'])
        ->name('certificates.file');

    // --------------------------------------------------------
    //  Landing Home — Public (كل بيانات الصفحة الرئيسية في نداء واحد)
    // --------------------------------------------------------
    Route::get('landing/home', [\App\Http\Controllers\Api\LandingController::class, 'home']);

    // --------------------------------------------------------
    //  Maintenance Mode — Public
    // --------------------------------------------------------
    Route::get('app/maintenance',                [SettingController::class, 'maintenance']);
    Route::get('app/maintenance/preview/{slug}', [SettingController::class, 'maintenancePreview']);

    // --------------------------------------------------------
    //  App Contact Info (شاشة الدعم) — Public (no auth)
    // --------------------------------------------------------
    Route::get('app/contact', [SettingController::class, 'contact']);

    // --------------------------------------------------------
    //  About the Union (شاشة عن الاتحاد) — Public (no auth)
    // --------------------------------------------------------
    Route::get('app/about', [SettingController::class, 'about']);

    // --------------------------------------------------------
    //  General Settings (معلومات الدفع، التواصل، السوشال ميديا) — Public
    // --------------------------------------------------------
    Route::get('app/settings', [SettingController::class, 'general']);

    // --------------------------------------------------------
    //  Governorates & Cities (المحافظات والمدن) — Public
    // --------------------------------------------------------
    Route::get('app/governorates', [SettingController::class, 'governorates']);

    // --------------------------------------------------------
    //  Specialties Catalog (المجالات/الاختصاصات/الدرجات) — Public
    // --------------------------------------------------------
    Route::get('app/specialties-catalog', [SettingController::class, 'specialtiesCatalog']);

    // --------------------------------------------------------
    //  Admin Auth — Public
    // --------------------------------------------------------
    Route::prefix('auth')->group(function () {
        Route::post('login',    [AuthController::class, 'login']);
        Route::post('register', [AuthController::class, 'register']);

        // Google OAuth
        Route::get('google/redirect',  [AuthController::class, 'redirectToGoogle']);
        Route::post('google/exchange', [AuthController::class, 'exchangeGoogleCode']);
    });

    // --------------------------------------------------------
    //  Protected — Sanctum
    // --------------------------------------------------------
    // supervisor.scope: المشرف بيوصل بس للمسارات اللي عليها perm:... (انظر RestrictSupervisor)
    Route::middleware(['auth:sanctum', 'supervisor.scope'])->group(function () {

        // بيانات المستخدم الحالي (authStore.fetchUser)
        Route::get('user', function (\Illuminate\Http\Request $request) {
            $user = $request->user();
            return response()->json([
                'id'       => $user->id,
                'fullName' => $user->name,
                'role'     => $user->role,
                'email'    => $user->email,
                'avatar'   => $user->avatar ?? null,
                'permissions' => $user instanceof \App\Models\User ? $user->dashboardPermissions() : null,
            ]);
        });

        // Admin Auth — me / logout
        Route::prefix('auth')->group(function () {
            Route::post('logout', [AuthController::class, 'logout']);
            Route::get('me', function (\Illuminate\Http\Request $request) {
                $user = $request->user();
                return response()->json([
                    'id'       => $user->id,
                    'fullName' => $user->name,
                    'role'     => $user->role,
                    'email'    => $user->email,
                    'avatar'   => $user->avatar ?? null,
                    'permissions' => $user instanceof \App\Models\User ? $user->dashboardPermissions() : null,
                ]);
            });
        });

        // --------------------------------------------------------
        //  Dashboard
        // --------------------------------------------------------
        Route::get('dashboard/stats', [DashboardController::class, 'index'])->middleware('perm:dashboard.home');

        // --------------------------------------------------------
        //  News — Admin CRUD
        // --------------------------------------------------------
        Route::prefix('admin/news')->group(function () {
            Route::get('/',          [NewsController::class, 'adminIndex'])->middleware('perm:content.news');
            Route::post('/',         [NewsController::class, 'store'])->middleware('perm:content.news');
            Route::put('{news}',     [NewsController::class, 'update'])->middleware('perm:content.news');
            Route::delete('{news}',  [NewsController::class, 'destroy'])->middleware('perm:content.news');
            Route::delete('{news}/gallery-image', [NewsController::class, 'destroyGalleryImage'])->middleware('perm:content.news');
        });

        // --------------------------------------------------------
        //  Events — Admin CRUD
        // --------------------------------------------------------
        Route::prefix('admin/events')->group(function () {
            Route::get('/',                 [EventController::class, 'adminIndex'])->middleware('perm:content.events');
            Route::post('/',                [EventController::class, 'store'])->middleware('perm:content.events');
            Route::put('{event}',           [EventController::class, 'update'])->middleware('perm:content.events');
            Route::delete('{event}',        [EventController::class, 'destroy'])->middleware('perm:content.events');
            Route::get('{event}/attendees', [EventController::class, 'attendees'])->middleware('perm:content.events');
        });

        // --------------------------------------------------------
        //  Announcements — Admin CRUD
        // --------------------------------------------------------
        Route::prefix('admin/announcements')->group(function () {
            Route::get('/',                  [\App\Http\Controllers\Api\AnnouncementController::class, 'adminIndex'])->middleware('perm:content.announcements');
            Route::post('/',                 [\App\Http\Controllers\Api\AnnouncementController::class, 'store'])->middleware('perm:content.announcements');
            Route::put('{announcement}',     [\App\Http\Controllers\Api\AnnouncementController::class, 'update'])->middleware('perm:content.announcements');
            Route::delete('{announcement}',  [\App\Http\Controllers\Api\AnnouncementController::class, 'destroy'])->middleware('perm:content.announcements');
        });

        Route::get('announcement-categories',    [\App\Http\Controllers\Api\AnnouncementCategoryController::class, 'index'])->middleware('perm:content.announcement_categories|content.announcements,view');
        Route::post('announcement-categories',   [\App\Http\Controllers\Api\AnnouncementCategoryController::class, 'store'])->middleware('perm:content.announcement_categories');
        Route::patch('announcement-categories/{announcementCategory}',  [\App\Http\Controllers\Api\AnnouncementCategoryController::class, 'update'])->middleware('perm:content.announcement_categories');
        Route::delete('announcement-categories/{announcementCategory}', [\App\Http\Controllers\Api\AnnouncementCategoryController::class, 'destroy'])->middleware('perm:content.announcement_categories');

        // --------------------------------------------------------
        //  Terms & Conditions — Admin CRUD
        // --------------------------------------------------------
        Route::middleware('role:admin')->group(function () {
            Route::get('dashboard/terms',          [TermsController::class, 'index'])->middleware('perm:settings.terms');
            Route::post('dashboard/terms',         [TermsController::class, 'store'])->middleware('perm:settings.terms');
            Route::put('dashboard/terms/{term}',   [TermsController::class, 'update'])->middleware('perm:settings.terms');
            Route::delete('dashboard/terms/{term}',[TermsController::class, 'destroy'])->middleware('perm:settings.terms');
        });

        // --------------------------------------------------------
        //  Legal Library — Admin CRUD
        // --------------------------------------------------------
        Route::middleware('role:admin')->group(function () {
            Route::get('dashboard/legal-files',                  [LegalFileController::class, 'index'])->middleware('perm:legal.library');
            Route::post('dashboard/legal-files',                 [LegalFileController::class, 'store'])->middleware('perm:legal.library');
            Route::post('dashboard/legal-files/{legalFile}',     [LegalFileController::class, 'update'])->middleware('perm:legal.library,update');
            Route::delete('dashboard/legal-files/{legalFile}',   [LegalFileController::class, 'destroy'])->middleware('perm:legal.library');

            Route::get('dashboard/legal-file-categories',                          [LegalFileCategoryController::class, 'index'])->middleware('perm:legal.library');
            Route::post('dashboard/legal-file-categories',                         [LegalFileCategoryController::class, 'store'])->middleware('perm:legal.library');
            Route::post('dashboard/legal-file-categories/{legalFileCategory}',     [LegalFileCategoryController::class, 'update'])->middleware('perm:legal.library,update');
            Route::delete('dashboard/legal-file-categories/{legalFileCategory}',   [LegalFileCategoryController::class, 'destroy'])->middleware('perm:legal.library');
        });

        // --------------------------------------------------------
        //  Contractors
        // --------------------------------------------------------
        Route::get('contractors/next-membership-number', [ContractorController::class, 'nextMembershipNumber'])->middleware('perm:contractors.list,create');
        // القائمة/التفاصيل بتستخدمها صفحات تانية لاختيار مقاول (الذمم، المدفوعات، الوثائق...)
        Route::apiResource('contractors', ContractorController::class)
             ->only(['index', 'show'])
             ->middleware('perm:contractors.list|contractors.penalties|finance.payments|finance.dues|finance.balances|marketplace.equipment|services.membership_certificates|services.certificate_requests|documents.manage,view');
        Route::apiResource('contractors', ContractorController::class)
             ->only(['store', 'update', 'destroy'])
             ->middleware('perm:contractors.list');
        Route::post('contractors/{contractor}/qr', [ContractorController::class, 'generateQR'])->middleware('perm:contractors.list,update');
        Route::patch('contractors/{contractor}/status', [ContractorController::class, 'changeStatus'])->middleware('perm:contractors.list');
        Route::patch('contractors/{contractor}/freeze', [ContractorController::class, 'freeze'])->middleware('perm:contractors.list');
        Route::patch('contractors/{contractor}/contact', [ContractorController::class, 'updateContact'])->middleware('perm:contractors.list');

        // --------------------------------------------------------
        //  Memberships
        // --------------------------------------------------------
        Route::get('memberships/pending',               [MembershipController::class, 'pending'])->middleware('perm:contractors.memberships');
        Route::get('memberships',                       [MembershipController::class, 'index'])->middleware('perm:contractors.memberships');
        Route::post('memberships',                      [MembershipController::class, 'store'])->middleware('perm:contractors.memberships');
        Route::post('memberships/{membership}/approve', [MembershipController::class, 'approve'])->middleware('perm:contractors.memberships,update');
        Route::post('memberships/{membership}/reject',  [MembershipController::class, 'reject'])->middleware('perm:contractors.memberships,update');

        // --------------------------------------------------------
        //  Payments
        // --------------------------------------------------------
        Route::get('payments/transactions',  [PaymentController::class, 'index'])->middleware('perm:finance.payments');
        Route::post('payments/transactions', [PaymentController::class, 'store'])->middleware('perm:finance.payments');
        Route::post('payments/transactions/manual', [PaymentController::class, 'storeManual'])
             ->middleware('role:admin,accountant')
             ->middleware('perm:finance.payments');
        Route::post('payments/transactions/{payment}/confirm', [PaymentController::class, 'confirm'])->middleware('perm:finance.payments,update');
        Route::post('payments/transactions/{payment}/reject',  [PaymentController::class, 'reject'])->middleware('perm:finance.payments,update');
        Route::post('payments/transactions/{payment}/status',  [PaymentController::class, 'changeStatus'])->middleware('perm:finance.payments,update');
        Route::post('payments/transactions/{payment}/receipt-image', [PaymentController::class, 'uploadReceiptImage'])->middleware('perm:finance.payments,update');

        // --------------------------------------------------------
        //  Bank Accounts — Admin CRUD (شاشة الدفع)
        // --------------------------------------------------------
        Route::middleware('role:admin')->group(function () {
            Route::get('dashboard/bank-accounts',                [BankAccountController::class, 'index'])->middleware('perm:finance.bank_accounts');
            Route::post('dashboard/bank-accounts',               [BankAccountController::class, 'store'])->middleware('perm:finance.bank_accounts');
            Route::match(['post', 'put'], 'dashboard/bank-accounts/{bankAccount}', [BankAccountController::class, 'update'])->middleware('perm:finance.bank_accounts,update');
            Route::delete('dashboard/bank-accounts/{bankAccount}',[BankAccountController::class, 'destroy'])->middleware('perm:finance.bank_accounts');
        });

        // --------------------------------------------------------
        //  App Settings — Admin (واتساب/بريد الدعم، بيانات الاتحاد، السوشال ميديا)
        // --------------------------------------------------------
        Route::middleware('role:admin')->group(function () {
            Route::get('dashboard/settings',       [SettingController::class, 'index'])->middleware('perm:settings.app');
            Route::put('dashboard/settings',       [SettingController::class, 'update'])->middleware('perm:settings.app');
            Route::post('dashboard/settings/logo', [SettingController::class, 'uploadLogo'])->middleware('perm:settings.app,update');
            Route::post('dashboard/settings/cover-image', [SettingController::class, 'uploadCoverImage'])->middleware('perm:settings.app,update');
            Route::post('dashboard/settings/service-icon', [SettingController::class, 'uploadServiceIcon'])->middleware('perm:settings.app,update');
        });

        // --------------------------------------------------------
        //  Activity Log — سجل النشاط الإداري (Admin only، للمساءلة والمراجعة)
        // --------------------------------------------------------
        Route::middleware('role:admin')->group(function () {
            Route::get('dashboard/activity-logs',         [\App\Http\Controllers\Api\ActivityLogController::class, 'index'])->middleware('perm:settings.activity_log,view');
            Route::get('dashboard/activity-logs/actions',  [\App\Http\Controllers\Api\ActivityLogController::class, 'actions'])->middleware('perm:settings.activity_log,view');
            Route::get('dashboard/activity-logs/critical-summary', [\App\Http\Controllers\Api\ActivityLogController::class, 'criticalSummary'])->middleware('perm:settings.activity_log,view');
        });

        // --------------------------------------------------------
        //  Supervisors — إدارة المشرفين وصلاحياتهم حسب الأقسام (أدمن فقط، ما في perm:
        //  عشان ولا مشرف يقدر يعطي حاله أو غيره صلاحيات)
        // --------------------------------------------------------
        Route::middleware('role:admin')->prefix('dashboard/supervisors')->group(function () {
            Route::get('permissions-catalog',  [\App\Http\Controllers\Api\SupervisorController::class, 'catalog']);
            Route::get('/',                    [\App\Http\Controllers\Api\SupervisorController::class, 'index']);
            Route::post('/',                   [\App\Http\Controllers\Api\SupervisorController::class, 'store']);
            Route::get('{id}',                 [\App\Http\Controllers\Api\SupervisorController::class, 'show'])->whereNumber('id');
            Route::put('{id}',                 [\App\Http\Controllers\Api\SupervisorController::class, 'update'])->whereNumber('id');
            Route::patch('{id}/status',        [\App\Http\Controllers\Api\SupervisorController::class, 'updateStatus'])->whereNumber('id');
            Route::post('{id}/reset-password', [\App\Http\Controllers\Api\SupervisorController::class, 'resetPassword'])->whereNumber('id');
        });

        // --------------------------------------------------------
        //  Dynamic Pages — Admin CRUD
        // --------------------------------------------------------
        Route::middleware('role:admin')->group(function () {
            Route::get('dashboard/pages',           [\App\Http\Controllers\Api\PageController::class, 'index'])->middleware('perm:settings.pages');
            Route::post('dashboard/pages',          [\App\Http\Controllers\Api\PageController::class, 'store'])->middleware('perm:settings.pages');
            Route::put('dashboard/pages/{page}',    [\App\Http\Controllers\Api\PageController::class, 'update'])->middleware('perm:settings.pages');
            Route::delete('dashboard/pages/{page}', [\App\Http\Controllers\Api\PageController::class, 'destroy'])->middleware('perm:settings.pages');
        });

        // --------------------------------------------------------
        //  Certificate Requests — Admin (طلبات شهادات العضوية)
        // --------------------------------------------------------
        // المسارات الثابتة قبل {certificateRequest} حتى لا تُلتقط كمعرّف
        Route::middleware('role:admin')->group(function () {
            Route::get('dashboard/certificate-requests',                                  [CertificateRequestController::class, 'adminIndex'])->middleware('perm:services.certificate_requests|services.membership_certificates');
            Route::post('dashboard/certificate-requests/issue-membership',                [CertificateRequestController::class, 'adminIssueMembership'])->middleware('perm:services.membership_certificates,create');
            Route::post('dashboard/certificate-requests/bulk-delete',                     [CertificateRequestController::class, 'bulkDestroy'])->middleware('perm:services.membership_certificates,delete');
            Route::get('dashboard/certificate-requests/{certificateRequest}',             [CertificateRequestController::class, 'show'])->middleware('perm:services.certificate_requests|services.membership_certificates');
            Route::post('dashboard/certificate-requests/{certificateRequest}/approve',    [CertificateRequestController::class, 'approve'])->middleware('perm:services.certificate_requests,update');
            Route::post('dashboard/certificate-requests/{certificateRequest}/reject',     [CertificateRequestController::class, 'reject'])->middleware('perm:services.certificate_requests,update');
            Route::post('dashboard/certificate-requests/{certificateRequest}/issue',      [CertificateRequestController::class, 'issue'])->middleware('perm:services.certificate_requests,update');
            Route::post('dashboard/certificate-requests/{certificateRequest}/regenerate', [CertificateRequestController::class, 'regenerate'])->middleware('perm:services.membership_certificates,update');
            Route::delete('dashboard/certificate-requests/{certificateRequest}',          [CertificateRequestController::class, 'destroy'])->middleware('perm:services.certificate_requests|services.membership_certificates');
        });

        // --------------------------------------------------------
        //  Support Tickets — Admin (الدعم الفني، أُعيد تفعيلها بتصميم جديد)
        // --------------------------------------------------------
        Route::middleware('role:admin')->group(function () {
            Route::get('dashboard/support-tickets',                     [SupportTicketController::class, 'index'])->middleware('perm:services.support_tickets');
            Route::get('dashboard/support-tickets/{ticket}',             [SupportTicketController::class, 'show'])->middleware('perm:services.support_tickets');
            Route::post('dashboard/support-tickets/{ticket}/reply',      [SupportTicketController::class, 'reply'])->middleware('perm:services.support_tickets,update');
            Route::patch('dashboard/support-tickets/{ticket}/status',    [SupportTicketController::class, 'updateStatus'])->middleware('perm:services.support_tickets');
            Route::delete('dashboard/support-tickets/{ticket}',          [SupportTicketController::class, 'destroy'])->middleware('perm:services.support_tickets');
        });

        // --------------------------------------------------------
        //  Contractor Dues — الذمم المالية (أدمن + محاسب)
        // --------------------------------------------------------
        Route::middleware('role:admin,accountant')->group(function () {
            Route::get('dashboard/dues',                [\App\Http\Controllers\Api\ContractorDueController::class, 'index'])->middleware('perm:finance.dues');
            Route::get('dashboard/dues/summary',        [\App\Http\Controllers\Api\ContractorDueController::class, 'summary'])->middleware('perm:finance.dues');
            Route::get('dashboard/dues/by-contractor',  [\App\Http\Controllers\Api\ContractorDueController::class, 'byContractor'])->middleware('perm:finance.dues');
            Route::post('dashboard/dues/import',        [\App\Http\Controllers\Api\ContractorDueController::class, 'import'])->middleware('perm:finance.dues,create');
            Route::post('dashboard/contractors/{contractor}/dues/pay', [\App\Http\Controllers\Api\ContractorDueController::class, 'payForContractor'])->middleware('perm:finance.dues,update');
            Route::post('dashboard/dues',               [\App\Http\Controllers\Api\ContractorDueController::class, 'store'])->middleware('perm:finance.dues');
            Route::patch('dashboard/dues/{due}',        [\App\Http\Controllers\Api\ContractorDueController::class, 'update'])->middleware('perm:finance.dues');
            Route::post('dashboard/dues/{due}/settle',  [\App\Http\Controllers\Api\ContractorDueController::class, 'settle'])->middleware('perm:finance.dues,update');
            Route::delete('dashboard/dues/{due}',       [\App\Http\Controllers\Api\ContractorDueController::class, 'destroy'])->middleware('perm:finance.dues');
            Route::post('dashboard/dues/bulk-delete',   [\App\Http\Controllers\Api\ContractorDueController::class, 'bulkDestroy'])->middleware('perm:finance.dues,delete');

            // أرصدة المقاولين — صافي له/عليه لكل شركة
            Route::get('dashboard/balances',            [\App\Http\Controllers\Api\ContractorBalanceController::class, 'index'])->middleware('perm:finance.balances');
            Route::get('dashboard/balances/summary',    [\App\Http\Controllers\Api\ContractorBalanceController::class, 'summary'])->middleware('perm:finance.balances');
            Route::get('dashboard/balances/{contractor}/statement', [\App\Http\Controllers\Api\ContractorBalanceController::class, 'statement'])->middleware('perm:finance.balances|finance.dues,view');

            // أسعار الصرف (عرض + override يدوي)
            Route::get('dashboard/exchange-rates',  [\App\Http\Controllers\Api\ExchangeRateController::class, 'index'])->middleware('perm:finance.exchange_rates|finance.payments|finance.dues|finance.balances,view');
            Route::post('dashboard/exchange-rates', [\App\Http\Controllers\Api\ExchangeRateController::class, 'store'])->middleware('perm:finance.exchange_rates,update');

            // جدول رسوم الدرجات — قراءة فقط هنا (التعديل صلاحية أدمن، أدناه)
            Route::get('dashboard/grade-fees', [\App\Http\Controllers\Api\GradeFeeController::class, 'index'])->middleware('perm:finance.grade_fees|finance.dues,view');

            // محرّك احتساب رسوم العضوية (المادة 37) — معاينة/توليد فردي وجماعي
            Route::post('dashboard/contractors/{contractor}/dues/calculate-fee', [\App\Http\Controllers\Api\ContractorDueController::class, 'calculateFee'])->middleware('perm:finance.dues,view');
            Route::post('dashboard/contractors/{contractor}/dues/generate-fee',  [\App\Http\Controllers\Api\ContractorDueController::class, 'generateFee'])->middleware('perm:finance.dues,create');
            Route::post('dashboard/dues/generate-fee/bulk',                     [\App\Http\Controllers\Api\ContractorDueController::class, 'generateFeeBulk'])->middleware('perm:finance.dues,create');

            // خصم فردي على ذمة قائمة (المادة 37/ت)
            Route::post('dashboard/dues/{due}/discount', [\App\Http\Controllers\Api\ContractorDueController::class, 'applyDiscount'])->middleware('perm:finance.dues_discounts,update');
        });

        // صلاحية أدمن فقط — تعديل رسوم الدرجات والخصم الجماعي (المادة 37/ت تُطر هذه كصلاحية مجلس إدارة)
        Route::middleware('role:admin')->group(function () {
            Route::put('dashboard/grade-fees/{gradeFee}', [\App\Http\Controllers\Api\GradeFeeController::class, 'update'])->middleware('perm:finance.grade_fees');
            Route::post('dashboard/dues/discount/bulk',   [\App\Http\Controllers\Api\ContractorDueController::class, 'applyDiscountBulk'])->middleware('perm:finance.dues_discounts,update');
        });

        // --------------------------------------------------------
        //  Contractor Lookups — إدارة المجالات/الاختصاصات/الدرجات (REQ-01 #7، صلاحية أدمن فقط:
        //  هذه الأكواد تغذّي محرّك حساب الرسوم وتوليد الشهادات مباشرة)
        // --------------------------------------------------------
        Route::middleware('role:admin')->group(function () {
            Route::get('dashboard/contractor-fields',          [\App\Http\Controllers\Api\ContractorLookupController::class, 'fieldsIndex'])->middleware('perm:settings.lookups');
            Route::post('dashboard/contractor-fields',         [\App\Http\Controllers\Api\ContractorLookupController::class, 'fieldsStore'])->middleware('perm:settings.lookups');
            Route::patch('dashboard/contractor-fields/{contractorField}',  [\App\Http\Controllers\Api\ContractorLookupController::class, 'fieldsUpdate'])->middleware('perm:settings.lookups');
            Route::delete('dashboard/contractor-fields/{contractorField}', [\App\Http\Controllers\Api\ContractorLookupController::class, 'fieldsDestroy'])->middleware('perm:settings.lookups');

            Route::get('dashboard/contractor-specializations',          [\App\Http\Controllers\Api\ContractorLookupController::class, 'specializationsIndex'])->middleware('perm:settings.lookups');
            Route::post('dashboard/contractor-specializations',         [\App\Http\Controllers\Api\ContractorLookupController::class, 'specializationsStore'])->middleware('perm:settings.lookups');
            Route::patch('dashboard/contractor-specializations/{contractorSpecialization}',  [\App\Http\Controllers\Api\ContractorLookupController::class, 'specializationsUpdate'])->middleware('perm:settings.lookups');
            Route::delete('dashboard/contractor-specializations/{contractorSpecialization}', [\App\Http\Controllers\Api\ContractorLookupController::class, 'specializationsDestroy'])->middleware('perm:settings.lookups');

            Route::get('dashboard/contractor-grades',          [\App\Http\Controllers\Api\ContractorLookupController::class, 'gradesIndex'])->middleware('perm:settings.lookups');
            Route::post('dashboard/contractor-grades',         [\App\Http\Controllers\Api\ContractorLookupController::class, 'gradesStore'])->middleware('perm:settings.lookups');
            Route::patch('dashboard/contractor-grades/{contractorGrade}',  [\App\Http\Controllers\Api\ContractorLookupController::class, 'gradesUpdate'])->middleware('perm:settings.lookups');
            Route::delete('dashboard/contractor-grades/{contractorGrade}', [\App\Http\Controllers\Api\ContractorLookupController::class, 'gradesDestroy'])->middleware('perm:settings.lookups');
        });

        // --------------------------------------------------------
        //  Governorates & Cities — إدارة المحافظات والمدن (صلاحية أدمن فقط: هذه الأكواد
        //  تغذّي contractors.governorate_id/city_id وGET /api/v1/app/governorates العام)
        // --------------------------------------------------------
        Route::middleware('role:admin')->group(function () {
            Route::get('dashboard/governorates',          [\App\Http\Controllers\Api\GovernorateController::class, 'governoratesIndex'])->middleware('perm:settings.governorates');
            Route::post('dashboard/governorates',         [\App\Http\Controllers\Api\GovernorateController::class, 'governoratesStore'])->middleware('perm:settings.governorates');
            Route::patch('dashboard/governorates/{governorate}',  [\App\Http\Controllers\Api\GovernorateController::class, 'governoratesUpdate'])->middleware('perm:settings.governorates');
            Route::delete('dashboard/governorates/{governorate}', [\App\Http\Controllers\Api\GovernorateController::class, 'governoratesDestroy'])->middleware('perm:settings.governorates');

            Route::get('dashboard/cities',          [\App\Http\Controllers\Api\GovernorateController::class, 'citiesIndex'])->middleware('perm:settings.governorates');
            Route::post('dashboard/cities',         [\App\Http\Controllers\Api\GovernorateController::class, 'citiesStore'])->middleware('perm:settings.governorates');
            Route::patch('dashboard/cities/{city}',  [\App\Http\Controllers\Api\GovernorateController::class, 'citiesUpdate'])->middleware('perm:settings.governorates');
            Route::delete('dashboard/cities/{city}', [\App\Http\Controllers\Api\GovernorateController::class, 'citiesDestroy'])->middleware('perm:settings.governorates');
        });

        // --------------------------------------------------------
        //  Penalties — Admin (الغرامات المالية)
        // --------------------------------------------------------
        Route::middleware('role:admin,accountant')->group(function () {
            Route::get('penalties',                    [PenaltyController::class, 'index'])->middleware('perm:contractors.penalties|finance.dues,view');
            Route::post('penalties',                   [PenaltyController::class, 'store'])->middleware('perm:contractors.penalties');
            Route::get('penalties/{penalty}',          [PenaltyController::class, 'show'])->middleware('perm:contractors.penalties');
            Route::patch('penalties/{penalty}/status', [PenaltyController::class, 'updateStatus'])->middleware('perm:contractors.penalties');
            Route::delete('penalties/{penalty}',       [PenaltyController::class, 'destroy'])->middleware('perm:contractors.penalties');
        });

        // --------------------------------------------------------
        //  Tenders
        // --------------------------------------------------------
        // تصنيفات العطاءات + صورة افتراضية لكل تصنيف (بدل tenders/category-images السابقة)
        Route::get('tender-categories',                     [TenderCategoryController::class, 'index'])->middleware('perm:tenders.categories|tenders.list,view');
        Route::post('tender-categories',                    [TenderCategoryController::class, 'store'])->middleware('perm:tenders.categories');
        Route::patch('tender-categories/{tenderCategory}',  [TenderCategoryController::class, 'update'])->middleware('perm:tenders.categories');
        Route::delete('tender-categories/{tenderCategory}', [TenderCategoryController::class, 'destroy'])->middleware('perm:tenders.categories');
        Route::apiResource('tenders', TenderController::class)->middleware('perm:tenders.list');
        Route::post('tenders/{tender}/attachments', [TenderController::class, 'storeAttachment'])->middleware('perm:tenders.list,update');
        Route::delete('tenders/{tender}/attachments/{attachment}', [TenderController::class, 'destroyAttachment'])->middleware('perm:tenders.list,update');

        // --------------------------------------------------------
        //  Documents
        // --------------------------------------------------------
        Route::get('documents',               [DocumentController::class, 'index'])->middleware('perm:documents.manage');
        Route::get('documents/export-zip',    [DocumentController::class, 'exportZip'])->middleware('perm:documents.manage,view');
        Route::post('documents',              [DocumentController::class, 'store'])->middleware('perm:documents.manage');
        Route::delete('documents/{document}', [DocumentController::class, 'destroy'])->middleware('perm:documents.manage');

        // --------------------------------------------------------
        //  Users & Notifications
        // --------------------------------------------------------
        Route::get('notifications',                    [NotificationController::class, 'index']);
        Route::get('notifications/unread-count',       [NotificationController::class, 'unreadCount']);
        Route::post('notifications/read',              [NotificationController::class, 'markAllRead']);
        Route::patch('notifications/{id}/mark-as-read', [NotificationController::class, 'markAsRead']);

        // بث إشعارات مستهدَفة لكل المقاولين — أدمن فقط (REQ-22)
        Route::middleware('role:admin')->group(function () {
            Route::post('notifications/broadcast', [NotificationController::class, 'broadcast'])->middleware('perm:reports.broadcast,create');
        });

        // --------------------------------------------------------
        //  Equipment Marketplace — سوق الآليات
        // --------------------------------------------------------
        Route::get('equipment-types',                    [EquipmentTypeController::class, 'index'])->middleware('perm:marketplace.types|marketplace.equipment,view');
        Route::post('equipment-types',                   [EquipmentTypeController::class, 'store'])->middleware('perm:marketplace.types');
        Route::patch('equipment-types/{equipmentType}',  [EquipmentTypeController::class, 'update'])->middleware('perm:marketplace.types');
        Route::delete('equipment-types/{equipmentType}', [EquipmentTypeController::class, 'destroy'])->middleware('perm:marketplace.types');

        Route::get('equipment/stats',                    [EquipmentController::class, 'stats'])->middleware('perm:marketplace.equipment,view');
        Route::get('equipment',                          [EquipmentController::class, 'index'])->middleware('perm:marketplace.equipment');
        Route::post('equipment',                         [EquipmentController::class, 'store'])->middleware('perm:marketplace.equipment');
        Route::get('equipment/{equipment}',              [EquipmentController::class, 'show'])->middleware('perm:marketplace.equipment');
        Route::patch('equipment/{equipment}',            [EquipmentController::class, 'update'])->middleware('perm:marketplace.equipment');
        Route::delete('equipment/{equipment}',           [EquipmentController::class, 'destroy'])->middleware('perm:marketplace.equipment');

        Route::post('equipment/{equipment}/images',                          [EquipmentController::class, 'uploadImages'])->middleware('perm:marketplace.equipment,update');
        Route::delete('equipment/{equipment}/images/{equipmentImage}',       [EquipmentController::class, 'deleteImage'])->middleware('perm:marketplace.equipment,update');
        Route::post('equipment/{equipment}/images/{equipmentImage}/primary', [EquipmentController::class, 'setPrimaryImage'])->middleware('perm:marketplace.equipment,update');

        Route::get('equipment/{equipment}/blocked-dates',                  [EquipmentController::class, 'blockedDates'])->middleware('perm:marketplace.equipment,update');
        Route::post('equipment/{equipment}/blocked-dates',                 [EquipmentController::class, 'addBlockedDate'])->middleware('perm:marketplace.equipment,update');
        Route::delete('equipment/{equipment}/blocked-dates/{blockedDate}', [EquipmentController::class, 'removeBlockedDate'])->middleware('perm:marketplace.equipment,update');
        Route::get('equipment/{equipment}/reservations',                  [EquipmentController::class, 'reservations'])->middleware('perm:marketplace.equipment');

        // بلاغات "الإبلاغ عن مشكلة" بالسوق (اكتُشف بتصميم الموبايل)
        Route::get('equipment-reports',                [\App\Http\Controllers\Api\EquipmentReportController::class, 'index'])->middleware('perm:marketplace.reports');
        Route::patch('equipment-reports/{equipmentReport}', [\App\Http\Controllers\Api\EquipmentReportController::class, 'update'])->middleware('perm:marketplace.reports');

        // باقات اشتراك سوق الآليات (REQ-06)
        Route::get('equipment-packages',                    [\App\Http\Controllers\Api\EquipmentPackageController::class, 'index'])->middleware('perm:marketplace.packages');
        Route::post('equipment-packages',                   [\App\Http\Controllers\Api\EquipmentPackageController::class, 'store'])->middleware('perm:marketplace.packages');
        Route::patch('equipment-packages/{equipmentPackage}', [\App\Http\Controllers\Api\EquipmentPackageController::class, 'update'])->middleware('perm:marketplace.packages');
        Route::delete('equipment-packages/{equipmentPackage}', [\App\Http\Controllers\Api\EquipmentPackageController::class, 'destroy'])->middleware('perm:marketplace.packages');

        // حظر مقاول من سوق الآليات فقط (REQ-08)
        Route::patch('contractors/{contractor}/equipment-ban', [ContractorController::class, 'equipmentBan'])->middleware('perm:marketplace.equipment|contractors.list,update');

        // --------------------------------------------------------
        //  Reports / Analytics
        // --------------------------------------------------------
        Route::prefix('reports')->group(function () {
            Route::get('summary',        [ReportsController::class, 'summary'])->middleware('perm:reports.analytics,view');
            Route::get('export/pdf',     [ReportsController::class, 'exportPdf'])->middleware('perm:reports.analytics,view');
            Route::get('export/excel',   [ReportsController::class, 'exportExcel'])->middleware('perm:reports.analytics,view');
        });
    });
});





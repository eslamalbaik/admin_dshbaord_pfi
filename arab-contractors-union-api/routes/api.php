<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\ContractorController;
use App\Http\Controllers\Api\MembershipController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\PenaltyController;
use App\Http\Controllers\Api\TenderController;
use App\Http\Controllers\Api\DocumentController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\ContractorAuthController;
use App\Http\Controllers\Api\ContractorRegisterController;
use App\Http\Controllers\Api\ContractorDashboardController;
use App\Http\Controllers\Api\EquipmentTypeController;
use App\Http\Controllers\Api\EquipmentController;
use App\Http\Controllers\Api\ReportsController;
use App\Http\Controllers\Api\NewsController;
use App\Http\Controllers\Api\TermsController;
use App\Http\Controllers\Api\LegalFileController;
use App\Http\Controllers\Api\BankAccountController;
use App\Http\Controllers\Api\SupportTicketController;
use App\Http\Controllers\Api\SettingController;
use App\Http\Controllers\Api\CertificateRequestController;
use App\Http\Controllers\Api\ContractorNameChangeRequestController;
use App\Http\Controllers\Api\ContractorHomeController;

// ============================================================
//  API v1
// ============================================================
Route::prefix('v1')->group(function () {

    // --------------------------------------------------------
    //  Contractor Mobile App — Auth (Public)
    // --------------------------------------------------------
    Route::prefix('contractor/auth')->group(function () {
        // throttle: 5 محاولات كل دقيقة لمنع brute force
        Route::middleware('throttle:5,1')->post('login', [ContractorAuthController::class, 'login']);

        // تسجيل العضو (خطوتان): التحقق من الهوية ثم تعيين كلمة المرور
        Route::middleware('throttle:10,1')->group(function () {
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
        Route::get('me',               [ContractorAuthController::class, 'me']);
        Route::get('profile',          [ContractorAuthController::class, 'profile']);
        Route::patch('profile',        [ContractorAuthController::class, 'updateProfile']);
        Route::post('profile/update',  [ContractorAuthController::class, 'updateFullProfile']);
        Route::post('logo',            [ContractorAuthController::class, 'updateLogo']);
        Route::get('profile/pdf',      [ContractorAuthController::class, 'exportPdf']);
        Route::get('profile/download-file/{field}', [ContractorAuthController::class, 'downloadFile']);
        Route::post('change-password', [ContractorAuthController::class, 'changePassword']);

        // طلب تعديل اسم الشركة (يتطلب موافقة الإدارة + وثيقة رسمية)
        Route::get('name-change-request',  [ContractorNameChangeRequestController::class, 'show']);
        Route::post('name-change-request', [ContractorNameChangeRequestController::class, 'store']);

        // إشعارات المقاول (صندوق الوارد)
        Route::get('notifications',                    [NotificationController::class, 'index']);
        Route::post('notifications/read',              [NotificationController::class, 'markAllRead']);
        Route::patch('notifications/{id}/mark-as-read', [NotificationController::class, 'markAsRead']);
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

        // شاشة الدفع — رفع إشعار التحويل ومتابعته
        Route::post('payments/transfer', [PaymentController::class, 'submitTransfer']);
        Route::get('payments/transfer',  [PaymentController::class, 'myTransfers']);

        // شاشة الدعم الفني والشكاوى
        Route::get('support-tickets',           [SupportTicketController::class, 'myTickets']);
        Route::post('support-tickets',          [SupportTicketController::class, 'store']);
        Route::get('support-tickets/{ticket}',  [SupportTicketController::class, 'showMine']);
        Route::post('support-tickets/{ticket}/reply', [SupportTicketController::class, 'replyMine']);

        // شاشة طلب شهادة العضوية
        Route::get('certificate-requests',  [CertificateRequestController::class, 'index']);
        Route::post('certificate-requests', [CertificateRequestController::class, 'store']);

        // شاشة الملف المالي — كشف حساب "ما له وما عليه"
        Route::get('financial', [ContractorDashboardController::class, 'financial']);

        // أهلية تجديد العضوية (تُمنع مع ذمم غير مسدَّدة)
        Route::get('renewal-eligibility', [ContractorDashboardController::class, 'renewalEligibility']);
    });

    // --------------------------------------------------------
    //  Tenders — Public (no auth) — بدون union_notes وبيانات إدارية
    // --------------------------------------------------------
    Route::get('tenders-public',          [TenderController::class, 'publicIndex']);
    Route::get('tenders-public/{tender}', [TenderController::class, 'publicShow']);

    // --------------------------------------------------------
    //  News — Public (no auth)
    // --------------------------------------------------------
    Route::prefix('news')->group(function () {
        Route::get('latest',  [NewsController::class, 'latest']);
        Route::get('/',       [NewsController::class, 'index']);
        Route::get('{slug}',  [NewsController::class, 'show']);
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
    //  Legal Library — Public (no auth)
    // --------------------------------------------------------
    Route::get('legal-files', [LegalFileController::class, 'publicIndex']);

    // --------------------------------------------------------
    //  Bank Accounts (شاشة الدفع) — Public (no auth)
    // --------------------------------------------------------
    Route::get('bank-accounts', [BankAccountController::class, 'publicIndex']);

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
    Route::middleware('auth:sanctum')->group(function () {

        // بيانات المستخدم الحالي (authStore.fetchUser)
        Route::get('user', function (\Illuminate\Http\Request $request) {
            $user = $request->user();
            return response()->json([
                'id'       => $user->id,
                'fullName' => $user->name,
                'role'     => $user->role,
                'email'    => $user->email,
                'avatar'   => $user->avatar ?? null,
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
                ]);
            });
        });

        // --------------------------------------------------------
        //  Dashboard
        // --------------------------------------------------------
        Route::get('dashboard/stats', [DashboardController::class, 'index']);

        // --------------------------------------------------------
        //  News — Admin CRUD
        // --------------------------------------------------------
        Route::prefix('admin/news')->group(function () {
            Route::get('/',          [NewsController::class, 'adminIndex']);
            Route::post('/',         [NewsController::class, 'store']);
            Route::put('{news}',     [NewsController::class, 'update']);
            Route::delete('{news}',  [NewsController::class, 'destroy']);
        });

        // --------------------------------------------------------
        //  Announcements — Admin CRUD
        // --------------------------------------------------------
        Route::prefix('admin/announcements')->group(function () {
            Route::get('/',                  [\App\Http\Controllers\Api\AnnouncementController::class, 'adminIndex']);
            Route::post('/',                 [\App\Http\Controllers\Api\AnnouncementController::class, 'store']);
            Route::put('{announcement}',     [\App\Http\Controllers\Api\AnnouncementController::class, 'update']);
            Route::delete('{announcement}',  [\App\Http\Controllers\Api\AnnouncementController::class, 'destroy']);
        });

        // --------------------------------------------------------
        //  Terms & Conditions — Admin CRUD
        // --------------------------------------------------------
        Route::get('dashboard/terms',          [TermsController::class, 'index']);
        Route::post('dashboard/terms',         [TermsController::class, 'store']);
        Route::put('dashboard/terms/{term}',   [TermsController::class, 'update']);
        Route::delete('dashboard/terms/{term}',[TermsController::class, 'destroy']);

        // --------------------------------------------------------
        //  Legal Library — Admin CRUD
        // --------------------------------------------------------
        Route::get('dashboard/legal-files',                  [LegalFileController::class, 'index']);
        Route::post('dashboard/legal-files',                 [LegalFileController::class, 'store']);
        Route::post('dashboard/legal-files/{legalFile}',     [LegalFileController::class, 'update']);
        Route::delete('dashboard/legal-files/{legalFile}',   [LegalFileController::class, 'destroy']);

        // --------------------------------------------------------
        //  Contractors
        // --------------------------------------------------------
        Route::get('contractors/next-membership-number', [ContractorController::class, 'nextMembershipNumber']);
        Route::apiResource('contractors', ContractorController::class)
             ->only(['index', 'store', 'show', 'update', 'destroy']);
        Route::post('contractors/{contractor}/qr', [ContractorController::class, 'generateQR']);
        Route::patch('contractors/{contractor}/status', [ContractorController::class, 'changeStatus']);

        // --------------------------------------------------------
        //  Memberships
        // --------------------------------------------------------
        Route::get('memberships/pending',               [MembershipController::class, 'pending']);
        Route::get('memberships',                       [MembershipController::class, 'index']);
        Route::post('memberships',                      [MembershipController::class, 'store']);
        Route::post('memberships/{membership}/approve', [MembershipController::class, 'approve']);
        Route::post('memberships/{membership}/reject',  [MembershipController::class, 'reject']);

        // --------------------------------------------------------
        //  Payments
        // --------------------------------------------------------
        Route::get('payments/transactions',  [PaymentController::class, 'index']);
        Route::post('payments/transactions', [PaymentController::class, 'store']);
        Route::post('payments/transactions/{payment}/confirm', [PaymentController::class, 'confirm']);
        Route::post('payments/transactions/{payment}/reject',  [PaymentController::class, 'reject']);

        // --------------------------------------------------------
        //  Bank Accounts — Admin CRUD (شاشة الدفع)
        // --------------------------------------------------------
        Route::get('dashboard/bank-accounts',                [BankAccountController::class, 'index']);
        Route::post('dashboard/bank-accounts',               [BankAccountController::class, 'store']);
        Route::post('dashboard/bank-accounts/{bankAccount}', [BankAccountController::class, 'update']);
        Route::delete('dashboard/bank-accounts/{bankAccount}',[BankAccountController::class, 'destroy']);

        // --------------------------------------------------------
        //  Support Tickets — Admin (الدعم الفني والشكاوى)
        // --------------------------------------------------------
        Route::get('dashboard/support-tickets',                   [SupportTicketController::class, 'index']);
        Route::get('dashboard/support-tickets/{ticket}',          [SupportTicketController::class, 'show']);
        Route::post('dashboard/support-tickets/{ticket}/reply',   [SupportTicketController::class, 'reply']);
        Route::patch('dashboard/support-tickets/{ticket}/status', [SupportTicketController::class, 'updateStatus']);
        Route::delete('dashboard/support-tickets/{ticket}',       [SupportTicketController::class, 'destroy']);

        // --------------------------------------------------------
        //  App Settings — Admin (واتساب/بريد الدعم، بيانات الاتحاد، السوشال ميديا)
        // --------------------------------------------------------
        Route::get('dashboard/settings',       [SettingController::class, 'index']);
        Route::put('dashboard/settings',       [SettingController::class, 'update']);
        Route::post('dashboard/settings/logo', [SettingController::class, 'uploadLogo']);

        // --------------------------------------------------------
        //  Dynamic Pages — Admin CRUD
        // --------------------------------------------------------
        Route::get('dashboard/pages',           [\App\Http\Controllers\Api\PageController::class, 'index']);
        Route::post('dashboard/pages',          [\App\Http\Controllers\Api\PageController::class, 'store']);
        Route::put('dashboard/pages/{page}',    [\App\Http\Controllers\Api\PageController::class, 'update']);
        Route::delete('dashboard/pages/{page}', [\App\Http\Controllers\Api\PageController::class, 'destroy']);

        // --------------------------------------------------------
        //  Certificate Requests — Admin (طلبات شهادات العضوية)
        // --------------------------------------------------------
        Route::get('dashboard/certificate-requests',                                [CertificateRequestController::class, 'adminIndex']);
        Route::get('dashboard/certificate-requests/{certificateRequest}',           [CertificateRequestController::class, 'show']);
        Route::post('dashboard/certificate-requests/{certificateRequest}/approve',  [CertificateRequestController::class, 'approve']);
        Route::post('dashboard/certificate-requests/{certificateRequest}/reject',   [CertificateRequestController::class, 'reject']);
        Route::post('dashboard/certificate-requests/{certificateRequest}/issue',    [CertificateRequestController::class, 'issue']);
        Route::delete('dashboard/certificate-requests/{certificateRequest}',        [CertificateRequestController::class, 'destroy']);

        // --------------------------------------------------------
        //  طلبات تعديل اسم الشركة — Admin
        // --------------------------------------------------------
        Route::get('dashboard/name-change-requests',                                    [ContractorNameChangeRequestController::class, 'index']);
        Route::post('dashboard/name-change-requests/{nameChangeRequest}/approve',        [ContractorNameChangeRequestController::class, 'approve']);
        Route::post('dashboard/name-change-requests/{nameChangeRequest}/reject',         [ContractorNameChangeRequestController::class, 'reject']);

        // --------------------------------------------------------
        //  Contractor Dues — الذمم المالية (أدمن + محاسب)
        // --------------------------------------------------------
        Route::middleware('role:admin,accountant')->group(function () {
            Route::get('dashboard/dues',                [\App\Http\Controllers\Api\ContractorDueController::class, 'index']);
            Route::get('dashboard/dues/summary',        [\App\Http\Controllers\Api\ContractorDueController::class, 'summary']);
            Route::get('dashboard/dues/by-contractor',  [\App\Http\Controllers\Api\ContractorDueController::class, 'byContractor']);
            Route::post('dashboard/dues/import',        [\App\Http\Controllers\Api\ContractorDueController::class, 'import']);
            Route::post('dashboard/contractors/{contractor}/dues/pay', [\App\Http\Controllers\Api\ContractorDueController::class, 'payForContractor']);
            Route::post('dashboard/dues',               [\App\Http\Controllers\Api\ContractorDueController::class, 'store']);
            Route::patch('dashboard/dues/{due}',        [\App\Http\Controllers\Api\ContractorDueController::class, 'update']);
            Route::post('dashboard/dues/{due}/settle',  [\App\Http\Controllers\Api\ContractorDueController::class, 'settle']);
            Route::delete('dashboard/dues/{due}',       [\App\Http\Controllers\Api\ContractorDueController::class, 'destroy']);

            // أسعار الصرف (عرض + override يدوي)
            Route::get('dashboard/exchange-rates',  [\App\Http\Controllers\Api\ExchangeRateController::class, 'index']);
            Route::post('dashboard/exchange-rates', [\App\Http\Controllers\Api\ExchangeRateController::class, 'store']);
        });

        // --------------------------------------------------------
        //  Penalties
        // --------------------------------------------------------
        Route::get('penalties',                 [PenaltyController::class, 'index']);
        Route::post('penalties',                [PenaltyController::class, 'store']);
        Route::patch('penalties/{penalty}/pay', [PenaltyController::class, 'markPaid']);

        // --------------------------------------------------------
        //  Tenders
        // --------------------------------------------------------
        Route::apiResource('tenders', TenderController::class);

        // --------------------------------------------------------
        //  Documents
        // --------------------------------------------------------
        Route::get('documents',               [DocumentController::class, 'index']);
        Route::post('documents',              [DocumentController::class, 'store']);
        Route::delete('documents/{document}', [DocumentController::class, 'destroy']);

        // --------------------------------------------------------
        //  Users & Notifications
        // --------------------------------------------------------
        Route::get('users',               [UserController::class,         'index']);
        Route::get('notifications',                    [NotificationController::class, 'index']);
        Route::post('notifications/read',              [NotificationController::class, 'markAllRead']);
        Route::patch('notifications/{id}/mark-as-read', [NotificationController::class, 'markAsRead']);

        // --------------------------------------------------------
        //  Equipment Marketplace — سوق الآليات
        // --------------------------------------------------------
        Route::get('equipment-types',                    [EquipmentTypeController::class, 'index']);
        Route::post('equipment-types',                   [EquipmentTypeController::class, 'store']);
        Route::patch('equipment-types/{equipmentType}',  [EquipmentTypeController::class, 'update']);
        Route::delete('equipment-types/{equipmentType}', [EquipmentTypeController::class, 'destroy']);

        Route::get('equipment/stats',                    [EquipmentController::class, 'stats']);
        Route::get('equipment',                          [EquipmentController::class, 'index']);
        Route::post('equipment',                         [EquipmentController::class, 'store']);
        Route::get('equipment/{equipment}',              [EquipmentController::class, 'show']);
        Route::patch('equipment/{equipment}',            [EquipmentController::class, 'update']);
        Route::delete('equipment/{equipment}',           [EquipmentController::class, 'destroy']);

        Route::post('equipment/{equipment}/images',                          [EquipmentController::class, 'uploadImages']);
        Route::delete('equipment/{equipment}/images/{equipmentImage}',       [EquipmentController::class, 'deleteImage']);
        Route::post('equipment/{equipment}/images/{equipmentImage}/primary', [EquipmentController::class, 'setPrimaryImage']);

        Route::get('equipment/{equipment}/blocked-dates',                  [EquipmentController::class, 'blockedDates']);
        Route::post('equipment/{equipment}/blocked-dates',                 [EquipmentController::class, 'addBlockedDate']);
        Route::delete('equipment/{equipment}/blocked-dates/{blockedDate}', [EquipmentController::class, 'removeBlockedDate']);

        // --------------------------------------------------------
        //  Reports / Analytics
        // --------------------------------------------------------
        Route::prefix('reports')->group(function () {
            Route::get('summary',        [ReportsController::class, 'summary']);
            Route::get('export/pdf',     [ReportsController::class, 'exportPdf']);
            Route::get('export/excel',   [ReportsController::class, 'exportExcel']);
        });
    });
});

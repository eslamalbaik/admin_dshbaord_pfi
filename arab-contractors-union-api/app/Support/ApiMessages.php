<?php

namespace App\Support;

/**
 * الرسائل المتفق عليها مع الفريق — مصدر واحد لكل الواجهات (تعميم REQ-08).
 * أي تعديل على نص رسالة يتم هنا فقط وينعكس على كل الـ APIs.
 */
class ApiMessages
{
    // Auth — الرسائل الدقيقة المتفق عليها في الاجتماع
    public const NO_MEMBERSHIP       = 'لا توجد لديك عضوية في الاتحاد';
    public const INVALID_CREDENTIALS = 'بيانات الاعتماد غير صحيحة';
    public const UNAUTHENTICATED     = 'غير مصرح بالوصول. يرجى تسجيل الدخول';
    public const ACCOUNT_FROZEN      = 'تم تجميد حسابك. تواصل مع الاتحاد للمزيد من المعلومات';
    public const ACCOUNT_SUSPENDED   = 'تم تعليق حسابك. تواصل مع الاتحاد للمزيد من المعلومات';
    public const PHONE_NOT_VERIFIED  = 'لم يتم تفعيل رقم جوالك بعد. أكمل خطوة التحقق من شاشة التسجيل أولاً';
    public const TOO_MANY_ATTEMPTS   = 'عدد محاولات كبير. حاول مرة أخرى بعد قليل';
    public const NOT_FOUND           = 'العنصر المطلوب غير موجود';
    public const SERVER_ERROR        = 'حدث خطأ غير متوقع. حاول مرة أخرى لاحقاً';
    public const VALIDATION_ERROR    = 'خطأ في البيانات المُدخلة';
}

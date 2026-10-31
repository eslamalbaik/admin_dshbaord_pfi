<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Models\CertificateRequest;
use App\Services\CertificateSerialService;
use Illuminate\Http\Request;

/**
 * التحقق العام من شهادة عضوية بالرقم التسلسلي (بدون تسجيل دخول).
 *
 * - يقرأ من قاعدة البيانات مباشرة في كل طلب (بدون كاش) والرد بـ no-store.
 * - لا يُرجع إلا الحقول العامة المطلوبة: اسم المقاول، رقم العضوية، الرقم التسلسلي، النوع،
 *   تاريخ الإصدار والانتهاء، والحالة. لا هاتف/هوية/عنوان/ملفات/أرصدة ولا بيانات المشرف.
 * - الحالات: valid | expired | revoked | not_found. المحدود بـ throttle لمنع تعداد الأرقام.
 */
class CertificateVerificationController extends Controller
{
    use ApiResponseTrait;

    private const LABELS = [
        'valid'     => 'شهادة سارية',
        'expired'   => 'شهادة منتهية',
        'revoked'   => 'شهادة ملغاة',
        'not_found' => 'شهادة غير موجودة',
    ];

    /** GET /api/v1/certificates/check?serial=MC-XXXXX-XXXXX-CC */
    public function check(Request $request)
    {
        $request->validate(['serial' => 'required|string|max:64']);

        // الرقم المنسوخ من PDF بسطر عربي ممكن ينقلب ترتيبه — بنجرّب الصيغ المحتملة (خانة الفحص بتصفّيها)
        // والقاعدة هي الحكم: اللي مش مسجّل فيها "غير موجودة".
        $candidates = CertificateSerialService::candidates($request->query('serial'));

        $cert = $candidates
            ? CertificateRequest::query()
                ->with('contractor:id,name,membership_number')
                ->whereIn('serial_number', $candidates)
                ->where('status', 'issued')
                ->first()
            : null;

        return $this->payload($cert)
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache');
    }

    private function payload(?CertificateRequest $cert)
    {
        if (! $cert) {
            return $this->success([
                'state'       => 'not_found',
                'state_label' => self::LABELS['not_found'],
                'found'       => false,
            ]);
        }

        $state = $cert->verificationState();

        return $this->success([
            'state'             => $state,
            'state_label'       => self::LABELS[$state],
            'found'             => true,
            'contractor_name'   => $cert->contractor?->name,
            'membership_number' => $cert->contractor?->membership_number,
            'serial_number'     => $cert->serial_number,
            'type'              => $cert->type,
            'type_label'        => $cert->type_label,
            'issued_at'         => $cert->issued_at?->setTimezone(config('app.local_timezone', 'Asia/Gaza'))->toDateString(),
            'valid_until'       => $cert->valid_until?->toDateString(),
            'revoked_at'        => $cert->revoked_at?->setTimezone(config('app.local_timezone', 'Asia/Gaza'))->toDateString(),
        ]);
    }
}

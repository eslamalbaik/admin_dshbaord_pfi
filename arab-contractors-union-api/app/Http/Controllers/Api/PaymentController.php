<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Models\Payment;
use App\Models\User;
use App\Notifications\PaymentConfirmedNotification;
use App\Notifications\PaymentRejectedNotification;
use App\Notifications\PaymentSubmittedNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;

class PaymentController extends Controller
{
    use ApiResponseTrait;

    /** تنسيق معاملة دفع لاستجابة الـ API */
    private function format(Payment $p): array
    {
        return [
            'id'                => $p->id,
            'transaction_number' => $p->transaction_number,
            'contractor'        => $p->contractor?->name,
            'contractor_id'     => $p->contractor_id,
            'membership_id'     => $p->membership_id,
            'equipment_package_id' => $p->equipment_package_id,
            'bank_account_id'   => $p->bank_account_id,
            'amount'            => $p->amount,
            'currency'          => $p->currency ?? 'JOD',
            'exchange_rate'     => $p->exchange_rate,
            'amount_jod'        => $p->amount_jod,
            'rate_source'       => $p->rate_source,
            'type'              => $p->type,
            'status'            => $p->status,
            'status_label'      => $p->status_label,
            'method'            => $p->method,
            'reference_number'  => $p->reference_number,
            'receipt_image_url' => $p->receipt_image_url,
            'receipt_pdf_url'   => $p->receipt_pdf_url,
            'rejection_reason'  => $p->rejection_reason,
            'notes'             => $p->notes,
            'submitted_at'      => $p->submitted_at,
            'confirmed_at'      => $p->confirmed_at,
            'paid_at'           => $p->paid_at,
            'created_at'        => $p->created_at,
        ];
    }

    // ═════════════════════════════════════════════════════════════════════════
    //  Contractor Mobile App — شاشة الدفع
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * POST /api/v1/contractor/payments/transfer
     * يرفع المقاول إشعار التحويل (صورة إشعار الدفع + المبلغ) فتُسجَّل معاملة
     * بحالة "pending" بانتظار تأكيد موظف المحاسبة.
     */
    public function submitTransfer(Request $request)
    {
        $contractor = $request->user();

        $data = $request->validate([
            'amount'           => 'required|numeric|min:1',
            'currency'         => 'nullable|in:JOD,ILS,USD',
            'receipt_image'    => 'required|file|mimes:png,jpg,jpeg,webp,pdf|max:5120',
            'bank_account_id'  => 'nullable|exists:bank_accounts,id',
            'membership_id'    => 'nullable|exists:memberships,id',
            'equipment_package_id' => 'nullable|exists:equipment_packages,id',
            'reference_number' => 'nullable|string|max:100',
            'notes'            => 'nullable|string|max:500',
            'type'             => 'nullable|string|max:50',
        ]);

        // منع أي عمليات باستثناء تسديد الذمم إذا كانت هناك ذمم/غرامات غير مسدَّدة
        $type = $data['type'] ?? 'membership_fee';
        if ($type !== 'dues_payment') {
            $blockers = \App\Support\ContractorRequirements::renewalBlockers($contractor);
            if (count($blockers) > 0) {
                return $this->error(
                    'لا يمكن إتمام أي عمليات قبل تسوية الذمم المالية المستحقّة.',
                    403,
                    ['issues' => $blockers],
                    'dues_pending',
                );
            }
        }

        $path = $request->file('receipt_image')->store('payment-receipts', 'public');

        $payment = Payment::create([
            'contractor_id'    => $contractor->id,
            'membership_id'    => $data['membership_id'] ?? null,
            'equipment_package_id' => $data['equipment_package_id'] ?? null,
            'bank_account_id'  => $data['bank_account_id'] ?? null,
            'amount'           => $data['amount'],
            'currency'         => $data['currency'] ?? 'JOD',
            'type'             => $type,
            'status'           => 'pending',
            'method'           => 'bank_transfer',
            'reference_number' => $data['reference_number'] ?? null,
            'receipt_image'    => $path,
            'notes'            => $data['notes'] ?? null,
            'submitted_at'     => now(),
        ]);
        $payment->update(['transaction_number' => Payment::generateTransactionNumber($payment)]);

        // إشعار موظفي المحاسبة والإدارة بوجود إشعار تحويل بانتظار المراجعة
        $reviewers = User::whereIn('role', ['accountant', 'admin'])->get();
        if ($reviewers->isNotEmpty()) {
            Notification::send($reviewers, new PaymentSubmittedNotification($payment));
        }

        // نص شاشة "تم الإرسال" (عنوان ثابت بالتطبيق + هالنص من الـ response) — REQ: نص الرسالة يرجع بالـ API
        return $this->success(
            $this->format($payment->load('contractor')),
            'سيقوم المحاسب بتقديم الاعتماد وتحديث حالة حسابك فور التحقق من الحوالة.',
            201,
        );
    }

    /**
     * GET /api/v1/contractor/payments/{payment}/receipt
     * تحميل إيصال القبض PDF — يُولَّد عند أول طلب إن لم يكن موجوداً (مثلاً لدفعات قديمة
     * أُكِّدت قبل إضافة هذه الميزة).
     */
    public function receipt(Request $request, Payment $payment)
    {
        if ($payment->contractor_id !== $request->user()->id) {
            return $this->error('غير مصرَّح لك بالوصول لهذا الإيصال.', 403);
        }

        if ($payment->status !== 'paid') {
            return $this->error('الإيصال متاح فقط للدفعات المؤكَّدة.', 404);
        }

        if (! $payment->receipt_pdf_path || ! \Illuminate\Support\Facades\Storage::disk('public')->exists($payment->receipt_pdf_path)) {
            $path = app(\App\Services\ReceiptPdfService::class)->generate($payment->load('contractor'));
            $payment->update(['receipt_pdf_path' => $path]);
        }

        return \Illuminate\Support\Facades\Storage::disk('public')->download(
            $payment->receipt_pdf_path,
            "receipt-{$payment->id}.pdf",
        );
    }

    /**
     * GET /api/v1/contractor/payments/transfer
     * قائمة تحويلات المقاول الحالي.
     * ?status=paid&type=dues_payment — تبويب "مكتمل" بشاشة الذمم المالية (تحويلات ذمم مؤكَّدة فقط).
     */
    public function myTransfers(Request $request)
    {
        $query = $request->user()->payments()->with('contractor:id,name');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        $paginator = $query->latest()
            ->paginate($request->integer('per_page', 15))
            ->through(fn ($p) => $this->format($p));

        return $this->paginated($paginator);
    }

    /**
     * GET /api/v1/contractor/payments/{payment}
     * شاشة "معاينة اشعار التحويل" — تفاصيل تحويل واحد (المرجع، المبلغ، صورة الإشعار، الحالة).
     */
    public function show(Request $request, Payment $payment)
    {
        if ($payment->contractor_id !== $request->user()->id) {
            return $this->error('غير مصرَّح لك بالوصول لهذا الإشعار.', 403);
        }

        return $this->success($this->format($payment->load('contractor:id,name')));
    }

    // ═════════════════════════════════════════════════════════════════════════
    //  Admin / Accountant Dashboard
    // ═════════════════════════════════════════════════════════════════════════

    // GET /api/v1/payments/transactions
    public function index(Request $request)
    {
        $query = Payment::with('contractor');

        if ($request->filled('search')) {
            $q = $request->search;
            $query->whereHas('contractor', fn ($qb) => $qb->where('name', 'like', "%{$q}%"));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('method')) {
            $query->where('method', $request->method);
        }

        $paginator = $query->latest()->paginate(15)->through(fn ($p) => $this->format($p));

        return $this->paginated($paginator);
    }

    // POST /api/v1/payments/transactions
    public function store(Request $request)
    {
        $validated = $request->validate([
            'contractor_id'    => 'required|exists:contractors,id',
            'membership_id'    => 'nullable|exists:memberships,id',
            'amount'           => 'required|numeric|min:0',
            'currency'         => 'nullable|in:JOD,ILS,USD',
            'exchange_rate'    => 'nullable|numeric|min:0.0001|max:1000',
            'type'             => 'nullable|string',
            'status'           => 'nullable|in:pending,paid,refunded,failed,rejected',
            'method'           => 'nullable|string',
            'reference_number' => 'nullable|string',
            'notes'            => 'nullable|string',
        ]);

        $currency = strtoupper($validated['currency'] ?? 'JOD');
        $service  = app(\App\Services\ExchangeRateService::class);

        $rate       = null;
        $rateSource = null;

        if ($currency !== 'JOD') {
            if (isset($validated['exchange_rate'])) {
                $rate       = (float) $validated['exchange_rate'];
                $rateSource = 'manual';
            } else {
                $latest = $service->latest($currency);
                if (! $latest) {
                    return $this->error("لا يوجد سعر صرف معتمد لعملة {$currency} — أدخل السعر يدوياً.", 422);
                }
                $rate       = (float) $latest->rate_to_jod;
                $rateSource = $latest->source;
            }
        }

        $amountJod = $service->convertToJod((float) $validated['amount'], $currency, $rate ?? 1.0);

        $paymentData = array_merge($validated, [
            'currency'      => $currency,
            'exchange_rate' => $rate,
            'amount_jod'    => $amountJod,
            'rate_source'   => $currency === 'JOD' ? null : $rateSource,
        ]);

        if (isset($paymentData['status']) && $paymentData['status'] === 'paid') {
            $paymentData['paid_at']      = now();
            $paymentData['confirmed_at'] = now();
            $paymentData['confirmed_by'] = Auth::id();
        }

        $payment = Payment::create($paymentData);

        return $this->success($payment->toArray(), 'تم تسجيل المعاملة بنجاح.', 201);
    }

    /**
     * POST /api/v1/payments/transactions/{payment}/confirm
     * يؤكّد موظف المحاسبة استلام التحويل → تصبح الحالة "paid".
     */
    public function confirm(Request $request, Payment $payment)
    {
        if ($payment->status === 'paid') {
            return $this->error('تم تأكيد هذه المعاملة مسبقًا.', 409);
        }

        $data = $request->validate([
            // للمحاسب: تجاوز سعر الصرف المقترح يدوياً عند التأكيد
            'exchange_rate' => 'nullable|numeric|min:0.0001|max:1000',
        ]);

        // تثبيت سعر الصرف والمعادل بالدينار لحظة التأكيد
        $currency   = strtoupper($payment->currency ?? 'JOD');
        $service    = app(\App\Services\ExchangeRateService::class);
        $rate       = null;
        $rateSource = null;

        if ($currency !== 'JOD') {
            if (isset($data['exchange_rate'])) {
                $rate       = (float) $data['exchange_rate'];
                $rateSource = 'manual';
            } else {
                $latest = $service->latest($currency);
                if (! $latest) {
                    return $this->error(
                        "لا يوجد سعر صرف معتمد لعملة {$currency} — أدخل السعر يدوياً أو شغّل rates:fetch.",
                        422,
                    );
                }
                $rate       = (float) $latest->rate_to_jod;
                $rateSource = $latest->source;
            }
        }

        // كل الآثار الجانبية (تحديث الدفعة، تجديد العضوية، تفعيل الاشتراك، الإيصال، سجل التدقيق)
        // بمعاملة واحدة — فشل أي خطوة يرجّع الدفعة لحالتها الأصلية بدل ما تضل "مدفوعة" للأبد
        // بلا تجديد/إيصال وبلا أي طريقة لإعادة المحاولة (كانت confirm() ترفض أي محاولة ثانية
        // بمجرد status=paid حتى لو الآثار الجانبية فشلت فعلياً).
        \Illuminate\Support\Facades\DB::transaction(function () use ($request, $payment, $currency, $rate, $rateSource, $service) {
            $payment->update([
                'status'           => 'paid',
                'exchange_rate'    => $rate,
                'amount_jod'       => $service->convertToJod((float) $payment->amount, $currency, $rate ?? 1.0),
                'rate_source'      => $currency === 'JOD' ? null : $rateSource,
                'confirmed_by'     => Auth::id(),
                'confirmed_at'     => now(),
                'paid_at'          => now(),
                'rejection_reason' => null,
            ]);

            // دفعة رسوم عضوية مؤكَّدة تُجدِّد/تُنشئ سجل العضوية تلقائياً بنفس منطق "الموعد الثابت"
            // المستخدَم بموافقة الأدمن اليدوية (MembershipController::approve).
            app(\App\Services\MembershipRenewalService::class)->renewFromPayment($payment, Auth::id());

            // دفعة اشتراك سوق الآليات مؤكَّدة → تفعيل الاشتراك تلقائياً (REQ-06)
            if ($payment->type === 'equipment_subscription' && $payment->equipment_package_id) {
                $package = \App\Models\EquipmentPackage::find($payment->equipment_package_id);
                if ($package) {
                    \App\Models\ContractorEquipmentSubscription::create([
                        'contractor_id'         => $payment->contractor_id,
                        'equipment_package_id'  => $package->id,
                        'payment_id'            => $payment->id,
                        'starts_at'             => now(),
                        'expires_at'            => now()->addDays($package->duration_days),
                    ]);
                }
            }

            // إيصال قبض PDF ثابت — يُولَّد مرة واحدة لحظة التأكيد (REQ-19)
            $receiptPath = app(\App\Services\ReceiptPdfService::class)->generate($payment->fresh('contractor'));
            $payment->update(['receipt_pdf_path' => $receiptPath]);

            \App\Services\AuditLogService::record(
                $request->user(),
                'payment.confirmed',
                $payment,
                ['amount' => $payment->amount, 'currency' => $currency, 'amount_jod' => $payment->amount_jod],
            );
        });

        \Illuminate\Support\Facades\Log::channel('finance')->info('payment.confirmed', [
            'user_id'       => Auth::id(),
            'payment_id'    => $payment->id,
            'contractor_id' => $payment->contractor_id,
            'amount'        => $payment->amount,
            'currency'      => $currency,
            'exchange_rate' => $rate,
            'amount_jod'    => $payment->amount_jod,
        ]);

        // إشعار المقاول بتأكيد الدفع — بعد نجاح الـcommit فقط، Notification مُصنَّفة ShouldQueue أصلاً
        if ($payment->contractor) {
            $payment->contractor->notify(new PaymentConfirmedNotification($payment));
        }

        return $this->success($this->format($payment->fresh('contractor')), 'تم تأكيد عملية الدفع بنجاح.');
    }

    /**
     * POST /api/v1/payments/transactions/{payment}/reject
     * يرفض موظف المحاسبة إشعار التحويل مع ذكر السبب.
     */
    public function reject(Request $request, Payment $payment)
    {
        $data = $request->validate([
            'rejection_reason' => 'required|string|max:500',
        ]);

        $payment->update([
            'status'           => 'rejected',
            'confirmed_by'     => Auth::id(),
            'confirmed_at'     => now(),
            'rejection_reason' => $data['rejection_reason'],
        ]);

        \App\Services\AuditLogService::record(
            Auth::user(),
            'payment.rejected',
            $payment,
            ['reason' => $data['rejection_reason']],
        );

        if ($payment->contractor) {
            $payment->contractor->notify(new PaymentRejectedNotification($payment));
        }

        return $this->success($this->format($payment->fresh('contractor')), 'تم رفض إشعار التحويل.');
    }
}

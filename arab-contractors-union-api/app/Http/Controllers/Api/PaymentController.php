<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Models\Payment;
use App\Models\User;
use App\Notifications\PaymentConfirmedNotification;
use App\Notifications\PaymentRejectedNotification;
use App\Notifications\PaymentSubmittedNotification;
use App\Http\Requests\Payment\SubmitTransferRequest;
use App\Http\Requests\Payment\StorePaymentRequest;
use App\Http\Requests\Payment\ConfirmPaymentRequest;
use App\Http\Requests\Payment\UploadReceiptImageRequest;
use App\Http\Requests\Payment\RejectPaymentRequest;
use App\Http\Resources\PaymentResource;
use App\Services\PaymentConfirmationService;
use App\Services\ExchangeRateService;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use App\Services\ReceiptPdfService;

class PaymentController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private PaymentConfirmationService $confirmationService,
        private ExchangeRateService $exchangeRateService
    ) {}

    // ═════════════════════════════════════════════════════════════════════════
    //  Contractor Mobile App — شاشة الدفع
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * POST /api/v1/contractor/payments/transfer
     */
    public function submitTransfer(SubmitTransferRequest $request)
    {
        $contractor = $request->user();
        $data = $request->validated();

        $type = $data['type'] ?? 'membership_fee';
        if ($type !== 'dues_payment') {
            $blockers = \App\Support\ContractorRequirements::renewalBlockers($contractor);
            if (count($blockers) > 0) {
                // الرسالة عامة عمداً: blockers ممكن تكون ذمم/غرامات أو حساب موقوف
                // إدارياً (account_suspended) — التفاصيل الدقيقة في issues لكل الواجهة.
                return $this->error(
                    'لا يمكن إتمام العملية قبل تسوية المتطلبات المستحقّة.',
                    403,
                    ['issues' => $blockers],
                    'dues_pending',
                );
            }
        }

        $path = $request->file('receipt_image')->store('payment-receipts', 'public');

        $payment = Payment::create([
            'contractor_id'        => $contractor->id,
            'membership_id'        => $data['membership_id'] ?? null,
            'equipment_package_id' => $data['equipment_package_id'] ?? null,
            'bank_account_id'      => $data['bank_account_id'] ?? null,
            'amount'               => $data['amount'],
            'currency'             => $data['currency'] ?? 'JOD',
            'type'                 => $type,
            'status'               => 'pending',
            'method'               => 'bank_transfer',
            'reference_number'     => $data['reference_number'] ?? null,
            'receipt_image'        => $path,
            'notes'                => $data['notes'] ?? null,
            'submitted_at'         => now(),
        ]);
        $payment->update(['transaction_number' => Payment::generateTransactionNumber($payment)]);

        $reviewers = User::whereIn('role', ['accountant', 'admin'])->get();
        if ($reviewers->isNotEmpty()) {
            Notification::send($reviewers, new PaymentSubmittedNotification($payment));
        }

        return $this->success(
            new PaymentResource($payment->load('contractor')),
            'سيقوم المحاسب بتقديم الاعتماد وتحديث حالة حسابك فور التحقق من الحوالة.',
            201,
        );
    }

    /**
     * GET /api/v1/contractor/payments/{payment}/receipt
     */
    public function receipt(Request $request, Payment $payment)
    {
        if ($payment->contractor_id !== $request->user()->id) {
            return $this->error('غير مصرَّح لك بالوصول لهذا الإيصال.', 403);
        }

        if ($payment->status !== 'paid') {
            return $this->error('الإيصال متاح فقط للدفعات المؤكَّدة.', 404);
        }

        // بما أن توليد الـ PDF أصبح يتم في الخلفية (Background Job)،
        // نتحقق مما إذا كانت المهمة قد اكتملت بالفعل أم لا.
        if (! $payment->receipt_pdf_path) {
            return $this->error('الإيصال قيد الإصدار حالياً، يرجى المحاولة بعد قليل.', 409);
        }

        $signedUrl = \Illuminate\Support\Facades\URL::temporarySignedRoute(
            'contractor.payment-receipt.signed',
            now()->addMinutes(15),
            ['payment' => $payment->id]
        );

        return $this->success(['signed_url' => $signedUrl], 'تم توليد رابط تحميل الإيصال بأمان.');
    }

    /**
     * GET /api/v1/downloads/payments/{payment}/receipt (SIGNED)
     */
    public function receiptSigned(Request $request, Payment $payment)
    {
        if (! $request->hasValidSignature()) {
            abort(401, 'الرابط غير صالح أو انتهت صلاحيته.');
        }

        if ($payment->status !== 'paid') {
            abort(404, 'الإيصال متاح فقط للدفعات المؤكَّدة.');
        }

        if (! $payment->receipt_pdf_path || ! Storage::disk('public')->exists($payment->receipt_pdf_path)) {
            // إعادة إطلاق الحدث كإجراء احتياطي في حال فُقد الملف أو توقف طابور المهام (Queue).
            \App\Jobs\GeneratePaymentReceiptJob::dispatch($payment->id);
            abort(409, 'الإيصال قيد الإصدار حالياً، يرجى المحاولة بعد قليل.');
        }

        return Storage::disk('public')->download(
            $payment->receipt_pdf_path,
            "receipt-{$payment->id}.pdf",
        );
    }

    /**
     * GET /api/v1/contractor/payments/transfer
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
            ->through(fn ($p) => new PaymentResource($p));

        return $this->paginated($paginator);
    }

    /**
     * GET /api/v1/contractor/payments/{payment}
     */
    public function show(Request $request, Payment $payment)
    {
        if ($payment->contractor_id !== $request->user()->id) {
            return $this->error('غير مصرَّح لك بالوصول لهذا الإشعار.', 403);
        }

        return $this->success(new PaymentResource($payment->load('contractor:id,name')));
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

        $paginator = $query->latest()->paginate(15)->through(fn ($p) => new PaymentResource($p));

        return $this->paginated($paginator);
    }

    // POST /api/v1/payments/transactions
    public function store(StorePaymentRequest $request)
    {
        $validated = $request->validated();
        $currency = strtoupper($validated['currency'] ?? 'JOD');

        $rate = null;
        $rateSource = null;

        if ($currency !== 'JOD') {
            if (isset($validated['exchange_rate'])) {
                $rate       = (float) $validated['exchange_rate'];
                $rateSource = 'manual';
            } else {
                $latest = $this->exchangeRateService->latest($currency);
                if (! $latest) {
                    return $this->error("لا يوجد سعر صرف معتمد لعملة {$currency} — أدخل السعر يدوياً.", 422);
                }
                $rate       = (float) $latest->rate_to_jod;
                $rateSource = $latest->source;
            }
        }

        $amountJod = $this->exchangeRateService->convertToJod((float) $validated['amount'], $currency, $rate ?? 1.0);

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
     */
    public function confirm(ConfirmPaymentRequest $request, Payment $payment)
    {
        try {
            $this->confirmationService->confirm($payment, $request->validated(), $request->user());
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 422);
        }

        Log::channel('finance')->info('payment.confirmed', [
            'user_id'       => Auth::id(),
            'payment_id'    => $payment->id,
            'contractor_id' => $payment->contractor_id,
            'amount'        => $payment->amount,
            'currency'      => $payment->currency,
            'exchange_rate' => $payment->exchange_rate,
            'amount_jod'    => $payment->amount_jod,
        ]);

        if ($payment->contractor) {
            $payment->contractor->notify(new PaymentConfirmedNotification($payment));
        }

        return $this->success(new PaymentResource($payment->fresh('contractor')), 'تم تأكيد عملية الدفع بنجاح.');
    }

    /**
     * POST /api/v1/payments/transactions/{payment}/receipt-image
     */
    public function uploadReceiptImage(UploadReceiptImageRequest $request, Payment $payment)
    {
        if ($payment->receipt_image) {
            Storage::disk('public')->delete($payment->receipt_image);
        }

        $path = $request->file('receipt_image')->store('payment-receipts', 'public');
        $payment->update(['receipt_image' => $path]);

        AuditLogService::record(
            $request->user(),
            'payment.receipt_image_uploaded',
            $payment,
            [],
        );

        return $this->success(new PaymentResource($payment->fresh('contractor')), 'تم رفع صورة الإشعار بنجاح.');
    }

    /**
     * POST /api/v1/payments/transactions/{payment}/reject
     */
    public function reject(RejectPaymentRequest $request, Payment $payment)
    {
        $this->confirmationService->reject($payment, $request->validated()['rejection_reason'], $request->user());

        if ($payment->contractor) {
            $payment->contractor->notify(new PaymentRejectedNotification($payment));
        }

        return $this->success(new PaymentResource($payment->fresh('contractor')), 'تم رفض إشعار التحويل.');
    }
}

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
use App\Http\Requests\Payment\StoreManualPaymentRequest;
use App\Models\Contractor;
use App\Services\DuesPaymentService;
use App\Http\Requests\Payment\ConfirmPaymentRequest;
use App\Http\Requests\Payment\UploadReceiptImageRequest;
use App\Http\Requests\Payment\RejectPaymentRequest;
use App\Http\Requests\Payment\ChangePaymentStatusRequest;
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

        // تحويل لتسديد ذمة محددة (زر "ادفع الآن" على كرت الذمة)
        $due = null;
        $penalty = null;
        if (! empty($data['contractor_due_id'])) {
            $due = \App\Models\ContractorDue::find($data['contractor_due_id']);

            if ($due->contractor_id !== $contractor->id) {
                return $this->error('هذه الذمة لا تعود لحسابك.', 403);
            }
            if ($due->status === 'paid') {
                return $this->error('هذه الذمة مسدَّدة بالكامل مسبقاً.', 409);
            }
            if ($due->payments()->where('status', 'pending')->exists()) {
                return $this->error('يوجد إشعار تحويل لهذه الذمة قيد المراجعة بالفعل.', 409);
            }

            $type = 'dues_payment';
        } elseif (! empty($data['penalty_id'])) {
            // تحويل لدفع غرامة محددة (كرت الغرامة بالمستحقات)
            $penalty = \App\Models\Penalty::find($data['penalty_id']);

            if ($penalty->contractor_id !== $contractor->id) {
                return $this->error('هذه الغرامة لا تعود لحسابك.', 403);
            }
            if ($penalty->remaining <= 0) {
                return $this->error('هذه الغرامة مسدَّدة بالكامل أو ملغاة.', 409);
            }
            if (Payment::where('penalty_id', $penalty->id)->where('status', 'pending')->exists()) {
                return $this->error('يوجد إشعار تحويل لهذه الغرامة قيد المراجعة بالفعل.', 409);
            }

            $type = 'penalty_payment';
        } elseif (empty($data['type']) && empty($data['membership_id'])
            && \App\Services\PaymentConfirmationService::hasOpenObligations($contractor)) {
            // بدون نوع والمقاول عليه ذمم = سداد ذمم (بتسدّد أقدمها والفائض رصيد له)، مش رسوم عضوية
            $type = 'dues_payment';
        }

        // سداد ذمة/غرامة ودفعة مقدمة مسموحين دايماً (حتى بدون ذمة مسجّلة — الدفعة المقدمة بتصير
        // رصيداً بينصرف على أي ذمة جديدة). الحظر بالذمم بس على رسوم العضوية وباقي الأنواع.
        if (! in_array($type, Payment::CREDIT_TYPES, true)) {
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
            'contractor_due_id'    => $due?->id,
            'penalty_id'           => $penalty?->id,
            'bank_account_id'      => $data['bank_account_id'] ?? null,
            'amount'               => $data['amount'],
            'currency'             => $data['currency'] ?? 'JOD',
            'type'                 => $type,
            'status'               => 'pending',
            'method'               => 'bank_transfer',
            'reference_number'     => $data['reference_number'] ?? null,
            'receipt_image'        => $path,
            'notes'                => filled($data['notes'] ?? null) ? trim($data['notes']) : ($due?->description ?? $penalty?->reason),
            'submitted_at'         => now(),
        ]);
        $payment->update(['transaction_number' => Payment::generateTransactionNumber($payment)]);

        $reviewers = User::whereIn('role', ['accountant', 'admin'])->get();
        if ($reviewers->isNotEmpty()) {
            Notification::send($reviewers, new PaymentSubmittedNotification($payment));
        }

        return $this->success(
            new PaymentResource($payment->load(['contractor', ...Payment::TITLE_RELATIONS])),
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
        $query = $request->user()->payments()->with(['contractor:id,name', ...Payment::TITLE_RELATIONS]);

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

        return $this->success(new PaymentResource($payment->load(['contractor:id,name', ...Payment::TITLE_RELATIONS])));
    }

    // ═════════════════════════════════════════════════════════════════════════
    //  Admin / Accountant Dashboard
    // ═════════════════════════════════════════════════════════════════════════

    // GET /api/v1/payments/transactions
    public function index(Request $request)
    {
        $query = Payment::with(['contractor', 'latestStatusChange.changer:id,name', ...Payment::TITLE_RELATIONS]);

        // فتح دفعة محددة من إشعار في لوحة التحكم
        if ($request->filled('id')) {
            $query->whereKey((int) $request->id);
        }

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

        AuditLogService::record($request->user(), 'payment.created', $payment, ['contractor_id' => $payment->contractor_id, 'amount' => $payment->amount, 'currency' => $payment->currency]);

        return $this->success($payment->toArray(), 'تم تسجيل المعاملة بنجاح.', 201);
    }

    /**
     * POST /api/v1/payments/transactions/manual
     * دفعة يُدخلها الأدمن/المحاسب نيابةً عن المقاول (مع صورة الإشعار) — تُسجَّل مؤكَّدة
     * مباشرة وتوزَّع على أقدم الذمم، والزائد يبقى رصيداً متاحاً في الدفعة (amount_jod - used_amount_jod).
     */
    public function storeManual(StoreManualPaymentRequest $request, DuesPaymentService $duesPaymentService)
    {
        $data       = $request->validated();
        $contractor = Contractor::findOrFail($data['contractor_id']);

        if ($request->hasFile('receipt_image')) {
            $data['receipt_image'] = $request->file('receipt_image')->store('payment-receipts', 'public');
        }

        try {
            $result = $duesPaymentService->processPayment($contractor, $data, Auth::id());
        } catch (\Exception $e) {
            if (! empty($data['receipt_image'])) {
                Storage::disk('public')->delete($data['receipt_image']);
            }
            return $this->error($e->getMessage(), 422);
        }

        $payment = Payment::with('contractor')->findOrFail($result['payment_id']);

        \App\Jobs\GeneratePaymentReceiptJob::dispatch($payment->id);

        AuditLogService::record(
            $request->user(),
            'payment.manual_created',
            $payment,
            ['amount' => $payment->amount, 'currency' => $payment->currency, 'amount_jod' => $payment->amount_jod],
        );

        Log::channel('finance')->info('payment.manual_created', [
            'user_id'       => Auth::id(),
            'payment_id'    => $payment->id,
            'contractor_id' => $contractor->id,
            'amount'        => $payment->amount,
            'currency'      => $payment->currency,
            'exchange_rate' => $payment->exchange_rate,
            'amount_jod'    => $payment->amount_jod,
            'applied'       => $result['applied'],
        ]);

        $contractor->notify(new PaymentConfirmedNotification($payment));

        return $this->success([
            'payment'             => new PaymentResource($payment),
            'applied'             => $result['applied'],
            'unapplied_jod'       => $result['unapplied_jod'],
            'remaining_total_jod' => app(\App\Services\ContractorFinancialService::class)->outstandingDuesTotal($contractor),
        ], 'تمت إضافة الدفعة وتوزيعها على الذمم بنجاح.', 201);
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
        try {
            $this->confirmationService->reject($payment, $request->validated()['rejection_reason'], $request->user());
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 422);
        }

        if ($payment->contractor) {
            $payment->contractor->notify(new PaymentRejectedNotification($payment));
        }

        return $this->success(new PaymentResource($payment->fresh('contractor')), 'تم رفض إشعار التحويل.');
    }

    /**
     * POST /api/v1/payments/transactions/{payment}/status
     * "تغيير الحالة" من قائمة الإجراءات — السبب إجباري وبينحفظ مع مين غيّر وإيمتى.
     */
    public function changeStatus(ChangePaymentStatusRequest $request, Payment $payment)
    {
        $data = $request->validated();

        try {
            $this->confirmationService->changeStatus($payment, $data['status'], $data['reason'], $data, $request->user());
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 422);
        }

        $payment->refresh();

        if ($payment->contractor) {
            match ($payment->status) {
                'paid'     => $payment->contractor->notify(new PaymentConfirmedNotification($payment)),
                'rejected' => $payment->contractor->notify(new PaymentRejectedNotification($payment)),
                default    => null,
            };
        }

        Log::channel('finance')->info('payment.status_changed', [
            'user_id'    => Auth::id(),
            'payment_id' => $payment->id,
            'to'         => $payment->status,
            'reason'     => $data['reason'],
        ]);

        return $this->success(
            new PaymentResource($payment->load(['contractor', 'latestStatusChange.changer:id,name'])),
            'تم تغيير حالة الدفعة.',
        );
    }
}

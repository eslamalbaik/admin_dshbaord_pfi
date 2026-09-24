<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Models\Contractor;
use App\Models\ContractorDue;
use App\Models\Payment;
use App\Http\Requests\ContractorDue\PayContractorDueRequest;
use App\Http\Requests\ContractorDue\ImportContractorDuesRequest;
use App\Http\Requests\ContractorDue\StoreContractorDueRequest;
use App\Http\Requests\ContractorDue\UpdateContractorDueRequest;
use App\Http\Requests\ContractorDue\SettleContractorDueRequest;
use App\Http\Requests\ContractorDue\GenerateFeeRequest;
use App\Http\Requests\ContractorDue\GenerateFeeBulkRequest;
use App\Http\Requests\ContractorDue\ApplyDiscountRequest;
use App\Http\Requests\ContractorDue\ApplyDiscountBulkRequest;
use App\Http\Resources\ContractorDueResource;
use App\Services\DuesPaymentService;
use App\Services\DuesDiscountService;
use App\Services\DuesGenerationService;
use App\Services\LegacyDuesImporter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ContractorDueController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private DuesPaymentService $paymentService,
        private DuesDiscountService $discountService,
        private DuesGenerationService $generationService,
        private \App\Services\ContractorFinancialService $financialService
    ) {}

    private function financeLog(string $action, array $context = []): void
    {
        Log::channel('finance')->info($action, array_merge([
            'user_id' => Auth::id(),
        ], $context));
    }

    /** GET /api/v1/dashboard/dues */
    public function index(Request $request)
    {
        $query = ContractorDue::with(['contractor:id,name,membership_number', 'createdBy:id,name']);

        if ($request->filled('contractor_id')) {
            $query->where('contractor_id', $request->contractor_id);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('year')) {
            $query->where('year', $request->year);
        }
        if ($request->filled('source')) {
            $query->where('source', $request->source);
        }
        if ($request->filled('search')) {
            $q = $request->search;
            $query->whereHas('contractor', fn ($c) => $c
                ->where('name', 'like', "%{$q}%")
                ->orWhere('membership_number', 'like', "%{$q}%"));
        }

        return $this->paginated(
            $query->latest()
                ->paginate(min($request->integer('per_page', 15), 100))
                ->through(fn ($d) => new ContractorDueResource($d))
        );
    }

    /** GET /api/v1/dashboard/dues/by-contractor */
    public function byContractor(Request $request)
    {
        $query = Contractor::query()
            ->whereHas('dues')
            ->withCount('dues')
            ->withSum('dues as dues_total_jod', 'amount_jod')
            ->withSum('dues as dues_paid_jod', 'paid_jod');

        if ($request->filled('search')) {
            $q = $request->search;
            $query->where(fn ($qb) => $qb
                ->where('name', 'like', "%{$q}%")
                ->orWhere('membership_number', 'like', "%{$q}%"));
        }

        if ($request->boolean('outstanding_only')) {
            $query->whereHas('dues', fn ($d) => $d->where('status', '!=', 'paid'));
        }

        $paginator = $query
            ->orderByRaw('(COALESCE(dues_total_jod,0) - COALESCE(dues_paid_jod,0)) desc')
            ->paginate(min($request->integer('per_page', 15), 100))
            ->through(fn ($c) => [
                'contractor_id'     => $c->id,
                'name'              => $c->name,
                'membership_number' => $c->membership_number,
                'dues_count'        => $c->dues_count,
                'total_jod'         => round((float) $c->dues_total_jod, 2),
                'paid_jod'          => round((float) $c->dues_paid_jod, 2),
                'remaining_jod'     => round((float) $c->dues_total_jod - (float) $c->dues_paid_jod, 2),
            ]);

        return $this->paginated($paginator);
    }

    /** POST /api/v1/dashboard/contractors/{contractor}/dues/pay */
    public function payForContractor(PayContractorDueRequest $request, Contractor $contractor)
    {
        $data = $request->validated();
        
        $result = $this->paymentService->processPayment($contractor, $data, Auth::id());

        $this->financeLog('due.contractor_payment', [
            'contractor_id' => $contractor->id,
            'payment_id'    => $result['payment_id'],
            'amount'        => $data['amount'],
            'currency'      => $result['currency'],
            'exchange_rate' => $result['exchange_rate'],
            'amount_jod'    => $result['amount_jod'],
            'applied'       => $result['applied'],
        ]);

        return $this->success(
            $result + ['remaining_total_jod' => $this->financialService->outstandingDuesTotal($contractor)],
            'تم تسجيل الدفعة وتوزيعها على الذمم بنجاح.',
            201,
        );
    }

    /** POST /api/v1/dashboard/dues/import */
    public function import(ImportContractorDuesRequest $request, LegacyDuesImporter $importer)
    {
        $dryRun        = $request->boolean('dry_run');
        $force         = $request->boolean('force');
        $createMissing = $request->boolean('create_missing', true);

        if ($importer->hasPreviousImport() && ! $force && ! $dryRun) {
            return $this->error(
                'يوجد استيراد سابق — فعّل خيار "استبدال الاستيراد السابق" لإعادة الاستيراد.',
                409,
                null,
                'previous_import_exists',
            );
        }

        @set_time_limit(300);
        $path = $request->file('file')->storeAs(
            'imports',
            'dues-import-' . now()->format('Ymd-His') . '.' . $request->file('file')->getClientOriginalExtension(),
            'local',
        );
        $fullPath = Storage::disk('local')->path($path);

        try {
            $analysis = $importer->analyze($fullPath);
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($path);
            return $this->error('تعذّرت قراءة الملف: ' . $e->getMessage(), 422);
        }

        $imported = ['dues' => 0, 'contractors_created' => 0];
        if (! $dryRun) {
            $imported = $importer->import($analysis['matched'], $analysis['unmatched'], $force, Auth::id(), $createMissing);
            $this->financeLog('due.excel_imported', [
                'imported'            => $imported['dues'],
                'contractors_created' => $imported['contractors_created'],
                'matched'             => count($analysis['matched']),
                'unmatched'           => count($analysis['unmatched']),
                'force'               => $force,
            ]);
        }

        $importer->logReport($analysis, $dryRun, Auth::id());

        return $this->success([
            'dry_run'             => $dryRun,
            'create_missing'      => $createMissing,
            'imported_count'      => $imported['dues'],
            'contractors_created' => $imported['contractors_created'],
            'companies_total'    => count($analysis['companies']),
            'matched_count'      => count($analysis['matched']),
            'unmatched_count'    => count($analysis['unmatched']),
            'total_sheet_jod'    => $analysis['total_sheet_jod'],
            'total_matched_jod'  => $analysis['total_matched_jod'],
            'matched'            => collect($analysis['matched'])->map(fn ($c) => [
                'seq'               => $c['seq'],
                'membership_number' => $c['membership_number'],
                'name'              => $c['name'],
                'dues'              => $c['dues'],
            ])->values(),
            'unmatched'          => collect($analysis['unmatched'])->map(fn ($c) => [
                'seq'               => $c['seq'],
                'membership_number' => $c['membership_number'],
                'name'              => $c['name'],
                'dues'              => $c['dues'],
            ])->values(),
        ], $dryRun
            ? 'معاينة الاستيراد — لم يُكتب أي شيء.'
            : "تم استيراد {$imported['dues']} ذمة" . ($imported['contractors_created'] > 0 ? " وإنشاء {$imported['contractors_created']} شركة جديدة" : '') . ' بنجاح.');
    }

    /** GET /api/v1/dashboard/dues/summary */
    public function summary()
    {
        $base = ContractorDue::query();

        // ذمم سنة 2024 وما قبل تُجمّع تحت "رسوم متراكمة" بدون تفصيل بالسنة
        $byYear = (clone $base)
            ->selectRaw('year, COALESCE(SUM(amount_jod), 0) as total_jod, COALESCE(SUM(amount_jod - paid_jod), 0) as outstanding_jod')
            ->groupBy('year')
            ->where(function ($q) {
                $q->where('year', '>=', 2025)->orWhereNull('year');
            })
            ->orderBy('year')
            ->get()
            ->toArray();

        // إجمالي ذمم ما قبل 2025
        $preBefore2025 = (clone $base)
            ->where(function ($q) {
                $q->where('year', '<', 2025)->whereNotNull('year');
            })
            ->selectRaw('COALESCE(SUM(amount_jod), 0) as total_jod, COALESCE(SUM(amount_jod - paid_jod), 0) as outstanding_jod')
            ->first();

        if ($preBefore2025 && ($preBefore2025->total_jod > 0 || $preBefore2025->outstanding_jod > 0)) {
            array_unshift($byYear, [
                'year' => null,
                'total_jod' => (float) $preBefore2025->total_jod,
                'outstanding_jod' => (float) $preBefore2025->outstanding_jod,
            ]);
        }

        return $this->success([
            'outstanding_total_jod' => round((float) (clone $base)->outstanding()
                ->selectRaw('COALESCE(SUM(amount_jod - paid_jod), 0) as t')->value('t'), 2),
            'collected_total_jod'   => round((float) (clone $base)
                ->selectRaw('COALESCE(SUM(paid_jod), 0) as t')->value('t'), 2),
            'contractors_with_dues' => (clone $base)->outstanding()
                ->distinct('contractor_id')->count('contractor_id'),
            'outstanding_dues_count' => (clone $base)->outstanding()->count(),
            'dues_count'             => (clone $base)->count(),
            'by_year' => $byYear,
            'registration_fees' => ContractorDue::registrationFeesSummary(),
        ]);
    }

    /** POST /api/v1/dashboard/dues */
    public function store(StoreContractorDueRequest $request)
    {
        $data = $request->validated();

        // ليسا عمودين بالجدول — يُستخرجان قبل الإنشاء ويُسجَّلان بسجل المالية وبالملاحظات،
        // حتى يبقى للتاريخ السابق أثر مكتوب يُسأل عنه لاحقاً (TASK-17 #7).
        $allowBackdate  = (bool) ($data['allow_backdate'] ?? false);
        $backdateReason = $data['backdate_reason'] ?? null;
        unset($data['allow_backdate'], $data['backdate_reason']);

        if ($allowBackdate && $backdateReason) {
            $data['notes'] = trim(($data['notes'] ?? '') . "\nذمة متأخّرة سابقة — سبب التاريخ السابق: {$backdateReason}");
        }

        $due = ContractorDue::create($data + [
            'status'     => 'unpaid',
            'source'     => 'manual',
            'created_by' => Auth::id(),
        ]);
        $due->update(['reference_number' => ContractorDue::generateReferenceNumber($due)]);

        $this->financeLog('due.created', [
            'due_id'          => $due->id,
            'contractor_id'   => $due->contractor_id,
            'amount_jod'      => $due->amount_jod,
            'due_date'        => $due->due_date?->toDateString(),
            'backdated'       => $allowBackdate,
            'backdate_reason' => $allowBackdate ? $backdateReason : null,
        ]);

        return $this->success(
            new ContractorDueResource($due->load('contractor:id,name,membership_number')),
            'تمت إضافة الذمة المالية بنجاح.',
            201,
        );
    }

    /** PATCH /api/v1/dashboard/dues/{due} */
    public function update(UpdateContractorDueRequest $request, ContractorDue $due)
    {
        $data = $request->validated();

        if ($due->source === 'fee_engine' && array_key_exists('amount_jod', $data)) {
            return $this->error(
                'هذه الذمة صادرة عن محرّك احتساب الرسوم — استخدم مسار إعادة التوليد أو مسار الخصم بدل تعديل المبلغ مباشرة.',
                422,
            );
        }

        $due->update($data);
        $due->applyPayment(0); // إعادة احتساب الحالة إن تغيّر المبلغ

        $this->financeLog('due.updated', ['due_id' => $due->id, 'changes' => $data]);

        return $this->success(new ContractorDueResource($due->fresh(['contractor:id,name,membership_number'])), 'تم تحديث الذمة.');
    }

    /** POST /api/v1/dashboard/dues/{due}/settle */
    public function settle(SettleContractorDueRequest $request, ContractorDue $due)
    {
        $data = $request->validated();

        if ($due->status === 'paid') {
            return $this->error('هذه الذمة مسدَّدة بالكامل مسبقاً.', 409);
        }

        if (! empty($data['payment_id'])) {
            $payment = Payment::find($data['payment_id']);

            if ($payment->contractor_id !== $due->contractor_id) {
                return $this->error('معاملة الدفع لا تعود لنفس المقاول.', 422);
            }
            if ($payment->status !== 'paid') {
                return $this->error('لا يمكن التسوية بمعاملة دفع غير مؤكّدة.', 422);
            }
        }

        $amount = (float) ($data['amount_jod'] ?? $due->remaining_jod);

        if ($amount > $due->remaining_jod) {
            return $this->error('المبلغ المراد تسويته يتجاوز المتبقي على هذه الذمة.', 422);
        }

        if (isset($payment)) {
            $available = $payment->amount_jod - $payment->used_amount_jod;
            if ($amount > $available) {
                return $this->error('المبلغ المراد تسويته يتجاوز الرصيد المتاح في الدفعة.', 422);
            }
            $payment->increment('used_amount_jod', $amount);
        }

        $due->applyPayment($amount);

        if (! empty($data['notes'])) {
            $due->update(['notes' => trim($due->notes . "\n" . $data['notes'])]);
        }

        $this->financeLog('due.settled', [
            'due_id'     => $due->id,
            'amount_jod' => $amount,
            'payment_id' => $data['payment_id'] ?? null,
            'new_status' => $due->status,
        ]);

        return $this->success(new ContractorDueResource($due->fresh(['contractor:id,name,membership_number'])), 'تمت تسوية الذمة بنجاح.');
    }

    /** DELETE /api/v1/dashboard/dues/{due} */
    public function destroy(ContractorDue $due)
    {
        $due->delete();

        $this->financeLog('due.deleted', ['due_id' => $due->id, 'contractor_id' => $due->contractor_id]);

        return $this->success(message: 'تم حذف الذمة.');
    }

    /** POST /api/v1/dashboard/contractors/{contractor}/dues/calculate-fee */
    public function calculateFee(GenerateFeeRequest $request, Contractor $contractor)
    {
        $data = $request->validated();

        try {
            $result = $this->generationService->calculateFee($contractor, $data['year'], $data['discount_type'] ?? null, $data['discount_value'] ?? null);
            return $this->success($result);
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    /** POST /api/v1/dashboard/contractors/{contractor}/dues/generate-fee */
    public function generateFee(GenerateFeeRequest $request, Contractor $contractor)
    {
        $data = $request->validated();
        $force = $request->boolean('force');

        try {
            $result = $this->generationService->generateFee($contractor, $data['year'], $data, $force, Auth::id());
            $this->financeLog('due.fee_generated', ['due_id' => $result['due']->id, 'contractor_id' => $contractor->id, 'year' => $data['year'], 'force' => $force]);

            return $this->success([
                'due'     => new ContractorDueResource($result['due']),
                'warning' => $result['warning'],
            ], 'تم توليد ذمة الرسوم بنجاح.');
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 422);
        }
    }

    /** POST /api/v1/dashboard/dues/generate-fee/bulk */
    public function generateFeeBulk(GenerateFeeBulkRequest $request)
    {
        $data = $request->validated();
        $dryRun = $request->boolean('dry_run');

        $result = $this->generationService->generateFeeBulk($data['contractor_ids'] ?? [], $data['year'], $dryRun, Auth::id());

        $this->financeLog('due.fee_generated_bulk', ['year' => $data['year'], 'created' => $result['created_count'], 'dry_run' => $dryRun]);

        return $this->success($result);
    }

    /** POST /api/v1/dashboard/dues/{due}/discount */
    public function applyDiscount(ApplyDiscountRequest $request, ContractorDue $due)
    {
        $data = $request->validated();

        try {
            $due->applyDiscount($data['discount_type'], $data['discount_value'], $data['discount_reason'] ?? null, Auth::id());
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 422);
        }

        $this->financeLog('due.discount_applied', [
            'due_id' => $due->id, 'discount_type' => $data['discount_type'], 'discount_value' => $data['discount_value'],
        ]);

        return $this->success(new ContractorDueResource($due->fresh(['contractor:id,name,membership_number'])), 'تم تطبيق الخصم بنجاح.');
    }

    /** POST /api/v1/dashboard/dues/discount/bulk */
    public function applyDiscountBulk(ApplyDiscountBulkRequest $request)
    {
        $data = $request->validated();
        $dryRun = $request->boolean('dry_run');

        $result = $this->discountService->applyBulk($data['mode'] === 'ids' ? ['ids' => $data['ids']] : $data['criteria'], $data['mode'], $data, $dryRun, Auth::id());

        if (! $dryRun) {
            $this->financeLog('due.discount_applied_bulk', [
                'mode'        => $data['mode'],
                'criteria'    => $data['mode'] === 'criteria' ? $data['criteria'] : null,
                'matched'     => $result['matched_count'],
                'applicable'  => $result['applicable_count'],
                'contractors' => $result['contractors_count'],
                'applied'     => $result['applied_count'],
                'skipped'     => count($result['skipped']),
            ]);
            return $this->success($result, 'تم تطبيق الخصم الجماعي بنجاح.');
        }

        return $this->success($result);
    }
}

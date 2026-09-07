<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Models\ContractorDue;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class ContractorDueController extends Controller
{
    use ApiResponseTrait;

    private function format(ContractorDue $d): array
    {
        return [
            'id'                => $d->id,
            'contractor_id'     => $d->contractor_id,
            'contractor'        => $d->contractor?->name,
            'membership_number' => $d->contractor?->membership_number,
            'year'              => $d->year,
            'period'            => $d->period,
            'reference_number'  => $d->reference_number,
            'description'       => $d->description,
            'amount_jod'        => $d->amount_jod,
            'paid_jod'          => $d->paid_jod,
            'remaining_jod'     => $d->remaining_jod,
            'status'            => $d->status,
            'status_label'      => $d->status_label,
            'source'            => $d->source,
            'due_date'          => $d->due_date?->toDateString(),
            'notes'             => $d->notes,
            'created_by'        => $d->createdBy?->name,
            'created_at'        => $d->created_at,
            'discount_type'        => $d->discount_type,
            'discount_value'       => $d->discount_value,
            'discount_amount_jod'  => $d->discount_amount_jod,
            'discount_reason'      => $d->discount_reason,
            'original_amount_jod'  => $d->original_amount_jod,
            'fee_breakdown'        => $d->fee_breakdown,
        ];
    }

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
                ->through(fn ($d) => $this->format($d))
        );
    }

    /**
     * GET /api/v1/dashboard/dues/by-contractor
     * عرض مجمّع: صف لكل مقاول مع إجمالي ذممه والمتبقي عليه.
     */
    public function byContractor(Request $request)
    {
        $query = \App\Models\Contractor::query()
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

    /**
     * POST /api/v1/dashboard/contractors/{contractor}/dues/pay
     * يسجّل المحاسب دفعة واردة من الشركة (بأي عملة) وتُوزَّع تلقائياً
     * على ذممها غير المسدَّدة — الأقدم أولاً.
     */
    public function payForContractor(Request $request, \App\Models\Contractor $contractor)
    {
        $data = $request->validate([
            'amount'           => 'required|numeric|min:0.01',
            'currency'         => 'nullable|in:JOD,ILS,USD',
            'exchange_rate'    => 'nullable|numeric|min:0.0001|max:1000',
            'method'           => 'nullable|in:cash,bank_transfer,cheque',
            'reference_number' => 'nullable|string|max:100',
            'notes'            => 'nullable|string|max:500',
        ]);

        $currency = strtoupper($data['currency'] ?? 'JOD');
        $service  = app(\App\Services\ExchangeRateService::class);

        // تثبيت سعر الصرف والمعادل بالدينار
        $rate       = null;
        $rateSource = null;
        if ($currency !== 'JOD') {
            if (isset($data['exchange_rate'])) {
                $rate       = (float) $data['exchange_rate'];
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

        $amountJod = $service->convertToJod((float) $data['amount'], $currency, $rate ?? 1.0);

        $result = \Illuminate\Support\Facades\DB::transaction(function () use ($contractor, $data, $currency, $rate, $rateSource, $amountJod) {
            $payment = Payment::create([
                'contractor_id'    => $contractor->id,
                'amount'           => $data['amount'],
                'currency'         => $currency,
                'exchange_rate'    => $rate,
                'amount_jod'       => $amountJod,
                'used_amount_jod'  => 0, // Will be updated below
                'rate_source'      => $currency === 'JOD' ? null : $rateSource,
                'type'             => 'dues_payment',
                'status'           => 'paid',
                'method'           => $data['method'] ?? 'cash',
                'reference_number' => $data['reference_number'] ?? null,
                'notes'            => $data['notes'] ?? null,
                'confirmed_by'     => Auth::id(),
                'confirmed_at'     => now(),
                'paid_at'          => now(),
            ]);

            // التوزيع على الذمم غير المسدَّدة — الأقدم (سنةً) أولاً
            $remaining = $amountJod;
            $settled   = [];

            $dues = $contractor->dues()
                ->outstanding()
                ->orderByRaw('year IS NULL, year asc')
                ->orderBy('id')
                ->get();

            foreach ($dues as $due) {
                if ($remaining <= 0) {
                    break;
                }

                $applied = min($remaining, $due->remaining_jod);
                $due->applyPayment($applied);
                $remaining = round($remaining - $applied, 2);

                $settled[] = [
                    'due_id'      => $due->id,
                    'year'        => $due->year,
                    'description' => $due->description,
                    'applied_jod' => $applied,
                    'status'      => $due->status,
                ];
            }

            // تحديث المبلغ المستخدم من الدفعة
            $payment->update(['used_amount_jod' => $amountJod - $remaining]);

            return [
                'payment_id'     => $payment->id,
                'amount_jod'     => $amountJod,
                'applied'        => $settled,
                'unapplied_jod'  => $remaining, // فائض بعد سداد كل الذمم (إن وُجد)
            ];
        });

        $this->financeLog('due.contractor_payment', [
            'contractor_id' => $contractor->id,
            'payment_id'    => $result['payment_id'],
            'amount'        => $data['amount'],
            'currency'      => $currency,
            'exchange_rate' => $rate,
            'amount_jod'    => $amountJod,
            'applied'       => $result['applied'],
        ]);

        return $this->success(
            $result + ['remaining_total_jod' => $contractor->outstandingDuesTotal()],
            'تم تسجيل الدفعة وتوزيعها على الذمم بنجاح.',
            201,
        );
    }

    /**
     * POST /api/v1/dashboard/dues/import
     * رفع كشف إكسل (بنفس بنية كشف الأرشفة) واستيراده كذمم.
     * dry_run=1: معاينة التقرير فقط دون كتابة.
     * force=1: حذف استيراد سابق وإعادة الاستيراد.
     */
    public function import(Request $request, \App\Services\LegacyDuesImporter $importer)
    {
        $request->validate([
            'file'           => 'required|file|mimes:xlsx,xls|max:10240',
            'dry_run'        => 'nullable|boolean',
            'force'          => 'nullable|boolean',
            'create_missing' => 'nullable|boolean',
        ]);

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

        // حفظ الملف بامتداده الصحيح حتى يتعرف القارئ على نوعه بدقة
        @set_time_limit(300);
        $path = $request->file('file')->storeAs(
            'imports',
            'dues-import-' . now()->format('Ymd-His') . '.' . $request->file('file')->getClientOriginalExtension(),
            'local',
        );
        $fullPath = \Illuminate\Support\Facades\Storage::disk('local')->path($path);

        try {
            $analysis = $importer->analyze($fullPath);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Storage::disk('local')->delete($path);

            return $this->error('تعذّرت قراءة الملف: ' . $e->getMessage(), 422);
        }

        $imported = ['dues' => 0, 'contractors_created' => 0];
        if (! $dryRun) {
            $imported = $importer->import(
                $analysis['matched'],
                $analysis['unmatched'],
                $force,
                Auth::id(),
                $createMissing,
            );
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

        return $this->success([
            'outstanding_total_jod' => round((float) (clone $base)->outstanding()
                ->selectRaw('COALESCE(SUM(amount_jod - paid_jod), 0) as t')->value('t'), 2),
            'collected_total_jod'   => round((float) (clone $base)
                ->selectRaw('COALESCE(SUM(paid_jod), 0) as t')->value('t'), 2),
            'contractors_with_dues' => (clone $base)->outstanding()
                ->distinct('contractor_id')->count('contractor_id'),
            'by_year' => (clone $base)
                ->selectRaw('year, COALESCE(SUM(amount_jod), 0) as total_jod, COALESCE(SUM(amount_jod - paid_jod), 0) as outstanding_jod')
                ->groupBy('year')
                ->orderBy('year')
                ->get(),
            'registration_fees' => ContractorDue::registrationFeesSummary(),
        ]);
    }

    /** POST /api/v1/dashboard/dues */
    public function store(Request $request)
    {
        $data = $request->validate([
            'contractor_id' => 'required|exists:contractors,id',
            'description'   => 'required|string|max:500',
            'amount_jod'    => 'required|numeric|min:0.01|max:99999999',
            'year'          => 'nullable|integer|between:1990,2100',
            'period'        => 'nullable|string|max:50',
            'due_date'      => 'nullable|date',
            'notes'         => 'nullable|string|max:2000',
        ]);

        $due = ContractorDue::create($data + [
            'source'     => 'manual',
            'created_by' => Auth::id(),
        ]);
        $due->update(['reference_number' => ContractorDue::generateReferenceNumber($due)]);

        $this->financeLog('due.created', [
            'due_id'        => $due->id,
            'contractor_id' => $due->contractor_id,
            'amount_jod'    => $due->amount_jod,
        ]);

        return $this->success(
            $this->format($due->load('contractor:id,name,membership_number')),
            'تمت إضافة الذمة المالية بنجاح.',
            201,
        );
    }

    /** PATCH /api/v1/dashboard/dues/{due} */
    public function update(Request $request, ContractorDue $due)
    {
        $data = $request->validate([
            'description' => 'sometimes|string|max:500',
            'amount_jod'  => 'sometimes|numeric|min:0.01|max:99999999',
            'year'        => 'nullable|integer|between:1990,2100',
            'period'      => 'nullable|string|max:50',
            'due_date'    => 'nullable|date',
            'notes'       => 'nullable|string|max:2000',
        ]);

        if ($due->source === 'fee_engine' && array_key_exists('amount_jod', $data)) {
            return $this->error(
                'هذه الذمة صادرة عن محرّك احتساب الرسوم — استخدم مسار إعادة التوليد أو مسار الخصم بدل تعديل المبلغ مباشرة.',
                422,
            );
        }

        $due->update($data);

        // إعادة احتساب الحالة إن تغيّر المبلغ
        $due->applyPayment(0);

        $this->financeLog('due.updated', ['due_id' => $due->id, 'changes' => $data]);

        return $this->success($this->format($due->fresh(['contractor:id,name,membership_number'])), 'تم تحديث الذمة.');
    }

    /**
     * POST /api/v1/dashboard/dues/{due}/settle
     * تسوية ذمة (كاملة أو جزئية) — اختيارياً بربطها بمعاملة دفع مؤكّدة.
     */
    public function settle(Request $request, ContractorDue $due)
    {
        $data = $request->validate([
            'amount_jod' => 'nullable|numeric|min:0.01',
            'payment_id' => 'nullable|exists:payments,id',
            'notes'      => 'nullable|string|max:500',
        ]);

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

        return $this->success($this->format($due->fresh(['contractor:id,name,membership_number'])), 'تمت تسوية الذمة بنجاح.');
    }

    /** DELETE /api/v1/dashboard/dues/{due} */
    public function destroy(ContractorDue $due)
    {
        $due->delete();

        $this->financeLog('due.deleted', ['due_id' => $due->id, 'contractor_id' => $due->contractor_id]);

        return $this->success(message: 'تم حذف الذمة.');
    }

    private function validateFeeRequest(Request $request): array
    {
        return $request->validate([
            'year'             => 'required|integer|between:1990,2100',
            'discount_type'    => 'nullable|in:percent,fixed',
            'discount_value'   => 'required_with:discount_type|nullable|numeric|min:0.01',
            'discount_reason'  => 'nullable|string|max:255',
        ]);
    }

    /** يطبّق خصماً اختيارياً على مبلغ إجمالي (معاينة فقط، لا يكتب شيء) */
    private function previewDiscount(float $total, ?string $type, ?float $value): float
    {
        if (! $type || ! $value) {
            return $total;
        }

        $discounted = $type === 'percent' ? $total * (1 - $value / 100) : $total - $value;

        return round(max(0, $discounted), 2);
    }

    /**
     * POST /api/v1/dashboard/contractors/{contractor}/dues/calculate-fee
     * معاينة احتساب رسوم العضوية السنوية لمقاول — بدون كتابة أي شيء.
     */
    public function calculateFee(Request $request, \App\Models\Contractor $contractor)
    {
        $data = $this->validateFeeRequest($request);

        $breakdown = app(\App\Services\MembershipFeeCalculator::class)->calculate($contractor, $data['year']);

        if ($breakdown['unresolvable']) {
            return $this->error(
                'تعذّر احتساب الرسوم — يوجد تخصص بدرجة غير موحَّدة على هذا المقاول. شغّل أمر contractor:normalize-specialty-grades أولاً.',
                422,
                ['breakdown' => $breakdown],
            );
        }

        $total = $breakdown['total_before_discount_jod'];
        $final = $this->previewDiscount($total, $data['discount_type'] ?? null, $data['discount_value'] ?? null);

        return $this->success([
            'breakdown'                 => $breakdown,
            'total_before_discount_jod' => $total,
            'discount_amount_jod'       => round($total - $final, 2),
            'total_after_discount_jod'  => $final,
        ]);
    }

    /**
     * POST /api/v1/dashboard/contractors/{contractor}/dues/generate-fee
     * يولّد (أو يحدّث مع force=true) ذمة رسوم عضوية لمقاول عن سنة معيَّنة.
     */
    public function generateFee(Request $request, \App\Models\Contractor $contractor)
    {
        $data  = $this->validateFeeRequest($request);
        $force = $request->boolean('force');

        $breakdown = app(\App\Services\MembershipFeeCalculator::class)->calculate($contractor, $data['year']);

        if ($breakdown['unresolvable']) {
            return $this->error(
                'تعذّر احتساب الرسوم — يوجد تخصص بدرجة غير موحَّدة على هذا المقاول. شغّل أمر contractor:normalize-specialty-grades أولاً.',
                422,
                ['breakdown' => $breakdown],
            );
        }

        $existingFeeEngine = ContractorDue::where('contractor_id', $contractor->id)
            ->where('year', $data['year'])
            ->where('source', 'fee_engine')
            ->first();

        if ($existingFeeEngine && ! $force) {
            return $this->error(
                'يوجد بالفعل ذمة رسوم مُولَّدة لهذا المقاول عن هذه السنة.',
                409,
                ['existing_due_id' => $existingFeeEngine->id],
            );
        }

        $total = $breakdown['total_before_discount_jod'];
        $final = $this->previewDiscount($total, $data['discount_type'] ?? null, $data['discount_value'] ?? null);

        $attributes = [
            'contractor_id'        => $contractor->id,
            'year'                 => $data['year'],
            'description'          => "رسوم اشتراك سنة {$data['year']} (محرّك الاحتساب الآلي)",
            'amount_jod'           => $final,
            'original_amount_jod'  => $total !== $final ? $total : null,
            'discount_type'        => $data['discount_type'] ?? null,
            'discount_value'       => $data['discount_value'] ?? null,
            'discount_amount_jod'  => $total !== $final ? round($total - $final, 2) : null,
            'discount_reason'      => $data['discount_reason'] ?? null,
            'discount_by'          => isset($data['discount_type']) ? Auth::id() : null,
            'fee_breakdown'        => $breakdown,
            'source'               => 'fee_engine',
        ];

        if ($existingFeeEngine) {
            $existingFeeEngine->update($attributes);
            $existingFeeEngine->applyPayment(0);
            $due = $existingFeeEngine->fresh(['contractor:id,name,membership_number']);
        } else {
            $attributes['created_by'] = Auth::id();
            $due = ContractorDue::create($attributes);
            $due->update(['reference_number' => ContractorDue::generateReferenceNumber($due)]);
            $due = $due->fresh(['contractor:id,name,membership_number']);
        }

        $otherExisting = ContractorDue::where('contractor_id', $contractor->id)
            ->where('year', $data['year'])
            ->where('source', '!=', 'fee_engine')
            ->exists();

        $this->financeLog('due.fee_generated', ['due_id' => $due->id, 'contractor_id' => $contractor->id, 'year' => $data['year'], 'force' => $force]);

        return $this->success([
            'due'     => $this->format($due),
            'warning' => $otherExisting ? 'يوجد بالفعل ذمة أخرى (غير محرّك الاحتساب) لنفس المقاول والسنة — تحقّق من عدم ازدواج الرسوم.' : null,
        ], 'تم توليد ذمة الرسوم بنجاح.');
    }

    /**
     * POST /api/v1/dashboard/dues/generate-fee/bulk
     * توليد رسوم العضوية لعدة مقاولين دفعة واحدة (أو للكل)، مع معاينة dry_run قبل الالتزام.
     */
    public function generateFeeBulk(Request $request)
    {
        $data = $request->validate([
            'year'            => 'required|integer|between:1990,2100',
            'contractor_ids'  => 'nullable|array',
            'contractor_ids.*' => 'integer|exists:contractors,id',
            'dry_run'         => 'boolean',
        ]);

        $query = \App\Models\Contractor::query()->whereNotNull('specialties');
        if (! empty($data['contractor_ids'])) {
            $query->whereIn('id', $data['contractor_ids']);
        }

        $wouldCreate = [];
        $wouldSkipExisting = [];
        $unresolvable = [];
        $created = 0;
        $dryRun = $request->boolean('dry_run');

        $query->chunkById(100, function ($contractors) use ($data, $dryRun, &$wouldCreate, &$wouldSkipExisting, &$unresolvable, &$created) {
            foreach ($contractors as $contractor) {
                $exists = ContractorDue::where('contractor_id', $contractor->id)
                    ->where('year', $data['year'])
                    ->where('source', 'fee_engine')
                    ->exists();

                if ($exists) {
                    $wouldSkipExisting[] = ['contractor_id' => $contractor->id, 'name' => $contractor->name];
                    continue;
                }

                $breakdown = app(\App\Services\MembershipFeeCalculator::class)->calculate($contractor, $data['year']);

                if ($breakdown['unresolvable']) {
                    $unresolvable[] = ['contractor_id' => $contractor->id, 'name' => $contractor->name];
                    continue;
                }

                if ($dryRun) {
                    $wouldCreate[] = ['contractor_id' => $contractor->id, 'name' => $contractor->name, 'total_jod' => $breakdown['total_before_discount_jod']];
                    continue;
                }

                $due = ContractorDue::create([
                    'contractor_id' => $contractor->id,
                    'year'          => $data['year'],
                    'description'   => "رسوم اشتراك سنة {$data['year']} (محرّك الاحتساب الآلي)",
                    'amount_jod'    => $breakdown['total_before_discount_jod'],
                    'fee_breakdown' => $breakdown,
                    'source'        => 'fee_engine',
                    'created_by'    => Auth::id(),
                ]);
                $due->update(['reference_number' => ContractorDue::generateReferenceNumber($due)]);
                $wouldCreate[] = ['contractor_id' => $contractor->id, 'name' => $contractor->name, 'total_jod' => $breakdown['total_before_discount_jod']];
                $created++;
            }
        });

        $this->financeLog('due.fee_generated_bulk', ['year' => $data['year'], 'created' => $created, 'dry_run' => $dryRun]);

        return $this->success([
            'would_create'        => $wouldCreate,
            'would_skip_existing' => $wouldSkipExisting,
            'unresolvable'        => $unresolvable,
            'created_count'       => $created,
        ]);
    }

    /**
     * POST /api/v1/dashboard/dues/{due}/discount
     * خصم إداري فردي على ذمة قائمة (المادة 37/ت).
     */
    public function applyDiscount(Request $request, ContractorDue $due)
    {
        $data = $request->validate([
            'discount_type'   => 'required|in:percent,fixed',
            'discount_value'  => 'required|numeric|min:0.01',
            'discount_reason' => 'nullable|string|max:255',
        ]);

        try {
            $due->applyDiscount($data['discount_type'], $data['discount_value'], $data['discount_reason'] ?? null, Auth::id());
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 422);
        }

        $this->financeLog('due.discount_applied', [
            'due_id' => $due->id, 'discount_type' => $data['discount_type'], 'discount_value' => $data['discount_value'],
        ]);

        return $this->success($this->format($due->fresh(['contractor:id,name,membership_number'])), 'تم تطبيق الخصم بنجاح.');
    }

    /**
     * POST /api/v1/dashboard/dues/discount/bulk
     * خصم إداري جماعي (المادة 37/ت) — اختيار صريح بمعرّفات الذمم أو بمعايير (سنة/حالة/مصدر).
     */
    public function applyDiscountBulk(Request $request)
    {
        $data = $request->validate([
            'mode'                => 'required|in:ids,criteria',
            'ids'                 => 'required_if:mode,ids|array|max:200',
            'ids.*'               => 'integer|exists:contractor_dues,id',
            'criteria'            => 'required_if:mode,criteria|array',
            'criteria.year'       => 'nullable|integer|between:1990,2100',
            'criteria.status'     => 'nullable|in:unpaid,partially_paid,paid',
            'criteria.source'     => 'nullable|in:legacy_import,manual,fee_engine',
            'discount_type'       => 'required|in:percent,fixed',
            'discount_value'      => 'required|numeric|min:0.01',
            'discount_reason'     => 'nullable|string|max:255',
            'dry_run'             => 'boolean',
        ]);

        $query = ContractorDue::query();

        if ($data['mode'] === 'ids') {
            $query->whereIn('id', $data['ids']);
        } else {
            $c = $data['criteria'];
            if (! empty($c['year'])) {
                $query->where('year', $c['year']);
            }
            if (! empty($c['status'])) {
                $query->where('status', $c['status']);
            }
            if (! empty($c['source'])) {
                $query->where('source', $c['source']);
            }
        }

        $dues = $query->get();

        if ($request->boolean('dry_run')) {
            $impact = $dues->sum(function ($due) use ($data) {
                $original = (float) ($due->original_amount_jod ?? $due->amount_jod);
                $newAmount = $data['discount_type'] === 'percent'
                    ? $original * (1 - $data['discount_value'] / 100)
                    : $original - $data['discount_value'];

                return round($original - max(0, $newAmount), 2);
            });

            return $this->success([
                'matched_count'            => $dues->count(),
                'total_discount_impact_jod' => round($impact, 2),
            ]);
        }

        $applied = 0;
        $skipped = [];

        foreach ($dues as $due) {
            try {
                $due->applyDiscount($data['discount_type'], $data['discount_value'], $data['discount_reason'] ?? null, Auth::id());
                $applied++;
            } catch (\InvalidArgumentException $e) {
                $skipped[] = ['due_id' => $due->id, 'reason' => $e->getMessage()];
            }
        }

        $this->financeLog('due.discount_applied_bulk', [
            'mode' => $data['mode'], 'matched' => $dues->count(), 'applied' => $applied, 'skipped' => count($skipped),
        ]);

        return $this->success([
            'matched_count' => $dues->count(),
            'applied_count' => $applied,
            'skipped'       => $skipped,
        ], 'تم تطبيق الخصم الجماعي بنجاح.');
    }
}

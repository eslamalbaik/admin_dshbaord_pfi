<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Models\BankAccount;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class BankAccountController extends Controller
{
    use ApiResponseTrait;

    /** تنسيق حساب بنكي لاستجابة الـ API */
    private function format(BankAccount $b): array
    {
        return [
            'id'             => $b->id,
            'bank_name'      => $b->bank_name,
            'bank_name_en'   => $b->bank_name_en,
            'currency'       => $b->currency,
            'logo_url'       => $b->logo_url,
            'iban'           => $b->iban,
            'account_number' => $b->account_number,
            'account_holder' => $b->account_holder,
            'swift'          => $b->swift,
            'notes'          => $b->notes,
            'is_active'      => $b->is_active,
            'sort'           => $b->sort,
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Contractor Mobile App — الحسابات البنكية الظاهرة في شاشة الدفع (Public)
    //  GET /api/v1/bank-accounts
    //  كل حساب فعّال بطاقة مستقلة (نفس عدد وترتيب payment_info.bank_accounts في app/settings).
    //  كانت مجمَّعة حسب اسم البنك، فحسابان بنفس الاسم (مثلاً عملتين) يصيروا بطاقة واحدة
    //  والتطبيق يعرض أول إيبان بس — فيبان كأن الحساب الرابع "مخفي".
    //  accounts[] باقية (عنصر واحد) حتى ما ينكسر شكل الـ JSON عند التطبيق.
    // ─────────────────────────────────────────────────────────────────────────
    public function publicIndex()
    {
        $banks = BankAccount::active()->orderBy('sort')->orderBy('id')->get()
            ->map(fn ($r) => [
                'id'             => $r->id,
                'bank_name'      => $r->bank_name,
                'bank_name_en'   => $r->bank_name_en,
                'logo_url'       => $r->logo_url,
                'account_holder' => $r->account_holder,
                'account_number' => $r->account_number,
                'swift'          => $r->swift,
                'notes'          => $r->notes,
                'currency'       => $r->currency,
                'iban'           => $r->iban,
                'accounts'       => [[
                    'id'       => $r->id,
                    'currency' => $r->currency,
                    'iban'     => $r->iban,
                ]],
            ])->values();

        return $this->success(['banks' => $banks]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Admin Dashboard (Protected)
    // ─────────────────────────────────────────────────────────────────────────

    /** GET /api/v1/dashboard/bank-accounts */
    public function index()
    {
        $accounts = BankAccount::orderBy('sort')->orderBy('id')
            ->get()->map(fn ($b) => $this->format($b));

        return $this->success(['bank_accounts' => $accounts->values()]);
    }

    /** POST /api/v1/dashboard/bank-accounts */
    public function store(Request $request)
    {
        $data = $request->validate([
            'bank_name'      => 'required|string|max:255',
            'bank_name_en'   => 'nullable|string|max:255',
            'currency'       => 'nullable|in:ILS,JOD,USD,EUR',
            'iban'           => 'required|string|max:60',
            'account_number' => 'nullable|string|max:60',
            'account_holder' => 'nullable|string|max:255',
            'swift'          => 'nullable|string|max:30',
            'notes'          => 'nullable|string|max:500',
            'is_active'      => 'boolean',
            'sort'           => 'nullable|integer|min:0',
            'logo'           => 'nullable|image|mimes:png,jpg,jpeg,svg,webp|max:2048',
        ]);

        if ($request->hasFile('logo')) {
            $data['logo_path'] = $request->file('logo')->store('bank-logos', 'public');
        }
        unset($data['logo']);

        $account = BankAccount::create($data);

        AuditLogService::record(Auth::user(), 'bank_account.created', $account, ['bank_name' => $account->bank_name, 'currency' => $account->currency]);

        return $this->success($this->format($account), 'تم إضافة الحساب البنكي بنجاح.', 201);
    }

    /** POST /api/v1/dashboard/bank-accounts/{bankAccount} (with _method=PUT for multipart) */
    public function update(Request $request, BankAccount $bankAccount)
    {
        $data = $request->validate([
            'bank_name'      => 'sometimes|required|string|max:255',
            'bank_name_en'   => 'nullable|string|max:255',
            'currency'       => 'nullable|in:ILS,JOD,USD,EUR',
            'iban'           => 'sometimes|required|string|max:60',
            'account_number' => 'nullable|string|max:60',
            'account_holder' => 'nullable|string|max:255',
            'swift'          => 'nullable|string|max:30',
            'notes'          => 'nullable|string|max:500',
            'is_active'      => 'boolean',
            'sort'           => 'nullable|integer|min:0',
            'logo'           => 'nullable|image|mimes:png,jpg,jpeg,svg,webp|max:2048',
        ]);

        if ($request->hasFile('logo')) {
            if ($bankAccount->logo_path) {
                Storage::disk('public')->delete($bankAccount->logo_path);
            }
            $data['logo_path'] = $request->file('logo')->store('bank-logos', 'public');
        }
        unset($data['logo']);

        $bankAccount->update($data);

        AuditLogService::record(Auth::user(), 'bank_account.updated', $bankAccount, ['bank_name' => $bankAccount->bank_name]);

        return $this->success($this->format($bankAccount->fresh()), 'تم تحديث الحساب البنكي بنجاح.');
    }

    /** DELETE /api/v1/dashboard/bank-accounts/{bankAccount} */
    public function destroy(BankAccount $bankAccount)
    {
        AuditLogService::record(Auth::user(), 'bank_account.deleted', $bankAccount, ['bank_name' => $bankAccount->bank_name]);

        if ($bankAccount->logo_path) {
            Storage::disk('public')->delete($bankAccount->logo_path);
        }
        $bankAccount->delete();

        return $this->success(message: 'تم حذف الحساب البنكي بنجاح.');
    }
}

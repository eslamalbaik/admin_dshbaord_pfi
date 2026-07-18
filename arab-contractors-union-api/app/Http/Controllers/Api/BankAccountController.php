<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Models\BankAccount;
use Illuminate\Http\Request;
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
    // ─────────────────────────────────────────────────────────────────────────
    public function publicIndex()
    {
        $accounts = BankAccount::active()
            ->orderBy('sort')->orderBy('id')
            ->get()
            ->map(fn ($b) => $this->format($b));

        return $this->success(['bank_accounts' => $accounts->values()]);
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

        return $this->success($this->format($account), 'تم إضافة الحساب البنكي بنجاح.', 201);
    }

    /** POST /api/v1/dashboard/bank-accounts/{bankAccount} (with _method=PUT for multipart) */
    public function update(Request $request, BankAccount $bankAccount)
    {
        $data = $request->validate([
            'bank_name'      => 'sometimes|required|string|max:255',
            'bank_name_en'   => 'nullable|string|max:255',
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

        return $this->success($this->format($bankAccount->fresh()), 'تم تحديث الحساب البنكي بنجاح.');
    }

    /** DELETE /api/v1/dashboard/bank-accounts/{bankAccount} */
    public function destroy(BankAccount $bankAccount)
    {
        if ($bankAccount->logo_path) {
            Storage::disk('public')->delete($bankAccount->logo_path);
        }
        $bankAccount->delete();

        return $this->success(message: 'تم حذف الحساب البنكي بنجاح.');
    }
}

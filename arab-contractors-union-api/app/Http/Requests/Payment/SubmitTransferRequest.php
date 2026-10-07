<?php

namespace App\Http\Requests\Payment;

use Illuminate\Foundation\Http\FormRequest;

class SubmitTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * الملاحظات: بعض نسخ التطبيق بتبعتها باسم ثاني (note/description/comment/remarks)، فكانت
     * توصل null — بنوحّدها على notes. ونوع الدفعة بيتوحّد على Payment::APP_TYPES (أو null).
     */
    protected function prepareForValidation(): void
    {
        $merge = [];

        if (blank($this->input('notes'))) {
            foreach (['note', 'description', 'comment', 'comments', 'remarks', 'notice'] as $alias) {
                if (filled($this->input($alias))) {
                    $merge['notes'] = $this->input($alias);
                    break;
                }
            }
        }

        if ($this->has('type')) {
            $merge['type'] = \App\Models\Payment::normalizeAppType($this->input('type'));
        }

        if ($merge) {
            $this->merge($merge);
        }
    }

    public function rules(): array
    {
        return [
            'amount'           => 'required|numeric|min:1',
            'currency'         => 'nullable|in:JOD,ILS,USD',
            'receipt_image'    => 'required|file|mimes:png,jpg,jpeg,webp,pdf|max:5120',
            'bank_account_id'  => 'nullable|exists:bank_accounts,id',
            'membership_id'    => 'nullable|exists:memberships,id',
            'equipment_package_id' => 'nullable|exists:equipment_packages,id',
            // الذمة اللي انضغط عليها "ادفع الآن" — بتفرض type=dues_payment
            'contractor_due_id' => 'nullable|integer|exists:contractor_dues,id',
            // الغرامة اللي انضغط عليها "ادفع الآن" — بتفرض type=penalty_payment
            'penalty_id'       => 'nullable|integer|exists:penalties,id',
            'reference_number' => 'nullable|string|max:100',
            'notes'            => 'nullable|string|max:500',
            // سداد ذمة / رسوم اشتراك / دفع غرامة / دفعة مقدمة (+ اشتراك باقة المعدات)؛ غير هيك بيتجاهل
            'type'             => 'nullable|string|in:dues_payment,membership_fee,penalty_payment,advance_payment,equipment_subscription',
        ];
    }
}

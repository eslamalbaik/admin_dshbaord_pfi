<?php

namespace App\Http\Requests\ContractorDue;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class ApplyDiscountBulkRequest extends FormRequest
{
    /** المعايير التي يُقبَل حصر الخصم الجماعي بأيٍّ منها. */
    private const CRITERIA_KEYS = ['year', 'status', 'source', 'contractor_ids'];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'mode'                    => 'required|in:ids,criteria',
            'ids'                     => 'required_if:mode,ids|array|max:200',
            'ids.*'                   => 'integer|exists:contractor_dues,id',
            'criteria'                => 'required_if:mode,criteria|array',
            'criteria.year'           => 'nullable|integer|between:1990,2100',
            // "paid" محذوفة عمداً: الذمة المسدَّدة بالكامل لا يمكن خصمها، فحصر الخصم بها
            // لا يُنتج إلا قائمة متخطّاة — رفضها بالتحقق أصدق من إرجاع صفر بلا تفسير.
            'criteria.status'         => 'nullable|in:unpaid,partially_paid',
            'criteria.source'         => 'nullable|in:legacy_import,manual,fee_engine',
            'criteria.contractor_ids' => 'nullable|array|max:200',
            'criteria.contractor_ids.*' => 'integer|exists:contractors,id',
            'discount_type'           => 'required|in:percent,fixed',
            'discount_value'          => 'required|numeric|min:0.01',
            'discount_reason'         => 'nullable|string|max:255',
            'dry_run'                 => 'boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'criteria.status.in' => 'لا يمكن حصر الخصم الجماعي بالذمم المسدَّدة بالكامل — لا خصم يُطبَّق عليها.',
        ];
    }

    /**
     * مود «معايير» بكائن معايير فارغ كان يمرّ بالتحقق، فيتخطّى DuesDiscountService كل شرط
     * ويطابق **كل ذمة في قاعدة البيانات** — خصم 100% بلا معايير كان يصفّر ذمم كل المقاولين
     * دفعة واحدة (TASK-17 #10). معيار واحد على الأقل صار إلزامياً.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($this->input('mode') !== 'criteria') {
                return;
            }

            $criteria = (array) $this->input('criteria', []);

            $hasAny = collect(self::CRITERIA_KEYS)
                ->contains(fn ($key) => ! empty($criteria[$key]));

            if (! $hasAny) {
                $validator->errors()->add(
                    'criteria',
                    'يجب تحديد معيار واحد على الأقل (السنة أو الحالة أو المصدر أو المقاولين) — الخصم الجماعي بلا معايير يشمل كل ذمم النظام.',
                );
            }
        });
    }
}

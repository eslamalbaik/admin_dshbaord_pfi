<?php

namespace App\Http\Requests\ContractorDue;

use Illuminate\Foundation\Http\FormRequest;

class StoreContractorDueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * نوع الذمة يحدّده المستخدم صراحةً أول الفورم (Trello #13): «جديدة» أو «قديمة». العملاء
     * الأقدم يرسلون allow_backdate فقط، فيُترجم: true = قديمة، غيابه = جديدة.
     */
    protected function prepareForValidation(): void
    {
        if (! $this->filled('due_kind')) {
            $this->merge(['due_kind' => $this->boolean('allow_backdate') ? 'old' : 'new']);
        }
        if ($this->input('due_kind') === 'old') {
            $this->merge(['allow_backdate' => true]);
        }
    }

    public function rules(): array
    {
        // «اليوم» بتوقيت غزة، لا UTC — وإلا بين منتصف الليل و3 الصبح يُرفض تاريخ بكرة الصحيح.
        $today = now(config('app.local_timezone'))->toDateString();

        return [
            'contractor_id' => 'required|exists:contractors,id',
            'description'   => 'required|string|max:500',
            'amount_jod'    => 'required|numeric|min:0.01|max:99999999',
            // السنة وتاريخ الاستحقاق إلزاميان: الذمة تظهر للمقاول بتاريخ استحقاقها، وبدونه لا تُحسب متأخّرة أبداً.
            'year'          => 'required|integer|between:1990,2100',
            'period'        => 'nullable|string|max:50',

            // الذمة الجديدة تاريخها من بكرة وما بعد، والقديمة تاريخها اليوم أو قبله (Trello #13).
            // القديمة قرار واعٍ بسبب مكتوب لا تاريخاً ماضياً مرَّ بالخطأ (TASK-17 #7).
            'due_kind'      => 'required|in:old,new',
            'due_date'      => $this->input('due_kind') === 'old'
                ? ['required', 'date', 'before_or_equal:' . $today]
                : ['required', 'date', 'after:' . $today],
            'allow_backdate'  => 'boolean',
            'backdate_reason' => 'required_if:allow_backdate,1,true|nullable|string|max:255',

            'notes'         => 'nullable|string|max:2000',
        ];
    }

    public function messages(): array
    {
        return [
            'amount_jod.min'          => 'مبلغ الذمة لازم يكون أكبر من صفر.',
            'year.required'           => 'السنة إلزامية.',
            'year.between'  => 'السنة لازم تكون بين 1990 و2100 (مثلاً 2026).',
            'year.integer'  => 'السنة لازم تكون رقم من 4 خانات (مثلاً 2026).',
            'due_date.required'       => 'تاريخ الاستحقاق إلزامي.',
            'due_kind.in'              => 'نوع الذمة لازم يكون «قديمة» أو «جديدة».',
            'due_date.after'           => 'تاريخ استحقاق الذمة الجديدة لازم يكون من بكرة وما بعد. لتسجيل ذمة بتاريخ سابق، اختر «ذمة قديمة» واذكر السبب.',
            'due_date.before_or_equal' => 'الذمة القديمة تاريخها اليوم أو قبله. لتاريخ قادم اختر «ذمة جديدة».',
            'backdate_reason.required_if' => 'سبب التاريخ السابق إلزامي عند تسجيل ذمة متأخّرة.',
        ];
    }
}

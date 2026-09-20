<?php

namespace App\Http\Requests;

use App\Models\PoVarcost;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PoVarcostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()?->hasRole('admin') === true;
    }

    public function rules(): array
    {
        $po = $this->route('po_varcost');

        return [
            'po_number' => [
                'required',
                'string',
                'max:255',
                Rule::unique('po_varcost', 'po_number')->ignore($po?->id),
            ],
            'expense_type' => ['required', Rule::in(PoVarcost::EXPENSE_TYPES)],
            'description' => ['required', 'string'],
            'gr_status' => ['nullable', 'string', 'max:255'],
            'po_year' => ['required', 'integer', 'between:1900,2200'],
            'delivery_date' => ['nullable', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'po_number.unique' => 'PO Number tersebut sudah terdaftar.',
            'description.required' => 'Description wajib diisi.',
            'expense_type.in' => 'Capex/Opex harus Capex atau Opex.',
        ];
    }
}

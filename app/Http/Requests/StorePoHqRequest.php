<?php

namespace App\Http\Requests;

use App\Models\PoHq;
use Illuminate\Foundation\Http\FormRequest;

class StorePoHqRequest extends FormRequest
{
    /**
     * Mutasi PO hanya untuk role admin (lapis kedua setelah middleware route).
     */
    public function authorize(): bool
    {
        return $this->user()?->hasRole('admin') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'po_number' => ['required', 'string', 'max:100', 'unique:po_hq,po_number'],
            'agreement_number' => ['nullable', 'string', 'max:100'],
            'vendor_name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'expense_type' => ['required', 'in:'.implode(',', PoHq::EXPENSE_TYPES)],
            'status' => ['required', 'in:'.implode(',', PoHq::STATUSES)],
            'location' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'po_number.required' => 'PO Number wajib diisi.',
            'po_number.unique' => 'PO Number sudah terdaftar.',
            'vendor_name.required' => 'Vendor Name wajib diisi.',
            'expense_type.required' => 'Pilih Capex atau Opex.',
            'expense_type.in' => 'Tipe biaya harus Capex atau Opex.',
            'status.required' => 'Status wajib dipilih.',
            'status.in' => 'Status tidak dikenal.',
        ];
    }
}

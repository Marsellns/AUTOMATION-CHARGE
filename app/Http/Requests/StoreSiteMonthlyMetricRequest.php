<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSiteMonthlyMetricRequest extends FormRequest
{
    /**
     * Authorization terpisah (middleware/auth) akan diatur saat controller dibuat.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Rules untuk input manual data bulanan site.
     *
     * - revenue & cost wajib angka >= 0
     * - profit_loss DILARANG diinput: selalu dihitung otomatis oleh model
     *   (event saving: revenue - cost)
     */
    public function rules(): array
    {
        return [
            'site_id' => [
                'required',
                Rule::exists('sites', 'id'),
            ],
            'bulan' => ['required', 'integer', 'between:1,12'],
            'tahun' => ['required', 'integer', 'between:2000,2100'],
            'revenue' => ['required', 'numeric', 'min:0'],
            'cost' => ['required', 'numeric', 'min:0'],
            'profit_loss' => ['prohibited'],
        ];
    }

    public function messages(): array
    {
        return [
            'revenue.min' => 'Revenue tidak boleh negatif.',
            'cost.min' => 'Cost tidak boleh negatif.',
            'profit_loss.prohibited' => 'profit_loss tidak boleh diinput manual; nilai dihitung otomatis (revenue - cost).',
        ];
    }
}

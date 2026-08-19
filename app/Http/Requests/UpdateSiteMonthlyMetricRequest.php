<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSiteMonthlyMetricRequest extends FormRequest
{
    /**
     * Authorization terpisah (middleware/auth) akan diatur saat controller dibuat.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Rules untuk update manual data bulanan site (mendukung PATCH parsial).
     *
     * - revenue & cost (jika dikirim) wajib angka >= 0
     * - profit_loss DILARANG diinput: selalu dihitung otomatis oleh model
     *   (event saving: revenue - cost)
     * - kombinasi (site_id, bulan, tahun) tetap harus unik
     */
    public function rules(): array
    {
        return [
            'site_id' => [
                'sometimes', 'required',
                Rule::exists('sites', 'id'),
            ],
            'bulan' => ['sometimes', 'required', 'integer', 'between:1,12'],
            'tahun' => ['sometimes', 'required', 'integer', 'between:2000,2100'],
            'revenue' => ['sometimes', 'required', 'numeric', 'min:0'],
            'cost' => ['sometimes', 'required', 'numeric', 'min:0'],
            'profit_loss' => ['prohibited'],
        ];
    }

    /**
     * Validasi unik (site_id, bulan, tahun) kecuali record yang sedang di-update.
     * Dipanggil setelah field di-merge agar mendukung PATCH parsial.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            /** @var \App\Models\SiteMonthlyMetric|null $metric */
            $metric = $this->route('site_monthly_metric');
            if (! $metric instanceof \App\Models\SiteMonthlyMetric) {
                return;
            }

            $siteId = $this->input('site_id', $metric->site_id);
            $bulan = $this->input('bulan', $metric->bulan);
            $tahun = $this->input('tahun', $metric->tahun);

            $exists = \App\Models\SiteMonthlyMetric::where('site_id', $siteId)
                ->where('bulan', $bulan)
                ->where('tahun', $tahun)
                ->whereKeyNot($metric->id)
                ->exists();

            if ($exists) {
                $validator->errors()->add(
                    'bulan',
                    'Data untuk site, bulan, dan tahun tersebut sudah ada.'
                );
            }
        });
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

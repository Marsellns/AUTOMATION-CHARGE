<?php

namespace App\Http\Requests;

use App\Models\SewaLahanRenewal;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSewaLahanRenewalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // route-level middleware handles role check
    }

    public function rules(): array
    {
        return [
            'site_code'           => ['required', 'string', 'max:30'],
            'site_name'           => ['nullable', 'string', 'max:255'],
            'tahun_renewal'       => ['nullable', 'integer', 'min:2000', 'max:2099'],
            'status_dokumen'      => ['nullable', Rule::in(SewaLahanRenewal::STATUS_DOKUMEN)],
            'status_perpanjangan' => ['nullable', 'string', 'max:100'],
            'no_pks_baru'         => ['nullable', 'string', 'max:150'],
            'start_date_baru'     => ['nullable', 'date'],
            'end_date_baru'       => ['nullable', 'date', 'after_or_equal:start_date_baru'],
            'harga_baru'          => ['nullable', 'numeric', 'min:0'],
            'total_harga_baru'    => ['nullable', 'numeric', 'min:0'],
            'no_bak_baru'         => ['nullable', 'string', 'max:150'],
            'tgl_bak_baru'        => ['nullable', 'date'],
            'no_sip'              => ['nullable', 'string', 'max:150'],
            'tgl_terima_sip'      => ['nullable', 'date'],
            'penawaran_1'         => ['nullable', 'numeric', 'min:0'],
            'nego_1'              => ['nullable', 'numeric', 'min:0'],
            'penawaran_2'         => ['nullable', 'numeric', 'min:0'],
            'nego_2'              => ['nullable', 'numeric', 'min:0'],
            'penawaran_3'         => ['nullable', 'numeric', 'min:0'],
            'nego_3'              => ['nullable', 'numeric', 'min:0'],
            'no_pks_lama'         => ['nullable', 'string', 'max:150'],
            'start_date_lama'     => ['nullable', 'date'],
            'end_date_lama'       => ['nullable', 'date', 'after_or_equal:start_date_lama'],
            'harga_lama'          => ['nullable', 'numeric', 'min:0'],
            'keterangan'          => ['nullable', 'string'],
        ];
    }
}

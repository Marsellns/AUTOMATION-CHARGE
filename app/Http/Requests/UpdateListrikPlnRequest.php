<?php

namespace App\Http\Requests;

use App\Models\ListrikPln;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateListrikPlnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // route-level middleware handles role check
    }

    public function rules(): array
    {
        return [
            'id_pelanggan'         => ['required', 'string', 'max:50'],
            'site_id'              => ['nullable', 'string', 'max:50'],
            'site_name'            => ['nullable', 'string', 'max:255'],
            'alamat'               => ['nullable', 'string'],
            'status_aktif_site'    => ['nullable', Rule::in(ListrikPln::STATUS_AKTIF_SITE)],
            'nama_pelanggan'       => ['nullable', 'string', 'max:255'],
            'daya_va'              => ['nullable', 'integer', 'min:0'],
            'phasa'                => ['nullable', Rule::in(ListrikPln::PHASA)],
            'status_amr'           => ['nullable', Rule::in(ListrikPln::STATUS_AMR)],
            'gol_tarif'            => ['nullable', 'string', 'max:100'],
            'unit_layanan_pln'     => ['nullable', 'string', 'max:100'],
            'jenis_bayar'          => ['nullable', Rule::in(ListrikPln::JENIS_BAYAR)],
            'tp_owner'             => ['nullable', 'string', 'max:100'],
            'nop'                  => ['nullable', 'string', 'max:50'],
        ];
    }
}
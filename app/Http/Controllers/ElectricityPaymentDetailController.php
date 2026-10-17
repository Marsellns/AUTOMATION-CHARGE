<?php

namespace App\Http\Controllers;

use App\Services\ElectricityPaymentComparisonService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ElectricityPaymentDetailController extends Controller
{
    public function __invoke(Request $request, ElectricityPaymentComparisonService $payments): JsonResponse
    {
        $data = $request->validate([
            'source' => ['required', 'in:macro,inbuilding'],
            'tahun' => ['required', 'integer', 'between:2000,2100'],
            'bulan' => ['required', 'integer', 'between:1,12'],
            'metric' => ['required', 'in:sites,amount,billing'],
            'nop' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1', 'max:100000'],
        ]);

        return response()->json($payments->details($data['source'], (int) $data['tahun'], (int) $data['bulan'], $data['metric'], $data['nop'] ?? null, (int) ($data['page'] ?? 1)));
    }
}

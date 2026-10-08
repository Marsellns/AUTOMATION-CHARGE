<?php

namespace App\Http\Controllers;

use App\Exports\EquipmentRelocationExport;
use App\Imports\EquipmentRelocationImport;
use App\Models\EquipmentRelocation;
use App\Models\EquipmentRelocationInventory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EquipmentRelocationController extends Controller
{
    private const INVENTORY_COLUMNS = [
        'uniq_key', 'site_id', 'nop', 'region', 'to_name', 'ne_name',
        'equipment_group', 'equipment_type', 'category', 'board_name',
        'board_type', 'serial_number', 'utilization_status', 'safe_to_reloc',
    ];

    public function index(): View
    {
        return view('equipment-relocation.index');
    }

    public function inventoryData(): StreamedResponse
    {
        return response()->stream(function (): void {
            echo '{"schema":1,"columns":'.json_encode(self::INVENTORY_COLUMNS).',"rows":[';
            $first = true;

            DB::table('equipment_relocation_inventory')
                ->select(['id', ...self::INVENTORY_COLUMNS])
                ->orderBy('id')
                ->chunkById(500, function ($records) use (&$first): void {
                    foreach ($records as $record) {
                        if (!$first) {
                            echo ',';
                        }

                        $values = [];
                        foreach (self::INVENTORY_COLUMNS as $column) {
                            $values[] = (string) $record->{$column};
                        }
                        echo json_encode($values, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
                        $first = false;
                    }
                });

            echo ']}';
        }, 200, [
            'Content-Type' => 'application/json; charset=UTF-8',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function relocationData(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => EquipmentRelocation::query()->latest('id')->get(),
        ]);
    }

    public function exportExcel(): BinaryFileResponse
    {
        return Excel::download(new EquipmentRelocationExport(), 'equipment-relocation.xlsx');
    }

    public function importExcel(Request $request): RedirectResponse
    {
        abort_unless($this->canEditRelocation(), 403, 'Role Anda hanya memiliki akses baca untuk Equipment Relocation.');

        $validated = $request->validate([
            'relocation_file' => ['required', 'file', 'mimes:xls,xlsx', 'max:10240'],
        ], [
            'relocation_file.mimes' => 'File harus berformat Excel (.xls atau .xlsx).',
            'relocation_file.max' => 'Ukuran file Excel maksimal 10 MB.',
        ]);

        try {
            $import = new EquipmentRelocationImport();
            Excel::import($import, $validated['relocation_file']);

            $now = now();
            $userId = $request->user()?->id;
            $rows = array_map(static fn (array $record) => [
                ...$record,
                'created_by' => $userId,
                'updated_by' => $userId,
                'created_at' => $now,
                'updated_at' => $now,
            ], $import->records());

            DB::transaction(static function () use ($rows): void {
                DB::table('equipment_relocations')->upsert(
                    $rows,
                    ['donor_uniq_key'],
                    [
                        'donor_acceptor',
                        'site_target_source',
                        'pic',
                        'progress',
                        'remark',
                        'updated_by',
                        'updated_at',
                    ]
                );
            });

            return to_route('equipment-relocation.index')->with(
                'success',
                'Upload Excel berhasil. '.count($rows).' data Equipment Relocation diproses.'
            );
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors())->withInput();
        } catch (\Throwable $exception) {
            report($exception);

            return back()->withErrors([
                'relocation_file' => 'File Excel gagal diproses. Pastikan format dan kolomnya sesuai file hasil Download Excel.',
            ])->withInput();
        }
    }

    public function saveRelocation(Request $request): JsonResponse
    {
        abort_unless($this->canEditRelocation(), 403, 'Role Anda hanya memiliki akses baca untuk Equipment Relocation.');
        $data = $request->validate([
            'donor_uniq_key' => ['required', 'string', 'max:191'],
            'donor_acceptor' => ['nullable', 'string', 'max:255'],
            'site_target_source' => ['nullable', 'string', 'max:255'],
            'pic' => ['nullable', 'string', Rule::in(['NOP BOGOR', 'NOP BEKASI', 'NOP KARAWANG', 'NBAE'])],
            'progress' => ['nullable', 'string', Rule::in(['NOT YET', 'ON GOING', 'DONE'])],
            'remark' => ['nullable', 'string', 'max:5000'],
        ]);

        // Relokasi hanya boleh memakai equipment yang ada di inventaris MySQL.
        if (!EquipmentRelocationInventory::query()->where('uniq_key', $data['donor_uniq_key'])->exists()) {
            throw ValidationException::withMessages(['donor_uniq_key' => 'Equipment tidak ditemukan dalam inventaris.']);
        }

        $userId = $request->user()?->id;
        $relocation = EquipmentRelocation::firstOrNew(['donor_uniq_key' => $data['donor_uniq_key']]);
        $relocation->fill([...$data, 'updated_by' => $userId]);
        $relocation->created_by ??= $userId;
        $relocation->save();

        return response()->json(['success' => true, 'data' => $relocation]);
    }

    public function deleteRelocation(Request $request): JsonResponse
    {
        abort_unless($this->canEditRelocation(), 403, 'Role Anda hanya memiliki akses baca untuk Equipment Relocation.');
        $data = $request->validate([
            'donor_uniq_key' => ['required', 'string', 'max:191'],
        ]);

        $deleted = EquipmentRelocation::where('donor_uniq_key', $data['donor_uniq_key'])->delete();

        return response()->json(['success' => true, 'deleted' => $deleted > 0]);
    }

    private function canEditRelocation(): bool
    {
        $user = request()->user();
        if (!$user) return false;
        if ($user->hasRole('admin')) return true;

        return collect($user->getRoleNames())
            ->map(fn ($role) => strtolower((string) $role))
            ->contains(fn (string $role) => str_starts_with($role, 'manager_') || str_starts_with($role, 'manager '));
    }

}

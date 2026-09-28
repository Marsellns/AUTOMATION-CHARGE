<?php

namespace App\Http\Controllers;

use App\Exports\EquipmentRelocationExport;
use App\Imports\EquipmentRelocationImport;
use App\Models\EquipmentRelocation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class EquipmentRelocationController extends Controller
{
    public function index(): View
    {
        return view('equipment-relocation.index');
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
            $import = new EquipmentRelocationImport($this->inventorySnapshot());
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

        // Hanya baris yang benar-benar ada dalam snapshot inventaris lokal.
        $encodedKey = json_encode($data['donor_uniq_key'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($encodedKey === false || !str_contains($this->inventorySnapshot(), '['.$encodedKey.',')) {
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

    private function inventorySnapshot(): string
    {
        $inventory = file_get_contents(public_path('data/equipment_relocation_inventory.json'));
        if ($inventory === false) {
            throw new \RuntimeException('Snapshot inventaris Equipment Relocation tidak dapat dibaca.');
        }

        return $inventory;
    }

}

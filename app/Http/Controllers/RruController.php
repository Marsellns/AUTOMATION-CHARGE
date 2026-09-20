<?php

namespace App\Http\Controllers;

use App\Models\EquipmentRelocation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RruController extends Controller
{
    public function index(): View
    {
        return view('rru.index');
    }

    public function relocationData(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => EquipmentRelocation::query()->latest('id')->get(),
        ]);
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
        $inventory = file_get_contents(public_path('data/equipment_relocation_inventory.json'));
        $encodedKey = json_encode($data['donor_uniq_key'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($inventory === false || !str_contains($inventory, '['.$encodedKey.',')) {
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

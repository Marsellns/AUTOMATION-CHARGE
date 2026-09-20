<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class UserApprovalController extends Controller
{
    public function index(): View
    {
        return view('admin.user-approvals', [
            'pendingUsers' => User::query()
                ->where('account_status', 'pending')
                ->with('roles')
                ->latest()
                ->get(),
        ]);
    }

    public function approve(User $user): RedirectResponse
    {
        abort_unless($user->account_status === 'pending', 422, 'Akun tidak sedang menunggu persetujuan.');

        $user->update([
            'account_status' => 'approved',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);
        $user->syncRoles([$user->requested_role ?: 'viewer']);

        return back()->with('status', 'Akun ' . $user->email . ' berhasil disetujui.');
    }

    public function reject(User $user): RedirectResponse
    {
        abort_unless($user->account_status === 'pending', 422, 'Akun tidak sedang menunggu persetujuan.');

        $user->update(['account_status' => 'rejected']);

        return back()->with('status', 'Permintaan akun ' . $user->email . ' ditolak.');
    }
}

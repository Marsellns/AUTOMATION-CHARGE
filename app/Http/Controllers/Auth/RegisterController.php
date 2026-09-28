<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class RegisterController extends Controller
{
    public function showRegistrationForm(): View
    {
        return view('auth.register', [
            'roles' => Role::query()->whereIn('name', ['viewer', 'admin', 'manager_nop', 'manager_sq', 'manager_nos', 'manager_nbae'])->orderBy('name')->pluck('name'),
        ]);
    }

    public function register(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(12)->mixedCase()->numbers()->symbols()],
            'requested_role' => ['required', 'in:viewer,admin,manager_nop,manager_sq,manager_nos,manager_nbae'],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'account_status' => 'pending',
            'requested_role' => $validated['requested_role'],
        ]);

        return redirect()->route('login')->with('status', 'Permintaan akses telah dikirim dan menunggu persetujuan admin.');
    }
}

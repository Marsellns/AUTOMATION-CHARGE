<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountApproved
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user === null || $user->account_status === 'approved') {
            return $next($request);
        }

        // Revocation also applies to sessions created before an account was
        // rejected; checking only during password login leaves them active.
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        $message = 'Akun belum disetujui atau akses akun telah dinonaktifkan.';

        return $request->expectsJson()
            ? response()->json(['message' => $message], 403)
            : redirect()->route('login')->withErrors(['email' => $message]);
    }
}

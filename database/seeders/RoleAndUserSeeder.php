<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Role;

class RoleAndUserSeeder extends Seeder
{
    /**
     * Role dasar modul UI: admin (CRUD penuh) dan viewer (lihat + detail saja).
     * Idempotent — aman dijalankan ulang.
     */
    public function run(): void
    {
        foreach (['admin', 'viewer', 'manager_nop', 'manager_sq', 'manager_nos', 'manager_nbae'] as $roleName) {
            Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
        }

        // Tidak pernah membuat akun demo dengan kredensial yang diketahui umum.
        // Administrator pertama bersifat opsional dan harus dikonfigurasi lewat
        // environment saat provisioning.
        $email = trim((string) env('SIMASTER_INITIAL_ADMIN_EMAIL', ''));
        $password = (string) env('SIMASTER_INITIAL_ADMIN_PASSWORD', '');

        if (($email === '') !== ($password === '')) {
            throw new \RuntimeException('SIMASTER_INITIAL_ADMIN_EMAIL dan SIMASTER_INITIAL_ADMIN_PASSWORD harus diisi bersama-sama.');
        }

        if ($email === '') {
            return;
        }

        Validator::make([
            'email' => $email,
            'password' => $password,
        ], [
            'email' => ['required', 'email'],
            'password' => ['required', Password::min(12)->mixedCase()->numbers()->symbols()],
        ])->validate();

        $admin = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => trim((string) env('SIMASTER_INITIAL_ADMIN_NAME', 'Administrator')) ?: 'Administrator',
                'password' => Hash::make($password),
                'account_status' => 'approved',
                'requested_role' => 'admin',
                'approved_at' => now(),
            ]
        );
        $admin->syncRoles(['admin']);
    }
}

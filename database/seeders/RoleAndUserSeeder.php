<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
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

        $admin = User::firstOrCreate(
            ['email' => 'admin@example.com'],
            ['name' => 'Admin SIMASTER', 'password' => Hash::make('password')]
        );
        $admin->syncRoles(['admin']);

        $viewer = User::firstOrCreate(
            ['email' => 'viewer@example.com'],
            ['name' => 'Viewer SIMASTER', 'password' => Hash::make('password')]
        );
        $viewer->syncRoles(['viewer']);
    }
}

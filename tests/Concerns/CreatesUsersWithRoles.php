<?php

namespace Tests\Concerns;

use App\Models\User;
use Spatie\Permission\Models\Role;

trait CreatesUsersWithRoles
{
    protected function userWithRole(string $role): User
    {
        Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        $user = User::factory()->create(['account_status' => 'approved']);
        $user->assignRole($role);

        return $user;
    }
}

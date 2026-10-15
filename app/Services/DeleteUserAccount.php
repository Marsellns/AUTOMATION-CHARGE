<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class DeleteUserAccount
{
    public function delete(User $actor, User $target): bool
    {
        abort_unless($actor->account_status === 'approved' && $actor->hasRole('admin'), 403);

        return DB::transaction(function () use ($actor, $target): bool {
            $activeAdminIds = User::query()
                ->where('account_status', 'approved')
                ->role('admin')
                ->orderBy('users.id')
                ->lockForUpdate()
                ->pluck('users.id');

            $user = User::query()->lockForUpdate()->findOrFail($target->getKey());

            if ($user->is($actor) || ($activeAdminIds->contains($user->getKey()) && $activeAdminIds->count() === 1)) {
                return false;
            }

            $user->syncRoles([]);
            $user->syncPermissions([]);
            $user->notifications()->delete();
            DB::table('sessions')->where('user_id', $user->getKey())->delete();
            DB::table('password_reset_tokens')->where('email', $user->email)->delete();

            return (bool) $user->delete();
        });
    }
}

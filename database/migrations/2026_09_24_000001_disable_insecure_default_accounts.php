<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

return new class extends Migration
{
    /** Disable only legacy accounts that still use the documented demo password. */
    public function up(): void
    {
        foreach (['admin@example.com', 'viewer@example.com'] as $email) {
            $user = DB::table('users')->where('email', $email)->first(['id', 'password']);

            if ($user !== null && Hash::check('password', $user->password)) {
                DB::table('users')->where('id', $user->id)->update([
                    'account_status' => 'rejected',
                    'remember_token' => null,
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        // An insecure credential must never be restored automatically.
    }
};

<?php

namespace Tests\Feature;

use App\Filament\Pages\UserManagement;
use App\Models\User;
use App\Services\DeleteUserAccount;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Concerns\CreatesUsersWithRoles;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use CreatesUsersWithRoles, RefreshDatabase;

    public function test_only_admin_can_open_user_management(): void
    {
        $this->actingAs($this->userWithRole('viewer'))
            ->get('/report/user-management')
            ->assertForbidden();

        $this->actingAs($this->userWithRole('admin'))
            ->get('/report/user-management')
            ->assertOk()
            ->assertSee('Kelola Pengguna');
    }

    public function test_admin_can_delete_another_user_from_the_table(): void
    {
        $admin = $this->userWithRole('admin');
        $target = $this->userWithRole('viewer');

        DB::table('sessions')->insert([
            'id' => 'deleted-user-session',
            'user_id' => $target->getKey(),
            'payload' => '',
            'last_activity' => time(),
        ]);
        DB::table('password_reset_tokens')->insert([
            'email' => $target->email,
            'token' => 'old-token',
            'created_at' => now(),
        ]);

        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('report'));

        Livewire::test(UserManagement::class)
            ->assertSee($target->email)
            ->callAction(TestAction::make('deleteUser')->table($target))
            ->assertHasNoActionErrors();

        $this->assertModelMissing($target);
        $this->assertDatabaseMissing('sessions', ['id' => 'deleted-user-session']);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $target->email]);
        $this->assertModelExists($admin);
    }

    public function test_admin_cannot_delete_self(): void
    {
        $admin = $this->userWithRole('admin');
        $service = app(DeleteUserAccount::class);

        $this->assertFalse($service->delete($admin, $admin));
        $this->assertModelExists($admin);
    }

    public function test_non_admin_cannot_delete_users_even_when_service_is_called_directly(): void
    {
        $viewer = $this->userWithRole('viewer');
        $target = User::factory()->create(['account_status' => 'approved']);

        try {
            app(DeleteUserAccount::class)->delete($viewer, $target);
            $this->fail('Viewer tidak boleh menghapus pengguna.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }

        $this->assertModelExists($target);
    }
}

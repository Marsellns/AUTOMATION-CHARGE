<?php

namespace App\Filament\Pages;

use App\Http\Controllers\Admin\UserApprovalController;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;

class AccountApprovals extends Page implements HasTable
{
    use InteractsWithTable;

    protected string $view = 'filament.pages.account-approvals';

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function getNavigationLabel(): string
    {
        return 'Persetujuan Akun';
    }

    public static function getNavigationGroup(): string
    {
        return 'Administrasi';
    }

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-user-group';
    }

    public function getTitle(): string
    {
        return 'Persetujuan Akun';
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->account_status === 'approved' && auth()->user()->hasRole('admin');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(User::query()->where('account_status', 'pending'))
            ->columns([
                TextColumn::make('name')->label('Nama')->searchable()->sortable(),
                TextColumn::make('email')->label('Email / Login ID')->searchable(),
                TextColumn::make('requested_role')->label('Role Diminta')->badge(),
                TextColumn::make('created_at')->label('Dibuat')->dateTime('d/m/Y H:i')->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                Action::make('approve')->label('Setujui')->color('success')->requiresConfirmation()
                    ->action(fn (User $record) => $this->decide($record, true)),
                Action::make('reject')->label('Tolak')->color('danger')->requiresConfirmation()
                    ->action(fn (User $record) => $this->decide($record, false)),
            ])
            ->emptyStateHeading('Tidak ada permintaan akun yang menunggu persetujuan');
    }

    private function decide(User $record, bool $approve): void
    {
        abort_unless(static::canAccess(), 403);
        DB::transaction(function () use ($record, $approve): void {
            $user = User::query()->lockForUpdate()->findOrFail($record->getKey());
            $controller = app(UserApprovalController::class);
            $approve ? $controller->approve($user) : $controller->reject($user);
        });
        Notification::make()->title($approve ? 'Akun berhasil disetujui' : 'Permintaan akun ditolak')->success()->send();
    }
}

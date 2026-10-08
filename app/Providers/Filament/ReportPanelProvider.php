<?php

namespace App\Providers\Filament;

use App\Filament\Pages\AccountApprovals;
use App\Filament\Pages\OperationalReport;
use App\Livewire\ReportNotifications;
use App\Http\Middleware\EnsureAccountApproved;
use App\Http\Middleware\RenderReportInFilament;
use App\Filament\Modules\ModuleNavigation;
use App\Filament\Modules\ReportModuleRegistry;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationBuilder;
use Filament\Actions\Action;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Livewire\Livewire;
use Spatie\Permission\Middleware\RoleMiddleware;

class ReportPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('report')
            ->path('report')
            ->login()
            ->authGuard('web')
            ->brandName('SIMAWAR')
            ->colors(['primary' => Color::Red])
            ->maxContentWidth('full')
            ->sidebarCollapsibleOnDesktop()
            ->homeUrl(fn () => route('dashboard'))
            ->plugins(ReportModuleRegistry::plugins())
            ->databaseNotifications(livewireComponent: ReportNotifications::class, isLazy: false)
            ->userMenuItems([
                Action::make('accountApprovals')
                    ->label('Persetujuan Akun')
                    ->icon('heroicon-o-user-group')
                    ->url(fn () => AccountApprovals::getUrl())
                    ->visible(fn () => AccountApprovals::canAccess()),
            ])
            ->navigation(fn (NavigationBuilder $builder) => $builder->groups(ModuleNavigation::groups()))
            ->renderHook('panels::head.end', fn () => view('filament.partials.report-assets'))
            ->middleware([
                EncryptCookies::class, AddQueuedCookiesToResponse::class,
                StartSession::class, AuthenticateSession::class, ShareErrorsFromSession::class,
                VerifyCsrfToken::class, SubstituteBindings::class,
                DisableBladeIconComponents::class, DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([Authenticate::class, EnsureAccountApproved::class], isPersistent: true);
    }

    public function boot(): void
    {
        Livewire::component('simaster.operational-report', OperationalReport::class);
        Livewire::component('report-notifications', ReportNotifications::class);
        Livewire::addPersistentMiddleware([
            EnsureAccountApproved::class, RenderReportInFilament::class, RoleMiddleware::class,
        ]);
    }
}

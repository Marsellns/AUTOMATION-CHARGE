<?php

namespace App\Filament\Modules;

use Filament\Contracts\Plugin;
use Filament\Pages\Page;
use Filament\Panel;
use Livewire\Livewire;

abstract class ReportModulePlugin implements Plugin
{
    /** @return array<class-string<Page>> */
    abstract public function pages(): array;

    public function navigation(): array
    {
        $items = [];
        foreach ($this->pages() as $page) {
            if (is_subclass_of($page, ModuleReportPage::class) && ($item = $page::reportNavigation()) !== null) {
                $items[] = $item;
            }
        }

        return $items;
    }

    public function dashboardGroups(): array
    {
        return [];
    }

    public function register(Panel $panel): void
    {
        $panel->pages($this->pages());

        // Explicit aliases are available for initial requests and Livewire updates,
        // including installations that cache Filament component discovery.
        foreach ($this->pages() as $page) {
            if (is_subclass_of($page, ModuleReportPage::class)) {
                Livewire::component($page::reportComponentName(), $page);
            }
        }
    }

    public function boot(Panel $panel): void
    {
        //
    }
}

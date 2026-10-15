<x-filament-panels::page>
    <div @class(['simaster-report app-main', 'simaster-infrastructure-report' => str($sourceRoute)->startsWith('infrastruktur.')]) wire:ignore data-bs-theme="light">
        {{ $reportHtml }}
    </div>
</x-filament-panels::page>

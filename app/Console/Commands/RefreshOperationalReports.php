<?php

namespace App\Console\Commands;

use App\Services\SiteStatusSummaryService;
use App\Support\ElectricityReadViews;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

final class RefreshOperationalReports extends Command
{
    protected $signature = 'simaster:refresh-reports';

    protected $description = 'Perbarui definisi view laporan operasional dan cache tanpa mengubah data sumber';

    public function handle(): int
    {
        if (! Schema::hasColumn('status_pembayaran', 'is_operational')) {
            $this->error('Jalankan migrasi laporan operasional terlebih dahulu.');

            return self::FAILURE;
        }
        ElectricityReadViews::create();
        app(SiteStatusSummaryService::class)->invalidateCache();
        $this->info('Enam view operasional dan cache laporan berhasil diperbarui. Data sumber dipertahankan.');

        return self::SUCCESS;
    }
}

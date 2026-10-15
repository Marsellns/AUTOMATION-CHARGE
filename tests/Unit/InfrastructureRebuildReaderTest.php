<?php

namespace Tests\Unit;

use App\Imports\Datasets\InfrastructureRebuildImport;
use PHPUnit\Framework\TestCase;

class InfrastructureRebuildReaderTest extends TestCase
{
    public function test_csv_templates_continue_to_use_the_existing_importer(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'infra-csv-');
        file_put_contents($path, "Site ID,Site Name,Year\nCSV-SEWA,CSV Site,2026\n");

        try {
            $this->assertFalse((new InfrastructureRebuildImport())->supports($path));
        } finally {
            unlink($path);
        }
    }
}

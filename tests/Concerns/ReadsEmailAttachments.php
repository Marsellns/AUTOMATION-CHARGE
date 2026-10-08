<?php

namespace Tests\Concerns;

use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Component\Mime\Email;

trait ReadsEmailAttachments
{
    private function attachmentRows(Email $email): array
    {
        $this->assertCount(1, $email->getAttachments());
        $attachment = $email->getAttachments()[0];
        $this->assertStringEndsWith('.xlsx', $attachment->getFilename());
        $path = tempnam(sys_get_temp_dir(), 'simaster-email-xlsx-');
        try {
            file_put_contents($path, $attachment->getBody());
            $workbook = IOFactory::load($path);
            $rows = $workbook->getActiveSheet()->toArray(null, false, false, false);
            $workbook->disconnectWorksheets();

            $this->assertSame('No', $rows[0][0]);
            $rows = array_slice($rows, 1);
            $this->assertSame(range(1, count($rows)), array_column($rows, 0));

            return $rows;
        } finally {
            unlink($path);
        }
    }
}

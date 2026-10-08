<?php

namespace App\Imports;

use App\Models\EquipmentRelocationInventory;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Parses a downloaded Equipment Relocation workbook before the controller
 * performs a single database transaction. No database writes occur here.
 */
class EquipmentRelocationImport implements ToCollection, WithHeadingRow, SkipsEmptyRows
{
    private const PIC_OPTIONS = ['NOP BOGOR', 'NOP BEKASI', 'NOP KARAWANG', 'NBAE'];
    private const PROGRESS_OPTIONS = ['NOT YET', 'ON GOING', 'DONE'];

    /** @var array<int, array<string, string|null>> */
    private array $records = [];

    /** @var array<string, int> */
    private array $seenKeys = [];

    /** @var array<int, string> */
    private array $errors = [];

    public function collection(Collection $rows): void
    {
        foreach ($rows->values() as $offset => $row) {
            $values = $row instanceof Collection ? $row : collect($row);
            if ($values->filter(fn ($value) => $this->clean($value) !== null)->isEmpty()) {
                continue;
            }

            $this->parseRow($values, $offset + 2);
        }

        if ($this->errors === []) {
            $this->validateInventoryKeys();
        }

        if ($this->errors !== []) {
            throw ValidationException::withMessages(['relocation_file' => $this->errors]);
        }

        if ($this->records === []) {
            throw ValidationException::withMessages([
                'relocation_file' => 'File Excel tidak memiliki baris data yang dapat diimpor.',
            ]);
        }
    }

    /** @return array<int, array<string, string|null>> */
    public function records(): array
    {
        return $this->records;
    }

    private function parseRow(Collection $row, int $rowNumber): void
    {
        $key = $this->clean($row->get('donor_uniq_key'));
        if ($key === null) {
            $this->addError("Baris {$rowNumber}: kolom donor_uniq_key wajib diisi.");

            return;
        }

        if (mb_strlen($key) > 191) {
            $this->addError("Baris {$rowNumber}: donor_uniq_key maksimal 191 karakter.");

            return;
        }

        if (isset($this->seenKeys[$key])) {
            $this->addError("Baris {$rowNumber}: donor_uniq_key duplikat di file Excel.");

            return;
        }

        $errorsBeforeRow = count($this->errors);
        $donorAcceptor = $this->nullableString($row->get('donor_acceptor'), 255, 'donor_acceptor', $rowNumber);
        $siteTargetSource = $this->nullableString($row->get('site_target_source'), 255, 'site_target_source', $rowNumber);
        $remark = $this->nullableString($row->get('remark'), 5000, 'remark', $rowNumber);
        $pic = $this->choice($row->get('pic'), self::PIC_OPTIONS, 'pic', $rowNumber);
        $progress = $this->choice($row->get('progress'), self::PROGRESS_OPTIONS, 'progress', $rowNumber);

        if (count($this->errors) > $errorsBeforeRow) {
            return;
        }

        $this->seenKeys[$key] = $rowNumber;
        $this->records[] = [
            'donor_uniq_key' => $key,
            'donor_acceptor' => $donorAcceptor,
            'site_target_source' => $siteTargetSource,
            'pic' => $pic,
            'progress' => $progress,
            'remark' => $remark,
        ];
    }

    private function nullableString(mixed $value, int $maximumLength, string $column, int $rowNumber): ?string
    {
        $value = $this->clean($value);
        if ($value === null || $value === '-') {
            return null;
        }

        if (mb_strlen($value) > $maximumLength) {
            $this->addError("Baris {$rowNumber}: {$column} maksimal {$maximumLength} karakter.");
        }

        return $value;
    }

    private function choice(mixed $value, array $options, string $column, int $rowNumber): ?string
    {
        $value = $this->clean($value);
        if ($value === null || $value === '-') {
            return null;
        }

        $value = strtoupper((string) preg_replace('/\s+/', ' ', $value));
        if (!in_array($value, $options, true)) {
            $this->addError("Baris {$rowNumber}: {$column} harus salah satu dari ".implode(', ', $options).'.');

            return null;
        }

        return $value;
    }

    private function clean(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function validateInventoryKeys(): void
    {
        foreach (array_chunk(array_keys($this->seenKeys), 500) as $keys) {
            $existing = EquipmentRelocationInventory::query()
                ->whereIn('uniq_key', $keys)
                ->pluck('uniq_key')
                ->all();

            foreach (array_diff($keys, $existing) as $key) {
                $this->addError("Baris {$this->seenKeys[$key]}: donor_uniq_key tidak ditemukan dalam inventaris equipment.");
            }
        }
    }

    private function addError(string $message): void
    {
        if (count($this->errors) < 10) {
            $this->errors[] = $message;
        }
    }
}

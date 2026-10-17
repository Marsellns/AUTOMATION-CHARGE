<?php

namespace App\Imports;

use App\Support\OperationalDatasets;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use PhpOffice\PhpSpreadsheet\Shared\Date;

final class OperationalDatasetImport implements ToCollection, WithChunkReading, WithHeadingRow
{
    public int $processed = 0;

    private array $seen = [];

    public function __construct(private readonly string $dataset, private readonly string $actor) {}

    public function chunkSize(): int
    {
        return 500;
    }

    public function collection(Collection $rows): void
    {
        foreach ($rows as $row) {
            $data = $row->all();
            if (collect($data)->filter(fn ($value) => $value !== null && $value !== '')->isEmpty()) {
                continue;
            }
            if (in_array($this->dataset, ['site-owner', 'pnl'], true) && isset($data['site_id'])) {
                $data['site_code'] = $data['site_id'];
            }
            foreach (OperationalDatasets::fields($this->dataset) as $name => $field) {
                if (! isset($data[$name]) || $data[$name] === '') {
                    if (! $field['required']) {
                        unset($data[$name]);
                    }

                    continue;
                }
                if ($field['type'] === 'text' && is_scalar($data[$name])) {
                    $data[$name] = (string) $data[$name];
                }
                if ($field['type'] === 'date' && is_numeric($data[$name])) {
                    $data[$name] = Date::excelToDateTimeObject($data[$name])->format('Y-m-d');
                }
            }
            $identity = [];
            foreach (OperationalDatasets::DEFINITIONS[$this->dataset][3] as $field) {
                $identity[] = strtoupper(trim((string) ($data[$field] ?? '')));
            }
            $key = json_encode($identity);
            if (isset($this->seen[$key])) {
                throw ValidationException::withMessages(['dataset_file' => 'Identitas data berulang di file Excel. Periksa baris '.($this->processed + 2).'.']);
            }
            $this->seen[$key] = true;
            try {
                OperationalDatasets::save($this->dataset, $data, false, $this->actor);
            } catch (ValidationException $exception) {
                throw ValidationException::withMessages(['dataset_file' => 'Baris '.($this->processed + 2).': '.implode(' ', $exception->validator->errors()->all())]);
            }
            $this->processed++;
        }
    }
}

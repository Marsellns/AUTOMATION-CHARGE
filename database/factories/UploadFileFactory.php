<?php

namespace Database\Factories;

use App\Models\UploadFile;
use Illuminate\Database\Eloquent\Factories\Factory;

class UploadFileFactory extends Factory
{
    protected $model = UploadFile::class;

    public function definition(): array
    {
        return [
            'keterangan'  => fake()->sentence(),
            'file_path'   => 'uploads/pdf/' . fake()->uuid() . '.pdf',
            'update_by'   => fake()->name(),
            'update_time' => fake()->dateTimeBetween('-3 months', 'now'),
        ];
    }
}

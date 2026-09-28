<?php

namespace Tests\Unit;

use App\Support\ElectricityAmountParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ElectricityAmountParserTest extends TestCase
{
    #[DataProvider('amounts')]
    public function test_it_parses_electricity_amounts_without_losing_indonesian_thousands(float|int|string|null $input, ?float $expected): void
    {
        $this->assertSame($expected, ElectricityAmountParser::parse($input));
    }

    public static function amounts(): array
    {
        return [
            'empty' => [null, null],
            'dash' => ['-', null],
            'integer numeric' => [3145183, 3145183.0],
            'numeric dot interpreted by Excel' => [467.216, 467216.0],
            'short numeric dot interpreted by Excel' => [20.2, 20200.0],
            'numeric string dot interpreted by Excel' => ['467.216', 467216.0],
            'short numeric string dot interpreted by Excel' => ['20.2', 20200.0],
            'indonesian thousands' => ['3.145.183', 3145183.0],
            'currency text' => ['Rp 3.145.183', 3145183.0],
            'indonesian decimal' => ['1.234,56', 1234.56],
            'international decimal' => ['1,234.56', 1234.56],
            'comma thousands' => ['467,216', 467216.0],
            'negative amount' => ['-1.250.000', -1250000.0],
        ];
    }
}

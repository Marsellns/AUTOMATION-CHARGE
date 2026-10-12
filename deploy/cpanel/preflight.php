<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

$basePath = dirname(__DIR__, 2);
require $basePath.'/vendor/autoload.php';
$app = require $basePath.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$fail = static function (string $message): never {
    fwrite(STDERR, "Deployment stopped: {$message}\n");
    exit(1);
};

if (! $app->environment('production') || config('app.debug') !== false) {
    $fail('APP_ENV must be production and APP_DEBUG must be false');
}
if (config('app.url') !== 'https://simawars.web.id') {
    $fail('APP_URL must be https://simawars.web.id');
}
$key = (string) config('app.key');
$decodedKey = str_starts_with($key, 'base64:') ? base64_decode(substr($key, 7), true) : $key;
if (! is_string($decodedKey) || strlen($decodedKey) !== 32) {
    $fail('APP_KEY must contain a valid AES-256 key');
}
if (config('session.secure') !== true || config('session.http_only') !== true) {
    $fail('session cookies must be Secure and HttpOnly');
}
if (! is_file(public_path('build/manifest.json'))) {
    $fail('Vite build manifest is missing');
}

try {
    if (DB::connection()->getDriverName() !== 'mysql') {
        $fail('production database must use MySQL');
    }
    DB::connection()->getPdo();
    $migrator = app('migrator');
    if (! $migrator->getRepository()->repositoryExists()) {
        $fail('migration table is missing; initialize the database first');
    }
    $pending = array_diff(
        array_keys($migrator->getMigrationFiles(database_path('migrations'))),
        $migrator->getRepository()->getRan(),
    );
    if ($pending !== []) {
        $fail('pending database migrations: '.implode(', ', $pending));
    }
} catch (Throwable $exception) {
    $fail('database preflight failed: '.$exception->getMessage());
}

echo "Production preflight passed; database schema is ready.\n";

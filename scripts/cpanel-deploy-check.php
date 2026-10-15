<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli' || PHP_VERSION_ID < 80200) {
    exit(1);
}

$mode = $argv[1] ?? '';

if ($mode === 'version' && count($argv) === 2) {
    echo PHP_VERSION_ID, PHP_EOL;
    exit(0);
}

if ($mode === 'package' && count($argv) === 6) {
    $metadata = json_decode(file_get_contents($argv[2]), true, 512, JSON_THROW_ON_ERROR);
    exit(
        ($metadata['source_commit'] ?? null) === $argv[3]
        && ($metadata['composer_lock_sha256'] ?? null) === hash_file('sha256', $argv[4])
        && ($metadata['vite_manifest_sha256'] ?? null) === hash_file('sha256', $argv[5])
        && ($metadata['includes_credentials'] ?? true) === false
        ? 0 : 1
    );
}

if ($mode === 'release' && count($argv) === 4) {
    $metadata = json_decode(file_get_contents($argv[2]), true, 512, JSON_THROW_ON_ERROR);
    exit(($metadata['source_commit'] ?? null) === $argv[3] ? 0 : 1);
}

exit(1);

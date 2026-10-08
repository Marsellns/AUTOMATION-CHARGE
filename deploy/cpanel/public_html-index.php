<?php

// Gunakan sebagai public_html/index.php hanya untuk domain utama yang
// document root-nya tidak bisa diarahkan ke /home/USER/simaster/public.
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));
$basePath = dirname(__DIR__).'/simaster';

if (file_exists($maintenance = $basePath.'/storage/framework/maintenance.php')) {
    require $maintenance;
}

require $basePath.'/vendor/autoload.php';
$app = require_once $basePath.'/bootstrap/app.php';
$app->usePublicPath(__DIR__);
$app->handleRequest(Request::capture());

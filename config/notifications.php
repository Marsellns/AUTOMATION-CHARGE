<?php

return [
    /*
    | Peringatan operasional dikirim pagi dan sore pada zona waktu
    | bisnis SIMASTER. Guard per kanal tetap diterapkan agar pemanggilan
    | command secara manual atau dari proses import tidak membuat duplikat.
    */
    'daily_at' => env('NOTIFICATION_DAILY_AT', '08:00'),
    'evening_at' => env('NOTIFICATION_EVENING_AT', '17:00'),
    'timezone' => env('NOTIFICATION_TIMEZONE', 'Asia/Jakarta'),
];

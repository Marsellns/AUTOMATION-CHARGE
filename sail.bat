@echo off
REM Sail wrapper for Windows - forwards commands to Docker container
REM Usage: .\sail artisan migrate:status
REM        .\sail artisan make:model Site
REM        .\sail php artisan tinker
REM        .\sail composer require package/name

docker compose exec laravel.test %*

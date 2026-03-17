#!/bin/bash
php artisan config:clear --silent
php artisan optimize:clear --silent
php artisan filament:optimize-clear --silent
rm -f storage/logs/*.log
php artisan filament:optimize --silent

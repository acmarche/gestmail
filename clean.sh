#!/bin/bash
php artisan filament:clear-cached-components --silent
php artisan cache:clear --silent
php artisan config:clear --silent
php artisan view:clear --silent
php artisan route:clear --silent
php artisan filament:optimize-clear --silent
rm -fr storage/logs/*.log
rm -fr storage/framework/views/*.php
php artisan filament:optimize --silent

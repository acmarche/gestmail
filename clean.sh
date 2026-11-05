#!/usr/bin/env bash
# Clear Laravel/Filament caches and reset ownership & permissions.
# Usage: sudo ./clean.sh
set -euo pipefail

if [[ ${EUID} -ne 0 ]]; then
    echo "This script must be run as root (sudo $0)" >&2
    exit 1
fi

cd "$(dirname "$(readlink -f "$0")")"

OWNER="root:root"
WEB_USER="www-data:www-data"
WRITABLE_DIRS=(storage bootstrap/cache)

# Caches & logs (removed before artisan boots so stale manifests can't break it)
rm -rf bootstrap/cache/*.php bootstrap/cache/filament/ storage/framework/views/*.php storage/logs/*.log

# optimize:clear covers config, route, view, event, cache and compiled files
php artisan optimize:clear --quiet
php artisan filament:optimize-clear --quiet

# Ownership
chown -R "${OWNER}" .
chown -R "${WEB_USER}" "${WRITABLE_DIRS[@]}"

# Permissions: 755 dirs / 644 files, skipping .git, vendor and node_modules (keeps their binaries executable)
find . \( -path ./.git -o -path ./vendor -o -path ./node_modules \) -prune -o -type d -exec chmod 755 {} + \
    -o -type f -exec chmod 644 {} +
chmod 755 artisan clean.sh

# Writable dirs: 775 dirs / 664 files, setgid so new files inherit the www-data group
find "${WRITABLE_DIRS[@]}" -type d -exec chmod 2775 {} +
find "${WRITABLE_DIRS[@]}" -type f -exec chmod 664 {} +

# .env readable by the web server only
[[ -f .env ]] && chown root:www-data .env && chmod 640 .env

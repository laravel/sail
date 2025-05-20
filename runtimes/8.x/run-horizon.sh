#!/bin/sh

cd /var/www

# Check if horizon command exists (still optional)
php artisan horizon:publish --help > /dev/null 2>&1 || exit 0

# Check if Horizon is already running
if pgrep -f "artisan horizon" > /dev/null; then
  echo "Horizon is already running."
  exit 0
fi

# Start Horizon in foreground (s6 will manage it)
exec php artisan horizon
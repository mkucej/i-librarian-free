#!/bin/bash

# Start PHP-FPM.
/usr/sbin/php-fpm8.1 -F &
status=$?

if [ $status -ne 0 ]; then
  echo "Failed to start php-fpm: $status"
  exit $status
fi

# Start Caddy.
exec /usr/bin/caddy run --config /etc/caddy/Caddyfile --adapter caddyfile

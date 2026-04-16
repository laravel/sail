#!/bin/sh
set -e

mkdir -p /var/log/php-fpm /var/log/php
chown -R sail:sail /var/log/php-fpm /var/log/php
chmod 02755 /var/log/php-fpm /var/log/php

MODE="${SAIL_LOG_MODE:-both}"
ARCHIVES="${SAIL_LOG_MAX_ARCHIVES:-20}"
SIZE="${SAIL_LOG_ROTATE_SIZE:-10000000}"

case "$MODE" in
  stdout) DEST='p[php-fpm] 1' ;;
  file)   DEST='/var/log/php-fpm p[php-fpm]' ;;
  both)   DEST='/var/log/php-fpm p[php-fpm] 1' ;;
  *)
    echo "php-fpm-log-prepare: invalid SAIL_LOG_MODE='$MODE' (expected stdout|file|both)" >&2
    exit 1
    ;;
esac

cat > /etc/s6-overlay/s6-rc.d/php-fpm-log/run <<RUN_SCRIPT
#!/bin/sh
exec s6-log -b n${ARCHIVES} s${SIZE} T !"gzip -nq9" ${DEST}
RUN_SCRIPT
chmod +x /etc/s6-overlay/s6-rc.d/php-fpm-log/run

echo php-fpm-log-prepare completed > /tmp/php-fpm-log-prepare-ran

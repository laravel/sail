#!/bin/sh
set -e

# Ensure log directory exists regardless of mode.
mkdir -p /var/log/nginx
chown root:root /var/log/nginx
chmod 02755 /var/log/nginx

MODE="${SAIL_LOG_MODE:-both}"
ARCHIVES="${SAIL_LOG_MAX_ARCHIVES:-20}"
SIZE="${SAIL_LOG_ROTATE_SIZE:-10000000}"

case "$MODE" in
  stdout) DEST='p[nginx] 1' ;;
  file)   DEST='/var/log/nginx p[nginx]' ;;
  both)   DEST='/var/log/nginx p[nginx] 1' ;;
  *)
    echo "nginx-log-prepare: invalid SAIL_LOG_MODE='$MODE' (expected stdout|file|both)" >&2
    exit 1
    ;;
esac

cat > /etc/s6-overlay/s6-rc.d/nginx-log/run <<RUN_SCRIPT
#!/bin/sh
exec s6-log -b n${ARCHIVES} s${SIZE} T !"gzip -nq9" ${DEST}
RUN_SCRIPT
chmod +x /etc/s6-overlay/s6-rc.d/nginx-log/run

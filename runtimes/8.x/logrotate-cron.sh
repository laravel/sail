#!/bin/sh
while true; do
  /usr/sbin/logrotate /etc/logrotate.conf
  sleep 3600 # Rotate logs every hour
done
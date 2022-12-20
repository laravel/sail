#!/usr/bin/env bash

if [ ! -z "$WWWUSER" ]; then
    usermod -u $WWWUSER sail

elif [ ! -d /.composer ]; then
    mkdir /.composer &&
    chmod -R ugo+rw /.composer
    
elif [ $# -gt 0 ]; then
    exec gosu $WWWUSER "$@"
    
else
    exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
fi

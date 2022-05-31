#!/bin/sh

ME=$(basename $0)
KEY=/etc/nginx/certs/server.key
CERT=/etc/nginx/certs/server.pem

if [ -f $KEY ] && [ -f $CERT ]; then
    echo "$ME: Server certificate already exists, do nothing."
else
    openssl req -x509 -newkey rsa:2048 -keyout $KEY \
        -out $CERT -sha256 -days 3650 -nodes -subj '/CN=localhost'
    echo "$ME: Server certificate has been generated."
fi

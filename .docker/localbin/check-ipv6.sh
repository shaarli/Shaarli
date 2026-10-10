#!/bin/sh
# Verify IPv6 binding works. If not, strip the [::]:80 listen directive from nginx.conf.

if ! nc -l -s :: -p 9999 -w 1 >/dev/null 2>&1; then
    echo "[INFO] Cannot bind IPv6 address, disabling IPv6 listening"
    sed -i '/^[[:space:]]*listen[[:space:]]*\[::\]:80;/d' /etc/nginx/nginx.conf
fi

exit 0

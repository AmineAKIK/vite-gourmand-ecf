#!/bin/sh
export PORT=${PORT:-8080}
php-fpm -D
envsubst '${PORT}' < /etc/nginx/nginx.conf > /tmp/nginx-runtime.conf
nginx -c /tmp/nginx-runtime.conf -g "daemon off;"

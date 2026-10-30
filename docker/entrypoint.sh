#!/bin/sh
set -e

# cache config, routes, views and events from the runtime environment
php artisan optimize

exec "$@"

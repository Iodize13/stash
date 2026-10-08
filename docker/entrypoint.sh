#!/bin/sh
set -e

# Cache config/routes/views with the real environment (env vars come from Kubernetes).
php artisan optimize --no-interaction >/dev/null

exec "$@"

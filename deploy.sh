#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")"

COMPOSE="podman-compose -f compose.prod.yaml"

git pull --ff-only
$COMPOSE build

# database first, and wait until it answers
$COMPOSE up -d db
until podman exec budget-db pg_isready -q; do sleep 1; done

# podman-compose does not recreate containers when the image changes, so force it
$COMPOSE up -d --force-recreate app scheduler
until podman exec budget-app php -v >/dev/null 2>&1; do sleep 1; done

podman exec budget-app php artisan migrate --force
podman image prune -f

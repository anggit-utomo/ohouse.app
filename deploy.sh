#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")"

COMPOSE="podman-compose -f compose.prod.yaml"

git pull --ff-only
$COMPOSE build

# database first, and wait until it answers
$COMPOSE up -d db
until podman exec budget-db pg_isready -q; do sleep 1; done

# podman-compose does not recreate containers when the image changes, so force it.
# Refresh the renderer first so its bundle matches the PHP code that follows.
$COMPOSE up -d --force-recreate ssr

$COMPOSE up -d --force-recreate app worker
until podman exec budget-app php -v >/dev/null 2>&1; do sleep 1; done

podman exec budget-app php artisan migrate --force
podman image prune -f

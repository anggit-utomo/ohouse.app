#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")"

COMPOSE="podman-compose -f compose.prod.yaml"

# Manual fallback: if GitHub Actions is down, you can still run this.
# Normal deploys are now via Actions -> GHCR -> podman pull (no build on VPS).
# This script intentionally does NOT build; it only pulls.

git pull --ff-only || true

$COMPOSE pull

$COMPOSE up -d db
until podman exec budget-db pg_isready -q; do sleep 1; done

$COMPOSE up -d --force-recreate ssr
$COMPOSE up -d --force-recreate app worker scheduler
until podman exec budget-app php -v >/dev/null 2>&1; do sleep 1; done

for i in $(seq 1 30); do
  if podman exec budget-ssr wget -qO- http://127.0.0.1:13714/health >/dev/null 2>&1; then break; fi
  sleep 1
done

podman exec budget-app php artisan migrate --force
podman image prune -f
echo "deploy done"

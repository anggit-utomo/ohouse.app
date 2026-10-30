#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."

# cron has no login environment, and rootless Podman needs these
export XDG_RUNTIME_DIR="/run/user/$(id -u)"
export DBUS_SESSION_BUS_ADDRESS="unix:path=$XDG_RUNTIME_DIR/bus"

mkdir -p backups
stamp=$(date +%F-%H%M)

podman exec budget-db sh -c 'pg_dump -U "$POSTGRES_USER" -d "$POSTGRES_DB" --format=custom' > "backups/budget-$stamp.dump"

find backups -name 'budget-*.dump' -mtime +14 -delete

# a copy on the same disk is not a backup: send it somewhere else too, for example
# rclone copy backups remote:budget-backups --max-age 2d

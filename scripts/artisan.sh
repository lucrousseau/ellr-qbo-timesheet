#!/usr/bin/env sh
# Runs Laravel artisan in the Docker api service when it is up, otherwise in backend/.
set -e

ROOT_DIR="$(CDPATH= cd "$(dirname "$0")/.." && pwd)"
COMPOSE_FILE="$ROOT_DIR/docker-compose.yml"

if [ -f "$ROOT_DIR/docker/.env" ]; then
  ENV_FILE="$ROOT_DIR/docker/.env"
elif [ -f "$ROOT_DIR/.env" ]; then
  ENV_FILE="$ROOT_DIR/.env"
else
  ENV_FILE=""
fi

COMPOSE_ENV_ARGS=""
if [ -n "$ENV_FILE" ]; then
  COMPOSE_ENV_ARGS="--env-file $ENV_FILE"
fi

if [ -f "$COMPOSE_FILE" ] && docker compose $COMPOSE_ENV_ARGS ps --status running api 2>/dev/null | grep -q api; then
  exec docker compose $COMPOSE_ENV_ARGS exec api php artisan "$@"
fi

cd "$ROOT_DIR/backend"
exec php artisan "$@"

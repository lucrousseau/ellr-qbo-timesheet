#!/bin/sh
set -e

SCRIPT_DIR="$(CDPATH= cd "$(dirname "$0")" && pwd)"
ROOT_DIR="${DOCKER_RESET_ROOT:-$(CDPATH= cd "$SCRIPT_DIR/.." && pwd)}"
cd "$ROOT_DIR"

RESOLVE_SQLITE="$SCRIPT_DIR/lib/resolve-sqlite-path.sh"

if [ ! -f docker/.env ]; then
  echo "docker/.env not found; copying from docker/.env.example..."
  cp -n docker/.env.example docker/.env 2>/dev/null || true
fi

COMPOSE="docker compose --env-file docker/.env"

echo "WARNING: This permanently deletes the local SQLite database (tokens, time entries, QBO snapshots)."
echo "Stopping stack and removing Docker volumes (node_modules, npm_cache, api_vendor)..."
$COMPOSE down -v --remove-orphans

if SQLITE_FILE=$("$RESOLVE_SQLITE" "$ROOT_DIR/backend"); then
  echo "Removing local SQLite database at $SQLITE_FILE (fresh DB on next start)..."
  rm -f "$SQLITE_FILE" "${SQLITE_FILE}-wal" "${SQLITE_FILE}-shm"
else
  echo "Skipping SQLite removal (DB_CONNECTION is not sqlite in backend/.env)."
fi

echo "Docker reset complete. Run npm run docker:up:build to rebuild images and start."

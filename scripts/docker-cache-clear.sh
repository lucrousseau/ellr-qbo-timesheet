#!/bin/sh
set -e

ROOT_DIR="$(CDPATH= cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT_DIR"

if [ ! -f docker/.env ]; then
  echo "docker/.env not found; copying from docker/.env.example..."
  cp -n docker/.env.example docker/.env 2>/dev/null || true
fi

COMPOSE="docker compose --env-file docker/.env"

echo "Stopping stack, removing volumes (node_modules, npm_cache, api_vendor), and local images..."
$COMPOSE down -v --rmi local --remove-orphans

echo "Pruning npm BuildKit cache mounts for this machine (not other builder layers)..."
docker builder prune --filter type=exec.cachemount -f

if [ "${DOCKER_CACHE_CLEAR_ALL:-}" = "1" ]; then
  echo "DOCKER_CACHE_CLEAR_ALL=1 set; pruning all Docker builder cache..."
  docker builder prune -f
fi

echo "Docker caches cleared. SQLite is kept; use npm run docker:reset for a fresh database."
echo "Run npm run docker:up:build to rebuild images and start."

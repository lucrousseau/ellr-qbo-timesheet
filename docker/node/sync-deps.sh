#!/bin/sh
set -e

APP_ROOT="${APP_ROOT:-/app}"
cd "$APP_ROOT"

LOCK_HASH_FILE="node_modules/.package-lock-hash"
CURRENT_HASH=$(md5sum package-lock.json | awk '{print $1}')
NODE_MODULES_SEED="${NODE_MODULES_SEED:-/opt/node_modules_seed}"
SEED_HASH_FILE="$NODE_MODULES_SEED/.package-lock-hash"

lock_matches() {
  [ -f "$1" ] && [ "$(cat "$1")" = "$CURRENT_HASH" ]
}

node_modules_ready() {
  lock_matches "$LOCK_HASH_FILE" \
    && [ -n "$(find node_modules -mindepth 1 ! -name '.package-lock-hash' -print -quit 2>/dev/null)" ]
}

clear_node_modules() {
  mkdir -p node_modules
  find node_modules -mindepth 1 -delete 2>/dev/null || true
}

needs_install=false
if ! node_modules_ready; then
  needs_install=true
fi

if [ "$needs_install" = true ]; then
  if [ -d "$NODE_MODULES_SEED" ] && lock_matches "$SEED_HASH_FILE"; then
    echo "Seeding node_modules from image (lock file matches build)..."
    clear_node_modules
    cp -a "$NODE_MODULES_SEED/." node_modules/
  else
    echo "Installing Node dependencies (package-lock.json changed or image not rebuilt)..."
    clear_node_modules
    npm ci --ignore-scripts --prefer-offline
    echo "$CURRENT_HASH" > "$LOCK_HASH_FILE"
  fi
fi

echo "Node dependencies are up to date."

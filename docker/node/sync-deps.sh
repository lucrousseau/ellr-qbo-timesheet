#!/bin/sh
set -e

cd /app

LOCK_HASH_FILE="node_modules/.package-lock-hash"
CURRENT_HASH=$(md5sum package-lock.json | awk '{print $1}')

if [ ! -d node_modules ] \
  || [ ! -f "$LOCK_HASH_FILE" ] \
  || [ "$(cat "$LOCK_HASH_FILE")" != "$CURRENT_HASH" ]; then
  echo "Installing Node dependencies (package-lock.json changed or node_modules missing)..."
  npm ci
  echo "$CURRENT_HASH" > "$LOCK_HASH_FILE"
fi

echo "Node dependencies are up to date."

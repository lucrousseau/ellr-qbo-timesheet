#!/bin/sh
set -e

cd /app
/usr/local/bin/sync-node-deps.sh
exec "$@"

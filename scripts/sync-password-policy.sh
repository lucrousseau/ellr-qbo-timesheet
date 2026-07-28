#!/bin/sh
set -e

ROOT_DIR="$(CDPATH= cd "$(dirname "$0")/.." && pwd)"
SOURCE="${ROOT_DIR}/packages/password-policy/password-policy.json"
TARGET="${ROOT_DIR}/backend/config/password-policy.json"

if [ ! -f "$SOURCE" ]; then
  echo "Missing password policy source at ${SOURCE}"
  exit 1
fi

mkdir -p "$(dirname "$TARGET")"
cp "$SOURCE" "$TARGET"

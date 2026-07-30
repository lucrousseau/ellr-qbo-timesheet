#!/bin/sh
set -e

ROOT_DIR="$(CDPATH= cd "$(dirname "$0")/.." && pwd)"
RESOLVE_SQLITE="$ROOT_DIR/scripts/lib/resolve-sqlite-path.sh"
TMP_DIR="$(mktemp -d)"
trap 'rm -rf "$TMP_DIR"' EXIT

assert_eq() {
  expected="$1"
  actual="$2"
  label="$3"
  if [ "$expected" != "$actual" ]; then
    echo "FAIL: $label"
    echo "  expected: $expected"
    echo "  actual:   $actual"
    exit 1
  fi
}

assert_fails() {
  if "$@"; then
    echo "FAIL: expected command to fail: $*"
    exit 1
  fi
}

echo "resolve-sqlite-path: defaults to database/database.sqlite when .env is missing..."
backend_dir="$TMP_DIR/default"
mkdir -p "$backend_dir"
path=$("$RESOLVE_SQLITE" "$backend_dir")
assert_eq "$backend_dir/database/database.sqlite" "$path" "default sqlite path"

echo "resolve-sqlite-path: honors DB_DATABASE from backend/.env..."
backend_dir="$TMP_DIR/custom-relative"
mkdir -p "$backend_dir/storage"
printf '%s\n' 'DB_CONNECTION=sqlite' 'DB_DATABASE=storage/custom.sqlite' > "$backend_dir/.env"
path=$("$RESOLVE_SQLITE" "$backend_dir")
assert_eq "$backend_dir/storage/custom.sqlite" "$path" "relative DB_DATABASE"

echo "resolve-sqlite-path: honors absolute DB_DATABASE..."
backend_dir="$TMP_DIR/absolute"
mkdir -p "$backend_dir"
absolute_path="$TMP_DIR/outside/absolute.sqlite"
printf '%s\n' "DB_CONNECTION=sqlite" "DB_DATABASE=$absolute_path" > "$backend_dir/.env"
path=$("$RESOLVE_SQLITE" "$backend_dir")
assert_eq "$absolute_path" "$path" "absolute DB_DATABASE"

echo "resolve-sqlite-path: exits non-zero when DB_CONNECTION is mysql..."
backend_dir="$TMP_DIR/mysql"
mkdir -p "$backend_dir"
printf '%s\n' 'DB_CONNECTION=mysql' 'DB_DATABASE=ellr' > "$backend_dir/.env"
assert_fails "$RESOLVE_SQLITE" "$backend_dir"

echo "resolve-sqlite-path: prefers shell env over backend/.env..."
backend_dir="$TMP_DIR/shell-env"
mkdir -p "$backend_dir"
printf '%s\n' 'DB_CONNECTION=sqlite' 'DB_DATABASE=storage/from-file.sqlite' > "$backend_dir/.env"
path=$(DB_DATABASE=storage/from-shell.sqlite "$RESOLVE_SQLITE" "$backend_dir")
assert_eq "$backend_dir/storage/from-shell.sqlite" "$path" "shell env precedence"

echo "All resolve-sqlite-path tests passed."

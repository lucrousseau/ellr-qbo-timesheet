#!/bin/sh
set -e

ROOT_DIR="$(CDPATH= cd "$(dirname "$0")/.." && pwd)"
SYNC_DEPS="$ROOT_DIR/docker/node/sync-deps.sh"
TMP_DIR="$(mktemp -d)"
trap 'rm -rf "$TMP_DIR"' EXIT

setup_workspace() {
  mkdir -p "$TMP_DIR/node_modules" "$TMP_DIR/seed/pkg" "$TMP_DIR/fake_bin"
  printf '%s\n' '{"name":"test"}' > "$TMP_DIR/package-lock.json"
  HASH=$(md5sum "$TMP_DIR/package-lock.json" | awk '{print $1}')
  echo "$HASH" > "$TMP_DIR/seed/.package-lock-hash"
  echo "seeded" > "$TMP_DIR/seed/pkg/marker.txt"

  cat > "$TMP_DIR/fake_bin/npm" <<'EOF'
#!/bin/sh
if [ "$1" = "ci" ]; then
  mkdir -p node_modules/fallback-pkg
  echo "installed" > node_modules/fallback-pkg/marker.txt
  exit 0
fi
exit 1
EOF
  chmod +x "$TMP_DIR/fake_bin/npm"
}

run_sync() {
  (
    cd "$TMP_DIR"
    APP_ROOT="$TMP_DIR" NODE_MODULES_SEED="$TMP_DIR/seed" PATH="$TMP_DIR/fake_bin:$PATH" \
      sh "$SYNC_DEPS"
  ) >/dev/null
}

assert_file() {
  if [ ! -f "$1" ]; then
    echo "FAIL: expected file $1"
    exit 1
  fi
}

echo "sync-deps: seeds empty node_modules from image cache..."
setup_workspace
run_sync
assert_file "$TMP_DIR/node_modules/pkg/marker.txt"
assert_file "$TMP_DIR/node_modules/.package-lock-hash"

echo "sync-deps: skips when node_modules already matches lock file..."
run_sync

echo "sync-deps: reinstalls when node_modules is empty but hash file remains..."
setup_workspace
echo "$(md5sum "$TMP_DIR/package-lock.json" | awk '{print $1}')" > "$TMP_DIR/node_modules/.package-lock-hash"
run_sync
assert_file "$TMP_DIR/node_modules/pkg/marker.txt"

echo "sync-deps: falls back to npm ci when seed is stale..."
setup_workspace
printf '%s\n' '{"name":"test","lockVersion":2}' > "$TMP_DIR/package-lock.json"
run_sync
assert_file "$TMP_DIR/node_modules/fallback-pkg/marker.txt"
assert_file "$TMP_DIR/node_modules/.package-lock-hash"

echo "All docker-sync-deps tests passed."

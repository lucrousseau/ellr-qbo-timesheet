#!/usr/bin/env sh
set -e

ROOT_DIR="$(CDPATH= cd "$(dirname "$0")/.." && pwd)"
STATUS_SCRIPT="$ROOT_DIR/scripts/dev-webhook-tunnel-status.sh"
TMP_DIR="$(mktemp -d)"
trap 'rm -rf "$TMP_DIR"' EXIT

assert_contains() {
  haystack="$1"
  needle="$2"
  label="$3"
  if ! printf '%s' "$haystack" | grep -q "$needle"; then
    echo "FAIL: $label"
    echo "  expected output to contain: $needle"
    echo "  actual output:"
    printf '%s\n' "$haystack"
    exit 1
  fi
}

mkdir -p "$TMP_DIR/fake_bin" "$TMP_DIR/backend"
printf '%s\n' 'QUICKBOOKS_WEBHOOK_VERIFIER=test-verifier-token' > "$TMP_DIR/backend/.env"

cat > "$TMP_DIR/fake_bin/curl" <<'EOF'
#!/bin/sh
URL="$2"
case "$URL" in
  *:8000/api/health)
    exit 0
    ;;
  *:4040/api/tunnels)
  cat <<'JSON'
{"tunnels":[{"name":"command_line","public_url":"https://example.ngrok-free.dev","proto":"https","config":{"addr":"http://localhost:8000"}}]}
JSON
    exit 0
    ;;
esac
exit 1
EOF

cat > "$TMP_DIR/fake_bin/docker" <<'EOF'
#!/bin/sh
if [ "$1" = "compose" ]; then
  shift
  while [ $# -gt 0 ]; do
    case "$1" in
      --env-file|--status)
        shift
        ;;
      queue|scheduler)
        echo "ellr-qbo-timesheet-$1-1 $1 running"
        exit 0
        ;;
    esac
    shift
  done
fi
exit 1
EOF

cat > "$TMP_DIR/fake_bin/ngrok" <<'EOF'
#!/bin/sh
if [ "$1" = "version" ]; then
  echo "ngrok version 3.39.10"
  exit 0
fi
exit 1
EOF

chmod +x "$TMP_DIR/fake_bin/"*

run_status() {
  PATH="$TMP_DIR/fake_bin:$PATH" NGROK_API=http://127.0.0.1:4040 sh "$STATUS_SCRIPT" "$@"
}

echo "dev-webhook-tunnel-status: reports OK when tunnel, API, and Docker workers are healthy..."
set +e
OUTPUT=$(run_status 2>&1)
STATUS_EXIT=$?
set -e
if [ "$STATUS_EXIT" -ne 0 ]; then
  echo "FAIL: expected exit 0 from status script (got $STATUS_EXIT)"
  printf '%s\n' "$OUTPUT"
  exit 1
fi
assert_contains "$OUTPUT" 'OK: ngrok tunnel (https://example.ngrok-free.dev)' 'ngrok tunnel'
assert_contains "$OUTPUT" 'OK: Docker queue worker' 'queue worker'
assert_contains "$OUTPUT" 'OK: Docker scheduler' 'scheduler'
assert_contains "$OUTPUT" 'https://example.ngrok-free.dev/api/quickbooks/webhook' 'intuit endpoint'

echo "dev-webhook-tunnel-status: fails when tunnel forwards to the wrong port..."
cat > "$TMP_DIR/fake_bin/curl" <<'EOF'
#!/bin/sh
URL="$2"
case "$URL" in
  *:8000/api/health)
    exit 0
    ;;
  *:4040/api/tunnels)
  cat <<'JSON'
{"tunnels":[{"name":"command_line","public_url":"https://other.ngrok-free.dev","proto":"https","config":{"addr":"http://localhost:9000"}}]}
JSON
    exit 0
    ;;
esac
exit 1
EOF
chmod +x "$TMP_DIR/fake_bin/curl"

OUTPUT=$(run_status 2>&1 || true)
if printf '%s' "$OUTPUT" | grep -q 'not forwarding to port 8000'; then
  :
else
  echo "FAIL: expected wrong-port tunnel message"
  printf '%s\n' "$OUTPUT"
  exit 1
fi
set +e
run_status >/dev/null 2>&1
STATUS_EXIT=$?
set -e
if [ "$STATUS_EXIT" -ne 1 ]; then
  echo "FAIL: expected exit 1 for wrong-port tunnel (got $STATUS_EXIT)"
  exit 1
fi

echo "dev-webhook-tunnel-status: fails when ngrok is not running..."
cat > "$TMP_DIR/fake_bin/curl" <<'EOF'
#!/bin/sh
URL="$2"
case "$URL" in
  *:8000/api/health)
    exit 0
    ;;
esac
exit 1
EOF
chmod +x "$TMP_DIR/fake_bin/curl"

OUTPUT=$(run_status 2>&1 || true)
if ! printf '%s' "$OUTPUT" | grep -q 'ngrok tunnel'; then
  echo "FAIL: expected missing tunnel message"
  printf '%s\n' "$OUTPUT"
  exit 1
fi

echo "entrypoint: only api container runs migrations..."
ENTRYPOINT="$ROOT_DIR/docker/api/entrypoint.sh"
if ! grep -q 'CONTAINER_ROLE:-api}" = "api"' "$ENTRYPOINT"; then
  echo "FAIL: entrypoint must run migrations only when CONTAINER_ROLE is api"
  exit 1
fi

echo "All dev-webhook-tunnel-status tests passed."

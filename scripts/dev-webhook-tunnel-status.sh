#!/usr/bin/env sh
# Checks whether ngrok is running and forwarding to the local API for Intuit webhooks.
# Usage: npm run dev:webhook-tunnel:status

set -e

ROOT_DIR="$(CDPATH= cd "$(dirname "$0")/.." && pwd)"
PORT="${API_PORT:-8000}"
NGROK_API="${NGROK_API:-http://127.0.0.1:4040}"
WEBHOOK_PATH="/api/quickbooks/webhook"
FAIL=0

check_ok() {
  LABEL="$1"
  DETAIL="$2"
  printf 'OK: %s' "$LABEL"
  if [ -n "$DETAIL" ]; then
    printf ' (%s)' "$DETAIL"
  fi
  printf '\n'
}

check_fail() {
  LABEL="$1"
  DETAIL="$2"
  printf 'FAIL: %s' "$LABEL"
  if [ -n "$DETAIL" ]; then
    printf ' — %s' "$DETAIL"
  fi
  printf '\n'
  FAIL=1
}

check_warn() {
  LABEL="$1"
  DETAIL="$2"
  printf 'WARN: %s' "$LABEL"
  if [ -n "$DETAIL" ]; then
    printf ' (%s)' "$DETAIL"
  fi
  printf '\n'
}

compose_env_args() {
  if [ -f "$ROOT_DIR/docker/.env" ]; then
    printf '%s' "--env-file $ROOT_DIR/docker/.env"
  fi
}

docker_service_running() {
  SERVICE="$1"
  COMPOSE_ENV_ARGS=$(compose_env_args)

  # shellcheck disable=SC2086
  docker compose $COMPOSE_ENV_ARGS ps --status running "$SERVICE" 2>/dev/null | grep -q "$SERVICE"
}

printf '%s\n' 'QuickBooks webhook tunnel status' ''

if command -v ngrok >/dev/null 2>&1; then
  NGROK_VERSION=$(ngrok version 2>/dev/null | head -n 1)
  check_ok 'ngrok CLI' "$NGROK_VERSION"
else
  check_fail 'ngrok CLI' 'install: brew install ngrok/ngrok/ngrok'
fi

if curl -sf "http://127.0.0.1:${PORT}/api/health" >/dev/null 2>&1; then
  check_ok 'Local API' "http://127.0.0.1:${PORT}/api/health"
else
  check_fail 'Local API' 'start Docker: npm run docker:up'
fi

if command -v docker >/dev/null 2>&1; then
  if docker_service_running queue; then
    check_ok 'Docker queue worker' 'running'
  else
    check_fail 'Docker queue worker' 'start: npm run docker:up'
  fi

  if docker_service_running scheduler; then
    check_ok 'Docker scheduler' 'running'
  else
    check_fail 'Docker scheduler' 'start: npm run docker:up'
  fi
else
  check_warn 'Docker services' 'docker CLI not found; skipped queue/scheduler checks'
fi

TUNNEL_JSON=$(curl -sf "${NGROK_API}/api/tunnels" 2>/dev/null || true)
PUBLIC_URL=''

if [ -z "$TUNNEL_JSON" ]; then
  check_fail 'ngrok tunnel' 'not running — start: npm run dev:webhook-tunnel'
else
  TUNNEL_RESULT=$(node -e "
    const payload = JSON.parse(process.argv[1]);
    const port = process.argv[2];
    const tunnels = payload.tunnels || [];
    const matchesPort = (tunnel) => {
      const addr = String(tunnel.config?.addr || '');
      return addr.includes(':' + port) || addr.endsWith(port);
    };
    const httpsForPort = tunnels.find((tunnel) => tunnel.proto === 'https' && matchesPort(tunnel));
    const anyHttps = tunnels.find((tunnel) => tunnel.proto === 'https');

    if (httpsForPort?.public_url) {
      process.stdout.write('ok\t' + httpsForPort.public_url);
    } else if (anyHttps?.public_url) {
      process.stdout.write('wrong_port\t' + anyHttps.public_url);
    } else {
      process.stdout.write('missing\t');
    }
  " "$TUNNEL_JSON" "$PORT" 2>/dev/null || true)

  TUNNEL_STATUS=$(printf '%s' "$TUNNEL_RESULT" | cut -f1)
  PUBLIC_URL=$(printf '%s' "$TUNNEL_RESULT" | cut -f2)

  case "$TUNNEL_STATUS" in
    ok)
      check_ok 'ngrok tunnel' "$PUBLIC_URL"
      ;;
    wrong_port)
      check_fail 'ngrok tunnel' "HTTPS tunnel at ${PUBLIC_URL} is not forwarding to port ${PORT}"
      PUBLIC_URL=''
      ;;
    *)
      check_fail 'ngrok tunnel' "no HTTPS tunnel forwarding to port ${PORT}"
      ;;
  esac
fi

if [ -f "$ROOT_DIR/backend/.env" ] && grep -qE '^QUICKBOOKS_WEBHOOK_VERIFIER=.+' "$ROOT_DIR/backend/.env" 2>/dev/null; then
  check_ok 'Webhook verifier' 'present in backend/.env (signature not validated here)'
else
  check_fail 'Webhook verifier' 'set QUICKBOOKS_WEBHOOK_VERIFIER in backend/.env'
fi

printf '\n'

if [ -n "$PUBLIC_URL" ]; then
  printf '%s\n' \
    'Intuit endpoint URL:' \
    "  ${PUBLIC_URL}${WEBHOOK_PATH}" \
    '' \
    'ngrok inspect dashboard (open in browser):' \
    "  ${NGROK_API}" \
    ''
fi

if [ "$FAIL" -ne 0 ]; then
  exit 1
fi

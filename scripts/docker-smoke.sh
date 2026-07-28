#!/bin/sh
set -e

API_URL="${API_URL:-http://localhost:8000/api/health}"
ADMIN_URL="${ADMIN_URL:-http://localhost:5173/}"
ADMIN_ORIGIN="${ADMIN_ORIGIN:-http://localhost:5173}"
TIMESHEET_URL="${TIMESHEET_URL:-http://localhost:5174/}"
MAX_ATTEMPTS="${SMOKE_WAIT_ATTEMPTS:-60}"
SMOKE_LOGIN_EMAIL="${SMOKE_LOGIN_EMAIL:-admin@ellr.local}"
SMOKE_LOGIN_PASSWORD="${SMOKE_LOGIN_PASSWORD:-password}"
COOKIE_JAR="$(mktemp)"
trap 'rm -f "$COOKIE_JAR"' EXIT

wait_for_url() {
  URL="$1"
  NAME="$2"
  ATTEMPT=0

  until curl -sf "$URL" >/dev/null; do
    ATTEMPT=$((ATTEMPT + 1))
    if [ "$ATTEMPT" -ge "$MAX_ATTEMPTS" ]; then
      echo "Smoke test failed: $NAME did not become ready at $URL"
      exit 1
    fi
    sleep 2
  done

  echo "OK: $NAME is ready ($URL)"
}

wait_for_api_health() {
  ATTEMPT=0

  while true; do
    HEALTH_BODY=$(curl -sf "$API_URL" || true)
    if [ -n "$HEALTH_BODY" ] && echo "$HEALTH_BODY" | grep -q '"status":"ok"'; then
      echo "OK: API health check passed ($API_URL)"
      return 0
    fi

    ATTEMPT=$((ATTEMPT + 1))
    if [ "$ATTEMPT" -ge "$MAX_ATTEMPTS" ]; then
      echo "Smoke test failed: API health check did not pass at $API_URL"
      echo "Last response: ${HEALTH_BODY:-<empty>}"
      exit 1
    fi
    sleep 2
  done
}

read_xsrf_token() {
  awk '$6 == "XSRF-TOKEN" { print $7 }' "$COOKIE_JAR" | tail -n 1
}

urldecode() {
  node -e 'console.log(decodeURIComponent(process.argv[1]))' "$1"
}

smoke_login_through_admin_proxy() {
  ATTEMPT=0

  while true; do
    curl -sf -c "$COOKIE_JAR" -b "$COOKIE_JAR" \
      -H "Accept: application/json" \
      -H "Origin: ${ADMIN_ORIGIN}" \
      -H "Referer: ${ADMIN_ORIGIN}/" \
      "${ADMIN_ORIGIN}/sanctum/csrf-cookie" >/dev/null || true

    XSRF_TOKEN=$(read_xsrf_token)
    if [ -z "$XSRF_TOKEN" ]; then
      ATTEMPT=$((ATTEMPT + 1))
      if [ "$ATTEMPT" -ge "$MAX_ATTEMPTS" ]; then
        echo "Smoke test failed: could not obtain XSRF-TOKEN through admin proxy"
        exit 1
      fi
      sleep 2
      continue
    fi

    DECODED_XSRF=$(urldecode "$XSRF_TOKEN")

    LOGIN_BODY=$(curl -sf -c "$COOKIE_JAR" -b "$COOKIE_JAR" \
      -X POST "${ADMIN_ORIGIN}/api/login" \
      -H "Accept: application/json" \
      -H "Content-Type: application/json" \
      -H "Origin: ${ADMIN_ORIGIN}" \
      -H "Referer: ${ADMIN_ORIGIN}/" \
      -H "X-XSRF-TOKEN: ${DECODED_XSRF}" \
      -d "{\"email\":\"${SMOKE_LOGIN_EMAIL}\",\"password\":\"${SMOKE_LOGIN_PASSWORD}\"}" || true)

    if [ -n "$LOGIN_BODY" ] && echo "$LOGIN_BODY" | grep -q "\"email\":\"${SMOKE_LOGIN_EMAIL}\""; then
      USER_BODY=$(curl -sf -b "$COOKIE_JAR" \
        -H "Accept: application/json" \
        -H "Origin: ${ADMIN_ORIGIN}" \
        -H "Referer: ${ADMIN_ORIGIN}/" \
        "${ADMIN_ORIGIN}/api/user" || true)

      if [ -n "$USER_BODY" ] && echo "$USER_BODY" | grep -q "\"email\":\"${SMOKE_LOGIN_EMAIL}\""; then
        echo "OK: Admin proxy login passed (${ADMIN_ORIGIN}/api/login)"
        return 0
      fi
    fi

    ATTEMPT=$((ATTEMPT + 1))
    if [ "$ATTEMPT" -ge "$MAX_ATTEMPTS" ]; then
      echo "Smoke test failed: admin proxy login did not return the seeded user"
      echo "Last login response: ${LOGIN_BODY:-<empty>}"
      echo "Last user response: ${USER_BODY:-<empty>}"
      exit 1
    fi
    sleep 2
  done
}

wait_for_api_health
wait_for_url "$ADMIN_URL" "Admin"
wait_for_url "$TIMESHEET_URL" "Timesheet"
smoke_login_through_admin_proxy

echo "Docker smoke test passed."

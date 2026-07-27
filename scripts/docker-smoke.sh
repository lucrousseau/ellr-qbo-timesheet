#!/bin/sh
set -e

API_URL="${API_URL:-http://localhost:8000/api/health}"
ADMIN_URL="${ADMIN_URL:-http://localhost:5173/}"
TIMESHEET_URL="${TIMESHEET_URL:-http://localhost:5174/}"
MAX_ATTEMPTS="${SMOKE_WAIT_ATTEMPTS:-60}"

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

wait_for_api_health
wait_for_url "$ADMIN_URL" "Admin"
wait_for_url "$TIMESHEET_URL" "Timesheet"

echo "Docker smoke test passed."

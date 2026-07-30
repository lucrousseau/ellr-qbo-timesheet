#!/usr/bin/env sh
# Exposes local API (Docker port 8000) for Intuit QuickBooks webhooks.
# Usage: npm run dev:webhook-tunnel
# Then paste the HTTPS URL into Intuit Developer portal (see docs/quickbooks-time-activity-sync.md).

set -e

PORT="${API_PORT:-8000}"
WEBHOOK_PATH="/api/quickbooks/webhook"

if ! command -v ngrok >/dev/null 2>&1; then
  printf '%s\n' \
    "ngrok is not installed." \
    "" \
    "Install:" \
    "  brew install ngrok/ngrok/ngrok" \
    "" \
    "Then sign up at https://dashboard.ngrok.com and run:" \
    "  ngrok config add-authtoken <your-token>" \
    "" \
    "Docs: docs/quickbooks-time-activity-sync.md (Local with webhooks)"
  exit 1
fi

if ! curl -sf "http://127.0.0.1:${PORT}/api/health" >/dev/null 2>&1; then
  printf '%s\n' \
    "API not reachable at http://127.0.0.1:${PORT}/api/health" \
    "Start Docker first: docker compose up -d" \
    ""
fi

printf '%s\n' \
  "Starting ngrok on port ${PORT}..." \
  "" \
  "In Intuit Developer portal > Webhooks > Development:" \
  "  Endpoint URL: https://<ngrok-host>${WEBHOOK_PATH}" \
  "  Entity: TimeActivity only (Create, Update, Delete)" \
  "  Copy verifier token to backend/.env as QUICKBOOKS_WEBHOOK_VERIFIER" \
  "" \
  "Then: npm run sync:restart" \
  "Check tunnel: npm run dev:webhook-tunnel:status" \
  "Queue worker: npm run sync:queue:logs" \
  ""

exec ngrok http "${PORT}"

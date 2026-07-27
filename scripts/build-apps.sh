#!/bin/sh
set -e

npm run build:admin &
ADMIN_PID=$!

npm run build:timesheet &
TIMESHEET_PID=$!

ADMIN_STATUS=0
TIMESHEET_STATUS=0
wait "$ADMIN_PID" || ADMIN_STATUS=$?
wait "$TIMESHEET_PID" || TIMESHEET_STATUS=$?

if [ "$ADMIN_STATUS" -ne 0 ] || [ "$TIMESHEET_STATUS" -ne 0 ]; then
  exit 1
fi

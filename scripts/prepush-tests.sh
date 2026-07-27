#!/bin/sh
set -e

npm run test:coverage --workspaces --parallel &
FRONT_PID=$!

( cd backend && composer test:behat ) &
BEHAT_PID=$!

FRONT_STATUS=0
BEHAT_STATUS=0
wait "$FRONT_PID" || FRONT_STATUS=$?
wait "$BEHAT_PID" || BEHAT_STATUS=$?

if [ "$FRONT_STATUS" -ne 0 ] || [ "$BEHAT_STATUS" -ne 0 ]; then
  exit 1
fi

cd backend && composer test:coverage && composer analyse

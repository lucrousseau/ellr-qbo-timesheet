#!/bin/sh
# Coverage, Behat, PHPStan, and production builds with parallel phases.
# Phase 1: frontend Vitest coverage + Behat (independent).
# Phase 2: backend Pest coverage + PHPStan + Vite builds (independent).
set -e

ROOT_DIR="$(CDPATH= cd "$(dirname "$0")/.." && pwd)"

npm run test:coverage --workspaces --parallel &
FRONT_PID=$!

( cd "$ROOT_DIR/backend" && composer test:behat ) &
BEHAT_PID=$!

FRONT_STATUS=0
BEHAT_STATUS=0
wait "$FRONT_PID" || FRONT_STATUS=$?
wait "$BEHAT_PID" || BEHAT_STATUS=$?

if [ "$FRONT_STATUS" -ne 0 ] || [ "$BEHAT_STATUS" -ne 0 ]; then
  exit 1
fi

( cd "$ROOT_DIR/backend" && composer test:coverage ) &
COVERAGE_PID=$!

( cd "$ROOT_DIR/backend" && composer analyse ) &
ANALYSE_PID=$!

( sh "$ROOT_DIR/scripts/build-apps.sh" ) &
BUILD_PID=$!

COVERAGE_STATUS=0
ANALYSE_STATUS=0
BUILD_STATUS=0
wait "$COVERAGE_PID" || COVERAGE_STATUS=$?
wait "$ANALYSE_PID" || ANALYSE_STATUS=$?
wait "$BUILD_PID" || BUILD_STATUS=$?

if [ "$COVERAGE_STATUS" -ne 0 ] || [ "$ANALYSE_STATUS" -ne 0 ] || [ "$BUILD_STATUS" -ne 0 ]; then
  exit 1
fi

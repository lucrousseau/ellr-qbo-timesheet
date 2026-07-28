#!/bin/sh
set -e

ROOT_DIR="$(CDPATH= cd "$(dirname "$0")/.." && pwd)"
POLICY_SOURCE="${ROOT_DIR}/packages/password-policy/password-policy.json"
POLICY_TARGET="${ROOT_DIR}/backend/config/password-policy.json"
TEST_PASSWORDS_SOURCE="${ROOT_DIR}/packages/password-policy/test-passwords.json"
TEST_PASSWORDS_TARGET="${ROOT_DIR}/backend/config/test-passwords.json"

if [ ! -f "$POLICY_SOURCE" ]; then
  echo "Missing password policy source at ${POLICY_SOURCE}"
  exit 1
fi

if [ ! -f "$TEST_PASSWORDS_SOURCE" ]; then
  echo "Missing test passwords source at ${TEST_PASSWORDS_SOURCE}"
  exit 1
fi

mkdir -p "$(dirname "$POLICY_TARGET")"
cp "$POLICY_SOURCE" "$POLICY_TARGET"
cp "$TEST_PASSWORDS_SOURCE" "$TEST_PASSWORDS_TARGET"

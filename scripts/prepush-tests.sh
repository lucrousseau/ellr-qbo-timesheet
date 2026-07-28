#!/bin/sh
# Back-compat alias for the parallel QA test phase (see scripts/qa-tests.sh).
set -e
exec "$(CDPATH= cd "$(dirname "$0")" && pwd)/qa-tests.sh"

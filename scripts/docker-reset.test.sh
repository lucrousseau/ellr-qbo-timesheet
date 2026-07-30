#!/bin/sh
set -e

ROOT_DIR="$(CDPATH= cd "$(dirname "$0")/.." && pwd)"
ENTRYPOINT="$ROOT_DIR/docker/api/entrypoint.sh"
TMP_DIR="$(mktemp -d)"
trap 'rm -rf "$TMP_DIR"' EXIT

assert_file() {
  if [ ! -f "$1" ]; then
    echo "FAIL: expected file $1"
    exit 1
  fi
}

echo "entrypoint: creates SQLite before composer install..."
resolve_line=$(grep -n 'resolve-sqlite-path.sh /var/www/html' "$ENTRYPOINT" | head -1 | cut -d: -f1)
composer_line=$(grep -n 'composer install --no-interaction' "$ENTRYPOINT" | head -1 | cut -d: -f1)
if [ -z "$resolve_line" ] || [ -z "$composer_line" ] || [ "$resolve_line" -ge "$composer_line" ]; then
  echo "FAIL: entrypoint must resolve/create SQLite before composer install"
  echo "  resolve-sqlite-path line: ${resolve_line:-missing}"
  echo "  composer install line: ${composer_line:-missing}"
  exit 1
fi

echo "docker-reset: removes default SQLite and calls compose down..."
mkdir -p "$TMP_DIR/backend/database" "$TMP_DIR/docker" "$TMP_DIR/fake_bin"
cp "$ROOT_DIR/docker/.env.example" "$TMP_DIR/docker/.env"
touch "$TMP_DIR/backend/database/database.sqlite"
printf '%s\n' 'DB_CONNECTION=sqlite' > "$TMP_DIR/backend/.env"

cat > "$TMP_DIR/fake_bin/docker" <<EOF
#!/bin/sh
if [ "\$1" = "compose" ]; then
  shift
  while [ \$# -gt 0 ]; do
    case "\$1" in
      down) echo down > "$TMP_DIR/compose-down.called" ;;
    esac
    shift
  done
  exit 0
fi
exit 1
EOF
chmod +x "$TMP_DIR/fake_bin/docker"

DOCKER_RESET_ROOT="$TMP_DIR" PATH="$TMP_DIR/fake_bin:$PATH" sh "$ROOT_DIR/scripts/docker-reset.sh" >/dev/null
assert_file "$TMP_DIR/compose-down.called"
if [ -f "$TMP_DIR/backend/database/database.sqlite" ]; then
  echo "FAIL: docker-reset should delete the default SQLite file"
  exit 1
fi

echo "docker-reset: removes custom DB_DATABASE path..."
mkdir -p "$TMP_DIR/custom/backend/storage" "$TMP_DIR/custom/docker" "$TMP_DIR/custom/fake_bin"
cp "$ROOT_DIR/docker/.env.example" "$TMP_DIR/custom/docker/.env"
touch "$TMP_DIR/custom/backend/storage/custom.sqlite"
printf '%s\n' 'DB_CONNECTION=sqlite' 'DB_DATABASE=storage/custom.sqlite' > "$TMP_DIR/custom/backend/.env"

cat > "$TMP_DIR/custom/fake_bin/docker" <<EOF
#!/bin/sh
if [ "\$1" = "compose" ]; then
  exit 0
fi
exit 1
EOF
chmod +x "$TMP_DIR/custom/fake_bin/docker"

DOCKER_RESET_ROOT="$TMP_DIR/custom" PATH="$TMP_DIR/custom/fake_bin:$PATH" sh "$ROOT_DIR/scripts/docker-reset.sh" >/dev/null
if [ -f "$TMP_DIR/custom/backend/storage/custom.sqlite" ]; then
  echo "FAIL: docker-reset should delete a custom SQLite path from DB_DATABASE"
  exit 1
fi

echo "All docker-reset tests passed."

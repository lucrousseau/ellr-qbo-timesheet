#!/bin/sh
set -e

cd /var/www/html

if [ -f /var/www/packages/password-policy/password-policy.json ]; then
  mkdir -p config
  cp /var/www/packages/password-policy/password-policy.json config/password-policy.json
  cp /var/www/packages/password-policy/test-passwords.json config/test-passwords.json
elif [ ! -f config/password-policy.json ]; then
  echo "Missing backend/config/password-policy.json. Run scripts/sync-password-policy.sh on the host."
  exit 1
fi

if [ ! -f .env ]; then
  if [ -f .env.example ]; then
    cp .env.example .env
    echo "Created backend/.env from .env.example"
  else
    echo "Missing backend/.env. Copy backend/.env.example to backend/.env and set QuickBooks credentials."
    exit 1
  fi
fi

# SQLite must exist before composer install (post-autoload-dump runs artisan package:discover).
if SQLITE_FILE=$(/usr/local/bin/resolve-sqlite-path.sh /var/www/html); then
  mkdir -p "$(dirname "$SQLITE_FILE")"
  touch "$SQLITE_FILE"
fi

LOCK_HASH_FILE="vendor/.composer-lock-hash"
CURRENT_HASH=$(md5sum composer.lock | awk '{print $1}')

if [ ! -f vendor/autoload.php ] \
  || [ ! -f "$LOCK_HASH_FILE" ] \
  || [ "$(cat "$LOCK_HASH_FILE")" != "$CURRENT_HASH" ]; then
  composer install --no-interaction --prefer-dist
  echo "$CURRENT_HASH" > "$LOCK_HASH_FILE"
fi

if ! grep -qE '^APP_KEY=base64:.+' .env 2>/dev/null; then
  php artisan key:generate --force
fi

MAX_ATTEMPTS="${MYSQL_WAIT_ATTEMPTS:-60}"
DB_CONNECTION="${DB_CONNECTION:-sqlite}"

if [ "$DB_CONNECTION" = "mysql" ]; then
  ATTEMPT=0
  echo "Waiting for MySQL at ${DB_HOST:-mysql}:${DB_PORT:-3306}..."
  until php -r "
    try {
      new PDO(
        'mysql:host=' . getenv('DB_HOST') . ';port=' . (getenv('DB_PORT') ?: '3306'),
        getenv('DB_USERNAME'),
        getenv('DB_PASSWORD')
      );
      exit(0);
    } catch (Throwable \$e) {
      exit(1);
    }
  " 2>/dev/null; do
    ATTEMPT=$((ATTEMPT + 1))
    if [ "$ATTEMPT" -ge "$MAX_ATTEMPTS" ]; then
      echo "MySQL did not become ready after $MAX_ATTEMPTS attempts."
      exit 1
    fi
    sleep 2
  done
fi

if [ "${CONTAINER_ROLE:-api}" != "queue" ]; then
  php artisan migrate --force

  if [ "$APP_ENV" = "local" ] && [ "$DEV_SEED_ENABLED" = "true" ]; then
    php artisan db:seed --force
  fi
fi

mkdir -p storage/framework/sessions storage/framework/views storage/framework/cache/data storage/logs bootstrap/cache
find storage/framework storage/logs bootstrap/cache -type d -exec chmod ug+rwx {} + 2>/dev/null || true

exec "$@"

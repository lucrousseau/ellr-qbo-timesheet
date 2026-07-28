#!/bin/sh
set -e

cd /var/www/html

if [ ! -f .env ]; then
  if [ -f .env.example ]; then
    cp .env.example .env
    echo "Created backend/.env from .env.example"
  else
    echo "Missing backend/.env. Copy backend/.env.example to backend/.env and set QuickBooks credentials."
    exit 1
  fi
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

if [ "$DB_CONNECTION" = "sqlite" ]; then
  DB_DATABASE="${DB_DATABASE:-database/database.sqlite}"
  case "$DB_DATABASE" in
    /*) SQLITE_FILE="$DB_DATABASE" ;;
    *) SQLITE_FILE="/var/www/html/$DB_DATABASE" ;;
  esac
  mkdir -p "$(dirname "$SQLITE_FILE")"
  touch "$SQLITE_FILE"
elif [ "$DB_CONNECTION" = "mysql" ]; then
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

php artisan migrate --force

if [ "$APP_ENV" = "local" ] && [ "$DEV_SEED_ENABLED" = "true" ]; then
  php artisan db:seed --force
fi

mkdir -p storage/framework/sessions storage/framework/views storage/framework/cache/data storage/logs bootstrap/cache
find storage/framework storage/logs bootstrap/cache -type d -exec chmod ug+rwx {} + 2>/dev/null || true

exec "$@"

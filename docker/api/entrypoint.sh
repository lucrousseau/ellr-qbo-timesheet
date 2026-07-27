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

php artisan migrate --force

mkdir -p storage/framework/sessions storage/framework/views storage/framework/cache/data storage/logs bootstrap/cache
find storage/framework storage/logs bootstrap/cache -type d -exec chmod ug+rwx {} + 2>/dev/null || true

exec "$@"

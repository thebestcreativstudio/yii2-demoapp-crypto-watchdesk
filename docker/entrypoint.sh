#!/bin/sh
set -e
cd /app

if [ ! -f vendor/autoload.php ]; then
  echo "[entrypoint] composer install..."
  composer install --no-interaction --prefer-dist
fi

mkdir -p runtime web/assets
chmod -R 777 runtime web/assets 2>/dev/null || true

if [ -n "${DB_DSN:-}" ]; then
  echo "[entrypoint] waiting for MySQL..."
  i=0
  until php -r 'try { new PDO(getenv("DB_DSN"), getenv("DB_USER") ?: "root", getenv("DB_PASSWORD") ?: ""); exit(0);} catch (Throwable $e) { exit(1);}'; do
    i=$((i + 1))
    [ "$i" -ge 60 ] && echo "[entrypoint] MySQL timeout" >&2 && exit 1
    sleep 1
  done
fi

if [ "${RUN_MIGRATIONS:-0}" = "1" ]; then
  echo "[entrypoint] migrate..."
  php yii migrate --interactive=0
fi

exec "$@"

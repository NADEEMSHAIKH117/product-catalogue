#!/bin/sh
# First-run setup so `docker compose up` is the only command a reviewer needs.
set -e

# vendor/ comes from the image; this is a fast no-op unless composer.lock changed
# since the image was built.
composer install --no-interaction --prefer-dist --no-progress --quiet

if [ ! -f .env ]; then
  echo "No .env found, creating one from .env.example"
  cp .env.example .env
fi

if ! grep -q '^APP_KEY=base64:' .env; then
  php artisan key:generate --force
fi

exec "$@"

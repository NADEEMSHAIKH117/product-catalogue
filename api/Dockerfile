# Development image for the Laravel API.
# Production would use php-fpm + nginx (or Octane) behind a load balancer; see DECISIONS.md.
FROM php:8.4-cli-alpine

RUN apk add --no-cache unzip
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Parallel downloads hit intermittent TLS errors ("bad record mac") on some
# networks with this image; serial is slower but reliable.
ENV COMPOSER_MAX_PARALLEL_HTTP=1

WORKDIR /app

# Install dependencies in a cached layer first, then copy the source.
COPY composer.json composer.lock ./
RUN composer install --no-interaction --no-scripts --no-autoloader --prefer-dist

COPY . .
RUN composer dump-autoload --optimize

COPY docker/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

EXPOSE 8000
ENTRYPOINT ["entrypoint"]
# --no-reload lets PHP_CLI_SERVER_WORKERS serve concurrent requests; code edits still apply live,
# but .env changes need `docker compose restart api`.
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000", "--no-reload"]

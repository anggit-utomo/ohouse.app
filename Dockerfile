# --- build: PHP dependencies and front-end assets in one stage,
# --- because some starter kits run `php artisan` during `npm run build`
FROM docker.io/dunglas/frankenphp:1.13.4-php8.4-alpine AS build
RUN install-php-extensions pdo_pgsql intl zip bcmath
COPY --from=docker.io/library/composer:2 /usr/bin/composer /usr/bin/composer
RUN apk add --no-cache nodejs npm && npm install -g pnpm@12
WORKDIR /app

# dummy key, build stage only. The real key comes from .env when the container starts.
ENV APP_KEY=base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist
COPY package.json pnpm-lock.yaml ./
RUN pnpm install --frozen-lockfile
COPY . .
RUN composer install --no-dev --optimize-autoloader

# Build both the client assets and the self-contained Inertia SSR bundle in
# bootstrap/ssr. Dependencies are inlined, so node_modules can be removed here
# and never copied into the runtime image.
RUN pnpm run build:ssr && rm -rf node_modules

# --- runtime
FROM docker.io/dunglas/frankenphp:1.13.4-php8.4-alpine
RUN install-php-extensions pdo_pgsql opcache intl zip bcmath
# One image, two entrypoints: FrankenPHP for the web service and Node for the
# internal Inertia renderer (`node bootstrap/ssr/ssr.mjs`).
RUN apk add --no-cache nodejs
WORKDIR /app
COPY --from=build /app /app
COPY docker/Caddyfile /etc/frankenphp/Caddyfile
COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh && mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs

ENV APP_ENV=production
ENTRYPOINT ["/entrypoint.sh"]
CMD ["frankenphp", "run", "--config", "/etc/frankenphp/Caddyfile", "--adapter", "caddyfile"]

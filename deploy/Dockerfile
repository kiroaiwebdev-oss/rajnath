# Production image: PHP-FPM 8.4 + Laravel app + Swiss Ephemeris engine + built assets.
# Build from the repository root:  docker build -f deploy/Dockerfile -t arj-app .
FROM node:22-bookworm-slim AS assets
WORKDIR /src/backend
COPY backend/package.json backend/package-lock.json ./
RUN npm ci
COPY backend/ ./
RUN npm run build

FROM debian:bookworm-slim AS engine
RUN apt-get update && apt-get install -y --no-install-recommends gcc make libc6-dev curl ca-certificates && rm -rf /var/lib/apt/lists/*
WORKDIR /src
COPY engine/ engine/
RUN engine/build.sh

FROM php:8.4-fpm-bookworm AS app
# chromium + Noto fonts: server-side PDFs (invoices, reports) with correct Devanagari shaping
RUN apt-get update && apt-get install -y --no-install-recommends libzip-dev libicu-dev libpng-dev libjpeg62-turbo-dev libwebp-dev unzip chromium fonts-noto-core \
 && docker-php-ext-configure gd --with-jpeg --with-webp \
 && docker-php-ext-install -j"$(nproc)" pdo_mysql bcmath intl zip gd opcache pcntl \
 && pecl install redis && docker-php-ext-enable redis \
 && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY deploy/php.ini /usr/local/etc/php/conf.d/zz-arj.ini
ENV PDF_CHROME_BINARY=/usr/bin/chromium
WORKDIR /var/www
COPY engine/ engine/
COPY --from=engine /src/engine/bin engine/bin
COPY --from=engine /src/engine/ephe engine/ephe
COPY backend/ backend/
COPY --from=assets /src/backend/public/build backend/public/build
WORKDIR /var/www/backend
RUN composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader \
 && chown -R www-data:www-data storage bootstrap/cache
USER www-data
CMD ["php-fpm"]

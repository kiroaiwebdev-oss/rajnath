# Codespaces image: PHP 8.4 CLI with the same extensions as production, Composer,
# Chromium + Noto fonts (PDFs with Devanagari), and build tools for the ephemeris engine.
FROM mcr.microsoft.com/devcontainers/php:1-8.4-bookworm

RUN apt-get update && export DEBIAN_FRONTEND=noninteractive \
 && apt-get install -y --no-install-recommends libzip-dev libicu-dev libpng-dev libjpeg62-turbo-dev libwebp-dev libfreetype6-dev \
      mariadb-client chromium fonts-noto-core build-essential curl unzip \
 && docker-php-ext-configure gd --with-jpeg --with-webp --with-freetype \
 && docker-php-ext-install -j"$(nproc)" pdo_mysql bcmath intl zip gd pcntl exif \
 && rm -rf /var/lib/apt/lists/*

# Same limits as production (deploy/php.ini), without opcache timestamps off (code changes live).
RUN { echo 'memory_limit = 512M'; echo 'upload_max_filesize = 10M'; echo 'post_max_size = 96M'; echo 'max_file_uploads = 20'; echo 'max_execution_time = 60'; } \
      > /usr/local/etc/php/conf.d/zz-arj-dev.ini

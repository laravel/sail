##########################################################################
# Reyem Tech - Laravel PHP Runtime Dockerfile
#
# This Dockerfile builds a multi-stage image to support both CLI workers
# and FPM-based web servers, with consistent PHP extension setup.
# It supports Alpine-based PHP 8.x and is optimized for Laravel Sail.
#
# Stages:
# - base:        CLI runtime with PHP extensions
# - builder:     FPM runtime with PHP extensions
# - app-build-*: App layer with dependencies installed
# - worker:      Final image for queue/schedule workers
# - app:         Final image for local development (FPM + Nginx)
# - production:  Final production image (FPM + Nginx, optimized)
##########################################################################

ARG PHP_VERSION="8.4"

# ===========================================
# CLI Base Image Stage (php-cli-alpine)
# ===========================================

# Stage 1: Build dependencies
FROM php:${PHP_VERSION}-cli-alpine AS base

LABEL maintainer="Reyem Tech"

WORKDIR /var/www/

ENV DEBIAN_FRONTEND=noninteractive
ENV TZ=UTC

# Install runtime system dependencies (Alpine packages)
RUN apk add --no-cache \
    tzdata \
    gnupg \
    bash \
    curl \
    libpng \
    libjpeg-turbo \
    libwebp \
    libzip \
    oniguruma \
    icu \
    zlib \
    libxml2 \
    git \
    zip \
    unzip \
    supervisor \
    logrotate \
    libcap-setcap \
    npm

# Install build dependencies for PHP extensions
RUN apk add --no-cache --virtual .build-deps \
    $PHPIZE_DEPS \
    icu-dev \
    libzip-dev \
    freetype-dev \
    libjpeg-turbo-dev \
    libpng-dev \
    libwebp-dev \
    libxpm-dev \
    zlib-dev \
    oniguruma-dev \
    libxml2-dev

# Configure the GD extension with support for JPEG, WebP, and FreeType
RUN docker-php-ext-configure gd \
    --with-freetype \
    --with-jpeg \
    --with-webp \
    CPPFLAGS="-I/usr/local/include/php/TSRM"

# Fix for intl extension on AMD64 architecture on Alpine
RUN docker-php-source delete && docker-php-source extract \
    && mkdir -p /usr/src/php/ext/intl/{formatter,normalizer,collator,modules,converter} \
    && [ -f /usr/src/php/ext/intl/libtool ] && chmod +x /usr/src/php/ext/intl/libtool || true \
    && chmod -R u+rwX /usr/src/php/ext/intl \
    && export CFLAGS="-O2 -march=native" \
    && export CXXFLAGS="$CFLAGS"

# Install PHP extensions using docker-php-ext-install
RUN docker-php-ext-install -j$(nproc) \
    pdo_mysql
RUN docker-php-ext-install -j$(nproc) \
    zip
RUN docker-php-ext-install -j$(nproc) \
    intl
RUN docker-php-ext-install -j$(nproc) \
    bcmath
RUN docker-php-ext-install -j$(nproc) \
    gd
RUN docker-php-ext-install -j$(nproc) \
    opcache
RUN docker-php-ext-install -j$(nproc) \
    xml
RUN docker-php-ext-install -j$(nproc) \
    pcntl

# Install Redis extension via PECL
RUN pecl install redis

RUN docker-php-ext-enable redis

# Remove build dependencies to reduce image size
RUN apk del .build-deps

# Create log directories required by Laravel and Supervisor
RUN mkdir -p /var/log/nginx \
    /var/log/php \
    /var/log/supervisord \
    /var/log/laravel/scheduler \
    /var/log/laravel/queue \
    /var/log/logrotate

# Set correct permissions for log directories
RUN chown -R www-data:www-data /var/log

# Copy utility scripts and configuration files from the runtime layer
# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copy mkcert root CA certificate
COPY --from=runtime certs/mkcert-rootCA.pem /usr/local/share/ca-certificates/mkcert-rootCA.crt
RUN update-ca-certificates

# Allow PHP to bind to low-numbered ports if necessary
RUN setcap "cap_net_bind_service=+ep" /usr/local/bin/php

# ===========================================
# FPM Builder Stage (php-fpm-alpine)
# ===========================================

# Stage 1: Build dependencies
FROM php:${PHP_VERSION}-fpm-alpine AS builder

LABEL maintainer="Reyem Tech"

WORKDIR /var/www/

ENV DEBIAN_FRONTEND=noninteractive
ENV TZ=UTC
ENV SUPERVISOR_PHP_COMMAND="/usr/local/sbin/php-fpm -F"
ENV SUPERVISOR_PHP_USER="sail"

# Install runtime system dependencies (Alpine packages)
RUN apk add --no-cache \
    tzdata \
    gnupg \
    bash \
    curl \
    libpng \
    libjpeg-turbo \
    libwebp \
    libzip \
    oniguruma \
    icu \
    zlib \
    libxml2 \
    git \
    zip \
    unzip \
    nginx \
    supervisor \
    logrotate \
    npm \
    libcap-setcap

# Install build dependencies for PHP extensions
RUN apk add --no-cache --virtual .build-deps \
    $PHPIZE_DEPS \
    icu-dev \
    libzip-dev \
    freetype-dev \
    libjpeg-turbo-dev \
    libpng-dev \
    libwebp-dev \
    libxpm-dev \
    zlib-dev \
    oniguruma-dev \
    libxml2-dev

# Configure the GD extension with support for JPEG, WebP, and FreeType
RUN docker-php-ext-configure gd \
    --with-freetype \
    --with-jpeg \
    --with-webp \
    CPPFLAGS="-I/usr/local/include/php/TSRM"

# Fix for intl extension on AMD64 architecture on Alpine
RUN docker-php-source delete && docker-php-source extract \
    && mkdir -p /usr/src/php/ext/intl/{formatter,normalizer,collator,modules,converter} \
    && [ -f /usr/src/php/ext/intl/libtool ] && chmod +x /usr/src/php/ext/intl/libtool || true \
    && chmod -R u+rwX /usr/src/php/ext/ \
    && export CFLAGS="-O2 -march=native" \
    && export CXXFLAGS="$CFLAGS"

# Install PHP extensions using docker-php-ext-install
RUN docker-php-ext-install -j$(nproc) \
    pdo_mysql
RUN docker-php-ext-install -j$(nproc) \
    zip
RUN docker-php-ext-install -j$(nproc) \
    intl
RUN docker-php-ext-install -j$(nproc) \
    bcmath
RUN docker-php-ext-install -j$(nproc) \
    gd
RUN docker-php-ext-install -j$(nproc) \
    opcache
RUN docker-php-ext-install -j$(nproc) \
    xml
RUN docker-php-ext-install -j$(nproc) \
    pcntl

# Install Redis extension via PECL
RUN pecl install redis

RUN docker-php-ext-enable redis

# Remove build dependencies to reduce image size
RUN apk del .build-deps

# Create log directories required by Laravel and Supervisor
RUN mkdir -p /var/log/nginx \
    /var/log/php \
    /var/log/supervisord \
    /var/log/laravel/scheduler \
    /var/log/laravel/queue \
    /var/log/logrotate

# Set correct permissions for log directories
RUN chown -R www-data:www-data /var/log

RUN rm -rf /usr/local/etc/php-fpm.d/docker.conf

# Copy utility scripts and configuration files from the runtime layer
# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copy Sail files
COPY --from=runtime start-container /usr/local/bin/start-container
COPY --from=runtime php.ini /usr/local/etc/php/conf.d/99-sail.ini
COPY --from=runtime laravel-queue /usr/local/bin/laravel-queue
COPY --from=runtime logrotate-cron.sh /usr/local/bin/logrotate-cron.sh
COPY --from=runtime supervisord.conf /etc/supervisord.conf
COPY --from=runtime nginx.conf /etc/nginx/http.d/default.conf
COPY --from=runtime php-fpm.conf /usr/local/etc/php-fpm.d/docker.conf

# Copy mkcert root CA certificate
COPY --from=runtime certs/mkcert-rootCA.pem /usr/local/share/ca-certificates/mkcert-rootCA.crt
RUN update-ca-certificates

# Install Minio Client
RUN curl -sSL https://dl.min.io/client/mc/release/linux-amd64/mc -o /usr/local/bin/mc \
    && chmod +x /usr/local/bin/mc

# Allow PHP to bind to low-numbered ports if necessary
RUN setcap "cap_net_bind_service=+ep" /usr/local/bin/php

# ==========================================
# Application build - CLI
# ==========================================

FROM base AS app-build-cli
LABEL maintainer="Reyem Tech"

# Clean application directory
RUN rm -rf /var/www/*

COPY --from=mainapp --chown=www-data:www-data / /var/www/

# Recreate empty storage structure
RUN rm -rf /var/www/storage /var/www/vendor /var/www/node_modules \
    && mkdir -p /var/www/storage/app \
    /var/www/bootstrap/cache \
    /var/www/storage/framework/cache \
    /var/www/storage/framework/sessions \
    /var/www/storage/framework/views \
    /var/www/storage/logs

RUN chown -R www-data:www-data /var/www/storage

RUN chmod -R 755 /var/www/storage

# Set proper permissions
RUN chown -R www-data:www-data /var/www

RUN chmod -R 755 /var/www/storage /var/www/bootstrap/cache

# Install PHP dependencies using Composer
RUN composer install --no-dev --optimize-autoloader

# Install and build frontend assets using npm
RUN npm ci

RUN npm run build

# Clean up build artifacts to reduce final image size
RUN rm -rf /var/www/node_modules \
    /var/www/vendor

# ==========================================
# Application build - FPM
# ==========================================

FROM builder AS app-build-fpm
LABEL maintainer="Reyem Tech"

# Clean application directory
RUN rm -rf /var/www/*

COPY --from=mainapp --chown=www-data:www-data / /var/www/

# Recreate empty storage structure
RUN rm -rf /var/www/storage /var/www/vendor /var/www/node_modules \
    && mkdir -p /var/www/storage/app \
    /var/www/bootstrap/cache \
    /var/www/storage/framework/cache \
    /var/www/storage/framework/sessions \
    /var/www/storage/framework/views \
    /var/www/storage/logs

RUN chown -R www-data:www-data /var/www/storage

RUN chmod -R 755 /var/www/storage

# Set proper permissions
RUN chown -R www-data:www-data /var/www

RUN chmod -R 755 /var/www/storage /var/www/bootstrap/cache

# Install PHP dependencies using Composer
RUN composer install --no-dev --optimize-autoloader

# Clean up build artifacts to reduce final image size
RUN rm -rf /var/www/node_modules \
    /var/www/vendor

# ===========================================
# Final Image: Worker (CLI-based)
# ===========================================

FROM php:${PHP_VERSION}-cli-alpine AS worker
LABEL maintainer="Reyem Tech"
WORKDIR /var/www/

COPY --from=base /usr/local /usr/local
COPY --from=base /etc/ssl/certs /etc/ssl/certs
COPY --from=base /var/log /var/log
COPY --from=base /usr/bin/supervisord /usr/bin/supervisord
COPY --from=app-build-cli /var/www/ /var/www/

COPY --from=runtime ./supervisord-worker.conf /etc/supervisord.conf

# Install runtime system dependencies (Alpine packages)
RUN apk add --no-cache supervisor \
    libjpeg-turbo \
    libpng \
    freetype \
    libwebp \
    libxpm \
    libzip \
    oniguruma \
    icu-libs \
    zlib \
    libxml2 \
    rm -rf /var/cache/apk/*

COPY --from=runtime ./start-container-worker /usr/local/bin/start-container

RUN chmod +x /usr/local/bin/start-container

ENTRYPOINT ["start-container"]

# Start Supervisor to manage Laravel processes
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisord.conf"]

# ===========================================
# Final Image: Local Application (FPM + Nginx)
# ===========================================

# Stage 2: Final image
FROM php:${PHP_VERSION}-fpm-alpine AS app

LABEL maintainer="Reyem Tech"

WORKDIR /var/www/

# Copy necessary files from builder stage
COPY --from=builder /usr/local /usr/local
COPY --from=builder /etc/ssl/certs /etc/ssl/certs
COPY --from=builder /var/log /var/log
COPY --from=builder /usr/bin/supervisord /usr/bin/supervisord
COPY --from=builder /bin/bash /bin/bash
COPY --from=builder /etc/nginx /etc/nginx
COPY --from=builder /etc/supervisord.conf /etc/supervisord.conf
COPY --from=builder /usr/share/zoneinfo /usr/share/zoneinfo
COPY --from=builder /usr/bin/composer /usr/bin/composer
COPY --from=builder /usr/local/bin /usr/local/bin

ARG WWWGROUP=1000
ARG WWWUSER=1000

# Finalize the image
RUN addgroup -g ${WWWGROUP} sail \
    && adduser -D -u ${WWWUSER} -G sail sail \
    && chmod +x /usr/local/bin/start-container \
    # Install runtime system dependencies (Alpine packages)
    && apk add --no-cache nginx \
    supervisor \
    libjpeg-turbo \
    libpng \
    freetype \
    libwebp \
    libxpm \
    libzip \
    oniguruma \
    icu-libs \
    zlib \
    libxml2 \
    npm \
    && chown -R sail:sail /var/ \
    && chmod -R 755 /var/log \
    && rm -rf /var/cache/apk/*
    # \
    # && rm -rf /usr/local/etc/php-fpm.d/docker.conf

EXPOSE 80/tcp

ENTRYPOINT ["start-container"]

# Start Supervisor to manage Laravel processes
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisord.conf"]

# ===========================================
# Final Image: Production (FPM + Nginx, optimized)
# ===========================================

# Stage 2: Final image
FROM php:${PHP_VERSION}-fpm-alpine AS production

LABEL maintainer="Reyem Tech"

WORKDIR /var/www/

# Copy necessary files from builder stage
COPY --from=builder /usr/local /usr/local
COPY --from=builder /etc/ssl/certs /etc/ssl/certs
COPY --from=builder /var/log /var/log
COPY --from=builder /usr/bin/supervisord /usr/bin/supervisord
COPY --from=builder /bin/bash /bin/bash
COPY --from=builder /etc/nginx /etc/nginx
COPY --from=runtime ./supervisord-app.conf /etc/supervisord.conf
COPY --from=builder /usr/share/zoneinfo /usr/share/zoneinfo
COPY --from=builder /usr/bin/composer /usr/bin/composer
COPY --from=builder /usr/local/bin /usr/local/bin
COPY --from=app-build-fpm /var/www/ /var/www/

ARG WWWGROUP=1000
ARG WWWUSER=1000

# Finalize the image
RUN addgroup -g ${WWWGROUP} sail \
    && adduser -D -u ${WWWUSER} -G sail sail \
    && chmod +x /usr/local/bin/start-container \
    # Install runtime system dependencies (Alpine packages)
    && apk add --no-cache nginx \
    supervisor \
    libjpeg-turbo \
    libpng \
    freetype \
    libwebp \
    libxpm \
    libzip \
    oniguruma \
    icu-libs \
    zlib \
    libxml2 \
    npm \
    && chown -R sail:sail /var/ \
    && chmod -R 755 /var/log \
    && rm -rf /var/cache/apk/*
# \
# && rm -rf /usr/local/etc/php-fpm.d/docker.conf

EXPOSE 80/tcp

ENTRYPOINT ["start-container"]

# Start Supervisor to manage Laravel processes
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisord.conf"]

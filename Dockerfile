# ============================================================
# Stage 1 — Node: compile Vite assets (Livewire + Alpine bundle)
# Runtime image contains no Node tooling (ADR-002).
# ============================================================
FROM node:22-alpine AS assets

WORKDIR /build

COPY package.json package-lock.json ./
RUN npm ci --ignore-scripts

COPY vite.config.js ./
COPY resources/ ./resources/
COPY public/ ./public/

RUN npm run build


# ============================================================
# Stage 2 — PHP-FPM runtime (nginx proxy sits in front)
# ============================================================
FROM php:8.4-fpm-alpine AS runtime

# ---- system deps + PHP extensions -------------------------
RUN apk add --no-cache \
        $PHPIZE_DEPS \
        bash \
        git \
        unzip \
        mysql-client \
        libpng-dev \
        libjpeg-turbo-dev \
        freetype-dev \
        libzip-dev \
        icu-dev \
        oniguruma-dev \
        linux-headers \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_mysql \
        mbstring \
        bcmath \
        pcntl \
        opcache \
        intl \
        zip \
        gd \
        exif \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && rm -rf /tmp/pear

# ---- opcache config ----------------------------------------
COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/opcache.ini

# ---- upload limits -------------------------------------------
COPY docker/php/uploads.ini /usr/local/etc/php/conf.d/uploads.ini

# ---- composer ----------------------------------------------
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# ---- non-root user -----------------------------------------
# php:fpm-alpine ships www-data (uid 82). We use a dedicated
# app user (uid 1000) so volume mounts align with common hosts.
RUN addgroup -g 1000 app && adduser -u 1000 -G app -s /bin/sh -D app

WORKDIR /var/www/html

# ---- composer deps (no dev) --------------------------------
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-scripts --prefer-dist --optimize-autoloader

# ---- application code --------------------------------------
COPY . .

# ---- compiled assets from stage 1 -------------------------
COPY --from=assets /build/public/build ./public/build

# ---- storage dirs + permissions ----------------------------
# vendor/ is included since every RUN before this point (including
# composer install above) executes as root by default — php-fpm runs
# as `app` below, and some packages need to write into their own
# vendor subdirectory (e.g. cache dirs) even outside of testing.
RUN mkdir -p storage/app/private \
             storage/app/public \
             storage/framework/cache/data \
             storage/framework/sessions \
             storage/framework/views \
             storage/logs \
             bootstrap/cache \
    && chown -R app:app storage bootstrap/cache vendor \
    && chmod -R 755 storage bootstrap/cache

# ---- php-fpm runs as app user ------------------------------
RUN sed -i 's/user = www-data/user = app/' /usr/local/etc/php-fpm.d/www.conf \
    && sed -i 's/group = www-data/group = app/' /usr/local/etc/php-fpm.d/www.conf

# ---- entrypoint: storage skeleton, opt-in migrations/seeders on
# the app role only, cache warming (see docker/entrypoint.sh) -----
COPY docker/entrypoint.sh /usr/local/bin/onyx-aip-entrypoint
RUN chmod +x /usr/local/bin/onyx-aip-entrypoint

USER app

EXPOSE 9000

ENTRYPOINT ["onyx-aip-entrypoint"]
CMD ["php-fpm"]


# ============================================================
# Stage 3 — nginx: serves public/ (Vite build included), proxies
# PHP requests to app:9000. Baking public/ in here instead of a
# shared volume means every redeploy ships exactly what this image
# build produced — no stale-volume risk, no sync step needed.
# ============================================================
FROM nginx:alpine AS nginx

COPY --from=runtime /var/www/html/public /var/www/html/public
COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf

EXPOSE 80


# ============================================================
# Stage 4 — CI test image: adds dev dependencies (Pint/Larastan/
# Pest) on top of the runtime image. Never shipped to production —
# docker-compose.yml pins the app/queue/scheduler services to the
# `runtime` target and nginx to the `nginx` target above.
# `tests/` is excluded by .dockerignore to keep the runtime image
# lean, so CI bind-mounts it in at `docker run` time instead of
# baking it into this layer.
# ============================================================
FROM runtime AS test

USER root
RUN composer install --no-interaction --no-scripts --prefer-dist --optimize-autoloader \
    && chown -R app:app vendor
USER app

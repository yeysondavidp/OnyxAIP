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
FROM php:8.3-fpm-alpine AS runtime

# ---- system deps + PHP extensions -------------------------
RUN apk add --no-cache \
        bash \
        git \
        unzip \
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
RUN mkdir -p storage/framework/{cache,sessions,views} \
             storage/logs \
             bootstrap/cache \
    && chown -R app:app storage bootstrap/cache \
    && chmod -R 755 storage bootstrap/cache

# ---- php-fpm runs as app user ------------------------------
RUN sed -i 's/user = www-data/user = app/' /usr/local/etc/php-fpm.d/www.conf \
    && sed -i 's/group = www-data/group = app/' /usr/local/etc/php-fpm.d/www.conf

USER app

EXPOSE 9000

CMD ["php-fpm"]

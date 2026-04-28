FROM php:8.3-apache-bookworm

ARG DEBIAN_FRONTEND=noninteractive

# Build deps for the PHP extensions we need.
# - libmemcached + zlib + ssl: for the `memcached` PECL ext
# - libcurl: for `curl`
# - libpng/jpeg/freetype: for `gd` (charts/captcha)
# - libicu: for `intl`
# - libgmp: for `gmp` (large-int math)
# - libxml2 + libzip: standard
RUN apt-get update && apt-get install -y --no-install-recommends \
        libmemcached-dev \
        zlib1g-dev \
        libssl-dev \
        libcurl4-openssl-dev \
        libpng-dev \
        libjpeg-dev \
        libfreetype6-dev \
        libicu-dev \
        libgmp-dev \
        libxml2-dev \
        libzip-dev \
        git \
        unzip \
        nano \
    && rm -rf /var/lib/apt/lists/*

# Core PHP extensions MPOS needs.
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        mysqli \
        pdo_mysql \
        gd \
        bcmath \
        gmp \
        intl \
        zip \
        opcache \
        sockets

# `memcached` (libmemcached-backed; this is the modern ext that the
# bundled custom wrapper class is trying to emulate).
RUN pecl install memcached \
    && docker-php-ext-enable memcached

# Composer (pinned major version, latest patch via the official image).
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Apache: enable mod_rewrite + headers, install our vhost.
RUN a2enmod rewrite headers expires
COPY docker/apache-vhost.conf /etc/apache2/sites-available/000-default.conf

# Dev-friendly php.ini overrides (display errors, opcache tuned for dev).
COPY docker/php.ini /usr/local/etc/php/conf.d/zz-mpos.ini

WORKDIR /var/www/html

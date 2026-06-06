ARG PHP_VERSION=8.3
FROM php:${PHP_VERSION}-apache

ARG DEBIAN_FRONTEND=noninteractive

RUN set -eux; \
    . /etc/os-release; \
    if [ "${VERSION_CODENAME:-}" = "buster" ]; then \
        sed -i 's|http://deb.debian.org/debian|http://archive.debian.org/debian|g' /etc/apt/sources.list; \
        sed -i 's|http://security.debian.org/debian-security|http://archive.debian.org/debian-security|g' /etc/apt/sources.list; \
        sed -i 's|http://deb.debian.org/debian-security|http://archive.debian.org/debian-security|g' /etc/apt/sources.list; \
        sed -i '/buster-updates/d' /etc/apt/sources.list; \
        printf 'Acquire::Check-Valid-Until "false";\n' > /etc/apt/apt.conf.d/99archive; \
    fi; \
    apt-get update -o Acquire::Check-Valid-Until=false; \
    apt-get install -y --no-install-recommends \
        bash \
        ca-certificates \
        curl \
        git \
        unzip \
        zip \
        expect \
        default-mysql-client \
        libcurl4-openssl-dev \
        libfreetype6-dev \
        libjpeg62-turbo-dev \
        libpng-dev \
        libwebp-dev \
        libzip-dev \
        libicu-dev \
        libgmp-dev \
        libonig-dev \
        libxml2-dev \
    ; \
    if php -r 'exit(PHP_VERSION_ID < 70400 ? 0 : 1);'; then \
        docker-php-ext-configure gd --with-freetype-dir=/usr/include/ --with-jpeg-dir=/usr/include/; \
    else \
        docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp; \
    fi; \
    docker-php-ext-install -j"$(nproc)" \
        mysqli \
        pdo_mysql \
        gd \
        intl \
        zip \
        gmp \
        exif \
        opcache \
        mbstring \
    ; \
    a2enmod rewrite headers; \
    rm -rf /var/lib/apt/lists/*

# phpredis (PECL) for XenForo Redis caching. phpredis 6.x needs PHP 7.4+, so pin
# 5.3.7 for older PHP (e.g. 7.2). docker-php-ext-enable loads it for CLI + Apache.
RUN set -eux; \
    if php -r 'exit(PHP_VERSION_ID < 70400 ? 0 : 1);'; then \
        pecl install redis-5.3.7; \
    else \
        pecl install redis; \
    fi; \
    docker-php-ext-enable redis; \
    php -m | grep -qi '^redis$'

COPY docker/php/xenforo.ini /usr/local/etc/php/conf.d/zz-xenforo.ini
COPY tools/xf-auto-install.expect /usr/local/bin/xf-auto-install
RUN chmod +x /usr/local/bin/xf-auto-install

WORKDIR /var/www/html

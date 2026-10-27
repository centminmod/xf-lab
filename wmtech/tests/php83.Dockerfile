FROM php:8.3.36-apache@sha256:0d46cb96775228313d3997c155f78227de13bf9972e0c736e653fb23aded79b2
RUN apt-get update && apt-get install -y --no-install-recommends ca-certificates libfreetype6-dev libjpeg62-turbo-dev libpng-dev libwebp-dev libonig-dev libzip-dev \
 && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
 && docker-php-ext-install -j4 mysqli gd mbstring zip \
 && a2enmod rewrite headers ssl \
 && rm -rf /var/lib/apt/lists/*
WORKDIR /var/www/html

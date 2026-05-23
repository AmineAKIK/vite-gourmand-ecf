FROM php:8.2-cli

RUN apt-get update && apt-get install -y \
    libzip-dev libpng-dev libssl-dev pkg-config libcurl4-openssl-dev \
    zip unzip curl autoconf g++ make \
    && docker-php-ext-install pdo pdo_mysql zip \
    && pecl install mongodb-2.1.0 \
    && docker-php-ext-enable mongodb \
    && apt-get remove -y autoconf g++ make \
    && apt-get autoremove -y \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
COPY . .

ENV COMPOSER_ALLOW_SUPERUSER=1
RUN composer install --no-dev --optimize-autoloader --no-interaction

CMD ["sh", "-c", "php -d upload_max_filesize=20M -d post_max_size=25M -d memory_limit=128M -S 0.0.0.0:${PORT:-8080} -t public"]

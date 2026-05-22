FROM php:8.2-fpm-alpine

RUN apk add --no-cache \
    nginx zip unzip curl gettext \
    libpng-dev openssl-dev pkgconfig \
    libzip-dev autoconf g++ make

RUN docker-php-ext-install pdo pdo_mysql zip \
    && pecl install mongodb-2.1.0 \
    && docker-php-ext-enable mongodb \
    && apk del autoconf g++ make

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
COPY . .

ENV COMPOSER_ALLOW_SUPERUSER=1
RUN composer install --no-dev --optimize-autoloader --no-interaction

COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/start.sh /start.sh
RUN chmod +x /start.sh

EXPOSE 80
CMD ["/start.sh"]

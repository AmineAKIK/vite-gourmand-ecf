FROM php:8.2-apache

RUN apt-get update && apt-get install -y \
    libzip-dev libpng-dev libssl-dev pkg-config \
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

RUN a2dismod mpm_event mpm_worker 2>/dev/null || true \
    && a2enmod mpm_prefork rewrite \
    && sed -i 's|DocumentRoot /var/www/html|DocumentRoot /var/www/html/public|g' \
        /etc/apache2/sites-available/000-default.conf \
    && echo '<Directory /var/www/html/public>\n    AllowOverride All\n    Require all granted\n</Directory>' \
        >> /etc/apache2/sites-available/000-default.conf

EXPOSE 80
CMD ["apache2-foreground"]

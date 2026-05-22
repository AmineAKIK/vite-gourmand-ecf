FROM php:8.2-apache

RUN apt-get update && apt-get install -y \
    libzip-dev zip unzip curl libpng-dev libssl-dev pkg-config \
    && docker-php-ext-install pdo pdo_mysql zip \
    && pecl install mongodb \
    && docker-php-ext-enable mongodb \
    && a2enmod rewrite headers

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
COPY . .

RUN composer install --no-dev --optimize-autoloader --no-interaction

RUN sed -i 's|DocumentRoot /var/www/html|DocumentRoot /var/www/html/public|g' \
    /etc/apache2/sites-available/000-default.conf && \
    echo '<Directory /var/www/html/public>\n    AllowOverride All\n    Require all granted\n    Options -Indexes\n</Directory>' \
    >> /etc/apache2/sites-available/000-default.conf

EXPOSE 80
CMD ["apache2-foreground"]

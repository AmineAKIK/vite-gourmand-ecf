FROM php:8.2.33-apache-bookworm

RUN apt-get update && apt-get install -y --no-install-recommends \
    libzip-dev libcurl4-openssl-dev curl unzip \
    && docker-php-ext-install pdo_mysql zip curl opcache \
    && a2enmod rewrite headers expires \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2.10.2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
COPY composer.json composer.lock ./
ENV COMPOSER_ALLOW_SUPERUSER=1
RUN composer install --no-dev --prefer-dist --optimize-autoloader --classmap-authoritative --no-interaction --no-progress

COPY . .
COPY docker/php.ini /usr/local/etc/php/conf.d/zz-tugeres-production.ini

RUN sed -ri 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/000-default.conf \
    && printf '<Directory /var/www/html/public>\n    AllowOverride All\n    Require all granted\n</Directory>\n' > /etc/apache2/conf-available/tugeres.conf \
    && a2enconf tugeres \
    && chmod +x /var/www/html/docker/entrypoint.sh \
    && chown -R www-data:www-data /var/www/html/public/uploads /var/www/html/storage 2>/dev/null || true

ENV PORT=8080
EXPOSE 8080

HEALTHCHECK --interval=30s --timeout=3s --start-period=20s --retries=3 \
    CMD curl --fail --silent --show-error "http://127.0.0.1:${PORT}/health" >/dev/null || exit 1

ENTRYPOINT ["/var/www/html/docker/entrypoint.sh"]

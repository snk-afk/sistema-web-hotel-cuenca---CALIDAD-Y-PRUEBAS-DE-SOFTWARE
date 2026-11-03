FROM php:8.4-apache

# Instalar dependencias sin paquetes recomendados
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git unzip libzip-dev libpng-dev \
        libjpeg62-turbo-dev libfreetype6-dev libicu-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo_mysql mysqli gd zip intl \
    && rm -rf /var/lib/apt/lists/*

# Configurar Apache para servir public/ en un puerto sin privilegios
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public

RUN a2enmod rewrite \
    && sed -ri 's/^Listen 80$/Listen 8080/' /etc/apache2/ports.conf \
    && sed -ri \
        -e 's!<VirtualHost \*:80>!<VirtualHost *:8080>!g' \
        -e "s!/var/www/html!${APACHE_DOCUMENT_ROOT}!g" \
        /etc/apache2/sites-available/*.conf \
    && printf '%s\n' \
        "<Directory ${APACHE_DOCUMENT_ROOT}>" \
        '    AllowOverride All' \
        '    Require all granted' \
        '</Directory>' \
        > /etc/apache2/conf-available/hotel.conf \
    && a2enconf hotel

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

WORKDIR /var/www/html

COPY composer.json composer.lock .htaccess router.php ./
COPY config/ ./config/
COPY database/ ./database/
COPY public/ ./public/
COPY routes/ ./routes/
COPY src/ ./src/
COPY storage/ ./storage/
COPY views/ ./views/

RUN composer install \
        --no-dev \
        --no-interaction \
        --prefer-dist \
        --optimize-autoloader \
    && mkdir -p /var/run/apache2 /var/lock/apache2 \
    && chown -R www-data:www-data \
        /var/run/apache2 /var/lock/apache2 /var/log/apache2 /var/www/html/storage

USER www-data

EXPOSE 8080
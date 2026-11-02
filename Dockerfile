FROM php:8.4-apache

# Instalar dependencias
RUN apt-get update && apt-get install -y \
    git unzip libzip-dev libpng-dev \
    libjpeg62-turbo-dev libfreetype6-dev libicu-dev \
    && docker-php-ext-configure gd \
        --with-freetype --with-jpeg \
    && docker-php-ext-install \
        pdo_mysql mysqli gd zip intl \
    && rm -rf /var/lib/apt/lists/*

# Activar rutas amigables
RUN a2enmod rewrite

# Apuntar Apache a public/
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public

RUN sed -ri \
    -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' \
    /etc/apache2/sites-available/*.conf

RUN printf '<Directory /var/www/html/public>\n\
AllowOverride All\n\
Require all granted\n\
</Directory>\n' \
    > /etc/apache2/conf-available/hotel.conf \
    && a2enconf hotel

# Instalar Composer
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

WORKDIR /var/www/html

# Copiar el proyecto
COPY . .

# Instalar dependencias PHP
RUN composer install \
    --no-dev \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader

EXPOSE 80
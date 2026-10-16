FROM php:8.4-apache

# Instalar extensiones necesarias de MySQL
RUN docker-php-ext-install pdo pdo_mysql mysqli

# Copiar el código del proyecto al directorio web de Apache
COPY . /var/www/html/

# Dar permisos y habilitar reescritura de URLs
RUN chown -R www-data:www-data /var/www/html \
    && a2enmod rewrite

# Railway asigna un puerto dinámico mediante la variable PORT, ajustamos Apache para que escuche en él
RUN sed -i 's/80/${PORT}/g' /etc/apache2/sites-available/000-default.conf /etc/apache2/ports.conf

EXPOSE ${PORT}
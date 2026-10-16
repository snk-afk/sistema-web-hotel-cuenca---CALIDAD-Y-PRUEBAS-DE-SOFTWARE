FROM php:8.4-apache

# Instalar extensiones necesarias de MySQL
RUN docker-php-ext-install pdo pdo_mysql mysqli

# Cambiar la ruta predeterminada de Apache a la carpeta public de tu proyecto
ENV APACHE_DOCUMENT_ROOT /var/www/html/public

RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Copiar el código del proyecto
COPY . /var/www/html/

# Dar permisos y habilitar reescritura de URLs
RUN chown -R www-data:www-data /var/www/html \
    && a2enmod rewrite

# Ajustar el puerto de Apache para que coincida con el que exige Railway
RUN sed -i 's/80/${PORT}/g' /etc/apache2/sites-available/000-default.conf /etc/apache2/ports.conf

EXPOSE ${PORT}
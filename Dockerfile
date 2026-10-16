FROM php:8.4-apache

# 1. Instalar extensiones necesarias de MySQL
RUN docker-php-ext-install pdo pdo_mysql mysqli

# 2. Resolver el conflicto de múltiples MPM deshabilitando event y asegurando prefork
RUN a2dismod mpm_event mpm_worker || true \
    && a2enmod mpm_prefork

# 3. Configurar la ruta pública de Apache (si tu proyecto usa la carpeta public)
ENV APACHE_DOCUMENT_ROOT /var/www/html/public

RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# 4. Copiar el código y dar permisos
COPY . /var/www/html/
RUN chown -R www-data:www-data /var/www/html \
    && a2enmod rewrite

# 5. Adaptar el puerto para Railway
RUN sed -i 's/80/${PORT}/g' /etc/apache2/sites-available/000-default.conf /etc/apache2/ports.conf
EXPOSE ${PORT}
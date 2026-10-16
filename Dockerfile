FROM php:8.4-apache

# Instalar el driver de PDO MySQL que le falta a PHP
RUN docker-php-ext-install pdo pdo_mysql mysqli

# Configurar directorio de trabajo
WORKDIR /var/www/html
COPY . /var/www/html

# Habilitar mod_rewrite para rutas amigables
RUN a2enmod rewrite
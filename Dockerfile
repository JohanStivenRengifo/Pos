# Utilizar una imagen base de PHP con Apache
FROM php:8.1-apache

# Instalar extensiones de PHP necesarias
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Copiar los archivos de tu aplicación al contenedor
COPY . /var/www/html/

# Cambiar el directorio de trabajo
WORKDIR /var/www/html

# Exponer el puerto 80
EXPOSE 80
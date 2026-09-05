FROM php:8.2-apache
RUN docker-php-ext-install pdo pdo_mysql && a2enmod rewrite
WORKDIR /var/www/html
COPY index.php /var/www/html/index.php
EXPOSE 80
CMD ["apache2-foreground"]

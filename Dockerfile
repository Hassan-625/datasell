FROM php:8.2-cli
RUN docker-php-ext-install pdo_mysql
WORKDIR /app
COPY . /app
EXPOSE 80
CMD ["sh", "-c", "php -d auto_prepend_file=/app/bootstrap_roles.php -S 0.0.0.0:${PORT:-80} -t /app"]

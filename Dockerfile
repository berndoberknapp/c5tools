FROM serversideup/php:8.4-fpm-nginx-alpine
EXPOSE 8080

USER root
RUN install-php-extensions gd
ENV PHP_MEMORY_LIMIT 4096M
ENV PHP_MAX_EXECUTION_TIME 1200
COPY custom-php.ini /usr/local/etc/php/conf.d/

USER www-data
COPY --chown=www-data:www-data . /var/www/html
WORKDIR /var/www/html
RUN composer install --no-interaction --optimize-autoloader

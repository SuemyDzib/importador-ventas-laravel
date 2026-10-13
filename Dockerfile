FROM php:8.5-apache

# Extensiones para PhpSpreadsheet (fileinfo, mbstring y pdo_sqlite ya vienen en la imagen)
COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions gd zip opcache

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Apache: servir /public, permitir el .htaccess de Laravel y escuchar en el puerto de Render
ENV PORT=10000
RUN a2enmod rewrite \
 && sed -i 's#/var/www/html#/var/www/html/public#g' /etc/apache2/sites-available/000-default.conf \
 && sed -i 's/Listen 80/Listen ${PORT}/' /etc/apache2/ports.conf \
 && sed -i 's/<VirtualHost \*:80>/<VirtualHost *:${PORT}>/' /etc/apache2/sites-available/000-default.conf \
 && printf '<Directory /var/www/html/public>\n    AllowOverride All\n    Require all granted\n</Directory>\n' > /etc/apache2/conf-available/laravel.conf \
 && a2enconf laravel

COPY docker/php.ini /usr/local/etc/php/conf.d/app.ini

WORKDIR /var/www/html

# Primero las dependencias, para que Docker las reutilice si solo cambia el código
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --no-interaction --prefer-dist

COPY . .
RUN composer dump-autoload --optimize --no-dev \
 && sed -i 's/\r$//' docker/entrypoint.sh \
 && chmod +x docker/entrypoint.sh \
 && chown -R www-data:www-data storage bootstrap/cache database

ENTRYPOINT ["/var/www/html/docker/entrypoint.sh"]

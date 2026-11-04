# CampusFind production image: PHP 8.3 + Apache, serving only public/.
FROM php:8.3-apache

# PHP extensions used by the app (database, image uploads, photo rotation)
RUN apt-get update \
 && apt-get install -y --no-install-recommends libjpeg62-turbo-dev libpng-dev libwebp-dev \
 && docker-php-ext-configure gd --with-jpeg --with-webp \
 && docker-php-ext-install -j"$(nproc)" gd pdo_mysql exif \
 && rm -rf /var/lib/apt/lists/*

# Production PHP settings (errors are logged, not shown)
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

# Apache: web root = public/, allow public/.htaccess (clean URLs), hide version
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
 && sed -ri 's!AllowOverride None!AllowOverride All!g' /etc/apache2/apache2.conf \
 && a2enmod rewrite headers \
 && printf 'ServerTokens Prod\nServerSignature Off\n' > /etc/apache2/conf-enabled/zz-campusfind.conf

COPY . /var/www/html
RUN chmod +x /var/www/html/docker/start.sh

CMD ["/var/www/html/docker/start.sh"]

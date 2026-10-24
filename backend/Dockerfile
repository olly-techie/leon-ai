FROM php:8.3-apache

RUN apt-get update && apt-get install -y libpq-dev libzip-dev unzip \
    && docker-php-ext-install pdo_pgsql zip \
    && rm -rf /var/lib/apt/lists/*

RUN a2enmod rewrite

# Report PDFs exceed PHP defaults (2M upload / 128M memory)
RUN { \
      echo 'upload_max_filesize = 20M'; \
      echo 'post_max_size = 25M'; \
      echo 'memory_limit = 512M'; \
      echo 'max_execution_time = 120'; \
    } > /usr/local/etc/php/conf.d/uploads.ini

COPY composer.json composer.lock* /tmp/
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer \
    && cd /tmp && composer install --no-dev --no-interaction

COPY . /var/www/html/
RUN cp -r /tmp/vendor /var/www/html/vendor && rm -rf /tmp/*

# Point DocumentRoot at public/
RUN sed -ri -e 's!/var/www/html!/var/www/html/public!g' \
    /etc/apache2/sites-available/000-default.conf \
    /etc/apache2/apache2.conf \
    /etc/apache2/conf-available/security.conf

# Allow .htaccess rewrites
RUN sed -ri -e 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf

ENV PORT=10000
EXPOSE ${PORT}

COPY docker-entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh
ENTRYPOINT ["entrypoint.sh"]

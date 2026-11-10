# Production image (used by Render). Local development uses docker-compose.yml.
FROM php:8.2-apache

RUN docker-php-ext-install pdo_mysql opcache \
 && a2enmod rewrite headers

RUN { \
      echo 'expose_php=Off'; \
      echo 'display_errors=Off'; \
      echo 'log_errors=On'; \
      echo 'error_log=/dev/stderr'; \
      echo 'upload_max_filesize=8M'; \
      echo 'post_max_size=10M'; \
      echo 'session.use_strict_mode=1'; \
      echo 'session.use_only_cookies=1'; \
      echo 'session.cookie_httponly=1'; \
      echo 'session.cookie_samesite=Lax'; \
      echo 'date.timezone=Africa/Johannesburg'; \
      echo 'opcache.enable=1'; \
      echo 'opcache.validate_timestamps=0'; \
    } > /usr/local/etc/php/conf.d/sentra.ini

COPY docker/php/vhost.conf /etc/apache2/sites-available/000-default.conf
COPY docker/render-start.sh /usr/local/bin/render-start
COPY . /var/www/sentra

RUN chmod +x /usr/local/bin/render-start \
 && rm -f /var/www/sentra/.env \
 && mkdir -p /var/www/sentra/storage/logs /var/www/sentra/public/uploads \
 && chown -R www-data:www-data /var/www/sentra/storage /var/www/sentra/public/uploads

WORKDIR /var/www/sentra
ENV PORT=10000
EXPOSE 10000
CMD ["render-start"]

FROM php:8.2-apache

RUN docker-php-ext-install pdo_mysql \
 && a2enmod rewrite headers

# Production-leaning PHP settings
RUN { \
      echo 'expose_php=Off'; \
      echo 'display_errors=Off'; \
      echo 'log_errors=On'; \
      echo 'upload_max_filesize=8M'; \
      echo 'post_max_size=10M'; \
      echo 'session.use_strict_mode=1'; \
      echo 'session.use_only_cookies=1'; \
      echo 'session.cookie_httponly=1'; \
      echo 'session.cookie_samesite=Lax'; \
      echo 'date.timezone=Africa/Johannesburg'; \
    } > /usr/local/etc/php/conf.d/sentra.ini

COPY vhost.conf /etc/apache2/sites-available/000-default.conf

WORKDIR /var/www/sentra

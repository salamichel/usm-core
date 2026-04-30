FROM php:8.2-apache

# Extensions PHP
RUN docker-php-ext-install pdo_mysql

# Activer mod_rewrite
RUN a2enmod rewrite

# Aligner les limites d'upload PHP sur la taille max acceptée par Photo::uploadSingle (10 Mo)
COPY docker/uploads.ini /usr/local/etc/php/conf.d/uploads.ini

# Configurer le VirtualHost : document root = /var/www/html/public
RUN echo '<VirtualHost *:80>\n\
  DocumentRoot /var/www/html/public\n\
  <Directory /var/www/html/public>\n\
    Options Indexes FollowSymLinks\n\
    AllowOverride All\n\
    Require all granted\n\
  </Directory>\n\
</VirtualHost>' > /etc/apache2/sites-available/000-default.conf

WORKDIR /var/www/html

COPY . .

RUN rm -rf .git .gitignore && \
    mkdir -p public/assets/uploads logs && \
    chown -R www-data:www-data public/assets/uploads logs

EXPOSE 80

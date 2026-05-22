FROM php:8.2-apache

# Installer les dépendances système
RUN apt-get update && apt-get install -y \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    libpng-dev \
    libwebp-dev \
    libzip-dev \
    zip \
    unzip \
    && rm -rf /var/lib/apt/lists/*

# Configurer et installer les extensions PHP
RUN docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql gd zip

# Activer mod_rewrite pour Apache
RUN a2enmod rewrite

# Configurer le DocumentRoot d'Apache vers le dossier public/
ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Configurer les limites d'upload
COPY ./docker/uploads.ini /usr/local/etc/php/conf.d/uploads.ini

# Définir le répertoire de travail
WORKDIR /var/www/html

# Copier les fichiers du projet (en respectant .dockerignore)
COPY . .

# Créer les répertoires nécessaires et gérer les permissions
# On utilise l'utilisateur www-data d'Apache pour la sécurité
RUN mkdir -p public/assets/uploads logs \
    && chown -R www-data:www-data /var/www/html

# Utiliser l'utilisateur non-root pour les processus Apache si possible
# Note: Sur l'image officielle php:apache, Apache démarre en root pour se binder au port 80
# mais bascule ensuite ses workers vers www-data.
# Pour une sécurité maximale, on s'assure que les dossiers d'écriture appartiennent à www-data.

EXPOSE 80

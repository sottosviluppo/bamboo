FROM php:7.2-apache

RUN a2enmod rewrite

RUN sed -i 's/deb.debian.org/archive.debian.org/g' /etc/apt/sources.list && \
    sed -i '/security.debian.org/d' /etc/apt/sources.list && \
    echo 'Acquire::Check-Valid-Until "false";' > /etc/apt/apt.conf.d/99no-check-valid-until

# Install dependencies
RUN set -ex && apt-get update && apt-get install -y \
        git \
        unzip \
        libfreetype6-dev \
        libjpeg62-turbo-dev \
        libpng-dev \
        libicu-dev \
        libcurl4-openssl-dev \
        libxml2-dev \
        libzip-dev \
        libxslt1-dev \
        zlib1g-dev \
        pkg-config \
        libreadline-dev \
        libssl-dev \
        libedit-dev \
    && docker-php-ext-configure gd \
        --with-freetype-dir=/usr/include/ \
        --with-jpeg-dir=/usr/include/ \
        --with-png-dir=/usr/include/ \
    && docker-php-ext-install -j$(nproc) gd pdo pdo_mysql intl mbstring curl xml zip bcmath soap \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Composer v1 install (specifico)
RUN curl -sS https://getcomposer.org/installer | php -- --version=1.10.27 --install-dir=/usr/local/bin --filename=composer

# Set timezone
RUN rm /etc/localtime && \
    ln -s /usr/share/zoneinfo/Europe/Rome /etc/localtime

WORKDIR /var/www/bamboo

# Fix permessi: www-data (UID 33) è l'utente di Apache
# Creiamo le cartelle critiche di Symfony e le assegniamo a www-data.
# I permessi vengono applicati all'avvio tramite entrypoint, perché
# le cartelle sono sovrascritte dal bind mount del volume dopo il build.
COPY docker-compose/php/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["apache2-foreground"]

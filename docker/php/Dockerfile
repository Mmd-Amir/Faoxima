FROM php:8.2-fpm

ARG PUID=33
ARG PGID=33

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        libzip-dev \
        libpng-dev \
        libjpeg-dev \
        libfreetype6-dev \
        libcurl4-openssl-dev \
        libxml2-dev \
        libssh2-1-dev \
        libssh2-1 \
        libonig-dev \
        openssl \
        unzip \
        default-mysql-client \
        cron \
    && docker-php-ext-configure gd --with-jpeg --with-freetype \
    && docker-php-ext-install -j"$(nproc)" mysqli pdo_mysql mbstring zip gd curl soap \
    && (pecl install ssh2-1.3.1 && docker-php-ext-enable ssh2 || echo "ssh2 extension build failed or unsupported; continuing without it") \
    && (pecl install redis && docker-php-ext-enable redis || echo "redis extension build failed or unsupported; continuing without it") \
    && rm -rf /var/lib/apt/lists/*

RUN groupmod -o -g "${PGID}" www-data \
    && usermod -o -u "${PUID}" -g "${PGID}" www-data

COPY docker/php/conf.d/faoxima.ini /usr/local/etc/php/conf.d/faoxima.ini

WORKDIR /var/www/faoxima

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["php-fpm"]

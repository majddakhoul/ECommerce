FROM php:8.3-cli

RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip libzip-dev libonig-dev \
    && docker-php-ext-install pdo_mysql zip mbstring \
    && rm -rf /var/lib/apt/lists/*

COPY --from=ghcr.io/mlocati/php-extension-installer:latest /usr/bin/install-php-extensions /usr/local/bin/
ENV IPE_PROCESSOR_COUNT=3
ENV MAKEFLAGS=-j3
RUN for attempt in 1 2 3; do install-php-extensions grpc && exit 0; echo "grpc install failed (attempt $attempt), retrying"; sleep 10; done; exit 1

RUN install-php-extensions redis pcntl sockets

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY composer.json composer.loc[k] ./
RUN composer install --no-interaction --no-scripts --prefer-dist --no-dev --optimize-autoloader

COPY . .
RUN composer dump-autoload --optimize --no-dev \
    && mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs storage/app/public bootstrap/cache \
    && chmod -R ug+rwx storage bootstrap/cache \
    && sed -i 's/\r$//' docker/entrypoint.sh \
    && chmod +x docker/entrypoint.sh

EXPOSE 8000

ENTRYPOINT ["docker/entrypoint.sh"]

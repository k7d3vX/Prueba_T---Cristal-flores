FROM php:8.3-cli

RUN apt-get update \
 && apt-get install -y --no-install-recommends git unzip libpq-dev libzip-dev \
 && docker-php-ext-install pdo_pgsql zip \
 && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

# Dependencias primero (aprovecha la caché de Docker)
COPY composer.json ./
RUN composer install --no-interaction --prefer-dist --no-scripts --no-autoloader

COPY . .
RUN composer dump-autoload --no-interaction \
 && chmod +x docker/entrypoint.sh

EXPOSE 8000
ENTRYPOINT ["bash", "docker/entrypoint.sh"]

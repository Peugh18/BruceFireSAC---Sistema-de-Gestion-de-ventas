# Imagen del sistema: PHP 8.4 con FrankenPHP (HTTPS automático, sin mod_php).
# Se construye en el servidor con `docker compose build` (lo hace instalar.sh).

# Base con las extensiones que pide el sistema (SUNAT: soap y xsl; PDF: gd).
FROM dunglas/frankenphp:1-php8.4-bookworm AS base
RUN install-php-extensions pdo_mysql gd intl zip bcmath soap xsl exif opcache pcntl \
    && apt-get update && apt-get install -y --no-install-recommends default-mysql-client \
    && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /app

# Compilación: dependencias de PHP y pantallas (Node solo aquí, no en la imagen final).
FROM base AS compilar
COPY --from=node:22-bookworm-slim /usr/local/ /usr/local/
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-interaction --prefer-dist --no-autoloader
COPY package.json package-lock.json .npmrc ./
RUN npm ci --no-audit --no-fund
COPY . .
RUN composer dump-autoload --optimize --no-dev \
    && cp .env.example .env && php artisan key:generate --force \
    && npm run build \
    && rm -rf node_modules .env

# Imagen final: solo PHP, el código y las pantallas ya compiladas.
FROM base
COPY --from=compilar /app /app
COPY despliegue/php.ini /usr/local/etc/php/conf.d/brucefire.ini
RUN mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs storage/app/backups bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache
ENV SERVER_NAME=:80

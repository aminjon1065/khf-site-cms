# syntax=docker/dockerfile:1.7
#
# CMS КЧС для одного сервера в Docker (deploy/docker/compose.yml).
#
# Таргет `app` — PHP-FPM; тот же образ запускает воркеры очередей и
# планировщик. Таргет `web` — nginx со статикой (public/) того же релиза.
# Контекст сборки — корень репозитория CMS.

ARG PHP_IMAGE=php:8.5-fpm-trixie
ARG NODE_IMAGE=node:22-trixie-slim
ARG MYSQL_IMAGE=mysql:8.4
ARG NGINX_IMAGE=nginx:1.28-alpine
ARG COMPOSER_IMAGE=composer:2
ARG EXTENSION_INSTALLER_IMAGE=mlocati/php-extension-installer:2

FROM ${MYSQL_IMAGE} AS mysql
FROM ${NODE_IMAGE} AS node
FROM ${COMPOSER_IMAGE} AS composer
FROM ${EXTENSION_INSTALLER_IMAGE} AS extension-installer

FROM ${PHP_IMAGE} AS base
COPY --from=extension-installer /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions bcmath exif gd intl opcache pcntl pdo_mysql redis zip
# avifenc — AVIF-производные (AvifDerivativeGenerator); остальное —
# оптимизаторы spatie/image-optimizer (config/media-library.php).
# libncurses6 нужна клиенту `mysql` из образа MySQL (см. ниже).
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        gifsicle jpegoptim libavif-bin libncurses6 optipng pngquant webp \
    && rm -rf /var/lib/apt/lists/*
# Резервная копия (ops:backup) и контрольное восстановление (ops:restore-drill)
# зовут mysqldump/mysql. В Debian под этими именами клиент MariaDB, а он не
# проходит аутентификацию MySQL 8 (caching_sha2_password) — берём
# официальные клиенты из образа той же версии, что и сервер.
COPY --from=mysql /usr/bin/mysqldump /usr/bin/mysql /usr/local/bin/
COPY deploy/docker/cms/php.ini "$PHP_INI_DIR/conf.d/zz-khf.ini"
COPY deploy/docker/cms/php-fpm.conf /usr/local/etc/php-fpm.d/zz-khf.conf
WORKDIR /var/www/html

FROM base AS build
COPY --from=composer /usr/bin/composer /usr/local/bin/composer
COPY --from=node /usr/local/bin/node /usr/local/bin/node
COPY --from=node /usr/local/lib/node_modules /usr/local/lib/node_modules
RUN ln -s ../lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction
COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY . .
# Wayfinder (часть `npm run build`) генерирует TS-маршруты через artisan,
# поэтому автозагрузчик собирается раньше ассетов. Ссылка public/storage —
# относительная: она же работает в nginx-образе с тем же томом storage.
RUN composer dump-autoload --classmap-authoritative --no-dev \
    && php artisan package:discover --ansi \
    && npm run build \
    && rm -rf node_modules \
    && ln -sfn ../storage/app/public public/storage \
    && mkdir -p storage/app/public storage/app/private storage/app/content-private \
        storage/app/purifier storage/framework/cache/data storage/framework/sessions \
        storage/framework/views storage/logs \
    && chown -R www-data:www-data storage bootstrap/cache

FROM base AS app
COPY --from=build /var/www/html /var/www/html
COPY deploy/docker/cms/entrypoint.sh /usr/local/bin/khf-entrypoint
ENTRYPOINT ["khf-entrypoint"]
CMD ["php-fpm"]

FROM ${NGINX_IMAGE} AS web
COPY deploy/docker/cms/nginx.conf /etc/nginx/conf.d/default.conf
COPY --from=build /var/www/html/public /var/www/html/public

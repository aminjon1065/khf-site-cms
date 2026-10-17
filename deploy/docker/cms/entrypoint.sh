#!/bin/sh
# Конфигурация Laravel собирается из окружения контейнера при старте: при
# сборке образа окружения ещё нет. Кэш маршрутов, событий и шаблонов —
# тот же `optimize`, что в обычном деплое (DEPLOYMENT.md §4).
#
# Всё, кроме самого PHP-FPM (его мастер-процесс запускается от root и сам
# опускает воркеры до www-data), выполняется от www-data. Иначе разовая
# команда вроде `docker compose run cms-app php artisan db:seed` создавала
# каталоги медиатеки от root, и воркер очереди потом не мог писать в них
# производные изображения.
set -e

if [ "$(id -u)" != "0" ]; then
    php artisan optimize --no-interaction > /dev/null
    exec "$@"
fi

runuser -u www-data -- php artisan optimize --no-interaction > /dev/null

if [ "$1" = "php-fpm" ]; then
    exec "$@"
fi

exec runuser -u www-data -- "$@"

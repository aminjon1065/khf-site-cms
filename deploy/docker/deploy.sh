#!/usr/bin/env bash
#
# Развёртывание и обновление стека КЧС на одном сервере (DEPLOYMENT.md §8).
#
#   deploy/docker/deploy.sh --init   # первый запуск: + справочники в БД
#   deploy/docker/deploy.sh          # обновление на текущие коммиты
#
# Порядок важен: сайт пререндерит страницы с данными CMS, поэтому его образ
# собирается, когда CMS уже отвечает по своему домену. Пока собирается новый
# образ, работает старый контейнер; простой — секунды на пересоздание.
#
# Откат кода: RELEASE=<тег из `docker image ls khf-cms`> docker compose up -d
# (схему БД откат не возвращает — миграции пишутся совместимыми с прошлым
# релизом, DEPLOYMENT.md §4).

set -euo pipefail

cd "$(dirname "$0")"

INIT=0
for arg in "$@"; do
    case "$arg" in
        --init) INIT=1 ;;
        *) echo "Неизвестный параметр: $arg" >&2; exit 2 ;;
    esac
done

if [ ! -f .env ]; then
    echo "Нет deploy/docker/.env: cp env.example .env и заполните его." >&2
    exit 1
fi

set -a
# shellcheck disable=SC1091
. ./.env
set +a

FRONT_SRC=${FRONT_SRC:-../../../khf-site-front}
BACKUP_DIR=${BACKUP_DIR:-/srv/khf/backups}

# Скрипт запускают через sudo, а репозитории принадлежат обычному
# пользователю: без safe.directory git откажется их читать (dubious ownership).
rev() { git -c safe.directory='*' -C "$1" rev-parse --short HEAD; }
RELEASE="$(rev ../..)-$(rev "$FRONT_SRC")"
export RELEASE
echo "Релиз $RELEASE (CMS-сайт)"

# Каталог копий принадлежит www-data образа PHP (uid 33): копии пишет
# планировщик, работающий от этого пользователя.
mkdir -p "$BACKUP_DIR"
chown 33:33 "$BACKUP_DIR"
chmod 750 "$BACKUP_DIR"

echo "→ Сборка образов CMS"
docker compose build cms-app cms-web

echo "→ MySQL и Redis"
docker compose up -d --wait mysql redis

echo "→ Миграции"
docker compose run --rm --no-deps cms-app php artisan migrate --force

if [ "$INIT" = 1 ]; then
    # В production сеются только справочники: роли, регионы, настройки,
    # меню, страницы. Демо-учётки и тестовые материалы — никогда.
    echo "→ Справочники"
    docker compose run --rm --no-deps cms-app php artisan db:seed --force
fi

echo "→ CMS, воркеры, планировщик, HTTPS"
docker compose up -d --wait cms-app cms-web cms-worker cms-worker-media \
    cms-worker-db cms-worker-db-media cms-scheduler caddy

echo "→ Проверка CMS по её домену (DNS и сертификат)"
for attempt in $(seq 1 30); do
    if curl -fsS "https://${CMS_DOMAIN}/api/v1/health" > /dev/null 2>&1; then
        break
    fi
    if [ "$attempt" = 30 ]; then
        echo "https://${CMS_DOMAIN}/api/v1/health не отвечает. Проверьте, что DNS" >&2
        echo "указывает на этот сервер, а порты 80/443 открыты: docker compose logs caddy" >&2
        exit 1
    fi
    sleep 5
done

# Сборка сайта требует готовую CMS (/ready = 200): на свежем стеке первую
# минуту он отвечает 503 — планировщик и воркер ещё не отметились, и сборка
# упала бы на первом же запуске.
echo "→ Ожидание готовности CMS (планировщик и очередь)"
for attempt in $(seq 1 36); do
    if curl -fsS "https://${CMS_DOMAIN}/api/v1/ready" > /dev/null 2>&1; then
        break
    fi
    if [ "$attempt" = 36 ]; then
        echo "https://${CMS_DOMAIN}/api/v1/ready не отвечает 200 за 3 минуты:" >&2
        echo "  docker compose logs cms-scheduler cms-worker" >&2
        exit 1
    fi
    sleep 5
done

echo "→ Сборка сайта (пререндер с данными CMS)"
docker compose build front

echo "→ Сайт"
docker compose up -d --wait front

echo "→ Проверка конфигурации"
docker compose exec -T -u www-data cms-app php artisan ops:production-check

docker compose ps
echo "Готово: https://${SITE_DOMAIN} и https://${CMS_DOMAIN}"
if [ "$INIT" = 1 ]; then
    echo "Первый администратор:"
    echo "  docker compose exec -u www-data cms-app php artisan cms:create-admin --email=… --name=…"
fi

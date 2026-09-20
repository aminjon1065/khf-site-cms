#!/usr/bin/env bash
# Атомарный релиз CMS (DEPLOYMENT.md §4, audit J-3): сборка в новом каталоге
# releases/<timestamp> и переключение symlink current. Прежний релиз остаётся
# как кандидат отката; работающий код не меняется до переключения.
#
# Использование: sudo ./deploy/deploy-cms.sh
# Настройка (переопределяются переменными окружения):
#   APP_BASE       базовый каталог           (по умолч. /var/www/khf-site-cms)
#   REPO_URL       git-репозиторий           (обязателен при первом запуске)
#   BRANCH         ветка                     (по умолчанию main)
#   PHP_FPM_SERVICE  systemd-юнит PHP-FPM    (по умолчанию php8.5-fpm)
#   RAM_MB         бюджет RAM для ops:capacity (по умолчанию 4096)
#   KEEP_RELEASES  сколько релизов хранить  (по умолчанию 5)
#   DRY_RUN=1      печатать шаги, не выполнять
set -euo pipefail

APP_BASE="${APP_BASE:-/var/www/khf-site-cms}"
REPO_URL="${REPO_URL:-}"
BRANCH="${BRANCH:-main}"
PHP_FPM_SERVICE="${PHP_FPM_SERVICE:-php8.5-fpm}"
RAM_MB="${RAM_MB:-4096}"
KEEP_RELEASES="${KEEP_RELEASES:-5}"
DRY_RUN="${DRY_RUN:-0}"

log()  { printf '\033[1;34m[deploy]\033[0m %s\n' "$*"; }
step() { printf '\033[1;32m==>\033[0m %s\n' "$*"; }
die()  { printf '\033[1;31m[deploy] ОШИБКА:\033[0m %s\n' "$*" >&2; exit 1; }

run() {
  if [[ "$DRY_RUN" == "1" ]]; then
    printf '  [dry-run] %s\n' "$*"
  else
    "$@"
  fi
}
run_in() { # run_in <каталог> <команда...>
  local dir="$1"; shift
  if [[ "$DRY_RUN" == "1" ]]; then
    printf '  [dry-run] (cd %s) %s\n' "$dir" "$*"
  else
    (cd "$dir" && "$@")
  fi
}

[[ $EUID -eq 0 ]] || die "запустите от root (нужны systemctl и права на $APP_BASE)"
[[ -n "$REPO_URL" ]] || die "задайте REPO_URL (git-адрес репозитория), например: REPO_URL=git@github.com:…/khf-site-cms.git $0"

RELEASES="$APP_BASE/releases"
SHARED="$APP_BASE/shared"
RELEASE="$RELEASES/$(date +%Y%m%d%H%M%S)"

[[ -f "$SHARED/.env" ]] || die "нет $SHARED/.env — первичная установка по DEPLOYMENT.md §2.2"

step "Релиз $RELEASE"
run mkdir -p "$RELEASES" "$SHARED"
run git clone --depth 1 --branch "$BRANCH" "$REPO_URL" "$RELEASE"

step "Связка shared-ресурсов (.env, storage)"
run ln -s "$SHARED/.env" "$RELEASE/.env"
run rm -rf "$RELEASE/storage"
run ln -s "$SHARED/storage" "$RELEASE/storage"

step "Зависимости PHP"
# --classmap-authoritative: автозагрузчик перестаёт искать классы по файловой
# системе, что и требуется в неизменяемом релизе (DEPLOYMENT §4).
run_in "$RELEASE" composer install --no-dev --prefer-dist --classmap-authoritative --no-interaction

step "Сборка админ-интерфейса"
run_in "$RELEASE" npm ci
run_in "$RELEASE" npm run build

step "Миграции (expand-contract: новый релиз обязан работать со старой схемой)"
run_in "$RELEASE" php artisan migrate --force

step "Оптимизация и проверки релиза"
run_in "$RELEASE" php artisan optimize
run_in "$RELEASE" php artisan ops:capacity --ram="$RAM_MB"
run_in "$RELEASE" php artisan ops:production-check

step "Переключение current и перезапуск рантайма"
run ln -sfn "$RELEASE" "$APP_BASE/current"
run systemctl reload "$PHP_FPM_SERVICE"
run_in "$APP_BASE/current" php artisan queue:restart

step "Чистка старых релизов (хранится $KEEP_RELEASES)"
if [[ "$DRY_RUN" != "1" ]]; then
  current_name=$(basename "$(readlink "$APP_BASE/current")")
  (cd "$RELEASES" && find . -mindepth 1 -maxdepth 1 | sed 's|^\./||' | sort -r | tail -n +$((KEEP_RELEASES + 1)) | while read -r old; do
    [[ "$old" == "$current_name" ]] && continue
    log "удаляю $old"
    rm -rf "${RELEASES:?}/${old:?}"
  done)
fi

log "Готово. Проверка после развёртывания — DEPLOYMENT.md §5 (health/ready, вход, ревалидация)."

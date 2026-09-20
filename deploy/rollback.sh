#!/usr/bin/env bash
# Откат релиза (DEPLOYMENT.md §4, audit J-3): переключение current на
# предыдущий (или указанный) каталог релиза без сборки. Откат кода
# мгновенный; откат схемы БД — нет: миграции обязаны быть совместимы
# сверху вниз (expand-contract), иначе «мгновенный откат» существует
# только на бумаге.
#
# Использование:
#   ./deploy/rollback.sh cms                 # предыдущий релиз CMS
#   ./deploy/rollback.sh front               # предыдущий релиз сайта
#   ./deploy/rollback.sh cms 20260920180000  # конкретный релиз
set -euo pipefail

die() { printf '\033[1;31m[rollback] ОШИБКА:\033[0m %s\n' "$*" >&2; exit 1; }

[[ $# -ge 1 ]] || die "укажите приложение: $0 cms|front [release]"

case "$1" in
  cms)   APP_BASE="${APP_BASE:-/var/www/khf-site-cms}";   RELOAD=(systemctl reload php8.5-fpm) ;;
  front) APP_BASE="${APP_BASE:-/var/www/khf-site-front}"; RELOAD=(systemctl restart khf-front) ;;
  *) die "неизвестное приложение «$1» (ожидалось cms|front)" ;;
esac
TARGET_RELEASE="${2:-}"
PHP_FPM_SERVICE="${PHP_FPM_SERVICE:-php8.5-fpm}"
[[ $EUID -eq 0 ]] || die "запустите от root (нужен systemctl)"
[[ $1 == cms ]] && RELOAD=(systemctl reload "$PHP_FPM_SERVICE")

CURRENT_LINK="$APP_BASE/current"
RELEASES="$APP_BASE/releases"

[[ -L "$CURRENT_LINK" ]] || die "$CURRENT_LINK — не symlink; откат работает только по релизной схеме"
CURRENT=$(basename "$(readlink "$CURRENT_LINK")")

if [[ -n "$TARGET_RELEASE" ]]; then
  TARGET="$TARGET_RELEASE"
  [[ -d "$RELEASES/$TARGET" ]] || die "релиз $TARGET не найден в $RELEASES"
else
  # Предыдущий по времени релиз раньше текущего.
  TARGET=$(cd "$RELEASES" && find . -mindepth 1 -maxdepth 1 | sed 's|^\./||' | sort | awk -v cur="$CURRENT" '$0 < cur' | tail -1)
  [[ -n "$TARGET" ]] || die "релизов старше текущего ($CURRENT) нет — откатывать не на что"
fi

printf 'Откат: %s -> %s\n' "$CURRENT" "$TARGET"
ln -sfn "$RELEASES/$TARGET" "$CURRENT_LINK"

"${RELOAD[@]}"

if [[ "$1" == cms ]]; then
  # Воркеры обязаны увидеть откаченный код до конца текущей задачи.
  (cd "$CURRENT_LINK" && php artisan queue:restart)
fi

printf 'Готово. Проверка — DEPLOYMENT.md §5 (health/ready, ключевые страницы).\n'

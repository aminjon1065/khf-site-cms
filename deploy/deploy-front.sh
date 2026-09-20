#!/usr/bin/env bash
# Атомарный релиз публичного сайта (DEPLOYMENT.md §4, audit J-3): сборка в
# новом каталоге и переключение symlink current + restart systemd-юнита.
#
# `npm run build` проверяет готовность CMS и падает до компиляции, если та
# не отвечает (fail-fast против пустого релиза) — поэтому сначала убедитесь,
# что CMS и её очередь/планировщик живы.
#
# Использование: sudo ./deploy/deploy-front.sh
# Настройка (переопределяются переменными окружения):
#   APP_BASE       базовый каталог           (по умолч. /var/www/khf-site-front)
#   REPO_URL       git-репозиторий           (обязателен при первом запуске)
#   BRANCH         ветка                     (по умолчанию main)
#   SERVICE        systemd-юнит              (по умолчанию khf-front)
#   KEEP_RELEASES  сколько релизов хранить  (по умолчанию 5)
#   DRY_RUN=1      печатать шаги, не выполнять
set -euo pipefail

APP_BASE="${APP_BASE:-/var/www/khf-site-front}"
REPO_URL="${REPO_URL:-}"
BRANCH="${BRANCH:-main}"
SERVICE="${SERVICE:-khf-front}"
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
run_in() {
  local dir="$1"; shift
  if [[ "$DRY_RUN" == "1" ]]; then
    printf '  [dry-run] (cd %s) %s\n' "$dir" "$*"
  else
    (cd "$dir" && "$@")
  fi
}

[[ $EUID -eq 0 ]] || die "запустите от root (нужны systemctl и права на $APP_BASE)"
[[ -n "$REPO_URL" ]] || die "задайте REPO_URL, например: REPO_URL=git@github.com:…/khf-site-front.git $0"

RELEASES="$APP_BASE/releases"
SHARED="$APP_BASE/shared"
RELEASE="$RELEASES/$(date +%Y%m%d%H%M%S)"

# Next.js при NODE_ENV=production читает .env.production (DEPLOYMENT §3.1/§4 —
# файл назван одинаково в обоих местах).
[[ -f "$SHARED/.env.production" ]] || die "нет $SHARED/.env.production — DEPLOYMENT.md §3.1"

step "Релиз $RELEASE"
run mkdir -p "$RELEASES" "$SHARED"
run git clone --depth 1 --branch "$BRANCH" "$REPO_URL" "$RELEASE"
run ln -s "$SHARED/.env.production" "$RELEASE/.env.production"

step "Сборка (включает fail-fast проверку готовности CMS)"
run_in "$RELEASE" npm ci
run_in "$RELEASE" npm run build

step "Переключение current и рестарт юнита"
# restart, а не reload: у Node нет SIGHUP-обработчика, reload на юните без
# ExecReload просто падает (см. deploy/systemd/khf-front.service).
run ln -sfn "$RELEASE" "$APP_BASE/current"
run systemctl restart "$SERVICE"

step "Чистка старых релизов (хранится $KEEP_RELEASES)"
if [[ "$DRY_RUN" != "1" ]]; then
  current_name=$(basename "$(readlink "$APP_BASE/current")")
  (cd "$RELEASES" && find . -mindepth 1 -maxdepth 1 | sed 's|^\./||' | sort -r | tail -n +$((KEEP_RELEASES + 1)) | while read -r old; do
    [[ "$old" == "$current_name" ]] && continue
    log "удаляю $old"
    rm -rf "${RELEASES:?}/${old:?}"
  done)
fi

log "Готово. Быстрая проверка: curl -sI https://khf.tj/ru | head -1 и страница /ru/news."
